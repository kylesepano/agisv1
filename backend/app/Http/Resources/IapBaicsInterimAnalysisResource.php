<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IapBaicsInterimAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'assessmentId' => $this->assessment_id, 'analysisCode' => $this->analysis_code, 'title' => $this->title, 'analysisPeriodStart' => $this->analysis_period_start?->toDateString(), 'analysisPeriodEnd' => $this->analysis_period_end?->toDateString(), 'analysisNarrative' => $this->analysis_narrative, 'findingsSummary' => $this->findings_summary, 'recommendationsSummary' => $this->recommendations_summary, 'limitations' => $this->limitations, 'sourceManifest' => $this->source_manifest, 'status' => $this->status, 'preparedBy' => $this->person($this->preparer), 'reviewer' => $this->person($this->reviewer), 'approvedBy' => $this->person($this->approver), 'versionNumber' => $this->version_number, 'lockVersion' => $this->lock_version, 'availableActions' => match ($this->status) { 'DRAFT', 'RETURNED' => ['UPDATE', 'SUBMIT'], 'PENDING_REVIEW' => ['RETURN', 'APPROVE'], default => [], }];
    }
    private function person(mixed $person): ?array { return $person ? $person->only(['id', 'employee_id', 'name', 'initials', 'position']) : null; }
}
