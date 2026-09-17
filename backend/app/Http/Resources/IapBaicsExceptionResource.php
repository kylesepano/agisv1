<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IapBaicsExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'assessmentId' => $this->assessment_id, 'componentId' => $this->component_id, 'reason' => $this->reason, 'authorityUserId' => $this->authority_user_id, 'authority' => $this->person($this->authority), 'compensatingEvidence' => $this->compensating_evidence, 'expiryDate' => $this->expiry_date?->toDateString(), 'status' => $this->status, 'createdBy' => $this->person($this->creator), 'reviewedBy' => $this->person($this->reviewer), 'approvedBy' => $this->person($this->approver), 'approvedAt' => $this->approved_at?->toISOString(), 'immutableAt' => $this->immutable_at?->toISOString(), 'versionNumber' => $this->version_number, 'lockVersion' => $this->lock_version, 'versions' => $this->whenLoaded('versions', fn () => $this->versions->map(fn ($version) => ['id' => $version->id, 'versionNumber' => $version->version_number, 'status' => $version->status, 'snapshotHash' => $version->snapshot_hash, 'reason' => $version->reason, 'createdAt' => $version->created_at?->toISOString()])->values())];
    }
    private function person(mixed $person): ?array { return $person ? $person->only(['id', 'employee_id', 'name', 'initials', 'position']) : null; }
}
