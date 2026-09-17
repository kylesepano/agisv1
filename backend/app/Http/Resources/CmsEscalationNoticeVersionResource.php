<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsEscalationNoticeVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $v = $this->resource;

        return ['id' => $v->id, 'escalationId' => $v->cms_escalation_id, 'versionNumber' => $v->version_number, 'previousVersionId' => $v->previous_version_id, 'status' => $v->status_code, 'subject' => $v->subject, 'escalationSummary' => $v->escalation_summary, 'basisAndContext' => $v->basis_and_context, 'requiredManagementActions' => $v->required_management_actions, 'requiredResponseContents' => $v->required_response_contents, 'responseDueDate' => $v->response_due_date?->toDateString(), 'consequenceOrFollowUpStatement' => $v->consequence_or_follow_up_statement, 'managementAttentionRequested' => (bool) $v->management_attention_requested, 'additionalTriggerExplanation' => $v->additional_trigger_explanation, 'preparedBy' => $this->person($v->preparer), 'submittedAt' => $v->submitted_at?->toISOString(), 'reviewStartedAt' => $v->review_started_at?->toISOString(), 'returnedAt' => $v->returned_at?->toISOString(), 'returnReason' => $v->return_reason, 'issuedAt' => $v->issued_at?->toISOString(), 'issuanceComment' => $v->issuance_comment, 'recipients' => $v->recipients?->map(fn ($r) => ['id' => $r->id, 'recipientType' => $r->recipient_type, 'recipientName' => $r->recipient_name_snapshot, 'officeName' => $r->office_name_snapshot, 'positionOrRole' => $r->position_or_role_snapshot])->values(), 'acknowledgements' => $v->acknowledgements?->map(fn ($a) => ['id' => $a->id, 'officeId' => $a->office_id, 'userId' => $a->user_id, 'acknowledgedAt' => $a->acknowledged_at?->toISOString(), 'comment' => $a->acknowledgement_comment])->values(), 'evidence' => $v->activeEvidenceLinks?->map(fn ($e) => ['id' => $e->id, 'title' => $e->title, 'category' => $e->evidence_category, 'description' => $e->description, 'checksumSha256' => $e->checksum_sha256, 'confidentialityCode' => $e->confidentiality_code_snapshot])->values(), 'immutable' => $v->status_code !== 'DRAFT', 'lockVersion' => $v->lock_version, 'availableActions' => $v->available_actions ?? []];
    }

    private function person($user): ?array
    {
        return $user ? ['id' => $user->id, 'employeeId' => $user->employee_id, 'name' => $user->name, 'initials' => $user->initials] : null;
    }
}
