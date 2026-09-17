<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsReopeningReviewAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'recommendationCode' => $this->recommendation_code, 'sourceDecisionIntegrityAssessment' => $this->source_decision_integrity_assessment, 'newEvidenceOrChangedConditionAssessment' => $this->new_evidence_or_changed_condition_assessment, 'materialityAssessment' => $this->materiality_assessment, 'riskAssessment' => $this->risk_assessment, 'destinationStatusAssessment' => $this->destination_status_assessment, 'actionPlanRequirementAssessment' => $this->action_plan_requirement_assessment, 'assignmentAndMonitoringAssessment' => $this->assignment_and_monitoring_assessment, 'evidenceSufficiencyAssessment' => $this->evidence_sufficiency_assessment, 'recommendationRationale' => $this->recommendation_rationale, 'conditionsOrObservations' => $this->conditions_or_observations, 'reviewedAt' => $this->reviewed_at?->toISOString(), 'reviewer' => $this->reviewer?->only(['id', 'employee_id', 'name', 'initials'])];
    }
}
