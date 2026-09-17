<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\EntryConference;
use App\Models\EntryConferenceAcknowledgement;
use App\Models\EntryConferenceAgreement;
use App\Models\EntryConferenceAttachment;
use App\Models\EntryConferenceMatter;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Owns the official PGIAM Entry Conference record and controlled workflow. */
class AemsEntryConferenceService
{
    private const EDITABLE_STATUSES = [
        'DRAFT',
        'SCHEDULED',
        'RESCHEDULED',
        'HELD',
        'NOTES_FOR_ACKNOWLEDGEMENT',
        'ACKNOWLEDGED',
    ];

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
                ->whereHas('entryConference')
            : AuditEngagement::query()->visibleTo($user);

        return $query
            ->whereNull('deleted_at')
            ->with('entryConference:id,audit_engagement_id,status')
            ->orderByDesc('updated_at')
            ->get(['id', 'engagement_code', 'title', 'status'])
            ->map(fn (AuditEngagement $engagement): array => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
                'entryConferenceStatus' => $engagement->entryConference?->status,
            ])->all();
    }

    /** @return array<string, mixed> */
    public function workspace(Request $request, AuditEngagement $engagement): array
    {
        $this->access->authorizeEntryConferenceView($request->user(), $engagement);
        $conference = EntryConference::query()
            ->where('audit_engagement_id', $engagement->id)
            ->with($this->relations())
            ->first();
        $officeIds = $engagement->offices()->pluck('offices.id');
        $ciasOffice = Office::query()->where('code', 'CIAS')->first();
        $responsibleOfficeIds = $officeIds
            ->push($ciasOffice?->id)
            ->filter()
            ->unique()
            ->values();
        $teamIds = $engagement->teamMembers()
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->pluck('user_id');
        $users = User::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereIn('id', $teamIds)
                ->orWhereIn('office_id', $officeIds))
            ->with('office')
            ->orderBy('name')
            ->get();
        $teamIdLookup = $teamIds->map(fn ($id): int => (int) $id)->all();
        $engagementOfficeIdLookup = $officeIds->map(fn ($id): int => (int) $id)->all();

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
                'lockVersion' => $engagement->lock_version,
            ],
            'conference' => $conference ? $this->conferenceData($conference) : null,
            'references' => [
                'users' => $users->map(function (User $user) use ($teamIdLookup, $engagementOfficeIdLookup): array {
                    $participantTypes = [];
                    if (in_array((int) $user->id, $teamIdLookup, true)) {
                        $participantTypes[] = 'AUDIT_TEAM';
                    }
                    if (in_array((int) $user->office_id, $engagementOfficeIdLookup, true)) {
                        $participantTypes[] = 'AUDITEE';
                    }

                    return $this->userData($user, $participantTypes);
                })->all(),
                'offices' => Office::query()
                    ->whereIn('id', $responsibleOfficeIds)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Office $office): array => $this->officeData($office))->all(),
                'ciasOfficeId' => $ciasOffice?->id,
                'statuses' => EntryConference::STATUSES,
                'attachmentCategories' => EntryConferenceAttachment::CATEGORIES,
            ],
            'history' => $engagement->events()
                ->where('subject_type', 'ENTRY_CONFERENCE')
                ->with('actor:id,name')
                ->latest()
                ->get()
                ->map(fn ($event): array => [
                    'id' => $event->id,
                    'action' => $event->action,
                    'fromStatus' => $event->from_status,
                    'toStatus' => $event->to_status,
                    'comment' => $event->comment,
                    'actor' => $this->userData($event->actor),
                    'createdAt' => $event->created_at?->toISOString(),
                    'documentVersionIds' => $event->document_version_ids,
                ])->all(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function create(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): EntryConference {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.entry-conference.manage',
        );
        if ($engagement->status !== 'ENTRY_CONFERENCE') {
            throw ValidationException::withMessages([
                'engagement' => ['Start the aggregate Entry Conference stage before creating its record.'],
            ]);
        }
        if (EntryConference::withTrashed()->where('audit_engagement_id', $engagement->id)->exists()) {
            throw ValidationException::withMessages([
                'conference' => ['This engagement already has an Entry Conference record.'],
            ]);
        }

        return DB::transaction(function () use ($request, $engagement, $attributes): EntryConference {
            $conference = EntryConference::query()->create([
                'audit_engagement_id' => $engagement->id,
                'conference_code' => 'ENTRY-'.$engagement->engagement_code,
                'status' => 'DRAFT',
                ...$this->conferenceAttributes($attributes),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->syncChildren($conference, $engagement, $attributes);
            $conference = $this->load($conference);
            $this->record($request, $engagement, $conference, 'aems.entry-conference.created', null, 'DRAFT');

            return $conference;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(
        Request $request,
        AuditEngagement $engagement,
        EntryConference $conference,
        array $attributes,
    ): EntryConference {
        Gate::forUser($request->user())->authorize('manage', $conference);

        return DB::transaction(function () use ($request, $engagement, $conference, $attributes): EntryConference {
            $conference = $this->lock($engagement, $conference, $attributes['lockVersion']);
            $this->ensureEditable($conference);
            $old = $this->snapshot($conference);
            $conference->fill([
                ...$this->conferenceAttributes($attributes),
                'updated_by' => $request->user()->id,
                'lock_version' => $conference->lock_version + 1,
            ])->save();
            $this->syncChildren($conference, $engagement, $attributes);
            $conference = $this->load($conference);
            $this->record(
                $request,
                $engagement,
                $conference,
                'aems.entry-conference.updated',
                $old['status'],
                $conference->status,
                $old,
            );

            return $conference;
        });
    }

    /** @param array<string, mixed> $details */
    public function transition(
        Request $request,
        AuditEngagement $engagement,
        EntryConference $conference,
        string $action,
        int $lockVersion,
        array $details,
    ): EntryConference {
        $action = strtoupper($action);
        Gate::forUser($request->user())->authorize(
            $action === 'WAIVE' ? 'waive' : 'manage',
            $conference,
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $conference,
            $action,
            $lockVersion,
            $details,
        ): EntryConference {
            $conference = $this->lock($engagement, $conference, $lockVersion);
            $from = $conference->status;
            $old = $this->snapshot($conference);
            $changes = $this->transitionChanges(
                $conference,
                $action,
                $details,
                $request->user()->id,
            );
            if ($action === 'MARK_HELD') {
                $this->recordAttendance($conference, $details['participantAttendance'] ?? []);
            }
            if ($action === 'COMPLETE') {
                $conference->load($this->relations());
                $this->validateCompletion($conference);
                $changes['completed_at'] = now();
                $changes['completed_by'] = $request->user()->id;
            }
            if ($action === 'WAIVE') {
                $this->validateWaiver($conference, $details);
                $changes += [
                    'waiver_reason' => trim($details['reason']),
                    'waiver_authority' => trim($details['authority']),
                    'waived_at' => now(),
                    'waived_by' => $request->user()->id,
                ];
            }
            $conference->fill([
                ...$changes,
                'updated_by' => $request->user()->id,
                'lock_version' => $conference->lock_version + 1,
            ])->save();
            $conference = $this->load($conference);
            $this->record(
                $request,
                $engagement,
                $conference,
                'aems.entry-conference.'.strtolower($action),
                $from,
                $conference->status,
                $old,
                $details['reason'] ?? $details['comment'] ?? null,
            );
            $this->notifications->entryConference($request, $engagement, $conference, $action);

            return $conference;
        });
    }

    public function acknowledge(
        Request $request,
        AuditEngagement $engagement,
        EntryConference $conference,
        string $status,
        ?string $reservation,
        int $lockVersion,
    ): EntryConferenceAcknowledgement {
        Gate::forUser($request->user())->authorize('acknowledge', $conference);

        return DB::transaction(function () use (
            $request,
            $engagement,
            $conference,
            $status,
            $reservation,
            $lockVersion,
        ): EntryConferenceAcknowledgement {
            $conference = $this->lock($engagement, $conference, $lockVersion);
            if (! in_array($conference->status, ['NOTES_FOR_ACKNOWLEDGEMENT', 'ACKNOWLEDGED'], true)) {
                throw ValidationException::withMessages([
                    'conference' => ['Only circulated Entry Conference Notes can be acknowledged.'],
                ]);
            }
            if ($status === 'ACKNOWLEDGED_WITH_RESERVATION' && blank($reservation)) {
                throw ValidationException::withMessages([
                    'reservation' => ['Describe the reservation.'],
                ]);
            }
            $participant = $conference->participants()
                ->where('participant_type', 'AUDITEE')
                ->where(function ($query) use ($request): void {
                    $query->where('user_id', $request->user()->id)
                        ->orWhere('office_id', $request->user()->office_id);
                })->first();
            if (! $participant) {
                throw ValidationException::withMessages([
                    'conference' => ['Only an invited auditee participant may acknowledge these notes.'],
                ]);
            }
            $acknowledgement = EntryConferenceAcknowledgement::query()->create([
                'entry_conference_id' => $conference->id,
                'user_id' => $request->user()->id,
                'office_id' => $request->user()->office_id,
                'conference_version' => $conference->lock_version,
                'acknowledgement_status' => $status,
                'reservation' => $this->nullableTrim($reservation),
                'acknowledged_at' => now(),
            ]);
            $conference->forceFill([
                'status' => 'ACKNOWLEDGED',
                'updated_by' => $request->user()->id,
                'lock_version' => $conference->lock_version + 1,
            ])->save();
            $this->record(
                $request,
                $engagement,
                $conference,
                'aems.entry-conference.acknowledged',
                $conference->getOriginal('status'),
                'ACKNOWLEDGED',
                null,
                $reservation,
            );
            $this->notifications->entryConference($request, $engagement, $conference, 'ACKNOWLEDGED');

            return $acknowledgement->load(['user', 'office']);
        });
    }

    public function uploadAttachment(
        Request $request,
        AuditEngagement $engagement,
        EntryConference $conference,
        UploadedFile $file,
        string $category,
        ?string $caption,
        int $lockVersion,
    ): EntryConferenceAttachment {
        Gate::forUser($request->user())->authorize('manage', $conference);
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
            ): EntryConferenceAttachment {
                $conference = $this->lock($engagement, $conference, $lockVersion);
                $this->ensureEditable($conference);
                $version = $this->createDocument($request, $engagement, $conference, $category, $stored);
                $attachment = EntryConferenceAttachment::query()->create([
                    'entry_conference_id' => $conference->id,
                    'attachment_code' => $this->nextAttachmentCode($conference),
                    'category' => $category,
                    'caption' => $this->nullableTrim($caption),
                    'document_version_id' => $version->id,
                    'uploaded_by' => $request->user()->id,
                ]);
                $conference->forceFill([
                    'updated_by' => $request->user()->id,
                    'lock_version' => $conference->lock_version + 1,
                ])->save();
                $this->record(
                    $request,
                    $engagement,
                    $conference,
                    'aems.entry-conference.attachment_uploaded',
                    $conference->status,
                    $conference->status,
                    null,
                    $caption,
                    [$version->id],
                );

                return $attachment->load(['documentVersion', 'uploader']);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($stored['storage_path']);
            throw $exception;
        }
    }

    public function downloadAttachment(
        Request $request,
        AuditEngagement $engagement,
        EntryConference $conference,
        EntryConferenceAttachment $attachment,
    ): DocumentVersion {
        Gate::forUser($request->user())->authorize('view', $conference);
        $this->ensureConference($engagement, $conference);
        if ((int) $attachment->entry_conference_id !== (int) $conference->id) {
            throw ValidationException::withMessages([
                'attachment' => ['This attachment does not belong to the selected Entry Conference.'],
            ]);
        }
        $version = $attachment->documentVersion;
        if (! $version || ! Storage::disk('local')->exists($version->storage_path)) {
            abort(404, 'The Entry Conference attachment file is unavailable.');
        }
        $this->record(
            $request,
            $engagement,
            $conference,
            'aems.entry-conference.attachment_downloaded',
            $conference->status,
            $conference->status,
            null,
            $attachment->attachment_code,
            [$version->id],
        );

        return $version;
    }

    /** @return array<string, mixed> */
    public function conferenceData(EntryConference $conference): array
    {
        $conference->loadMissing($this->relations());

        return [
            'id' => $conference->id,
            'conferenceCode' => $conference->conference_code,
            'status' => $conference->status,
            'scheduledStartAt' => $conference->scheduled_start_at?->toISOString(),
            'scheduledEndAt' => $conference->scheduled_end_at?->toISOString(),
            'heldAt' => $conference->held_at?->toISOString(),
            'venue' => $conference->venue,
            'meetingLink' => $conference->meeting_link,
            'onlineMeetingDetails' => $conference->online_meeting_details,
            'agenda' => $conference->agenda,
            'briefingPaper' => $conference->briefing_paper,
            'auditeeViews' => $conference->auditee_views,
            'auditeeExpectations' => $conference->auditee_expectations,
            'conferenceNotes' => $conference->conference_notes,
            'materialMattersDisposition' => $conference->material_matters_disposition,
            'notesCirculatedAt' => $conference->notes_circulated_at?->toISOString(),
            'rescheduleReason' => $conference->reschedule_reason,
            'cancellationReason' => $conference->cancellation_reason,
            'waiverReason' => $conference->waiver_reason,
            'waiverAuthority' => $conference->waiver_authority,
            'waivedAt' => $conference->waived_at?->toISOString(),
            'completedAt' => $conference->completed_at?->toISOString(),
            'lockVersion' => $conference->lock_version,
            'immutable' => in_array($conference->status, EntryConference::TERMINAL_STATUSES, true),
            'createdBy' => $this->userData($conference->creator),
            'completedBy' => $this->userData($conference->completer),
            'waiverApprover' => $this->userData($conference->waiverApprover),
            'participants' => $conference->participants->map(fn ($participant): array => [
                'id' => $participant->id,
                'userId' => $participant->user_id,
                'user' => $this->userData($participant->user),
                'officeId' => $participant->office_id,
                'office' => $this->officeData($participant->office),
                'participantType' => $participant->participant_type,
                'participantRole' => $participant->participant_role,
                'externalName' => $participant->external_name,
                'externalEmail' => $participant->external_email,
                'attendanceStatus' => $participant->attendance_status,
                'attendedAt' => $participant->attended_at?->toISOString(),
                'attendanceNotes' => $participant->attendance_notes,
            ])->all(),
            'matters' => $conference->matters->map(fn (EntryConferenceMatter $matter): array => [
                'id' => $matter->id,
                'matterType' => $matter->matter_type,
                'description' => $matter->description,
                'isMaterial' => $matter->is_material,
                'dispositionStatus' => $matter->disposition_status,
                'disposition' => $matter->disposition,
                'responsibleUserId' => $matter->responsible_user_id,
                'responsibleOfficeId' => $matter->responsible_office_id,
                'dueDate' => $matter->due_date?->toDateString(),
            ])->all(),
            'agreements' => $conference->agreements->map(fn (EntryConferenceAgreement $agreement): array => [
                'id' => $agreement->id,
                'agreement' => $agreement->agreement,
                'responsibleUserId' => $agreement->responsible_user_id,
                'responsibleOfficeId' => $agreement->responsible_office_id,
                'dueDate' => $agreement->due_date?->toDateString(),
                'status' => $agreement->status,
            ])->all(),
            'acknowledgements' => $conference->acknowledgements->map(fn ($ack): array => [
                'id' => $ack->id,
                'conferenceVersion' => $ack->conference_version,
                'status' => $ack->acknowledgement_status,
                'reservation' => $ack->reservation,
                'actor' => $this->userData($ack->user),
                'office' => $this->officeData($ack->office),
                'acknowledgedAt' => $ack->acknowledged_at?->toISOString(),
            ])->all(),
            'attachments' => $conference->attachments->map(
                fn (EntryConferenceAttachment $attachment): array => $this->attachmentData($attachment),
            )->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function attachmentData(EntryConferenceAttachment $attachment): array
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

    /** @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function transitionChanges(
        EntryConference $conference,
        string $action,
        array $details,
        int $actorId,
    ): array {
        $allowed = [
            'DRAFT' => ['SCHEDULE' => 'SCHEDULED', 'WAIVE' => 'WAIVED', 'CANCEL' => 'CANCELLED'],
            'SCHEDULED' => ['RESCHEDULE' => 'RESCHEDULED', 'MARK_HELD' => 'HELD', 'WAIVE' => 'WAIVED', 'CANCEL' => 'CANCELLED'],
            'RESCHEDULED' => ['RESCHEDULE' => 'RESCHEDULED', 'MARK_HELD' => 'HELD', 'WAIVE' => 'WAIVED', 'CANCEL' => 'CANCELLED'],
            'HELD' => ['CIRCULATE_NOTES' => 'NOTES_FOR_ACKNOWLEDGEMENT'],
            'NOTES_FOR_ACKNOWLEDGEMENT' => ['COMPLETE' => 'COMPLETED'],
            'ACKNOWLEDGED' => ['COMPLETE' => 'COMPLETED'],
        ];
        $target = $allowed[$conference->status][$action] ?? null;
        if (! $target) {
            throw ValidationException::withMessages([
                'action' => ["{$action} is not allowed while the Entry Conference is {$conference->status}."],
            ]);
        }
        if ($action === 'CIRCULATE_NOTES' && blank($conference->conference_notes)) {
            throw ValidationException::withMessages([
                'conferenceNotes' => ['Prepare the Entry Conference Notes before circulation.'],
            ]);
        }

        return match ($action) {
            'SCHEDULE' => [
                'status' => $target,
                ...$this->validatedSchedule($details),
            ],
            'RESCHEDULE' => [
                'status' => $target,
                ...$this->validatedSchedule($details),
                'reschedule_reason' => $this->requiredText($details, 'reason', 'A reschedule reason is required.'),
            ],
            'MARK_HELD' => [
                'status' => $target,
                'held_at' => $details['heldAt'] ?? now(),
            ],
            'CIRCULATE_NOTES' => [
                'status' => $target,
                'notes_circulated_at' => now(),
                'notes_circulated_by' => $actorId,
            ],
            'CANCEL' => [
                'status' => $target,
                'cancellation_reason' => $this->requiredText($details, 'reason', 'A cancellation reason is required.'),
                'cancelled_at' => now(),
                'cancelled_by' => $actorId,
            ],
            default => ['status' => $target],
        };
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function conferenceAttributes(array $attributes): array
    {
        return [
            'scheduled_start_at' => $attributes['scheduledStartAt'] ?? null,
            'scheduled_end_at' => $attributes['scheduledEndAt'] ?? null,
            'venue' => $this->nullableTrim($attributes['venue'] ?? null),
            'meeting_link' => $this->nullableTrim($attributes['meetingLink'] ?? null),
            'online_meeting_details' => $this->nullableTrim($attributes['onlineMeetingDetails'] ?? null),
            'agenda' => $this->nullableTrim($attributes['agenda'] ?? null),
            'briefing_paper' => $attributes['briefingPaper'] ?? null,
            'auditee_views' => $this->nullableTrim($attributes['auditeeViews'] ?? null),
            'auditee_expectations' => $this->nullableTrim($attributes['auditeeExpectations'] ?? null),
            'conference_notes' => $this->nullableTrim($attributes['conferenceNotes'] ?? null),
            'material_matters_disposition' => $this->nullableTrim($attributes['materialMattersDisposition'] ?? null),
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function syncChildren(
        EntryConference $conference,
        AuditEngagement $engagement,
        array $attributes,
    ): void {
        $ciasOfficeId = Office::query()->where('code', 'CIAS')->value('id');
        $allowedResponsibleOfficeIds = $engagement->offices()->pluck('offices.id')
            ->push($ciasOfficeId)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $activeTeamUserIds = $engagement->teamMembers()
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $resolveResponsibleOffice = function (?int $responsibleUserId, $responsibleOfficeId) use ($allowedResponsibleOfficeIds, $activeTeamUserIds, $ciasOfficeId, $engagement): ?int {
            $user = $responsibleUserId ? User::query()->find($responsibleUserId) : null;
            if ($responsibleUserId && in_array($responsibleUserId, $activeTeamUserIds, true) && $ciasOfficeId) {
                return (int) $ciasOfficeId;
            }
            if ($responsibleOfficeId && $allowedResponsibleOfficeIds->contains((int) $responsibleOfficeId)) {
                return (int) $responsibleOfficeId;
            }
            if ($user?->office_id && $engagement->offices()->whereKey($user->office_id)->exists()) {
                return (int) $user->office_id;
            }
            return $ciasOfficeId ? (int) $ciasOfficeId : null;
        };
        if (array_key_exists('participants', $attributes)) {
            $conference->participants()->delete();
            foreach ($attributes['participants'] as $index => $item) {
                $type = $item['participantType'];
                $user = ! empty($item['userId'])
                    ? User::query()->where('is_active', true)->find($item['userId'])
                    : null;
                $externalName = $this->nullableTrim($item['externalName'] ?? null);
                if ($type === 'EXTERNAL' && ! $externalName) {
                    throw ValidationException::withMessages([
                        "participants.{$index}.externalName" => ['External participant name is required.'],
                    ]);
                }
                if ($type !== 'EXTERNAL' && ! $user) {
                    throw ValidationException::withMessages([
                        "participants.{$index}.userId" => ['Select an active internal participant.'],
                    ]);
                }
                if ($type === 'AUDIT_TEAM' && ! $engagement->teamMembers()
                    ->where('user_id', $user?->id)->where('is_active', true)->whereNull('ended_at')->exists()) {
                    throw ValidationException::withMessages([
                        "participants.{$index}.userId" => ['Audit-team participants must have an active assignment.'],
                    ]);
                }
                $officeId = $item['officeId'] ?? $user?->office_id;
                if ($type === 'AUDITEE' && (! $officeId
                    || ! $engagement->offices()->whereKey($officeId)->exists())) {
                    throw ValidationException::withMessages([
                        "participants.{$index}.officeId" => ['Auditee participants must belong to an engagement office.'],
                    ]);
                }
                $conference->participants()->create([
                    'user_id' => $user?->id,
                    'office_id' => $officeId,
                    'participant_type' => $type,
                    'participant_role' => $this->nullableTrim($item['participantRole'] ?? null),
                    'external_name' => $externalName,
                    'external_email' => $this->nullableTrim($item['externalEmail'] ?? null),
                ]);
            }
        }
        if (array_key_exists('matters', $attributes)) {
            $conference->matters()->delete();
            foreach ($attributes['matters'] as $matter) {
                $conference->matters()->create([
                    'matter_type' => $matter['matterType'] ?? 'GENERAL',
                    'description' => trim($matter['description']),
                    'is_material' => (bool) ($matter['isMaterial'] ?? false),
                    'disposition_status' => $matter['dispositionStatus'] ?? 'OPEN',
                    'disposition' => $this->nullableTrim($matter['disposition'] ?? null),
                    'responsible_user_id' => $matter['responsibleUserId'] ?? null,
                    'responsible_office_id' => $resolveResponsibleOffice($matter['responsibleUserId'] ?? null, $matter['responsibleOfficeId'] ?? null),
                    'due_date' => $matter['dueDate'] ?? null,
                ]);
            }
        }
        if (array_key_exists('agreements', $attributes)) {
            $conference->agreements()->delete();
            foreach ($attributes['agreements'] as $agreement) {
                $conference->agreements()->create([
                    'agreement' => trim($agreement['agreement']),
                    'responsible_user_id' => $agreement['responsibleUserId'] ?? null,
                    'responsible_office_id' => $resolveResponsibleOffice($agreement['responsibleUserId'] ?? null, $agreement['responsibleOfficeId'] ?? null),
                    'due_date' => $agreement['dueDate'] ?? null,
                    'status' => $agreement['status'] ?? 'OPEN',
                ]);
            }
        }
    }

    /** @param list<array<string, mixed>> $records */
    private function recordAttendance(EntryConference $conference, array $records): void
    {
        $participants = $conference->participants()->get()->keyBy('id');
        if ($participants->isEmpty() || count($records) !== $participants->count()) {
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
            $attended = $record['attendanceStatus'] === 'ATTENDED';
            $participant->forceFill([
                'attendance_status' => $record['attendanceStatus'],
                'attended_at' => $attended ? ($record['attendedAt'] ?? now()) : null,
                'attendance_notes' => $this->nullableTrim($record['attendanceNotes'] ?? null),
            ])->save();
        }
    }

    private function validateCompletion(EntryConference $conference): void
    {
        $engagement = $conference->engagement()->with([
            'engagementOrder',
            'engagementPlan',
            'programs',
        ])->firstOrFail();
        $currentProgram = $engagement->programs
            ->where('is_current_revision', true)->where('is_active', true)->sortByDesc('revision_number')->first();
        $errors = [];
        if ($engagement->engagementOrder?->status !== 'ISSUED') {
            $errors[] = 'The AEO must remain issued.';
        }
        if ($engagement->engagementPlan?->status !== 'APPROVED') {
            $errors[] = 'The AEP must remain approved.';
        }
        if (! $currentProgram || ! in_array($currentProgram->status, ['APPROVED', 'ACTIVE', 'COMPLETED'], true)) {
            $errors[] = 'The current Audit Program must remain approved.';
        }
        if (! $conference->held_at) {
            $errors[] = 'The held date is required.';
        }
        if (blank($conference->agenda)
            && blank($conference->briefing_paper)
            && ! $conference->attachments->contains('category', 'BRIEFING_PAPER')) {
            $errors[] = 'An agenda or briefing paper is required.';
        }
        if (! $conference->participants->contains(
            fn ($item) => $item->participant_type === 'AUDIT_TEAM' && $item->attendance_status === 'ATTENDED',
        )) {
            $errors[] = 'At least one audit-team participant must attend.';
        }
        if (! $conference->participants->contains(
            fn ($item) => $item->participant_type === 'AUDITEE' && $item->attendance_status === 'ATTENDED',
        )) {
            $errors[] = 'At least one auditee participant must attend.';
        }
        if (blank($conference->conference_notes)) {
            $errors[] = 'Entry Conference Notes are required.';
        }
        if ($conference->matters->contains(
            fn ($matter) => $matter->is_material
                && ($matter->disposition_status === 'OPEN' || blank($matter->disposition)),
        )) {
            $errors[] = 'Every material matter requires a recorded disposition.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(['requirements' => $errors]);
        }
    }

    /** @param array<string, mixed> $details */
    private function validateWaiver(EntryConference $conference, array $details): void
    {
        if (blank($details['reason'] ?? null) || blank($details['authority'] ?? null)) {
            throw ValidationException::withMessages([
                'reason' => ['A waiver reason is required.'],
                'authority' => ['The approving authority is required.'],
            ]);
        }
        if (($details['supportingDocumentRequired'] ?? false)
            && ! $conference->attachments()->where('category', 'WAIVER_SUPPORT')->exists()) {
            throw ValidationException::withMessages([
                'supportingDocument' => ['Upload the required waiver supporting document first.'],
            ]);
        }
    }

    /** @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function validatedSchedule(array $details): array
    {
        if (blank($details['scheduledStartAt'] ?? null)) {
            throw ValidationException::withMessages(['scheduledStartAt' => ['The conference schedule is required.']]);
        }
        if (! empty($details['scheduledEndAt'])
            && Carbon::parse($details['scheduledEndAt'])->lte(Carbon::parse($details['scheduledStartAt']))) {
            throw ValidationException::withMessages(['scheduledEndAt' => ['The end must be after the start.']]);
        }
        if (blank($details['venue'] ?? null)
            && blank($details['meetingLink'] ?? null)
            && blank($details['onlineMeetingDetails'] ?? null)) {
            throw ValidationException::withMessages(['venue' => ['Provide a venue or online meeting details.']]);
        }

        return [
            'scheduled_start_at' => $details['scheduledStartAt'],
            'scheduled_end_at' => $details['scheduledEndAt'] ?? null,
            'venue' => $this->nullableTrim($details['venue'] ?? null),
            'meeting_link' => $this->nullableTrim($details['meetingLink'] ?? null),
            'online_meeting_details' => $this->nullableTrim($details['onlineMeetingDetails'] ?? null),
        ];
    }

    private function lock(
        AuditEngagement $engagement,
        EntryConference $conference,
        int $lockVersion,
    ): EntryConference {
        $locked = EntryConference::query()->lockForUpdate()->findOrFail($conference->id);
        $this->ensureConference($engagement, $locked);
        if ($locked->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['This Entry Conference changed in another session. Refresh before continuing.'],
            ]);
        }

        return $locked;
    }

    private function ensureConference(AuditEngagement $engagement, EntryConference $conference): void
    {
        if ((int) $conference->audit_engagement_id !== (int) $engagement->id) {
            throw ValidationException::withMessages([
                'conference' => ['The Entry Conference does not belong to this engagement.'],
            ]);
        }
    }

    private function ensureEditable(EntryConference $conference): void
    {
        if (! in_array($conference->status, self::EDITABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'conference' => ['Completed, waived, and cancelled Entry Conferences are locked.'],
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function storeFile(UploadedFile $file, AuditEngagement $engagement): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = Storage::disk('local')->putFileAs(
            "aems/engagements/{$engagement->id}/entry-conference",
            $file,
            Str::uuid().($extension ? ".{$extension}" : ''),
        );
        if (! $path) {
            throw ValidationException::withMessages(['file' => ['The attachment could not be stored.']]);
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
        EntryConference $conference,
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
            'title' => "{$conference->conference_code} - ".Str::headline(strtolower($category)),
            'description' => "Private Entry Conference record for {$engagement->engagement_code}.",
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
            'version_label' => 'Entry Conference attachment version 1',
            'change_summary' => 'Initial immutable Entry Conference file.',
            ...$stored,
            'uploaded_by' => $request->user()->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id, 'version' => $version->version_label])->save();
        $document->links()->create([
            'module_code' => 'AEMS',
            'record_type' => 'ENTRY_CONFERENCE',
            'record_id' => $conference->id,
            'record_code' => $conference->conference_code,
            'record_label' => "{$conference->conference_code} - Entry Conference",
            'linked_by' => $request->user()->id,
        ]);

        return $version;
    }

    private function nextAttachmentCode(EntryConference $conference): string
    {
        $sequence = $conference->attachments()->count() + 1;
        do {
            $code = sprintf('ATT-%s-%02d', $conference->conference_code, $sequence++);
        } while (EntryConferenceAttachment::query()->where('attachment_code', $code)->exists());

        return $code;
    }

    private function load(EntryConference $conference): EntryConference
    {
        return $conference->fresh($this->relations());
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'creator',
            'updater',
            'completer',
            'waiverApprover',
            'participants.user.office',
            'participants.office',
            'matters.responsibleUser',
            'matters.responsibleOffice',
            'agreements.responsibleUser',
            'agreements.responsibleOffice',
            'acknowledgements.user',
            'acknowledgements.office',
            'attachments.documentVersion',
            'attachments.uploader',
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(EntryConference $conference): array
    {
        return [
            'id' => $conference->id,
            'conferenceCode' => $conference->conference_code,
            'status' => $conference->status,
            'scheduledStartAt' => $conference->scheduled_start_at?->toISOString(),
            'heldAt' => $conference->held_at?->toISOString(),
            'lockVersion' => $conference->lock_version,
        ];
    }

    private function record(
        Request $request,
        AuditEngagement $engagement,
        EntryConference $conference,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?array $oldValues = null,
        ?string $comment = null,
        ?array $documentVersionIds = null,
    ): void {
        $newValues = $this->snapshot($conference);
        $this->support->event(
            $request,
            $engagement,
            $action,
            $fromStatus,
            $toStatus,
            $oldValues,
            $newValues,
            $comment,
            'ENTRY_CONFERENCE',
            $conference->id,
            $conference->lock_version,
            $conference->conference_code,
            null,
            $documentVersionIds,
        );
        $this->support->audit($request, $action, $engagement, $oldValues, $newValues, [
            'subjectType' => 'ENTRY_CONFERENCE',
            'subjectId' => $conference->id,
            'subjectCode' => $conference->conference_code,
            'documentVersionIds' => $documentVersionIds,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function userData(?User $user, ?array $participantTypes = null): ?array
    {
        if (! $user) {
            return null;
        }

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'officeId' => $user->office_id,
        ];
        if ($participantTypes !== null) {
            $data['participantTypes'] = $participantTypes;
        }

        return $data;
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

    /** @param array<string, mixed> $details */
    private function requiredText(array $details, string $key, string $message): string
    {
        if (blank($details[$key] ?? null)) {
            throw ValidationException::withMessages([$key => [$message]]);
        }

        return trim($details[$key]);
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
