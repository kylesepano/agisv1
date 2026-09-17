<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsRecommendationTargetDateHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'historyCode' => $this->history_code,
            'previousTargetDate' => $this->previous_target_date?->toDateString(),
            'newTargetDate' => $this->new_target_date?->toDateString(),
            'decisionId' => $this->cms_target_date_extension_decision_id,
            'occurredAt' => $this->occurred_at?->toISOString(),
            'metadata' => $this->metadata,
            'actor' => $this->actor ? [
                'id' => $this->actor->id,
                'employeeId' => $this->actor->employee_id,
                'name' => $this->actor->name,
                'initials' => $this->actor->initials,
            ] : null,
        ];
    }
}
