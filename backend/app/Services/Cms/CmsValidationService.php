<?php

namespace App\Services\Cms;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\CmsActionPlanVersion;
use App\Models\CmsProgressUpdate;
use App\Models\CmsProgressUpdateVersion;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\CmsValidationAssignment;
use App\Models\CmsValidationEvidenceLink;
use App\Models\CmsValidationReview;
use App\Models\CmsValidationVersion;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Authoritative independent-validation aggregate. Management reporting remains
 * immutable input; only finalized professional conclusions change case state.
 */
class CmsValidationService
{
    private const VERSION_RELATIONS = [
        'validator',
        'preparer',
        'submitter',
        'supervisoryReviewer',
        'returner',
        'finalizer',
        'items.actionPlanMilestone',
        'items.milestoneProgress',
        'evidenceAssessments.progressEvidenceLink.documentVersion',
        'evidenceAssessments.validationEvidenceLink.documentVersion',
        'evidenceAssessments.assessor',
        'activeEvidenceLinks.documentVersion',
        'activeEvidenceLinks.confidentialityLevel',
        'activeEvidenceLinks.linker',
    ];

    public function __construct(
        private readonly CmsRecommendationScopeService $scope,
        private readonly NotificationService $notifications,
    ) {}

    /** @return array{case: CmsRecommendationCase, reviews: Collection<int, CmsValidationReview>, permittedActions: list<string>} */
    public function forRecommendation(User $actor, int $caseId): array
    {
        $case = $this->scope->resolveVisibleCase($actor, $caseId, 'cms.validation.view');
        $case->load($this->caseRelations());
        $reviews = CmsValidationReview::query()
            ->where('cms_recommendation_case_id', $case->id)
            ->with($this->reviewRelations())
            ->orderByDesc('validation_sequence')
            ->get();

        return [
            'case' => $case,
            'reviews' => $this->filterReadOnlyVersions($actor, $reviews),
            'permittedActions' => $this->canCreate($actor, $case) ? ['create'] : [],
        ];
    }

    /**
     * Return only the records and users that can safely participate in a
     * Validation Review for this recommendation. Eligibility is evaluated by
     * the same aggregate guards used by create() and assign().
     *
     * @return array{case: CmsRecommendationCase, eligibleRecordedProgressUpdates: list<array<string, mixed>>, eligibleValidators: list<array<string, mixed>>, unavailableReasons: list<string>}
     */
    public function validationOptions(User $actor, int $caseId): array
    {
        $this->scope->authorizeValidationAssignmentAuthority($actor);
        $case = $this->scope->resolveVisibleCase($actor, $caseId, 'cms.validation.view');
        $case->load([
            'recommendation',
            'leadResponsibleOffice',
            'currentAssignment.user',
            'activeValidationReview.recordedProgressUpdateVersion.progressUpdate.recordedVersion',
            'activeValidationReview.recordedProgressUpdateVersion.activeEvidenceLinks',
        ]);

        $updates = CmsProgressUpdate::query()
            ->where('cms_recommendation_case_id', $case->id)
            ->whereNotNull('recorded_version_id')
            ->with([
                'acceptedActionPlanVersion',
                'recordedVersion.progressUpdate',
                'recordedVersion.activeEvidenceLinks',
            ])
            ->orderByDesc('reporting_period_end')
            ->orderByDesc('reporting_sequence')
            ->get();

        $eligibleUpdates = $updates
            ->filter(function (CmsProgressUpdate $update) use ($case): bool {
                $recorded = $update->recordedVersion;
                if (! $recorded || CmsValidationReview::query()
                    ->where('recorded_progress_update_version_id', $recorded->id)
                    ->exists()) {
                    return false;
                }

                try {
                    $this->assertRecordedVersionEligible($case, $update, $recorded);
                } catch (ValidationException) {
                    return false;
                }

                return true;
            })
            ->map(fn (CmsProgressUpdate $update): array => [
                'id' => $update->id,
                'displayCode' => sprintf(
                    'CMS-UPD-%06d-%03d',
                    $case->id,
                    $update->reporting_sequence,
                ),
                'reportingSequence' => $update->reporting_sequence,
                'reportingPeriodStart' => $update->reporting_period_start?->toDateString(),
                'reportingPeriodEnd' => $update->reporting_period_end?->toDateString(),
                'recordedVersionId' => $update->recorded_version_id,
                'recordedVersionNumber' => $update->recordedVersion?->version_number,
                'managementReportedPercentage' => $update->recordedVersion
                    ?->management_reported_overall_percentage,
                'systemCalculatedWeightedReportedPercentage' => $update->recordedVersion
                    ?->system_calculated_weighted_percentage,
                'evidenceCount' => $update->recordedVersion?->activeEvidenceLinks?->count() ?? 0,
                'acceptedActionPlanVersion' => $update->acceptedActionPlanVersion?->only([
                    'id', 'version_number', 'status_code', 'accepted_at',
                ]),
            ])
            ->values()
            ->all();

        $eligibleUpdateIds = collect($eligibleUpdates)->pluck('id')->all();
        $sourceUpdate = $case->activeValidationReview?->recordedProgressUpdateVersion?->progressUpdate
            ?? $updates->first(fn (CmsProgressUpdate $update): bool => in_array($update->id, $eligibleUpdateIds, true));
        $sourceRecorded = $sourceUpdate?->recordedVersion;
        $validatorSource = $sourceRecorded && $sourceUpdate
            ? [$sourceUpdate, $sourceRecorded]
            : null;

        $validators = collect();
        if ($validatorSource) {
            [$sourceUpdate, $sourceRecorded] = $validatorSource;
            $validators = User::withTrashed()
                ->with(['role.permissions', 'roles.permissions'])
                ->get()
                ->filter(function (User $target) use ($case, $sourceUpdate, $sourceRecorded): bool {
                    try {
                        $this->assertValidatorEligible($case, $sourceUpdate, $sourceRecorded, $target);
                    } catch (ValidationException) {
                        return false;
                    }

                    return true;
                })
                ->map(fn (User $target): array => [
                    'id' => $target->id,
                    'employeeId' => $target->employee_id,
                    'name' => $target->name,
                    'initials' => $target->initials,
                ])
                ->values();
        }

        $reasons = [];
        if (! in_array($case->status_code, [
            CmsRecommendationCase::STATUS_MONITORING,
            CmsRecommendationCase::STATUS_PARTIALLY_IMPLEMENTED,
            CmsRecommendationCase::STATUS_FOR_VALIDATION,
        ], true)) {
            $reasons[] = 'Validation creation is not available from the current recommendation status.';
        }
        if ($case->activeValidationReview) {
            $reasons[] = 'An active Validation Review already exists for this recommendation.';
        }
        if ($eligibleUpdates === []) {
            $reasons[] = 'No latest current recorded Progress Update Version is eligible for validation.';
        }
        if ($validators->isEmpty()) {
            $reasons[] = 'No active, unlocked, independently eligible professional validator is available.';
        }

        return [
            'case' => $case,
            'eligibleRecordedProgressUpdates' => $eligibleUpdates,
            'eligibleValidators' => $validators->all(),
            'unavailableReasons' => array_values(array_unique($reasons)),
        ];
    }

    /** @return array{case: CmsRecommendationCase, review: CmsValidationReview} */
    public function show(User $actor, int $reviewId): array
    {
        [$case, $review] = $this->resolveReview($actor, $reviewId);
        $case->load($this->caseRelations());
        $review->load($this->reviewRelations());
        $this->filterReadOnlyVersions($actor, collect([$review]));

        return ['case' => $case, 'review' => $review];
    }

    /** @param array<string, mixed> $attributes */
    public function create(
        Request $request,
        int $caseId,
        array $attributes,
    ): CmsValidationReview {
        $actor = $request->user();
        $this->scope->authorizeValidationAssignmentAuthority($actor);
        $review = DB::transaction(function () use ($request, $actor, $caseId, $attributes) {
            $case = $this->scope->resolveVisibleCase(
                $actor,
                $caseId,
                'cms.validation.view',
                true,
            );
            $this->assertCaseLock($case, (int) $attributes['lockVersion']);
            $this->assertValidationStartStatus($case);
            if ($case->activeValidationReview()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'recommendation' => ['This recommendation already has an active Validation Review.'],
                ]);
            }

            $recorded = CmsProgressUpdateVersion::query()
                ->with([
                    'progressUpdate.acceptedActionPlanVersion.milestones',
                    'milestoneProgress',
                    'activeEvidenceLinks',
                ])
                ->lockForUpdate()
                ->findOrFail((int) $attributes['recordedProgressUpdateVersionId']);
            $update = CmsProgressUpdate::query()->lockForUpdate()->findOrFail(
                $recorded->cms_progress_update_id,
            );
            $this->assertRecordedVersionEligible($case, $update, $recorded);
            if (CmsValidationReview::query()
                ->where('recorded_progress_update_version_id', $recorded->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'recordedProgressUpdateVersionId' => [
                        'This recorded Progress Update Version already has a Validation Review.',
                    ],
                ]);
            }
            $target = User::withTrashed()
                ->with(['role.permissions', 'roles.permissions'])
                ->lockForUpdate()
                ->find((int) $attributes['validatorUserId']);
            $this->assertValidatorEligible($case, $update, $recorded, $target);

