<?php

namespace App\Services\Cms;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\CmsActionPlanMilestone;
use App\Models\CmsActionPlanVersion;
use App\Models\CmsCorrectiveActionPlan;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Owns the complete CMS-3A Action Plan aggregate and its controlled workflow.
 *
 * Controllers never set statuses, version numbers, accepted pointers, or actors.
 */
class CmsActionPlanService
{
    private const VERSION_RELATIONS = [
        'ownerOffice',
        'focalUser',
        'preparer',
        'submitter',
        'reviewStarter',
        'accepter',
        'returner',
        'milestones.responsibleOffice',
        'milestones.responsibleUser',
    ];

    public function __construct(
        private readonly CmsRecommendationScopeService $scope,
        private readonly NotificationService $notifications,
    ) {}

    /** @return array{case: CmsRecommendationCase, plan: CmsCorrectiveActionPlan|null, permittedActions: list<string>} */
    public function showForCase(User $actor, int $caseId): array
    {
        $case = $this->scope->resolveVisibleCase(
            $actor,
            $caseId,
            'cms.action-plan.view',
        );
        $case->load([
            'recommendation',
            'leadResponsibleOffice',
            'currentAssignment.user',
        ]);
        $plan = CmsCorrectiveActionPlan::query()
            ->where('cms_recommendation_case_id', $case->id)
            ->with($this->planRelations())
            ->first();

        return [
            'case' => $case,
            'plan' => $plan,
            'permittedActions' => $plan
                ? $this->permittedActions($actor, $plan, $plan->currentVersion)
                : ($this->canResponsible($actor, $case, 'cms.action-plan.create')
                    ? ['create']
                    : []),
        ];
    }

    /** @return array{case: CmsRecommendationCase, plan: CmsCorrectiveActionPlan} */
    public function show(User $actor, int $planId): array
    {
        [$case, $plan] = $this->resolvePlan($actor, $planId);
        $case->load([
            'recommendation',
            'leadResponsibleOffice',
            'currentAssignment.user',
        ]);
        $plan->load($this->planRelations());

        return ['case' => $case, 'plan' => $plan];
    }

