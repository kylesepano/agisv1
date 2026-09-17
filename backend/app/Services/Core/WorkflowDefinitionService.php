<?php

namespace App\Services;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Validates and normalizes administrator-configured workflow definitions.
 */
class WorkflowDefinitionService
{
    public function __construct(private readonly RuntimeConfiguration $runtime) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, int $actorId): WorkflowDefinition
    {
        $this->validateGraph($data);

        return DB::transaction(function () use ($data, $actorId): WorkflowDefinition {
            $nextVersion = ((int) WorkflowDefinition::withTrashed()
                ->where('code', $data['code'])
                ->max('version')) + 1;
            $definition = WorkflowDefinition::query()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'module_code' => $data['moduleCode'],
                'subject_type' => $data['subjectType'],
                'version' => max(1, $nextVersion),
                'description' => $data['description'] ?? null,
                'status' => 'DRAFT',
                'is_active' => true,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $this->syncGraph($definition, $data);

            return $definition;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(WorkflowDefinition $definition, array $data, int $actorId): WorkflowDefinition
    {
        $this->ensureDraft($definition);
        if ($definition->code !== $data['code']) {
            throw ValidationException::withMessages([
                'code' => ['The workflow family code cannot be changed after creation.'],
            ]);
        }
        $this->validateGraph($data);

        return DB::transaction(function () use ($definition, $data, $actorId): WorkflowDefinition {
            $definition->update([
                'name' => $data['name'],
                'module_code' => $data['moduleCode'],
                'subject_type' => $data['subjectType'],
                'description' => $data['description'] ?? null,
                'updated_by' => $actorId,
            ]);
            $definition->transitions()->delete();
            $definition->steps()->delete();
            $this->syncGraph($definition, $data);

            return $definition;
        }, 3);
    }

    public function publish(WorkflowDefinition $definition, int $actorId): WorkflowDefinition
    {
        $this->ensureDraft($definition);
        $definition->load('steps', 'transitions.fromStep', 'transitions.toStep');
        $this->validateStoredGraph($definition);

        return DB::transaction(function () use ($definition, $actorId): WorkflowDefinition {
            WorkflowDefinition::query()
                ->where('code', $definition->code)
                ->where('id', '<>', $definition->id)
                ->where('status', 'PUBLISHED')
                ->update([
                    'status' => 'RETIRED',
                    'is_active' => false,
                    'updated_by' => $actorId,
                    'updated_at' => now(),
                ]);
            $definition->update([
                'status' => 'PUBLISHED',
                'is_active' => true,
                'published_by' => $actorId,
                'published_at' => now(),
                'updated_by' => $actorId,
            ]);

            return $definition;
        }, 3);
    }

    public function createRevision(WorkflowDefinition $source, int $actorId): WorkflowDefinition
    {
        $source->load('steps', 'transitions');

        return DB::transaction(function () use ($source, $actorId): WorkflowDefinition {
            $version = ((int) WorkflowDefinition::withTrashed()
                ->where('code', $source->code)
                ->lockForUpdate()
                ->max('version')) + 1;
            $revision = WorkflowDefinition::query()->create([
                'code' => $source->code,
                'name' => $source->name,
                'module_code' => $source->module_code,
                'subject_type' => $source->subject_type,
                'version' => $version,
                'description' => $source->description,
                'status' => 'DRAFT',
                'is_active' => true,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $stepMap = [];
            foreach ($source->steps as $step) {
                $copy = $revision->steps()->create($step->only([
                    'code',
                    'name',
                    'sequence',
                    'step_type',
                    'responsible_role_id',
                    'sla_hours',
                    'instructions',
                ]));
                $stepMap[$step->id] = $copy->id;
            }
            foreach ($source->transitions as $transition) {
                $revision->transitions()->create([
                    ...$transition->only([
                        'code',
                        'name',
                        'sequence',
                        'actor_role_id',
                        'required_permission_id',
                        'requires_comment',
                        'enforce_separation_of_duties',
                        'is_active',
                    ]),
                    'from_step_id' => $stepMap[$transition->from_step_id],
                    'to_step_id' => $stepMap[$transition->to_step_id],
                ]);
            }

            return $revision;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function syncGraph(WorkflowDefinition $definition, array $data): void
    {
        $stepMap = [];
        foreach (array_values($data['steps']) as $index => $step) {
            $created = $definition->steps()->create([
                'code' => $step['code'],
                'name' => $step['name'],
                'sequence' => $index + 1,
                'step_type' => $step['stepType'],
                'responsible_role_id' => $step['responsibleRoleId'] ?? null,
                // A workflow author may override the SLA per step. Otherwise
                // non-terminal steps inherit the live platform default.
                'sla_hours' => $step['slaHours']
                    ?? ($step['stepType'] === 'END'
                        ? null
                        : max(1, $this->runtime->integer('default_workflow_sla_hours'))),
                'instructions' => $step['instructions'] ?? null,
            ]);
            $stepMap[$step['code']] = $created->id;
        }
        foreach (array_values($data['transitions']) as $index => $transition) {
            $definition->transitions()->create([
                'from_step_id' => $stepMap[$transition['fromStepCode']],
                'to_step_id' => $stepMap[$transition['toStepCode']],
                'code' => $transition['code'],
                'name' => $transition['name'],
                'sequence' => $index + 1,
                // Transition authority is permission-based. Keep the legacy
                // actor-role column nullable for backwards compatibility, but
                // never persist a role as an authorization requirement.
                'actor_role_id' => null,
                'required_permission_id' => $transition['requiredPermissionId'] ?? null,
                'requires_comment' => $transition['requiresComment'],
                'enforce_separation_of_duties' => $transition['enforceSeparationOfDuties'],
                'is_active' => $transition['isActive'] ?? true,
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function validateGraph(array $data): void
    {
        $steps = collect($data['steps']);
        $transitions = collect($data['transitions']);
        $codes = $steps->pluck('code');

        if ($steps->where('stepType', 'START')->count() !== 1) {
            throw ValidationException::withMessages([
                'steps' => ['A workflow must contain exactly one START step.'],
            ]);
        }
        if ($steps->where('stepType', 'END')->isEmpty()) {
            throw ValidationException::withMessages([
                'steps' => ['A workflow must contain at least one END step.'],
            ]);
        }
        foreach ($transitions as $index => $transition) {
            if (! $codes->contains($transition['fromStepCode'])
                || ! $codes->contains($transition['toStepCode'])) {
                throw ValidationException::withMessages([
                    "transitions.{$index}" => ['Both transition steps must exist in this workflow.'],
                ]);
            }
            if ($transition['fromStepCode'] === $transition['toStepCode']) {
                throw ValidationException::withMessages([
                    "transitions.{$index}" => ['A transition cannot return to the same step.'],
                ]);
            }
        }
        foreach ($steps->where('stepType', '<>', 'END') as $step) {
            if (! $transitions->contains('fromStepCode', $step['code'])) {
                throw ValidationException::withMessages([
                    'transitions' => ["The {$step['name']} step requires an outgoing transition."],
                ]);
            }
        }
        foreach ($steps->where('stepType', 'END') as $step) {
            if ($transitions->contains('fromStepCode', $step['code'])) {
                throw ValidationException::withMessages([
                    'transitions' => ["The terminal {$step['name']} step cannot have outgoing transitions."],
                ]);
            }
        }

        $start = $steps->firstWhere('stepType', 'START')['code'];
        $reachable = collect([$start]);
        do {
            $count = $reachable->count();
            $next = $transitions
                ->whereIn('fromStepCode', $reachable)
                ->pluck('toStepCode');
            $reachable = $reachable->merge($next)->unique()->values();
        } while ($reachable->count() > $count);

        if ($reachable->count() !== $steps->count()) {
            throw ValidationException::withMessages([
                'steps' => ['Every workflow step must be reachable from the START step.'],
            ]);
        }
    }

    private function validateStoredGraph(WorkflowDefinition $definition): void
    {
        $this->validateGraph([
            'steps' => $definition->steps->map(fn (WorkflowStep $step): array => [
                'code' => $step->code,
                'name' => $step->name,
                'stepType' => $step->step_type,
            ])->all(),
            'transitions' => $definition->transitions->map(fn ($transition): array => [
                'code' => $transition->code,
                'fromStepCode' => $transition->fromStep->code,
                'toStepCode' => $transition->toStep->code,
            ])->all(),
        ]);
    }

    private function ensureDraft(WorkflowDefinition $definition): void
    {
        if ($definition->status !== 'DRAFT' || $definition->trashed()) {
            throw ValidationException::withMessages([
                'workflow' => ['Only an active draft workflow definition can be modified. Create a revision instead.'],
            ]);
        }
    }
}
