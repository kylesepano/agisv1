<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IapBaicsIntegrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latest = $this->relationLoaded('versions') ? $this->versions->first() : null;
        return [
            'id' => $this->id,
            'integrationCode' => $this->integration_code,
            'assessmentId' => $this->assessment_id,
            'reportId' => $this->report_id,
            'reportVersionId' => $this->report_version_id,
            'consumerType' => $this->consumer_type,
            'consumerId' => $this->consumer_id,
            'consumerSnapshot' => $this->consumer_snapshot,
            'decisionType' => $this->decision_type,
            'status' => $this->status,
            'decisionReason' => $this->decision_reason,
            'legacyReason' => $this->legacy_reason,
            'compensatingSource' => $this->compensating_source,
            'expiresAt' => $this->expires_at?->toDateString(),
            'reviewer' => $this->person($this->reviewer),
            'authority' => $this->person($this->authority),
            'approver' => $this->person($this->approver),
            'creator' => $this->person($this->creator),
            'submittedAt' => $this->submitted_at?->toISOString(),
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'approvedAt' => $this->approved_at?->toISOString(),
            'retiredAt' => $this->retired_at?->toISOString(),
            'versionNumber' => $this->version_number,
            'lockVersion' => $this->lock_version,
            'sourceSnapshot' => $this->source_snapshot,
            'providerSnapshot' => $this->provider_snapshot,
            'sourceManifestSha256' => $this->source_manifest_sha256,
            'latestVersion' => $latest ? ['id' => $latest->id, 'versionNumber' => $latest->version_number, 'status' => $latest->status, 'snapshotSha256' => $latest->snapshot_sha256, 'reason' => $latest->reason, 'createdAt' => $latest->created_at?->toISOString()] : null,
            'availableActions' => match ($this->status) {
                'DRAFT', 'RETURNED' => ['UPDATE', 'SUBMIT'],
                'PENDING_REVIEW' => ['REVIEW', 'RETURN', 'APPROVE'],
                'APPROVED' => ['RETIRE'],
                default => [],
            },
        ];
    }

    private function person(mixed $person): ?array { return $person ? $person->only(['id', 'employee_id', 'name', 'initials', 'position']) : null; }
}
