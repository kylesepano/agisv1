<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CmsDispositionEvidenceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'evidenceCategory' => $this->evidence_category,
            'title' => $this->title,
            'description' => $this->description,
            'sourceOrCustodian' => $this->source_or_custodian,
            'documentId' => $this->document_id,
            'documentVersionId' => $this->document_version_id,
            'fileName' => $this->documentVersion?->original_file_name,
            'mimeType' => $this->documentVersion?->mime_type,
            'fileSize' => $this->documentVersion?->file_size,
            'checksumSha256' => $this->checksum_sha256,
            'confidentialityCode' => $this->confidentiality_code_snapshot,
            'linkedBy' => $this->linked_by,
            'linkedAt' => $this->linked_at,
            'removedAt' => $this->removed_at,
            'removalReason' => $this->removal_reason,
        ];
    }
}
