<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsValidationEvidenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'validationVersionId' => $this->cms_validation_version_id,
            'validationItemId' => $this->cms_validation_item_id,
            'documentId' => $this->document_id,
            'documentVersionId' => $this->document_version_id,
            'evidenceCategory' => $this->evidence_category,
            'title' => $this->title,
            'description' => $this->description,
            'sourceOrCustodian' => $this->source_or_custodian,
            'linkedBy' => $this->whenLoaded('linker', fn () => $this->safeUser($this->linker)),
            'linkedAt' => $this->linked_at?->toISOString(),
            'checksumSha256' => $this->checksum_sha256,
            'confidentialityCode' => $this->confidentiality_code_snapshot,
            'file' => $this->whenLoaded('documentVersion', fn () => [
                'name' => $this->documentVersion->original_file_name,
                'mimeType' => $this->documentVersion->mime_type,
                'fileSize' => $this->documentVersion->file_size,
                'versionNumber' => $this->documentVersion->version_number,
            ]),
            'downloadUrl' => "/api/cms/validation-evidence/{$this->id}/download",
        ];
    }

    /** @return array<string, mixed>|null */
    private function safeUser(mixed $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'name' => $user->name,
            'initials' => $user->initials,
        ] : null;
    }
}
