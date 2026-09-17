<?php

namespace App\Services;

use App\Models\AemsDialogueDueProcess;
use App\Models\AemsDueProcessAttachment;
use App\Models\AemsEngagementTask;
use App\Models\AemsEngagementTaskEvent;
use App\Models\AemsEscalationCandidate;
use App\Models\AemsReviewNote;
use App\Models\AemsReviewNoteAttachment;
use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\DocumentVersion;
use App\Models\EntryConference;
use App\Models\ExitConference;
use App\Models\ManagementResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Owns AEMS-7A operational tasks, review notes, due process, and reviewable
 * escalation candidates. It never makes a final professional decision or
 * issues an escalation notice automatically.
 */
class AemsWorkQueueService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly NotificationService $notifications,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(Request $request, AuditEngagement $engagement): array
    {
        $this->authorizeView($request, $engagement);
        $user = $request->user();
        $tasks = AemsEngagementTask::query()
            ->where('audit_engagement_id', $engagement->id)
            ->with(['assignee:id,name,employee_id', 'assignedOffice:id,code,name', 'finding:id,finding_code,title', 'entryConference:id,conference_code', 'exitConference:id,conference_code'])
            ->when($user->hasPermission('access.auditee_scope'), fn ($query) => $query->where(function ($scope) use ($user): void {
                $scope->where('assigned_to', $user->id)->orWhere('assigned_office_id', $user->office_id);
            }))
            ->orderByRaw("CASE WHEN status IN ('COMPLETED','CANCELLED') THEN 1 ELSE 0 END")
            ->orderBy('due_at')
            ->get();
        $notes = AemsReviewNote::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('is_current_revision', true)
            ->with(['creator:id,name,employee_id', 'finalizer:id,name,employee_id', 'finding:id,finding_code,title', 'entryConference:id,conference_code', 'exitConference:id,conference_code', 'task:id,task_code,title', 'attachments.documentVersion', 'attachments.uploader:id,name,employee_id'])
            ->when($user->hasPermission('access.auditee_scope'), fn ($query) => $query->whereHas('finding', fn ($finding) => $finding->visibleTo($user)))
            ->latest('updated_at')->get();
        $dueProcess = AemsDialogueDueProcess::query()
            ->where('audit_engagement_id', $engagement->id)
            ->with(['finding:id,finding_code,title', 'response:id,response_code', 'actor:id,name,employee_id', 'attachments.documentVersion', 'attachments.uploader:id,name,employee_id'])
            ->when($user->hasPermission('access.auditee_scope'), fn ($query) => $query->whereHas('finding', fn ($finding) => $finding->visibleTo($user)))
            ->latest('recorded_at')->get();
        $candidates = AemsEscalationCandidate::query()
            ->where('audit_engagement_id', $engagement->id)
            ->with(['finding:id,finding_code,title', 'task:id,task_code,title', 'entryConference:id,conference_code', 'exitConference:id,conference_code', 'reviewer:id,name,employee_id'])
            ->when($user->hasPermission('access.auditee_scope'), fn ($query) => $query->whereRaw('1 = 0'))
            ->latest('detected_at')->get();

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
            ],
            'tasks' => $tasks->map(fn (AemsEngagementTask $task): array => $this->taskData($task))->values(),
            'reviewNotes' => $notes->map(fn (AemsReviewNote $note): array => $this->reviewNoteData($note))->values(),
            'dueProcess' => $dueProcess->map(fn (AemsDialogueDueProcess $item): array => $this->dueProcessData($item))->values(),
            'escalationCandidates' => $candidates->map(fn (AemsEscalationCandidate $candidate): array => $this->candidateData($candidate))->values(),
            'references' => [
                'taskStatuses' => AemsEngagementTask::STATUSES,
                'reviewNoteStatuses' => AemsReviewNote::STATUSES,
                'dueProcessTypes' => AemsDialogueDueProcess::TYPES,
                'candidateStatuses' => AemsEscalationCandidate::STATUSES,
            ],
        ];
    }

    public function createTask(Request $request, AuditEngagement $engagement, array $attributes): AemsEngagementTask
    {
        $this->authorize($request, $engagement, 'aems.task.create');
        $this->validateTaskLinks($engagement, $attributes);
        return DB::transaction(function () use ($request, $engagement, $attributes): AemsEngagementTask {
            $task = AemsEngagementTask::query()->create([
                'audit_engagement_id' => $engagement->id,
                'task_code' => $this->nextTaskCode($engagement),
                'task_type' => strtoupper($attributes['taskType']),
                'title' => trim($attributes['title']),
                'description' => $this->nullable($attributes['description'] ?? null),
                'subject_type' => $attributes['subjectType'] ?? null,
                'subject_id' => $attributes['subjectId'] ?? null,
                'audit_finding_id' => $attributes['findingId'] ?? null,
                'entry_conference_id' => $attributes['entryConferenceId'] ?? null,
                'exit_conference_id' => $attributes['exitConferenceId'] ?? null,
                'assigned_to' => $attributes['assignedTo'] ?? null,
                'assigned_office_id' => $attributes['assignedOfficeId'] ?? null,
                'status' => 'OPEN',
                'due_at' => $attributes['dueAt'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'lock_version' => 1,
            ]);
            $this->taskEvent($request, $engagement, $task, 'CREATED', null, 'OPEN', $task->description, $this->taskSnapshot($task));
            $this->record($request, $engagement, 'aems.task.created', null, $this->taskSnapshot($task), null, 'AEMS_TASK', $task->id, 1, $task->task_code);
            $this->notifyTask($request, $engagement, $task, 'ASSIGNED');
            return $task->fresh($this->taskRelations());
        });
    }

    public function updateTask(Request $request, AuditEngagement $engagement, AemsEngagementTask $task, array $attributes): AemsEngagementTask
    {
        $this->authorize($request, $engagement, 'aems.task.update');
        $this->validateTaskLinks($engagement, $attributes, true);
        return DB::transaction(function () use ($request, $engagement, $task, $attributes): AemsEngagementTask {
            $locked = $this->lockTask($engagement, $task, (int) $attributes['lockVersion']);
            if (in_array($locked->status, ['COMPLETED', 'CANCELLED'], true)) throw ValidationException::withMessages(['task' => ['Terminal tasks are immutable.']]);
            $before = $this->taskSnapshot($locked);
            $locked->update([
                'task_type' => array_key_exists('taskType', $attributes) ? strtoupper($attributes['taskType']) : $locked->task_type,
                'title' => array_key_exists('title', $attributes) ? trim($attributes['title']) : $locked->title,
                'description' => array_key_exists('description', $attributes) ? $this->nullable($attributes['description']) : $locked->description,
                'due_at' => array_key_exists('dueAt', $attributes) ? $attributes['dueAt'] : $locked->due_at,
                'assigned_to' => array_key_exists('assignedTo', $attributes) ? $attributes['assignedTo'] : $locked->assigned_to,
                'assigned_office_id' => array_key_exists('assignedOfficeId', $attributes) ? $attributes['assignedOfficeId'] : $locked->assigned_office_id,
                'updated_by' => $request->user()->id,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->taskEvent($request, $engagement, $locked, 'UPDATED', $locked->getOriginal('status'), $locked->status, $locked->description, $this->taskSnapshot($locked));
            $this->record($request, $engagement, 'aems.task.updated', $before, $this->taskSnapshot($locked), null, 'AEMS_TASK', $locked->id, $locked->lock_version, $locked->task_code);
            if ($locked->wasChanged(['assigned_to', 'assigned_office_id'])) $this->notifyTask($request, $engagement, $locked, 'REASSIGNED');
            return $locked->fresh($this->taskRelations());
        });
    }

    public function transitionTask(Request $request, AuditEngagement $engagement, AemsEngagementTask $task, string $action, int $lockVersion, ?string $comment): AemsEngagementTask
    {
        $permission = match (strtoupper($action)) {
            'START' => 'aems.task.update',
            'COMPLETE' => 'aems.task.complete',
            'CANCEL' => 'aems.task.cancel',
            'REOPEN' => 'aems.task.reopen',
            'ESCALATE' => 'aems.task.escalate',
            default => throw ValidationException::withMessages(['action' => ['Unsupported task action.']]),
        };
        $this->authorize($request, $engagement, $permission);
        return DB::transaction(function () use ($request, $engagement, $task, $action, $lockVersion, $comment): AemsEngagementTask {
            $locked = $this->lockTask($engagement, $task, $lockVersion);
            $action = strtoupper($action);
            $from = $locked->status;
            $to = match ($action) {
                'START' => $from === 'OPEN' ? 'IN_PROGRESS' : null,
                'COMPLETE' => in_array($from, ['OPEN', 'IN_PROGRESS'], true) ? 'COMPLETED' : null,
                'CANCEL' => in_array($from, ['OPEN', 'IN_PROGRESS'], true) ? 'CANCELLED' : null,
                'REOPEN' => in_array($from, ['COMPLETED', 'CANCELLED'], true) ? 'OPEN' : null,
                'ESCALATE' => $from === 'OPEN' || $from === 'IN_PROGRESS' ? $from : null,
            };
            if (! $to) throw ValidationException::withMessages(['action' => ["{$action} is not available while the task is {$from}."]]);
            $before = $this->taskSnapshot($locked);
            $changes = ['status' => $to, 'updated_by' => $request->user()->id, 'lock_version' => $locked->lock_version + 1];
            if ($action === 'START') $changes['started_at'] = $locked->started_at ?? now();
            if ($action === 'COMPLETE') $changes += ['completed_at' => now(), 'completed_by' => $request->user()->id, 'completion_comment' => $this->nullable($comment)];
            if ($action === 'REOPEN') $changes += ['completed_at' => null, 'completed_by' => null, 'completion_comment' => null];
            AemsEngagementTask::allowControlledTransition(fn () => $locked->update($changes));
            $this->taskEvent($request, $engagement, $locked, $action, $from, $to, $comment, $this->taskSnapshot($locked));
            $this->record($request, $engagement, 'aems.task.'.strtolower($action), $before, $this->taskSnapshot($locked), $comment, 'AEMS_TASK', $locked->id, $locked->lock_version, $locked->task_code);
            if ($action === 'ESCALATE') $this->createCandidate($request, $engagement, 'TASK_OVERDUE', $locked, $locked->finding, null, 'Task requires escalation review.', ['taskCode' => $locked->task_code]);
            $this->notifyTask($request, $engagement, $locked, $action);
            return $locked->fresh($this->taskRelations());
        });
    }

    public function createReviewNote(Request $request, AuditEngagement $engagement, array $attributes): AemsReviewNote
    {
        $this->authorize($request, $engagement, 'aems.review-note.create');
        $this->validateNoteLinks($engagement, $attributes);
        return DB::transaction(function () use ($request, $engagement, $attributes): AemsReviewNote {
            $note = AemsReviewNote::query()->create([
                'note_family_uuid' => (string) Str::uuid(),
                'version_number' => 1,
                'is_current_revision' => true,
                'audit_engagement_id' => $engagement->id,
                'audit_finding_id' => $attributes['findingId'] ?? null,
                'entry_conference_id' => $attributes['entryConferenceId'] ?? null,
                'exit_conference_id' => $attributes['exitConferenceId'] ?? null,
                'aems_engagement_task_id' => $attributes['taskId'] ?? null,
                'note_code' => $this->nextNoteCode($engagement),
                'note_type' => strtoupper($attributes['noteType'] ?? 'REVIEW'),
                'content' => trim($attributes['content']),
                'status' => 'DRAFT',
                'created_by' => $request->user()->id,
                'lock_version' => 1,
            ]);
            $this->record($request, $engagement, 'aems.review-note.created', null, $this->reviewNoteSnapshot($note), null, 'AEMS_REVIEW_NOTE', $note->id, 1, $note->note_code, $note->note_family_uuid);
            return $note->fresh($this->noteRelations());
        });
    }

    public function updateReviewNote(Request $request, AuditEngagement $engagement, AemsReviewNote $note, array $attributes): AemsReviewNote
    {
        $this->authorize($request, $engagement, 'aems.review-note.update');
        return DB::transaction(function () use ($request, $engagement, $note, $attributes): AemsReviewNote {
            $locked = $this->lockNote($engagement, $note, (int) $attributes['lockVersion']);
            if ($locked->status !== 'DRAFT' || (int) $locked->created_by !== (int) $request->user()->id) throw ValidationException::withMessages(['note' => ['Only your current draft review note can be edited.']]);
            $before = $this->reviewNoteSnapshot($locked);
            $locked->update(['content' => trim($attributes['content']), 'note_type' => strtoupper($attributes['noteType'] ?? $locked->note_type), 'lock_version' => $locked->lock_version + 1]);
            $this->record($request, $engagement, 'aems.review-note.updated', $before, $this->reviewNoteSnapshot($locked), null, 'AEMS_REVIEW_NOTE', $locked->id, $locked->version_number, $locked->note_code, $locked->note_family_uuid);
            return $locked->fresh($this->noteRelations());
        });
    }

    public function transitionReviewNote(Request $request, AuditEngagement $engagement, AemsReviewNote $note, string $action, int $lockVersion, ?string $comment): AemsReviewNote
    {
        $permission = strtoupper($action) === 'FINALIZE' ? 'aems.review-note.finalize' : 'aems.review-note.update';
        $this->authorize($request, $engagement, $permission);
        return DB::transaction(function () use ($request, $engagement, $note, $action, $lockVersion, $comment): AemsReviewNote {
            $locked = $this->lockNote($engagement, $note, $lockVersion);
            $action = strtoupper($action);
            if ($action === 'FINALIZE') {
                if ($locked->status !== 'DRAFT'
                    || ((int) $locked->created_by === (int) $request->user()->id
                        && ! $this->access->mayUseSingleCiasHeadReviewException($request->user(), 'aems.review-note.finalize'))) throw ValidationException::withMessages(['note' => ['A review note must be a draft prepared by another actor before finalization.']]);
                $locked->update(['status' => 'FINALIZED', 'finalized_by' => $request->user()->id, 'finalized_at' => now(), 'lock_version' => $locked->lock_version + 1]);
            } elseif ($action === 'VOID') {
                if ($locked->status !== 'DRAFT') throw ValidationException::withMessages(['note' => ['Only draft review notes can be voided.']]);
                $locked->update(['status' => 'VOIDED', 'lock_version' => $locked->lock_version + 1]);
            } else throw ValidationException::withMessages(['action' => ['Unsupported review-note action.']]);
            $this->record($request, $engagement, 'aems.review-note.'.strtolower($action), null, $this->reviewNoteSnapshot($locked), $comment, 'AEMS_REVIEW_NOTE', $locked->id, $locked->version_number, $locked->note_code, $locked->note_family_uuid);
            return $locked->fresh($this->noteRelations());
        });
    }

    public function reviseReviewNote(Request $request, AuditEngagement $engagement, AemsReviewNote $note, int $lockVersion, string $reason): AemsReviewNote
    {
        $this->authorize($request, $engagement, 'aems.review-note.revise');
        if (mb_strlen(trim($reason)) < 5) throw ValidationException::withMessages(['reason' => ['A revision reason is required.']]);
        return DB::transaction(function () use ($request, $engagement, $note, $lockVersion, $reason): AemsReviewNote {
            $source = $this->lockNote($engagement, $note, $lockVersion);
            if ($source->status !== 'FINALIZED' || ! $source->is_current_revision) throw ValidationException::withMessages(['note' => ['Only the current finalized review note can be revised.']]);
            DB::table('aems_review_notes')->whereKey($source->id)->update(['is_current_revision' => false, 'updated_at' => now()]);
            $revision = AemsReviewNote::query()->create([
                'note_family_uuid' => $source->note_family_uuid,
                'version_number' => $source->version_number + 1,
                'supersedes_note_id' => $source->id,
                'is_current_revision' => true,
                'audit_engagement_id' => $source->audit_engagement_id,
                'audit_finding_id' => $source->audit_finding_id,
                'entry_conference_id' => $source->entry_conference_id,
                'exit_conference_id' => $source->exit_conference_id,
                'aems_engagement_task_id' => $source->aems_engagement_task_id,
                'note_code' => $source->note_code,
                'note_type' => $source->note_type,
                'content' => $source->content,
                'status' => 'DRAFT',
                'revision_reason' => trim($reason),
                'created_by' => $request->user()->id,
                'lock_version' => 1,
            ]);
            foreach ($source->attachments as $attachment) AemsReviewNoteAttachment::query()->create(['aems_review_note_id' => $revision->id, 'attachment_code' => $attachment->attachment_code.'-V'.$revision->version_number, 'caption' => $attachment->caption, 'document_version_id' => $attachment->document_version_id, 'uploaded_by' => $request->user()->id]);
            $this->record($request, $engagement, 'aems.review-note.revised', $this->reviewNoteSnapshot($source), $this->reviewNoteSnapshot($revision), $reason, 'AEMS_REVIEW_NOTE', $revision->id, $revision->version_number, $revision->note_code, $revision->note_family_uuid);
            return $revision->fresh($this->noteRelations());
        });
    }

    public function attachReviewNote(Request $request, AuditEngagement $engagement, AemsReviewNote $note, int $documentVersionId, ?string $caption, int $lockVersion): AemsReviewNoteAttachment
    {
        $this->authorize($request, $engagement, 'aems.review-note.attach');
        $this->ensureDocumentVersion($documentVersionId);
        return DB::transaction(function () use ($request, $engagement, $note, $lockVersion, $caption, $documentVersionId): AemsReviewNoteAttachment {
            $locked = $this->lockNote($engagement, $note, $lockVersion);
            if ($locked->status !== 'DRAFT') throw ValidationException::withMessages(['note' => ['Attachments can only be added to a draft review note.']]);
            $attachment = AemsReviewNoteAttachment::query()->create(['aems_review_note_id' => $locked->id, 'attachment_code' => $locked->note_code.'-ATT-'.(AemsReviewNoteAttachment::query()->where('aems_review_note_id', $locked->id)->count() + 1), 'caption' => $this->nullable($caption), 'document_version_id' => $documentVersionId, 'uploaded_by' => $request->user()->id]);
            $locked->update(['lock_version' => $locked->lock_version + 1]);
            $this->record($request, $engagement, 'aems.review-note.attachment_added', null, ['attachmentId' => $attachment->id, 'documentVersionId' => $documentVersionId], null, 'AEMS_REVIEW_NOTE', $locked->id, $locked->version_number, $locked->note_code, $locked->note_family_uuid, [$documentVersionId]);
            return $attachment->fresh(['documentVersion', 'uploader']);
        });
    }

    public function recordDueProcess(Request $request, AuditEngagement $engagement, array $attributes, bool $internal = false): AemsDialogueDueProcess
    {
        if (! $internal) $this->authorize($request, $engagement, 'aems.due-process.create');
        $finding = AuditFinding::query()->where('audit_engagement_id', $engagement->id)->whereKey($attributes['findingId'])->firstOrFail();
        if (! $request->user()->hasPermission('access.auditee_scope')) $this->access->authorizeFindingView($request->user(), $finding);
        $type = strtoupper($attributes['eventType']);
        if (! in_array($type, AemsDialogueDueProcess::TYPES, true)) throw ValidationException::withMessages(['eventType' => ['Unsupported due-process event type.']]);
        if ($type === 'FINAL_NON_RESPONSE' && mb_strlen(trim($attributes['content'])) < 5) throw ValidationException::withMessages(['content' => ['A final non-response explanation is required.']]);
        if (! empty($attributes['responseId'])) {
            $response = ManagementResponse::query()->where('audit_finding_id', $finding->id)->find($attributes['responseId']);
            if (! $response) throw ValidationException::withMessages(['responseId' => ['The response does not belong to this finding.']]);
        }
        return DB::transaction(function () use ($request, $engagement, $finding, $attributes, $type): AemsDialogueDueProcess {
            $version = AemsDialogueDueProcess::query()->where('audit_finding_id', $finding->id)->where('event_type', $type)->max('version_number') + 1;
            $item = AemsDialogueDueProcess::query()->create(['audit_engagement_id' => $engagement->id, 'audit_finding_id' => $finding->id, 'management_response_id' => $attributes['responseId'] ?? null, 'event_code' => 'DP-'.$finding->finding_code.'-'.str_pad((string) (AemsDialogueDueProcess::query()->where('audit_finding_id', $finding->id)->count() + 1), 3, '0', STR_PAD_LEFT), 'version_number' => $version, 'event_type' => $type, 'content' => trim($attributes['content']), 'due_date' => $attributes['dueDate'] ?? null, 'actor_id' => $request->user()->id, 'metadata' => $attributes['metadata'] ?? null]);
            $this->record($request, $engagement, 'aems.due-process.'.strtolower($type), null, $this->dueProcessSnapshot($item), $item->content, 'AEMS_DUE_PROCESS', $item->id, $item->version_number, $item->event_code, null);
            if (in_array($type, ['FINAL_NON_RESPONSE', 'ESCALATION_RECOMMENDED'], true)) $this->createCandidate($request, $engagement, 'MANAGEMENT_RESPONSE_NON_RESPONSE', null, $finding, null, 'Management response due process requires escalation review.', ['findingCode' => $finding->finding_code, 'dueProcessId' => $item->id]);
            return $item->fresh($this->dueProcessRelations());
        });
    }

    public function attachDueProcess(Request $request, AuditEngagement $engagement, AemsDialogueDueProcess $item, int $documentVersionId, ?string $caption): AemsDueProcessAttachment
    {
        $this->authorize($request, $engagement, 'aems.due-process.attach');
        $this->ensureDueProcess($engagement, $item);
        $this->ensureDocumentVersion($documentVersionId);
        $attachment = AemsDueProcessAttachment::query()->create(['aems_dialogue_due_process_id' => $item->id, 'attachment_code' => $item->event_code.'-ATT-'.(AemsDueProcessAttachment::query()->where('aems_dialogue_due_process_id', $item->id)->count() + 1), 'caption' => $this->nullable($caption), 'document_version_id' => $documentVersionId, 'uploaded_by' => $request->user()->id]);
        $this->record($request, $engagement, 'aems.due-process.attachment_added', null, ['attachmentId' => $attachment->id, 'documentVersionId' => $documentVersionId], null, 'AEMS_DUE_PROCESS', $item->id, $item->version_number, $item->event_code, null, [$documentVersionId]);
        return $attachment->fresh(['documentVersion', 'uploader']);
    }

    public function reviewCandidate(Request $request, AuditEngagement $engagement, AemsEscalationCandidate $candidate, string $action, int $lockVersion, string $comment): AemsEscalationCandidate
    {
        $permission = match (strtoupper($action)) { 'ACKNOWLEDGE' => 'aems.escalation-candidate.review', 'RESOLVE' => 'aems.escalation-candidate.resolve', 'DISMISS' => 'aems.escalation-candidate.dismiss', default => throw ValidationException::withMessages(['action' => ['Unsupported escalation candidate action.']]) };
        $this->authorize($request, $engagement, $permission);
        if ((int) $candidate->audit_engagement_id !== (int) $engagement->id) abort(404);
        return DB::transaction(function () use ($request, $engagement, $candidate, $action, $lockVersion, $comment): AemsEscalationCandidate {
            $locked = AemsEscalationCandidate::query()->lockForUpdate()->findOrFail($candidate->id);
            if ((int) $locked->lock_version !== $lockVersion || $locked->status === 'RESOLVED' || $locked->status === 'DISMISSED') throw ValidationException::withMessages(['lockVersion' => ['The candidate changed or is already terminal.']]);
            $to = strtoupper($action) === 'ACKNOWLEDGE' ? 'ACKNOWLEDGED' : strtoupper($action);
            $before = $this->candidateSnapshot($locked);
            $locked->update(['status' => $to, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_comment' => trim($comment), 'lock_version' => $locked->lock_version + 1]);
            $this->record($request, $engagement, 'aems.escalation-candidate.'.strtolower($action), $before, $this->candidateSnapshot($locked), $comment, 'AEMS_ESCALATION_CANDIDATE', $locked->id, $locked->lock_version, $locked->candidate_code);
            return $locked->fresh($this->candidateRelations());
        });
    }

    public function createCandidate(Request $request, AuditEngagement $engagement, string $type, ?AemsEngagementTask $task, ?AuditFinding $finding, ?ExitConference $conference, string $reason, array $snapshot): AemsEscalationCandidate
    {
        $key = 'aems:'.$engagement->id.':'.strtolower($type).':'.($task?->id ?? $finding?->id ?? $conference?->id ?? sha1(json_encode($snapshot)));
        $existing = AemsEscalationCandidate::query()->where('detection_key', $key)->first();
        if ($existing) return $existing;
        $candidate = AemsEscalationCandidate::query()->create(['audit_engagement_id' => $engagement->id, 'candidate_code' => 'ESC-'.$engagement->engagement_code.'-'.str_pad((string) (AemsEscalationCandidate::query()->where('audit_engagement_id', $engagement->id)->count() + 1), 3, '0', STR_PAD_LEFT), 'detection_key' => $key, 'candidate_type' => strtoupper($type), 'source_type' => $task ? AemsEngagementTask::class : ($finding ? AuditFinding::class : ($conference ? ExitConference::class : null)), 'source_id' => $task?->id ?? $finding?->id ?? $conference?->id, 'audit_finding_id' => $finding?->id, 'aems_engagement_task_id' => $task?->id, 'exit_conference_id' => $conference?->id, 'status' => 'OPEN', 'reason' => $reason, 'detected_at' => now(), 'due_at' => $task?->due_at ?? $finding?->management_response_due_date, 'trigger_snapshot' => $snapshot, 'lock_version' => 1]);
        $this->record($request, $engagement, 'aems.escalation-candidate.created', null, $this->candidateSnapshot($candidate), $reason, 'AEMS_ESCALATION_CANDIDATE', $candidate->id, 1, $candidate->candidate_code);
        $recipients = $this->reviewers($engagement, 'aems.escalation-candidate.review')->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)->values();
        DB::afterCommit(fn () => $this->notifications->send($recipients, ['actorId' => $request->user()->id, 'type' => 'AEMS_ESCALATION_CANDIDATE', 'category' => 'OVERDUE', 'priority' => 'HIGH', 'moduleCode' => 'AEMS', 'title' => "{$candidate->candidate_code}: escalation review required", 'message' => $reason, 'actionUrl' => "/audit-engagement-management/work-queues?engagementId={$engagement->id}", 'actionLabel' => 'Review AEMS candidate', 'subjectType' => AemsEscalationCandidate::class, 'subjectId' => $candidate->id, 'subjectCode' => $candidate->candidate_code, 'dedupeKey' => $key.':notification', 'metadata' => ['engagementId' => $engagement->id, 'candidateType' => $candidate->candidate_type]]));
        return $candidate;
    }

    /** Called by the reminder worker; returns in-app notifications sent. */
    public function dispatchDueReminders(): int
    {
        if (! $this->runtime->boolean('aems_reminders_enabled')) {
            return 0;
        }
        $count = 0;
        AemsEngagementTask::query()->whereIn('status', ['OPEN', 'IN_PROGRESS'])->whereNotNull('due_at')->where('due_at', '<=', now()->addHours($this->runtime->integer('aems_reminder_due_hours')))->with('engagement')->each(function (AemsEngagementTask $task) use (&$count): void {
            if (! $task->engagement?->is_active) return;
            $overdue = $task->due_at->isPast();
            $recipients = collect([$task->assigned_to]);
            if ($task->assigned_office_id) $recipients = $recipients->merge(User::query()->where('office_id', $task->assigned_office_id)->where('is_active', true)->pluck('id'));
            $count += $this->notifications->send($recipients->filter()->unique(), ['type' => $overdue ? 'AEMS_TASK_OVERDUE' : 'AEMS_TASK_DUE', 'category' => $overdue ? 'OVERDUE' : 'DUE_DATE', 'priority' => $overdue ? 'URGENT' : 'HIGH', 'moduleCode' => 'AEMS', 'title' => ($overdue ? 'Overdue task: ' : 'Task due soon: ').$task->task_code, 'message' => $task->title.' is due '.$task->due_at->diffForHumans().'.', 'actionUrl' => "/audit-engagement-management/work-queues?engagementId={$task->audit_engagement_id}", 'actionLabel' => 'Open AEMS work queue', 'subjectType' => AemsEngagementTask::class, 'subjectId' => $task->id, 'subjectCode' => $task->task_code, 'dedupeKey' => "aems:task:{$task->id}:due:{$task->due_at->toDateString()}:".($overdue ? 'overdue' : 'upcoming')])->count();
            if ($overdue) $this->createCandidateForSystem($task->engagement, 'TASK_OVERDUE', $task, null, null, 'Task is overdue and requires review.', ['taskCode' => $task->task_code, 'dueAt' => $task->due_at->toISOString()]);
        });
        return $count;
    }

    public function createCandidateForSystem(AuditEngagement $engagement, string $type, ?AemsEngagementTask $task, ?AuditFinding $finding, ?ExitConference $conference, string $reason, array $snapshot): AemsEscalationCandidate
    {
        $systemRequest = Request::create('/system/aems-work-queue', 'POST');
        $actor = User::query()->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'aems.escalation-candidate.create'))->where('is_active', true)->first() ?? User::query()->where('is_active', true)->firstOrFail();
        $systemRequest->setUserResolver(fn (): User => $actor);
        return $this->createCandidate($systemRequest, $engagement, $type, $task, $finding, $conference, $reason, $snapshot);
    }

    public function taskData(AemsEngagementTask $task): array
    {
        $task->loadMissing($this->taskRelations());
        return ['id' => $task->id, 'taskCode' => $task->task_code, 'taskType' => $task->task_type, 'title' => $task->title, 'description' => $task->description, 'status' => $task->status, 'dueAt' => $task->due_at?->toISOString(), 'dueState' => $task->due_state, 'startedAt' => $task->started_at?->toISOString(), 'completedAt' => $task->completed_at?->toISOString(), 'completionComment' => $task->completion_comment, 'assignedTo' => $this->user($task->assignee), 'assignedOffice' => $task->assignedOffice?->only(['id', 'code', 'name']), 'finding' => $task->finding?->only(['id', 'finding_code', 'title']), 'entryConference' => $task->entryConference?->only(['id', 'conference_code']), 'exitConference' => $task->exitConference?->only(['id', 'conference_code']), 'lockVersion' => $task->lock_version, 'events' => $task->events->map(fn (AemsEngagementTaskEvent $event): array => ['id' => $event->id, 'versionNumber' => $event->version_number, 'action' => $event->action, 'fromStatus' => $event->from_status, 'toStatus' => $event->to_status, 'content' => $event->content, 'actor' => $this->user($event->actor), 'recordedAt' => $event->recorded_at?->toISOString()])->values()->all()];
    }

    public function reviewNoteData(AemsReviewNote $note): array
    {
        $note->loadMissing($this->noteRelations());
        return ['id' => $note->id, 'familyUuid' => $note->note_family_uuid, 'versionNumber' => $note->version_number, 'isCurrentRevision' => $note->is_current_revision, 'noteCode' => $note->note_code, 'noteType' => $note->note_type, 'content' => $note->content, 'status' => $note->status, 'revisionReason' => $note->revision_reason, 'createdBy' => $this->user($note->creator), 'finalizedBy' => $this->user($note->finalizer), 'finalizedAt' => $note->finalized_at?->toISOString(), 'finding' => $note->finding?->only(['id', 'finding_code', 'title']), 'entryConference' => $note->entryConference?->only(['id', 'conference_code']), 'exitConference' => $note->exitConference?->only(['id', 'conference_code']), 'task' => $note->task?->only(['id', 'task_code', 'title']), 'lockVersion' => $note->lock_version, 'attachments' => $note->attachments->map(fn (AemsReviewNoteAttachment $attachment): array => $this->attachmentData($attachment))->values()->all()];
    }

    public function dueProcessData(AemsDialogueDueProcess $item): array
    {
        $item->loadMissing($this->dueProcessRelations());
        return ['id' => $item->id, 'eventCode' => $item->event_code, 'versionNumber' => $item->version_number, 'eventType' => $item->event_type, 'content' => $item->content, 'dueDate' => $item->due_date?->toDateString(), 'recordedAt' => $item->recorded_at?->toISOString(), 'actor' => $this->user($item->actor), 'finding' => $item->finding?->only(['id', 'finding_code', 'title']), 'response' => $item->response?->only(['id', 'response_code']), 'metadata' => $item->metadata, 'attachments' => $item->attachments->map(fn (AemsDueProcessAttachment $attachment): array => $this->attachmentData($attachment))->values()->all()];
    }

    public function candidateData(AemsEscalationCandidate $candidate): array
    {
        $candidate->loadMissing($this->candidateRelations());
        return ['id' => $candidate->id, 'candidateCode' => $candidate->candidate_code, 'candidateType' => $candidate->candidate_type, 'status' => $candidate->status, 'reason' => $candidate->reason, 'detectedAt' => $candidate->detected_at?->toISOString(), 'dueAt' => $candidate->due_at?->toISOString(), 'triggerSnapshot' => $candidate->trigger_snapshot, 'reviewedBy' => $this->user($candidate->reviewer), 'reviewedAt' => $candidate->reviewed_at?->toISOString(), 'reviewComment' => $candidate->review_comment, 'finding' => $candidate->finding?->only(['id', 'finding_code', 'title']), 'task' => $candidate->task?->only(['id', 'task_code', 'title']), 'entryConference' => $candidate->entryConference?->only(['id', 'conference_code']), 'exitConference' => $candidate->exitConference?->only(['id', 'conference_code']), 'lockVersion' => $candidate->lock_version];
    }

    private function authorizeView(Request $request, AuditEngagement $engagement): void
    {
        if ($request->user()->hasPermission('access.auditee_scope')) $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.finding.view');
        else $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.task.view');
    }

    private function authorize(Request $request, AuditEngagement $engagement, string $permission): void { $this->access->authorizeEngagementAction($request->user(), $engagement, $permission); }
    private function lockTask(AuditEngagement $engagement, AemsEngagementTask $task, int $version): AemsEngagementTask { if ((int) $task->audit_engagement_id !== (int) $engagement->id) abort(404); $locked = AemsEngagementTask::query()->lockForUpdate()->findOrFail($task->id); if ((int) $locked->lock_version !== $version) throw ValidationException::withMessages(['lockVersion' => ['The task changed. Refresh before continuing.']]); return $locked; }
    private function lockNote(AuditEngagement $engagement, AemsReviewNote $note, int $version): AemsReviewNote { if ((int) $note->audit_engagement_id !== (int) $engagement->id) abort(404); $locked = AemsReviewNote::query()->lockForUpdate()->findOrFail($note->id); if ((int) $locked->lock_version !== $version) throw ValidationException::withMessages(['lockVersion' => ['The review note changed. Refresh before continuing.']]); return $locked->load($this->noteRelations()); }
    private function ensureDueProcess(AuditEngagement $engagement, AemsDialogueDueProcess $item): void { if ((int) $item->audit_engagement_id !== (int) $engagement->id) abort(404); }
    private function ensureDocumentVersion(int $id): void { if (! DocumentVersion::query()->whereKey($id)->exists()) throw ValidationException::withMessages(['documentVersionId' => ['Select an existing immutable Core Document Version.']]); }
    private function validateTaskLinks(AuditEngagement $engagement, array $attributes, bool $partial = false): void
    {
        foreach (['findingId' => AuditFinding::class, 'entryConferenceId' => EntryConference::class, 'exitConferenceId' => ExitConference::class] as $field => $class) {
            if (! empty($attributes[$field]) && ! $class::query()->where('audit_engagement_id', $engagement->id)->whereKey($attributes[$field])->exists()) {
                throw ValidationException::withMessages([$field => ['The linked record must belong to this engagement.']]);
            }
        }
        if (! $partial && empty($attributes['title'])) {
            throw ValidationException::withMessages(['title' => ['A task title is required.']]);
        }
        if (! empty($attributes['assignedOfficeId'])
            && ! $engagement->offices()->whereKey($attributes['assignedOfficeId'])->exists()) {
            throw ValidationException::withMessages(['assignedOfficeId' => ['The assigned office must be part of this engagement scope.']]);
        }
        if (! empty($attributes['assignedTo'])) {
            $assignee = User::query()->find($attributes['assignedTo']);
            if (! $assignee || ! $assignee->is_active) {
                throw ValidationException::withMessages(['assignedTo' => ['The assignee must be an active user.']]);
            }
            $isTeamMember = $engagement->teamMembers()
                ->where('user_id', $assignee->id)
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->exists();
            if (! $isTeamMember && ! $assignee->hasGlobalEngagementAccess()) {
                throw ValidationException::withMessages(['assignedTo' => ['The assignee must have an active assignment on this engagement.']]);
            }
            if (! empty($attributes['assignedOfficeId'])
                && $assignee->office_id !== null
                && (int) $assignee->office_id !== (int) $attributes['assignedOfficeId']) {
                throw ValidationException::withMessages(['assignedOfficeId' => ['The assigned user and office must match.']]);
            }
        }
    }
    private function validateNoteLinks(AuditEngagement $engagement, array $attributes): void { $links = collect(['findingId' => AuditFinding::class, 'entryConferenceId' => EntryConference::class, 'exitConferenceId' => ExitConference::class, 'taskId' => AemsEngagementTask::class])->filter(fn ($class, $field) => ! empty($attributes[$field])); if ($links->isEmpty()) throw ValidationException::withMessages(['link' => ['Link the note to a finding, conference, or task.']]); foreach ($links as $field => $class) { if (! $class::query()->where('audit_engagement_id', $engagement->id)->whereKey($attributes[$field])->exists()) throw ValidationException::withMessages([$field => ['The linked record must belong to this engagement.']]); } }
    private function taskEvent(Request $request, AuditEngagement $engagement, AemsEngagementTask $task, string $action, ?string $from, ?string $to, ?string $content, array $snapshot): void { AemsEngagementTaskEvent::query()->create(['aems_engagement_task_id' => $task->id, 'audit_engagement_id' => $engagement->id, 'version_number' => $task->lock_version, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'content' => $content, 'actor_id' => $request->user()->id, 'snapshot' => $snapshot]); }
    private function nextTaskCode(AuditEngagement $engagement): string { $sequence = AemsEngagementTask::withTrashed()->where('audit_engagement_id', $engagement->id)->count() + 1; do { $code = 'TASK-'.$engagement->engagement_code.'-'.str_pad((string) $sequence++, 3, '0', STR_PAD_LEFT); } while (AemsEngagementTask::withTrashed()->where('task_code', $code)->exists()); return $code; }
    private function nextNoteCode(AuditEngagement $engagement): string { $sequence = AemsReviewNote::withTrashed()->where('audit_engagement_id', $engagement->id)->count() + 1; do { $code = 'RN-'.$engagement->engagement_code.'-'.str_pad((string) $sequence++, 3, '0', STR_PAD_LEFT); } while (AemsReviewNote::withTrashed()->where('note_code', $code)->exists()); return $code; }
    private function taskSnapshot(AemsEngagementTask $task): array { return ['id' => $task->id, 'taskCode' => $task->task_code, 'status' => $task->status, 'title' => $task->title, 'dueAt' => $task->due_at?->toISOString(), 'assignedTo' => $task->assigned_to, 'assignedOfficeId' => $task->assigned_office_id]; }
    private function reviewNoteSnapshot(AemsReviewNote $note): array { return ['id' => $note->id, 'noteCode' => $note->note_code, 'versionNumber' => $note->version_number, 'status' => $note->status, 'content' => $note->content]; }
    private function dueProcessSnapshot(AemsDialogueDueProcess $item): array { return ['id' => $item->id, 'eventCode' => $item->event_code, 'eventType' => $item->event_type, 'findingId' => $item->audit_finding_id, 'responseId' => $item->management_response_id]; }
    private function candidateSnapshot(AemsEscalationCandidate $candidate): array { return ['id' => $candidate->id, 'candidateCode' => $candidate->candidate_code, 'candidateType' => $candidate->candidate_type, 'status' => $candidate->status, 'reason' => $candidate->reason]; }
    private function record(Request $request, AuditEngagement $engagement, string $action, ?array $old, ?array $new, ?string $comment, string $subjectType, int $subjectId, int $subjectVersion, string $subjectCode, ?string $family = null, ?array $documentIds = null): void { $this->support->audit($request, $action, $engagement, $old, $new, ['subjectType' => $subjectType, 'subjectId' => $subjectId, 'subjectVersion' => $subjectVersion]); $this->support->event($request, $engagement, $action, null, null, $old, $new, $comment, $subjectType, $subjectId, $subjectVersion, $subjectCode, $family, $documentIds); }
    private function taskRelations(): array { return ['assignee', 'assignedOffice', 'finding', 'entryConference', 'exitConference', 'events.actor']; }
    private function noteRelations(): array { return ['creator', 'finalizer', 'finding', 'entryConference', 'exitConference', 'task', 'attachments.documentVersion', 'attachments.uploader']; }
    private function dueProcessRelations(): array { return ['finding', 'response', 'actor', 'attachments.documentVersion', 'attachments.uploader']; }
    private function candidateRelations(): array { return ['finding', 'task', 'entryConference', 'exitConference', 'reviewer']; }
    private function user(?User $user): ?array { return $user?->only(['id', 'employee_id', 'name', 'initials']); }
    private function attachmentData(AemsReviewNoteAttachment|AemsDueProcessAttachment $attachment): array { $attachment->loadMissing(['documentVersion', 'uploader']); return ['id' => $attachment->id, 'attachmentCode' => $attachment->attachment_code, 'caption' => $attachment->caption, 'documentVersionId' => $attachment->document_version_id, 'fileName' => $attachment->documentVersion?->original_file_name, 'fileSize' => $attachment->documentVersion?->file_size, 'mimeType' => $attachment->documentVersion?->mime_type, 'checksumSha256' => $attachment->documentVersion?->checksum_sha256, 'uploadedBy' => $this->user($attachment->uploader), 'uploadedAt' => $attachment->created_at?->toISOString()]; }
    private function notifyTask(Request $request, AuditEngagement $engagement, AemsEngagementTask $task, string $action): void { $recipients = collect([$task->assigned_to])->filter()->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)->unique(); if ($recipients->isEmpty()) return; DB::afterCommit(fn () => $this->notifications->send($recipients, ['actorId' => $request->user()->id, 'type' => 'AEMS_TASK_'.strtoupper($action), 'category' => 'ASSIGNMENT', 'priority' => 'HIGH', 'moduleCode' => 'AEMS', 'title' => "{$task->task_code}: task {$action}", 'message' => "{$task->title} for {$engagement->title} was {$action}.", 'actionUrl' => "/audit-engagement-management/work-queues?engagementId={$engagement->id}", 'actionLabel' => 'Open AEMS work queue', 'subjectType' => AemsEngagementTask::class, 'subjectId' => $task->id, 'subjectCode' => $task->task_code, 'dedupeKey' => "aems:task:{$task->id}:{$task->lock_version}:".strtolower($action), 'metadata' => ['engagementId' => $engagement->id, 'taskType' => $task->task_type]])); }
    private function reviewers(AuditEngagement $engagement, string $permission) { $teamIds = $engagement->teamMembers()->where('is_active', true)->whereNull('ended_at')->pluck('user_id'); return User::query()->where('is_active', true)->whereIn('id', $teamIds)->get()->filter(fn (User $user): bool => $user->hasPermission($permission))->pluck('id'); }
    private function nullable(mixed $value): ?string { return blank($value) ? null : trim((string) $value); }
}
