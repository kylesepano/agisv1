<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsValidationEvidenceAssessmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'validationItemId' => $this->cms_validation_item_id,
            'progressEvidenceLinkId' => $this->cms_progress_evidence_link_id,
            'validationEvidenceLinkId' => $this->cms_validation_evidence_link_id,
            'evidenceSourceCode' => $this->evidence_source_code,
            'relevanceCode' => $this->relevance_code,
            'reliabilityCode' => $this->reliability_code,
            'sufficiencyCode' => $this->sufficiency_code,
            'reliedUpon' => $this->relied_upon,
            'assessmentSummary' => $this->assessment_summary,
            'limitationSummary' => $this->limitation_summary,
            'assessedBy' => $this->whenLoaded('assessor', fn () => $this->safeUser($this->assessor)),
            'assessedAt' => $this->assessed_at?->toISOString(),
            'managementEvidence' => $this->whenLoaded(
                'progressEvidenceLink',
                fn () => $this->progressEvidenceLink ? [
                    'id' => $this->progressEvidenceLink->id,
                    'title' => $this->progressEvidenceLink->title,
                    'category' => $this->progressEvidenceLink->evidence_category,
                    'sourceOrCustodian' => $this->progressEvidenceLink->source_or_custodian,
                    'documentVersionId' => $this->progressEvidenceLink->document_version_id,
                    'checksumSha256' => $this->progressEvidenceLink->checksum_sha256,
                    'confidentialityCode' => $this->progressEvidenceLink
                        ->confidentiality_code_snapshot,
                ] : null,
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    private function safeUser(mixed $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'name' => $user->name,
            'initials' => $user->initials,
        ] : null;
    }
}
