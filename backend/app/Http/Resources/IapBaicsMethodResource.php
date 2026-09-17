<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IapBaicsMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'componentId' => $this->component_id, 'familyUuid' => $this->family_uuid, 'versionNumber' => $this->version_number, 'methodType' => $this->method_type, 'title' => $this->title, 'description' => $this->description, 'performedBy' => $this->person($this->performer), 'officeId' => $this->office_id, 'processReference' => $this->process_reference, 'performedOn' => $this->performed_on?->toDateString(), 'procedure' => $this->procedure, 'result' => $this->result, 'limitations' => $this->limitations, 'reviewer' => $this->person($this->reviewer), 'status' => $this->status, 'reviewedAt' => $this->reviewed_at?->toISOString(), 'immutableAt' => $this->immutable_at?->toISOString(), 'lockVersion' => $this->lock_version, 'evidence' => $this->whenLoaded('evidenceLinks', fn () => $this->evidenceLinks->map(fn ($link) => ['id' => $link->id, 'componentId' => $link->component_id, 'methodId' => $link->method_id, 'documentVersionId' => $link->document_version_id, 'fileName' => $link->documentVersion?->original_file_name, 'checksumSha256' => $link->documentVersion?->checksum_sha256])->values())];
    }
    private function person(mixed $person): ?array { return $person ? $person->only(['id', 'employee_id', 'name', 'initials', 'position']) : null; }
}
