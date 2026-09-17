<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsReopeningRequestVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $professionalReviewer = $request->user()?->hasPermission('cms.reopening.review')
            || $request->user()?->hasPermission('cms.reopening.approve');

        return ['id' => $this->id, 'versionNumber' => $this->version_number, 'previousVersionId' => $this->previous_version_id, 'status' => $this->status_code, 'isCurrent' => $this->active_slot === 'ACTIVE', 'isImmutable' => $this->status_code !== 'DRAFT', 'reasonCode' => $this->reopening_reason_code, 'narratives' => ['requestSummary' => $this->request_summary, 'changedConditionOrNewFact' => $this->changed_condition_or_new_fact, 'materialityAssessment' => $this->materiality_assessment, 'sourceTerminalDecisionAssessment' => $this->source_terminal_decision_assessment, 'implementationOrControlFailureAssessment' => $this->implementation_or_control_failure_assessment, 'riskImpact' => $this->risk_impact, 'responsibleOfficeImpact' => $this->responsible_office_impact, 'proposedFollowUpApproach' => $this->proposed_follow_up_approach, 'newActionPlanRequirementExplanation' => $this->new_action_plan_requirement_explanation, 'existingActionPlanSuitabilityAssessment' => $this->existing_action_plan_suitability_assessment, 'complianceMonitorRequirement' => $this->compliance_monitor_requirement, 'targetDateImplications' => $this->target_date_implications, 'relatedRecurrenceSummary' => $this->related_recurrence_summary, 'relatedEscalationSummary' => $this->related_escalation_summary, 'managementPosition' => $this->management_position, 'ciasInitiatorPosition' => $this->cias_initiator_position, 'noAdditionalEvidenceExplanation' => $this->no_additional_evidence_explanation], 'proposedDestinationStatus' => $this->proposed_destination_code, 'preparedBy' => $this->preparer?->only(['id', 'employee_id', 'name', 'initials']), 'submittedBy' => $this->submitter?->only(['id', 'employee_id', 'name', 'initials']), 'submittedAt' => $this->submitted_at?->toISOString(), 'reviewStartedBy' => $this->reviewStarter?->only(['id', 'employee_id', 'name', 'initials']), 'reviewStartedAt' => $this->review_started_at?->toISOString(), 'returnedAt' => $this->returned_at?->toISOString(), 'returnReason' => $this->return_reason, 'revisionReason' => $this->revision_reason, 'submissionSnapshot' => $this->submission_snapshot, 'assessment' => $professionalReviewer ? $this->whenLoaded('assessment', fn () => new CmsReopeningReviewAssessmentResource($this->assessment)) : null, 'decision' => $this->whenLoaded('decision', fn () => new CmsReopeningDecisionResource($this->decision)), 'evidence' => CmsReopeningEvidenceResource::collection($this->whenLoaded('evidenceLinks')), 'lockVersion' => $this->lock_version, 'availableActions' => $this->getAttribute('available_actions') ?? []];
    }
}
