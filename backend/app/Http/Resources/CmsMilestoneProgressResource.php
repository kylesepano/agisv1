<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsMilestoneProgressResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actionPlanMilestoneId' => $this->cms_action_plan_milestone_id,
            'milestoneSequence' => $this->milestone_sequence,
            'milestoneSnapshot' => $this->milestone_snapshot,
            'managementReportedStatusCode' => $this->management_reported_status_code,
            'managementReportedPercentage' => $this->management_reported_percentage,
            'accomplishmentDescription' => $this->accomplishment_description,
            'issuesAndConstraints' => $this->issues_and_constraints,
            'nextStep' => $this->next_step,
            'forecastCompletionDate' => $this->forecast_completion_date?->toDateString(),
            'noEvidenceExplanation' => $this->no_evidence_explanation,
            'displayOrder' => $this->display_order,
            'evidenceCount' => $this->relationLoaded('evidenceLinks')
                ? $this->evidenceLinks->count()
                : null,
        ];
    }
}
