<?php

namespace App\Services;

use App\Models\SiapObjective;
use App\Models\SiapPriority;
use App\Models\SiapWorkflowEvent;
use App\Models\StrategicInternalAuditPlan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies strategic-plan state transitions, revision rules, and workflow history.
 */
class SiapWorkflowService
{
    public function __construct(
        private readonly SiapPlanGuard $guard,
        private readonly IapSupport $support,
        private readonly IapBaicsIntegrationService $baicsIntegrations,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    /** @return array{complete: bool, errors: list<string>} */
    public function completeness(StrategicInternalAuditPlan $plan): array
    {
        $plan->loadMissing(['objectives.auditAreas', 'priorities']);
        $errors = [];

        if (blank($plan->expected_outcomes)) {
            $errors[] = 'Overall expected outcomes are required.';
        }
        if ($plan->objectives->isEmpty()) {
            $errors[] = 'At least one strategic objective is required.';
        }
        foreach ($plan->objectives as $objective) {
            if ($objective->auditAreas->isEmpty()) {
                $errors[] = "Objective {$objective->objective_code} must link at least one audit area.";
            }
            if (blank($objective->expected_outcome)) {
                $errors[] = "Objective {$objective->objective_code} requires an expected outcome.";
            }
        }
        if ($plan->priorities->isEmpty()) {
            $errors[] = 'At least one audit priority or theme is required.';
        }

        return ['complete' => $errors === [], 'errors' => $errors];
    }

    public function transition(
        Request $request,
        StrategicInternalAuditPlan $plan,
        string $action,
        int $lockVersion,
        ?string $comment,
        bool $completionConfirmed,
    ): StrategicInternalAuditPlan {
        $action = strtolower($action);
        $definition = $this->transitionDefinition($action);
        if (! $request->user()->hasPermission($definition['permission'])) {
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
        ): StrategicInternalAuditPlan {
            $locked = StrategicInternalAuditPlan::query()
                ->lockForUpdate()
                ->findOrFail($plan->id);
            $this->guard->assertCanView($request->user(), $locked);
            $this->guard->assertLockVersion($locked, $lockVersion);

            if (! in_array($locked->status, $definition['from'], true)) {
                throw ValidationException::withMessages([
                    'action' => [
                        "The {$action} action is not allowed while the strategic plan is {$locked->status}.",
                    ],
                ]);
            }
            if ($definition['management']) {
                $this->guard->assertManagement($request->user());
            }
            if ($definition['comment'] && blank($comment)) {
                throw ValidationException::withMessages([
                    'comment' => ["A comment is required to {$action} this strategic plan."],
                ]);
            }
            if ($action === 'approve' && $locked->submitted_by === $request->user()->id) {
                throw ValidationException::withMessages([
                    'approver' => ['The user who submitted the strategic plan cannot approve it.'],
                ]);
            }
            if ($action === 'approve' && $this->runtime->boolean('baics_integration_required')) {
                $this->baicsIntegrations->assertStrategicPlanReady($locked);
            }
            if ($action === 'complete' && ! $completionConfirmed) {
                throw ValidationException::withMessages([
                    'completionConfirmed' => [
                        'Confirm that the strategic planning period is complete.',
                    ],
                ]);
            }
            if (in_array($action, ['submit', 'resubmit'], true)) {
                $check = $this->completeness($locked);
                if (! $check['complete']) {
                    throw ValidationException::withMessages(['plan' => $check['errors']]);
                }
            }

            $from = $locked->status;
            $to = $definition['to'];
            $updates = [
                'status' => $to,
                'lock_version' => $locked->lock_version + 1,
            ];
            $now = now();
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
            $this->event($request, $locked, strtoupper($action), $from, $to, $comment);
            $this->support->audit(
                $request,
                "iap.siap.{$action}",
                $locked,
                ['status' => $from, 'lock_version' => $lockVersion],
                ['status' => $to, 'lock_version' => $locked->lock_version],
            );

            return $locked;
        }, 3);
    }

