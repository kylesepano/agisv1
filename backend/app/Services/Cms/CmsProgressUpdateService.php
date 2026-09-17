<?php

namespace App\Services\Cms;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\CmsActionPlanMilestone;
use App\Models\CmsActionPlanVersion;
use App\Models\CmsCorrectiveActionPlan;
use App\Models\CmsMilestoneProgress;
use App\Models\CmsProgressEvidenceLink;
use App\Models\CmsProgressUpdate;
use App\Models\CmsProgressUpdateVersion;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\DocumentLink;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Owns management-reported progress. Recording means completeness review only;
 * it never validates implementation or establishes an implementation state.
 */
class CmsProgressUpdateService
{
    private const VERSION_RELATIONS = [
        'preparer',
        'submitter',
        'reviewStarter',
        'returner',
        'recorder',
        'milestoneProgress.actionPlanMilestone',
        'activeEvidenceLinks.documentVersion',
        'activeEvidenceLinks.document.confidentialityLevel',
        'activeEvidenceLinks.confidentialityLevel',
        'activeEvidenceLinks.linker',
    ];

    public function __construct(
        private readonly CmsRecommendationScopeService $scope,
        private readonly NotificationService $notifications,
    ) {}

    /** @return array{case: CmsRecommendationCase, updates: Collection<int, CmsProgressUpdate>, permittedActions: list<string>} */
    public function forRecommendation(User $actor, int $caseId): array
    {
        $case = $this->scope->resolveVisibleCase($actor, $caseId, 'cms.progress.view');
        $case->load($this->caseRelations());
        $updates = CmsProgressUpdate::query()
            ->where('cms_recommendation_case_id', $case->id)
            ->with($this->familyRelations())
            ->orderByDesc('reporting_sequence')
            ->get();

        return [
            'case' => $case,
            'updates' => $this->filterReadOnlyVersions($actor, $updates),
            'permittedActions' => $this->canResponsible($actor, $case, 'cms.progress.create')
                && $this->hasCurrentAcceptedBaseline($case)
                ? ['create']
                : [],
        ];
    }

    /** @return array{case: CmsRecommendationCase, update: CmsProgressUpdate} */
    public function show(User $actor, int $updateId): array
    {
        [$case, $update] = $this->resolveUpdate($actor, $updateId);
        $case->load($this->caseRelations());
        $update->load($this->familyRelations());
        $this->filterReadOnlyVersions($actor, collect([$update]));

        return ['case' => $case, 'update' => $update];
    }

    /** @param array<string, mixed> $attributes */
    public function create(
        Request $request,
        int $caseId,
        array $attributes,
    ): CmsProgressUpdate {
        $actor = $request->user();
        $result = DB::transaction(function () use ($request, $actor, $caseId, $attributes) {
            $case = $this->scope->resolveVisibleCase(
                $actor,
                $caseId,
                'cms.progress.view',
                true,
            );
            $this->authorizeResponsible($actor, $case, 'cms.progress.create');
            $this->assertCaseLock($case, (int) $attributes['lockVersion']);
            $this->assertMonitoringCase($case);

            $plan = CmsCorrectiveActionPlan::query()
                ->where('cms_recommendation_case_id', $case->id)
                ->lockForUpdate()
                ->first();
            $this->assertAcceptedBaseline($case, $plan);
            $accepted = CmsActionPlanVersion::query()
                ->with('milestones')
                ->lockForUpdate()
                ->findOrFail($plan->accepted_version_id);
            $this->validateReportingPeriod(
                $case,
                $accepted,
                $attributes['reportingPeriodStart'],
                $attributes['reportingPeriodEnd'],
            );

            $sequence = ((int) CmsProgressUpdate::query()
                ->where('cms_recommendation_case_id', $case->id)
                ->lockForUpdate()
                ->max('reporting_sequence')) + 1;
            $update = CmsProgressUpdate::query()->create([
                'cms_recommendation_case_id' => $case->id,
                'cms_corrective_action_plan_id' => $plan->id,
                'accepted_action_plan_version_id' => $accepted->id,
                'reporting_sequence' => $sequence,
                'reporting_period_start' => $attributes['reportingPeriodStart'],
                'reporting_period_end' => $attributes['reportingPeriodEnd'],
                'created_by' => $actor->id,
                'lock_version' => 1,
            ]);
            $version = CmsProgressUpdateVersion::query()->create([
                'cms_progress_update_id' => $update->id,
                'version_number' => 1,
                'status_code' => CmsProgressUpdateVersion::STATUS_DRAFT,
                'active_slot' => 'ACTIVE',
                ...$this->draftAttributes($attributes),
                'baseline_weighted' => $this->baselineWeighted($accepted),
                'prepared_by' => $actor->id,
                'lock_version' => 1,
            ]);
            $this->replaceMilestoneProgress(
                $version,
                $accepted,
                $attributes['milestoneProgress'] ?? [],
                true,
            );
            $this->applyCalculation($version, $attributes);
            $version->save();
            $update->forceFill(['current_version_id' => $version->id])->save();
            $case->forceFill(['lock_version' => $case->lock_version + 1])->save();
            $this->record(
                $request,
                $case,
                $update,
                $version,
                CmsRecommendationEvent::EVENT_PROGRESS_UPDATE_CREATED,
                'cms.progress.created',
                null,
                CmsProgressUpdateVersion::STATUS_DRAFT,
            );

            return $update;
        }, 3);

        return $result->fresh($this->familyRelations());
    }

    /** @param array<string, mixed> $attributes */
    public function update(
        Request $request,
        int $updateId,
        int $versionId,
        array $attributes,
    ): CmsProgressUpdate {
        $actor = $request->user();
        $result = DB::transaction(function () use (
            $request,
            $actor,
            $updateId,
            $versionId,
            $attributes,
        ) {
            [$case, $update] = $this->resolveUpdate($actor, $updateId, true);
            $this->authorizeResponsible($actor, $case, 'cms.progress.update');
            $version = $this->resolveVersion($update, $versionId, true);
            $this->assertVersionLock($version, (int) $attributes['lockVersion']);
            $this->assertCurrentDraft($update, $version);
            $this->assertPeriodUnchanged($update, $attributes);
            $accepted = $this->currentAcceptedBaseline($case, $update);

            $oldLock = $version->lock_version;
            $version->fill($this->draftAttributes($attributes));
            if (array_key_exists('milestoneProgress', $attributes)) {
                $this->replaceMilestoneProgress(
                    $version,
                    $accepted,
                    $attributes['milestoneProgress'],
                );
            }
            $this->applyCalculation($version, $attributes);
            $version->forceFill(['lock_version' => $version->lock_version + 1])->save();
            $update->forceFill(['lock_version' => $update->lock_version + 1])->save();
            $this->record(
                $request,
                $case,
                $update,
                $version,
                CmsRecommendationEvent::EVENT_PROGRESS_UPDATE_UPDATED,
                'cms.progress.updated',
                CmsProgressUpdateVersion::STATUS_DRAFT,
                CmsProgressUpdateVersion::STATUS_DRAFT,
                ['previousVersionLock' => $oldLock],
            );

            return $update;
        }, 3);

        return $result->fresh($this->familyRelations());
    }

