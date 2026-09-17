<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CmsClosureRequestVersionResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'versionNumber' => $this->version_number, 'previousVersionId' => $this->previous_version_id, 'status' => $this->status_code, 'isCurrent' => $this->active_slot === 'ACTIVE', 'isImmutable' => in_array($this->status_code, ['SUBMITTED', 'UNDER_REVIEW', 'FOR_DECISION', 'APPROVED', 'REJECTED'], true), 'source' => ['validationReviewId' => $this->finalized_validation_review_id, 'validationVersionId' => $this->finalized_validation_version_id, 'actionPlanVersionId' => $this->accepted_action_plan_version_id, 'progressUpdateVersionId' => $this->recorded_progress_update_version_id], 'narratives' => ['closureRequestSummary' => $this->closure_request_summary, 'implementationBasis' => $this->implementation_basis, 'validatedImplementationSummary' => $this->validated_implementation_summary, 'residualMattersSummary' => $this->residual_matters_summary, 'residualRiskStatement' => $this->residual_risk_statement, 'ongoingMonitoringRequirements' => $this->ongoing_monitoring_requirements, 'recordsAndDocumentationSummary' => $this->records_and_documentation_summary, 'resolvedEscalationSummary' => $this->resolved_escalation_summary, 'managementConfirmation' => $this->management_confirmation, 'complianceMonitorRecommendationSummary' => $this->compliance_monitor_recommendation_summary, 'noAdditionalEvidenceExplanation' => $this->no_additional_evidence_explanation], 'preparedBy' => $this->prepared_by, 'submittedBy' => $this->submitted_by, 'submittedAt' => $this->submitted_at, 'reviewStartedBy' => $this->review_started_by, 'reviewStartedAt' => $this->review_started_at, 'returnedBy' => $this->returned_by, 'returnedAt' => $this->returned_at, 'returnReason' => $this->return_reason, 'revisionReason' => $this->revision_reason, 'submissionSnapshot' => $this->submission_snapshot, 'assessment' => $this->whenLoaded('assessment'), 'decision' => $this->whenLoaded('decision'), 'evidence' => $this->whenLoaded('evidenceLinks'), 'lockVersion' => $this->lock_version, 'availableActions' => $this->available_actions ?? []];
    }
}