    public function createRevision(
        Request $request,
        StrategicInternalAuditPlan $plan,
        int $lockVersion,
        string $reason,
    ): StrategicInternalAuditPlan {
        $this->guard->assertManagement($request->user());
        if (! in_array($plan->status, ['APPROVED', 'ACTIVE'], true)) {
            throw ValidationException::withMessages([
                'status' => [
                    'Only an approved or active strategic plan can be formally revised.',
                ],
            ]);
        }

        return DB::transaction(function () use (
            $request,
            $plan,
            $lockVersion,
            $reason,
        ): StrategicInternalAuditPlan {
            $source = StrategicInternalAuditPlan::query()
                ->with(['objectives.auditAreas', 'priorities'])
                ->lockForUpdate()
                ->findOrFail($plan->id);
            $this->guard->assertLockVersion($source, $lockVersion);

            $nextRevision = (int) StrategicInternalAuditPlan::withTrashed()
                ->where('start_year', $source->start_year)
                ->where('end_year', $source->end_year)
                ->max('revision_number') + 1;
            $code = sprintf(
                'SIAP-%d-%d-R%02d',
                $source->start_year,
                $source->end_year,
                $nextRevision,
            );

            $source->forceFill([
                'is_current_revision' => false,
                'lock_version' => $source->lock_version + 1,
            ])->save();

            $revision = StrategicInternalAuditPlan::query()->create([
                ...$source->only([
                    'start_year',
                    'end_year',
                    'title',
                    'strategic_context',
                    'vision',
                    'mission_alignment',
                    'planning_methodology',
                    'expected_outcomes',
                    'prepared_by',
                    'coordinator_id',
                ]),
                'plan_code' => $code,
                'status' => 'DRAFT',
                'revision_number' => $nextRevision,
                'supersedes_plan_id' => $source->id,
                'is_current_revision' => true,
                'lock_version' => 1,
                'is_active' => true,
            ]);

            foreach ($source->objectives as $objective) {
                $clone = SiapObjective::query()->create([
                    ...$objective->only([
                        'objective_code',
                        'title',
                        'description',
                        'expected_outcome',
                        'display_order',
                    ]),
                    'strategic_plan_id' => $revision->id,
                ]);
                $clone->auditAreas()->sync($objective->auditAreas->pluck('id'));
            }
            foreach ($source->priorities as $priority) {
                SiapPriority::query()->create([
                    ...$priority->only([
                        'priority_code',
                        'title',
                        'theme',
                        'description',
                        'expected_outcome',
                        'display_order',
                    ]),
                    'strategic_plan_id' => $revision->id,
                ]);
            }

            $this->event(
                $request,
                $source,
                'CREATE_REVISION',
                $source->status,
                $source->status,
                $reason,
                ['revision_plan_id' => $revision->id],
            );
            $this->event(
                $request,
                $revision,
                'CREATE',
                null,
                'DRAFT',
                $reason,
                ['source_plan_id' => $source->id],
            );
            $this->support->audit(
                $request,
                'iap.siap.revision_created',
                $revision,
                null,
                $revision->toArray(),
                ['source_plan_id' => $source->id, 'reason' => $reason],
            );

            return $revision;
        }, 3);
    }

    /** @return array<string, mixed> */
    private function transitionDefinition(string $action): array
    {
        return match ($action) {
            'submit' => [
                'permission' => 'iap.submit',
                'from' => ['DRAFT'],
                'to' => 'PENDING_REVIEW',
                'management' => false,
                'comment' => false,
            ],
            'resubmit' => [
                'permission' => 'iap.submit',
                'from' => ['RETURNED_FOR_REVISION'],
                'to' => 'RESUBMITTED',
                'management' => false,
                'comment' => false,
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
                'comment' => false,
            ],
            'activate' => [
                'permission' => 'iap.activate',
                'from' => ['APPROVED'],
                'to' => 'ACTIVE',
                'management' => true,
                'comment' => false,
            ],
            'complete' => [
                'permission' => 'iap.complete',
                'from' => ['ACTIVE'],
                'to' => 'COMPLETED',
                'management' => true,
                'comment' => true,
            ],
            default => throw ValidationException::withMessages([
                'action' => ['Unknown SIAP workflow action.'],
            ]),
        };
    }

    /** @param array<string, mixed>|null $metadata */
    public function event(
        Request $request,
        StrategicInternalAuditPlan $plan,
        string $action,
        ?string $fromStatus,
        string $toStatus,
        ?string $comment,
        ?array $metadata = null,
    ): void {
        SiapWorkflowEvent::query()->create([
            'strategic_plan_id' => $plan->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_id' => $request->user()->id,
            'actor_role_code' => $request->user()->role->code,
            'comment' => $comment,
            'plan_lock_version' => $plan->lock_version,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ]);
    }
}
