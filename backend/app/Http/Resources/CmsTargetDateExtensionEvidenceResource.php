<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsTargetDateExtensionEvidenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'documentId' => $this->document_id,
            'documentVersionId' => $this->document_version_id,
            'evidenceCategory' => $this->evidence_category,
            'title' => $this->title,
            'description' => $this->description,
            'sourceOrCustodian' => $this->source_or_custodian,
            'linkedAt' => $this->linked_at?->toISOString(),
            'checksumSha256' => $this->checksum_sha256,
            'confidentialityCode' => $this->confidentiality_code_snapshot,
            'removedAt' => $this->removed_at?->toISOString(),
            'isActive' => $this->removed_at === null,
            'file' => $this->when($this->relationLoaded('documentVersion') && $this->documentVersion, [
                'name' => $this->documentVersion->original_file_name,
                'mimeType' => $this->documentVersion->mime_type,
                'size' => $this->documentVersion->file_size,
            ]),
        ];
    }
}
