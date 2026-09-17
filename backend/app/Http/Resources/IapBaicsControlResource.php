<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IapBaicsControlResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'assessmentId' => $this->assessment_id, 'scopeItemId' => $this->scope_item_id,
            'scopeItem' => $this->scopeItem ? ['id' => $this->scopeItem->id, 'auditUniverseItemId' => $this->scopeItem->audit_universe_item_id, 'sourceSnapshot' => $this->scopeItem->source_snapshot, 'office' => $this->scopeItem->office?->only(['id', 'code', 'name']), 'auditArea' => $this->scopeItem->auditArea?->only(['id', 'code', 'name']), 'auditFocus' => $this->scopeItem->auditFocus?->only(['id', 'code', 'name'])] : null,
            'componentId' => $this->component_id, 'componentCode' => $this->component?->component_code,
            'controlCode' => $this->control_code, 'processStep' => $this->process_step, 'responsibleUnit' => $this->responsible_unit,
            'controlOwnerOfficeId' => $this->control_owner_office_id, 'controlOwnerOffice' => $this->controlOwnerOffice?->only(['id', 'code', 'name']),
            'controlOwnerUserId' => $this->control_owner_user_id, 'controlOwner' => $this->person($this->controlOwner),
            'objective' => $this->objective, 'relatedRisk' => $this->related_risk, 'controlDescription' => $this->control_description,
            'expectedResult' => $this->expected_result, 'controlType' => $this->control_type, 'executionMode' => $this->execution_mode,
            'frequency' => $this->frequency, 'evidenceProduced' => $this->evidence_produced, 'approvalRequired' => (bool) $this->approval_required,
            'segregationOfDutiesRequired' => (bool) $this->segregation_of_duties_required, 'designAssessment' => $this->design_assessment,
            'operatingAssessment' => $this->operating_assessment, 'controlStatus' => $this->control_status,
            'deficiencyClassification' => $this->deficiency_classification, 'limitationDetails' => $this->limitation_details,
            'gapDetails' => $this->gap_details, 'breakdownDetails' => $this->breakdown_details, 'contradictionDetails' => $this->contradiction_details,
            'recommendationAction' => $this->recommendation_action, 'status' => $this->status, 'preparedBy' => $this->person($this->preparer),
            'reviewer' => $this->person($this->reviewer), 'approvedBy' => $this->person($this->approver),
            'reviewedAt' => $this->reviewed_at?->toISOString(), 'approvedAt' => $this->approved_at?->toISOString(),
            'immutableAt' => $this->immutable_at?->toISOString(), 'versionNumber' => $this->version_number, 'lockVersion' => $this->lock_version,
            'methods' => $this->whenLoaded('methods', fn () => $this->methods->map(fn ($method) => ['id' => $method->id, 'methodType' => $method->method_type, 'title' => $method->title, 'status' => $method->status])->values()),
            'evidence' => $this->whenLoaded('evidenceLinks', fn () => $this->evidenceLinks->map(fn ($link) => ['id' => $link->id, 'evidenceLinkId' => $link->id, 'documentVersionId' => $link->document_version_id, 'fileName' => $link->documentVersion?->original_file_name, 'checksumSha256' => $link->documentVersion?->checksum_sha256, 'description' => $link->description])->values()),
            'versions' => $this->whenLoaded('versions', fn () => $this->versions->map(fn ($version) => ['id' => $version->id, 'versionNumber' => $version->version_number, 'status' => $version->status, 'snapshotHash' => $version->snapshot_hash, 'reason' => $version->reason, 'createdAt' => $version->created_at?->toISOString()])->values()),
            'availableActions' => $this->availableActions(),
        ];
    }
    private function person(mixed $person): ?array { return $person ? $person->only(['id', 'employee_id', 'name', 'initials', 'position']) : null; }
    private function availableActions(): array { return match ($this->status) { 'DRAFT', 'RETURNED' => ['UPDATE', 'SUBMIT'], 'PENDING_REVIEW' => ['RETURN', 'APPROVE'], default => [], }; }
}
