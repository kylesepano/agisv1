<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsProgressEvidenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'milestoneProgressId' => $this->cms_milestone_progress_id,
            'documentId' => $this->document_id,
            'documentVersionId' => $this->document_version_id,
            'evidenceCategory' => $this->evidence_category,
            'title' => $this->title,
            'description' => $this->description,
            'sourceOrCustodian' => $this->source_or_custodian,
            'checksumSha256' => $this->checksum_sha256,
            'confidentiality' => $this->whenLoaded(
                'confidentialityLevel',
                fn () => $this->confidentialityLevel?->only(['id', 'code', 'label']),
            ),
            'fileName' => $this->whenLoaded(
                'documentVersion',
                fn () => $this->documentVersion?->original_file_name,
            ),
            'fileSize' => $this->whenLoaded(
                'documentVersion',
                fn () => $this->documentVersion?->file_size,
            ),
            'mimeType' => $this->whenLoaded(
                'documentVersion',
                fn () => $this->documentVersion?->mime_type,
            ),
            'linkedBy' => $this->whenLoaded(
                'linker',
                fn () => $this->safeUser($this->linker),
            ),
            'linkedAt' => $this->linked_at?->toISOString(),
            'isRemoved' => $this->removed_at !== null,
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
