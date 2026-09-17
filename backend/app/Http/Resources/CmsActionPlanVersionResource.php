<?php

namespace App\Http\Resources;

use App\Models\CmsActionPlanVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsActionPlanVersionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $plan = $this->plan;
        $caseId = $plan?->cms_recommendation_case_id;

        return [
            'id' => $this->id,
            'displayCode' => $caseId
                ? sprintf('CAP-CMS-REC-%06d-V%d', $caseId, $this->version_number)
                : null,
            'versionNumber' => $this->version_number,
            'status' => $this->status_code,
            'previousVersionId' => $this->previous_version_id,
            'planSummary' => $this->plan_summary,
            'implementationStrategy' => $this->implementation_strategy,
            'expectedOutcome' => $this->expected_outcome,
            'rootCauseResponse' => $this->root_cause_response,
            'resourcesRequired' => $this->resources_required,
            'dependencies' => $this->dependencies,
            'risksAndConstraints' => $this->risks_and_constraints,
            'plannedStartDate' => $this->planned_start_date?->toDateString(),
            'plannedTargetDate' => $this->planned_target_date?->toDateString(),
            'ownerOffice' => $this->whenLoaded(
                'ownerOffice',
                fn () => $this->ownerOffice?->only(['id', 'code', 'name', 'acronym']),
            ),
            'focalUser' => $this->whenLoaded('focalUser', fn () => $this->safeUser($this->focalUser)),
            'preparedBy' => $this->whenLoaded('preparer', fn () => $this->safeUser($this->preparer)),
            'submittedBy' => $this->whenLoaded('submitter', fn () => $this->safeUser($this->submitter)),
            'submittedAt' => $this->submitted_at?->toISOString(),
            'reviewStartedBy' => $this->whenLoaded(
                'reviewStarter',
                fn () => $this->safeUser($this->reviewStarter),
            ),
            'reviewStartedAt' => $this->review_started_at?->toISOString(),
            'acceptedBy' => $this->whenLoaded('accepter', fn () => $this->safeUser($this->accepter)),
            'acceptedAt' => $this->accepted_at?->toISOString(),
            'acceptanceComment' => $this->acceptance_comment,
            'returnedBy' => $this->whenLoaded('returner', fn () => $this->safeUser($this->returner)),
            'returnedAt' => $this->returned_at?->toISOString(),
            'returnReason' => $this->return_reason,
            'revisionReason' => $this->revision_reason,
            'hasSubmissionSnapshot' => $this->submission_snapshot !== null,
            'lockVersion' => $this->lock_version,
            'milestones' => CmsActionPlanMilestoneResource::collection(
                $this->whenLoaded('milestones'),
            ),
            'completeness' => $this->getAttribute('completeness'),
            'availableActions' => $this->getAttribute('available_actions') ?? [],
            'isCurrent' => $plan && (int) $plan->current_version_id === $this->id,
            'isAcceptedCurrent' => $plan
                && (int) $plan->accepted_version_id === $this->id,
            'isSuperseded' => $this->status_code === CmsActionPlanVersion::STATUS_ACCEPTED
                && $plan
                && $plan->accepted_version_id !== null
                && (int) $plan->accepted_version_id !== $this->id,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function safeUser(mixed $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'name' => $user->name,
            'initials' => $user->initials,
        ] : null;
    }
}