    public function submit(
        Request $request,
        int $updateId,
        int $versionId,
        int $lockVersion,
    ): CmsProgressUpdate {
        return $this->transition(
            $request,
            $updateId,
            $versionId,
            $lockVersion,
            CmsProgressUpdateVersion::STATUS_DRAFT,
            CmsProgressUpdateVersion::STATUS_SUBMITTED,
            function (
                CmsRecommendationCase $case,
                CmsProgressUpdate $update,
                CmsProgressUpdateVersion $version,
                User $actor,
            ): array {
                $this->authorizeResponsible($actor, $case, 'cms.progress.submit');
                $this->currentAcceptedBaseline($case, $update);
                $this->assertComplete($update, $version);
                $submittedAt = now();
                $version->forceFill([
                    'submitted_by' => $actor->id,
                    'submitted_at' => $submittedAt,
                ]);

                return [
                    'submitted_by' => $actor->id,
                    'submitted_at' => $submittedAt,
                    'submission_snapshot' => $this->snapshotFor($update, $version),
                ];
            },
            CmsRecommendationEvent::EVENT_PROGRESS_UPDATE_SUBMITTED,
            'cms.progress.submitted',
            'submitted',
        );
    }

    public function startReview(
        Request $request,
        int $updateId,
        int $versionId,
        int $lockVersion,
        ?string $comment,
    ): CmsProgressUpdate {
        return $this->transition(
            $request,
            $updateId,
            $versionId,
            $lockVersion,
            CmsProgressUpdateVersion::STATUS_SUBMITTED,
            CmsProgressUpdateVersion::STATUS_UNDER_REVIEW,
            function (
                CmsRecommendationCase $case,
                CmsProgressUpdate $update,
                CmsProgressUpdateVersion $version,
                User $actor,
            ) use ($comment): array {
                $this->authorizeReviewer($actor, $case, $update, $version, 'cms.progress.review');
                $this->assertSnapshotIntegrity($update, $version);

                return [
                    'review_started_by' => $actor->id,
                    'review_started_at' => now(),
                    'review_comment' => $comment,
                ];
            },
            CmsRecommendationEvent::EVENT_PROGRESS_UPDATE_REVIEW_STARTED,
            'cms.progress.review_started',
            'review_started',
            ['reviewComment' => $comment],
        );
    }

    public function return(
        Request $request,
        int $updateId,
        int $versionId,
        int $lockVersion,
        string $reason,
    ): CmsProgressUpdate {
        return $this->transition(
            $request,
            $updateId,
            $versionId,
            $lockVersion,
            CmsProgressUpdateVersion::STATUS_UNDER_REVIEW,
            CmsProgressUpdateVersion::STATUS_RETURNED,
            function (
                CmsRecommendationCase $case,
                CmsProgressUpdate $update,
                CmsProgressUpdateVersion $version,
                User $actor,
            ) use ($reason): array {
                $this->authorizeReviewer($actor, $case, $update, $version, 'cms.progress.return');
                $this->assertSnapshotIntegrity($update, $version);

                return [
                    'returned_by' => $actor->id,
                    'returned_at' => now(),
                    'return_reason' => $reason,
                ];
            },
            CmsRecommendationEvent::EVENT_PROGRESS_UPDATE_RETURNED,
            'cms.progress.returned',
            'returned',
            ['returnReason' => $reason],
        );
    }

    public function recordUpdate(
        Request $request,
        int $updateId,
        int $versionId,
        int $lockVersion,
        string $comment,
    ): CmsProgressUpdate {
        $actor = $request->user();
        $result = DB::transaction(function () use (
            $request,
            $actor,
            $updateId,
            $versionId,
            $lockVersion,
            $comment,
        ) {
            [$case, $update] = $this->resolveUpdate($actor, $updateId, true);
            $version = $this->resolveVersion($update, $versionId, true);
            $this->assertVersionLock($version, $lockVersion);
            $this->assertStatus($version, CmsProgressUpdateVersion::STATUS_UNDER_REVIEW);
            $this->authorizeReviewer($actor, $case, $update, $version, 'cms.progress.record');
            $this->assertSnapshotIntegrity($update, $version);
            $this->assertComplete($update, $version);
            $this->assertMonitoringCase($case);

            $version->forceFill([
                'status_code' => CmsProgressUpdateVersion::STATUS_RECORDED,
                'active_slot' => null,
                'recorded_by' => $actor->id,
                'recorded_at' => now(),
                'recording_comment' => $comment,
                'lock_version' => $version->lock_version + 1,
            ])->save();
            $update->forceFill([
                'current_version_id' => $version->id,
                'recorded_version_id' => $version->id,
                'lock_version' => $update->lock_version + 1,
            ])->save();
            $this->record(
                $request,
                $case,
                $update,
                $version,
                CmsRecommendationEvent::EVENT_PROGRESS_UPDATE_RECORDED,
                'cms.progress.recorded',
                CmsProgressUpdateVersion::STATUS_UNDER_REVIEW,
                CmsProgressUpdateVersion::STATUS_RECORDED,
                [
                    'recordingComment' => $comment,
                    'recordedVersionId' => $version->id,
                    'notIndependentlyValidated' => true,
                ],
            );
            DB::afterCommit(fn () => $this->notify(
                'recorded',
                $actor->id,
                $case,
                $update,
                $version,
            ));

            return $update;
        }, 3);

        return $result->fresh($this->familyRelations());
    }

