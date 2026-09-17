<?php

namespace App\Services;

use App\Contracts\Aems\EngagementRetentionProvider;
use App\Models\AemsEngagementMilestone;
use App\Models\AemsRecordDispositionAction;
use App\Models\AuditEngagement;
use App\Models\EngagementRetentionRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Controlled AEMS record disposition and engagement calendar operations.
 * This service records disposition decisions; it never physically deletes a
 * Core document or its immutable DocumentVersion.
 */
class AemsRecordsCalendarService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly EngagementRetentionProvider $retention,
    ) {}

    /** @return array<string, mixed> */
    public function recordsWorkspace(Request $request, AuditEngagement $engagement, ?string $query = null): array
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.records.view');
        $needle = trim((string) $query);
        if ($needle !== '') {
            $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.records.search');
        }
        $items = $engagement->documentIndexItems()
            ->with('documentVersion.document')
            ->when($needle !== '', function ($builder) use ($needle): void {
                $like = '%'.mb_strtolower($needle).'%';
                $builder->where(function ($where) use ($like): void {
                    $where->whereRaw('LOWER(reference_code) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(title) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(record_type) LIKE ?', [$like]);
                });
            })
            ->orderBy('sequence_no')
            ->get();
        if (! $request->user()->hasPermission('documents.view_restricted')) {
            $items = $items->reject(fn ($item): bool => in_array(strtoupper((string) $item->confidentiality_code), ['RESTRICTED', 'SECRET'], true))->values();
        }
        $record = $engagement->retentionRecord()->with('dispositionActions.actor')->first();

        return [
            'items' => $items->map(fn ($item): array => [
                'id' => $item->id,
                'sequenceNo' => $item->sequence_no,
                'recordType' => $item->record_type,
                'recordId' => $item->record_id,
                'referenceCode' => $item->reference_code,
                'title' => $item->title,
                'documentVersionId' => $item->document_version_id,
                'confidentialityCode' => $item->confidentiality_code,
                'includedFlag' => $item->included_flag,
                'exclusionReason' => $item->exclusion_reason,
                'documentDate' => $item->document_date?->toDateString(),
            ])->values()->all(),
            'query' => $needle,
            'retention' => $record ? $this->retentionData($record) : null,
            'retentionReadiness' => $this->retention->readiness($record),
            'blockers' => $this->closureBlockers($engagement, $record),
            'actions' => $record?->dispositionActions->map(fn ($action): array => [
                'id' => $action->id,
                'actionCode' => $action->action_code,
                'fromStatus' => $action->from_status,
                'toStatus' => $action->to_status,
                'reason' => $action->reason,
                'referenceCode' => $action->reference_code,
                'actorId' => $action->actor_id,
                'actorName' => $action->actor?->name,
                'occurredAt' => $action->occurred_at?->toISOString(),
            ])->values()->all() ?? [],
        ];
    }

    /** @return array<string, mixed> */
    public function calendar(Request $request, AuditEngagement $engagement): array
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.calendar.view');
        $today = today();
        $milestones = $engagement->milestones()->with(['responsibleOffice', 'responsibleUser'])->get();

        return [
            'milestones' => $milestones->map(fn (AemsEngagementMilestone $milestone): array => $this->milestoneData($milestone))->values()->all(),
            'summary' => [
                'total' => $milestones->count(),
                'open' => $milestones->whereIn('status_code', ['OPEN', 'IN_PROGRESS'])->count(),
                'overdue' => $milestones->filter(fn ($milestone): bool => $milestone->due_date
                    && $milestone->due_date->lt($today)
                    && ! in_array($milestone->status_code, ['COMPLETED', 'WAIVED', 'CANCELLED'], true))->count(),
                'completed' => $milestones->where('status_code', 'COMPLETED')->count(),
            ],
            'today' => $today->toDateString(),
        ];
    }

    public function createMilestone(Request $request, AuditEngagement $engagement, array $attributes): AemsEngagementMilestone
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.calendar.manage');
        if ($engagement->status === 'CLOSED') {
            throw ValidationException::withMessages(['engagement' => ['Closed engagement calendars are immutable.']]);
        }
        $milestone = $engagement->milestones()->create([
            'milestone_code' => strtoupper($attributes['milestoneCode']),
            'category_code' => strtoupper($attributes['categoryCode'] ?? 'GENERAL'),
            'title' => trim($attributes['title']),
            'description' => $attributes['description'] ?? null,
            'planned_start_date' => $attributes['plannedStartDate'] ?? null,
            'due_date' => $attributes['dueDate'] ?? null,
            'status_code' => 'OPEN',
            'required_flag' => $attributes['requiredFlag'] ?? true,
            'responsible_office_id' => $attributes['responsibleOfficeId'] ?? null,
            'responsible_user_id' => $attributes['responsibleUserId'] ?? null,
            'related_record_type' => $attributes['relatedRecordType'] ?? null,
            'related_record_id' => $attributes['relatedRecordId'] ?? null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'lock_version' => 1,
        ]);
        $this->support->audit($request, 'aems.calendar.milestone_created', $engagement, null, $milestone->toArray(), ['subjectType' => 'AEMS_MILESTONE', 'subjectId' => $milestone->id]);

        return $milestone->fresh(['responsibleOffice', 'responsibleUser']);
    }

    public function updateMilestone(Request $request, AuditEngagement $engagement, AemsEngagementMilestone $milestone, array $attributes): AemsEngagementMilestone
    {
        $this->ensureMilestone($engagement, $milestone);
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.calendar.manage');
        if ($milestone->status_code === 'COMPLETED') {
            throw ValidationException::withMessages(['milestone' => ['Completed milestones are immutable.']]);
        }
        if ((int) $milestone->lock_version !== (int) $attributes['lockVersion']) {
            throw ValidationException::withMessages(['lockVersion' => ['The milestone changed in another session. Refresh first.']]);
        }
        $old = $milestone->toArray();
        $milestone->fill([
            'category_code' => strtoupper($attributes['categoryCode'] ?? $milestone->category_code),
            'title' => trim($attributes['title'] ?? $milestone->title),
            'description' => $attributes['description'] ?? $milestone->description,
            'planned_start_date' => $attributes['plannedStartDate'] ?? $milestone->planned_start_date,
            'due_date' => $attributes['dueDate'] ?? $milestone->due_date,
            'required_flag' => $attributes['requiredFlag'] ?? $milestone->required_flag,
            'responsible_office_id' => $attributes['responsibleOfficeId'] ?? $milestone->responsible_office_id,
            'responsible_user_id' => $attributes['responsibleUserId'] ?? $milestone->responsible_user_id,
            'updated_by' => $request->user()->id,
            'lock_version' => $milestone->lock_version + 1,
        ])->save();
        $this->support->audit($request, 'aems.calendar.milestone_updated', $engagement, $old, $milestone->toArray(), ['subjectType' => 'AEMS_MILESTONE', 'subjectId' => $milestone->id]);

        return $milestone->fresh(['responsibleOffice', 'responsibleUser']);
    }

    public function transitionMilestone(Request $request, AuditEngagement $engagement, AemsEngagementMilestone $milestone, string $status, int $lockVersion): AemsEngagementMilestone
    {
        $this->ensureMilestone($engagement, $milestone);
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.calendar.manage');
        $status = strtoupper($status);
        if (! in_array($status, ['IN_PROGRESS', 'COMPLETED', 'WAIVED', 'CANCELLED', 'OPEN'], true)) {
            throw ValidationException::withMessages(['status' => ['Unsupported milestone status.']]);
        }
        if ($milestone->lock_version !== $lockVersion) {
            throw ValidationException::withMessages(['lockVersion' => ['The milestone changed in another session. Refresh first.']]);
        }
        if ($milestone->status_code === 'COMPLETED') {
            throw ValidationException::withMessages(['milestone' => ['Completed milestones are immutable.']]);
        }
        $old = $milestone->toArray();
        $milestone->forceFill([
            'status_code' => $status,
            'completed_date' => $status === 'COMPLETED' ? today() : null,
            'updated_by' => $request->user()->id,
            'lock_version' => $milestone->lock_version + 1,
        ])->save();
        $this->support->event($request, $engagement, 'AEMS_MILESTONE_'.$status, $old['status_code'], $status, $old, $milestone->toArray(), 'Audit calendar milestone transitioned.', 'AEMS_MILESTONE', $milestone->id, $milestone->lock_version, $milestone->milestone_code);

        return $milestone->fresh(['responsibleOffice', 'responsibleUser']);
    }

    public function archive(Request $request, AuditEngagement $engagement, EngagementRetentionRecord $record, string $reason): EngagementRetentionRecord
    {
        $this->ensureRetention($engagement, $record);
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.retention.archive');
        if ($engagement->status !== 'CLOSED') {
            throw ValidationException::withMessages(['engagement' => ['Records may be archived only after formal engagement closure.']]);
        }
        if (! $record->approved_at) {
            throw ValidationException::withMessages(['retention' => ['Approved retention metadata is required before archival.']]);
        }
        if ($record->legal_hold_flag) {
            throw ValidationException::withMessages(['legalHold' => ['Active legal hold prevents archival disposition.']]);
        }
        if ($record->archive_status === 'DISPOSITION_RECORDED') {
            throw ValidationException::withMessages(['retention' => ['A recorded disposition is immutable.']]);
        }
        if ($record->archive_status === 'ARCHIVED') {
            return $record;
        }

        return DB::transaction(function () use ($request, $engagement, $record, $reason): EngagementRetentionRecord {
            $locked = EngagementRetentionRecord::query()->lockForUpdate()->findOrFail($record->id);
            $from = $locked->archive_status;
            $locked->newQuery()->whereKey($locked->id)->update([
                'archive_status' => 'ARCHIVED', 'archived_at' => now(), 'archived_by' => $request->user()->id,
                'archive_reason' => trim($reason), 'lock_version' => $locked->lock_version + 1,
                'updated_at' => now(),
            ]);
            $action = $this->action($request, $engagement, $locked, 'ARCHIVE', $from, 'ARCHIVED', $reason, null);
            $this->support->audit($request, 'aems.records.archived', $engagement, ['archiveStatus' => $from], ['archiveStatus' => 'ARCHIVED'], ['actionId' => $action->id]);

            return $locked->fresh();
        });
    }

    public function releaseLegalHold(Request $request, AuditEngagement $engagement, EngagementRetentionRecord $record, string $reason, ?string $reference): EngagementRetentionRecord
    {
        $this->ensureRetention($engagement, $record);
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.retention.legal_hold_release');
        if (! $record->legal_hold_flag) {
            throw ValidationException::withMessages(['legalHold' => ['No active legal hold is recorded.']]);
        }
        if ($record->archive_status === 'DISPOSITION_RECORDED') {
            throw ValidationException::withMessages(['retention' => ['A recorded disposition is immutable.']]);
        }
        return DB::transaction(function () use ($request, $engagement, $record, $reason, $reference): EngagementRetentionRecord {
            $locked = EngagementRetentionRecord::query()->lockForUpdate()->findOrFail($record->id);
            $locked->newQuery()->whereKey($locked->id)->update([
                'legal_hold_flag' => false, 'legal_hold_released_at' => now(), 'legal_hold_released_by' => $request->user()->id,
                'legal_hold_release_reason' => trim($reason), 'legal_hold_release_reference' => $reference,
                'lock_version' => $locked->lock_version + 1, 'updated_at' => now(),
            ]);
            $action = $this->action($request, $engagement, $locked, 'LEGAL_HOLD_RELEASE', 'ON_HOLD', 'ACTIVE', $reason, $reference);
            $this->support->audit($request, 'aems.records.legal_hold_released', $engagement, ['legalHoldFlag' => true], ['legalHoldFlag' => false], ['actionId' => $action->id]);

            return $locked->fresh();
        });
    }

    /** @return array{eligible: bool, reasons: list<string>} */
    public function reviewDestruction(Request $request, AuditEngagement $engagement, EngagementRetentionRecord $record, string $reason): array
    {
        $this->ensureRetention($engagement, $record);
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.retention.destruction_review');
        if ($record->archive_status === 'DISPOSITION_RECORDED') {
            throw ValidationException::withMessages(['retention' => ['A recorded disposition is immutable.']]);
        }
        $reasons = [];
        if (! $record->approved_at) $reasons[] = 'Retention metadata is not approved.';
        if ($record->legal_hold_flag) $reasons[] = 'An active legal hold prevents disposition.';
        if ($record->permanent_flag) $reasons[] = 'Permanent records are not eligible for destruction.';
        if (! $record->scheduled_disposition_date || $record->scheduled_disposition_date->isFuture()) $reasons[] = 'The scheduled disposition date has not been reached.';
        if ($engagement->status !== 'CLOSED') $reasons[] = 'Formal engagement closure is required.';
        $index = $engagement->documentIndexItems()->where('included_flag', true)->count();
        if ($index === 0) $reasons[] = 'No included final document-index records are available.';
        $eligible = $reasons === [];
        DB::transaction(function () use ($request, $engagement, $record, $reason, $eligible, $reasons): void {
            $locked = EngagementRetentionRecord::query()->lockForUpdate()->findOrFail($record->id);
            $status = $eligible ? 'ELIGIBLE' : 'NOT_ELIGIBLE';
            $locked->newQuery()->whereKey($locked->id)->update([
                'destruction_eligibility_status' => $status, 'destruction_reviewed_at' => now(),
                'destruction_reviewed_by' => $request->user()->id,
                'destruction_review_reason' => trim($reason).' '.implode(' ', $reasons),
                'lock_version' => $locked->lock_version + 1, 'updated_at' => now(),
            ]);
            $this->action($request, $engagement, $locked, 'DESTRUCTION_REVIEW', $locked->destruction_eligibility_status, $status, $reason, null, ['reasons' => $reasons]);
            $this->support->audit($request, 'aems.records.destruction_reviewed', $engagement, null, ['eligible' => $eligible, 'reasons' => $reasons]);
        });

        return ['eligible' => $eligible, 'reasons' => $reasons, 'retention' => $record->fresh()];
    }

    public function recordDisposition(Request $request, AuditEngagement $engagement, EngagementRetentionRecord $record, string $reason, string $reference): EngagementRetentionRecord
    {
        $this->ensureRetention($engagement, $record);
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.retention.disposition_execute');
        if ($record->destruction_eligibility_status !== 'ELIGIBLE' || $record->legal_hold_flag) {
            throw ValidationException::withMessages(['retention' => ['Only a reviewed eligible record without legal hold may receive a disposition record.']]);
        }
        return DB::transaction(function () use ($request, $engagement, $record, $reason, $reference): EngagementRetentionRecord {
            $locked = EngagementRetentionRecord::query()->lockForUpdate()->findOrFail($record->id);
            if ($locked->destruction_eligibility_status !== 'ELIGIBLE' || $locked->legal_hold_flag) {
                throw ValidationException::withMessages(['retention' => ['The retention record changed before disposition was recorded.']]);
            }
            $locked->newQuery()->whereKey($locked->id)->update([
                'archive_status' => 'DISPOSITION_RECORDED', 'disposition_recorded_at' => now(),
                'disposition_recorded_by' => $request->user()->id, 'disposition_reference' => trim($reference),
                'lock_version' => $locked->lock_version + 1, 'updated_at' => now(),
            ]);
            $this->action($request, $engagement, $locked, 'DISPOSITION_RECORDED', 'ELIGIBLE', 'DISPOSITION_RECORDED', $reason, $reference);

            return $locked->fresh();
        });
    }

    /** @return list<array{code: string, description: string, blocking: bool}> */
    public function closureBlockers(AuditEngagement $engagement, ?EngagementRetentionRecord $record = null): array
    {
        $record ??= $engagement->retentionRecord;
        $blockers = [];
        if (! $record) $blockers[] = ['code' => 'RETENTION_RECORD_MISSING', 'description' => 'Retention record is required before closure.', 'blocking' => true];
        if ($record && ! $record->approved_at) $blockers[] = ['code' => 'RETENTION_NOT_APPROVED', 'description' => 'Retention metadata must be approved before closure.', 'blocking' => true];
        if ($record?->legal_hold_flag) $blockers[] = ['code' => 'LEGAL_HOLD_ACTIVE', 'description' => 'Active legal hold must be resolved or explicitly preserved before disposition.', 'blocking' => true];
        $overdue = $engagement->milestones()->whereNotNull('due_date')->where('due_date', '<', today())->whereNotIn('status_code', ['COMPLETED', 'WAIVED', 'CANCELLED'])->count();
        if ($overdue > 0) $blockers[] = ['code' => 'OVERDUE_MILESTONES', 'description' => "{$overdue} required audit calendar milestone(s) are overdue.", 'blocking' => true];
        return $blockers;
    }

    private function ensureRetention(AuditEngagement $engagement, EngagementRetentionRecord $record): void
    {
        abort_unless((int) $record->audit_engagement_id === (int) $engagement->id, 404);
    }

    private function ensureMilestone(AuditEngagement $engagement, AemsEngagementMilestone $milestone): void
    {
        abort_unless((int) $milestone->audit_engagement_id === (int) $engagement->id, 404);
    }

    private function action(Request $request, AuditEngagement $engagement, EngagementRetentionRecord $record, string $code, ?string $from, ?string $to, string $reason, ?string $reference, array $extra = []): AemsRecordDispositionAction
    {
        return AemsRecordDispositionAction::query()->create([
            'audit_engagement_id' => $engagement->id,
            'engagement_retention_record_id' => $record->id,
            'action_code' => $code,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => trim($reason),
            'reference_code' => $reference,
            'actor_id' => $request->user()->id,
            'occurred_at' => now(),
            'snapshot_json' => ['retention' => $record->getAttributes(), ...$extra],
        ]);
    }

    /** @return array<string, mixed> */
    private function retentionData(EngagementRetentionRecord $record): array
    {
        return [
            'id' => $record->id, 'archiveStatus' => $record->archive_status,
            'legalHoldFlag' => $record->legal_hold_flag,
            'legalHoldReleasedAt' => $record->legal_hold_released_at?->toISOString(),
            'legalHoldReleaseReason' => $record->legal_hold_release_reason,
            'legalHoldReleaseReference' => $record->legal_hold_release_reference,
            'destructionEligibilityStatus' => $record->destruction_eligibility_status,
            'destructionReviewedAt' => $record->destruction_reviewed_at?->toISOString(),
            'destructionReviewReason' => $record->destruction_review_reason,
            'dispositionRecordedAt' => $record->disposition_recorded_at?->toISOString(),
            'dispositionReference' => $record->disposition_reference,
            'approvedAt' => $record->approved_at?->toISOString(), 'lockVersion' => $record->lock_version,
        ];
    }

    /** @return array<string, mixed> */
    private function milestoneData(AemsEngagementMilestone $milestone): array
    {
        return [
            'id' => $milestone->id, 'milestoneCode' => $milestone->milestone_code,
            'categoryCode' => $milestone->category_code, 'title' => $milestone->title,
            'description' => $milestone->description, 'plannedStartDate' => $milestone->planned_start_date?->toDateString(),
            'dueDate' => $milestone->due_date?->toDateString(), 'completedDate' => $milestone->completed_date?->toDateString(),
            'statusCode' => $milestone->status_code, 'requiredFlag' => $milestone->required_flag,
            'responsibleOfficeId' => $milestone->responsible_office_id, 'responsibleOffice' => $milestone->responsibleOffice?->name,
            'responsibleUserId' => $milestone->responsible_user_id, 'responsibleUser' => $milestone->responsibleUser?->name,
            'relatedRecordType' => $milestone->related_record_type, 'relatedRecordId' => $milestone->related_record_id,
            'lockVersion' => $milestone->lock_version,
        ];
    }
}