            $sequence = ((int) CmsValidationReview::query()
                ->where('cms_recommendation_case_id', $case->id)
                ->lockForUpdate()
                ->max('validation_sequence')) + 1;
            $review = CmsValidationReview::query()->create([
                'cms_recommendation_case_id' => $case->id,
                'cms_corrective_action_plan_id' => $update->cms_corrective_action_plan_id,
                'accepted_action_plan_version_id' => $update->accepted_action_plan_version_id,
                'cms_progress_update_id' => $update->id,
                'recorded_progress_update_version_id' => $recorded->id,
                'validation_sequence' => $sequence,
                'created_by' => $actor->id,
                'active_slot' => 'ACTIVE',
                'lock_version' => 1,
            ]);
            $version = CmsValidationVersion::query()->create([
                'cms_validation_review_id' => $review->id,
                'version_number' => 1,
                'status_code' => CmsValidationVersion::STATUS_DRAFT,
                'active_slot' => 'ACTIVE',
                'validator_user_id' => $target->id,
                'prepared_by' => $target->id,
                'lock_version' => 1,
            ]);
            $review->forceFill(['current_version_id' => $version->id])->save();
            $this->initializeItems($review, $version, $recorded);
            $this->initializeManagementAssessments($version, $recorded);
            $assignment = $this->createAssignment(
                $review,
                $target,
                $actor,
                $attributes['assignmentReason'],
            );

            $oldCaseStatus = $case->status_code;
            $case->forceFill([
                'status_code' => CmsRecommendationCase::STATUS_FOR_VALIDATION,
                'lock_version' => $case->lock_version + 1,
            ])->save();
            $review->forceFill(['lock_version' => $review->lock_version + 1])->save();
            $this->record(
                $request,
                $case,
                $review,
                $version,
                CmsRecommendationEvent::EVENT_VALIDATION_REVIEW_CREATED,
                'cms.validation.created',
                null,
                CmsValidationVersion::STATUS_DRAFT,
                $oldCaseStatus,
                $case->status_code,
                ['validatorAssignmentId' => $assignment->id],
            );
            $this->record(
                $request,
                $case,
                $review,
                $version,
                CmsRecommendationEvent::EVENT_VALIDATOR_ASSIGNED,
                'cms.validation.validator_assigned',
                CmsValidationVersion::STATUS_DRAFT,
                CmsValidationVersion::STATUS_DRAFT,
                $case->status_code,
                $case->status_code,
                [
                    'validatorAssignmentId' => $assignment->id,
                    'validatorUserId' => $target->id,
                    'assignmentReason' => $attributes['assignmentReason'],
                ],
            );
            DB::afterCommit(fn () => $this->notify(
                'assigned',
                $actor->id,
                $case->id,
                $review->id,
                $version->id,
                $target->id,
            ));

