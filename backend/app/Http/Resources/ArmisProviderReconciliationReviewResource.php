<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ArmisProviderReconciliationReview */
class ArmisProviderReconciliationReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reconciliationRunId' => $this->reconciliation_run_id,
            'decision' => $this->decision,
            'discrepancyDecisions' => $this->discrepancy_decisions,
            'comment' => $this->comment,
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'reviewedBy' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->only(['id', 'name'])),
        ];
    }
}
