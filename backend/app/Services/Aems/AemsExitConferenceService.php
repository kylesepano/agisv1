<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\ExitConference;
use App\Models\ExitConferenceAcknowledgement;
use App\Models\ExitConferenceAttachment;
use App\Models\ExitConferenceParticipant;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Implements engagement-scoped exit conference scheduling, minutes, and acknowledgement. */
class AemsExitConferenceService
{
    private const EDITABLE_STATUSES = ['SCHEDULED', 'RESCHEDULED'];

    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly RuntimeConfiguration $runtime,
        private readonly AemsNotificationService $notifications,
    ) {}

    /** @return list<array<string, mixed>> */
    public function engagements(Request $request): array
    {
        $user = $request->user();
        $query = $user->hasPermission('access.auditee_scope')
            ? AuditEngagement::query()
                ->whereHas('offices', fn ($offices) => $offices->whereKey($user->office_id))
                ->whereHas('exitConferences')
            : AuditEngagement::query()->visibleTo($user);

        return $query
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->get(['id', 'engagement_code', 'title', 'status'])
            ->map(fn (AuditEngagement $engagement): array => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    public function workspace(Request $request, AuditEngagement $engagement): array
    {
        $user = $request->user();
        if ($user->hasPermission('access.auditee_scope')) {
            $covered = $user->office_id
                && $engagement->offices()->whereKey($user->office_id)->exists();
            throw_unless($covered, new \Symfony\Component\HttpKernel\Exception\HttpException(
                403,
                'This exit conference workspace is outside your office scope.',
            ));
        } else {
            $this->access->authorizeEngagementAction(
                $user,
                $engagement,
                'aems.conference.view',
            );
        }

        $conferenceQuery = ExitConference::query()
            ->where('audit_engagement_id', $engagement->id)
            ->with($this->relations())
            ->orderByDesc('scheduled_start_at');

        if ($user->hasPermission('access.auditee_scope')) {
            $conferenceQuery->where(function ($visible) use ($user): void {
                $visible
                    ->whereHas('participants', fn ($participants) => $participants
                        ->where('user_id', $user->id)
                        ->orWhere('office_id', $user->office_id))
                    ->orWhereHas('findings', fn ($findings) => $findings
                        ->where('responsible_office_id', $user->office_id));
            });
        }

        $findings = AuditFinding::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('is_current_revision', true)
            ->whereIn('status', [
                'COMMUNICATED',
                'AWAITING_MANAGEMENT_RESPONSE',
                'UNDER_DIALOGUE',
                'FINALIZED',
            ])
            ->when(
                $user->hasPermission('access.auditee_scope'),
                fn ($query) => $query->where('responsible_office_id', $user->office_id),
            )
            ->with(['responsibleOffice', 'riskRating'])
            ->orderBy('finding_code')
            ->get()
            ->map(fn (AuditFinding $finding): array => $this->findingData($finding))
            ->all();

        $officeIds = $engagement->offices()->pluck('offices.id');
        $teamUserIds = $engagement->teamMembers()
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->pluck('user_id');
        $users = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($officeIds, $teamUserIds, $user): void {
                $query->whereIn('office_id', $officeIds)
                    ->orWhereIn('id', $teamUserIds)
                    ->orWhere('id', $user->id);
            })
            ->with('office')
            ->orderBy('name')
            ->get()
            ->map(fn (User $member): array => $this->userData($member))
            ->all();

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
            ],
            'conferences' => $conferenceQuery->get()
                ->map(fn (ExitConference $conference): array => $this->conferenceData($conference))
                ->all(),
            'references' => [
                'findings' => $findings,
                'users' => $users,
                'offices' => Office::query()
                    ->whereIn('id', $officeIds)
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'acronym'])
                    ->map(fn (Office $office): array => $this->officeData($office))
                    ->all(),
            ],
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function create(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): ExitConference {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.conference.manage',
        );
        $this->ensureEngagementState($engagement);
        $this->validateSchedule($attributes);
        $this->validateFindingIds($engagement, $attributes['findingIds']);

        return DB::transaction(function () use ($request, $engagement, $attributes): ExitConference {
            $conference = ExitConference::query()->create([
                'audit_engagement_id' => $engagement->id,
                'conference_code' => $this->nextCode($engagement),
                ...$this->scheduleAttributes($attributes),
                'status' => 'SCHEDULED',
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->syncFindings($conference, $attributes['findingIds']);
            $this->syncParticipants($conference, $engagement, $attributes['participants']);
            $conference = $this->load($conference);
            $this->record($request, $engagement, $conference, 'aems.conference.created', null, 'SCHEDULED');
            $this->notifications->exitConferenceScheduled(
                $request,
                $engagement,
                $conference,
                'SCHEDULED',
            );

            return $conference;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
        array $attributes,
    ): ExitConference {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.conference.manage',
        );
        $this->ensureEngagementState($engagement);
        $this->validateSchedule($attributes);
        $this->validateFindingIds($engagement, $attributes['findingIds']);

        return DB::transaction(function () use ($request, $engagement, $conference, $attributes): ExitConference {
            $conference = $this->lock($engagement, $conference, $attributes['lockVersion']);
            $this->ensureEditable($conference);
            $old = $this->conferenceAudit($conference);
            $scheduleChanged = $conference->scheduled_start_at?->toISOString()
                !== Carbon::parse($attributes['scheduledStartAt'])->toISOString()
                || ($conference->scheduled_end_at?->toISOString()
                    !== ($attributes['scheduledEndAt']
                        ? Carbon::parse($attributes['scheduledEndAt'])->toISOString()
                        : null));
            $conference->fill([
                ...$this->scheduleAttributes($attributes),
                'status' => $scheduleChanged ? 'RESCHEDULED' : $conference->status,
                'updated_by' => $request->user()->id,
                'lock_version' => $conference->lock_version + 1,
            ])->save();
            $this->syncFindings($conference, $attributes['findingIds']);
            $this->syncParticipants($conference, $engagement, $attributes['participants']);
            $conference = $this->load($conference);
            $this->record(
                $request,
                $engagement,
                $conference,
                $scheduleChanged ? 'aems.conference.rescheduled' : 'aems.conference.updated',
                $old['status'],
                $conference->status,
                $old,
            );
            if ($scheduleChanged) {
                $this->notifications->exitConferenceScheduled(
                    $request,
                    $engagement,
                    $conference,
                    'RESCHEDULED',
                );
            }

            return $conference;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function complete(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
        array $attributes,
    ): ExitConference {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.conference.manage',
        );
        $this->ensureEngagementState($engagement);

        return DB::transaction(function () use ($request, $engagement, $conference, $attributes): ExitConference {
            $conference = $this->lock($engagement, $conference, $attributes['lockVersion']);
            $this->ensureEditable($conference);
            $old = $this->conferenceAudit($conference);
            $this->recordAttendance(
                $conference,
                $attributes['participantAttendance'],
                $request->user()->id,
            );
            $this->recordFindingDiscussions($conference, $attributes['findingDiscussions']);
            $conference->load($this->relations());
            $this->validateCompletion($conference, $attributes);
            $conference->fill([
                'discussion_summary' => trim($attributes['discussionSummary']),
                'minutes' => trim($attributes['minutes']),
                'agreements' => $this->nullableTrim($attributes['agreements'] ?? null),
                'disagreements' => $this->nullableTrim($attributes['disagreements'] ?? null),
                'status' => 'COMPLETED',
                'completed_at' => now(),
                'completed_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'lock_version' => $conference->lock_version + 1,
                'completion_snapshot' => $this->completionSnapshot($conference, $attributes),
            ])->save();
            $conference = $this->load($conference);
            $this->record(
                $request,
                $engagement,
                $conference,
                'aems.conference.completed',
                $old['status'],
                'COMPLETED',
                $old,
            );

            return $conference;
        });
    }

    public function closeWithoutConference(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
        string $action,
        int $lockVersion,
        string $reason,
    ): ExitConference {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.conference.manage',
        );
        $this->ensureEngagementState($engagement);

        return DB::transaction(function () use (
            $request,
            $engagement,
            $conference,
            $action,
            $lockVersion,
            $reason,
        ): ExitConference {
            $conference = $this->lock($engagement, $conference, $lockVersion);
            $this->ensureEditable($conference);
            $old = $this->conferenceAudit($conference);
            $status = $action === 'WAIVE' ? 'WAIVED' : 'CANCELLED';
            $conference->fill([
                'status' => $status,
                'waiver_reason' => $action === 'WAIVE' ? trim($reason) : null,
                'cancellation_reason' => $action === 'CANCEL' ? trim($reason) : null,
                'updated_by' => $request->user()->id,
                'lock_version' => $conference->lock_version + 1,
            ])->save();
            $conference = $this->load($conference);
            $this->record(
                $request,
                $engagement,
                $conference,
                "aems.conference.".strtolower($status),
                $old['status'],
                $status,
                $old,
                $reason,
            );

            return $conference;
        });
    }

    public function uploadAttachment(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
        UploadedFile $file,
        string $category,
        ?string $caption,
        int $lockVersion,
    ): ExitConferenceAttachment {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.conference.manage',
        );
        $this->ensureEngagementState($engagement);
        $stored = $this->storeFile($file, $engagement);

        try {
            return DB::transaction(function () use (
                $request,
                $engagement,
                $conference,
                $stored,
                $category,
                $caption,
                $lockVersion,
            ): ExitConferenceAttachment {
                $conference = $this->lock($engagement, $conference, $lockVersion);
                $this->ensureEditable($conference);
                $version = $this->createDocument(
                    $request,
                    $engagement,
                    $conference,
                    $category,
                    $stored,
                );
                $attachment = ExitConferenceAttachment::query()->create([
                    'exit_conference_id' => $conference->id,
                    'attachment_code' => $this->nextAttachmentCode($conference),
                    'category' => $category,
                    'caption' => $this->nullableTrim($caption),
                    'document_version_id' => $version->id,
                    'uploaded_by' => $request->user()->id,
                ]);
                $conference->forceFill([
                    'minutes_document_version_id' => $category === 'MINUTES'
                        ? $version->id
                        : $conference->minutes_document_version_id,
                    'updated_by' => $request->user()->id,
                    'lock_version' => $conference->lock_version + 1,
                ])->save();
                $attachment->load(['documentVersion', 'uploader']);
                $this->record(
                    $request,
                    $engagement,
                    $conference,
                    'aems.conference.attachment_uploaded',
                    $conference->status,
                    $conference->status,
                    null,
                    $caption,
                    [$version->id],
                );

                return $attachment;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($stored['storage_path']);
            throw $exception;
        }
    }

    public function acknowledge(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
        string $status,
        ?string $comment,
        int $lockVersion,
    ): ExitConferenceAcknowledgement {
        throw_unless(
            $request->user()->hasPermission('access.auditee_scope')
                && $request->user()->hasPermission('aems.conference.acknowledge'),
            new \Symfony\Component\HttpKernel\Exception\HttpException(
                403,
                'Only an authorized Auditee Representative can acknowledge conference minutes.',
            ),
        );
        $this->access->authorizeConferenceView($request->user(), $conference);

        return DB::transaction(function () use (
            $request,
            $engagement,
            $conference,
            $status,
            $comment,
            $lockVersion,
        ): ExitConferenceAcknowledgement {
            $conference = $this->lock($engagement, $conference, $lockVersion);
            if ($conference->status !== 'COMPLETED') {
                throw ValidationException::withMessages([
                    'conference' => ['Only completed conference minutes can be acknowledged.'],
                ]);
            }
            $participant = ExitConferenceParticipant::query()
                ->where('exit_conference_id', $conference->id)
                ->where(function ($query) use ($request): void {
                    $query->where('user_id', $request->user()->id)
                        ->orWhere('office_id', $request->user()->office_id);
                })
                ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$request->user()->id])
                ->first();
            if (! $participant || ! $request->user()->office_id) {
                throw ValidationException::withMessages([
                    'conference' => ['You are not an invited participant in this conference.'],
                ]);
            }
            if (ExitConferenceAcknowledgement::query()
                ->where('exit_conference_id', $conference->id)
                ->where('user_id', $request->user()->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'conference' => ['You have already acknowledged these conference minutes.'],
                ]);
            }

            $acknowledgement = ExitConferenceAcknowledgement::query()->create([
                'exit_conference_id' => $conference->id,
                'exit_conference_participant_id' => $participant->id,
                'user_id' => $request->user()->id,
                'office_id' => $request->user()->office_id,
                'version_number' => 1,
                'acknowledgement_status' => $status,
                'comment' => $this->nullableTrim($comment),
                'acknowledged_at' => now(),
            ]);
            $participant->forceFill(['acknowledged_at' => now()])->save();
            $conference->forceFill([
                'acknowledged_at' => $conference->acknowledged_at ?? now(),
                'acknowledged_by' => $conference->acknowledged_by ?? $request->user()->id,
                'lock_version' => $conference->lock_version + 1,
            ])->save();
            $acknowledgement->load(['user', 'office', 'participant']);
            $this->record(
                $request,
                $engagement,
                $conference,
                'aems.conference.acknowledged',
                'COMPLETED',
                'COMPLETED',
                null,
                $comment,
            );

            return $acknowledgement;
        });
    }

    public function downloadAttachment(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
        ExitConferenceAttachment $attachment,
    ): DocumentVersion {
        $this->access->authorizeConferenceView($request->user(), $conference);
        $this->ensureConference($engagement, $conference);
        if ((int) $attachment->exit_conference_id !== (int) $conference->id) {
            throw ValidationException::withMessages([
                'attachment' => ['This attachment does not belong to the selected conference.'],
            ]);
        }
        $version = $attachment->documentVersion;
        if (! $version || ! Storage::disk('local')->exists($version->storage_path)) {
            abort(404, 'The conference attachment file is unavailable.');
        }
        $this->record(
            $request,
            $engagement,
            $conference,
            'aems.conference.attachment_downloaded',
            $conference->status,
            $conference->status,
            null,
            $attachment->attachment_code,
            [$version->id],
        );

        return $version;
    }

    /** @return array<string, mixed> */
    public function conferenceData(ExitConference $conference): array
    {
        $conference->loadMissing($this->relations());

        return [
            'id' => $conference->id,
            'conferenceCode' => $conference->conference_code,
            'scheduledStartAt' => $conference->scheduled_start_at?->toISOString(),
            'scheduledEndAt' => $conference->scheduled_end_at?->toISOString(),
            'venue' => $conference->venue,
            'meetingLink' => $conference->meeting_link,
            'onlineMeetingDetails' => $conference->online_meeting_details,
            'agenda' => $conference->agenda,
            'discussionSummary' => $conference->discussion_summary,
            'minutes' => $conference->minutes,
            'agreements' => $conference->agreements,
            'disagreements' => $conference->disagreements,
            'status' => $conference->status,
            'waiverReason' => $conference->waiver_reason,
            'cancellationReason' => $conference->cancellation_reason,
            'completionSnapshot' => $conference->completion_snapshot,
            'lockVersion' => $conference->lock_version,
            'createdBy' => $this->userData($conference->creator),
            'updatedBy' => $this->userData($conference->updater),
            'completedBy' => $this->userData($conference->completer),
            'completedAt' => $conference->completed_at?->toISOString(),
            'createdAt' => $conference->created_at?->toISOString(),
            'updatedAt' => $conference->updated_at?->toISOString(),
            'participants' => $conference->participants
                ->map(fn (ExitConferenceParticipant $participant): array => $this->participantData($participant))
                ->values()
                ->all(),
            'findings' => $conference->findings
                ->map(fn (AuditFinding $finding): array => [
                    ...$this->findingData($finding),
                    'discussion' => [
                        'sequenceNumber' => (int) $finding->pivot->sequence_number,
                        'discussionStatus' => $finding->pivot->discussion_status,
                        'agreementStatus' => $finding->pivot->agreement_status,
                        'discussionNotes' => $finding->pivot->discussion_notes,
                        'agreementDetails' => $finding->pivot->agreement_details,
                        'disagreementDetails' => $finding->pivot->disagreement_details,
                        'revisedTargetDate' => $finding->pivot->revised_target_date,
                    ],
                ])
                ->values()
                ->all(),
            'attachments' => $conference->attachments
                ->map(fn (ExitConferenceAttachment $attachment): array => $this->attachmentData($attachment))
                ->values()
                ->all(),
            'acknowledgements' => $conference->acknowledgements
                ->map(fn (ExitConferenceAcknowledgement $acknowledgement): array => $this->acknowledgementData($acknowledgement))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function attachmentData(ExitConferenceAttachment $attachment): array
    {
        $attachment->loadMissing(['documentVersion', 'uploader']);
        $version = $attachment->documentVersion;

        return [
            'id' => $attachment->id,
            'attachmentCode' => $attachment->attachment_code,
            'category' => $attachment->category,
            'caption' => $attachment->caption,
            'documentVersionId' => $attachment->document_version_id,
            'fileName' => $version?->original_file_name,
            'fileSize' => $version?->file_size,
            'mimeType' => $version?->mime_type,
            'checksumSha256' => $version?->checksum_sha256,
            'fileVersionNumber' => $version?->version_number,
            'uploadedBy' => $this->userData($attachment->uploader),
            'uploadedAt' => $attachment->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    public function acknowledgementData(ExitConferenceAcknowledgement $acknowledgement): array
    {
        $acknowledgement->loadMissing(['user', 'office']);

        return [
            'id' => $acknowledgement->id,
            'versionNumber' => $acknowledgement->version_number,
            'status' => $acknowledgement->acknowledgement_status,
            'comment' => $acknowledgement->comment,
            'actor' => $this->userData($acknowledgement->user),
            'office' => $this->officeData($acknowledgement->office),
            'acknowledgedAt' => $acknowledgement->acknowledged_at?->toISOString(),
        ];
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'creator',
            'updater',
            'completer',
            'participants.user.office',
            'participants.office',
            'participants.attendanceRecorder',
            'findings.responsibleOffice',
            'findings.riskRating',
            'attachments.documentVersion',
            'attachments.uploader',
            'acknowledgements.user',
            'acknowledgements.office',
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function scheduleAttributes(array $attributes): array
    {
        return [
            'scheduled_start_at' => $attributes['scheduledStartAt'],
            'scheduled_end_at' => $attributes['scheduledEndAt'] ?? null,
            'venue' => $this->nullableTrim($attributes['venue'] ?? null),
            'meeting_link' => $this->nullableTrim($attributes['meetingLink'] ?? null),
            'online_meeting_details' => $this->nullableTrim($attributes['onlineMeetingDetails'] ?? null),
            'agenda' => trim($attributes['agenda']),
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function validateSchedule(array $attributes): void
    {
        if (! empty($attributes['scheduledEndAt'])
            && Carbon::parse($attributes['scheduledEndAt'])->lte(Carbon::parse($attributes['scheduledStartAt']))) {
            throw ValidationException::withMessages([
                'scheduledEndAt' => ['The conference end must be after its start.'],
            ]);
        }
        if (empty($attributes['venue']) && empty($attributes['meetingLink'])
            && empty($attributes['onlineMeetingDetails'])) {
            throw ValidationException::withMessages([
                'venue' => ['Provide a venue or online meeting details.'],
            ]);
        }
    }

    private function ensureEngagementState(AuditEngagement $engagement): void
    {
        if ($engagement->status !== 'EXIT_CONFERENCE') {
            throw ValidationException::withMessages([
                'engagement' => ['Exit conferences can be scheduled only after Fieldwork and Issues & AFR are complete. Move the engagement to Exit Conference first.'],
            ]);
        }
    }

    /** @param list<int> $findingIds */
    private function validateFindingIds(AuditEngagement $engagement, array $findingIds): void
    {
        $count = AuditFinding::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('is_current_revision', true)
            ->whereIn('status', [
                'COMMUNICATED',
                'AWAITING_MANAGEMENT_RESPONSE',
                'UNDER_DIALOGUE',
                'FINALIZED',
            ])
            ->whereIn('id', $findingIds)
            ->count();
        if ($count !== count(array_unique($findingIds))) {
            throw ValidationException::withMessages([
                'findingIds' => ['Every selected Finding must be a current, formally communicated Finding from this engagement.'],
            ]);
        }
    }

    /** @param list<int> $findingIds */
    private function syncFindings(ExitConference $conference, array $findingIds): void
    {
        $links = [];
        foreach (array_values(array_unique($findingIds)) as $index => $findingId) {
            $links[$findingId] = [
                'sequence_number' => $index + 1,
                'discussion_status' => 'PENDING',
            ];
        }
        $conference->findings()->sync($links);
    }

    /** @param list<array<string, mixed>> $participants */
    private function syncParticipants(
        ExitConference $conference,
        AuditEngagement $engagement,
        array $participants,
    ): void {
        $conference->participants()->delete();
        foreach ($participants as $index => $participant) {
            $userId = $participant['userId'] ?? null;
            $externalName = $this->nullableTrim($participant['externalName'] ?? null);
            if (($userId && $externalName) || (! $userId && ! $externalName)) {
                throw ValidationException::withMessages([
                    "participants.{$index}.userId" => ['Select an internal user or provide one external participant name.'],
                ]);
            }
            $officeId = $participant['officeId'] ?? null;
            $member = null;
            if ($userId) {
                $member = User::query()->where('is_active', true)->find($userId);
                if (! $member) {
                    throw ValidationException::withMessages([
                        "participants.{$index}.userId" => ['The participant is not an active user.'],
                    ]);
                }
                $officeId ??= $member->office_id;
            }
            $isAuditParticipant = $userId
                && ($engagement->teamMembers()
                    ->where('user_id', $userId)
                    ->where('is_active', true)
                    ->whereNull('ended_at')
                    ->exists()
                    || $member?->hasPermission('aems.conference.manage'));
            if ($officeId
                && ! $engagement->offices()->whereKey($officeId)->exists()
                && ! $isAuditParticipant) {
                throw ValidationException::withMessages([
                    "participants.{$index}.officeId" => ['The participant office is outside the engagement scope.'],
                ]);
            }
            $conference->participants()->create([
                'user_id' => $userId,
                'office_id' => $officeId,
                'external_name' => $externalName,
                'external_email' => $this->nullableTrim($participant['externalEmail'] ?? null),
                'participant_role' => $participant['participantRole'],
                'attendance_status' => 'INVITED',
            ]);
        }
    }

    /** @param list<array<string, mixed>> $records */
    private function recordAttendance(
        ExitConference $conference,
        array $records,
        int $actorId,
    ): void {
        $participants = $conference->participants()->get()->keyBy('id');
        if ($participants->count() !== count($records)) {
            throw ValidationException::withMessages([
                'participantAttendance' => ['Record attendance for every invited participant.'],
            ]);
        }
        foreach ($records as $record) {
            $participant = $participants->get((int) $record['participantId']);
            if (! $participant) {
                throw ValidationException::withMessages([
                    'participantAttendance' => ['An attendance record does not belong to this conference.'],
                ]);
            }
            $participant->forceFill([
                'attendance_status' => $record['attendanceStatus'],
                'attendance_notes' => $this->nullableTrim($record['attendanceNotes'] ?? null),
                'attendance_recorded_at' => now(),
                'attendance_recorded_by' => $actorId,
            ])->save();
        }
    }

    /** @param list<array<string, mixed>> $records */
    private function recordFindingDiscussions(ExitConference $conference, array $records): void
    {
        $findings = $conference->findings()->get()->keyBy('id');
        if ($findings->count() !== count($records)) {
            throw ValidationException::withMessages([
                'findingDiscussions' => ['Record an outcome for every linked Finding.'],
            ]);
        }
        foreach ($records as $record) {
            $finding = $findings->get((int) $record['findingId']);
            if (! $finding) {
                throw ValidationException::withMessages([
                    'findingDiscussions' => ['A discussion record does not belong to this conference.'],
                ]);
            }
            $discussionStatus = $record['discussionStatus'];
            $agreementStatus = $discussionStatus === 'DISCUSSED'
                ? ($record['agreementStatus'] ?? null)
                : null;
            if ($discussionStatus === 'DISCUSSED' && ! $agreementStatus) {
                throw ValidationException::withMessages([
                    'findingDiscussions' => ['Every discussed Finding requires an agreement status.'],
                ]);
            }
            if (in_array($agreementStatus, ['PARTIALLY_AGREED', 'DISAGREED'], true)
                && empty($record['disagreementDetails'])) {
                throw ValidationException::withMessages([
                    'findingDiscussions' => ['Partial agreement and disagreement require details.'],
                ]);
            }
            $conference->findings()->updateExistingPivot($finding->id, [
                'discussion_status' => $discussionStatus,
                'agreement_status' => $agreementStatus,
                'discussion_notes' => $this->nullableTrim($record['discussionNotes'] ?? null),
                'agreement_details' => $this->nullableTrim($record['agreementDetails'] ?? null),
                'disagreement_details' => $this->nullableTrim($record['disagreementDetails'] ?? null),
                'revised_target_date' => $record['revisedTargetDate'] ?? null,
                'updated_at' => now(),
            ]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function validateCompletion(ExitConference $conference, array $attributes): void
    {
        if ($conference->participants->isEmpty()) {
            throw ValidationException::withMessages([
                'participants' => ['The conference requires at least one participant.'],
            ]);
        }
        if ($conference->findings->isEmpty()) {
            throw ValidationException::withMessages([
                'findings' => ['The conference requires at least one linked Finding.'],
            ]);
        }
        if ($conference->participants->contains(fn ($participant) => $participant->attendance_status === 'INVITED')) {
            throw ValidationException::withMessages([
                'participantAttendance' => ['Attendance remains unrecorded for an invited participant.'],
            ]);
        }
        if ($conference->findings->contains(fn ($finding) => $finding->pivot->discussion_status === 'PENDING')) {
            throw ValidationException::withMessages([
                'findingDiscussions' => ['A linked Finding still has no discussion outcome.'],
            ]);
        }
        if (trim($attributes['minutes']) === '') {
            throw ValidationException::withMessages(['minutes' => ['Conference minutes are required.']]);
        }
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function completionSnapshot(ExitConference $conference, array $attributes): array
    {
        return [
            'conferenceCode' => $conference->conference_code,
            'schedule' => [
                'startAt' => $conference->scheduled_start_at?->toISOString(),
                'endAt' => $conference->scheduled_end_at?->toISOString(),
                'venue' => $conference->venue,
                'meetingLink' => $conference->meeting_link,
                'onlineMeetingDetails' => $conference->online_meeting_details,
            ],
            'agenda' => $conference->agenda,
            'discussionSummary' => trim($attributes['discussionSummary']),
            'minutes' => trim($attributes['minutes']),
            'agreements' => $this->nullableTrim($attributes['agreements'] ?? null),
            'disagreements' => $this->nullableTrim($attributes['disagreements'] ?? null),
            'participants' => $conference->participants
                ->map(fn ($participant): array => $this->participantData($participant))
                ->values()
                ->all(),
            'findings' => $conference->findings
                ->map(fn (AuditFinding $finding): array => [
                    'id' => $finding->id,
                    'findingCode' => $finding->finding_code,
                    'title' => $finding->title,
                    'discussionStatus' => $finding->pivot->discussion_status,
                    'agreementStatus' => $finding->pivot->agreement_status,
                    'discussionNotes' => $finding->pivot->discussion_notes,
                    'agreementDetails' => $finding->pivot->agreement_details,
                    'disagreementDetails' => $finding->pivot->disagreement_details,
                    'revisedTargetDate' => $finding->pivot->revised_target_date,
                ])
                ->values()
                ->all(),
            'documentVersionIds' => $conference->attachments
                ->pluck('document_version_id')
                ->values()
                ->all(),
            'completedAt' => now()->toISOString(),
        ];
    }

    private function lock(
        AuditEngagement $engagement,
        ExitConference $conference,
        int $lockVersion,
    ): ExitConference {
        $locked = ExitConference::query()->lockForUpdate()->findOrFail($conference->id);
        $this->ensureConference($engagement, $locked);
        if ($locked->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['This conference changed in another session. Refresh before continuing.'],
            ]);
        }

        return $locked;
    }

    private function ensureConference(
        AuditEngagement $engagement,
        ExitConference $conference,
    ): void {
        if ((int) $conference->audit_engagement_id !== (int) $engagement->id) {
            throw ValidationException::withMessages([
                'conference' => ['The conference does not belong to this engagement.'],
            ]);
        }
    }

    private function ensureEditable(ExitConference $conference): void
    {
        if (! in_array($conference->status, self::EDITABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'conference' => ['Completed, cancelled, and waived conferences are locked.'],
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function storeFile(UploadedFile $file, AuditEngagement $engagement): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid().($extension ? ".{$extension}" : '');
        $path = Storage::disk('local')->putFileAs(
            "aems/engagements/{$engagement->id}/exit-conferences",
            $file,
            $storedName,
        );
        if (! $path) {
            throw ValidationException::withMessages([
                'file' => ['The conference attachment could not be stored.'],
            ]);
        }

        return [
            'original_file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_extension' => $extension ?: null,
            'file_size' => $file->getSize(),
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    /** @param array<string, mixed> $stored */
    private function createDocument(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
        string $category,
        array $stored,
    ): DocumentVersion {
        $documentType = MasterList::query()->where('code', 'DOCUMENT_TYPE')
            ->firstOrFail()->items()->where('code', 'OTHER')->firstOrFail();
        $confidentiality = MasterList::query()->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->firstOrFail()->items()->where('code', 'INTERNAL')->firstOrFail();
        $document = Document::query()->create([
            'document_type_id' => $documentType->id,
            'confidentiality_level_id' => $confidentiality->id,
            'title' => "{$conference->conference_code} — ".Str::headline(strtolower($category)),
            'description' => "Private Exit Conference record for {$engagement->engagement_code}.",
            'owner_module' => 'AEMS',
            'library_visible' => false,
            ...$stored,
            'uploaded_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'is_active' => true,
        ]);
        $document->forceFill([
            'document_code' => $this->runtime->formatNumber('document_number_format', $document->id),
        ])->save();
        $version = $document->versions()->create([
            'version_number' => 1,
            'version_label' => 'Exit Conference attachment version 1',
            'change_summary' => 'Initial immutable Exit Conference file.',
            ...$stored,
            'uploaded_by' => $request->user()->id,
        ]);
        $document->forceFill([
            'current_version_id' => $version->id,
            'version' => $version->version_label,
        ])->save();
        $document->links()->create([
            'module_code' => 'AEMS',
            'record_type' => 'EXIT_CONFERENCE',
            'record_id' => $conference->id,
            'record_code' => $conference->conference_code,
            'record_label' => "{$conference->conference_code} — Exit Conference",
            'linked_by' => $request->user()->id,
        ]);

        return $version;
    }

    private function nextCode(AuditEngagement $engagement): string
    {
        $sequence = ExitConference::query()
            ->withTrashed()
            ->where('audit_engagement_id', $engagement->id)
            ->count() + 1;
        do {
            $code = sprintf('EXIT-%s-%02d', $engagement->engagement_code, $sequence++);
        } while (ExitConference::query()->withTrashed()->where('conference_code', $code)->exists());

        return $code;
    }

    private function nextAttachmentCode(ExitConference $conference): string
    {
        $sequence = $conference->attachments()->count() + 1;
        do {
            $code = sprintf('ATT-%s-%02d', $conference->conference_code, $sequence++);
        } while (ExitConferenceAttachment::query()->where('attachment_code', $code)->exists());

        return $code;
    }

    private function load(ExitConference $conference): ExitConference
    {
        return $conference->fresh($this->relations());
    }

    /** @return array<string, mixed> */
    private function participantData(ExitConferenceParticipant $participant): array
    {
        return [
            'id' => $participant->id,
            'userId' => $participant->user_id,
            'user' => $this->userData($participant->user),
            'officeId' => $participant->office_id,
            'office' => $this->officeData($participant->office),
            'externalName' => $participant->external_name,
            'externalEmail' => $participant->external_email,
            'participantRole' => $participant->participant_role,
            'attendanceStatus' => $participant->attendance_status,
            'attendanceNotes' => $participant->attendance_notes,
            'attendanceRecordedAt' => $participant->attendance_recorded_at?->toISOString(),
            'acknowledgedAt' => $participant->acknowledged_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function findingData(AuditFinding $finding): array
    {
        return [
            'id' => $finding->id,
            'findingCode' => $finding->finding_code,
            'title' => $finding->title,
            'status' => $finding->status,
            'responsibleOffice' => $this->officeData($finding->responsibleOffice),
            'riskRating' => $finding->riskRating?->only(['id', 'code', 'label']),
        ];
    }

    /** @return array<string, mixed>|null */
    private function userData(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'officeId' => $user->office_id,
        ] : null;
    }

    /** @return array<string, mixed>|null */
    private function officeData(?Office $office): ?array
    {
        return $office ? [
            'id' => $office->id,
            'code' => $office->code,
            'name' => $office->name,
            'acronym' => $office->acronym,
        ] : null;
    }

    /** @return array<string, mixed> */
    private function conferenceAudit(ExitConference $conference): array
    {
        return [
            'id' => $conference->id,
            'conferenceCode' => $conference->conference_code,
            'status' => $conference->status,
            'scheduledStartAt' => $conference->scheduled_start_at?->toISOString(),
            'scheduledEndAt' => $conference->scheduled_end_at?->toISOString(),
            'lockVersion' => $conference->lock_version,
        ];
    }

    private function record(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?array $oldValues = null,
        ?string $comment = null,
        ?array $documentVersionIds = null,
    ): void {
        $newValues = $this->conferenceAudit($conference);
        $this->support->event(
            $request,
            $engagement,
            $action,
            $fromStatus,
            $toStatus,
            $oldValues,
            $newValues,
            $comment,
            'EXIT_CONFERENCE',
            $conference->id,
            1,
            $conference->conference_code,
            null,
            $documentVersionIds,
        );
        $this->support->audit(
            $request,
            $action,
            $engagement,
            $oldValues,
            $newValues,
            [
                'subjectType' => 'EXIT_CONFERENCE',
                'subjectId' => $conference->id,
                'subjectCode' => $conference->conference_code,
                'documentVersionIds' => $documentVersionIds,
            ],
        );
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
