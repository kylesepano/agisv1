<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ArmisProviderAuthorityDecision */
class ArmisProviderAuthorityDecisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reconciliationRunId' => $this->reconciliation_run_id,
            'decisionCode' => $this->decision_code,
            'fromMode' => $this->from_mode,
            'toMode' => $this->to_mode,
            'reason' => $this->reason,
            'decidedAt' => $this->decided_at?->toISOString(),
            'decidedBy' => $this->whenLoaded('decider', fn () => $this->decider?->only(['id', 'name'])),
        ];
    }
}
