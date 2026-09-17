<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Serializes an ARMIS resource profile and its currently loaded foundation data. */
class ArmisResourceProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'resourceCode' => $this->resource_code,
            'userId' => $this->user_id,
            'officeId' => $this->office_id,
            'category' => $this->category,
            'status' => $this->status,
            'effectiveFrom' => $this->effective_from?->toDateString(),
            'effectiveTo' => $this->effective_to?->toDateString(),
            'notes' => $this->notes,
            'lockVersion' => $this->lock_version,
            'isArchived' => $this->trashed(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];

        if ($this->relationLoaded('user')) {
            $data['user'] = $this->user ? [
                'id' => $this->user->id,
                'employeeId' => $this->user->employee_id,
                'name' => $this->user->name,
                'position' => $this->user->position,
                'isActive' => $this->user->is_active,
            ] : null;
        }
        if ($this->relationLoaded('office')) {
            $data['office'] = $this->office ? [
                'id' => $this->office->id,
                'code' => $this->office->code,
                'name' => $this->office->name,
            ] : null;
        }
        if ($this->relationLoaded('competencies')) {
            $data['competencies'] = $this->competencies->map(fn ($competency): array => [
                'id' => $competency->id,
                'competencyFamilyUuid' => $competency->competency_family_uuid,
                'competencyId' => $competency->competency_id,
                'code' => $competency->competency?->code,
                'label' => $competency->competency?->label,
                'versionNumber' => $competency->version_number,
                'isCurrentRevision' => (bool) $competency->is_current_revision,
                'proficiencyLevel' => $competency->proficiency_level,
                'credentialType' => $competency->credential_type,
                'credentialReference' => $competency->credential_reference,
                'issuer' => $competency->issuer,
                'issuedAt' => $competency->issued_at?->toDateString(),
                'status' => $competency->status,
                'evidenceDocumentVersionId' => $competency->evidence_document_version_id,
                'verifiedBy' => $competency->verified_by,
                'verifiedAt' => $competency->verified_at?->toISOString(),
                'expiresAt' => $competency->expires_at?->toDateString(),
                'notes' => $competency->notes,
                'lockVersion' => $competency->lock_version,
            ])->values();
        }
        if ($this->relationLoaded('availabilityPeriods')) {
            $data['availabilityPeriods'] = $this->availabilityPeriods->map(fn ($period): array => [
                'id' => $period->id,
                'availabilityType' => $period->availability_type,
                'startDate' => $period->start_date?->toDateString(),
                'endDate' => $period->end_date?->toDateString(),
                'personDays' => $period->person_days !== null ? (float) $period->person_days : null,
                'status' => $period->status,
                'notes' => $period->notes,
                'lockVersion' => $period->lock_version,
            ])->values();
        }

        return $data;
    }
}
