<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ArmisProviderMonitoringCheck */
class ArmisProviderMonitoringCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'displayCode' => $this->display_code,
            'uuid' => $this->check_uuid,
            'sourceQueryVersion' => $this->source_query_version,
            'providerMode' => $this->provider_mode,
            'configuredMode' => $this->configured_mode,
            'overallStatus' => $this->overall_status,
            'scopeSnapshot' => $this->scope_snapshot,
            'checks' => $this->checks,
            'providerSnapshot' => $this->provider_snapshot,
            'resultChecksumSha256' => $this->result_checksum_sha256,
            'performedAt' => $this->performed_at?->toISOString(),
            'performedBy' => $this->whenLoaded('performer', fn () => $this->performer?->only(['id', 'name'])),
        ];
    }
}
