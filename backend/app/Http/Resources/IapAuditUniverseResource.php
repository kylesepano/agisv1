<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes auditable subjects with their office and audit-area relationships.
 */
class IapAuditUniverseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subjectCode' => $this->subject_code,
            'name' => $this->name,
            'subjectTypeId' => $this->subject_type_id,
            'subjectType' => $this->item($this->subjectType),
            'responsibleOfficeId' => $this->responsible_office_id,
            'responsibleOffice' => $this->responsibleOffice ? [
                'id' => $this->responsibleOffice->id,
                'code' => $this->responsibleOffice->code,
                'name' => $this->responsibleOffice->name,
            ] : null,
            'primaryAuditAreaId' => $this->primary_audit_area_id,
            'primaryAuditArea' => $this->primaryAuditArea ? [
                'id' => $this->primaryAuditArea->id,
                'code' => $this->primaryAuditArea->code,
                'name' => $this->primaryAuditArea->name,
            ] : null,
            'materialityLevelId' => $this->materiality_level_id,
            'materialityLevel' => $this->item($this->materialityLevel),
            'description' => $this->description,
            'auditScope' => $this->audit_scope,
            'materialityExposure' => $this->materiality_exposure,
            'lastAuditDate' => $this->last_audit_date?->toDateString(),
            'historicalAuditSummary' => $this->historical_audit_summary,
            'stakeholderOffices' => $this->stakeholderOffices->map(fn ($office) => [
                'id' => $office->id,
                'code' => $office->code,
                'name' => $office->name,
            ])->values(),
            'auditHistory' => $this->auditHistory->map(fn ($history) => [
                'id' => $history->id,
                'auditedOn' => $history->audited_on?->toDateString(),
                'engagementReference' => $history->engagement_reference,
                'title' => $history->title,
                'outcome' => $history->outcome,
                'reportReference' => $history->report_reference,
                'notes' => $history->notes,
            ])->values(),
            'lockVersion' => $this->lock_version,
            'isActive' => $this->is_active,
            'isArchived' => $this->trashed(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function item(mixed $item): ?array
    {
        return $item ? [
            'id' => $item->id,
            'code' => $item->code,
            'label' => $item->label,
            'description' => $item->description,
        ] : null;
    }
}
