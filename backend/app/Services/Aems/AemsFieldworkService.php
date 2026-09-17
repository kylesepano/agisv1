<?php

namespace App\Services;

use App\Models\AemsFieldworkEvidenceLink;
use App\Models\AemsFieldworkRecord;
use App\Models\AemsFieldworkRecordParticipant;
use App\Models\AemsFieldworkRecordVersion;
use App\Models\AemsFieldworkWorkingPaperLink;
use App\Models\AuditArea;
use App\Models\AuditEngagement;
use App\Models\AuditEvidence;
use App\Models\AuditFocus;
use App\Models\AuditProgramProcedure;
use App\Models\EngagementEvent;
use App\Models\WorkingPaper;
use App\Models\WorkingPaperVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Owns immutable Fieldwork Record content and the execution traceability gate.
 * A procedure cannot be marked completed unless a finalized record points to it.
 */
class AemsFieldworkService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly AemsNotificationService $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(Request $request, AuditEngagement $engagement): array
    {
        $engagement->loadMissing([
            'auditAreas:id,code,name',
            'auditFocuses:id,audit_area_id,code,name',
        ]);
        $records = AemsFieldworkRecord::query()
            ->visibleTo($request->user())
            ->where('audit_engagement_id', $engagement->id)
            ->with([
                'procedure.program',
                'auditArea:id,code,name',
                'auditFocus:id,code,name',
                'preparer:id,employee_id,name,initials',
                'reviewer:id,employee_id,name,initials',
                'finalizer:id,employee_id,name,initials',
                'versions.creator:id,employee_id,name,initials',
                'versions.auditArea:id,code,name',
                'versions.auditFocus:id,code,name',
                'versions.participants.user:id,employee_id,name,initials',
                'versions.participants.office:id,code,name',
                'versions.workingPaperLinks.workingPaper:id,working_paper_code,title,status',
                'versions.workingPaperLinks.workingPaperVersion',
                'versions.evidenceLinks.evidence',
            ])
            ->orderBy('record_code')
            ->get();
        $procedures = AuditProgramProcedure::query()
            ->whereHas('program', fn ($program) => $program
                ->where('audit_engagement_id', $engagement->id)
                ->where('is_current_revision', true)
                ->where('status', 'ACTIVE'))
            ->with(['program:id,program_code,title,status', 'assignee:id,employee_id,name,initials'])
            ->withCount(['fieldworkRecords as finalized_fieldwork_records_count' => fn ($records) => $records->where('status', 'FINALIZED')])
            ->orderBy('audit_program_id')
            ->orderBy('sequence_number')
            ->get();

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
            ],
            'recordTypes' => AemsFieldworkRecord::TYPES,
            'executionStatuses' => AemsFieldworkRecord::EXECUTION_STATUSES,
            'auditAreas' => $engagement->auditAreas->map(fn (AuditArea $area): array => $area->only(['id', 'code', 'name']))->values(),
            'auditFocuses' => $engagement->auditFocuses->map(fn (AuditFocus $focus): array => [
                'id' => $focus->id,
                'auditAreaId' => $focus->audit_area_id,
                'code' => $focus->code,
                'name' => $focus->name,
            ])->values(),
            'procedures' => $procedures->map(fn (AuditProgramProcedure $procedure): array => $this->procedureData($procedure))->values(),
            'records' => $records->map(fn (AemsFieldworkRecord $record): array => $this->data($record))->values(),
            'traceability' => [
                'procedures' => $procedures->count(),
                'completedProcedures' => $procedures->where('status', 'COMPLETED')->count(),
                'proceduresWithFinalizedRecords' => $procedures->filter(fn (AuditProgramProcedure $procedure): bool => (int) $procedure->finalized_fieldwork_records_count > 0)->count(),
                'complete' => $procedures->where('status', 'COMPLETED')->every(fn (AuditProgramProcedure $procedure): bool => (int) $procedure->finalized_fieldwork_records_count > 0),
            ],
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function create(Request $request, AuditEngagement $engagement, array $attributes): AemsFieldworkRecord
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.fieldwork.create');

        $record = DB::transaction(function () use ($request, $engagement, $attributes): AemsFieldworkRecord {
            $procedure = $this->procedure($engagement, (int) $attributes['procedureId']);
            $this->ensureActorMayExecute($request, $engagement, $procedure);
            [$area, $focus] = $this->classifications($engagement, $attributes);
            $links = $this->traceability($request, $engagement, $procedure, $attributes);
            $code = $this->nextCode($engagement);
            $record = AemsFieldworkRecord::query()->create([
                'record_family_uuid' => (string) Str::uuid(),
                'audit_engagement_id' => $engagement->id,
                'audit_program_procedure_id' => $procedure->id,
                'audit_area_id' => $area->id,
                'audit_focus_id' => $focus->id,
                'record_code' => $code,
                'record_type' => $attributes['recordType'],
                'status' => 'DRAFT',
                'current_version_number' => 1,
                'prepared_by' => $request->user()->id,
                'lock_version' => 1,
                'is_active' => true,
            ]);
            $version = $this->createVersion($request, $record, $attributes, $links, 1);
            $this->markProcedureInProgress($procedure);
            $this->event($request, $engagement, $record, $version, 'CREATED', null, 'DRAFT', null, $this->auditValues($record, $version));
            $this->support->audit($request, 'aems.fieldwork.created', $engagement, null, $this->auditValues($record, $version), [
                'fieldworkRecordId' => $record->id,
                'procedureId' => $procedure->id,
            ]);

            return $record;
        }, 3);

        return $this->load($record);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Request $request, AuditEngagement $engagement, AemsFieldworkRecord $record, array $attributes): AemsFieldworkRecord
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.fieldwork.create');

        $record = DB::transaction(function () use ($request, $engagement, $record, $attributes): AemsFieldworkRecord {
            $locked = $this->lockRecord($engagement, $record, (int) $attributes['lockVersion']);
            if (! in_array($locked->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
                throw ValidationException::withMessages(['status' => ['Only draft or returned Fieldwork Records can be edited.']]);
            }
            $procedure = $this->procedure($engagement, (int) $attributes['procedureId']);
            if ((int) $procedure->id !== (int) $locked->audit_program_procedure_id) {
                throw ValidationException::withMessages(['procedureId' => ['A Fieldwork Record cannot be moved to another procedure.']]);
            }
            $this->ensureActorMayExecute($request, $engagement, $procedure);
            [$area, $focus] = $this->classifications($engagement, $attributes);
            $links = $this->traceability($request, $engagement, $procedure, $attributes);
            $before = $this->auditValues($locked, $locked->latestVersion()->firstOrFail());
            $number = $locked->current_version_number + 1;
            $version = $this->createVersion($request, $locked, $attributes, $links, $number);
            $locked->update([
                'audit_area_id' => $area->id,
                'audit_focus_id' => $focus->id,
                'record_type' => $attributes['recordType'],
                'current_version_number' => $number,
                'reviewer_id' => null,
                'reviewed_at' => null,
                'reviewer_notes' => null,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($request, $engagement, $locked, $version, 'VERSION_CREATED', $locked->status, $locked->status, $before, $this->auditValues($locked, $version), $attributes['changeReason'] ?? null);
            $this->support->audit($request, 'aems.fieldwork.version_created', $engagement, $before, $this->auditValues($locked, $version), [
                'fieldworkRecordId' => $locked->id,
                'changeReason' => $attributes['changeReason'] ?? null,
            ]);

            return $locked;
        }, 3);

        return $this->load($record);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AemsFieldworkRecord $record,
        string $action,
        int $lockVersion,
        ?string $comment,
    ): AemsFieldworkRecord {
        $permission = match ($action) {
            'SUBMIT', 'RESUBMIT', 'REVISE' => 'aems.fieldwork.create',
            'REVIEW', 'RETURN' => 'aems.fieldwork.review',
            'FINALIZE' => 'aems.fieldwork.finalize',
            default => throw ValidationException::withMessages(['action' => ['Unsupported Fieldwork Record action.']]),
        };
        $this->access->authorizeEngagementAction($request->user(), $engagement, $permission, in_array($action, ['REVIEW', 'RETURN', 'FINALIZE'], true) ? $record->prepared_by : null);

        $record = DB::transaction(function () use ($request, $engagement, $record, $action, $lockVersion, $comment): AemsFieldworkRecord {
            $locked = $this->lockRecord($engagement, $record, $lockVersion);
            $version = $locked->latestVersion()->with(['participants', 'workingPaperLinks.workingPaper', 'workingPaperLinks.workingPaperVersion', 'evidenceLinks.evidence'])->firstOrFail();
            $from = $locked->status;
            if ($action === 'REVISE') {
                return $this->reviseLocked($request, $engagement, $locked, $version, $comment);
            }
            if ($action === 'REVIEW') {
                if (! in_array($from, ['SUBMITTED', 'RESUBMITTED'], true)) {
                    throw ValidationException::withMessages(['status' => ['Only submitted Fieldwork Records can be reviewed.']]);
                }
                if ((int) $request->user()->id === (int) $locked->prepared_by
                    && ! $this->access->mayUseSingleCiasHeadReviewException($request->user(), 'aems.fieldwork.review')) {
                    throw ValidationException::withMessages(['reviewer' => ['The preparer cannot independently review the Fieldwork Record.']]);
                }
                $locked->update([
                    'reviewer_id' => $request->user()->id,
                    'reviewed_at' => now(),
                    'reviewer_notes' => $comment,
                    'lock_version' => $locked->lock_version + 1,
                ]);
                $this->updateProcedureReviewState($locked->audit_program_procedure_id, 'REVIEWED');
                $this->event($request, $engagement, $locked, $version, 'REVIEW', $from, $from, ['status' => $from], ['status' => $from, 'reviewerNotes' => $comment], $comment);
                $this->support->audit($request, 'aems.fieldwork.reviewed', $engagement, ['status' => $from], ['status' => $from, 'reviewerNotes' => $comment], ['fieldworkRecordId' => $locked->id]);
                $this->notifications->fieldworkTransition($request, $engagement, $locked, $action, $version->version_number, $comment);

                return $locked;
            }
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) {
                $expected = $action === 'SUBMIT' ? 'DRAFT' : 'RETURNED_FOR_REVISION';
                if ($from !== $expected) {
                    throw ValidationException::withMessages(['status' => ["A {$action} action is not available from {$from}."]]);
                }
                $this->ensureReadyForReview($version);
            }
            if ($action === 'RETURN') {
                if (! in_array($from, ['SUBMITTED', 'RESUBMITTED'], true)) {
                    throw ValidationException::withMessages(['status' => ['Only submitted Fieldwork Records can be returned.']]);
                }
                if (mb_strlen(trim((string) $comment)) < 5) {
                    throw ValidationException::withMessages(['comment' => ['A clear return reason is required.']]);
                }
                $this->ensureNotPreparer($request, $locked);
            }
            if ($action === 'FINALIZE') {
                if (! in_array($from, ['SUBMITTED', 'RESUBMITTED'], true)) {
                    throw ValidationException::withMessages(['status' => ['Only submitted Fieldwork Records can be finalized.']]);
                }
                $this->ensureIndependentReviewer($request, $locked);
                if ((int) $locked->reviewer_id === (int) $request->user()->id
                    && ! $this->access->mayUseSingleCiasHeadReviewException($request->user(), 'aems.fieldwork.finalize')) {
                    throw ValidationException::withMessages(['reviewer' => ['The finalizer must be different from the independent reviewer.']]);
                }
                $this->ensureReadyForFinalization($version);
            }

            $to = match ($action) {
                'SUBMIT' => 'SUBMITTED',
                'RESUBMIT' => 'RESUBMITTED',
                'RETURN' => 'RETURNED_FOR_REVISION',
                'FINALIZE' => 'FINALIZED',
            };
            $changes = ['status' => $to, 'lock_version' => $locked->lock_version + 1];
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) {
                $changes['submitted_by'] = $request->user()->id;
                $changes['submitted_at'] = now();
                $changes['reviewer_id'] = null;
                $changes['reviewed_at'] = null;
                $changes['reviewer_notes'] = null;
            }
            if ($action === 'RETURN') {
                $changes['reviewer_id'] = $request->user()->id;
                $changes['reviewed_at'] = now();
                $changes['reviewer_notes'] = $comment;
            }
            if ($action === 'FINALIZE') {
                $changes['finalized_by'] = $request->user()->id;
                $changes['finalized_at'] = now();
            }
            $locked->update($changes);
            $this->updateProcedureReviewState($locked->audit_program_procedure_id, match ($action) {
                'SUBMIT' => 'PENDING_REVIEW',
                'RESUBMIT' => 'PENDING_REVIEW',
                'RETURN' => 'RETURNED',
                'FINALIZE' => 'FINALIZED',
            });
            if ($action === 'FINALIZE') {
                $this->completeProcedureFromVersion($request, $locked, $version);
            }
            $this->event($request, $engagement, $locked, $version, $action, $from, $to, ['status' => $from], ['status' => $to, 'versionNumber' => $version->version_number], $comment);
            $this->support->audit($request, 'aems.fieldwork.'.str($action)->lower(), $engagement, ['status' => $from], ['status' => $to, 'versionNumber' => $version->version_number], ['fieldworkRecordId' => $locked->id, 'comment' => $comment]);
            $this->notifications->fieldworkTransition($request, $engagement, $locked, $action, $version->version_number, $comment);

            return $locked;
        }, 3);

        return $this->load($record);
    }

    /** @return array<string, mixed> */
    public function data(AemsFieldworkRecord $record): array
    {
        $record = $this->load($record);

        return [
            'id' => $record->id,
            'recordFamilyUuid' => $record->record_family_uuid,
            'recordCode' => $record->record_code,
            'recordType' => $record->record_type,
            'status' => $record->status,
            'procedureId' => $record->audit_program_procedure_id,
            'procedure' => $record->procedure ? [
                'id' => $record->procedure->id,
                'procedureCode' => $record->procedure->procedure_code,
                'objective' => $record->procedure->objective,
                'fieldworkStatus' => $record->procedure->fieldwork_status,
            ] : null,
            'auditArea' => $record->auditArea?->only(['id', 'code', 'name']),
            'auditFocus' => $record->auditFocus?->only(['id', 'code', 'name']),
            'currentVersionNumber' => $record->current_version_number,
            'latestVersion' => $record->versions->sortByDesc('version_number')->first() ? $this->versionData($record->versions->sortByDesc('version_number')->first()) : null,
            'versions' => $record->versions->sortByDesc('version_number')->map(fn (AemsFieldworkRecordVersion $version): array => $this->versionData($version))->values(),
            'preparedBy' => $this->user($record->preparer),
            'submittedBy' => $this->user($record->submitter),
            'submittedAt' => $record->submitted_at?->toIso8601String(),
            'reviewedBy' => $this->user($record->reviewer),
            'reviewedAt' => $record->reviewed_at?->toIso8601String(),
            'reviewerNotes' => $record->reviewer_notes,
            'finalizedBy' => $this->user($record->finalizer),
            'finalizedAt' => $record->finalized_at?->toIso8601String(),
            'lockVersion' => $record->lock_version,
            'isActive' => $record->is_active,
            'events' => EngagementEvent::query()->where('audit_engagement_id', $record->audit_engagement_id)->where('subject_type', 'FIELDWORK_RECORD')->where('subject_id', $record->id)->with('actor:id,employee_id,name,initials')->latest('created_at')->get()->map(fn (EngagementEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action,
                'fromStatus' => $event->from_status,
                'toStatus' => $event->to_status,
                'subjectVersion' => $event->subject_version,
                'comment' => $event->comment,
                'actor' => $this->user($event->actor),
                'createdAt' => $event->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function load(AemsFieldworkRecord $record): AemsFieldworkRecord
    {
        return $record->fresh([
            'procedure.program', 'auditArea', 'auditFocus', 'preparer', 'submitter', 'reviewer', 'finalizer',
            'versions.creator', 'versions.auditArea', 'versions.auditFocus', 'versions.participants.user', 'versions.participants.office',
            'versions.workingPaperLinks.workingPaper', 'versions.workingPaperLinks.workingPaperVersion', 'versions.evidenceLinks.evidence',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createVersion(Request $request, AemsFieldworkRecord $record, array $attributes, array $links, int $number): AemsFieldworkRecordVersion
    {
        $version = AemsFieldworkRecordVersion::query()->create([
            'fieldwork_record_id' => $record->id,
            'version_number' => $number,
            'record_type' => $attributes['recordType'],
            'audit_program_procedure_id' => $record->audit_program_procedure_id,
            'audit_area_id' => (int) $attributes['auditAreaId'],
            'audit_focus_id' => (int) $attributes['auditFocusId'],
            'performed_on' => $attributes['performedOn'],
            'location' => $attributes['location'] ?? null,
            'objective' => $attributes['objective'] ?? null,
            'procedure_performed' => $attributes['procedurePerformed'],
            'population_description' => $attributes['populationDescription'] ?? null,
            'sample_description' => $attributes['sampleDescription'] ?? null,
            'analysis' => $attributes['analysis'] ?? null,
            'result' => $attributes['result'],
            'conclusion' => $attributes['conclusion'],
            'execution_status' => $attributes['executionStatus'],
            'related_tasks' => array_values($attributes['relatedTasks'] ?? []),
            'related_records' => array_values($attributes['relatedRecords'] ?? []),
            'change_reason' => $attributes['changeReason'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        foreach ($links['participants'] as $participant) {
            $version->participants()->create($participant);
        }
        foreach ($links['workingPapers'] as $link) {
            $version->workingPaperLinks()->create($link);
        }
        foreach ($links['evidence'] as $evidenceId) {
            $version->evidenceLinks()->create(['audit_evidence_id' => $evidenceId]);
        }

        return $version;
    }

    /** @return array{participants: list<array<string, mixed>>, workingPapers: list<array<string, mixed>>, evidence: list<int>} */
    private function traceability(Request $request, AuditEngagement $engagement, AuditProgramProcedure $procedure, array $attributes): array
    {
        $participants = [];
        foreach ($attributes['participants'] ?? [] as $participant) {
            $user = null;
            if (! empty($participant['userId'])) {
                $user = $engagement->teamMembers()->where('user_id', (int) $participant['userId'])->where('is_active', true)->whereNull('ended_at')->with('user')->first();
                if (! $user) {
                    throw ValidationException::withMessages(['participants' => ['Participants with a user ID must be active members of this engagement team.']]);
                }
            }
            $name = trim((string) ($participant['participantName'] ?? $user?->user?->name ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages(['participants' => ['Each participant requires a name or an active team member user ID.']]);
            }
            $participants[] = [
                'user_id' => $user?->user_id,
                'office_id' => $participant['officeId'] ?? $user?->user?->office_id,
                'participant_name' => $name,
                'participant_role' => $participant['participantRole'] ?? null,
            ];
        }
        $requestedLinks = collect($attributes['workingPaperLinks'] ?? [])->map(fn (array $link): array => [
            'workingPaperId' => (int) $link['workingPaperId'],
            'workingPaperVersionId' => isset($link['workingPaperVersionId']) ? (int) $link['workingPaperVersionId'] : null,
        ]);
        $requestedIds = collect($attributes['workingPaperIds'] ?? [])->map(fn ($id): int => (int) $id)->map(fn (int $id): array => ['workingPaperId' => $id, 'workingPaperVersionId' => null]);
        $requestedLinks = $requestedLinks->concat($requestedIds)->unique(fn (array $link): string => $link['workingPaperId'].'-'.($link['workingPaperVersionId'] ?? 'latest'))->values();
        $workingPapers = WorkingPaper::query()->visibleTo($request->user())->where('audit_engagement_id', $engagement->id)->whereIn('id', $requestedLinks->pluck('workingPaperId'))->with('latestVersion')->get()->keyBy('id');
        if ($workingPapers->count() !== $requestedLinks->pluck('workingPaperId')->unique()->count()) {
            throw ValidationException::withMessages(['workingPaperIds' => ['Every Working Paper must belong to this engagement and be visible to the actor.']]);
        }
        $workingPaperLinks = $requestedLinks->map(function (array $link) use ($workingPapers, $procedure): array {
            $paper = $workingPapers->get($link['workingPaperId']);
            if ((int) $paper->audit_program_procedure_id !== (int) $procedure->id) {
                throw ValidationException::withMessages(['workingPaperIds' => ['A linked Working Paper must belong to the selected procedure.']]);
            }
            $version = $link['workingPaperVersionId'] ? $paper->versions()->whereKey($link['workingPaperVersionId'])->first() : $paper->latestVersion;
            if (! $version) {
                throw ValidationException::withMessages(['workingPaperIds' => ['Each linked Working Paper must have a content version.']]);
            }
            return ['working_paper_id' => $paper->id, 'working_paper_version_id' => $version->id];
        })->values()->all();
        $evidenceIds = collect($attributes['evidenceIds'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values();
        $evidence = AuditEvidence::query()->visibleTo($request->user())->where('audit_engagement_id', $engagement->id)->whereIn('id', $evidenceIds)->whereNot('status', 'VOIDED')->get()->modelKeys();
        if (count($evidence) !== $evidenceIds->count()) {
            throw ValidationException::withMessages(['evidenceIds' => ['Every evidence record must belong to this engagement, be visible, and not be voided.']]);
        }

        return ['participants' => $participants, 'workingPapers' => $workingPaperLinks, 'evidence' => array_map('intval', $evidence)];
    }

    /** @return array{0: AuditArea, 1: AuditFocus} */
    private function classifications(AuditEngagement $engagement, array $attributes): array
    {
        $area = $engagement->auditAreas()->whereKey((int) $attributes['auditAreaId'])->first();
        $focus = $engagement->auditFocuses()->whereKey((int) $attributes['auditFocusId'])->first();
        if (! $area || ! $focus || (int) $focus->audit_area_id !== (int) $area->id) {
            throw ValidationException::withMessages(['auditFocusId' => ['The Audit Area and Focus must be linked to this engagement and to each other.']]);
        }

        return [$area, $focus];
    }

    private function procedure(AuditEngagement $engagement, int $procedureId): AuditProgramProcedure
    {
        $procedure = AuditProgramProcedure::query()->whereKey($procedureId)->whereHas('program', fn ($program) => $program->where('audit_engagement_id', $engagement->id)->where('is_current_revision', true)->where('status', 'ACTIVE'))->with('program')->lockForUpdate()->first();
        if (! $procedure) {
            throw ValidationException::withMessages(['procedureId' => ['Choose a procedure from the current active Audit Program.']]);
        }

        return $procedure;
    }

    private function ensureActorMayExecute(Request $request, AuditEngagement $engagement, AuditProgramProcedure $procedure): void
    {
        if ($request->user()->hasPermission('aems.fieldwork.manage')) return;
        $role = $engagement->teamMembers()->where('user_id', $request->user()->id)->where('is_active', true)->whereNull('ended_at')->value('assignment_role_code');
        if ($role === 'AUDITOR' && (int) $procedure->assigned_to !== (int) $request->user()->id) {
            throw ValidationException::withMessages(['procedureId' => ['An Auditor may record fieldwork only for their assigned procedure.']]);
        }
        if (! in_array($role, ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'], true)) {
            throw ValidationException::withMessages(['procedureId' => ['Only the assigned auditor, Team Leader, or Supervisor may record fieldwork.']]);
        }
    }

    private function lockRecord(AuditEngagement $engagement, AemsFieldworkRecord $record, int $lockVersion): AemsFieldworkRecord
    {
        $locked = AemsFieldworkRecord::query()->lockForUpdate()->findOrFail($record->id);
        if ((int) $locked->audit_engagement_id !== (int) $engagement->id || $locked->trashed()) {
            throw ValidationException::withMessages(['record' => ['The Fieldwork Record does not belong to this engagement.']]);
        }
        if ((int) $locked->lock_version !== $lockVersion) {
            throw ValidationException::withMessages(['lockVersion' => ['This Fieldwork Record changed in another session. Refresh before continuing.']]);
        }

        return $locked;
    }

    private function ensureIndependentReviewer(Request $request, AemsFieldworkRecord $record): void
    {
        if ((int) $request->user()->id === (int) $record->prepared_by
            && ! $this->access->mayUseSingleCiasHeadReviewException($request->user(), 'aems.fieldwork.finalize')) {
            throw ValidationException::withMessages(['reviewer' => ['The preparer cannot independently review or finalize the Fieldwork Record.']]);
        }
        if ($record->reviewer_id && (int) $record->reviewer_id !== (int) $request->user()->id) {
            return;
        }
        if (! $record->reviewer_id) {
            throw ValidationException::withMessages(['reviewer' => ['An independent review must be recorded before this action.']]);
        }
    }

    private function ensureNotPreparer(Request $request, AemsFieldworkRecord $record): void
    {
        if ((int) $request->user()->id === (int) $record->prepared_by
            && ! $this->access->mayUseSingleCiasHeadReviewException($request->user(), 'aems.fieldwork.review')) {
            throw ValidationException::withMessages(['reviewer' => ['The preparer cannot independently review or return the Fieldwork Record.']]);
        }
    }

    private function ensureReadyForReview(AemsFieldworkRecordVersion $version): void
    {
        if ($version->execution_status !== 'COMPLETED') {
            throw ValidationException::withMessages(['executionStatus' => ['Fieldwork execution must be completed before submission.']]);
        }
        if ($version->participants->isEmpty()) {
            throw ValidationException::withMessages(['participants' => ['At least one participant is required.']]);
        }
        if ($version->workingPaperLinks->isEmpty()) {
            throw ValidationException::withMessages(['workingPaperIds' => ['At least one Working Paper link is required for traceability.']]);
        }
        if ($version->evidenceLinks->isEmpty()) {
            throw ValidationException::withMessages(['evidenceIds' => ['At least one Evidence link is required for traceability.']]);
        }
    }

    private function ensureReadyForFinalization(AemsFieldworkRecordVersion $version): void
    {
        $this->ensureReadyForReview($version);
        foreach ($version->workingPaperLinks as $link) {
            if (! in_array($link->workingPaper?->status, ['APPROVED'], true)) {
                throw ValidationException::withMessages(['workingPaperIds' => ['All linked Working Papers must be approved before Fieldwork Record finalization.']]);
            }
        }
        foreach ($version->evidenceLinks as $link) {
            if (! in_array($link->evidence?->status, ['VERIFIED', 'LOCKED'], true)) {
                throw ValidationException::withMessages(['evidenceIds' => ['All linked Evidence must be verified or locked before Fieldwork Record finalization.']]);
            }
        }
    }

    private function completeProcedureFromVersion(Request $request, AemsFieldworkRecord $record, AemsFieldworkRecordVersion $version): void
    {
        $procedure = AuditProgramProcedure::query()->lockForUpdate()->findOrFail($record->audit_program_procedure_id);
        $procedure->update([
            'fieldwork_status' => 'COMPLETED',
            'fieldwork_results' => $version->result,
            'fieldwork_conclusion' => $version->conclusion,
            'fieldwork_review_state' => 'FINALIZED',
            'related_tasks' => $version->related_tasks,
            'related_records' => $version->related_records,
            'fieldwork_completed_at' => now(),
            'fieldwork_completed_by' => $request->user()->id,
            'lock_version' => $procedure->lock_version + 1,
        ]);
        $procedure->program()->increment('lock_version');
    }

    private function markProcedureInProgress(AuditProgramProcedure $procedure): void
    {
        if ($procedure->fieldwork_status === 'COMPLETED') {
            return;
        }

        $procedure->update([
            'fieldwork_status' => 'IN_PROGRESS',
            'fieldwork_review_state' => 'DRAFT',
            'lock_version' => $procedure->lock_version + 1,
        ]);
        $procedure->program()->increment('lock_version');
    }

    private function updateProcedureReviewState(int $procedureId, string $state): void
    {
        $procedure = AuditProgramProcedure::query()->lockForUpdate()->findOrFail($procedureId);
        if ($procedure->fieldwork_review_state === $state) {
            return;
        }

        $procedure->update([
            'fieldwork_review_state' => $state,
            'lock_version' => $procedure->lock_version + 1,
        ]);
        $procedure->program()->increment('lock_version');
    }

    private function reviseLocked(Request $request, AuditEngagement $engagement, AemsFieldworkRecord $record, AemsFieldworkRecordVersion $source, ?string $reason): AemsFieldworkRecord
    {
        if ($record->status !== 'FINALIZED') {
            throw ValidationException::withMessages(['status' => ['Only a finalized Fieldwork Record can start a formal revision.']]);
        }
        if (mb_strlen(trim((string) $reason)) < 5) {
            throw ValidationException::withMessages(['comment' => ['A revision reason is required.']]);
        }
        $number = $record->current_version_number + 1;
        $version = AemsFieldworkRecordVersion::query()->create([
            ...$source->only([
                'record_type', 'audit_program_procedure_id', 'audit_area_id', 'audit_focus_id', 'performed_on',
                'location', 'objective', 'procedure_performed', 'population_description', 'sample_description',
                'analysis', 'result', 'conclusion', 'execution_status', 'related_tasks', 'related_records',
            ]),
            'fieldwork_record_id' => $record->id,
            'version_number' => $number,
            'change_reason' => $reason,
            'created_by' => $request->user()->id,
        ]);
        foreach ($source->participants as $participant) {
            $version->participants()->create($participant->only(['user_id', 'office_id', 'participant_name', 'participant_role']));
        }
        foreach ($source->workingPaperLinks as $link) {
            $version->workingPaperLinks()->create($link->only(['working_paper_id', 'working_paper_version_id']));
        }
        foreach ($source->evidenceLinks as $link) {
            $version->evidenceLinks()->create(['audit_evidence_id' => $link->audit_evidence_id]);
        }
        $record->update([
            'status' => 'DRAFT',
            'current_version_number' => $number,
            'prepared_by' => $request->user()->id,
            'submitted_by' => null,
            'submitted_at' => null,
            'reviewer_id' => null,
            'reviewed_at' => null,
            'reviewer_notes' => null,
            'finalized_by' => null,
            'finalized_at' => null,
            'lock_version' => $record->lock_version + 1,
        ]);
        $procedure = AuditProgramProcedure::query()->lockForUpdate()->findOrFail($record->audit_program_procedure_id);
        $procedure->update([
            'fieldwork_status' => 'IN_PROGRESS',
            'fieldwork_review_state' => 'DRAFT',
            'lock_version' => $procedure->lock_version + 1,
        ]);
        $procedure->program()->increment('lock_version');
        $this->event($request, $engagement, $record, $version, 'REVISE', 'FINALIZED', 'DRAFT', ['status' => 'FINALIZED', 'versionNumber' => $source->version_number], ['status' => 'DRAFT', 'versionNumber' => $number], $reason);
        $this->support->audit($request, 'aems.fieldwork.revision_started', $engagement, ['status' => 'FINALIZED', 'versionNumber' => $source->version_number], ['status' => 'DRAFT', 'versionNumber' => $number], ['fieldworkRecordId' => $record->id, 'reason' => $reason]);

        return $record;
    }

    private function nextCode(AuditEngagement $engagement): string
    {
        $sequence = AemsFieldworkRecord::withTrashed()->where('audit_engagement_id', $engagement->id)->count() + 1;
        do {
            $code = sprintf('FWR-%s-%03d', $engagement->engagement_code, $sequence++);
        } while (AemsFieldworkRecord::withTrashed()->where('audit_engagement_id', $engagement->id)->where('record_code', $code)->exists());

        return $code;
    }

    /** @return array<string, mixed> */
    private function procedureData(AuditProgramProcedure $procedure): array
    {
        return [
            'id' => $procedure->id,
            'procedureCode' => $procedure->procedure_code,
            'objective' => $procedure->objective,
            'description' => $procedure->procedure_description,
            'status' => $procedure->status,
            'fieldworkStatus' => $procedure->fieldwork_status,
            'fieldworkResults' => $procedure->fieldwork_results,
            'fieldworkConclusion' => $procedure->fieldwork_conclusion,
            'fieldworkReviewState' => $procedure->fieldwork_review_state,
            'relatedTasks' => $procedure->related_tasks ?? [],
            'relatedRecords' => $procedure->related_records ?? [],
            'fieldworkCompletedAt' => $procedure->fieldwork_completed_at?->toIso8601String(),
            'fieldworkCompletedBy' => $this->user($procedure->fieldworkCompletedBy),
            'finalizedFieldworkRecords' => (int) ($procedure->finalized_fieldwork_records_count ?? $procedure->fieldworkRecords()->where('status', 'FINALIZED')->count()),
            'targetDate' => $procedure->target_date?->toDateString(),
            'program' => $procedure->program?->only(['id', 'program_code', 'title', 'status']),
            'assignee' => $this->user($procedure->assignee),
        ];
    }

    private function versionData(AemsFieldworkRecordVersion $version): array
    {
        return [
            'id' => $version->id,
            'versionNumber' => $version->version_number,
            'recordType' => $version->record_type,
            'procedureId' => $version->audit_program_procedure_id,
            'auditArea' => $version->auditArea?->only(['id', 'code', 'name']),
            'auditFocus' => $version->auditFocus?->only(['id', 'code', 'name']),
            'performedOn' => $version->performed_on?->toDateString(),
            'location' => $version->location,
            'objective' => $version->objective,
            'procedurePerformed' => $version->procedure_performed,
            'populationDescription' => $version->population_description,
            'sampleDescription' => $version->sample_description,
            'analysis' => $version->analysis,
            'result' => $version->result,
            'conclusion' => $version->conclusion,
            'executionStatus' => $version->execution_status,
            'relatedTasks' => $version->related_tasks ?? [],
            'relatedRecords' => $version->related_records ?? [],
            'reviewerNotes' => $version->reviewer_notes,
            'changeReason' => $version->change_reason,
            'createdBy' => $this->user($version->creator),
            'createdAt' => $version->created_at?->toIso8601String(),
            'participants' => $version->participants->map(fn (AemsFieldworkRecordParticipant $participant): array => [
                'id' => $participant->id,
                'userId' => $participant->user_id,
                'name' => $participant->participant_name,
                'role' => $participant->participant_role,
                'office' => $participant->office?->only(['id', 'code', 'name']),
            ])->values(),
            'workingPapers' => $version->workingPaperLinks->map(fn (AemsFieldworkWorkingPaperLink $link): array => [
                'id' => $link->working_paper_id,
                'workingPaperVersionId' => $link->working_paper_version_id,
                'code' => $link->workingPaper?->working_paper_code,
                'title' => $link->workingPaper?->title,
                'status' => $link->workingPaper?->status,
            ])->values(),
            'evidence' => $version->evidenceLinks->map(fn (AemsFieldworkEvidenceLink $link): array => [
                'id' => $link->audit_evidence_id,
                'code' => $link->evidence?->evidence_code,
                'title' => $link->evidence?->title,
                'status' => $link->evidence?->status,
                'documentVersionId' => $link->evidence?->document_version_id,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function auditValues(AemsFieldworkRecord $record, AemsFieldworkRecordVersion $version): array
    {
        return ['recordCode' => $record->record_code, 'recordType' => $record->record_type, 'status' => $record->status, 'versionNumber' => $version->version_number, 'procedureId' => $record->audit_program_procedure_id, 'executionStatus' => $version->execution_status];
    }

    private function event(Request $request, AuditEngagement $engagement, AemsFieldworkRecord $record, AemsFieldworkRecordVersion $version, string $action, ?string $from, ?string $to, ?array $old, ?array $new, ?string $comment = null): void
    {
        $documentIds = $version->evidenceLinks->map(fn (AemsFieldworkEvidenceLink $link): ?int => $link->evidence?->document_version_id)->filter()->values()->all();
        $this->support->event($request, $engagement, 'FIELDWORK_'.$action, $from, $to, $old, $new, $comment, 'FIELDWORK_RECORD', $record->id, $version->version_number, $record->record_code, $record->record_family_uuid, $documentIds ?: null);
    }

    /** @return array<string, mixed>|null */
    private function user(mixed $user): ?array
    {
        return $user ? ['id' => $user->id, 'employeeId' => $user->employee_id, 'name' => $user->name, 'initials' => $user->initials] : null;
    }
}
