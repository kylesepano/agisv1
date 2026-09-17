<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IapBaicsComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'assessmentId' => $this->assessment_id, 'componentCode' => $this->component_code,
            'status' => $this->status, 'conclusion' => $this->conclusion, 'supportingSummary' => $this->supporting_summary,
            'limitations' => $this->limitations, 'assessor' => $this->person($this->assessor), 'reviewer' => $this->person($this->reviewer),
            'approvedBy' => $this->person($this->approver), 'reviewedAt' => $this->reviewed_at?->toISOString(),
            'approvedAt' => $this->approved_at?->toISOString(), 'immutableAt' => $this->immutable_at?->toISOString(),
            'versionNumber' => $this->version_number, 'lockVersion' => $this->lock_version,
            'methods' => $this->whenLoaded('methods', fn () => IapBaicsMethodResource::collection($this->methods)),
            'evidence' => $this->whenLoaded('evidenceLinks', fn () => $this->evidenceLinks->map(fn ($link) => $this->evidence($link))->values()),
            'exceptions' => $this->whenLoaded('exceptions', fn () => IapBaicsExceptionResource::collection($this->exceptions)),
            'versions' => $this->whenLoaded('versions', fn () => $this->versions->map(fn ($version) => ['id' => $version->id, 'versionNumber' => $version->version_number, 'status' => $version->status, 'snapshotHash' => $version->snapshot_hash, 'reason' => $version->reason, 'createdAt' => $version->created_at?->toISOString()])),
            'readiness' => $this->when(isset($this->component_readiness), $this->component_readiness),
        ];
    }
    private function person(mixed $person): ?array { return $person ? $person->only(['id', 'employee_id', 'name', 'initials', 'position']) : null; }
    private function evidence(mixed $link): array
    {
        $version = $link->documentVersion;
        return ['id' => $link->id, 'methodId' => $link->method_id, 'documentVersionId' => $link->document_version_id, 'evidenceRole' => $link->evidence_role, 'description' => $link->description, 'fileName' => $version?->original_file_name, 'mimeType' => $version?->mime_type, 'fileSize' => $version?->file_size, 'checksumSha256' => $version?->checksum_sha256, 'documentId' => $version?->document_id, 'protectedDownloadUrl' => $version?->document_id ? "/api/documents/{$version->document_id}/versions/{$version->id}/download" : null];
    }
}
