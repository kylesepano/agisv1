<?php

namespace App\Http\Resources;

use App\Models\ArmisCompetency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Serializes an ARMIS competency certification claim and its revision lineage. */
class ArmisCompetencyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ArmisCompetency $competency */
        $competency = $this->resource;

        return [
            'id' => $competency->id,
            'competencyFamilyUuid' => $competency->competency_family_uuid,
            'resourceProfileId' => $competency->resource_profile_id,
            'resourceCode' => $competency->resourceProfile?->resource_code,
            'resourceUser' => $competency->resourceProfile?->user?->only(['id', 'employee_id', 'name', 'initials']),
            'competencyId' => $competency->competency_id,
            'code' => $competency->competency?->code,
            'label' => $competency->competency?->label,
            'description' => $competency->competency?->description,
            'versionNumber' => $competency->version_number,
            'supersedesId' => $competency->supersedes_id,
            'isCurrentRevision' => (bool) $competency->is_current_revision,
            'proficiencyLevel' => $competency->proficiency_level,
            'credentialType' => $competency->credential_type,
            'credentialReference' => $competency->credential_reference,
            'issuer' => $competency->issuer,
            'issuedAt' => $competency->issued_at?->toDateString(),
            'status' => $competency->status,
            'evidenceDocumentVersionId' => $competency->evidence_document_version_id,
            'evidenceDocument' => $competency->evidenceDocumentVersion?->only([
                'id', 'document_id', 'version_number', 'original_file_name',
                'mime_type', 'file_size', 'checksum_sha256',
            ]),
            'expiresAt' => $competency->expires_at?->toDateString(),
            'submittedBy' => $competency->submitter?->only(['id', 'employee_id', 'name', 'initials']),
            'submittedAt' => $competency->submitted_at?->toIso8601String(),
            'verifiedBy' => $competency->verifier?->only(['id', 'employee_id', 'name', 'initials']),
            'verifiedAt' => $competency->verified_at?->toIso8601String(),
            'reviewedBy' => $competency->reviewer?->only(['id', 'employee_id', 'name', 'initials']),
            'reviewedAt' => $competency->reviewed_at?->toIso8601String(),
            'notes' => $competency->notes,
            'verificationNotes' => $competency->verification_notes,
            'lockVersion' => $competency->lock_version,
            'createdAt' => $competency->created_at?->toIso8601String(),
            'updatedAt' => $competency->updated_at?->toIso8601String(),
        ];
    }
}
