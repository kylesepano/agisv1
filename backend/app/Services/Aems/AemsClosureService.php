<?php

namespace App\Services;

use App\Contracts\Aems\EngagementRetentionProvider;
use App\Models\AuditEngagement;
use App\Models\AuditRecommendation;
use App\Models\CompletionAssessment;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\EngagementClosure;
use App\Models\EngagementClosureChecklistItem;
use App\Models\EngagementLessonLearned;
use App\Models\EngagementRetentionRecord;
use App\Models\MasterList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AemsClosureService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsClosureChecklistService $checklist,
        private readonly AemsDocumentIndexService $documentIndex,
        private readonly EngagementRetentionProvider $retention,
        private readonly AemsEngagementTransitionService $transitions,
        private readonly AemsSupport $support,
        private readonly AemsNotificationService $notifications,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(Request $request, AuditEngagement $engagement): array
    {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.closure.view',
        );
        $engagement->loadMissing([
            'offices:id,code,name',
            'teamMembers.user:id,name,office_id',
        ]);
        $closure = $engagement->closure()
            ->with([
                'completionAssessment',
                'checklistItems',
                'events',
                'retentionRecord',
                'lessonsLearned',
                'closureDocumentVersion',
            ])
            ->first();
        $evaluated = $this->checklist->evaluate($engagement);
        $summary = $this->checklist->summary($evaluated);

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
                'lockVersion' => $engagement->lock_version,
                'isClosed' => $engagement->status === 'CLOSED',
                'closedAt' => $engagement->closed_at?->toISOString(),
                'reopenRevisionNumber' => $engagement->reopen_revision_number,
            ],
            'closure' => $closure ? $this->closureData($closure) : null,
            'readiness' => $summary,
            'evaluatedChecklist' => $evaluated,
            'retention' => $engagement->retentionRecord
                ? $this->retentionData($engagement->retentionRecord)
                : null,
            'lessonsLearned' => $engagement->lessonsLearned()
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (EngagementLessonLearned $lesson): array => $this->lessonData($lesson))
                ->values()
                ->all(),
            'cms' => $this->cmsData($engagement),
            'retentionOptions' => [
                'offices' => $engagement->offices->map->only(['id', 'code', 'name'])->values()->all(),
                'custodians' => $engagement->teamMembers
                    ->pluck('user')
                    ->filter()
                    ->unique('id')
                    ->map->only(['id', 'name', 'office_id'])
                    ->values()
                    ->all(),
            ],
            'permittedActions' => $this->permittedActions($request, $engagement, $closure, $summary),
        ];
    }

    public function create(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): EngagementClosure {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.closure.create',
        );
        if ($engagement->status !== 'CLOSURE_REVIEW') {
            throw ValidationException::withMessages([
                'engagement' => ['A Closure record begins only while the engagement is in CLOSURE_REVIEW.'],
            ]);
        }
        if ($engagement->closure()->exists()) {
            throw ValidationException::withMessages(['closure' => ['A current Closure record already exists.']]);
        }

        return DB::transaction(function () use ($request, $engagement, $attributes): EngagementClosure {
            $revision = (int) $engagement->closures()->max('revision_number') + 1;
            $supersededClosure = $engagement->closures()
                ->where('is_current_revision', false)
                ->orderByDesc('revision_number')
                ->first();
            $closure = $engagement->closures()->create([
                'closure_code' => sprintf('%s-CL-%02d', $engagement->engagement_code, $revision),
                'revision_number' => $revision,
                'supersedes_closure_id' => $supersededClosure?->id,
                'is_current_revision' => true,
                'completion_assessment_id' => $attributes['completionAssessmentId']
                    ?? $engagement->currentCompletionAssessment?->id,
                'closure_summary' => trim($attributes['closureSummary']),
                'unresolved_matters_summary' => $attributes['unresolvedMattersSummary'] ?? null,
                'lessons_learned_summary' => $attributes['lessonsLearnedSummary'] ?? null,
                'status_code' => 'DRAFT',
                'lock_version' => 1,
            ]);
            $version = $this->snapshotDocument($request, $closure, 1, 'Initial Closure draft');
            $closure->forceFill(['closure_document_version_id' => $version->id])->save();
            $engagement->documentIndexItems()->update([
                'engagement_closure_id' => $closure->id,
            ]);
            $engagement->retentionRecord()->update([
                'engagement_closure_id' => $closure->id,
            ]);
            $this->refreshChecklist($request, $engagement, $closure);
            $this->documentIndex->refresh($request, $engagement);
            $this->record($request, $engagement, $closure, 'CREATE_CLOSURE', null, 'DRAFT');

            return $this->load($closure);
        });
    }

    public function update(
        Request $request,
        AuditEngagement $engagement,
        EngagementClosure $closure,
        array $attributes,
    ): EngagementClosure {
        $this->ensureClosure($engagement, $closure);
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.closure.update',
        );
        if (! in_array($closure->status_code, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
            throw ValidationException::withMessages([
                'closure' => ['Only draft or returned Closure records can be edited.'],
            ]);
        }
        $this->assertLock($closure, (int) $attributes['lockVersion']);
        if (isset($attributes['completionAssessmentId'])) {
            $assessment = CompletionAssessment::query()->findOrFail($attributes['completionAssessmentId']);
            if ((int) $assessment->audit_engagement_id !== (int) $engagement->id
                || ! $assessment->is_current_revision) {
                throw ValidationException::withMessages([
                    'completionAssessmentId' => ['Select the current assessment for this engagement.'],
                ]);
            }
        }
        $old = $this->closureSnapshot($closure);
        $closure->fill([
            'completion_assessment_id' => $attributes['completionAssessmentId']
                ?? $closure->completion_assessment_id,
            'closure_summary' => trim($attributes['closureSummary']),
            'unresolved_matters_summary' => $attributes['unresolvedMattersSummary'] ?? null,
            'lessons_learned_summary' => $attributes['lessonsLearnedSummary'] ?? null,
            'lock_version' => $closure->lock_version + 1,
        ])->save();
        $version = $this->snapshotDocument(
            $request,
            $closure,
            ($closure->closureDocumentVersion?->version_number ?? 0) + 1,
            'Closure content updated',
        );
        $closure->forceFill(['closure_document_version_id' => $version->id])->save();
        $this->record(
            $request,
            $engagement,
            $closure,
            'UPDATE_CLOSURE',
            $closure->status_code,
            $closure->status_code,
            $old,
        );

        return $this->load($closure);
    }

    public function refreshChecklist(
        Request $request,
        AuditEngagement $engagement,
        EngagementClosure $closure,
    ): EngagementClosure {
        $this->ensureClosure($engagement, $closure);
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.closure.update',
        );
        if (in_array($closure->status_code, ['APPROVED', 'CLOSED'], true)) {
            throw ValidationException::withMessages([
                'closure' => ['The approved Closure checklist is immutable.'],
            ]);
        }
        $evaluated = $this->checklist->evaluate($engagement);
        $order = 1;
        foreach ($evaluated as $item) {
            $closure->checklistItems()->updateOrCreate(
                ['checklist_code' => $item['checklistCode']],
                [
                    'checklist_category_code' => $item['checklistCategoryCode'],
                    'description' => $item['description'],
                    'required_flag' => $item['requiredFlag'],
                    'result_code' => $item['resultCode'],
                    'explanation' => $item['explanation'],
                    'related_record_type' => $item['relatedRecordType'],
                    'related_record_id' => $item['relatedRecordId'],
                    'source_path' => $item['sourcePath'],
                    'source_snapshot_json' => $item['sourceSnapshot'],
                    'verified_by' => $request->user()->id,
                    'verified_at' => now(),
                    'blocking_flag' => $item['blockingFlag'],
                    'display_order' => $order++,
                ],
            );
        }
        $summary = $this->checklist->summary($evaluated);
        $documentReady = collect($evaluated)->firstWhere('checklistCode', 'DOCUMENT_INDEX')['resultCode'] === 'PASS';
        $retentionReady = collect($evaluated)->firstWhere('checklistCode', 'RETENTION_METADATA')['resultCode'] === 'PASS';
        $cmsReady = collect($evaluated)->firstWhere('checklistCode', 'CMS_COMPLETE')['resultCode'] === 'PASS';
        $effortReady = collect($evaluated)->firstWhere('checklistCode', 'ACTUAL_PERSON_DAYS')['resultCode'] === 'PASS';
        $closure->forceFill([
            'final_document_index_complete' => $documentReady,
            'retention_metadata_complete' => $retentionReady,
            'cms_transfer_complete' => $cmsReady,
            'actual_person_days_complete' => $effortReady,
            'lock_version' => $closure->lock_version + 1,
        ])->save();
        $this->record(
            $request,
            $engagement,
            $closure,
            'REFRESH_CHECKLIST',
            $closure->status_code,
            $closure->status_code,
            null,
            null,
            ['readiness' => $summary],
        );
        $this->notifications->closureRecordsBlockers(
            $request,
            $engagement,
            $closure,
            $evaluated,
        );

        return $this->load($closure);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        EngagementClosure $closure,
        string $action,
        int $lockVersion,
        ?int $engagementLockVersion,
        ?string $comment,
    ): EngagementClosure {
        $this->ensureClosure($engagement, $closure);
        $action = strtoupper($action);
        $permission = match ($action) {
            'SUBMIT_CLOSURE', 'RESUBMIT_CLOSURE' => 'aems.closure.submit',
            'RETURN_CLOSURE' => 'aems.closure.review',
            'APPROVE_CLOSURE' => 'aems.closure.approve',
            'CLOSE_ENGAGEMENT' => 'aems.closure.close',
            default => throw ValidationException::withMessages(['action' => ['Unsupported Closure action.']]),
        };
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            $permission,
            in_array($action, ['RETURN_CLOSURE', 'APPROVE_CLOSURE', 'CLOSE_ENGAGEMENT'], true)
                ? $closure->submitted_by
                : null,
        );
        if ($action === 'CLOSE_ENGAGEMENT') {
            if ($engagementLockVersion === null) {
                throw ValidationException::withMessages([
                    'engagementLockVersion' => ['The current engagement lock version is required.'],
                ]);
            }

            return $this->transitions->closeApprovedClosure(
                $request,
                $engagement,
                $closure,
                $engagementLockVersion,
                $lockVersion,
                fn (AuditEngagement $lockedEngagement): array => $this->checklist->evaluate($lockedEngagement),
            );
        }

        return DB::transaction(function () use (
            $request,
            $engagement,
            $closure,
            $action,
            $lockVersion,
            $comment,
        ): EngagementClosure {
            $locked = EngagementClosure::query()->lockForUpdate()->findOrFail($closure->id);
            $this->assertLock($locked, $lockVersion);
            $from = $locked->status_code;
            $to = match ($action) {
                'SUBMIT_CLOSURE' => $from === 'DRAFT' ? 'PENDING_REVIEW' : null,
                'RETURN_CLOSURE' => in_array($from, ['PENDING_REVIEW', 'RESUBMITTED'], true)
                    ? 'RETURNED_FOR_REVISION' : null,
                'RESUBMIT_CLOSURE' => $from === 'RETURNED_FOR_REVISION' ? 'RESUBMITTED' : null,
                'APPROVE_CLOSURE' => in_array($from, ['PENDING_REVIEW', 'RESUBMITTED'], true)
                    ? 'APPROVED' : null,
                default => null,
            };
            if (! $to) {
                throw ValidationException::withMessages([
                    'action' => ["{$action} is not allowed while Closure is {$from}."],
                ]);
            }
            if ($action === 'RETURN_CLOSURE' && blank($comment)) {
                throw ValidationException::withMessages(['comment' => ['A return comment is required.']]);
            }
            $this->refreshChecklist($request, $engagement, $locked);
            $locked->refresh();
            if (in_array($action, ['SUBMIT_CLOSURE', 'RESUBMIT_CLOSURE', 'APPROVE_CLOSURE'], true)) {
                $summary = $this->storedReadiness($locked);
                if (! $summary['ready']) {
                    throw ValidationException::withMessages([
                        'checklist' => collect($summary['blockers'])->pluck('description')->all(),
                    ]);
                }
            }
            $old = $this->closureSnapshot($locked);
            $changes = ['status_code' => $to, 'lock_version' => $locked->lock_version + 1];
            if (in_array($action, ['SUBMIT_CLOSURE', 'RESUBMIT_CLOSURE'], true)) {
                $changes['submitted_by'] = $request->user()->id;
                $changes['submitted_at'] = now();
            }
            if ($action === 'RETURN_CLOSURE') {
                $changes['reviewed_by'] = $request->user()->id;
                $changes['reviewed_at'] = now();
                $changes['return_comment'] = $comment;
            }
            if ($action === 'APPROVE_CLOSURE') {
                $changes['reviewed_by'] = $request->user()->id;
                $changes['reviewed_at'] = now();
                $changes['approved_by'] = $request->user()->id;
                $changes['approved_at'] = now();
                $changes['approved_snapshot_json'] = [
                    'closure' => $this->closureSnapshot($locked),
                    'checklist' => $locked->checklistItems->toArray(),
                ];
            }
            $locked->fill($changes)->save();
            if ($action === 'APPROVE_CLOSURE') {
                $version = $this->snapshotDocument(
                    $request,
                    $locked,
                    ($locked->closureDocumentVersion?->version_number ?? 0) + 1,
                    'Approved Closure snapshot',
                );
                $locked->forceFill(['closure_document_version_id' => $version->id])->save();
                $this->documentIndex->refresh($request, $engagement);
            }
            $this->record($request, $engagement, $locked, $action, $from, $to, $old, $comment);
            $this->notifications->closure($request, $engagement, $locked, $action);

            return $this->load($locked);
        });
    }

    public function saveRetention(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): EngagementRetentionRecord {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.retention.manage',
        );
        $old = $engagement->retentionRecord?->toArray();
        $record = $this->retention->save(
            $engagement,
            $engagement->closure,
            $request->user(),
            $attributes,
        );
        $this->support->audit(
            $request,
            'aems.retention.saved',
            $engagement,
            $old,
            $record->toArray(),
            ['subjectType' => 'ENGAGEMENT_RETENTION', 'subjectId' => $record->id],
        );

        return $record;
    }

    public function approveRetention(
        Request $request,
        AuditEngagement $engagement,
        EngagementRetentionRecord $record,
        int $lockVersion,
    ): EngagementRetentionRecord {
        if ((int) $record->audit_engagement_id !== (int) $engagement->id) {
            abort(404);
        }
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.retention.approve',
        );
        $old = $record->toArray();
        $approved = $this->retention->approve($record, $request->user(), $lockVersion);
        $this->support->audit(
            $request,
            'aems.retention.approved',
            $engagement,
            $old,
            $approved->toArray(),
            ['subjectType' => 'ENGAGEMENT_RETENTION', 'subjectId' => $record->id],
        );

        return $approved;
    }

    public function addLesson(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): EngagementLessonLearned {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.closure.update',
        );
        if ($engagement->status === 'CLOSED') {
            throw ValidationException::withMessages(['engagement' => ['Closed engagement lessons are immutable.']]);
        }
        $lesson = $engagement->lessonsLearned()->create([
            'engagement_closure_id' => $engagement->closure?->id,
            'category_code' => strtoupper($attributes['categoryCode']),
            'observation' => trim($attributes['observation']),
            'impact' => trim($attributes['impact']),
            'recommended_improvement' => trim($attributes['recommendedImprovement']),
            'responsible_office_id' => $attributes['responsibleOfficeId'] ?? null,
            'responsible_user_id' => $attributes['responsibleUserId'] ?? null,
            'target_date' => $attributes['targetDate'] ?? null,
            'status_code' => 'OPEN',
            'confidentiality_code' => strtoupper($attributes['confidentialityCode'] ?? 'INTERNAL'),
            'created_by' => $request->user()->id,
        ]);
        $this->support->audit(
            $request,
            'aems.closure.lesson_created',
            $engagement,
            null,
            $lesson->toArray(),
            ['subjectType' => 'ENGAGEMENT_LESSON', 'subjectId' => $lesson->id],
        );

        return $lesson;
    }

    public function excludeRecommendation(
        Request $request,
        AuditEngagement $engagement,
        AuditRecommendation $recommendation,
        string $reason,
        string $authority,
    ): AuditRecommendation {
        if ((int) $recommendation->finding?->audit_engagement_id !== (int) $engagement->id) {
            abort(404);
        }
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.closure.approve',
        );
        if (! in_array($recommendation->status, ['FINALIZED', 'EXCLUDED'], true)
            || $recommendation->cms_recommendation_id) {
            throw ValidationException::withMessages([
                'recommendation' => ['Only an untransferred finalized recommendation may be formally excluded.'],
            ]);
        }
        $old = $recommendation->toArray();
        $recommendation->forceFill([
            'status' => 'EXCLUDED',
            'cms_exclusion_reason' => $reason,
            'cms_exclusion_authority' => $authority,
            'cms_excluded_by' => $request->user()->id,
            'cms_excluded_at' => now(),
            'lock_version' => $recommendation->lock_version + 1,
        ])->save();
        $this->support->audit(
            $request,
            'aems.closure.cms_exclusion_authorized',
            $engagement,
            $old,
            $recommendation->toArray(),
            ['subjectType' => 'AUDIT_RECOMMENDATION', 'subjectId' => $recommendation->id],
        );

        return $recommendation->fresh();
    }

    /** @return array<string, mixed> */
    private function closureData(EngagementClosure $closure): array
    {
        $closure->loadMissing([
            'completionAssessment',
            'checklistItems',
            'events',
            'retentionRecord',
            'lessonsLearned',
            'closureDocumentVersion',
        ]);

        return [
            'id' => $closure->id,
            'closureCode' => $closure->closure_code,
            'revisionNumber' => $closure->revision_number,
            'isCurrentRevision' => $closure->is_current_revision,
            'completionAssessmentId' => $closure->completion_assessment_id,
            'completionAssessmentStatus' => $closure->completionAssessment?->status_code,
            'closureSummary' => $closure->closure_summary,
            'unresolvedMattersSummary' => $closure->unresolved_matters_summary,
            'lessonsLearnedSummary' => $closure->lessons_learned_summary,
            'statusCode' => $closure->status_code,
            'submittedAt' => $closure->submitted_at?->toISOString(),
            'reviewedAt' => $closure->reviewed_at?->toISOString(),
            'approvedAt' => $closure->approved_at?->toISOString(),
            'closedAt' => $closure->closed_at?->toISOString(),
            'returnComment' => $closure->return_comment,
            'documentIndexLockedAt' => $closure->document_index_locked_at?->toISOString(),
            'closureDocumentVersionId' => $closure->closure_document_version_id,
            'lockVersion' => $closure->lock_version,
            'checklist' => $closure->checklistItems
                ->map(fn (EngagementClosureChecklistItem $item): array => $this->checklistData($item))
                ->values()->all(),
            'readiness' => $this->storedReadiness($closure),
            'timeline' => $closure->events->map(fn ($event): array => [
                'id' => $event->id,
                'actionCode' => $event->action_code,
                'fromStatus' => $event->from_status,
                'toStatus' => $event->to_status,
                'comment' => $event->comment,
                'actorId' => $event->actor_id,
                'occurredAt' => $event->occurred_at?->toISOString(),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function checklistData(EngagementClosureChecklistItem $item): array
    {
        return [
            'id' => $item->id,
            'checklistCode' => $item->checklist_code,
            'checklistCategoryCode' => $item->checklist_category_code,
            'description' => $item->description,
            'requiredFlag' => $item->required_flag,
            'resultCode' => $item->result_code,
            'explanation' => $item->explanation,
            'relatedRecordType' => $item->related_record_type,
            'relatedRecordId' => $item->related_record_id,
            'sourcePath' => $item->source_path,
            'sourceSnapshot' => $item->source_snapshot_json,
            'verifiedAt' => $item->verified_at?->toISOString(),
            'blockingFlag' => $item->blocking_flag,
        ];
    }

    /** @return array<string, mixed> */
    private function storedReadiness(EngagementClosure $closure): array
    {
        $items = $closure->checklistItems->map(fn ($item): array => $this->checklistData($item))->all();

        return $this->checklist->summary($items);
    }

    /** @return array<string, mixed> */
    private function retentionData(EngagementRetentionRecord $record): array
    {
        return [
            'id' => $record->id,
            'retentionClassificationCode' => $record->retention_classification_code,
            'retentionTriggerCode' => $record->retention_trigger_code,
            'retentionStartDate' => $record->retention_start_date?->toDateString(),
            'retentionPeriodValue' => $record->retention_period_value,
            'retentionPeriodUnit' => $record->retention_period_unit,
            'permanentFlag' => $record->permanent_flag,
            'scheduledDispositionDate' => $record->scheduled_disposition_date?->toDateString(),
            'custodianUserId' => $record->custodian_user_id,
            'custodianOfficeId' => $record->custodian_office_id,
            'storageLocationDescription' => $record->storage_location_description,
            'legalHoldFlag' => $record->legal_hold_flag,
            'legalHoldReference' => $record->legal_hold_reference,
            'archiveStatus' => $record->archive_status,
            'archivedAt' => $record->archived_at?->toISOString(),
            'archiveReason' => $record->archive_reason,
            'legalHoldReleasedAt' => $record->legal_hold_released_at?->toISOString(),
            'legalHoldReleaseReason' => $record->legal_hold_release_reason,
            'legalHoldReleaseReference' => $record->legal_hold_release_reference,
            'destructionEligibilityStatus' => $record->destruction_eligibility_status,
            'destructionReviewedAt' => $record->destruction_reviewed_at?->toISOString(),
            'destructionReviewReason' => $record->destruction_review_reason,
            'dispositionRecordedAt' => $record->disposition_recorded_at?->toISOString(),
            'dispositionReference' => $record->disposition_reference,
            'approvedBy' => $record->approved_by,
            'approvedAt' => $record->approved_at?->toISOString(),
            'lockVersion' => $record->lock_version,
            'readiness' => $this->retention->readiness($record),
        ];
    }

    /** @return array<string, mixed> */
    private function lessonData(EngagementLessonLearned $lesson): array
    {
        return [
            'id' => $lesson->id,
            'categoryCode' => $lesson->category_code,
            'observation' => $lesson->observation,
            'impact' => $lesson->impact,
            'recommendedImprovement' => $lesson->recommended_improvement,
            'responsibleOfficeId' => $lesson->responsible_office_id,
            'responsibleUserId' => $lesson->responsible_user_id,
            'targetDate' => $lesson->target_date?->toDateString(),
            'statusCode' => $lesson->status_code,
            'confidentialityCode' => $lesson->confidentiality_code,
            'createdAt' => $lesson->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function cmsData(AuditEngagement $engagement): array
    {
        $recommendations = $engagement->findings()
            ->with('recommendations')
            ->get()
            ->flatMap->recommendations;

        return [
            'total' => $recommendations->count(),
            'transferred' => $recommendations->where('status', 'TRANSFERRED')->count(),
            'excluded' => $recommendations->where('status', 'EXCLUDED')->count(),
            'pending' => $recommendations->whereNotIn('status', ['TRANSFERRED', 'EXCLUDED'])->count(),
            'recommendations' => $recommendations->map(fn ($recommendation): array => [
                'id' => $recommendation->id,
                'recommendationCode' => $recommendation->recommendation_code,
                'status' => $recommendation->status,
                'cmsRecommendationId' => $recommendation->cms_recommendation_id,
                'exclusionReason' => $recommendation->cms_exclusion_reason,
                'exclusionAuthority' => $recommendation->cms_exclusion_authority,
            ])->values()->all(),
        ];
    }

    /** @return list<string> */
    private function permittedActions(
        Request $request,
        AuditEngagement $engagement,
        ?EngagementClosure $closure,
        array $summary,
    ): array {
        if (! $closure) {
            return $request->user()->hasPermission('aems.closure.create')
                && $engagement->status === 'CLOSURE_REVIEW'
                    ? ['CREATE_CLOSURE']
                    : [];
        }
        $actions = [];
        if ($request->user()->hasPermission('aems.closure.update')
            && ! in_array($closure->status_code, ['APPROVED', 'CLOSED'], true)) {
            $actions[] = 'REFRESH_CHECKLIST';
        }
        if ($summary['ready']) {
            if ($closure->status_code === 'DRAFT'
                && $request->user()->hasPermission('aems.closure.submit')) {
                $actions[] = 'SUBMIT_CLOSURE';
            }
            if ($closure->status_code === 'RETURNED_FOR_REVISION'
                && $request->user()->hasPermission('aems.closure.submit')) {
                $actions[] = 'RESUBMIT_CLOSURE';
            }
            if (in_array($closure->status_code, ['PENDING_REVIEW', 'RESUBMITTED'], true)
                && $request->user()->hasPermission('aems.closure.approve')) {
                $actions[] = 'APPROVE_CLOSURE';
            }
            if ($closure->status_code === 'APPROVED'
                && $request->user()->hasPermission('aems.closure.close')) {
                $actions[] = 'CLOSE_ENGAGEMENT';
            }
        }
        if (in_array($closure->status_code, ['PENDING_REVIEW', 'RESUBMITTED'], true)
            && $request->user()->hasPermission('aems.closure.review')) {
            $actions[] = 'RETURN_CLOSURE';
        }

        return $actions;
    }

    private function snapshotDocument(
        Request $request,
        EngagementClosure $closure,
        int $versionNo,
        string $reason,
    ): DocumentVersion {
        $closure->loadMissing(['checklistItems', 'closureDocumentVersion.document']);
        $snapshot = $this->closureSnapshot($closure);
        $payload = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $path = "aems/engagements/{$closure->audit_engagement_id}/closure/".Str::uuid().'.json';
        Storage::disk('local')->put($path, $payload);
        $document = $closure->closureDocumentVersion?->document;
        if (! $document) {
            $type = MasterList::query()->where('code', 'DOCUMENT_TYPE')
                ->firstOrFail()->items()->where('code', 'OTHER')->firstOrFail();
            $confidentiality = MasterList::query()->where('code', 'DOCUMENT_CONFIDENTIALITY')
                ->firstOrFail()->items()->where('code', 'INTERNAL')->firstOrFail();
            $document = Document::query()->create([
                'document_type_id' => $type->id,
                'confidentiality_level_id' => $confidentiality->id,
                'title' => "{$closure->closure_code} Engagement Closure",
                'description' => 'Private immutable AEMS Engagement Closure snapshot.',
                'owner_module' => 'AEMS',
                'library_visible' => false,
                'original_file_name' => "{$closure->closure_code}-v{$versionNo}.json",
                'storage_path' => $path,
                'mime_type' => 'application/json',
                'file_extension' => 'json',
                'file_size' => strlen($payload),
                'checksum_sha256' => hash('sha256', $payload),
                'uploaded_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'is_active' => true,
            ]);
            $document->forceFill([
                'document_code' => $this->runtime->formatNumber('document_number_format', $document->id),
            ])->save();
            $document->links()->create([
                'module_code' => 'AEMS',
                'record_type' => 'ENGAGEMENT_CLOSURE',
                'record_id' => $closure->id,
                'record_code' => $closure->closure_code,
                'record_label' => "{$closure->closure_code} - Engagement Closure",
                'linked_by' => $request->user()->id,
            ]);
        }
        $version = $document->versions()->create([
            'version_number' => $versionNo,
            'version_label' => "Engagement Closure version {$versionNo}",
            'change_summary' => $reason,
            'original_file_name' => "{$closure->closure_code}-v{$versionNo}.json",
            'storage_path' => $path,
            'mime_type' => 'application/json',
            'file_extension' => 'json',
            'file_size' => strlen($payload),
            'checksum_sha256' => hash('sha256', $payload),
            'uploaded_by' => $request->user()->id,
        ]);
        $document->forceFill([
            'current_version_id' => $version->id,
            'version' => $version->version_label,
            'original_file_name' => $version->original_file_name,
            'storage_path' => $path,
            'file_size' => strlen($payload),
            'checksum_sha256' => $version->checksum_sha256,
            'updated_by' => $request->user()->id,
        ])->save();

        return $version;
    }

    /** @return array<string, mixed> */
    private function closureSnapshot(EngagementClosure $closure): array
    {
        $closure->loadMissing('checklistItems');

        return [
            'closureCode' => $closure->closure_code,
            'revisionNumber' => $closure->revision_number,
            'statusCode' => $closure->status_code,
            'completionAssessmentId' => $closure->completion_assessment_id,
            'closureSummary' => $closure->closure_summary,
            'unresolvedMattersSummary' => $closure->unresolved_matters_summary,
            'lessonsLearnedSummary' => $closure->lessons_learned_summary,
            'checklist' => $closure->checklistItems
                ->map(fn ($item) => $this->checklistData($item))->values()->all(),
            'lockVersion' => $closure->lock_version,
        ];
    }

    /** @param array<string, mixed>|null $old
     * @param  array<string, mixed>|null  $metadata
     */
    private function record(
        Request $request,
        AuditEngagement $engagement,
        EngagementClosure $closure,
        string $action,
        ?string $from,
        ?string $to,
        ?array $old = null,
        ?string $comment = null,
        ?array $metadata = null,
    ): void {
        $snapshot = $this->closureSnapshot($closure);
        $closure->events()->create([
            'audit_engagement_id' => $engagement->id,
            'action_code' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $request->user()->id,
            'comment' => $comment,
            'snapshot_json' => $snapshot,
            'occurred_at' => now(),
            'request_metadata_json' => [
                'ipAddress' => $request->ip(),
                'userAgent' => mb_substr((string) $request->userAgent(), 0, 1000),
                ...($metadata ?? []),
            ],
        ]);
        $this->support->event(
            $request,
            $engagement,
            $action,
            $from,
            $to,
            $old,
            $snapshot,
            $comment,
            'ENGAGEMENT_CLOSURE',
            $closure->id,
            $closure->revision_number,
            $closure->closure_code,
            null,
            $closure->closure_document_version_id ? [$closure->closure_document_version_id] : null,
        );
        $this->support->audit(
            $request,
            'aems.closure.'.strtolower($action),
            $engagement,
            $old,
            $snapshot,
            [
                'subjectType' => 'ENGAGEMENT_CLOSURE',
                'subjectId' => $closure->id,
                ...($metadata ?? []),
            ],
        );
    }

    private function ensureClosure(
        AuditEngagement $engagement,
        EngagementClosure $closure,
    ): void {
        if ((int) $closure->audit_engagement_id !== (int) $engagement->id
            || ! $closure->is_current_revision) {
            abort(404);
        }
    }

    private function assertLock(EngagementClosure $closure, int $lockVersion): void
    {
        if ($closure->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['The Closure record changed in another session. Refresh first.'],
            ]);
        }
    }

    private function load(EngagementClosure $closure): EngagementClosure
    {
        return $closure->fresh([
            'completionAssessment',
            'checklistItems',
            'events',
            'retentionRecord',
            'lessonsLearned',
            'closureDocumentVersion',
        ]);
    }
}
