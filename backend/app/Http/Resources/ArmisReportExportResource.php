<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Exposes export metadata without private storage paths or public URLs. */
class ArmisReportExportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reportRunId' => $this->armis_report_run_id,
            'format' => strtolower($this->format),
            'versionNumber' => (int) $this->version_number,
            'fileName' => $this->file_name,
            'mimeType' => $this->mime_type,
            'fileSize' => (int) $this->file_size,
            'checksumSha256' => $this->checksum_sha256,
            'generatedAt' => $this->generated_at?->toISOString(),
            'downloadEndpoint' => "/api/armis/report-exports/{$this->id}/download",
        ];
    }
}