    /** @param array<string, mixed> $attributes */
    public function create(Request $request, int $caseId, array $attributes): CmsCorrectiveActionPlan
    {
        $actor = $request->user();
        $result = DB::transaction(function () use ($request, $actor, $caseId, $attributes) {
            $case = $this->scope->resolveVisibleCase(
                $actor,
                $caseId,
                'cms.action-plan.view',
                true,
            );
            $this->authorizeResponsible($actor, $case, 'cms.action-plan.create');
            $this->assertCaseLock($case, (int) $attributes['lockVersion']);
            $this->assertActionableCase($case);

            if (CmsCorrectiveActionPlan::query()
                ->where('cms_recommendation_case_id', $case->id)
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'actionPlan' => ['This recommendation already has an Action Plan.'],
                ]);
            }
            if (! $case->lead_responsible_office_id) {
                throw ValidationException::withMessages([
                    'ownerOfficeId' => ['The recommendation has no lead responsible office.'],
                ]);
            }
            $this->assertOwnerOffice($case, $attributes['ownerOfficeId'] ?? null);

            $plan = CmsCorrectiveActionPlan::query()->create([
                'cms_recommendation_case_id' => $case->id,
                'owner_office_id' => $case->lead_responsible_office_id,
                'created_by' => $actor->id,
                'lock_version' => 1,
            ]);
            $version = CmsActionPlanVersion::query()->create([
                'cms_corrective_action_plan_id' => $plan->id,
                'version_number' => 1,
                'status_code' => CmsActionPlanVersion::STATUS_DRAFT,
                'active_slot' => 'ACTIVE',
                ...$this->draftAttributes($attributes),
                'owner_office_id' => $plan->owner_office_id,
                'prepared_by' => $actor->id,
                'lock_version' => 1,
            ]);
            $this->validateDraft($case, $version, $attributes['milestones'] ?? []);
            $this->replaceMilestones($version, $attributes['milestones'] ?? []);
            $plan->forceFill(['current_version_id' => $version->id])->save();

            $oldCaseStatus = $case->status_code;
            $case->forceFill([
                'status_code' => CmsRecommendationCase::STATUS_FOR_ACTION_PLAN,
                'lock_version' => $case->lock_version + 1,
            ])->save();
            $this->record(
                $request,
                $case,
                $plan,
                $version,
                CmsRecommendationEvent::EVENT_ACTION_PLAN_CREATED,
                'cms.action-plan.created',
                null,
                CmsActionPlanVersion::STATUS_DRAFT,
                $oldCaseStatus,
                $case->status_code,
            );

            return $plan;
        });

        return $result->fresh($this->planRelations());
    }

    /** @param array<string, mixed> $attributes */
    public function update(
        Request $request,
        int $planId,
        int $versionId,
        array $attributes,
    ): CmsCorrectiveActionPlan {
        $actor = $request->user();
        $result = DB::transaction(function () use (
            $request,
            $actor,
            $planId,
            $versionId,
            $attributes,
        ) {
            [$case, $plan] = $this->resolvePlan($actor, $planId, true);
            $this->authorizeResponsible($actor, $case, 'cms.action-plan.update');
            $version = $this->resolveVersion($plan, $versionId, true);
            $this->assertVersionLock($version, (int) $attributes['lockVersion']);
            $this->assertStatus($version, CmsActionPlanVersion::STATUS_DRAFT);
            throw_unless(
                (int) $plan->current_version_id === $version->id,
                ValidationException::withMessages([
                    'version' => ['Only the current draft version may be updated.'],
                ]),
            );
            $this->assertOwnerOffice($case, $attributes['ownerOfficeId'] ?? null);

            $oldLock = $version->lock_version;
            $version->fill($this->draftAttributes($attributes));
            $milestones = array_key_exists('milestones', $attributes)
                ? $attributes['milestones']
                : $this->milestonePayload($version->milestones()->get());
            $this->validateDraft($case, $version, $milestones);
            $version->forceFill(['lock_version' => $version->lock_version + 1])->save();
            if (array_key_exists('milestones', $attributes)) {
                $this->replaceMilestones($version, $milestones);
            }
            $plan->forceFill(['lock_version' => $plan->lock_version + 1])->save();
            $this->record(
                $request,
                $case,
                $plan,
                $version,
                CmsRecommendationEvent::EVENT_ACTION_PLAN_UPDATED,
                'cms.action-plan.updated',
                CmsActionPlanVersion::STATUS_DRAFT,
                CmsActionPlanVersion::STATUS_DRAFT,
                $case->status_code,
                $case->status_code,
                ['previousVersionLock' => $oldLock],
            );

            return $plan;
        });

        return $result->fresh($this->planRelations());
    }

    public function submit(
        Request $request,
        int $planId,
        int $versionId,
        int $lockVersion,
    ): CmsCorrectiveActionPlan {
        return $this->transition(
            $request,
            $planId,
            $versionId,
            $lockVersion,
            CmsActionPlanVersion::STATUS_DRAFT,
            CmsActionPlanVersion::STATUS_SUBMITTED,
            'cms.action-plan.submit',
            CmsRecommendationEvent::EVENT_ACTION_PLAN_SUBMITTED,
            'cms.action-plan.submitted',
            function (
                CmsRecommendationCase $case,
                CmsCorrectiveActionPlan $plan,
                CmsActionPlanVersion $version,
                User $actor,
            ): array {
                $this->authorizeResponsible($actor, $case, 'cms.action-plan.submit');
                $this->assertComplete($case, $version);
                $snapshot = $this->snapshotFor($version);

                return [
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                    'submission_snapshot' => $snapshot,
                ];
            },
            'submitted',
        );
    }

    public function startReview(
        Request $request,
        int $planId,
        int $versionId,
        int $lockVersion,
        ?string $comment = null,
    ): CmsCorrectiveActionPlan {
        return $this->transition(
            $request,
            $planId,
            $versionId,
            $lockVersion,
            CmsActionPlanVersion::STATUS_SUBMITTED,
            CmsActionPlanVersion::STATUS_UNDER_REVIEW,
            'cms.action-plan.review',
            CmsRecommendationEvent::EVENT_ACTION_PLAN_REVIEW_STARTED,
            'cms.action-plan.review_started',
            function (
                CmsRecommendationCase $case,
                CmsCorrectiveActionPlan $plan,
                CmsActionPlanVersion $version,
                User $actor,
            ): array {
                $this->authorizeReviewer($actor, $case, $version, 'cms.action-plan.review');
                $this->assertSnapshotIntegrity($version);

                return [
                    'review_started_by' => $actor->id,
                    'review_started_at' => now(),
                ];
            },
            'review_started',
            ['reviewComment' => $comment],
        );
    }

    public function return(
        Request $request,
        int $planId,
        int $versionId,
        int $lockVersion,
        string $reason,
    ): CmsCorrectiveActionPlan {
        return $this->transition(
            $request,
            $planId,
            $versionId,
            $lockVersion,
            CmsActionPlanVersion::STATUS_UNDER_REVIEW,
            CmsActionPlanVersion::STATUS_RETURNED,
            'cms.action-plan.return',
            CmsRecommendationEvent::EVENT_ACTION_PLAN_RETURNED,
            'cms.action-plan.returned',
            function (
                CmsRecommendationCase $case,
                CmsCorrectiveActionPlan $plan,
                CmsActionPlanVersion $version,
                User $actor,
            ) use ($reason): array {
                $this->authorizeReviewer($actor, $case, $version, 'cms.action-plan.return');
                $this->assertSnapshotIntegrity($version);

                return [
                    'returned_by' => $actor->id,
                    'returned_at' => now(),
                    'return_reason' => $reason,
                ];
            },
            'returned',
            ['returnReason' => $reason],
        );
    }

    public function accept(
        Request $request,
        int $planId,
        int $versionId,
        int $lockVersion,
        string $comment,
    ): CmsCorrectiveActionPlan {
        $actor = $request->user();
        $result = DB::transaction(function () use (
            $request,
            $actor,
            $planId,
            $versionId,
            $lockVersion,
            $comment,
        ) {
            [$case, $plan] = $this->resolvePlan($actor, $planId, true);
            $version = $this->resolveVersion($plan, $versionId, true);
            $this->assertVersionLock($version, $lockVersion);
            $this->assertStatus($version, CmsActionPlanVersion::STATUS_UNDER_REVIEW);
            $this->authorizeReviewer($actor, $case, $version, 'cms.action-plan.accept');
            $this->assertSnapshotIntegrity($version);
            $this->assertComplete($case, $version);

            $oldCaseStatus = $case->status_code;
            $version->forceFill([
                'status_code' => CmsActionPlanVersion::STATUS_ACCEPTED,
                'active_slot' => null,
                'accepted_by' => $actor->id,
                'accepted_at' => now(),
                'acceptance_comment' => $comment,
                'lock_version' => $version->lock_version + 1,
            ])->save();
            $plan->forceFill([
                'current_version_id' => $version->id,
                'accepted_version_id' => $version->id,
                'lock_version' => $plan->lock_version + 1,
            ])->save();
            if ($case->status_code === CmsRecommendationCase::STATUS_FOR_ACTION_PLAN) {
                $case->forceFill([
                    'status_code' => CmsRecommendationCase::STATUS_MONITORING,
                    'lock_version' => $case->lock_version + 1,
                ])->save();
            }
            $this->record(
                $request,
                $case,
                $plan,
                $version,
                CmsRecommendationEvent::EVENT_ACTION_PLAN_ACCEPTED,
                'cms.action-plan.accepted',
                CmsActionPlanVersion::STATUS_UNDER_REVIEW,
                CmsActionPlanVersion::STATUS_ACCEPTED,
                $oldCaseStatus,
                $case->status_code,
                [
                    'acceptanceComment' => $comment,
                    'acceptedVersionId' => $version->id,
                ],
            );

            DB::afterCommit(fn () => $this->notify(
                'accepted',
                $actor->id,
                $case->id,
                $plan->id,
                $version,
            ));

            return $plan;
        });

        return $result->fresh($this->planRelations());
    }

    public function revise(
        Request $request,
        int $planId,
        int $versionId,
        int $lockVersion,
        string $reason,
    ): CmsCorrectiveActionPlan {
        $actor = $request->user();
        $result = DB::transaction(function () use (
            $request,
            $actor,
            $planId,
            $versionId,
            $lockVersion,
            $reason,
        ) {
            [$case, $plan] = $this->resolvePlan($actor, $planId, true);
            $this->authorizeResponsible($actor, $case, 'cms.action-plan.revise');
            $source = $this->resolveVersion($plan, $versionId, true);
            $this->assertVersionLock($source, $lockVersion);
            throw_unless(
                in_array($source->status_code, [
                    CmsActionPlanVersion::STATUS_RETURNED,
                    CmsActionPlanVersion::STATUS_ACCEPTED,
                ], true),
                ValidationException::withMessages([
                    'version' => ['Only a returned or accepted current version may be revised.'],
                ]),
            );
            throw_unless(
                (int) $plan->current_version_id === $source->id,
                ValidationException::withMessages([
                    'version' => ['A revision may originate only from the current version.'],
                ]),
            );
            if ($plan->versions()
                ->whereIn('status_code', CmsActionPlanVersion::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'version' => ['An active Action Plan version already exists.'],
                ]);
            }

            $source->load('milestones');
            $versionNumber = (int) $plan->versions()
                ->lockForUpdate()
                ->max('version_number') + 1;
            $revision = CmsActionPlanVersion::query()->create([
                'cms_corrective_action_plan_id' => $plan->id,
                'version_number' => $versionNumber,
                'previous_version_id' => $source->id,
                'status_code' => CmsActionPlanVersion::STATUS_DRAFT,
                'active_slot' => 'ACTIVE',
                ...$this->copyContent($source),
                'owner_office_id' => $source->owner_office_id,
                'focal_user_id' => $source->focal_user_id,
                'prepared_by' => $actor->id,
                'revision_reason' => $reason,
                'lock_version' => 1,
            ]);
            foreach ($source->milestones as $milestone) {
                $revision->milestones()->create(
                    collect($milestone->getAttributes())
                        ->except(['id', 'cms_action_plan_version_id', 'created_at', 'updated_at'])
                        ->all(),
                );
            }
            $plan->forceFill([
                'current_version_id' => $revision->id,
                'lock_version' => $plan->lock_version + 1,
            ])->save();
            $this->record(
                $request,
                $case,
                $plan,
                $revision,
                CmsRecommendationEvent::EVENT_ACTION_PLAN_REVISION_CREATED,
                'cms.action-plan.revision_created',
                $source->status_code,
                CmsActionPlanVersion::STATUS_DRAFT,
                $case->status_code,
                $case->status_code,
                [
                    'sourceVersionId' => $source->id,
                    'revisionReason' => $reason,
                    'acceptedVersionId' => $plan->accepted_version_id,
                ],
            );

            return $plan;
        });

        return $result->fresh($this->planRelations());
    }

    /** @return list<string> */
    public function permittedActions(
        User $actor,
        CmsCorrectiveActionPlan $plan,
        ?CmsActionPlanVersion $version,
    ): array {
        if (! $version) {
            return [];
        }
        $plan->loadMissing('case.currentAssignment');
        $case = $plan->case;
        $actions = [];
        if ($version->status_code === CmsActionPlanVersion::STATUS_DRAFT
            && $this->canResponsible($actor, $case, 'cms.action-plan.update')) {
            $actions[] = 'update';
            if ($actor->hasPermission('cms.action-plan.submit')) {
                $actions[] = 'submit';
            }
        }
        if (in_array($version->status_code, [
            CmsActionPlanVersion::STATUS_RETURNED,
            CmsActionPlanVersion::STATUS_ACCEPTED,
        ], true)
            && (int) $plan->current_version_id === $version->id
            && $this->canResponsible($actor, $case, 'cms.action-plan.revise')) {
            $actions[] = 'revise';
        }
        if ($version->status_code === CmsActionPlanVersion::STATUS_SUBMITTED
            && $this->canReviewer($actor, $case, $version, 'cms.action-plan.review')) {
            $actions[] = 'start-review';
        }
        if ($version->status_code === CmsActionPlanVersion::STATUS_UNDER_REVIEW) {
            if ($this->canReviewer($actor, $case, $version, 'cms.action-plan.return')) {
                $actions[] = 'return';
            }
            if ($this->canReviewer($actor, $case, $version, 'cms.action-plan.accept')) {
                $actions[] = 'accept';
            }
        }

        return $actions;
    }

    /** @return array{complete: bool, errors: array<string, list<string>>, missingTargetDatePolicy: bool, weightingUsed: bool} */
    public function completeness(
        CmsRecommendationCase $case,
        CmsActionPlanVersion $version,
    ): array {
        $version->loadMissing('milestones');
        $errors = [];
        foreach ([
            'plan_summary' => 'Plan summary',
            'implementation_strategy' => 'Implementation strategy',
            'expected_outcome' => 'Expected outcome',
            'planned_start_date' => 'Planned start date',
            'planned_target_date' => 'Planned target date',
            'focal_user_id' => 'Focal user',
        ] as $field => $label) {
            if (blank($version->{$field})) {
                $errors[$this->camel($field)][] = "{$label} is required before submission.";
            }
        }
        if ($version->milestones->isEmpty()) {
            $errors['milestones'][] = 'At least one measurable milestone is required.';
        }
        try {
            $this->validateDraft(
                $case,
                $version,
                $this->milestonePayload($version->milestones),
            );
        } catch (ValidationException $exception) {
            $errors = array_merge_recursive($errors, $exception->errors());
        }

        return [
            'complete' => $errors === [],
            'errors' => $errors,
            'missingTargetDatePolicy' => $case->effective_target_implementation_date === null,
            'weightingUsed' => $version->milestones->contains(
                fn ($milestone): bool => $milestone->weight_percentage !== null,
            ),
        ];
    }

    /**
     * @param  callable(CmsRecommendationCase, CmsCorrectiveActionPlan, CmsActionPlanVersion, User): array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    private function transition(
        Request $request,
        int $planId,
        int $versionId,
        int $lockVersion,
        string $from,
        string $to,
        string $permission,
        string $eventCode,
        string $auditAction,
        callable $attributes,
        string $notification,
        array $metadata = [],
    ): CmsCorrectiveActionPlan {
        $actor = $request->user();
        $result = DB::transaction(function () use (
            $request,
            $actor,
            $planId,
            $versionId,
            $lockVersion,
            $from,
            $to,
            $eventCode,
            $auditAction,
            $attributes,
            $notification,
            $metadata,
        ) {
            [$case, $plan] = $this->resolvePlan($actor, $planId, true);
            $version = $this->resolveVersion($plan, $versionId, true);
            $this->assertVersionLock($version, $lockVersion);
            $this->assertStatus($version, $from);
            $transitionAttributes = $attributes($case, $plan, $version, $actor);
            $version->forceFill([
                ...$transitionAttributes,
                'status_code' => $to,
                'active_slot' => in_array(
                    $to,
                    CmsActionPlanVersion::ACTIVE_STATUSES,
                    true,
                ) ? 'ACTIVE' : null,
                'lock_version' => $version->lock_version + 1,
            ])->save();
            $plan->forceFill(['lock_version' => $plan->lock_version + 1])->save();
            $this->record(
                $request,
                $case,
                $plan,
                $version,
                $eventCode,
                $auditAction,
                $from,
                $to,
                $case->status_code,
                $case->status_code,
                $metadata,
            );
            DB::afterCommit(fn () => $this->notify(
                $notification,
                $actor->id,
                $case->id,
                $plan->id,
                $version,
            ));

            return $plan;
        });

        return $result->fresh($this->planRelations());
    }

    /** @return array{0: CmsRecommendationCase, 1: CmsCorrectiveActionPlan} */
    private function resolvePlan(User $actor, int $planId, bool $lock = false): array
    {
        $reference = CmsCorrectiveActionPlan::query()->find($planId);
        throw_unless($reference, new HttpException(404, 'The Action Plan is unavailable.'));
        $case = $this->scope->resolveVisibleCase(
            $actor,
            $reference->cms_recommendation_case_id,
            'cms.action-plan.view',
            $lock,
        );
        $query = CmsCorrectiveActionPlan::query()
            ->whereKey($planId)
            ->where('cms_recommendation_case_id', $case->id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $plan = $query->first();
        throw_unless($plan, new HttpException(404, 'The Action Plan is unavailable.'));

        return [$case, $plan];
    }

    private function resolveVersion(
        CmsCorrectiveActionPlan $plan,
        int $versionId,
        bool $lock = false,
    ): CmsActionPlanVersion {
        $query = $plan->versions()->whereKey($versionId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $version = $query->first();
        throw_unless($version, new HttpException(404, 'The Action Plan version is unavailable.'));

        return $version;
    }

    private function authorizeResponsible(
        User $actor,
        CmsRecommendationCase $case,
        string $permission,
    ): void {
        throw_unless(
            $this->canResponsible($actor, $case, $permission),
            new HttpException(403, 'Only an authorized responsible-office user may perform this action.'),
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
        CmsActionPlanVersion $version,
        string $permission,
    ): void {
        throw_unless(
            $this->canReviewer($actor, $case, $version, $permission),
            new HttpException(403, 'An independent authorized Compliance reviewer is required.'),
        );
    }

    private function canReviewer(
        User $actor,
        CmsRecommendationCase $case,
        CmsActionPlanVersion $version,
        string $permission,
    ): bool {
        if (! $this->scope->isUsableAccount($actor)
            || ! $actor->hasPermission($permission)
            || (int) $actor->office_id === (int) $version->owner_office_id
            || in_array($actor->id, array_filter([
                $version->prepared_by,
                $version->focal_user_id,
                $version->submitted_by,
            ]), true)) {
            return false;
        }
        if ($actor->hasGlobalEngagementAccess()) {
            return true;
        }

        return CmsActionPlanVersion::query()
            ->whereKey($version->id)
            ->whereHas(
                'plan.case.currentAssignment',
                fn ($assignment) => $assignment
                    ->where('user_id', $actor->id)
                    ->where('assignment_role_code', 'COMPLIANCE_MONITOR')
                    ->where('is_current', true),
            )->exists();
    }

    /** @param array<string, mixed> $attributes */
    private function draftAttributes(array $attributes): array
    {
        $mapping = [
            'planSummary' => 'plan_summary',
            'implementationStrategy' => 'implementation_strategy',
            'expectedOutcome' => 'expected_outcome',
            'rootCauseResponse' => 'root_cause_response',
            'resourcesRequired' => 'resources_required',
            'dependencies' => 'dependencies',
            'risksAndConstraints' => 'risks_and_constraints',
            'plannedStartDate' => 'planned_start_date',
            'plannedTargetDate' => 'planned_target_date',
            'focalUserId' => 'focal_user_id',
        ];

        return collect($mapping)
            ->filter(fn (string $column, string $key): bool => array_key_exists($key, $attributes))
            ->mapWithKeys(fn (string $column, string $key): array => [
                $column => $attributes[$key],
            ])->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $milestones
     */
    private function validateDraft(
        CmsRecommendationCase $case,
        CmsActionPlanVersion $version,
        array $milestones,
    ): void {
        $errors = [];
        if ((int) $version->owner_office_id !== (int) $case->lead_responsible_office_id) {
            $errors['ownerOfficeId'][] = 'The Action Plan owner must be the lead responsible office.';
        }
        if ($version->focal_user_id) {
            $this->validateOfficeUser(
                (int) $version->focal_user_id,
                (int) $version->owner_office_id,
                'focalUserId',
                $errors,
            );
        }
        $planStart = $this->date($version->planned_start_date);
        $planTarget = $this->date($version->planned_target_date);
        if ($planStart && $planTarget && $planTarget->lt($planStart)) {
            $errors['plannedTargetDate'][] = 'The plan target date cannot precede its start date.';
        }
        $caseTarget = $this->date($case->effective_target_implementation_date);
        if ($caseTarget && $planTarget && $planTarget->gt($caseTarget)) {
            $errors['plannedTargetDate'][] =
                'The plan target date cannot exceed the effective recommendation target without an approved extension.';
        }

        $sequences = [];
        $weighted = 0;
        $weightTotal = 0.0;
        foreach ($milestones as $index => $milestone) {
            $sequence = (int) ($milestone['sequenceNumber'] ?? 0);
            if (in_array($sequence, $sequences, true)) {
                $errors["milestones.{$index}.sequenceNumber"][] =
                    'Milestone sequence numbers must be unique.';
            }
            $sequences[] = $sequence;
            if ((int) ($milestone['responsibleOfficeId'] ?? 0)
                !== (int) $version->owner_office_id) {
                $errors["milestones.{$index}.responsibleOfficeId"][] =
                    'CMS-3A milestones must remain with the Action Plan owner office.';
            }
            if (! empty($milestone['responsibleUserId'])) {
                $this->validateOfficeUser(
                    (int) $milestone['responsibleUserId'],
                    (int) $version->owner_office_id,
                    "milestones.{$index}.responsibleUserId",
                    $errors,
                );
            }
            $start = $this->date($milestone['plannedStartDate'] ?? null);
            $target = $this->date($milestone['plannedTargetDate'] ?? null);
            if ($start && $target && $target->lt($start)) {
                $errors["milestones.{$index}.plannedTargetDate"][] =
                    'The milestone target date cannot precede its start date.';
            }
            if ($planStart && $start && $start->lt($planStart)) {
                $errors["milestones.{$index}.plannedStartDate"][] =
                    'The milestone cannot start before the Action Plan.';
            }
            if ($planTarget && $target && $target->gt($planTarget)) {
                $errors["milestones.{$index}.plannedTargetDate"][] =
                    'The milestone target date must be within the Action Plan period.';
            }
            if (array_key_exists('weightPercentage', $milestone)
                && $milestone['weightPercentage'] !== null
                && $milestone['weightPercentage'] !== '') {
                $weighted++;
                $weightTotal += (float) $milestone['weightPercentage'];
            }
        }
        if ($weighted > 0 && $weighted !== count($milestones)) {
            $errors['milestones'][] =
                'When one milestone has a weight, every milestone must have a weight.';
        } elseif ($weighted > 0 && abs($weightTotal - 100.0) > 0.001) {
            $errors['milestones'][] = 'Milestone weights must total exactly 100%.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, list<string>> $errors */
    private function validateOfficeUser(
        int $userId,
        int $officeId,
        string $field,
        array &$errors,
    ): void {
        $user = User::withTrashed()->find($userId);
        if (! $user
            || ! $this->scope->isUsableAccount($user)
            || (int) $user->office_id !== $officeId) {
            $errors[$field][] =
                'Select an active, unlocked user assigned to the responsible office.';
        }
    }

    /** @param array<int, array<string, mixed>> $milestones */
    private function replaceMilestones(
        CmsActionPlanVersion $version,
        array $milestones,
    ): void {
        $version->milestones()->get()->each->delete();
        foreach ($milestones as $index => $milestone) {
            $version->milestones()->create([
                'sequence_number' => $milestone['sequenceNumber'],
                'title' => $milestone['title'],
                'description' => $milestone['description'] ?? null,
                'expected_output' => $milestone['expectedOutput'],
                'success_indicator' => $milestone['successIndicator'] ?? null,
                'verification_method' => $milestone['verificationMethod'] ?? null,
                'responsible_office_id' => $milestone['responsibleOfficeId'],
                'responsible_user_id' => $milestone['responsibleUserId'] ?? null,
                'planned_start_date' => $milestone['plannedStartDate'] ?? null,
                'planned_target_date' => $milestone['plannedTargetDate'],
                'weight_percentage' => $milestone['weightPercentage'] ?? null,
                'display_order' => $milestone['displayOrder'] ?? ($index + 1),
            ]);
        }
    }

    private function assertComplete(
        CmsRecommendationCase $case,
        CmsActionPlanVersion $version,
    ): void {
        $result = $this->completeness($case, $version);
        if (! $result['complete']) {
            throw ValidationException::withMessages($result['errors']);
        }
    }

    /** @return array<string, mixed> */
    private function snapshotFor(CmsActionPlanVersion $version): array
    {
        $version->loadMissing('milestones');

        return [
            'versionNumber' => $version->version_number,
            ...collect($this->copyContent($version))
                ->mapWithKeys(fn (mixed $value, string $key): array => [
                    $this->camel($key) => $value instanceof \DateTimeInterface
                        ? $value->format('Y-m-d')
                        : $value,
                ])->all(),
            'ownerOfficeId' => $version->owner_office_id,
            'focalUserId' => $version->focal_user_id,
            'milestones' => $this->milestonePayload($version->milestones),
        ];
    }

    private function assertSnapshotIntegrity(CmsActionPlanVersion $version): void
    {
        if (! $version->submission_snapshot
            || json_encode($version->submission_snapshot) !== json_encode($this->snapshotFor($version))) {
            throw ValidationException::withMessages([
                'version' => ['The submitted Action Plan snapshot is missing or no longer matches its content.'],
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function copyContent(CmsActionPlanVersion $version): array
    {
        return collect($version->getAttributes())->only([
            'plan_summary',
            'implementation_strategy',
            'expected_outcome',
            'root_cause_response',
            'resources_required',
            'dependencies',
            'risks_and_constraints',
            'planned_start_date',
            'planned_target_date',
        ])->all();
    }

    /** @param Collection<int, CmsActionPlanMilestone> $milestones */
    private function milestonePayload(Collection $milestones): array
    {
        return $milestones->map(fn (CmsActionPlanMilestone $milestone): array => [
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
        ])->values()->all();
    }

    private function assertCaseLock(CmsRecommendationCase $case, int $lockVersion): void
    {
        if ((int) $case->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['The CMS recommendation changed. Refresh before retrying.'],
            ]);
        }
    }

    private function assertVersionLock(CmsActionPlanVersion $version, int $lockVersion): void
    {
        if ((int) $version->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['The Action Plan version changed. Refresh before retrying.'],
            ]);
        }
    }

    private function assertStatus(CmsActionPlanVersion $version, string $status): void
    {
        if ($version->status_code !== $status) {
            throw ValidationException::withMessages([
                'status' => ["This action requires an Action Plan in {$status} status."],
            ]);
        }
    }

    private function assertActionableCase(CmsRecommendationCase $case): void
    {
        if (! in_array($case->status_code, [
            CmsRecommendationCase::STATUS_TRANSFERRED,
            CmsRecommendationCase::STATUS_FOR_ACTION_PLAN,
            CmsRecommendationCase::STATUS_MONITORING,
        ], true)) {
            throw ValidationException::withMessages([
                'recommendation' => ['This recommendation is not actionable.'],
            ]);
        }
    }

    private function assertOwnerOffice(
        CmsRecommendationCase $case,
        mixed $ownerOfficeId,
    ): void {
        if ($ownerOfficeId !== null
            && (int) $ownerOfficeId !== (int) $case->lead_responsible_office_id) {
            throw ValidationException::withMessages([
                'ownerOfficeId' => ['The owner office must match the lead responsible office.'],
            ]);
        }
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        return $value ? CarbonImmutable::parse($value)->startOfDay() : null;
    }

    /** @return list<string> */
    private function planRelations(): array
    {
        return [
            'case.recommendation',
            'case.leadResponsibleOffice',
            'case.currentAssignment.user',
            'ownerOffice',
            'creator',
            'acceptedVersion',
            'versions',
            ...array_map(
                fn (string $relation): string => "currentVersion.{$relation}",
                self::VERSION_RELATIONS,
            ),
            ...array_map(
                fn (string $relation): string => "acceptedVersion.{$relation}",
                self::VERSION_RELATIONS,
            ),
            ...array_map(
                fn (string $relation): string => "versions.{$relation}",
                self::VERSION_RELATIONS,
            ),
        ];
    }

    private function record(
        Request $request,
        CmsRecommendationCase $case,
        CmsCorrectiveActionPlan $plan,
        CmsActionPlanVersion $version,
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
            'actionPlanId' => $plan->id,
            'versionId' => $version->id,
            'versionNumber' => $version->version_number,
            'previousVersionStatus' => $previousVersionStatus,
            'newVersionStatus' => $newVersionStatus,
            'previousCaseStatus' => $previousCaseStatus,
            'newCaseStatus' => $newCaseStatus,
            'ownerOfficeId' => $version->owner_office_id,
            'focalUserId' => $version->focal_user_id,
            'milestoneCount' => $version->milestones()->count(),
            'actorId' => $request->user()->id,
            ...$extra,
        ];
        CmsRecommendationEvent::query()->create([
            'cms_recommendation_case_id' => $case->id,
            'cms_recommendation_id' => $case->cms_recommendation_id,
            'idempotency_key' => "cms-action-plan:{$version->id}:{$eventCode}:{$version->lock_version}",
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
            'description' => 'Updated the controlled CMS Corrective Action Plan workflow.',
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
            'auditable_type' => CmsCorrectiveActionPlan::class,
            'auditable_id' => $plan->id,
            'old_values' => [
                'versionStatus' => $previousVersionStatus,
                'caseStatus' => $previousCaseStatus,
            ],
            'new_values' => [
                'versionStatus' => $newVersionStatus,
                'caseStatus' => $newCaseStatus,
                'currentVersionId' => $plan->current_version_id,
                'acceptedVersionId' => $plan->accepted_version_id,
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
        int $planId,
        CmsActionPlanVersion $version,
    ): void {
        $version->loadMissing('plan.case.currentAssignment');
        $recipients = match ($transition) {
            'submitted' => $this->reviewerRecipients($version->plan->case),
            'review_started', 'returned' => collect([
                $version->submitted_by,
                $version->focal_user_id,
            ]),
            'accepted' => collect([
                $version->submitted_by,
                $version->focal_user_id,
                $version->plan->case->currentAssignment?->user_id,
            ]),
            default => collect(),
        };
        if ($transition === 'returned') {
            $officeRecipients = User::query()
                ->where('office_id', $version->owner_office_id)
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query
                        ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'access.auditee_scope'))
                        ->orWhereHas('role.permissions', fn ($permission) => $permission->where('code', 'access.auditee_scope'));
                })->pluck('id');
            $recipients = $recipients->merge($officeRecipients);
        }
        $labels = [
            'submitted' => ['ACTION_PLAN_SUBMITTED', 'Action Plan submitted', 'An Action Plan is ready for compliance review.'],
            'review_started' => ['ACTION_PLAN_REVIEW_STARTED', 'Action Plan review started', 'Compliance review of the submitted Action Plan has started.'],
            'returned' => ['ACTION_PLAN_RETURNED', 'Action Plan returned', 'The Action Plan was returned with instructions for a controlled revision.'],
            'accepted' => ['ACTION_PLAN_ACCEPTED', 'Action Plan accepted', 'The Action Plan was accepted as the official monitoring baseline.'],
        ];
        [$type, $title, $message] = $labels[$transition];
        if ($transition === 'returned' && filled($version->return_reason)) {
            $message = "Return instructions: {$version->return_reason}";
        }
        $this->notifications->send($recipients->filter()->unique(), [
            'actorId' => $actorId,
            'type' => "CMS_{$type}",
            'category' => 'WORKFLOW',
            'priority' => in_array($transition, ['returned', 'accepted'], true) ? 'HIGH' : 'NORMAL',
            'moduleCode' => 'CMS',
            'title' => $title,
            'message' => $message,
            'actionUrl' => "/compliance-management/recommendations/{$caseId}",
            'actionLabel' => 'Open recommendation',
            'subjectType' => CmsCorrectiveActionPlan::class,
            'subjectId' => $planId,
            'subjectCode' => sprintf('CAP-CMS-REC-%06d', $caseId),
            'dedupeKey' => "cms-action-plan:{$planId}:{$version->id}:{$transition}",
            'metadata' => [
                'caseId' => $caseId,
                'actionPlanId' => $planId,
                'versionId' => $version->id,
                'versionNumber' => $version->version_number,
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
            ->filter(fn (User $user): bool => $user->hasPermission('cms.action-plan.review')
                && $this->scope->canViewClassification(
                    $user,
                    $case->recommendation?->confidentiality_code_snapshot,
                ))
            ->pluck('id');

        return $recipients->merge($management)->filter()->unique()->values();
    }

    private function camel(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }
}
