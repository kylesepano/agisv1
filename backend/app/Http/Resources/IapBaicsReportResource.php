<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IapBaicsReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $version = $this->relationLoaded('versions') ? $this->versions->first() : null;
        return ['id' => $this->id, 'assessmentId' => $this->assessment_id, 'reportCode' => $this->report_code, 'title' => $this->title, 'status' => $this->status, 'executiveSummary' => $this->executive_summary, 'objectivesScopeMethodology' => $this->objectives_scope_methodology, 'overallFindings' => $this->overall_findings, 'controlGapSummary' => $this->control_gap_summary, 'recommendationsSummary' => $this->recommendations_summary, 'limitationsExceptions' => $this->limitations_exceptions, 'sourceManifest' => $this->source_manifest, 'preparedBy' => $this->person($this->preparer), 'reviewer' => $this->person($this->reviewer), 'approvedBy' => $this->person($this->approver), 'issuedBy' => $this->person($this->issuer), 'submittedAt' => $this->submitted_at?->toISOString(), 'approvedAt' => $this->approved_at?->toISOString(), 'issuedAt' => $this->issued_at?->toISOString(), 'versionNumber' => $this->version_number, 'lockVersion' => $this->lock_version, 'controls' => $this->whenLoaded('controls', fn () => IapBaicsControlResource::collection($this->controls)), 'interimAnalyses' => $this->whenLoaded('interimAnalyses', fn () => IapBaicsInterimAnalysisResource::collection($this->interimAnalyses)), 'latestVersion' => $version ? ['id' => $version->id, 'versionNumber' => $version->version_number, 'status' => $version->status, 'fileVersion' => $version->file_version, 'contentSha256' => $version->content_sha256, 'sourceManifestSha256' => $version->source_manifest_sha256, 'pdfChecksumSha256' => $version->pdf_checksum_sha256, 'csvChecksumSha256' => $version->csv_checksum_sha256, 'createdAt' => $version->created_at?->toISOString()] : null, 'availableActions' => match ($this->status) { 'DRAFT', 'RETURNED' => ['UPDATE', 'SUBMIT'], 'PENDING_REVIEW' => ['RETURN', 'APPROVE'], 'APPROVED' => ['ISSUE', 'SUPERSEDE'], default => [], }];
    }
    private function person(mixed $person): ?array { return $person ? $person->only(['id', 'employee_id', 'name', 'initials', 'position']) : null; }
}
