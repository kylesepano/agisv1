<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class CmsProgressUpdateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $case = $this->case;
        $intake = $case?->recommendation;
        $current = $this->whenLoaded('currentVersion');
        $currentVisible = $current && ! $current instanceof MissingValue;

        return [
            'id' => $this->id,
            'caseId' => $this->cms_recommendation_case_id,
            'displayCode' => sprintf(
                'CMS-UPD-%06d-%03d',
                $this->cms_recommendation_case_id,
                $this->reporting_sequence,
            ),
            'reportingSequence' => $this->reporting_sequence,
            'reportingPeriodStart' => $this->reporting_period_start?->toDateString(),
            'reportingPeriodEnd' => $this->reporting_period_end?->toDateString(),
            'actionPlanId' => $this->cms_corrective_action_plan_id,
            'acceptedActionPlanVersionId' => $this->accepted_action_plan_version_id,
            'acceptedActionPlanVersion' => $this->whenLoaded(
                'acceptedActionPlanVersion',
                fn () => [
                    'id' => $this->acceptedActionPlanVersion->id,
                    'versionNumber' => $this->acceptedActionPlanVersion->version_number,
                    'acceptedAt' => $this->acceptedActionPlanVersion->accepted_at?->toISOString(),
                    'milestoneCount' => $this->acceptedActionPlanVersion->milestones->count(),
                ],
            ),
            'createdBy' => $this->whenLoaded('creator', fn () => $this->safeUser($this->creator)),
            'currentVersionId' => $currentVisible ? $this->current_version_id : null,
            'recordedVersionId' => $this->recorded_version_id,
            'lockVersion' => $this->lock_version,
            'currentVersion' => $currentVisible
                ? new CmsProgressUpdateVersionResource($current)
                : null,
            'recordedVersion' => $this->recorded_version_id
                ? new CmsProgressUpdateVersionResource($this->whenLoaded('recordedVersion'))
                : null,
            'versions' => CmsProgressUpdateVersionResource::collection(
                $this->whenLoaded('versions'),
            ),
            'caseContext' => $case ? [
                'id' => $case->id,
                'cmsRecommendationCode' => sprintf('CMS-REC-%06d', $case->id),
                'status' => $case->status_code,
                'recommendationCode' => $intake?->recommendation_code,
                'recommendation' => data_get($intake?->source_snapshot, 'recommendation.wording'),
                'responsibleOffice' => $case->leadResponsibleOffice?->only([
                    'id', 'code', 'name', 'acronym',
                ]),
                'effectiveTargetDate' => $case
                    ->effective_target_implementation_date
                    ?->toDateString(),
                'currentMonitor' => $case->currentAssignment?->user
                    ? $this->safeUser($case->currentAssignment->user)
                    : null,
            ] : null,
            'notIndependentlyValidated' => true,
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
