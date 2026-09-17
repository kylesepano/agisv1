<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class CmsValidationReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $case = $this->case;
        $current = $this->whenLoaded('currentVersion');
        $currentVisible = $current && ! $current instanceof MissingValue;
        $recorded = $this->recordedProgressUpdateVersion;

        return [
            'id' => $this->id,
            'caseId' => $this->cms_recommendation_case_id,
            'displayCode' => sprintf(
                'VAL-CMS-REC-%06d-%03d',
                $this->cms_recommendation_case_id,
                $this->validation_sequence,
            ),
            'validationSequence' => $this->validation_sequence,
            'actionPlanId' => $this->cms_corrective_action_plan_id,
            'acceptedActionPlanVersionId' => $this->accepted_action_plan_version_id,
            'progressUpdateId' => $this->cms_progress_update_id,
            'recordedProgressUpdateVersionId' => $this->recorded_progress_update_version_id,
            'currentVersionId' => $currentVisible ? $this->current_version_id : null,
            'finalizedVersionId' => $this->finalized_version_id,
            'lockVersion' => $this->lock_version,
            'isActive' => $this->active_slot === 'ACTIVE',
            'createdBy' => $this->whenLoaded('creator', fn () => $this->safeUser($this->creator)),
            'currentPrimaryValidator' => $this->whenLoaded(
                'currentAssignment',
                fn () => $this->currentAssignment?->user
                    ? $this->safeUser($this->currentAssignment->user)
                    : null,
            ),
            'assignments' => CmsValidationAssignmentResource::collection(
                $this->whenLoaded('assignments'),
            ),
            'currentVersion' => $currentVisible
                ? new CmsValidationVersionResource($current)
                : null,
            'finalizedVersion' => $this->finalized_version_id
                ? new CmsValidationVersionResource($this->whenLoaded('finalizedVersion'))
                : null,
            'versions' => CmsValidationVersionResource::collection(
                $this->whenLoaded('versions'),
            ),
            'sourceContext' => [
                'recommendationCode' => $case?->recommendation?->recommendation_code,
                'recommendation' => data_get(
                    $case?->recommendation?->source_snapshot,
                    'recommendation.wording',
                ),
                'responsibleOffice' => $case?->leadResponsibleOffice?->only([
                    'id', 'code', 'name', 'acronym',
                ]),
                'caseStatus' => $case?->status_code,
                'acceptedActionPlanVersionId' => $this->accepted_action_plan_version_id,
                'recordedProgressUpdateVersionId' => $this->recorded_progress_update_version_id,
                'managementReportedPercentage' => $recorded
                    ?->management_reported_overall_percentage,
                'systemCalculatedWeightedReportedPercentage' => $recorded
                    ?->system_calculated_weighted_percentage,
                'managementEvidenceCount' => $recorded?->activeEvidenceLinks?->count() ?? 0,
                'activeComplianceMonitor' => $case?->currentAssignment?->user
                    ? $this->safeUser($case->currentAssignment->user)
                    : null,
            ],
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