            return $review;
        }, 3);

        return $review->fresh($this->reviewRelations());
    }

    /** @param array<string, mixed> $attributes */
    public function update(
        Request $request,
        int $reviewId,
        int $versionId,
        array $attributes,
    ): CmsValidationReview {
        $actor = $request->user();
        $review = DB::transaction(function () use (
            $request,
            $actor,
            $reviewId,
            $versionId,
            $attributes,
        ) {
            [$case, $review] = $this->resolveReview($actor, $reviewId, true);
            $version = $this->resolveVersion($review, $versionId, true);
            $this->authorizeAssignedValidator($actor, $case, $review, 'cms.validation.update');
            $this->assertVersionLock($version, (int) $attributes['lockVersion']);
            $this->assertCurrentDraft($review, $version);
            $this->assertPinnedSources($case, $review);

            $version->fill($this->draftAttributes($attributes));
            if (array_key_exists('validationItems', $attributes)) {
                $this->syncItems($review, $version, $attributes['validationItems']);
            }
            if (array_key_exists('evidenceAssessments', $attributes)) {
                $this->syncAssessments(
                    $actor,
                    $review,
                    $version,
                    $attributes['evidenceAssessments'],
                );
            }
            $version->forceFill([
                'validator_user_id' => $actor->id,
                'lock_version' => $version->lock_version + 1,
            ])->save();
            $review->forceFill(['lock_version' => $review->lock_version + 1])->save();
            $this->record(
                $request,
                $case,
                $review,
                $version,
                CmsRecommendationEvent::EVENT_VALIDATION_DRAFT_UPDATED,
                'cms.validation.updated',
                CmsValidationVersion::STATUS_DRAFT,
                CmsValidationVersion::STATUS_DRAFT,
                $case->status_code,
                $case->status_code,
            );

            return $review;
        }, 3);

        return $review->fresh($this->reviewRelations());
    }

    public function submit(
        Request $request,
        int $reviewId,
        int $versionId,
        int $lockVersion,
    ): CmsValidationReview {
        $actor = $request->user();
        $review = DB::transaction(function () use (
            $request,
            $actor,
            $reviewId,
            $versionId,
            $lockVersion,
        ) {
            [$case, $review] = $this->resolveReview($actor, $reviewId, true);
            $version = $this->resolveVersion($review, $versionId, true);
            $this->authorizeAssignedValidator($actor, $case, $review, 'cms.validation.submit');
            $this->assertVersionLock($version, $lockVersion);
            $this->assertCurrentDraft($review, $version);
            $this->assertPinnedSources($case, $review);
            $this->revalidateCurrentValidator($case, $review);
            $this->assertComplete($review, $version);
            $this->assertConclusionConsistency(
                $review,
                $version,
                (string) $version->proposed_conclusion_code,
            );

            $submittedAt = now();
            $version->forceFill([
                'validator_user_id' => $actor->id,
                'submitted_by' => $actor->id,
                'submitted_at' => $submittedAt,
            ]);
            $version->forceFill([
                'submission_snapshot' => $this->snapshotFor($review, $version),
                'status_code' => CmsValidationVersion::STATUS_SUBMITTED,
                'lock_version' => $version->lock_version + 1,
            ])->save();
            $review->forceFill(['lock_version' => $review->lock_version + 1])->save();
            $this->record(
                $request,
                $case,
                $review,
                $version,
                CmsRecommendationEvent::EVENT_VALIDATION_SUBMITTED,
                'cms.validation.submitted',
                CmsValidationVersion::STATUS_DRAFT,
                CmsValidationVersion::STATUS_SUBMITTED,
                $case->status_code,
                $case->status_code,
            );
            DB::afterCommit(fn () => $this->notify(
                'submitted',
                $actor->id,
                $case->id,
                $review->id,
                $version->id,
                $actor->id,
            ));

            return $review;
        }, 3);

        return $review->fresh($this->reviewRelations());
    }

    public function startReview(
        Request $request,
        int $reviewId,
        int $versionId,
        int $lockVersion,
        ?string $comment,
    ): CmsValidationReview {
        return $this->supervisoryTransition(
            $request,
            $reviewId,
            $versionId,
            $lockVersion,
            CmsValidationVersion::STATUS_SUBMITTED,
            CmsValidationVersion::STATUS_UNDER_REVIEW,
            'cms.validation.review',
            CmsRecommendationEvent::EVENT_VALIDATION_SUPERVISORY_REVIEW_STARTED,
            'cms.validation.review_started',
            [
                'supervisory_review_started_by' => $request->user()->id,
                'supervisory_review_started_at' => now(),
                'supervisory_review_comment' => $comment,
            ],
            'review_started',
        );
    }

    public function return(
        Request $request,
        int $reviewId,
        int $versionId,
        int $lockVersion,
        string $reason,
    ): CmsValidationReview {
        return $this->supervisoryTransition(
            $request,
            $reviewId,
            $versionId,
            $lockVersion,
            CmsValidationVersion::STATUS_UNDER_REVIEW,
            CmsValidationVersion::STATUS_RETURNED,
            'cms.validation.return',
            CmsRecommendationEvent::EVENT_VALIDATION_RETURNED,
            'cms.validation.returned',
            [
                'returned_by' => $request->user()->id,
                'returned_at' => now(),
                'return_reason' => $reason,
            ],
            'returned',
        );
    }

    /** @param array<string, mixed> $attributes */
    public function finalize(
        Request $request,
        int $reviewId,
        int $versionId,
        array $attributes,
    ): CmsValidationReview {
        $actor = $request->user();
        $review = DB::transaction(function () use (
            $request,
            $actor,
            $reviewId,
            $versionId,
            $attributes,
        ) {
            [$case, $review] = $this->resolveReview($actor, $reviewId, true);
            $version = $this->resolveVersion($review, $versionId, true);
            $this->assertVersionLock($version, (int) $attributes['lockVersion']);
            $this->assertStatus($version, CmsValidationVersion::STATUS_UNDER_REVIEW);
            $this->authorizeSupervisor($actor, $case, $review, $version, 'cms.validation.finalize');
            $this->assertPinnedSources($case, $review);
            $this->assertSnapshotIntegrity($review, $version);
            $this->assertComplete($review, $version);
            $finalConclusion = (string) $attributes['finalConclusionCode'];
            $this->assertConclusionConsistency($review, $version, $finalConclusion);
            if ($finalConclusion !== $version->proposed_conclusion_code
                && blank($attributes['overrideReason'] ?? null)) {
                throw ValidationException::withMessages([
                    'overrideReason' => [
                        'A supervisory override reason is required when changing the proposed conclusion.',
                    ],
                ]);
            }

            $oldCaseStatus = $case->status_code;
            $newCaseStatus = match ($finalConclusion) {
                'PARTIALLY_IMPLEMENTED' => CmsRecommendationCase::STATUS_PARTIALLY_IMPLEMENTED,
                'IMPLEMENTED' => CmsRecommendationCase::STATUS_IMPLEMENTED,
                default => CmsRecommendationCase::STATUS_MONITORING,
            };
            $version->forceFill([
                'status_code' => CmsValidationVersion::STATUS_FINALIZED,
                'active_slot' => null,
                'final_conclusion_code' => $finalConclusion,
                'finalized_by' => $actor->id,
                'finalized_at' => now(),
                'finalization_comment' => $attributes['finalizationComment'],
                'supervisory_override_reason' => $attributes['overrideReason'] ?? null,
                'lock_version' => $version->lock_version + 1,
            ])->save();
            $review->forceFill([
                'current_version_id' => $version->id,
                'finalized_version_id' => $version->id,
                'active_slot' => null,
                'lock_version' => $review->lock_version + 1,
            ])->save();
            $case->forceFill([
                'status_code' => $newCaseStatus,
                'lock_version' => $case->lock_version + 1,
            ])->save();
            $this->record(
                $request,
                $case,
                $review,
                $version,
                CmsRecommendationEvent::EVENT_VALIDATION_FINALIZED,
                'cms.validation.finalized',
                CmsValidationVersion::STATUS_UNDER_REVIEW,
                CmsValidationVersion::STATUS_FINALIZED,
                $oldCaseStatus,
                $newCaseStatus,
                [
                    'finalConclusionCode' => $finalConclusion,
                    'overrideReason' => $attributes['overrideReason'] ?? null,
                ],
            );
            DB::afterCommit(fn () => $this->notify(
                'finalized',
                $actor->id,
                $case->id,
                $review->id,
                $version->id,
                $review->currentAssignment()->value('user_id'),
                $finalConclusion,
            ));

            return $review;
        }, 3);

        return $review->fresh($this->reviewRelations());
    }

    public function revise(
        Request $request,
        int $reviewId,
        int $versionId,
        int $lockVersion,
        string $reason,
    ): CmsValidationReview {
        $actor = $request->user();
        $review = DB::transaction(function () use (
            $request,
            $actor,
            $reviewId,
            $versionId,
            $lockVersion,
            $reason,
        ) {
            [$case, $review] = $this->resolveReview($actor, $reviewId, true);
            $source = $this->resolveVersion($review, $versionId, true);
            $this->authorizeAssignedValidator($actor, $case, $review, 'cms.validation.revise');
            $this->assertVersionLock($source, $lockVersion);
            $this->assertStatus($source, CmsValidationVersion::STATUS_RETURNED);
            if ((int) $review->current_version_id !== $source->id
                || $review->finalized_version_id !== null
                || $review->active_slot !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    'version' => ['Only the current returned Validation Version may be revised.'],
                ]);
            }
            if ($review->versions()->whereIn(
                'status_code',
                CmsValidationVersion::ACTIVE_STATUSES,
            )->exists()) {
                throw ValidationException::withMessages([
                    'version' => ['This Validation Review already has an active revision.'],
                ]);
            }
            $this->assertPinnedSources($case, $review);
            $source->load([
                'items',
                'evidenceAssessments',
                'activeEvidenceLinks',
            ]);
            $nextNumber = ((int) $review->versions()->lockForUpdate()->max('version_number')) + 1;
            $version = CmsValidationVersion::query()->create([
                'cms_validation_review_id' => $review->id,
                'version_number' => $nextNumber,
                'previous_version_id' => $source->id,
                'status_code' => CmsValidationVersion::STATUS_DRAFT,
                'active_slot' => 'ACTIVE',
                ...$this->copyContent($source),
                'validator_user_id' => $actor->id,
                'prepared_by' => $actor->id,
                'revision_reason' => $reason,
                'lock_version' => 1,
            ]);
            $itemMap = [];
            foreach ($source->items as $item) {
                $copy = $version->items()->create(
                    collect($item->getAttributes())
                        ->except(['id', 'cms_validation_version_id', 'created_at', 'updated_at'])
                        ->all(),
                );
                $itemMap[$item->id] = $copy->id;
            }
            $evidenceMap = [];
            foreach ($source->activeEvidenceLinks as $evidence) {
                $copy = $version->evidenceLinks()->create([
                    ...collect($evidence->getAttributes())
                        ->except([
                            'id',
                            'cms_validation_version_id',
                            'cms_validation_item_id',
                            'removed_by',
                            'removed_at',
                            'removal_reason',
                            'created_at',
                            'updated_at',
                        ])->all(),
                    'cms_validation_item_id' => $evidence->cms_validation_item_id
                        ? $itemMap[$evidence->cms_validation_item_id]
                        : null,
                ]);
                $evidenceMap[$evidence->id] = $copy->id;
            }
            foreach ($source->evidenceAssessments as $assessment) {
                $version->evidenceAssessments()->create([
                    ...collect($assessment->getAttributes())
                        ->except([
                            'id',
                            'cms_validation_version_id',
                            'cms_validation_item_id',
                            'cms_validation_evidence_link_id',
                            'created_at',
                            'updated_at',
                        ])->all(),
                    'cms_validation_item_id' => $assessment->cms_validation_item_id
                        ? $itemMap[$assessment->cms_validation_item_id]
                        : null,
                    'cms_validation_evidence_link_id' => $assessment
                        ->cms_validation_evidence_link_id
                        ? $evidenceMap[$assessment->cms_validation_evidence_link_id]
                        : null,
                ]);
            }
            $review->forceFill([
                'current_version_id' => $version->id,
                'lock_version' => $review->lock_version + 1,
            ])->save();
            $this->record(
                $request,
                $case,
                $review,
                $version,
                CmsRecommendationEvent::EVENT_VALIDATION_REVISION_CREATED,
                'cms.validation.revision_created',
                CmsValidationVersion::STATUS_RETURNED,
                CmsValidationVersion::STATUS_DRAFT,
                $case->status_code,
                $case->status_code,
                ['revisionReason' => $reason, 'previousVersionId' => $source->id],
            );
            DB::afterCommit(fn () => $this->notify(
                'revision_created',
                $actor->id,
                $case->id,
                $review->id,
                $version->id,
                $actor->id,
            ));

            return $review;
        }, 3);

        return $review->fresh($this->reviewRelations());
    }

    /** @param array<string, mixed> $attributes */
    public function assign(
        Request $request,
        int $reviewId,
        array $attributes,
    ): CmsValidationReview {
        $actor = $request->user();
        $this->scope->authorizeValidationAssignmentAuthority($actor);
        $review = DB::transaction(function () use ($request, $actor, $reviewId, $attributes) {
            [$case, $review] = $this->resolveReview($actor, $reviewId, true);
            $this->assertReviewLock($review, (int) $attributes['lockVersion']);
            if ($review->active_slot !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    'validation' => ['A finalized Validation Review cannot be reassigned.'],
                ]);
            }
            $current = $review->currentAssignment()->lockForUpdate()->first();
            if (! $current) {
                throw ValidationException::withMessages([
                    'assignment' => ['The active Validation Review has no current Primary Validator.'],
                ]);
            }
            $target = User::withTrashed()
                ->with(['role.permissions', 'roles.permissions'])
                ->lockForUpdate()
                ->find((int) $attributes['validatorUserId']);
            $recorded = $review->recordedProgressUpdateVersion()->with('progressUpdate')->firstOrFail();
            $this->assertValidatorEligible($case, $recorded->progressUpdate, $recorded, $target);
            if ((int) $current->user_id === (int) $target->id) {
                throw ValidationException::withMessages([
                    'validatorUserId' => ['This user is already the current Primary Validator.'],
                ]);
            }

            $now = now();
            $current->forceFill([
                'effective_until' => $now,
                'ended_by' => $actor->id,
                'ended_at' => $now,
                'end_reason' => $attributes['assignmentReason'],
                'is_current' => false,
                'current_slot' => null,
            ])->save();
            $assignment = $this->createAssignment(
                $review,
                $target,
                $actor,
                $attributes['assignmentReason'],
            );
            $review->forceFill(['lock_version' => $review->lock_version + 1])->save();
            $version = $review->currentVersion()->firstOrFail();
            $this->record(
                $request,
                $case,
                $review,
                $version,
                CmsRecommendationEvent::EVENT_VALIDATOR_REPLACED,
                'cms.validation.validator_replaced',
                $version->status_code,
                $version->status_code,
                $case->status_code,
                $case->status_code,
                [
                    'previousAssignmentId' => $current->id,
                    'previousValidatorUserId' => $current->user_id,
                    'validatorAssignmentId' => $assignment->id,
                    'validatorUserId' => $target->id,
                    'assignmentReason' => $attributes['assignmentReason'],
                ],
            );
            DB::afterCommit(function () use (
                $actor,
                $case,
                $review,
                $version,
                $current,
                $target,
            ): void {
                $this->notify(
                    'replaced',
                    $actor->id,
                    $case->id,
                    $review->id,
                    $version->id,
                    $target->id,
                    null,
                    $current->user_id,
                );
            });

            return $review;
        }, 3);

        return $review->fresh($this->reviewRelations());
    }

    public function endAssignment(
        Request $request,
        int $reviewId,
        int $assignmentId,
        int $lockVersion,
        string $reason,
    ): CmsValidationReview {
        $actor = $request->user();
        $this->scope->authorizeValidationAssignmentAuthority($actor);
        $review = DB::transaction(function () use (
            $request,
            $actor,
            $reviewId,
            $assignmentId,
            $lockVersion,
            $reason,
        ) {
            [$case, $review] = $this->resolveReview($actor, $reviewId, true);
            $this->assertReviewLock($review, $lockVersion);
            if ($review->active_slot === 'ACTIVE') {
                throw ValidationException::withMessages([
                    'assignment' => [
                        'An active Validation Review must retain a Primary Validator; replace the assignment instead.',
                    ],
                ]);
            }
            $assignment = $review->assignments()
                ->whereKey($assignmentId)
                ->where('is_current', true)
                ->lockForUpdate()
                ->first();
            throw_unless(
                $assignment,
                new HttpException(404, 'The validator assignment is unavailable.'),
            );
            $now = now();
            $assignment->forceFill([
                'effective_until' => $now,
                'ended_by' => $actor->id,
                'ended_at' => $now,
                'end_reason' => $reason,
                'is_current' => false,
                'current_slot' => null,
            ])->save();
            $review->forceFill(['lock_version' => $review->lock_version + 1])->save();
            $version = $review->currentVersion()->firstOrFail();
            $this->record(
                $request,
                $case,
                $review,
                $version,
                CmsRecommendationEvent::EVENT_VALIDATOR_ASSIGNMENT_ENDED,
                'cms.validation.validator_assignment_ended',
                $version->status_code,
                $version->status_code,
                $case->status_code,
                $case->status_code,
                ['assignmentId' => $assignment->id, 'endReason' => $reason],
            );

            return $review;
        }, 3);

        return $review->fresh($this->reviewRelations());
    }

    /** @return list<string> */
    public function permittedActions(
        User $actor,
        CmsValidationReview $review,
        ?CmsValidationVersion $version,
    ): array {
        if (! $version) {
            return [];
        }
        $review->loadMissing('case.recommendation', 'currentAssignment');
        $case = $review->case;
        $actions = [];
        if ($version->status_code === CmsValidationVersion::STATUS_DRAFT
            && $this->canAssignedValidator($actor, $case, $review, 'cms.validation.update')) {
            $actions[] = 'update';
            if ($actor->hasPermission('cms.validation.submit')) {
                $actions[] = 'submit';
            }
            if ($actor->hasPermission('cms.validation-evidence.upload')) {
                $actions[] = 'upload-evidence';
            }
        }
        if ($version->status_code === CmsValidationVersion::STATUS_RETURNED
            && (int) $review->current_version_id === $version->id
            && $this->canAssignedValidator($actor, $case, $review, 'cms.validation.revise')) {
            $actions[] = 'revise';
        }
        if ($version->status_code === CmsValidationVersion::STATUS_SUBMITTED
            && $this->canSupervisor($actor, $case, $review, $version, 'cms.validation.review')) {
            $actions[] = 'start-review';
        }
        if ($version->status_code === CmsValidationVersion::STATUS_UNDER_REVIEW) {
            if ($this->canSupervisor($actor, $case, $review, $version, 'cms.validation.return')) {
                $actions[] = 'return';
            }
            if ($this->canSupervisor($actor, $case, $review, $version, 'cms.validation.finalize')) {
                $actions[] = 'finalize';
            }
        }
        if ($review->active_slot === 'ACTIVE'
            && $actor->hasPermission('cms.validation.assign')) {
            $actions[] = 'replace-validator';
        }

        return $actions;
    }

    /** @return array{complete: bool, errors: array<string, list<string>>} */
    public function completeness(
        CmsValidationReview $review,
        CmsValidationVersion $version,
    ): array {
        $review->loadMissing([
            'acceptedActionPlanVersion.milestones',
            'recordedProgressUpdateVersion.activeEvidenceLinks',
        ]);
        $version->loadMissing([
            'items',
            'evidenceAssessments',
            'activeEvidenceLinks',
        ]);
        $errors = [];
        foreach ([
            'validation_scope' => 'validationScope',
            'validation_objectives' => 'validationObjectives',
            'methodology_summary' => 'methodologySummary',
            'overall_work_performed' => 'overallWorkPerformed',
            'overall_evidence_summary' => 'overallEvidenceSummary',
            'professional_judgment_rationale' => 'professionalJudgmentRationale',
            'proposed_conclusion_code' => 'proposedConclusionCode',
        ] as $column => $field) {
            if (blank($version->{$column})) {
                $errors[$field][] = 'This professional validation field is required before submission.';
            }
        }
        $expected = $review->acceptedActionPlanVersion->milestones;
        $milestoneItems = $version->items->where('scope_code', 'MILESTONE');
        if ($milestoneItems->count() !== $expected->count()
            || $milestoneItems->pluck('cms_action_plan_milestone_id')->sort()->values()->all()
                !== $expected->pluck('id')->sort()->values()->all()) {
            $errors['validationItems'][] =
                'Complete exactly one Validation Item for every accepted Action Plan milestone.';
        }
        foreach ($milestoneItems->values() as $index => $item) {
            foreach ([
                'criterion' => 'criterion',
                'procedure_performed' => 'procedurePerformed',
                'population_or_source' => 'populationOrSource',
                'result_summary' => 'resultSummary',
                'item_conclusion_code' => 'itemConclusionCode',
            ] as $column => $field) {
                if (blank($item->{$column})) {
                    $errors["validationItems.{$index}.{$field}"][] =
                        'Complete this required Validation Item field.';
                }
            }
        }
        $managementEvidenceIds = $review->recordedProgressUpdateVersion
            ->activeEvidenceLinks->pluck('id')->sort()->values();
        $managementAssessments = $version->evidenceAssessments
            ->where('evidence_source_code', 'MANAGEMENT_SUBMITTED');
        if ($managementAssessments->pluck('cms_progress_evidence_link_id')->sort()->values()->all()
            !== $managementEvidenceIds->all()) {
            $errors['evidenceAssessments'][] =
                'Assess every management evidence link from the exact recorded submission.';
        }
        $validatorEvidenceIds = $version->activeEvidenceLinks->pluck('id')->sort()->values();
        $validatorAssessmentIds = $version->evidenceAssessments
            ->where('evidence_source_code', 'VALIDATOR_OBTAINED')
            ->pluck('cms_validation_evidence_link_id')->sort()->values();
        if ($validatorAssessmentIds->all() !== $validatorEvidenceIds->all()) {
            $errors['evidenceAssessments'][] =
                'Assess every active validator-obtained evidence link.';
        }
        foreach ($version->evidenceAssessments->values() as $index => $assessment) {
            if ($assessment->relied_upon
                && in_array('NOT_ASSESSED', [
                    $assessment->relevance_code,
                    $assessment->reliability_code,
                    $assessment->sufficiency_code,
                ], true)) {
                $errors["evidenceAssessments.{$index}"][] =
                    'Relied-upon evidence requires relevance, reliability, and sufficiency assessment.';
            }
            if (blank($assessment->assessment_summary)) {
                $errors["evidenceAssessments.{$index}.assessmentSummary"][] =
                    $assessment->relied_upon
                        ? 'Explain the professional assessment of relied-upon evidence.'
                        : 'Explain why this evidence was not relied upon.';
            }
        }

        return ['complete' => $errors === [], 'errors' => $errors];
    }

    /**
     * Safe aggregate resolver shared with validator evidence.
     *
     * @return array{0: CmsRecommendationCase, 1: CmsValidationReview, 2: CmsValidationVersion}
     */
    public function resolveVersionForActor(
        User $actor,
        int $reviewId,
        int $versionId,
        bool $lock = false,
    ): array {
        [$case, $review] = $this->resolveReview($actor, $reviewId, $lock);

        return [$case, $review, $this->resolveVersion($review, $versionId, $lock)];
    }

    public function authorizeAssignedValidatorAction(
        User $actor,
        CmsRecommendationCase $case,
        CmsValidationReview $review,
        string $permission,
    ): void {
        $this->authorizeAssignedValidator($actor, $case, $review, $permission);
    }

    public function assertDraftEvidenceMutation(
        CmsValidationReview $review,
        CmsValidationVersion $version,
        int $lockVersion,
    ): void {
        $this->assertVersionLock($version, $lockVersion);
        $this->assertCurrentDraft($review, $version);
    }

    public function recordEvidence(
        Request $request,
        CmsRecommendationCase $case,
        CmsValidationReview $review,
        CmsValidationVersion $version,
        CmsValidationEvidenceLink $evidence,
        string $eventCode,
        string $action,
    ): void {
        $this->record(
            $request,
            $case,
            $review,
            $version,
            $eventCode,
            $action,
            $version->status_code,
            $version->status_code,
            $case->status_code,
            $case->status_code,
            [
                'validationEvidenceLinkId' => $evidence->id,
                'documentId' => $evidence->document_id,
                'documentVersionId' => $evidence->document_version_id,
                'checksumSha256' => $evidence->checksum_sha256,
                'validationItemId' => $evidence->cms_validation_item_id,
            ],
        );
    }

    private function supervisoryTransition(
        Request $request,
        int $reviewId,
        int $versionId,
        int $lockVersion,
        string $from,
        string $to,
        string $permission,
        string $eventCode,
        string $auditAction,
        array $attributes,
        string $notification,
    ): CmsValidationReview {
        $actor = $request->user();
        $review = DB::transaction(function () use (
            $request,
            $actor,
            $reviewId,
            $versionId,
            $lockVersion,
            $from,
            $to,
            $permission,
            $eventCode,
            $auditAction,
            $attributes,
            $notification,
        ) {
            [$case, $review] = $this->resolveReview($actor, $reviewId, true);
            $version = $this->resolveVersion($review, $versionId, true);
            $this->assertVersionLock($version, $lockVersion);
            $this->assertStatus($version, $from);
            $this->authorizeSupervisor($actor, $case, $review, $version, $permission);
            $this->assertPinnedSources($case, $review);
            $this->assertSnapshotIntegrity($review, $version);
            $version->forceFill([
                ...$attributes,
                'status_code' => $to,
                'active_slot' => in_array($to, CmsValidationVersion::ACTIVE_STATUSES, true)
                    ? 'ACTIVE'
                    : null,
                'lock_version' => $version->lock_version + 1,
            ])->save();
            $review->forceFill(['lock_version' => $review->lock_version + 1])->save();
            $this->record(
                $request,
                $case,
                $review,
                $version,
                $eventCode,
                $auditAction,
                $from,
                $to,
                $case->status_code,
                $case->status_code,
            );
            $recipient = $review->currentAssignment()->value('user_id');
            DB::afterCommit(fn () => $this->notify(
                $notification,
                $actor->id,
                $case->id,
                $review->id,
                $version->id,
                $recipient,
            ));

            return $review;
        }, 3);

        return $review->fresh($this->reviewRelations());
    }

    /** @return array{0: CmsRecommendationCase, 1: CmsValidationReview} */
    private function resolveReview(User $actor, int $reviewId, bool $lock = false): array
    {
        $reference = CmsValidationReview::query()->find($reviewId);
        throw_unless(
            $reference,
            new HttpException(404, 'The Validation Review is unavailable.'),
        );
        $case = $this->scope->resolveVisibleCase(
            $actor,
            $reference->cms_recommendation_case_id,
            'cms.validation.view',
            $lock,
        );
        $query = CmsValidationReview::query()
            ->whereKey($reviewId)
            ->where('cms_recommendation_case_id', $case->id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $review = $query->first();
        throw_unless(
            $review,
            new HttpException(404, 'The Validation Review is unavailable.'),
        );

        return [$case, $review];
    }

    private function resolveVersion(
        CmsValidationReview $review,
        int $versionId,
        bool $lock = false,
    ): CmsValidationVersion {
        $query = $review->versions()->whereKey($versionId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $version = $query->first();
        throw_unless(
            $version,
            new HttpException(404, 'The Validation Version is unavailable.'),
        );

        return $version;
    }

    private function canCreate(User $actor, CmsRecommendationCase $case): bool
    {
        if (! $this->scope->isUsableAccount($actor)
            || ! $actor->hasPermission('cms.validation.create')
            || ! $actor->hasPermission('cms.validation.assign')
            || ! in_array($case->status_code, [
                CmsRecommendationCase::STATUS_MONITORING,
                CmsRecommendationCase::STATUS_PARTIALLY_IMPLEMENTED,
            ], true)
            || $case->activeValidationReview) {
            return false;
        }

        return $case->progressUpdates->contains(
            fn (CmsProgressUpdate $update): bool => $update->recorded_version_id !== null
                && ! CmsValidationReview::query()
                    ->where('recorded_progress_update_version_id', $update->recorded_version_id)
                    ->exists(),
        );
    }

    private function authorizeAssignedValidator(
        User $actor,
        CmsRecommendationCase $case,
        CmsValidationReview $review,
        string $permission,
    ): void {
        throw_unless(
            $this->canAssignedValidator($actor, $case, $review, $permission),
            new HttpException(403, 'The assigned independent Primary Validator is required.'),
        );
    }

    private function canAssignedValidator(
        User $actor,
        CmsRecommendationCase $case,
        CmsValidationReview $review,
        string $permission,
    ): bool {
        return $this->scope->isUsableAccount($actor)
            && $actor->hasPermission($permission)
            && $review->active_slot === 'ACTIVE'
            && $case->status_code === CmsRecommendationCase::STATUS_FOR_VALIDATION
            && $review->currentAssignment()
                ->where('user_id', $actor->id)
                ->where('is_current', true)
                ->where('effective_from', '<=', now())
                ->where(function ($query): void {
                    $query->whereNull('effective_until')->orWhere('effective_until', '>', now());
                })
                ->exists();
    }

    private function authorizeSupervisor(
        User $actor,
        CmsRecommendationCase $case,
        CmsValidationReview $review,
        CmsValidationVersion $version,
        string $permission,
    ): void {
        throw_unless(
            $this->canSupervisor($actor, $case, $review, $version, $permission),
            new HttpException(403, 'An independent authorized supervisory reviewer is required.'),
        );
    }

    private function canSupervisor(
        User $actor,
        CmsRecommendationCase $case,
        CmsValidationReview $review,
        CmsValidationVersion $version,
        string $permission,
    ): bool {
        if (! $this->scope->isUsableAccount($actor)
            || ! $actor->hasPermission($permission)
            || (int) $actor->office_id === (int) $case->lead_responsible_office_id
            || in_array($actor->id, array_filter([
                $review->currentAssignment()->value('user_id'),
                $version->validator_user_id,
                $version->prepared_by,
                $version->submitted_by,
            ]), true)
            || ! $this->scope->canViewClassification(
                $actor,
                $case->recommendation?->confidentiality_code_snapshot,
            )) {
            return false;
        }

        return ! $this->sourceParticipantIds($review)->contains($actor->id);
    }

    private function assertValidatorEligible(
        CmsRecommendationCase $case,
        CmsProgressUpdate $update,
        CmsProgressUpdateVersion $recorded,
        ?User $target,
    ): void {
        if (! $target
            || ! $this->scope->isUsableAccount($target)
            || ! $target->hasPermission('cms.validation.view')
            || ! $target->hasPermission('cms.validation.update')
            || ! $target->hasPermission('cms.validation.submit')
            || (int) $target->office_id === (int) $case->lead_responsible_office_id
            || ! $this->scope->canViewClassification(
                $target,
                $case->recommendation?->confidentiality_code_snapshot,
            )) {
            throw ValidationException::withMessages([
                'validatorUserId' => [
                    'Select an active, unlocked, independently eligible professional validator.',
                ],
            ]);
        }
        $plan = $update->acceptedActionPlanVersion()->firstOrFail();
        if (in_array($target->id, array_filter([
            $plan->prepared_by,
            $plan->focal_user_id,
            $plan->submitted_by,
            $recorded->prepared_by,
            $recorded->submitted_by,
            $recorded->recorded_by,
        ]), true)
            || $case->currentAssignment()
                ->where('user_id', $target->id)
                ->where('assignment_role_code', 'COMPLIANCE_MONITOR')
                ->where('is_current', true)
                ->exists()) {
            throw ValidationException::withMessages([
                'validatorUserId' => [
                    'The selected user has a prohibited management, recording, or monitoring conflict.',
                ],
            ]);
        }
    }

    private function revalidateCurrentValidator(
        CmsRecommendationCase $case,
        CmsValidationReview $review,
    ): void {
        $assignment = $review->currentAssignment()
            ->with('user.role.permissions', 'user.roles.permissions')
            ->lockForUpdate()
            ->firstOrFail();
        $recorded = $review->recordedProgressUpdateVersion()->with('progressUpdate')->firstOrFail();
        $this->assertValidatorEligible(
            $case,
            $recorded->progressUpdate,
            $recorded,
            $assignment->user,
        );
    }

    private function assertValidationStartStatus(CmsRecommendationCase $case): void
    {
        if (! in_array($case->status_code, [
            CmsRecommendationCase::STATUS_MONITORING,
            CmsRecommendationCase::STATUS_PARTIALLY_IMPLEMENTED,
        ], true)) {
            throw ValidationException::withMessages([
                'recommendation' => [
                    'Validation may begin only from MONITORING or PARTIALLY_IMPLEMENTED.',
                ],
            ]);
        }
    }

    private function assertRecordedVersionEligible(
        CmsRecommendationCase $case,
        CmsProgressUpdate $update,
        CmsProgressUpdateVersion $recorded,
    ): void {
        $latest = CmsProgressUpdate::query()
            ->where('cms_recommendation_case_id', $case->id)
            ->whereNotNull('recorded_version_id')
            ->orderByDesc('reporting_period_end')
            ->orderByDesc('reporting_sequence')
            ->first();
        if ((int) $update->cms_recommendation_case_id !== $case->id
            || $recorded->status_code !== CmsProgressUpdateVersion::STATUS_RECORDED
            || (int) $update->recorded_version_id !== $recorded->id
            || (int) $latest?->recorded_version_id !== $recorded->id
            || (int) $recorded->progressUpdate?->accepted_action_plan_version_id
                !== (int) $update->accepted_action_plan_version_id
            || $update->acceptedActionPlanVersion?->status_code
                !== CmsActionPlanVersion::STATUS_ACCEPTED) {
            throw ValidationException::withMessages([
                'recordedProgressUpdateVersionId' => [
                    'Select the latest eligible current recorded Progress Update Version.',
                ],
            ]);
        }
    }

    private function assertPinnedSources(
        CmsRecommendationCase $case,
        CmsValidationReview $review,
    ): void {
        if ($case->status_code !== CmsRecommendationCase::STATUS_FOR_VALIDATION
            || $review->active_slot !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'validation' => ['This Validation Review is no longer active.'],
            ]);
        }
        $update = CmsProgressUpdate::query()
            ->with('acceptedActionPlanVersion')
            ->lockForUpdate()
            ->findOrFail($review->cms_progress_update_id);
        $recorded = CmsProgressUpdateVersion::query()
            ->lockForUpdate()
            ->findOrFail($review->recorded_progress_update_version_id);
        $accepted = CmsActionPlanVersion::query()
            ->lockForUpdate()
            ->findOrFail($review->accepted_action_plan_version_id);
        if ((int) $update->cms_recommendation_case_id !== $case->id
            || (int) $update->accepted_action_plan_version_id
                !== (int) $review->accepted_action_plan_version_id
            || (int) $update->recorded_version_id !== $recorded->id
            || $recorded->status_code !== CmsProgressUpdateVersion::STATUS_RECORDED
            || $accepted->status_code !== CmsActionPlanVersion::STATUS_ACCEPTED) {
            throw ValidationException::withMessages([
                'validation' => [
                    'The pinned Action Plan or recorded Progress Update baseline is no longer valid.',
                ],
            ]);
        }
    }

    private function initializeItems(
        CmsValidationReview $review,
        CmsValidationVersion $version,
        CmsProgressUpdateVersion $recorded,
    ): void {
        $milestoneProgress = $recorded->milestoneProgress->keyBy('cms_action_plan_milestone_id');
        foreach ($review->acceptedActionPlanVersion->milestones as $index => $milestone) {
            $progress = $milestoneProgress->get($milestone->id);
            $version->items()->create([
                'scope_code' => 'MILESTONE',
                'cms_action_plan_milestone_id' => $milestone->id,
                'cms_milestone_progress_id' => $progress?->id,
                'sequence_number' => $index + 1,
                'criterion' => $milestone->success_indicator
                    ?: $milestone->expected_output
                    ?: $milestone->title,
                'population_or_source' => $milestone->verification_method,
                'display_order' => $index + 1,
            ]);
        }
    }

    private function initializeManagementAssessments(
        CmsValidationVersion $version,
        CmsProgressUpdateVersion $recorded,
    ): void {
        foreach ($recorded->activeEvidenceLinks as $evidence) {
            $version->evidenceAssessments()->create([
                'cms_progress_evidence_link_id' => $evidence->id,
                'evidence_source_code' => 'MANAGEMENT_SUBMITTED',
                'relevance_code' => 'NOT_ASSESSED',
                'reliability_code' => 'NOT_ASSESSED',
                'sufficiency_code' => 'NOT_ASSESSED',
                'relied_upon' => false,
            ]);
        }
    }

    private function createAssignment(
        CmsValidationReview $review,
        User $target,
        User $actor,
        string $reason,
    ): CmsValidationAssignment {
        return $review->assignments()->create([
            'user_id' => $target->id,
            'assignment_role_code' => CmsValidationAssignment::ROLE_PRIMARY_VALIDATOR,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
            'assignment_reason' => $reason,
            'effective_from' => now(),
            'is_current' => true,
            'current_slot' => 'CURRENT',
        ]);
    }

    /** @param list<array<string, mixed>> $payload */
    private function syncItems(
        CmsValidationReview $review,
        CmsValidationVersion $version,
        array $payload,
    ): void {
        $acceptedIds = $review->acceptedActionPlanVersion()
            ->firstOrFail()->milestones()->pluck('id');
        $progressById = $review->recordedProgressUpdateVersion()
            ->firstOrFail()->milestoneProgress()->get()
            ->keyBy('id');
        $seenMilestones = collect();
        $kept = collect();
        foreach ($payload as $index => $entry) {
            $scopeCode = strtoupper((string) $entry['scopeCode']);
            $milestoneId = $entry['actionPlanMilestoneId'] ?? null;
            $progressId = $entry['milestoneProgressId'] ?? null;
            if ($scopeCode === 'MILESTONE') {
                if (! $milestoneId || ! $acceptedIds->contains((int) $milestoneId)) {
                    throw ValidationException::withMessages([
                        "validationItems.{$index}.actionPlanMilestoneId" => [
                            'The milestone must belong to the pinned accepted Action Plan Version.',
                        ],
                    ]);
                }
                if ($seenMilestones->contains((int) $milestoneId)) {
                    throw ValidationException::withMessages([
                        'validationItems' => [
                            'Only one final Validation Item is supported per accepted milestone.',
                        ],
                    ]);
                }
                $seenMilestones->push((int) $milestoneId);
                if ($progressId) {
                    $progress = $progressById->get((int) $progressId);
                    if (! $progress
                        || (int) $progress->cms_action_plan_milestone_id !== (int) $milestoneId) {
                        throw ValidationException::withMessages([
                            "validationItems.{$index}.milestoneProgressId" => [
                                'Milestone Progress must come from the exact recorded submission.',
                            ],
                        ]);
                    }
                }
            } elseif ($milestoneId || $progressId) {
                throw ValidationException::withMessages([
                    "validationItems.{$index}.scopeCode" => [
                        'Recommendation-level items cannot reference a milestone.',
                    ],
                ]);
            }

            $item = isset($entry['id'])
                ? $version->items()->whereKey($entry['id'])->first()
                : null;
            if (isset($entry['id']) && ! $item) {
                throw ValidationException::withMessages([
                    "validationItems.{$index}.id" => ['The Validation Item is outside this draft.'],
                ]);
            }
            $values = [
                'scope_code' => $scopeCode,
                'cms_action_plan_milestone_id' => $milestoneId,
                'cms_milestone_progress_id' => $progressId,
                'sequence_number' => $entry['sequenceNumber'],
                'criterion' => $entry['criterion'] ?? null,
                'procedure_performed' => $entry['procedurePerformed'] ?? null,
                'population_or_source' => $entry['populationOrSource'] ?? null,
                'sample_description' => $entry['sampleDescription'] ?? null,
                'result_summary' => $entry['resultSummary'] ?? null,
                'exception_summary' => $entry['exceptionSummary'] ?? null,
                'item_conclusion_code' => $entry['itemConclusionCode'] ?? null,
                'validated_milestone_percentage' => $entry['validatedMilestonePercentage'] ?? null,
                'follow_up_required' => $entry['followUpRequired'] ?? null,
                'display_order' => $entry['displayOrder'],
            ];
            if ($item) {
                $item->fill($values)->save();
            } else {
                $item = $version->items()->create($values);
            }
            $kept->push($item->id);
        }
        $removing = $version->items()->whereNotIn('id', $kept)->get();
        foreach ($removing as $item) {
            if ($item->evidenceLinks()->whereNull('removed_at')->exists()
                || $item->evidenceAssessments()->exists()) {
                throw ValidationException::withMessages([
                    'validationItems' => [
                        'Remove or reassign linked draft evidence assessments before deleting an item.',
                    ],
                ]);
            }
            $item->delete();
        }
    }

    /** @param list<array<string, mixed>> $payload */
    private function syncAssessments(
        User $actor,
        CmsValidationReview $review,
        CmsValidationVersion $version,
        array $payload,
    ): void {
        foreach ($payload as $index => $entry) {
            $managementId = $entry['progressEvidenceLinkId'] ?? null;
            $validatorId = $entry['validationEvidenceLinkId'] ?? null;
            if (($managementId ? 1 : 0) + ($validatorId ? 1 : 0) !== 1) {
                throw ValidationException::withMessages([
                    "evidenceAssessments.{$index}" => [
                        'Exactly one management or validator evidence link is required.',
                    ],
                ]);
            }
            $source = strtoupper((string) $entry['evidenceSourceCode']);
            if ($managementId) {
                $valid = $review->recordedProgressUpdateVersion()
                    ->firstOrFail()->activeEvidenceLinks()->whereKey($managementId)->exists();
                if (! $valid || $source !== 'MANAGEMENT_SUBMITTED') {
                    throw ValidationException::withMessages([
                        "evidenceAssessments.{$index}.progressEvidenceLinkId" => [
                            'Management evidence must come from the exact recorded submission.',
                        ],
                    ]);
                }
            }
            if ($validatorId) {
                $valid = $version->activeEvidenceLinks()->whereKey($validatorId)->exists();
                if (! $valid || $source !== 'VALIDATOR_OBTAINED') {
                    throw ValidationException::withMessages([
                        "evidenceAssessments.{$index}.validationEvidenceLinkId" => [
                            'Validator evidence must belong to this Validation Version.',
                        ],
                    ]);
                }
            }
            $itemId = $entry['validationItemId'] ?? null;
            if ($itemId && ! $version->items()->whereKey($itemId)->exists()) {
                throw ValidationException::withMessages([
                    "evidenceAssessments.{$index}.validationItemId" => [
                        'The Validation Item is outside this draft.',
                    ],
                ]);
            }
            $reliedUpon = (bool) $entry['reliedUpon'];
            if ($reliedUpon
                && in_array('NOT_ASSESSED', [
                    $entry['relevanceCode'],
                    $entry['reliabilityCode'],
                    $entry['sufficiencyCode'],
                ], true)) {
                throw ValidationException::withMessages([
                    "evidenceAssessments.{$index}" => [
                        'Relied-upon evidence requires completed professional assessments.',
                    ],
                ]);
            }
            $assessment = isset($entry['id'])
                ? $version->evidenceAssessments()->whereKey($entry['id'])->first()
                : null;
            if (isset($entry['id']) && ! $assessment) {
                throw ValidationException::withMessages([
                    "evidenceAssessments.{$index}.id" => [
                        'The Evidence Assessment is outside this draft.',
                    ],
                ]);
            }
            $values = [
                'cms_validation_item_id' => $itemId,
                'cms_progress_evidence_link_id' => $managementId,
                'cms_validation_evidence_link_id' => $validatorId,
                'evidence_source_code' => $source,
                'relevance_code' => $entry['relevanceCode'],
                'reliability_code' => $entry['reliabilityCode'],
                'sufficiency_code' => $entry['sufficiencyCode'],
                'relied_upon' => $reliedUpon,
                'assessment_summary' => $entry['assessmentSummary'] ?? null,
                'limitation_summary' => $entry['limitationSummary'] ?? null,
                'assessed_by' => $actor->id,
                'assessed_at' => now(),
            ];
            if ($assessment) {
                $assessment->fill($values)->save();
            } else {
                $version->evidenceAssessments()->create($values);
            }
        }
    }

    private function assertComplete(
        CmsValidationReview $review,
        CmsValidationVersion $version,
    ): void {
        $result = $this->completeness($review, $version);
        if (! $result['complete']) {
            throw ValidationException::withMessages($result['errors']);
        }
    }

    private function assertConclusionConsistency(
        CmsValidationReview $review,
        CmsValidationVersion $version,
        string $conclusion,
    ): void {
        $version->loadMissing('items', 'evidenceAssessments');
        $itemCodes = $version->items
            ->where('scope_code', 'MILESTONE')
            ->pluck('item_conclusion_code');
        $errors = [];
        if ($conclusion === 'IMPLEMENTED') {
            if ($itemCodes->contains(fn ($code): bool => in_array($code, [
                'PARTIALLY_SATISFIED',
                'NOT_SATISFIED',
                'INADEQUATE_BASIS',
            ], true))) {
                $errors['proposedConclusionCode'][] =
                    'IMPLEMENTED conflicts with a partial, unsatisfied, or inadequate-basis milestone.';
            }
            if ($version->evidenceAssessments->where('relied_upon', true)->contains(
                fn ($assessment): bool => in_array($assessment->sufficiency_code, [
                    'INSUFFICIENT',
                    'NOT_ASSESSED',
                ], true),
            )) {
                $errors['proposedConclusionCode'][] =
                    'IMPLEMENTED requires sufficient assessed evidence for every relied-upon record.';
            }
        }
        if ($conclusion === 'PARTIALLY_IMPLEMENTED') {
            if (! $itemCodes->contains(fn ($code): bool => in_array($code, [
                'SATISFIED',
                'PARTIALLY_SATISFIED',
            ], true))
                || ! $itemCodes->contains(fn ($code): bool => in_array($code, [
                    'PARTIALLY_SATISFIED',
                    'NOT_SATISFIED',
                    'INADEQUATE_BASIS',
                ], true))) {
                $errors['proposedConclusionCode'][] =
                    'PARTIALLY_IMPLEMENTED requires both established progress and material remaining work.';
            }
        }
        if ($conclusion === 'NOT_IMPLEMENTED'
            && $itemCodes->isNotEmpty()
            && $itemCodes->every(fn ($code): bool => $code === 'SATISFIED')) {
            $errors['proposedConclusionCode'][] =
                'NOT_IMPLEMENTED conflicts with all required milestones being satisfied.';
        }
        if ($conclusion === 'INADEQUATE_BASIS') {
            $hasInadequateItem = $itemCodes->contains('INADEQUATE_BASIS');
            $hasEvidenceLimitation = $version->evidenceAssessments->contains(
                fn ($assessment): bool => $assessment->sufficiency_code === 'INSUFFICIENT'
                    || filled($assessment->limitation_summary),
            );
            if (blank($version->limitations) || (! $hasInadequateItem && ! $hasEvidenceLimitation)) {
                $errors['proposedConclusionCode'][] =
                    'INADEQUATE_BASIS requires a documented limitation and inadequate item or evidence basis.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, mixed> */
    private function snapshotFor(
        CmsValidationReview $review,
        CmsValidationVersion $version,
    ): array {
        $review->loadMissing([
            'case.recommendation',
            'acceptedActionPlanVersion.milestones',
            'recordedProgressUpdateVersion.milestoneProgress',
            'recordedProgressUpdateVersion.activeEvidenceLinks.documentVersion',
        ]);
        $version->loadMissing([
            'items',
            'evidenceAssessments',
            'activeEvidenceLinks.documentVersion',
        ]);

        return [
            'recommendationCaseId' => $review->cms_recommendation_case_id,
            'recommendationCode' => $review->case->recommendation?->recommendation_code,
            'validationReviewId' => $review->id,
            'validationVersionNumber' => $version->version_number,
            'acceptedActionPlanVersionId' => $review->accepted_action_plan_version_id,
            'recordedProgressUpdateVersionId' => $review->recorded_progress_update_version_id,
            'managementReportedProgress' => $review->recordedProgressUpdateVersion
                ->only([
                    'management_reported_overall_percentage',
                    'system_calculated_weighted_percentage',
                ]),
            'acceptedMilestones' => $review->acceptedActionPlanVersion->milestones
                ->map(fn ($milestone): array => [
                    'id' => $milestone->id,
                    'sequenceNumber' => $milestone->sequence_number,
                    'title' => $milestone->title,
                    'expectedOutput' => $milestone->expected_output,
                    'successIndicator' => $milestone->success_indicator,
                    'verificationMethod' => $milestone->verification_method,
                    'weightPercentage' => $milestone->weight_percentage,
                ])->values()->all(),
            'milestoneProgress' => $review->recordedProgressUpdateVersion->milestoneProgress
                ->map(fn ($progress): array => [
                    'id' => $progress->id,
                    'actionPlanMilestoneId' => $progress->cms_action_plan_milestone_id,
                    'managementReportedStatusCode' => $progress->management_reported_status_code,
                    'managementReportedPercentage' => $progress->management_reported_percentage,
                ])->values()->all(),
            'managementEvidence' => $review->recordedProgressUpdateVersion->activeEvidenceLinks
                ->map(fn ($evidence): array => [
                    'evidenceLinkId' => $evidence->id,
                    'documentVersionId' => $evidence->document_version_id,
                    'checksumSha256' => $evidence->checksum_sha256,
                ])->values()->all(),
            'professionalContent' => $this->copyContent($version),
            'validationItems' => $version->items->map(
                fn ($item): array => collect($item->getAttributes())
                    ->except(['created_at', 'updated_at'])
                    ->all(),
            )->values()->all(),
            'evidenceAssessments' => $version->evidenceAssessments->map(
                fn ($assessment): array => collect($assessment->getAttributes())
                    ->except(['created_at', 'updated_at'])
                    ->all(),
            )->values()->all(),
            'validatorEvidence' => $version->activeEvidenceLinks->map(
                fn ($evidence): array => [
                    'validationEvidenceLinkId' => $evidence->id,
                    'validationItemId' => $evidence->cms_validation_item_id,
                    'documentId' => $evidence->document_id,
                    'documentVersionId' => $evidence->document_version_id,
                    'checksumSha256' => $evidence->checksum_sha256,
                    'confidentialityCode' => $evidence->confidentiality_code_snapshot,
                ],
            )->values()->all(),
            'proposedConclusionCode' => $version->proposed_conclusion_code,
            'validatedCompletionPercentage' => $version->validated_completion_percentage,
            'validatorUserId' => $version->validator_user_id,
            'submittedBy' => $version->submitted_by,
            'submittedAt' => $version->submitted_at?->format('Y-m-d\TH:i:sP'),
        ];
    }

    private function assertSnapshotIntegrity(
        CmsValidationReview $review,
        CmsValidationVersion $version,
    ): void {
        if (! $version->submission_snapshot
            || json_encode($version->submission_snapshot)
                !== json_encode($this->snapshotFor($review, $version))) {
            throw ValidationException::withMessages([
                'version' => [
                    'The submitted Validation snapshot is missing or no longer matches its immutable content.',
                ],
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function draftAttributes(array $attributes): array
    {
        $mapping = [
            'validationScope' => 'validation_scope',
            'validationObjectives' => 'validation_objectives',
            'methodologySummary' => 'methodology_summary',
            'overallWorkPerformed' => 'overall_work_performed',
            'overallEvidenceSummary' => 'overall_evidence_summary',
            'limitations' => 'limitations',
            'professionalJudgmentRationale' => 'professional_judgment_rationale',
            'proposedConclusionCode' => 'proposed_conclusion_code',
            'validatedCompletionPercentage' => 'validated_completion_percentage',
        ];

        return collect($mapping)
            ->filter(fn (string $column, string $key): bool => array_key_exists($key, $attributes))
            ->mapWithKeys(fn (string $column, string $key): array => [
                $column => $attributes[$key],
            ])->all();
    }

    /** @return array<string, mixed> */
    private function copyContent(CmsValidationVersion $version): array
    {
        return collect($version->getAttributes())->only([
            'validation_scope',
            'validation_objectives',
            'methodology_summary',
            'overall_work_performed',
            'overall_evidence_summary',
            'limitations',
            'professional_judgment_rationale',
            'proposed_conclusion_code',
            'validated_completion_percentage',
        ])->all();
    }

    private function assertCaseLock(CmsRecommendationCase $case, int $lockVersion): void
    {
        if ((int) $case->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['The CMS recommendation changed. Refresh before retrying.'],
            ]);
        }
    }

    private function assertReviewLock(CmsValidationReview $review, int $lockVersion): void
    {
        if ((int) $review->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['The Validation Review changed. Refresh before retrying.'],
            ]);
        }
    }

    private function assertVersionLock(CmsValidationVersion $version, int $lockVersion): void
    {
        if ((int) $version->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['The Validation Version changed. Refresh before retrying.'],
            ]);
        }
    }

    private function assertStatus(CmsValidationVersion $version, string $status): void
    {
        if ($version->status_code !== $status) {
            throw ValidationException::withMessages([
                'status' => ["This action requires a Validation Version in {$status} status."],
            ]);
        }
    }

    private function assertCurrentDraft(
        CmsValidationReview $review,
        CmsValidationVersion $version,
    ): void {
        $this->assertStatus($version, CmsValidationVersion::STATUS_DRAFT);
        if ((int) $review->current_version_id !== $version->id
            || $review->active_slot !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'version' => ['Only the current Validation draft may be edited.'],
            ]);
        }
    }

    /** @return Collection<int, int> */
    private function sourceParticipantIds(CmsValidationReview $review): Collection
    {
        $review->loadMissing([
            'acceptedActionPlanVersion',
            'recordedProgressUpdateVersion',
        ]);

        return collect([
            $review->acceptedActionPlanVersion?->prepared_by,
            $review->acceptedActionPlanVersion?->focal_user_id,
            $review->acceptedActionPlanVersion?->submitted_by,
            $review->recordedProgressUpdateVersion?->prepared_by,
            $review->recordedProgressUpdateVersion?->submitted_by,
            $review->recordedProgressUpdateVersion?->recorded_by,
        ])->filter()->map(fn ($id): int => (int) $id)->unique()->values();
    }

    /** @return list<string> */
    private function caseRelations(): array
    {
        return [
            'recommendation',
            'leadResponsibleOffice',
            'currentAssignment.user',
            'actionPlan.acceptedVersion.milestones',
            'progressUpdates.recordedVersion',
            'activeValidationReview',
        ];
    }

    /** @return list<string> */
    private function reviewRelations(): array
    {
        return [
            'case.recommendation',
            'case.leadResponsibleOffice',
            'case.currentAssignment.user',
            'actionPlan',
            'acceptedActionPlanVersion.milestones',
            'progressUpdate',
            'recordedProgressUpdateVersion.milestoneProgress',
            'recordedProgressUpdateVersion.activeEvidenceLinks.documentVersion',
            'creator',
            'assignments.user',
            'assignments.assigner',
            'assignments.ender',
            'currentAssignment.user',
            'versions',
            ...array_map(
                fn (string $relation): string => "currentVersion.{$relation}",
                self::VERSION_RELATIONS,
            ),
            ...array_map(
                fn (string $relation): string => "finalizedVersion.{$relation}",
                self::VERSION_RELATIONS,
            ),
            ...array_map(
                fn (string $relation): string => "versions.{$relation}",
                self::VERSION_RELATIONS,
            ),
        ];
    }

    /**
     * @param  Collection<int, CmsValidationReview>  $reviews
     * @return Collection<int, CmsValidationReview>
     */
    private function filterReadOnlyVersions(User $actor, Collection $reviews): Collection
    {
        if (! $actor->hasAnyPermission(['access.read_only_scope', 'access.auditee_scope'])) {
            return $reviews;
        }
        foreach ($reviews as $review) {
            $finalized = $review->versions
                ->where('status_code', CmsValidationVersion::STATUS_FINALIZED)
                ->values();
            $review->setRelation('versions', $finalized);
            if ($review->currentVersion
                && $review->currentVersion->status_code !== CmsValidationVersion::STATUS_FINALIZED) {
                $review->setRelation('currentVersion', null);
            }
        }

        return $reviews;
    }

    private function record(
        Request $request,
        CmsRecommendationCase $case,
        CmsValidationReview $review,
        CmsValidationVersion $version,
        string $eventCode,
        string $auditAction,
        ?string $previousVersionStatus,
        string $newVersionStatus,
        string $previousCaseStatus,
        string $newCaseStatus,
        array $extra = [],
    ): void {
        $metadata = [
            'caseId' => $case->id,
            'validationReviewId' => $review->id,
            'validationVersionId' => $version->id,
            'versionNumber' => $version->version_number,
            'acceptedActionPlanVersionId' => $review->accepted_action_plan_version_id,
            'recordedProgressUpdateVersionId' => $review->recorded_progress_update_version_id,
            'validatorUserId' => $review->currentAssignment()->value('user_id'),
            'itemCount' => $version->items()->count(),
            'evidenceAssessmentCount' => $version->evidenceAssessments()->count(),
            'validatorEvidenceCount' => $version->activeEvidenceLinks()->count(),
            'previousVersionStatus' => $previousVersionStatus,
            'newVersionStatus' => $newVersionStatus,
            'previousCaseStatus' => $previousCaseStatus,
            'newCaseStatus' => $newCaseStatus,
            'proposedConclusionCode' => $version->proposed_conclusion_code,
            'finalConclusionCode' => $version->final_conclusion_code,
            'validatedCompletionPercentage' => $version->validated_completion_percentage,
            'actorId' => $request->user()->id,
            ...$extra,
        ];
        CmsRecommendationEvent::query()->create([
            'cms_recommendation_case_id' => $case->id,
            'cms_recommendation_id' => $case->cms_recommendation_id,
            'idempotency_key' => "cms-validation:{$review->id}:{$version->id}:{$eventCode}:{$version->lock_version}",
            'event_code' => $eventCode,
            'source_module' => 'CMS',
            'actor_id' => $request->user()->id,
            'previous_status' => $previousCaseStatus,
            'new_status' => $newCaseStatus,
            'event_metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => $auditAction,
            'description' => 'Updated the controlled CMS independent-validation workflow.',
            'old_values' => [
                'versionStatus' => $previousVersionStatus,
                'caseStatus' => $previousCaseStatus,
            ],
            'new_values' => [
                'versionStatus' => $newVersionStatus,
                'caseStatus' => $newCaseStatus,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => ['module' => 'CMS', ...$metadata],
        ]);
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => $auditAction,
            'auditable_type' => CmsValidationReview::class,
            'auditable_id' => $review->id,
            'old_values' => [
                'versionStatus' => $previousVersionStatus,
                'caseStatus' => $previousCaseStatus,
            ],
            'new_values' => [
                'versionStatus' => $newVersionStatus,
                'caseStatus' => $newCaseStatus,
                'currentVersionId' => $review->current_version_id,
                'finalizedVersionId' => $review->finalized_version_id,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => ['module' => 'CMS', ...$metadata],
        ]);
    }

    private function notify(
        string $transition,
        int $actorId,
        int $caseId,
        int $reviewId,
        int $versionId,
        ?int $validatorId,
        ?string $conclusion = null,
        ?int $priorValidatorId = null,
    ): void {
        $case = CmsRecommendationCase::query()
            ->with(['recommendation', 'currentAssignment'])
            ->find($caseId);
        if (! $case) {
            return;
        }
        $supervisors = $this->supervisorRecipients($case);
        $recipients = match ($transition) {
            'assigned' => collect([$validatorId])->merge($supervisors),
            'replaced' => collect([$validatorId, $priorValidatorId])->merge($supervisors),
            'submitted' => $supervisors,
            'review_started', 'returned', 'revision_created' => collect([$validatorId]),
            'finalized' => collect([
                $validatorId,
                $case->currentAssignment?->user_id,
            ])->merge($supervisors)->merge($this->responsibleRecipients($case)),
            default => collect(),
        };
        $labels = [
            'assigned' => [
                'CMS_VALIDATOR_ASSIGNED',
                'Independent validation assignment',
                'You were assigned to an authorized CMS Independent Validation Review.',
            ],
            'replaced' => [
                'CMS_VALIDATOR_REPLACED',
                'Independent validation assignment updated',
                'The Primary Validator assignment was replaced with preserved assignment history.',
            ],
            'submitted' => [
                'CMS_VALIDATION_SUBMITTED',
                'Validation submitted',
                'An independent professional validation is ready for supervisory review.',
            ],
            'review_started' => [
                'CMS_VALIDATION_REVIEW_STARTED',
                'Validation review started',
                'Supervisory review of the submitted validation has started.',
            ],
            'returned' => [
                'CMS_VALIDATION_RETURNED',
                'Validation returned',
                'The submitted validation was returned; create a controlled draft revision.',
            ],
            'revision_created' => [
                'CMS_VALIDATION_REVISION_CREATED',
                'Validation revision created',
                'A controlled draft revision was created from the returned validation.',
            ],
            'finalized' => [
                'CMS_VALIDATION_FINALIZED',
                'Independent validation finalized',
                "The professional conclusion is {$conclusion}. Recommendation status is {$case->status_code}; implementation does not mean closure.",
            ],
        ];
        [$type, $title, $message] = $labels[$transition];
        $this->notifications->send($recipients->filter()->unique(), [
            'actorId' => $actorId,
            'type' => $type,
            'category' => 'WORKFLOW',
            'priority' => in_array($transition, ['returned', 'finalized'], true) ? 'HIGH' : 'NORMAL',
            'moduleCode' => 'CMS',
            'title' => $title,
            'message' => $message,
            'actionUrl' => "/compliance-management/recommendations/{$caseId}",
            'actionLabel' => 'Open recommendation',
            'subjectType' => CmsValidationReview::class,
            'subjectId' => $reviewId,
            'subjectCode' => sprintf('VAL-CMS-REC-%06d', $caseId),
            'dedupeKey' => "cms-validation:{$reviewId}:{$versionId}:{$transition}",
            'metadata' => [
                'caseId' => $caseId,
                'validationReviewId' => $reviewId,
                'validationVersionId' => $versionId,
                'conclusion' => $conclusion,
                'caseStatus' => $case->status_code,
                'closurePending' => $conclusion === 'IMPLEMENTED',
            ],
        ]);
    }

    /** @return Collection<int, int> */
    private function supervisorRecipients(CmsRecommendationCase $case): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->with(['role.permissions', 'roles.permissions'])
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission('cms.validation.review')
                && $this->scope->canViewClassification(
                    $user,
                    $case->recommendation?->confidentiality_code_snapshot,
                ))
            ->pluck('id');
    }

    /** @return Collection<int, int> */
    private function responsibleRecipients(CmsRecommendationCase $case): Collection
    {
        return User::query()
            ->where('office_id', $case->lead_responsible_office_id)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query
                    ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'access.auditee_scope'))
                    ->orWhereHas('role.permissions', fn ($permission) => $permission->where('code', 'access.auditee_scope'));
            })->pluck('id');
    }
}
