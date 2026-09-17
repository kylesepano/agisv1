<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsTargetDateExtensionAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recommendationCode' => $this->recommendation_code,
            'assessmentSummary' => $this->assessment_summary,
            'evidenceReviewSummary' => $this->evidence_review_summary,
            'feasibilityAssessment' => $this->feasibility_assessment,
            'riskOfDelaySummary' => $this->risk_of_delay_summary,
            'conditionsOrObservations' => $this->conditions_or_observations,
            'assessedAt' => $this->assessed_at?->toISOString(),
            'assessor' => $this->assessor ? [
                'id' => $this->assessor->id,
                'employeeId' => $this->assessor->employee_id,
                'name' => $this->assessor->name,
                'initials' => $this->assessor->initials,
            ] : null,
        ];
    }
}
