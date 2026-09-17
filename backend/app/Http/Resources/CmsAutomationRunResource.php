<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsAutomationRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'runKey' => $this->run_key,
            'ruleCode' => $this->rule?->rule_code,
            'statusCode' => $this->status_code,
            'startedAt' => $this->started_at?->toISOString(),
            'finishedAt' => $this->finished_at?->toISOString(),
            'scannedCount' => $this->scanned_count,
            'createdCount' => $this->created_count,
            'skippedCount' => $this->skipped_count,
            'errorCount' => $this->error_count,
            'errorSummary' => $this->error_summary,
            'metadata' => $this->metadata,
        ];
    }
}