    public function revise(
        Request $request,
        int $updateId,
        int $versionId,
        int $lockVersion,
        string $reason,
    ): CmsProgressUpdate {
        $actor = $request->user();
        $result = DB::transaction(function () use (
            $request,
            $actor,
            $updateId,
            $versionId,
            $lockVersion,
            $reason,
        ) {
            [$case, $update] = $this->resolveUpdate($actor, $updateId, true);
            $this->authorizeResponsible($actor, $case, 'cms.progress.revise');
            $source = $this->resolveVersion($update, $versionId, true);
            $this->assertVersionLock($source, $lockVersion);
            throw_unless(
                in_array($source->status_code, [
                    CmsProgressUpdateVersion::STATUS_RETURNED,
                    CmsProgressUpdateVersion::STATUS_RECORDED,
                ], true)
                    && (int) $update->current_version_id === $source->id,
                ValidationException::withMessages([
                    'version' => ['Only the current returned or recorded version may be revised.'],
                ]),
            );
            $this->currentAcceptedBaseline($case, $update);
            if ($update->versions()
                ->whereIn('status_code', CmsProgressUpdateVersion::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'version' => ['An active Progress Update version already exists.'],
                ]);
            }

            $source->load(['milestoneProgress', 'activeEvidenceLinks.document']);
            $versionNumber = ((int) $update->versions()
                ->lockForUpdate()
                ->max('version_number')) + 1;
            $revision = CmsProgressUpdateVersion::query()->create([
                'cms_progress_update_id' => $update->id,
                'version_number' => $versionNumber,
                'previous_version_id' => $source->id,
                'status_code' => CmsProgressUpdateVersion::STATUS_DRAFT,
                'active_slot' => 'ACTIVE',
                ...$this->copyContent($source),
                'prepared_by' => $actor->id,
                'revision_reason' => $reason,
                'lock_version' => 1,
            ]);
            $progressMap = [];
            foreach ($source->milestoneProgress as $progress) {
                $copy = $revision->milestoneProgress()->create(
                    collect($progress->getAttributes())
                        ->except([
                            'id',
                            'cms_progress_update_version_id',
                            'created_at',
                            'updated_at',
                        ])->all(),
                );
                $progressMap[$progress->id] = $copy->id;
            }
            foreach ($source->activeEvidenceLinks as $evidence) {
                $copy = $revision->evidenceLinks()->create([
                    ...collect($evidence->getAttributes())
                        ->except([
                            'id',
                            'cms_progress_update_version_id',
                            'cms_milestone_progress_id',
                            'linked_by',
                            'linked_at',
                            'removed_by',
                            'removed_at',
                            'removal_reason',
                            'created_at',
                            'updated_at',
                        ])->all(),
                    'cms_milestone_progress_id' => $evidence->cms_milestone_progress_id
                        ? $progressMap[$evidence->cms_milestone_progress_id]
                        : null,
                    'linked_by' => $actor->id,
                    'linked_at' => now(),
                ]);
                $this->createDocumentLinks($copy, $revision, $actor);
            }
            $update->forceFill([
                'current_version_id' => $revision->id,
                'lock_version' => $update->lock_version + 1,
            ])->save();
            $this->record(
                $request,
                $case,
                $update,
                $revision,
                CmsRecommendationEvent::EVENT_PROGRESS_UPDATE_REVISION_CREATED,
                'cms.progress.revision_created',
                $source->status_code,
                CmsProgressUpdateVersion::STATUS_DRAFT,
                [
                    'sourceVersionId' => $source->id,
                    'revisionReason' => $reason,
                    'recordedVersionId' => $update->recorded_version_id,
                ],
            );

            return $update;
        }, 3);

