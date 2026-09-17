<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsReopeningEvidenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'category' => $this->evidence_category, 'description' => $this->description, 'sourceOrCustodian' => $this->source_or_custodian, 'documentId' => $this->document_id, 'documentVersionId' => $this->document_version_id, 'checksumSha256' => $this->checksum_sha256, 'confidentialityCode' => $this->confidentiality_code_snapshot, 'linkedBy' => $this->linker?->only(['id', 'employee_id', 'name']), 'linkedAt' => $this->linked_at?->toISOString(), 'removedAt' => $this->removed_at?->toISOString(), 'downloadAvailable' => ! $this->removed_at];
    }
}
