<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsEscalationResponseVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $v = $this->resource;

        return ['id' => $v->id, 'responseId' => $v->cms_escalation_response_id, 'versionNumber' => $v->version_number, 'previousVersionId' => $v->previous_version_id, 'status' => $v->status_code, 'managementResponseSummary' => $v->management_response_summary, 'rootCauseOrExplanation' => $v->root_cause_or_explanation, 'actionsCompleted' => $v->actions_completed, 'remainingActions' => $v->remaining_actions, 'committedActions' => $v->committed_actions, 'responsiblePersonOrOffice' => $v->responsible_person_or_office, 'commitmentStartDate' => $v->commitment_start_date?->toDateString(), 'commitmentTargetDate' => $v->commitment_target_date?->toDateString(), 'resourceOrDependencyNeeds' => $v->resource_or_dependency_needs, 'requestForCiasGuidance' => $v->request_for_cias_guidance, 'noEvidenceExplanation' => $v->no_evidence_explanation, 'preparedBy' => $v->preparer?->only(['id', 'employee_id', 'name', 'initials']), 'submittedAt' => $v->submitted_at?->toISOString(), 'reviewStartedAt' => $v->review_started_at?->toISOString(), 'returnedAt' => $v->returned_at?->toISOString(), 'returnReason' => $v->return_reason, 'acceptedAt' => $v->accepted_at?->toISOString(), 'acceptanceComment' => $v->acceptance_comment, 'evidence' => $v->activeEvidenceLinks?->map(fn ($e) => ['id' => $e->id, 'title' => $e->title, 'category' => $e->evidence_category, 'checksumSha256' => $e->checksum_sha256, 'confidentialityCode' => $e->confidentiality_code_snapshot])->values(), 'immutable' => $v->status_code !== 'DRAFT', 'lockVersion' => $v->lock_version, 'availableActions' => $v->available_actions ?? []];
    }
}