        return $result->fresh($this->familyRelations());
    }

    /** @return list<string> */
    public function permittedActions(
        User $actor,
        CmsProgressUpdate $update,
        ?CmsProgressUpdateVersion $version,
    ): array {
        if (! $version) {
            return [];
        }
        $update->loadMissing('case.currentAssignment', 'acceptedActionPlanVersion');
        $case = $update->case;
        $actions = [];
        if ($version->status_code === CmsProgressUpdateVersion::STATUS_DRAFT
            && $this->canResponsible($actor, $case, 'cms.progress.update')) {
            $actions[] = 'update';
            if ($actor->hasPermission('cms.progress.submit')) {
                $actions[] = 'submit';
            }
            if ($actor->hasPermission('cms.evidence.upload')) {
                $actions[] = 'upload-evidence';
            }
        }
        if (in_array($version->status_code, [
            CmsProgressUpdateVersion::STATUS_RETURNED,
            CmsProgressUpdateVersion::STATUS_RECORDED,
        ], true)
            && (int) $update->current_version_id === $version->id
            && $this->canResponsible($actor, $case, 'cms.progress.revise')) {
            $actions[] = 'revise';
        }
        if ($version->status_code === CmsProgressUpdateVersion::STATUS_SUBMITTED
            && $this->canReviewer($actor, $case, $update, $version, 'cms.progress.review')) {
            $actions[] = 'start-review';
        }
        if ($version->status_code === CmsProgressUpdateVersion::STATUS_UNDER_REVIEW) {
            if ($this->canReviewer($actor, $case, $update, $version, 'cms.progress.return')) {
                $actions[] = 'return';
            }
            if ($this->canReviewer($actor, $case, $update, $version, 'cms.progress.record')) {
                $actions[] = 'record';
            }
        }

        return $actions;
    }

    /** @return array<string, mixed> */
    public function completeness(
        CmsProgressUpdate $update,
        CmsProgressUpdateVersion $version,
    ): array {
        $update->loadMissing('acceptedActionPlanVersion.milestones');
        $version->loadMissing([
            'milestoneProgress.evidenceLinks',
            'activeEvidenceLinks',
        ]);
        $errors = [];
        if (blank($version->accomplishment_summary)) {
            $errors['accomplishmentSummary'][] =
                'An accomplishment summary is required before submission.';
        }
        $expected = $update->acceptedActionPlanVersion->milestones;
        if ($version->milestoneProgress->count() !== $expected->count()
            || $version->milestoneProgress->pluck('cms_action_plan_milestone_id')->sort()->values()
                ->all() !== $expected->pluck('id')->sort()->values()->all()) {
            $errors['milestoneProgress'][] =
                'Report progress exactly once for every accepted Action Plan milestone.';
        }
        foreach ($version->milestoneProgress as $index => $progress) {
            $percentage = (float) $progress->management_reported_percentage;
            if (blank($progress->accomplishment_description)
                && blank($progress->no_evidence_explanation)) {
                $errors["milestoneProgress.{$index}.accomplishmentDescription"][] =
                    'Describe the reported accomplishment or explain the absence of progress.';
            }
            $evidenceCount = $progress->evidenceLinks->count();
            if ($progress->management_reported_status_code === 'REPORTED_COMPLETED'
                && $evidenceCount === 0) {
                $errors["milestoneProgress.{$index}.evidence"][] =
                    'Management-reported completion requires supporting evidence.';
            } elseif ($percentage > 0
                && $evidenceCount === 0
                && blank($progress->no_evidence_explanation)) {
                $errors["milestoneProgress.{$index}.noEvidenceExplanation"][] =
                    'Link supporting evidence or explain why none is available.';
            }
        }
        if ($version->activeEvidenceLinks->isEmpty()
            && blank($version->general_evidence_explanation)) {
            $errors['generalEvidenceExplanation'][] =
                'Provide supporting evidence or a general evidence explanation.';
        }
        if (! $version->baseline_weighted
            && $version->management_reported_overall_percentage === null) {
            $errors['managementReportedOverallPercentage'][] =
                'An overall management-reported percentage is required for an unweighted plan.';
        }

        return [
            'complete' => $errors === [],
            'errors' => $errors,
            'baselineWeighted' => $version->baseline_weighted,
            'reportedOverallPercentage' => $version->management_reported_overall_percentage,
            'systemCalculatedWeightedPercentage' => $version
                ->system_calculated_weighted_percentage,
            'evidenceCount' => $version->activeEvidenceLinks->count(),
            'milestoneProgressCount' => $version->milestoneProgress->count(),
            'notIndependentlyValidated' => true,
        ];
    }

    /**
     * Safe aggregate resolver shared with the evidence service.
     *
     * @return array{0: CmsRecommendationCase, 1: CmsProgressUpdate, 2: CmsProgressUpdateVersion}
     */
    public function resolveVersionForActor(
        User $actor,
        int $updateId,
        int $versionId,
        bool $lock = false,
    ): array {
        [$case, $update] = $this->resolveUpdate($actor, $updateId, $lock);

        return [$case, $update, $this->resolveVersion($update, $versionId, $lock)];
    }

    public function authorizeResponsibleAction(
        User $actor,
        CmsRecommendationCase $case,
        string $permission,
    ): void {
        $this->authorizeResponsible($actor, $case, $permission);
    }

    public function assertDraftEvidenceMutation(
        CmsProgressUpdateVersion $version,
        int $lockVersion,
    ): void {
        $this->assertVersionLock($version, $lockVersion);
        $this->assertStatus($version, CmsProgressUpdateVersion::STATUS_DRAFT);
    }

    /** Records a CMS evidence operation inside the caller's transaction. */
    public function recordEvidence(
        Request $request,
        CmsRecommendationCase $case,
        CmsProgressUpdate $update,
        CmsProgressUpdateVersion $version,
        CmsProgressEvidenceLink $evidence,
        string $eventCode,
        string $action,
    ): void {
        $this->record(
            $request,
            $case,
            $update,
            $version,
            $eventCode,
            $action,
            $version->status_code,
            $version->status_code,
            [
                'evidenceLinkId' => $evidence->id,
                'documentId' => $evidence->document_id,
                'documentVersionId' => $evidence->document_version_id,
                'checksumSha256' => $evidence->checksum_sha256,
                'milestoneProgressId' => $evidence->cms_milestone_progress_id,
            ],
        );
    }

    private function transition(
        Request $request,
        int $updateId,
        int $versionId,
        int $lockVersion,
        string $from,
        string $to,
        callable $attributes,
        string $eventCode,
        string $auditAction,
        string $notification,
        array $metadata = [],
    ): CmsProgressUpdate {
        $actor = $request->user();
        $result = DB::transaction(function () use (
            $request,
            $actor,
            $updateId,
            $versionId,
            $lockVersion,
            $from,
            $to,
            $attributes,
            $eventCode,
            $auditAction,
            $notification,
            $metadata,
        ) {
            [$case, $update] = $this->resolveUpdate($actor, $updateId, true);
            $version = $this->resolveVersion($update, $versionId, true);
            $this->assertVersionLock($version, $lockVersion);
            $this->assertStatus($version, $from);
            $version->forceFill([
                ...$attributes($case, $update, $version, $actor),
                'status_code' => $to,
                'active_slot' => in_array(
                    $to,
                    CmsProgressUpdateVersion::ACTIVE_STATUSES,
                    true,
                ) ? 'ACTIVE' : null,
                'lock_version' => $version->lock_version + 1,
            ])->save();
            $update->forceFill(['lock_version' => $update->lock_version + 1])->save();
            $this->record(
                $request,
                $case,
                $update,
                $version,
                $eventCode,
                $auditAction,
                $from,
                $to,
                $metadata,
            );
            DB::afterCommit(fn () => $this->notify(
                $notification,
                $actor->id,
                $case,
                $update,
                $version,
            ));

            return $update;
        }, 3);

        return $result->fresh($this->familyRelations());
    }

    /** @return array{0: CmsRecommendationCase, 1: CmsProgressUpdate} */
    private function resolveUpdate(User $actor, int $updateId, bool $lock = false): array
    {
        $reference = CmsProgressUpdate::query()->find($updateId);
        throw_unless(
            $reference,
            new HttpException(404, 'The Progress Update is unavailable.'),
        );
        $case = $this->scope->resolveVisibleCase(
            $actor,
            $reference->cms_recommendation_case_id,
            'cms.progress.view',
            $lock,
        );
        $query = CmsProgressUpdate::query()
            ->whereKey($updateId)
            ->where('cms_recommendation_case_id', $case->id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $update = $query->first();
        throw_unless(
            $update,
            new HttpException(404, 'The Progress Update is unavailable.'),
        );

        return [$case, $update];
    }

    private function resolveVersion(
        CmsProgressUpdate $update,
        int $versionId,
        bool $lock = false,
    ): CmsProgressUpdateVersion {
        $query = $update->versions()->whereKey($versionId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $version = $query->first();
        throw_unless(
            $version,
            new HttpException(404, 'The Progress Update version is unavailable.'),
        );

        return $version;
    }

    private function authorizeResponsible(
        User $actor,
        CmsRecommendationCase $case,
        string $permission,
    ): void {
        throw_unless(
            $this->canResponsible($actor, $case, $permission),
            new HttpException(
                403,
                'Only an authorized responsible-office user may perform this action.',
            ),
        );
    }

    private function canResponsible(
        User $actor,
        CmsRecommendationCase $case,
        string $permission,
    ): bool {
        return $this->scope->isUsableAccount($actor)
            && $actor->hasPermission($permission)
            && $actor->office_id !== null
            && (int) $actor->office_id === (int) $case->lead_responsible_office_id;
    }

    private function authorizeReviewer(
        User $actor,
        CmsRecommendationCase $case,
        CmsProgressUpdate $update,
        CmsProgressUpdateVersion $version,
        string $permission,
    ): void {
        throw_unless(
            $this->canReviewer($actor, $case, $update, $version, $permission),
            new HttpException(
                403,
                'An independent authorized Compliance reviewer is required.',
            ),
        );
    }

    private function canReviewer(
        User $actor,
        CmsRecommendationCase $case,
        CmsProgressUpdate $update,
        CmsProgressUpdateVersion $version,
        string $permission,
    ): bool {
        $update->loadMissing('acceptedActionPlanVersion');
        if (! $this->scope->isUsableAccount($actor)
            || ! $actor->hasPermission($permission)
            || (int) $actor->office_id === (int) $case->lead_responsible_office_id
            || in_array($actor->id, array_filter([
                $version->prepared_by,
                $version->submitted_by,
                $update->acceptedActionPlanVersion?->focal_user_id,
            ]), true)) {
            return false;
        }
        if ($actor->hasGlobalEngagementAccess()) {
            return true;
        }

        return $case->currentAssignment()
            ->where('user_id', $actor->id)
            ->where('assignment_role_code', 'COMPLIANCE_MONITOR')
            ->where('is_current', true)
            ->exists();
    }

    private function hasCurrentAcceptedBaseline(CmsRecommendationCase $case): bool
    {
        return in_array($case->status_code, [
            CmsRecommendationCase::STATUS_MONITORING,
            CmsRecommendationCase::STATUS_PARTIALLY_IMPLEMENTED,
        ], true)
            && $case->actionPlan
            && $case->actionPlan->accepted_version_id
            && $case->actionPlan->acceptedVersion?->status_code === CmsActionPlanVersion::STATUS_ACCEPTED;
    }

    private function assertAcceptedBaseline(
        CmsRecommendationCase $case,
        ?CmsCorrectiveActionPlan $plan,
    ): void {
        if (! $plan
            || ! $plan->accepted_version_id
            || $plan->acceptedVersion()->value('status_code') !== CmsActionPlanVersion::STATUS_ACCEPTED) {
            throw ValidationException::withMessages([
                'actionPlan' => [
                    'A current accepted Action Plan baseline is required for progress reporting.',
                ],
            ]);
        }
    }

    private function currentAcceptedBaseline(
        CmsRecommendationCase $case,
        CmsProgressUpdate $update,
    ): CmsActionPlanVersion {
        $this->assertMonitoringCase($case);
        $plan = CmsCorrectiveActionPlan::query()
            ->whereKey($update->cms_corrective_action_plan_id)
            ->lockForUpdate()
            ->first();
        $this->assertAcceptedBaseline($case, $plan);
        if ((int) $plan->accepted_version_id !== (int) $update->accepted_action_plan_version_id) {
            throw ValidationException::withMessages([
                'actionPlan' => [
                    'The accepted Action Plan baseline changed. Create a new Progress Update against the current baseline.',
                ],
            ]);
        }

        return CmsActionPlanVersion::query()
            ->with('milestones')
            ->findOrFail($update->accepted_action_plan_version_id);
    }

    private function assertMonitoringCase(CmsRecommendationCase $case): void
    {
        if (! in_array($case->status_code, [
            CmsRecommendationCase::STATUS_MONITORING,
            CmsRecommendationCase::STATUS_PARTIALLY_IMPLEMENTED,
        ], true)) {
            throw ValidationException::withMessages([
                'recommendation' => [
                    'Progress Updates require MONITORING or PARTIALLY_IMPLEMENTED status.',
                ],
            ]);
        }
    }

    private function validateReportingPeriod(
        CmsRecommendationCase $case,
        CmsActionPlanVersion $accepted,
        mixed $startValue,
        mixed $endValue,
    ): void {
        $start = CarbonImmutable::parse($startValue)->startOfDay();
        $end = CarbonImmutable::parse($endValue)->startOfDay();
        $errors = [];
        if ($end->lt($start)) {
            $errors['reportingPeriodEnd'][] =
                'The reporting-period end cannot precede its start.';
        }
        if ($accepted->accepted_at && $start->lt($accepted->accepted_at->startOfDay())) {
            $errors['reportingPeriodStart'][] =
                'The reporting period cannot begin before the accepted Action Plan baseline.';
        }
        $overlap = CmsProgressUpdate::query()
            ->where('cms_recommendation_case_id', $case->id)
            ->whereDate('reporting_period_start', '<=', $end->toDateString())
            ->whereDate('reporting_period_end', '>=', $start->toDateString())
            ->exists();
        if ($overlap) {
            $errors['reportingPeriodEnd'][] =
                'This reporting period overlaps an existing Progress Update.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function assertPeriodUnchanged(
        CmsProgressUpdate $update,
        array $attributes,
    ): void {
        foreach ([
            'reportingPeriodStart' => 'reporting_period_start',
            'reportingPeriodEnd' => 'reporting_period_end',
        ] as $input => $column) {
            if (array_key_exists($input, $attributes)
                && CarbonImmutable::parse($attributes[$input])->toDateString()
                    !== $update->{$column}->toDateString()) {
                throw ValidationException::withMessages([
                    $input => [
                        'A Progress Update reporting period is fixed after family creation.',
                    ],
                ]);
            }
        }
    }

    /** @param array<string, mixed> $attributes */
    private function draftAttributes(array $attributes): array
    {
        $mapping = [
            'accomplishmentSummary' => 'accomplishment_summary',
            'managementReportedOverallPercentage' => 'management_reported_overall_percentage',
            'issuesAndConstraints' => 'issues_and_constraints',
            'correctiveActionsForDelays' => 'corrective_actions_for_delays',
            'nextSteps' => 'next_steps',
            'forecastCompletionDate' => 'forecast_completion_date',
            'managementDeclaration' => 'management_declaration',
            'generalEvidenceExplanation' => 'general_evidence_explanation',
        ];

        return collect($mapping)
            ->filter(fn (string $column, string $key): bool => array_key_exists($key, $attributes))
            ->mapWithKeys(fn (string $column, string $key): array => [
                $column => $attributes[$key],
            ])->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     */
    private function replaceMilestoneProgress(
        CmsProgressUpdateVersion $version,
        CmsActionPlanVersion $accepted,
        array $payload,
        bool $initializeMissing = false,
    ): void {
        $accepted->loadMissing('milestones');
        $milestones = $accepted->milestones->keyBy('id');
        $seen = [];
        foreach ($payload as $index => $entry) {
            $id = (int) $entry['actionPlanMilestoneId'];
            if (isset($seen[$id])) {
                throw ValidationException::withMessages([
                    "milestoneProgress.{$index}.actionPlanMilestoneId" => [
                        'Each accepted milestone may be reported only once.',
                    ],
                ]);
            }
            $seen[$id] = true;
            if (! $milestones->has($id)) {
                throw ValidationException::withMessages([
                    "milestoneProgress.{$index}.actionPlanMilestoneId" => [
                        'The milestone does not belong to this accepted Action Plan baseline.',
                    ],
                ]);
            }
            $this->validateProgressEntry($entry, $index);
        }

        $existing = $version->milestoneProgress()
            ->get()
            ->keyBy('cms_action_plan_milestone_id');
        $entries = collect($payload)->keyBy(
            fn (array $entry): int => (int) $entry['actionPlanMilestoneId'],
        );
        foreach ($milestones->values() as $index => $milestone) {
            $entry = $entries->get($milestone->id);
            $progress = $existing->get($milestone->id);
            if (! $entry && ! $initializeMissing && $progress) {
                continue;
            }
            if (! $entry && ! $initializeMissing) {
                continue;
            }
            $entry ??= [
                'managementReportedStatusCode' => 'NOT_STARTED',
                'managementReportedPercentage' => 0,
                'displayOrder' => $index + 1,
            ];
            $values = [
                'cms_action_plan_milestone_id' => $milestone->id,
                'milestone_sequence' => $milestone->sequence_number,
                'milestone_snapshot' => $this->milestoneSnapshot($milestone),
                'management_reported_status_code' => $entry['managementReportedStatusCode'],
                'management_reported_percentage' => $entry['managementReportedPercentage'],
                'accomplishment_description' => $entry['accomplishmentDescription'] ?? null,
                'issues_and_constraints' => $entry['issuesAndConstraints'] ?? null,
                'next_step' => $entry['nextStep'] ?? null,
                'forecast_completion_date' => $entry['forecastCompletionDate'] ?? null,
                'no_evidence_explanation' => $entry['noEvidenceExplanation'] ?? null,
                'display_order' => $entry['displayOrder'] ?? ($index + 1),
            ];
            if ($progress) {
                $progress->fill($values)->save();
            } else {
                $version->milestoneProgress()->create($values);
            }
        }
    }

    /** @param array<string, mixed> $entry */
    private function validateProgressEntry(array $entry, int $index): void
    {
        $status = $entry['managementReportedStatusCode'];
        $percentage = (float) $entry['managementReportedPercentage'];
        $errors = [];
        if ($status === 'NOT_STARTED' && abs($percentage) > 0.001) {
            $errors["milestoneProgress.{$index}.managementReportedPercentage"][] =
                'NOT_STARTED must report 0%.';
        }
        if ($status === 'REPORTED_COMPLETED' && abs($percentage - 100) > 0.001) {
            $errors["milestoneProgress.{$index}.managementReportedPercentage"][] =
                'REPORTED_COMPLETED must report 100%.';
        }
        if ($status === 'IN_PROGRESS' && ($percentage <= 0 || $percentage >= 100)) {
            $errors["milestoneProgress.{$index}.managementReportedPercentage"][] =
                'IN_PROGRESS must be greater than 0% and less than 100%.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function applyCalculation(
        CmsProgressUpdateVersion $version,
        array $attributes,
    ): void {
        $version->load('milestoneProgress');
        if ($version->baseline_weighted) {
            $weighted = $version->milestoneProgress->sum(function ($progress): float {
                $weight = (float) data_get($progress->milestone_snapshot, 'weightPercentage', 0);

                return (float) $progress->management_reported_percentage * $weight;
            });
            $calculated = round($weighted / 100, 2, PHP_ROUND_HALF_UP);
            if (array_key_exists('managementReportedOverallPercentage', $attributes)
                && $attributes['managementReportedOverallPercentage'] !== null
                && abs((float) $attributes['managementReportedOverallPercentage'] - $calculated) > 0.001) {
                throw ValidationException::withMessages([
                    'managementReportedOverallPercentage' => [
                        'The overall percentage must match the server-calculated weighted result.',
                    ],
                ]);
            }
            $version->forceFill([
                'management_reported_overall_percentage' => $calculated,
                'system_calculated_weighted_percentage' => $calculated,
            ]);
        } else {
            $version->forceFill(['system_calculated_weighted_percentage' => null]);
        }
    }

    private function baselineWeighted(CmsActionPlanVersion $accepted): bool
    {
        $accepted->loadMissing('milestones');

        return $accepted->milestones->isNotEmpty()
            && $accepted->milestones->every(
                fn ($milestone): bool => $milestone->weight_percentage !== null,
            );
    }

    /** @return array<string, mixed> */
    private function milestoneSnapshot(CmsActionPlanMilestone $milestone): array
    {
        return [
            'id' => $milestone->id,
            'sequenceNumber' => $milestone->sequence_number,
            'title' => $milestone->title,
            'description' => $milestone->description,
            'expectedOutput' => $milestone->expected_output,
            'successIndicator' => $milestone->success_indicator,
            'verificationMethod' => $milestone->verification_method,
            'responsibleOfficeId' => $milestone->responsible_office_id,
            'responsibleUserId' => $milestone->responsible_user_id,
            'plannedStartDate' => $milestone->planned_start_date?->toDateString(),
            'plannedTargetDate' => $milestone->planned_target_date?->toDateString(),
            'weightPercentage' => $milestone->weight_percentage,
            'displayOrder' => $milestone->display_order,
        ];
    }

    private function assertComplete(
        CmsProgressUpdate $update,
        CmsProgressUpdateVersion $version,
    ): void {
        $result = $this->completeness($update, $version);
        if (! $result['complete']) {
            throw ValidationException::withMessages($result['errors']);
        }
    }

    /** @return array<string, mixed> */
    private function snapshotFor(
        CmsProgressUpdate $update,
        CmsProgressUpdateVersion $version,
    ): array {
        $update->loadMissing([
            'case.recommendation',
            'acceptedActionPlanVersion.milestones',
        ]);
        $version->loadMissing([
            'milestoneProgress',
            'activeEvidenceLinks.documentVersion',
        ]);

        return [
            'recommendationCaseId' => $update->cms_recommendation_case_id,
            'recommendationCode' => $update->case->recommendation?->recommendation_code,
            'progressUpdateId' => $update->id,
            'versionNumber' => $version->version_number,
            'reportingPeriodStart' => $update->reporting_period_start->toDateString(),
            'reportingPeriodEnd' => $update->reporting_period_end->toDateString(),
            'acceptedActionPlanVersionId' => $update->accepted_action_plan_version_id,
            'acceptedActionPlanVersionNumber' => $update->acceptedActionPlanVersion->version_number,
            'content' => $this->copyContent($version),
            'milestoneProgress' => $version->milestoneProgress->map(
                fn (CmsMilestoneProgress $progress): array => [
                    'actionPlanMilestoneId' => $progress->cms_action_plan_milestone_id,
                    'milestoneSnapshot' => $progress->milestone_snapshot,
                    'managementReportedStatusCode' => $progress->management_reported_status_code,
                    'managementReportedPercentage' => $progress->management_reported_percentage,
                    'accomplishmentDescription' => $progress->accomplishment_description,
                    'issuesAndConstraints' => $progress->issues_and_constraints,
                    'nextStep' => $progress->next_step,
                    'forecastCompletionDate' => $progress->forecast_completion_date?->toDateString(),
                    'noEvidenceExplanation' => $progress->no_evidence_explanation,
                    'displayOrder' => $progress->display_order,
                ],
            )->values()->all(),
            'evidence' => $version->activeEvidenceLinks->map(
                fn (CmsProgressEvidenceLink $evidence): array => [
                    'evidenceLinkId' => $evidence->id,
                    'milestoneProgressId' => $evidence->cms_milestone_progress_id,
                    'documentId' => $evidence->document_id,
                    'documentVersionId' => $evidence->document_version_id,
                    'checksumSha256' => $evidence->checksum_sha256,
                    'confidentialityCode' => $evidence->confidentiality_code_snapshot,
                ],
            )->values()->all(),
            'preparedBy' => $version->prepared_by,
            'submittedBy' => $version->submitted_by,
            'submittedAt' => $version->submitted_at?->format('Y-m-d\TH:i:sP'),
            'notIndependentlyValidated' => true,
        ];
    }

    private function assertSnapshotIntegrity(
        CmsProgressUpdate $update,
        CmsProgressUpdateVersion $version,
    ): void {
        if (! $version->submission_snapshot
            || json_encode($version->submission_snapshot)
                !== json_encode($this->snapshotFor($update, $version))) {
            throw ValidationException::withMessages([
                'version' => [
                    'The submitted Progress Update snapshot is missing or no longer matches its content.',
                ],
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function copyContent(CmsProgressUpdateVersion $version): array
    {
        return collect($version->getAttributes())->only([
            'accomplishment_summary',
            'management_reported_overall_percentage',
            'system_calculated_weighted_percentage',
            'baseline_weighted',
            'issues_and_constraints',
            'corrective_actions_for_delays',
            'next_steps',
            'forecast_completion_date',
            'management_declaration',
            'general_evidence_explanation',
        ])->all();
    }

    private function assertCaseLock(CmsRecommendationCase $case, int $lockVersion): void
    {
        if ((int) $case->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => [
                    'The CMS recommendation changed. Refresh before retrying.',
                ],
            ]);
        }
    }

    private function assertVersionLock(
        CmsProgressUpdateVersion $version,
        int $lockVersion,
    ): void {
        if ((int) $version->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => [
                    'The Progress Update version changed. Refresh before retrying.',
                ],
            ]);
        }
    }

    private function assertStatus(
        CmsProgressUpdateVersion $version,
        string $status,
    ): void {
        if ($version->status_code !== $status) {
            throw ValidationException::withMessages([
                'status' => ["This action requires a Progress Update in {$status} status."],
            ]);
        }
    }

    private function assertCurrentDraft(
        CmsProgressUpdate $update,
        CmsProgressUpdateVersion $version,
    ): void {
        $this->assertStatus($version, CmsProgressUpdateVersion::STATUS_DRAFT);
        if ((int) $update->current_version_id !== $version->id) {
            throw ValidationException::withMessages([
                'version' => ['Only the current Progress Update draft may be edited.'],
            ]);
        }
    }

    /** @return list<string> */
    private function caseRelations(): array
    {
        return [
            'recommendation',
            'leadResponsibleOffice',
            'currentAssignment.user',
            'actionPlan.acceptedVersion.milestones',
        ];
    }

    /** @return list<string> */
    private function familyRelations(): array
    {
        return [
            'case.recommendation',
            'case.leadResponsibleOffice',
            'case.currentAssignment.user',
            'actionPlan',
            'acceptedActionPlanVersion.milestones',
            'creator',
            'versions',
            ...array_map(
                fn (string $relation): string => "currentVersion.{$relation}",
                self::VERSION_RELATIONS,
            ),
            ...array_map(
                fn (string $relation): string => "recordedVersion.{$relation}",
                self::VERSION_RELATIONS,
            ),
            ...array_map(
                fn (string $relation): string => "versions.{$relation}",
                self::VERSION_RELATIONS,
            ),
        ];
    }

    /**
     * @param  Collection<int, CmsProgressUpdate>  $updates
     * @return Collection<int, CmsProgressUpdate>
     */
    private function filterReadOnlyVersions(User $actor, Collection $updates): Collection
    {
        if (! $actor->hasPermission('access.read_only_scope')) {
            return $updates;
        }
        $visible = [
            CmsProgressUpdateVersion::STATUS_SUBMITTED,
            CmsProgressUpdateVersion::STATUS_UNDER_REVIEW,
            CmsProgressUpdateVersion::STATUS_RECORDED,
        ];
        foreach ($updates as $update) {
            $update->setRelation(
                'versions',
                $update->versions->whereIn('status_code', $visible)->values(),
            );
            if ($update->currentVersion
                && ! in_array($update->currentVersion->status_code, $visible, true)) {
                $update->setRelation('currentVersion', null);
            }
        }

        return $updates;
    }

    private function createDocumentLinks(
        CmsProgressEvidenceLink $evidence,
        CmsProgressUpdateVersion $version,
        User $actor,
    ): void {
        $update = $version->progressUpdate;
        DocumentLink::query()->firstOrCreate(
            [
                'document_id' => $evidence->document_id,
                'module_code' => 'CMS',
                'record_type' => 'PROGRESS_UPDATE_VERSION',
                'record_id' => $version->id,
            ],
            [
                'record_code' => sprintf(
                    'CMS-UPD-%06d-%03d-V%d',
                    $update->cms_recommendation_case_id,
                    $update->reporting_sequence,
                    $version->version_number,
                ),
                'record_label' => 'CMS management-reported Progress Update',
                'linked_by' => $actor->id,
            ],
        );
        if ($evidence->cms_milestone_progress_id) {
            DocumentLink::query()->firstOrCreate(
                [
                    'document_id' => $evidence->document_id,
                    'module_code' => 'CMS',
                    'record_type' => 'MILESTONE_PROGRESS',
                    'record_id' => $evidence->cms_milestone_progress_id,
                ],
                [
                    'record_code' => "CMS-MPR-{$evidence->cms_milestone_progress_id}",
                    'record_label' => 'CMS management-reported milestone progress',
                    'linked_by' => $actor->id,
                ],
            );
        }
    }

    private function record(
        Request $request,
        CmsRecommendationCase $case,
        CmsProgressUpdate $update,
        CmsProgressUpdateVersion $version,
        string $eventCode,
        string $auditAction,
        ?string $previousVersionStatus,
        string $newVersionStatus,
        array $extra = [],
    ): void {
        $metadata = [
            'caseId' => $case->id,
            'progressUpdateId' => $update->id,
            'versionId' => $version->id,
            'versionNumber' => $version->version_number,
            'reportingPeriodStart' => $update->reporting_period_start->toDateString(),
            'reportingPeriodEnd' => $update->reporting_period_end->toDateString(),
            'acceptedActionPlanVersionId' => $update->accepted_action_plan_version_id,
            'previousVersionStatus' => $previousVersionStatus,
            'newVersionStatus' => $newVersionStatus,
            'caseStatus' => $case->status_code,
            'milestoneProgressCount' => $version->milestoneProgress()->count(),
            'reportedOverallPercentage' => $version->management_reported_overall_percentage,
            'systemCalculatedWeightedPercentage' => $version
                ->system_calculated_weighted_percentage,
            'evidenceLinkCount' => $version->activeEvidenceLinks()->count(),
            'actorId' => $request->user()->id,
            'notIndependentlyValidated' => true,
            ...$extra,
        ];
        CmsRecommendationEvent::query()->create([
            'cms_recommendation_case_id' => $case->id,
            'cms_recommendation_id' => $case->cms_recommendation_id,
            'idempotency_key' => "cms-progress:{$version->id}:{$eventCode}:{$version->lock_version}",
            'event_code' => $eventCode,
            'source_module' => 'CMS',
            'actor_id' => $request->user()->id,
            'previous_status' => $case->status_code,
            'new_status' => $case->status_code,
            'event_metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => $auditAction,
            'description' => 'Updated the controlled CMS management-reported progress workflow.',
            'old_values' => ['versionStatus' => $previousVersionStatus],
            'new_values' => ['versionStatus' => $newVersionStatus],
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => ['module' => 'CMS', ...$metadata],
        ]);
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => $auditAction,
            'auditable_type' => CmsProgressUpdate::class,
            'auditable_id' => $update->id,
            'old_values' => ['versionStatus' => $previousVersionStatus],
            'new_values' => [
                'versionStatus' => $newVersionStatus,
                'currentVersionId' => $update->current_version_id,
                'recordedVersionId' => $update->recorded_version_id,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => ['module' => 'CMS', ...$metadata],
        ]);
    }

    private function notify(
        string $transition,
        int $actorId,
        CmsRecommendationCase $case,
        CmsProgressUpdate $update,
        CmsProgressUpdateVersion $version,
    ): void {
        $case->loadMissing('currentAssignment', 'recommendation');
        $update->loadMissing('acceptedActionPlanVersion');
        $recipients = match ($transition) {
            'submitted' => $this->reviewerRecipients($case),
            'review_started' => collect([
                $version->submitted_by,
                $update->acceptedActionPlanVersion?->focal_user_id,
            ]),
            'returned' => collect([
                $version->submitted_by,
                $update->acceptedActionPlanVersion?->focal_user_id,
            ])->merge($this->responsibleRecipients($case)),
            'recorded' => collect([
                $version->submitted_by,
                $update->acceptedActionPlanVersion?->focal_user_id,
                $case->currentAssignment?->user_id,
            ])->merge($this->reviewerRecipients($case)),
            default => collect(),
        };
        $labels = [
            'submitted' => [
                'PROGRESS_UPDATE_SUBMITTED',
                'Progress Update submitted',
                'A management-reported Progress Update is ready for completeness review.',
            ],
            'review_started' => [
                'PROGRESS_UPDATE_REVIEW_STARTED',
                'Progress Update review started',
                'Completeness review of the management-reported Progress Update has started.',
            ],
            'returned' => [
                'PROGRESS_UPDATE_RETURNED',
                'Progress Update returned',
                filled($version->return_reason)
                    ? "Return instructions: {$version->return_reason}"
                    : 'The management-reported Progress Update was returned for correction.',
            ],
            'recorded' => [
                'PROGRESS_UPDATE_RECORDED',
                'Management Progress Update recorded',
                'The management-reported Progress Update was recorded for follow-up monitoring and has not been independently validated.',
            ],
        ];
        [$type, $title, $message] = $labels[$transition];
        $this->notifications->send($recipients->filter()->unique(), [
            'actorId' => $actorId,
            'type' => "CMS_{$type}",
            'category' => 'WORKFLOW',
            'priority' => in_array($transition, ['returned', 'recorded'], true)
                ? 'HIGH'
                : 'NORMAL',
            'moduleCode' => 'CMS',
            'title' => $title,
            'message' => $message,
            'actionUrl' => "/compliance-management/recommendations/{$case->id}",
            'actionLabel' => 'Open recommendation',
            'subjectType' => CmsProgressUpdate::class,
            'subjectId' => $update->id,
            'subjectCode' => sprintf(
                'CMS-UPD-%06d-%03d',
                $case->id,
                $update->reporting_sequence,
            ),
            'dedupeKey' => "cms-progress:{$update->id}:{$version->id}:{$transition}",
            'metadata' => [
                'caseId' => $case->id,
                'progressUpdateId' => $update->id,
                'versionId' => $version->id,
                'versionNumber' => $version->version_number,
                'notIndependentlyValidated' => true,
            ],
        ]);
    }

    /** @return Collection<int, int> */
    private function reviewerRecipients(CmsRecommendationCase $case): Collection
    {
        $recipients = collect([$case->currentAssignment?->user_id]);
        $management = User::query()
            ->where('is_active', true)
            ->with(['role.permissions', 'roles.permissions'])
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission('cms.progress.review')
                && $this->scope->canViewClassification(
                    $user,
                    $case->recommendation?->confidentiality_code_snapshot,
                ))
            ->pluck('id');

        return $recipients->merge($management)->filter()->unique()->values();
    }

    /** @return Collection<int, int> */
    private function responsibleRecipients(CmsRecommendationCase $case): Collection
    {
        return User::query()
            ->where('office_id', $case->lead_responsible_office_id)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query
                    ->whereHas(
                        'roles.permissions',
                        fn ($permission) => $permission->where('code', 'access.auditee_scope'),
                    )
                    ->orWhereHas(
                        'role.permissions',
                        fn ($permission) => $permission->where('code', 'access.auditee_scope'),
                    );
            })->pluck('id');
    }
}
