<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsAutomationRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ruleCode' => $this->rule_code,
            'name' => $this->name,
            'description' => $this->description,
            'ruleType' => $this->rule_type,
            'statusCode' => $this->status_code,
            'scheduleCode' => $this->schedule_code,
            'configuration' => $this->configuration,
            'lockVersion' => $this->lock_version,
            'currentVersion' => $this->whenLoaded('currentVersion', fn () => [
                'id' => $this->currentVersion->id,
                'versionNumber' => $this->currentVersion->version_number,
                'effectiveFrom' => $this->currentVersion->effective_from?->toISOString(),
                'configuration' => $this->currentVersion->configuration,
            ]),
        ];
    }
}
