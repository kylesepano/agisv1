<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Serializes a scope-authorized reproducible CMS report run. */
class CmsReportRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $snapshot = $this->result_snapshot ?? [];

        return [
            'id' => $this->id,
            'displayCode' => $this->display_code,
            'reportCode' => $this->report_code,
            'title' => $this->report_title,
            'sourceQueryVersion' => $this->source_query_version,
            'filters' => $this->filters,
            'generatedAt' => $this->generated_at?->toISOString(),
            'generatedBy' => $this->whenLoaded(
                'generator',
                fn () => $this->generator?->only(['id', 'name', 'employee_id']),
            ),
            'rowCount' => (int) $this->row_count,
            'resultChecksumSha256' => $this->result_checksum_sha256,
            'columns' => $snapshot['columns'] ?? [],
            'rows' => $snapshot['rows'] ?? [],
            'exports' => CmsReportExportResource::collection(
                $this->whenLoaded('exports', fn () => $this->exports),
            ),
        ];
    }
}
