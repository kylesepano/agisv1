<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ArmisProviderReconciliationRun */
class ArmisProviderReconciliationRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'displayCode' => $this->display_code,
            'uuid' => $this->run_uuid,
            'sourceQueryVersion' => $this->source_query_version,
            'fiscalYear' => $this->fiscal_year,
            'providerMode' => $this->provider_mode,
            'status' => $this->status,
            'filters' => $this->filters,
            'scopeSnapshot' => $this->scope_snapshot,
            'resultSnapshot' => $this->result_snapshot,
            'summary' => $this->summary,
            'resultChecksumSha256' => $this->result_checksum_sha256,
            'generatedAt' => $this->generated_at?->toISOString(),
            'generatedBy' => $this->whenLoaded('generator', fn () => $this->generator?->only(['id', 'name'])),
            'reviews' => ArmisProviderReconciliationReviewResource::collection($this->whenLoaded('reviews')),
            'authorityDecisions' => ArmisProviderAuthorityDecisionResource::collection($this->whenLoaded('authorityDecisions')),
        ];
    }
}
