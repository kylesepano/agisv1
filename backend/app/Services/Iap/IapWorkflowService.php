<?php

namespace App\Services;

use App\Models\IapComment;
use App\Models\IapEngagementSkillRequirement;
use App\Models\IapEngagementTeamMember;
use App\Models\IapPlanEngagement;
use App\Models\IapRiskAssessment;
use App\Models\IapRiskAssessmentScore;
use App\Models\IapScheduleEvent;
use App\Models\IapWorkflowEvent;
use App\Models\InternalAuditPlan;
use App\Models\MasterListItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Executes Annual Plan lifecycle transitions and records their immutable history.
 */
class IapWorkflowService
{
    public function __construct(
        private readonly IapPlanGuard $guard,
        private readonly IapSupport $support,
        private readonly IapBaicsIntegrationService $baicsIntegrations,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    /** @return array{complete: bool, errors: list<string>} */
    public function completeness(InternalAuditPlan $plan): array
    {
        $plan->load([
            'riskAssessments.scores',
            'prioritizationRun.items.riskAssessment',
            'engagements.offices',
            'engagements.auditAreas',
            'engagements.prioritizationItem.riskAssessment',
            'engagements.teamMembers.teamRole',
        ]);
        $errors = [];

        foreach ([
            'title' => 'Plan title',
            'overall_objective' => 'Overall objective',
            'overall_scope' => 'Overall scope',
        ] as $field => $label) {
            if (blank($plan->{$field})) {
                $errors[] = "{$label} is required.";
            }
        }

        if ($plan->prioritization_run_id === null && $plan->riskAssessments->isEmpty()) {
            $errors[] = 'At least one risk assessment is required.';
        }

        if ($plan->prioritization_run_id !== null) {
            if (! $plan->prioritizationRun
                || $plan->prioritizationRun->status !== 'FINALIZED'
                || ! $plan->prioritizationRun->is_active
                || $plan->prioritizationRun->trashed()) {
                $errors[] = 'The connected prioritization must be active and finalized.';
            }
        }

        foreach ($plan->riskAssessments as $risk) {
            if ($risk->scores->isEmpty()
                || abs((float) $risk->scores->sum('criterion_weight') - 100.0) > 0.01
                || $risk->final_risk_level_id === null) {
                $errors[] = "Risk assessment #{$risk->id} must have complete scores totaling 100 percent.";
            }
        }

        if ($plan->engagements->isEmpty()) {
            $errors[] = 'At least one proposed audit engagement is required.';
        }

        foreach ($plan->engagements as $engagement) {
            $prefix = "Engagement {$engagement->engagement_code}";
            if ($engagement->prioritization_item_id !== null) {
                $source = $engagement->prioritizationItem;
                if (! $source
                    || $source->prioritization_run_id !== $plan->prioritization_run_id
                    || $source->decision !== 'SELECTED'
                    || ! in_array(
                        $source->riskAssessment?->status,
                        ['VALIDATED', 'LOCKED'],
                        true,
                    )) {
                    $errors[] = "{$prefix} must retain a selected, validated assessment from the finalized prioritization.";
                }
            }
            if ($engagement->offices->isEmpty() || $engagement->auditAreas->isEmpty()) {
                $errors[] = "{$prefix} must cover at least one office and audit area.";
            }
            if ($engagement->planned_start_date->lt($plan->planning_period_start)
                || $engagement->planned_end_date->gt($plan->planning_period_end)) {
                $errors[] = "{$prefix} dates must fall within the plan period.";
            }

            $roles = $engagement->teamMembers->pluck('teamRole.code');
            if (! $roles->contains('LEAD_AUDITOR') || ! $roles->contains('REVIEWER')) {
                $errors[] = "{$prefix} requires a Lead Auditor and Reviewer.";
            }
            if (abs(
                (float) $engagement->teamMembers->sum('planned_person_days')
                - (float) $engagement->estimated_person_days
            ) > 0.01) {
                $errors[] = "{$prefix} team person-days must equal its estimate.";
            }
        }

        return ['complete' => $errors === [], 'errors' => $errors];
    }

    public function transition(
        Request $request,
        InternalAuditPlan $plan,
        string $action,
        int $lockVersion,
        ?string $comment,
        bool $completionConfirmed,
    ): InternalAuditPlan {
        $action = strtolower($action);
        $definition = $this->transitionDefinition($action);
        $permission = $definition['permission'];
        if (! $request->user()->hasPermission($permission)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use (
            $request,
            $plan,
            $action,
            $definition,
            $lockVersion,
            $comment,
            $completionConfirmed,
        ): InternalAuditPlan {
            $locked = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->guard->assertCanView($request->user(), $locked);
            $this->guard->assertLockVersion($locked, $lockVersion);

            if (! in_array($locked->status, $definition['from'], true)) {
                throw ValidationException::withMessages([
                    'action' => ["The {$action} action is not allowed while the plan is {$locked->status}."],
                ]);
            }

            if ($definition['management']) {
                $this->guard->assertManagement($request->user());
            }
            if ($definition['comment'] && blank($comment)) {
                throw ValidationException::withMessages([
                    'comment' => ["A comment is required to {$action} this plan."],
                ]);
            }
            if ($action === 'approve' && $locked->submitted_by === $request->user()->id) {
                throw ValidationException::withMessages([
                    'approver' => ['The user who submitted the plan cannot approve it.'],
                ]);
            }
            if ($action === 'approve' && $this->runtime->boolean('baics_integration_required')) {
                $this->baicsIntegrations->assertAnnualPlanReady($locked);
            }
            if ($action === 'complete' && ! $completionConfirmed) {
                throw ValidationException::withMessages([
                    'completionConfirmed' => ['Confirm that all planned engagements have been completed.'],
                ]);
            }
            if (in_array($action, ['submit', 'resubmit'], true)) {
                $check = $this->completeness($locked);
                if (! $check['complete']) {
                    throw ValidationException::withMessages([
                        'plan' => $check['errors'],
                    ]);
                }
            }

            $oldValues = $this->workflowSnapshot($locked);
            $from = $locked->status;
            $to = $definition['to'];
            $now = now();
            $updates = [
                'status' => $to,
                'lock_version' => $locked->lock_version + 1,
            ];

            match ($action) {
                'submit', 'resubmit' => $updates += [
                    'submitted_at' => $now,
                    'submitted_by' => $request->user()->id,
                ],
                'approve' => $updates += [
                    'approved_at' => $now,
                    'approved_by' => $request->user()->id,
                ],
                'activate' => $updates += [
                    'activated_at' => $now,
                    'activated_by' => $request->user()->id,
                ],
                'complete' => $updates += [
                    'completed_at' => $now,
                    'completed_by' => $request->user()->id,
                ],
                default => null,
            };

            $locked->forceFill($updates)->save();
            $this->event(
                $request,
                $locked,
                strtoupper($action),
                $from,
                $to,
                $comment,
                null,
                $oldValues,
                $this->workflowSnapshot($locked),
            );
            if ($comment !== null && trim($comment) !== '') {
                $this->comment($request->user(), $locked, $action, $comment);
            }
            $this->support->audit(
                $request,
                "iap.plan.{$action}",
                $locked,
                ['status' => $from, 'lock_version' => $lockVersion],
                ['status' => $to, 'lock_version' => $locked->lock_version],
            );

            return $locked;
        }, 3);
    }

    public function createRevision(
        Request $request,
        InternalAuditPlan $plan,
        int $lockVersion,
        string $reason,
    ): InternalAuditPlan {
        $this->guard->assertManagement($request->user());
        if (! in_array($plan->status, ['APPROVED', 'ACTIVE'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only an approved or active plan can be formally revised.'],
            ]);
        }

        return DB::transaction(function () use ($request, $plan, $lockVersion, $reason): InternalAuditPlan {
            $source = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->guard->assertLockVersion($source, $lockVersion);
            if (! in_array($source->status, ['APPROVED', 'ACTIVE'], true)
                || ! $source->is_current_revision) {
                throw ValidationException::withMessages([
                    'status' => [
                        'Only the current approved or active version can start a formal revision.',
                    ],
                ]);
            }

            $nextRevision = (int) InternalAuditPlan::withTrashed()
                ->where('fiscal_year', $source->fiscal_year)
                ->max('revision_number') + 1;
            $newCode = sprintf('IAP-%d-R%02d', $source->fiscal_year, $nextRevision);
            $source->forceFill([
                'is_current_revision' => false,
                'lock_version' => $source->lock_version + 1,
            ])->save();

            $revision = InternalAuditPlan::query()->create([
                ...$source->only([
                    'fiscal_year',
                    'planning_period_type_id',
                    'prioritization_run_id',
                    'planning_period_start',
                    'planning_period_end',
                    'title',
                    'executive_summary',
                    'planning_methodology',
                    'overall_objective',
                    'overall_scope',
                    'limitations',
                    'prepared_by',
                    'coordinator_id',
                ]),
                'plan_code' => $newCode,
                'status' => 'DRAFT',
                'revision_number' => $nextRevision,
                'supersedes_plan_id' => $source->id,
                'is_current_revision' => true,
                'lock_version' => 1,
                'is_active' => true,
            ]);

            $riskMap = [];
            foreach ($source->riskAssessments()->with('scores')->get() as $risk) {
                $clone = IapRiskAssessment::query()->create([
                    ...$risk->only([
                        'office_id',
                        'audit_area_id',
                        'assessed_by',
                        'assessment_date',
                        'last_audit_date',
                        'inherent_risk_notes',
                        'control_environment_notes',
                        'total_weighted_score',
                        'calculated_risk_level_id',
                        'override_risk_level_id',
                        'override_reason',
                        'final_risk_level_id',
                        'justification',
                    ]),
                    'plan_id' => $revision->id,
                    'lock_version' => 1,
                ]);
                $riskMap[$risk->id] = $clone->id;

                foreach ($risk->scores as $score) {
                    IapRiskAssessmentScore::query()->create([
                        ...$score->only([
                            'risk_criterion_id',
                            'criterion_weight',
                            'rating',
                            'weighted_score',
                            'comment',
                        ]),
                        'risk_assessment_id' => $clone->id,
                    ]);
                }
            }

            foreach ($source->engagements()
                ->with([
                    'offices:id',
                    'auditAreas:id',
                    'auditFocuses:id',
                    'teamMembers',
                    'skillRequirements',
                ])
                ->get() as $engagement) {
                $clone = IapPlanEngagement::query()->create([
                    ...$engagement->only([
                        'engagement_code',
                        'title',
                        'engagement_type_id',
                        'audit_approach_id',
                        'priority_id',
                        'risk_level_id',
                        'prioritization_item_id',
                        'audit_universe_item_id',
                        'universe_risk_assessment_id',
                        'source_inherent_risk_score',
                        'source_residual_risk_score',
                        'source_priority_score',
                        'source_risk_level_code',
                        'source_decision',
                        'source_final_rank',
                        'target_quarter',
                        'imported_at',
                        'imported_by',
                        'background',
                        'objectives',
                        'scope',
                        'exclusions',
                        'audit_criteria',
                        'proposed_methodology',
                        'planned_start_date',
                        'planned_end_date',
                        'expected_report_date',
                        'schedule_status',
                        'scheduled_at',
                        'scheduled_by',
                        'last_rescheduled_at',
                        'last_rescheduled_by',
                        'last_reschedule_reason',
                        'cancelled_at',
                        'cancelled_by',
                        'cancellation_reason',
                        'estimated_person_days',
                        'estimated_cost',
                        'sequence_number',
                        'planning_notes',
                        'is_active',
                    ]),
                    'plan_id' => $revision->id,
                    'risk_assessment_id' => $engagement->risk_assessment_id
                        ? ($riskMap[$engagement->risk_assessment_id] ?? null)
                        : null,
                    'aem_engagement_id' => null,
                ]);
                $clone->offices()->sync($engagement->offices->pluck('id'));
                $clone->auditAreas()->sync($engagement->auditAreas->pluck('id'));
                $clone->auditFocuses()->sync($engagement->auditFocuses->pluck('id'));

                foreach ($engagement->teamMembers as $member) {
                    IapEngagementTeamMember::query()->create([
                        ...$member->only([
                            'user_id',
                            'team_role_id',
                            'planned_person_days',
                            'assignment_notes',
                        ]),
                        'plan_engagement_id' => $clone->id,
                    ]);
                }

                foreach ($engagement->skillRequirements as $requirement) {
                    IapEngagementSkillRequirement::query()->create([
                        ...$requirement->only([
                            'specialization_id',
                            'minimum_auditors',
                            'minimum_proficiency',
                            'notes',
                        ]),
                        'plan_engagement_id' => $clone->id,
                    ]);
                }

                if ($clone->schedule_status !== 'UNSCHEDULED') {
                    $team = $clone->teamMembers()
                        ->with(['user:id,employee_id,name', 'teamRole:id,code,label'])
                        ->get()
                        ->map(fn ($member) => [
                            'userId' => $member->user_id,
                            'employeeId' => $member->user?->employee_id,
                            'name' => $member->user?->name,
                            'teamRoleId' => $member->team_role_id,
                            'teamRoleCode' => $member->teamRole?->code,
                            'plannedPersonDays' => (float) $member->planned_person_days,
                        ])->all();
                    IapScheduleEvent::query()->create([
                        'plan_engagement_id' => $clone->id,
                        'action' => 'REVISION_CARRY_FORWARD',
                        'from_status' => $engagement->schedule_status,
                        'to_status' => $clone->schedule_status,
                        'old_start_date' => $engagement->planned_start_date,
                        'old_end_date' => $engagement->planned_end_date,
                        'old_expected_report_date' => $engagement->expected_report_date,
                        'new_start_date' => $clone->planned_start_date,
                        'new_end_date' => $clone->planned_end_date,
                        'new_expected_report_date' => $clone->expected_report_date,
                        'old_team' => $team,
                        'new_team' => $team,
                        'reason' => "Carried forward from approved plan {$source->plan_code}: {$reason}",
                        'actor_id' => $request->user()->id,
                    ]);
                }
            }

            foreach ($source->attachments()->get() as $attachment) {
                $revision->attachments()->create([
                    ...$attachment->only([
                        'document_id',
                        'attachment_type_id',
                        'display_name',
                        'visibility',
                        'uploaded_by',
                    ]),
                    'plan_engagement_id' => null,
                    'risk_assessment_id' => null,
                ]);
            }

            $this->comment($request->user(), $revision, 'revision', $reason);
            $this->event(
                $request,
                $revision,
                'CREATE_REVISION',
                $source->status,
                'DRAFT',
                $reason,
                ['source_plan_id' => $source->id, 'source_revision' => $source->revision_number],
                $this->workflowSnapshot($source),
                $this->workflowSnapshot($revision),
            );
            $this->support->audit(
                $request,
                'iap.plan.revision_created',
                $revision,
                null,
                $revision->toArray(),
                ['source_plan_id' => $source->id],
            );

            return $revision;
        }, 3);
    }

    /** @return array{permission: string, from: list<string>, to: string, management: bool, comment: bool} */
    private function transitionDefinition(string $action): array
    {
        return match ($action) {
            'submit' => [
                'permission' => 'iap.submit',
                'from' => ['DRAFT'],
                'to' => 'PENDING_REVIEW',
                'management' => false,
                'comment' => true,
            ],
            'resubmit' => [
                'permission' => 'iap.submit',
                'from' => ['RETURNED_FOR_REVISION'],
                'to' => 'RESUBMITTED',
                'management' => false,
                'comment' => true,
            ],
            'return' => [
                'permission' => 'iap.review',
                'from' => ['PENDING_REVIEW', 'RESUBMITTED'],
                'to' => 'RETURNED_FOR_REVISION',
                'management' => true,
                'comment' => true,
            ],
            'approve' => [
                'permission' => 'iap.approve',
                'from' => ['PENDING_REVIEW', 'RESUBMITTED'],
                'to' => 'APPROVED',
                'management' => true,
                'comment' => true,
            ],
            'reject' => [
                'permission' => 'iap.review',
                'from' => ['PENDING_REVIEW', 'RESUBMITTED'],
                'to' => 'REJECTED',
                'management' => true,
                'comment' => true,
            ],
            'activate' => [
                'permission' => 'iap.activate',
                'from' => ['APPROVED'],
                'to' => 'ACTIVE',
                'management' => true,
                'comment' => true,
            ],
            'complete' => [
                'permission' => 'iap.complete',
                'from' => ['ACTIVE'],
                'to' => 'COMPLETED',
                'management' => true,
                'comment' => true,
            ],
            default => throw ValidationException::withMessages([
                'action' => ['Unknown IAP workflow action.'],
            ]),
        };
    }

    private function comment(User $actor, InternalAuditPlan $plan, string $action, string $body): void
    {
        $typeCode = match ($action) {
            'return' => 'RETURN_INSTRUCTION',
            'approve' => 'APPROVAL_NOTE',
            'revision' => 'REVISION_EXPLANATION',
            default => 'MANAGEMENT',
        };
        $type = MasterListItem::query()
            ->where('code', $typeCode)
            ->whereHas('masterList', fn ($query) => $query->where('code', 'IAP_COMMENT_TYPE'))
            ->firstOrFail();

        IapComment::query()->create([
            'plan_id' => $plan->id,
            'author_id' => $actor->id,
            'comment_type_id' => $type->id,
            'visibility' => 'INTERNAL',
            'body' => trim($body),
            'is_immutable' => true,
        ]);
    }

    /** @param array<string, mixed>|null $metadata */
    private function event(
        Request $request,
        InternalAuditPlan $plan,
        string $action,
        ?string $from,
        string $to,
        ?string $comment,
        ?array $metadata = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        IapWorkflowEvent::query()->create([
            'plan_id' => $plan->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $request->user()->id,
            'actor_role_code' => $request->user()->role->code,
            'comment' => $comment,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'plan_lock_version' => $plan->lock_version,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ]);
    }

    /** @return array<string, mixed> */
    private function workflowSnapshot(InternalAuditPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'planCode' => $plan->plan_code,
            'status' => $plan->status,
            'revisionNumber' => $plan->revision_number,
            'isCurrentRevision' => $plan->is_current_revision,
            'submittedAt' => $plan->submitted_at?->toISOString(),
            'submittedBy' => $plan->submitted_by,
            'approvedAt' => $plan->approved_at?->toISOString(),
            'approvedBy' => $plan->approved_by,
            'activatedAt' => $plan->activated_at?->toISOString(),
            'activatedBy' => $plan->activated_by,
            'completedAt' => $plan->completed_at?->toISOString(),
            'completedBy' => $plan->completed_by,
            'lockVersion' => $plan->lock_version,
        ];
    }
}
