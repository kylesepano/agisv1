<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes SIAP revisions, objectives, priorities, and approval history.
 */
class SiapPlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'planCode' => $this->plan_code,
            'startYear' => $this->start_year,
            'endYear' => $this->end_year,
            'title' => $this->title,
            'strategicContext' => $this->strategic_context,
            'vision' => $this->vision,
            'missionAlignment' => $this->mission_alignment,
            'planningMethodology' => $this->planning_methodology,
            'expectedOutcomes' => $this->expected_outcomes,
            'status' => $this->status,
            'revisionNumber' => $this->revision_number,
            'supersedesPlanId' => $this->supersedes_plan_id,
            'isCurrentRevision' => $this->is_current_revision,
            'preparedBy' => $this->prepared_by,
            'coordinatorId' => $this->coordinator_id,
            'submittedAt' => $this->submitted_at?->toISOString(),
            'approvedAt' => $this->approved_at?->toISOString(),
            'activatedAt' => $this->activated_at?->toISOString(),
            'completedAt' => $this->completed_at?->toISOString(),
            'lockVersion' => $this->lock_version,
            'isActive' => $this->is_active,
            'isArchived' => $this->trashed(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];

        foreach ([
            'preparer',
            'coordinator',
            'submitter',
            'approver',
            'activator',
            'completer',
        ] as $relation) {
            if ($this->relationLoaded($relation)) {
                $data[$relation] = $this->user($this->{$relation});
            }
        }

        if (isset($this->objectives_count)) {
            $data['objectivesCount'] = (int) $this->objectives_count;
        }
        if (isset($this->priorities_count)) {
            $data['prioritiesCount'] = (int) $this->priorities_count;
        }

        if ($this->relationLoaded('objectives')) {
            $data['objectives'] = $this->objectives->map(fn ($objective) => [
                'id' => $objective->id,
                'objectiveCode' => $objective->objective_code,
                'title' => $objective->title,
                'description' => $objective->description,
                'expectedOutcome' => $objective->expected_outcome,
                'displayOrder' => $objective->display_order,
                'auditAreaIds' => $objective->auditAreas->pluck('id')->values(),
                'auditAreas' => $objective->auditAreas
                    ->map->only(['id', 'code', 'name'])
                    ->values(),
            ])->values();
        }

        if ($this->relationLoaded('priorities')) {
            $data['priorities'] = $this->priorities->map(fn ($priority) => [
                'id' => $priority->id,
                'priorityCode' => $priority->priority_code,
                'title' => $priority->title,
                'theme' => $priority->theme,
                'description' => $priority->description,
                'expectedOutcome' => $priority->expected_outcome,
                'displayOrder' => $priority->display_order,
            ])->values();
        }

        if ($this->relationLoaded('workflowEvents')) {
            $data['workflowEvents'] = $this->workflowEvents->map(fn ($event) => [
                'id' => $event->id,
                'action' => $event->action,
                'fromStatus' => $event->from_status,
                'toStatus' => $event->to_status,
                'actor' => $this->user($event->actor),
                'actorRoleCode' => $event->actor_role_code,
                'comment' => $event->comment,
                'planLockVersion' => $event->plan_lock_version,
                'createdAt' => $event->created_at?->toISOString(),
            ])->values();
        }

        return $data;
    }

    /** @return array<string, mixed>|null */
    private function user(mixed $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'name' => $user->name,
            'initials' => $user->initials,
        ] : null;
    }
}
