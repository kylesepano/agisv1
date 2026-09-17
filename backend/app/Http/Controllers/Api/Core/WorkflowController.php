<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\WorkflowActionRequest;
use App\Http\Requests\Core\WorkflowDefinitionRequest;
use App\Http\Requests\Core\WorkflowInstanceRequest;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceEvent;
use App\Models\WorkflowTransition;
use App\Services\WorkflowDefinitionService;
use App\Services\WorkflowEngine;
use App\Services\WorkflowAuthorityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Configures reusable workflows and executes their active instances and actions.
 */
class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowDefinitionService $definitions,
        private readonly WorkflowEngine $engine,
        private readonly WorkflowAuthorityService $authority,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $definitions = WorkflowDefinition::query()
            ->when($request->boolean('include_archived'), fn ($query) => $query->withTrashed())
            ->with($this->definitionRelations())
            ->withCount([
                'instances',
                'instances as active_instances_count' => fn ($query) => $query->where('status', 'ACTIVE'),
            ])
            ->orderBy('name')
            ->orderByDesc('version')
            ->get();

        $instancesQuery = WorkflowInstance::query()
            ->with($this->instanceRelations())
            ->latest('updated_at');
        $this->engine->scopeVisible($instancesQuery, $request->user());
        if (! $request->boolean('include_completed')) {
            $instancesQuery->where('status', 'ACTIVE');
        }

        $instances = $instancesQuery->get();
        $active = $instances->where('status', 'ACTIVE');

        return $this->success([
            'definitions' => $definitions
                ->map(fn (WorkflowDefinition $definition): array => $this->definitionData($definition))
                ->values(),
            'instances' => $instances
                ->map(fn (WorkflowInstance $instance): array => $this->instanceData(
                    $instance,
                    $request,
                ))
                ->values(),
            'summary' => [
                'definitions' => $definitions->count(),
                'drafts' => $definitions->where('status', 'DRAFT')->count(),
                'published' => $definitions->where('status', 'PUBLISHED')->count(),
                'activeInstances' => $active->count(),
                'overdueInstances' => $active
                    ->filter(fn (WorkflowInstance $instance): bool => $instance->due_at?->isPast() ?? false)
                    ->count(),
            ],
            'options' => [
                'modules' => WorkflowDefinition::MODULES,
                'roles' => Role::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'code', 'name']),
                'permissions' => Permission::query()
                    ->orderBy('module')
                    ->orderBy('action')
                    ->get(['id', 'code', 'name', 'module']),
                'offices' => $this->visibleOffices($request),
            ],
        ]);
    }

    public function show(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        return $this->success([
            'workflow' => $this->definitionData($workflow->load($this->definitionRelations())),
        ]);
    }

    public function store(WorkflowDefinitionRequest $request): JsonResponse
    {
        $workflow = $this->definitions->create($request->validated(), $request->user()->id);
        $workflow->load($this->definitionRelations())->loadCount(['instances', 'activeInstances']);
        $this->logDefinition($request, 'workflow.definition_created', $workflow, null);

        return $this->success([
            'workflow' => $this->definitionData($workflow),
        ], 'Workflow draft created successfully.', 201);
    }

    public function update(
        WorkflowDefinitionRequest $request,
        WorkflowDefinition $workflow,
    ): JsonResponse {
        $old = $this->definitionSnapshot($workflow->load($this->definitionRelations()));
        $updated = $this->definitions->update(
            $workflow,
            $request->validated(),
            $request->user()->id,
        );
        $updated->load($this->definitionRelations())->loadCount(['instances', 'activeInstances']);
        $this->logDefinition($request, 'workflow.definition_updated', $updated, $old);

        return $this->success([
            'workflow' => $this->definitionData($updated),
        ], 'Workflow draft updated successfully.');
    }

    public function publish(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        $old = $this->definitionSnapshot($workflow->load($this->definitionRelations()));
        $published = $this->definitions->publish($workflow, $request->user()->id);
        $published->load($this->definitionRelations())->loadCount(['instances', 'activeInstances']);
        $this->logDefinition($request, 'workflow.definition_published', $published, $old);

        return $this->success([
            'workflow' => $this->definitionData($published),
        ], 'Workflow version published and locked successfully.');
    }

    public function revision(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        $revision = $this->definitions->createRevision($workflow, $request->user()->id);
        $revision->load($this->definitionRelations())->loadCount(['instances', 'activeInstances']);
        $this->logDefinition($request, 'workflow.definition_revised', $revision, null, [
            'sourceWorkflowId' => $workflow->id,
            'sourceVersion' => $workflow->version,
        ]);

        return $this->success([
            'workflow' => $this->definitionData($revision),
        ], 'A new editable workflow version was created.', 201);
    }

    public function destroy(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        if ($workflow->instances()->where('status', 'ACTIVE')->exists()) {
            throw ValidationException::withMessages([
                'workflow' => ['A workflow with active instances cannot be archived.'],
            ]);
        }
        $old = $this->definitionSnapshot($workflow->load($this->definitionRelations()));
        $workflow->update(['is_active' => false, 'updated_by' => $request->user()->id]);
        $workflow->delete();
        $this->logDefinition($request, 'workflow.definition_archived', $workflow, $old);

        return $this->success(message: 'Workflow archived successfully. Its history was retained.');
    }

    public function restore(Request $request, int $workflow): JsonResponse
    {
        $definition = WorkflowDefinition::withTrashed()->findOrFail($workflow);
        if (! $definition->trashed()) {
            throw ValidationException::withMessages([
                'workflow' => ['This workflow is not archived.'],
            ]);
        }
        $hasNewerPublished = WorkflowDefinition::query()
            ->where('code', $definition->code)
            ->where('version', '>', $definition->version)
            ->where('status', 'PUBLISHED')
            ->exists();
        $definition->restore();
        $definition->update([
            'status' => $hasNewerPublished ? 'RETIRED' : $definition->status,
            'is_active' => ! $hasNewerPublished,
            'updated_by' => $request->user()->id,
        ]);
        $definition->load($this->definitionRelations())->loadCount(['instances', 'activeInstances']);
        $this->logDefinition($request, 'workflow.definition_restored', $definition, null);

        return $this->success([
            'workflow' => $this->definitionData($definition),
        ], 'Workflow restored successfully.');
    }

    public function start(WorkflowInstanceRequest $request): JsonResponse
    {
        $instance = $this->engine->start($request, $request->validated());

        return $this->success([
            'instance' => $this->instanceData(
                $instance->load($this->instanceRelations()),
                $request,
            ),
        ], 'Workflow instance started successfully.', 201);
    }

    public function instance(Request $request, WorkflowInstance $instance): JsonResponse
    {
        $this->engine->ensureVisible($request->user(), $instance);

        return $this->success([
            'instance' => $this->instanceData(
                $instance->load($this->instanceRelations()),
                $request,
            ),
        ]);
    }

    public function transition(
        WorkflowActionRequest $request,
        WorkflowInstance $instance,
        string $action,
    ): JsonResponse {
        $updated = $this->engine->transition(
            $request,
            $instance,
            $action,
            $request->integer('lockVersion'),
            $request->string('comment')->trim()->toString() ?: null,
        );

        return $this->success([
            'instance' => $this->instanceData(
                $updated->load($this->instanceRelations()),
                $request,
            ),
        ], 'Workflow action completed successfully.');
    }

    public function cancel(
        WorkflowActionRequest $request,
        WorkflowInstance $instance,
    ): JsonResponse {
        $request->validate(['comment' => ['required', 'string', 'max:5000']]);
        $updated = $this->engine->cancel(
            $request,
            $instance,
            $request->integer('lockVersion'),
            $request->string('comment')->trim()->toString(),
        );

        return $this->success([
            'instance' => $this->instanceData(
                $updated->load($this->instanceRelations()),
                $request,
            ),
        ], 'Workflow instance cancelled without deleting its history.');
    }

    /** @return list<string> */
    private function definitionRelations(): array
    {
        return [
            'steps.responsibleRole:id,code,name',
            'transitions.fromStep:id,code,name',
            'transitions.toStep:id,code,name,step_type',
            'transitions.actorRole:id,code,name',
            'transitions.requiredPermission:id,code,name,module,action,description',
            'creator:id,employee_id,name',
            'publisher:id,employee_id,name',
        ];
    }

    /** @return list<string> */
    private function instanceRelations(): array
    {
        return [
            'definition:id,code,name,module_code,subject_type,version',
            'currentStep:id,code,name,step_type,responsible_role_id,instructions,sla_hours',
            'currentStep.responsibleRole:id,code,name',
            'office:id,code,name',
            'starter:id,employee_id,name',
            'completer:id,employee_id,name',
            'events.actor:id,employee_id,name',
            'events.fromStep:id,code,name',
            'events.toStep:id,code,name',
        ];
    }

    /** @return array<string, mixed> */
    private function definitionData(WorkflowDefinition $definition): array
    {
        return [
            'id' => $definition->id,
            'code' => $definition->code,
            'name' => $definition->name,
            'moduleCode' => $definition->module_code,
            'subjectType' => $definition->subject_type,
            'version' => $definition->version,
            'description' => $definition->description,
            'status' => $definition->status,
            'isActive' => $definition->is_active,
            'isArchived' => $definition->trashed(),
            'isImmutable' => $definition->status !== 'DRAFT',
            'publishedAt' => $definition->published_at?->toISOString(),
            'publishedBy' => $definition->publisher ? [
                'id' => $definition->publisher->id,
                'employeeId' => $definition->publisher->employee_id,
                'name' => $definition->publisher->name,
            ] : null,
            'instancesCount' => $definition->instances_count ?? $definition->instances()->count(),
            'activeInstancesCount' => $definition->active_instances_count
                ?? $definition->instances()->where('status', 'ACTIVE')->count(),
            'steps' => $definition->steps->map(fn ($step): array => [
                'id' => $step->id,
                'code' => $step->code,
                'name' => $step->name,
                'sequence' => $step->sequence,
                'stepType' => $step->step_type,
                'responsibleRoleId' => $step->responsible_role_id,
                'responsibleRole' => $step->responsibleRole ? [
                    'id' => $step->responsibleRole->id,
                    'code' => $step->responsibleRole->code,
                    'name' => $step->responsibleRole->name,
                ] : null,
                'slaHours' => $step->sla_hours,
                'instructions' => $step->instructions,
            ])->values(),
            'transitions' => $definition->transitions->map(fn ($transition): array => [
                'id' => $transition->id,
                'code' => $transition->code,
                'name' => $transition->name,
                'sequence' => $transition->sequence,
                'fromStepCode' => $transition->fromStep->code,
                'toStepCode' => $transition->toStep->code,
                'actorRoleId' => $transition->actor_role_id,
                'actorRole' => $transition->actorRole ? [
                    'id' => $transition->actorRole->id,
                    'code' => $transition->actorRole->code,
                    'name' => $transition->actorRole->name,
                ] : null,
                'requiredPermissionId' => $transition->required_permission_id,
                'requiredPermission' => $transition->requiredPermission ? [
                    'id' => $transition->requiredPermission->id,
                    'code' => $transition->requiredPermission->code,
                    'name' => $transition->requiredPermission->name,
                ] : null,
                'authorization' => $this->authority->forTransition($transition),
                'requiresComment' => $transition->requires_comment,
                'enforceSeparationOfDuties' => $transition->enforce_separation_of_duties,
                'isActive' => $transition->is_active,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function instanceData(WorkflowInstance $instance, Request $request): array
    {
        $available = collect($this->engine->availableTransitions($request->user(), $instance));
        $currentTransitions = WorkflowTransition::query()
            ->where('workflow_definition_id', $instance->workflow_definition_id)
            ->where('from_step_id', $instance->current_step_id)
            ->where('is_active', true)
            ->with(['toStep', 'requiredPermission'])
            ->orderBy('sequence')
            ->get();

        return [
            'id' => $instance->id,
            'definition' => [
                'id' => $instance->definition->id,
                'code' => $instance->definition->code,
                'name' => $instance->definition->name,
                'version' => $instance->definition->version,
            ],
            'moduleCode' => $instance->module_code,
            'subjectType' => $instance->subject_type,
            'subjectId' => $instance->subject_id,
            'subjectCode' => $instance->subject_code,
            'subjectLabel' => $instance->subject_label,
            'office' => $instance->office ? [
                'id' => $instance->office->id,
                'code' => $instance->office->code,
                'name' => $instance->office->name,
            ] : null,
            'status' => $instance->status,
            'context' => $instance->context,
            'currentStep' => [
                'id' => $instance->currentStep->id,
                'code' => $instance->currentStep->code,
                'name' => $instance->currentStep->name,
                'stepType' => $instance->currentStep->step_type,
                'instructions' => $instance->currentStep->instructions,
                'responsibleRole' => $instance->currentStep->responsibleRole?->name,
            ],
            'startedBy' => $instance->starter ? [
                'id' => $instance->starter->id,
                'employeeId' => $instance->starter->employee_id,
                'name' => $instance->starter->name,
            ] : null,
            'startedAt' => $instance->started_at?->toISOString(),
            'stepEnteredAt' => $instance->step_entered_at?->toISOString(),
            'dueAt' => $instance->due_at?->toISOString(),
            'isOverdue' => $instance->status === 'ACTIVE' && ($instance->due_at?->isPast() ?? false),
            'completedAt' => $instance->completed_at?->toISOString(),
            'lockVersion' => $instance->lock_version,
            'availableTransitions' => $available->map(fn (WorkflowTransition $transition): array => [
                'code' => $transition->code,
                'name' => $transition->name,
                'toStep' => $transition->toStep->name,
                'requiresComment' => $transition->requires_comment,
                'authorization' => $this->authority->forTransition($transition, $instance),
            ])->values(),
            'transitionAuthorities' => $currentTransitions->map(fn (WorkflowTransition $transition): array => [
                'code' => $transition->code,
                'name' => $transition->name,
                'toStep' => $transition->toStep->name,
                'canAct' => $available->contains('id', $transition->id),
                'authorization' => $this->authority->forTransition($transition, $instance),
            ])->values(),
            'events' => $instance->events->map(fn (WorkflowInstanceEvent $event): array => [
                'id' => $event->id,
                'actionCode' => $event->action_code,
                'comment' => $event->comment,
                'fromStep' => $event->fromStep?->name,
                'toStep' => $event->toStep->name,
                'actor' => $event->actor ? [
                    'id' => $event->actor->id,
                    'employeeId' => $event->actor->employee_id,
                    'name' => $event->actor->name,
                ] : null,
                'oldValues' => $event->old_values,
                'newValues' => $event->new_values,
                'createdAt' => $event->created_at?->toISOString(),
            ])->values(),
        ];
    }

    private function visibleOffices(Request $request)
    {
        return Office::query()
            ->where('is_active', true)
            ->when(
                ! $request->user()->hasGlobalOfficeAccess(),
                fn ($query) => $query->whereKey($request->user()->office_id),
            )
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /** @return array<string, mixed> */
    private function definitionSnapshot(WorkflowDefinition $definition): array
    {
        return [
            'code' => $definition->code,
            'name' => $definition->name,
            'moduleCode' => $definition->module_code,
            'subjectType' => $definition->subject_type,
            'version' => $definition->version,
            'status' => $definition->status,
            'isActive' => $definition->is_active,
            'steps' => $definition->steps->pluck('code')->values()->all(),
            'transitions' => $definition->transitions->pluck('code')->values()->all(),
        ];
    }

    private function logDefinition(
        Request $request,
        string $action,
        WorkflowDefinition $definition,
        ?array $old,
        array $metadata = [],
    ): void {
        $new = $this->definitionSnapshot($definition);
        DB::transaction(function () use ($request, $action, $definition, $old, $new, $metadata): void {
            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => $action,
                'auditable_type' => WorkflowDefinition::class,
                'auditable_id' => $definition->id,
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'metadata' => $metadata,
            ]);
            ActivityLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => $action,
                'description' => "{$action}: {$definition->code} v{$definition->version}",
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'metadata' => ['module' => 'CORE', ...$metadata],
            ]);
        });
    }

    private function success(
        array $data = [],
        ?string $message = null,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data ?: null,
        ], $status);
    }
}
