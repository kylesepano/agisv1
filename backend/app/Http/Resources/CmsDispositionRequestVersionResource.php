<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CmsDispositionRequestVersionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'versionNumber' => $this->version_number,
            'previousVersionId' => $this->previous_version_id,
            'status' => $this->status_code,
            'statusCode' => $this->status_code,
            'isCurrent' => $this->active_slot === 'ACTIVE',
            'isImmutable' => in_array($this->status_code, ['SUBMITTED', 'UNDER_REVIEW', 'FOR_DECISION', 'APPROVED', 'REJECTED'], true),
            'previousCaseStatus' => $this->previous_case_status,
            'requestedEffectiveDate' => $this->requested_effective_date?->toDateString(),
            'narratives' => [
                'dispositionSummary' => $this->disposition_summary,
                'basisAndCriteria' => $this->basis_and_criteria,
                'riskImpactAssessment' => $this->risk_impact_assessment,
                'managementPosition' => $this->management_position,
                'responsibleOfficeConfirmation' => $this->responsible_office_confirmation,
                'acceptedRiskRationale' => $this->accepted_risk_rationale,
                'riskTreatmentAndMonitoring' => $this->risk_treatment_and_monitoring,
                'noLongerApplicableBasis' => $this->no_longer_applicable_basis,
                'transitionOrRecordsImpact' => $this->transition_or_records_impact,
                'residualRiskStatement' => $this->residual_risk_statement,
                'noAdditionalEvidenceExplanation' => $this->no_additional_evidence_explanation,
            ],
            'preparedBy' => $this->prepared_by,
            'submittedBy' => $this->submitted_by,
            'submittedAt' => $this->submitted_at,
            'reviewStartedBy' => $this->review_started_by,
            'reviewStartedAt' => $this->review_started_at,
            'returnedBy' => $this->returned_by,
            'returnedAt' => $this->returned_at,
            'returnReason' => $this->return_reason,
            'revisionReason' => $this->revision_reason,
            'submissionSnapshot' => $this->submission_snapshot,
            'assessment' => $this->assessment,
            'decision' => $this->decision,
            'evidence' => CmsDispositionEvidenceResource::collection($this->whenLoaded('evidenceLinks')),
            'lockVersion' => $this->lock_version,
            'availableActions' => $this->available_actions ?? [],
        ];
    }
}
