<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Runs reusable workflow instances transactionally with role and SLA enforcement.
 */
class WorkflowEngine
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    public function scopeVisible(Builder $query, User $user): Builder
    {
        if ($user->hasGlobalOfficeAccess()) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->where('office_id', $user->office_id)
                ->orWhere('started_by', $user->id);
        });
    }

    /** @param array<string, mixed> $data */
    public function start(Request $request, array $data): WorkflowInstance
    {
        $definitionQuery = WorkflowDefinition::query();
        if (! empty($data['workflowDefinitionId'])) {
            // Explicit selection pins the instance to that published version.
            $definitionQuery->whereKey($data['workflowDefinitionId']);
        } else {
            // Module mappings let callers start the configured default without
            // embedding workflow database IDs in module-specific code.
            $module = strtoupper((string) ($data['moduleCode'] ?? ''));
            $workflowCode = $this->runtime->string('workflow_mapping_'.strtolower($module));
            if ($workflowCode === '') {
                throw ValidationException::withMessages([
                    'moduleCode' => ['No default workflow is configured for this module.'],
                ]);
            }
            $definitionQuery
                ->where('module_code', $module)
                ->where('code', $workflowCode)
                ->latest('version');
        }

        $definition = $definitionQuery
            ->where('status', 'PUBLISHED')
            ->where('is_active', true)
            ->with('steps')
            ->firstOrFail();
        $this->ensureOfficeAccess($request->user(), $data['officeId'] ?? null);
        $initial = $definition->steps->firstWhere('step_type', 'START');
        if (! $initial) {
            throw ValidationException::withMessages([
                'workflowDefinitionId' => ['This workflow has no valid START step.'],
            ]);
        }

        $duplicate = WorkflowInstance::query()
            ->where('workflow_definition_id', $definition->id)
            ->where('subject_code', $data['subjectCode'])
            ->where('status', 'ACTIVE')
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'subjectCode' => ['An active instance already exists for this workflow and subject.'],
            ]);
        }

        // Instance creation and the immutable START event commit together.
        return DB::transaction(function () use ($request, $data, $definition, $initial): WorkflowInstance {
            $now = now();
            $instance = WorkflowInstance::query()->create([
                'workflow_definition_id' => $definition->id,
                'current_step_id' => $initial->id,
                'module_code' => $definition->module_code,
                'subject_type' => $definition->subject_type,
                'subject_id' => $data['subjectId'] ?? null,
                'subject_code' => $data['subjectCode'],
                'subject_label' => $data['subjectLabel'],
                'office_id' => $data['officeId'] ?? null,
                'status' => 'ACTIVE',
                'context' => $data['context'] ?? null,
                'started_by' => $request->user()->id,
                'started_at' => $now,
                'step_entered_at' => $now,
                'due_at' => $initial->sla_hours ? $now->copy()->addHours($initial->sla_hours) : null,
                'lock_version' => 0,
            ]);
            $this->event($request, $instance, null, null, $initial, 'START', null, [], $this->snapshot($instance));
            $this->log($request, 'workflow.instance_started', $instance, null, $this->snapshot($instance));
            $this->notifications->notifyWorkflowStep($instance, $request->user(), 'Started');

            return $instance;
        }, 3);
    }

    public function transition(
        Request $request,
        WorkflowInstance $instance,
        string $action,
        int $lockVersion,
        ?string $comment,
    ): WorkflowInstance {
        $this->ensureVisible($request->user(), $instance);

        return DB::transaction(function () use (
            $request,
            $instance,
            $action,
            $lockVersion,
            $comment,
        ): WorkflowInstance {
            $locked = WorkflowInstance::query()->whereKey($instance->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    'workflow' => ['Only an active workflow instance can be advanced.'],
                ]);
            }
            if ($locked->lock_version !== $lockVersion) {
                throw ValidationException::withMessages([
                    'lockVersion' => ['This workflow changed in another session. Refresh and try again.'],
                ]);
            }
            $transition = WorkflowTransition::query()
                ->where('workflow_definition_id', $locked->workflow_definition_id)
                ->where('from_step_id', $locked->current_step_id)
                ->where('code', strtoupper($action))
                ->where('is_active', true)
                ->with(['fromStep', 'toStep', 'actorRole', 'requiredPermission'])
                ->first();
            if (! $transition) {
                throw ValidationException::withMessages([
                    'action' => ['This action is not available from the current workflow step.'],
                ]);
            }
            $this->authorizeTransition($request->user(), $locked, $transition, $comment);

            $old = $this->snapshot($locked);
            $now = now();
            $terminal = $transition->toStep->step_type === 'END';
            $locked->update([
                'current_step_id' => $transition->to_step_id,
                'status' => $terminal ? 'COMPLETED' : 'ACTIVE',
                'step_entered_at' => $now,
                'due_at' => ! $terminal && $transition->toStep->sla_hours
                    ? $now->copy()->addHours($transition->toStep->sla_hours)
                    : null,
                'completed_by' => $terminal ? $request->user()->id : null,
                'completed_at' => $terminal ? $now : null,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $new = $this->snapshot($locked);
            $this->event(
                $request,
                $locked,
                $transition,
                $transition->fromStep,
                $transition->toStep,
                $transition->code,
                $comment,
                $old,
                $new,
            );
            $this->log($request, 'workflow.transitioned', $locked, $old, $new, [
                'transitionCode' => $transition->code,
                'comment' => $comment,
            ]);
            $this->notifications->notifyWorkflowStep(
                $locked,
                $request->user(),
                $transition->name,
            );

            return $locked;
        }, 3);
    }

    public function cancel(
        Request $request,
        WorkflowInstance $instance,
        int $lockVersion,
        string $comment,
    ): WorkflowInstance {
        $this->ensureVisible($request->user(), $instance);

        return DB::transaction(function () use ($request, $instance, $lockVersion, $comment): WorkflowInstance {
            $locked = WorkflowInstance::query()->whereKey($instance->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'ACTIVE' || $locked->lock_version !== $lockVersion) {
                throw ValidationException::withMessages([
                    'lockVersion' => ['The active workflow changed. Refresh and try again.'],
                ]);
            }
            $old = $this->snapshot($locked);
            $locked->update([
                'status' => 'CANCELLED',
                'completed_by' => $request->user()->id,
                'completed_at' => now(),
                'due_at' => null,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $new = $this->snapshot($locked);
            $this->event(
                $request,
                $locked,
                null,
                $locked->currentStep,
                $locked->currentStep,
                'CANCEL',
                $comment,
                $old,
                $new,
            );
            $this->log($request, 'workflow.cancelled', $locked, $old, $new, ['comment' => $comment]);
            $this->notifications->notifyWorkflowStep($locked, $request->user(), 'Cancelled');

            return $locked;
        }, 3);
    }

    public function ensureVisible(User $user, WorkflowInstance $instance): void
    {
        if (! $this->scopeVisible(WorkflowInstance::query()->whereKey($instance->id), $user)->exists()) {
            abort(403, 'This workflow instance is outside your office access scope.');
        }
    }

    /** @return list<WorkflowTransition> */
    public function availableTransitions(User $user, WorkflowInstance $instance): array
    {
        if ($instance->status !== 'ACTIVE') {
            return [];
        }

        return WorkflowTransition::query()
            ->where('workflow_definition_id', $instance->workflow_definition_id)
            ->where('from_step_id', $instance->current_step_id)
            ->where('is_active', true)
            ->with(['toStep', 'actorRole', 'requiredPermission'])
            ->orderBy('sequence')
            ->get()
            ->filter(fn (WorkflowTransition $transition): bool => $this->canAct($user, $instance, $transition))
            ->values()
            ->all();
    }

    private function authorizeTransition(
        User $user,
        WorkflowInstance $instance,
        WorkflowTransition $transition,
        ?string $comment,
    ): void {
        if (! $this->canAct($user, $instance, $transition)) {
            abort(403, 'Your permissions do not allow this workflow action.');
        }
        if ($transition->requires_comment && blank($comment)) {
            throw ValidationException::withMessages([
                'comment' => ['A comment is required for this workflow action.'],
            ]);
        }
        if ($transition->enforce_separation_of_duties && $instance->started_by === $user->id) {
            throw ValidationException::withMessages([
                'action' => ['The workflow initiator cannot perform this action.'],
            ]);
        }
    }

    private function canAct(
        User $user,
        WorkflowInstance $instance,
        WorkflowTransition $transition,
    ): bool {
        if (! $transition->requiredPermission) {
            return false;
        }
        if ($transition->requiredPermission
            && ! $user->hasPermission($transition->requiredPermission->code)) {
            return false;
        }
        if ($transition->enforce_separation_of_duties && $instance->started_by === $user->id) {
            return false;
        }

        return true;
    }

    private function ensureOfficeAccess(User $user, ?int $officeId): void
    {
        if ($officeId && ! $user->hasGlobalOfficeAccess() && (int) $user->office_id !== $officeId) {
            abort(403, 'You cannot start a workflow for another office.');
        }
    }

    private function event(
        Request $request,
        WorkflowInstance $instance,
        ?WorkflowTransition $transition,
        ?WorkflowStep $from,
        WorkflowStep $to,
        string $action,
        ?string $comment,
        array $old,
        array $new,
    ): void {
        $instance->events()->create([
            'workflow_transition_id' => $transition?->id,
            'from_step_id' => $from?->id,
            'to_step_id' => $to->id,
            'actor_id' => $request->user()->id,
            'action_code' => $action,
            'comment' => $comment,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);
    }

    private function log(
        Request $request,
        string $action,
        WorkflowInstance $instance,
        ?array $old,
        array $new,
        array $metadata = [],
    ): void {
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => WorkflowInstance::class,
            'auditable_id' => $instance->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ]);
        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'description' => "{$action}: {$instance->subject_code} — {$instance->subject_label}",
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => ['module' => $instance->module_code, ...$metadata],
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(WorkflowInstance $instance): array
    {
        $instance->loadMissing('currentStep:id,code,name');

        return [
            'status' => $instance->status,
            'currentStepId' => $instance->current_step_id,
            'currentStepCode' => $instance->currentStep->code,
            'currentStepName' => $instance->currentStep->name,
            'dueAt' => $instance->due_at?->toISOString(),
            'lockVersion' => $instance->lock_version,
        ];
    }
}
