<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsTargetDateExtensionDecisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'decisionCode' => $this->decision_code,
            'decidedAt' => $this->decided_at?->toISOString(),
            'decisionComment' => $this->decision_comment,
            'overrideReason' => $this->override_reason,
            'previousEffectiveTargetDate' => $this->previous_effective_target_date?->toDateString(),
            'approvedTargetDate' => $this->approved_target_date?->toDateString(),
            'newEffectiveTargetDate' => $this->new_effective_target_date?->toDateString(),
            'decidedBy' => $this->decider ? [
                'id' => $this->decider->id,
                'employeeId' => $this->decider->employee_id,
                'name' => $this->decider->name,
                'initials' => $this->decider->initials,
            ] : null,
        ];
    }
}
