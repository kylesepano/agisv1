<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsValidationVersionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $review = $this->review;
        $caseId = $review?->cms_recommendation_case_id;

        return [
            'id' => $this->id,
            'displayCode' => $caseId ? sprintf(
                'VAL-CMS-REC-%06d-%03d-V%d',
                $caseId,
                $review->validation_sequence,
                $this->version_number,
            ) : null,
            'versionNumber' => $this->version_number,
            'previousVersionId' => $this->previous_version_id,
            'status' => $this->status_code,
            'validationScope' => $this->validation_scope,
            'validationObjectives' => $this->validation_objectives,
            'methodologySummary' => $this->methodology_summary,
            'overallWorkPerformed' => $this->overall_work_performed,
            'overallEvidenceSummary' => $this->overall_evidence_summary,
            'limitations' => $this->limitations,
            'professionalJudgmentRationale' => $this->professional_judgment_rationale,
            'proposedConclusionCode' => $this->proposed_conclusion_code,
            'finalConclusionCode' => $this->final_conclusion_code,
            'validatedCompletionPercentage' => $this->validated_completion_percentage,
            'validator' => $this->whenLoaded('validator', fn () => $this->safeUser($this->validator)),
            'preparedBy' => $this->whenLoaded('preparer', fn () => $this->safeUser($this->preparer)),
            'submittedBy' => $this->whenLoaded('submitter', fn () => $this->safeUser($this->submitter)),
            'submittedAt' => $this->submitted_at?->toISOString(),
            'supervisoryReviewStartedBy' => $this->whenLoaded(
                'supervisoryReviewer',
                fn () => $this->safeUser($this->supervisoryReviewer),
            ),
            'supervisoryReviewStartedAt' => $this->supervisory_review_started_at?->toISOString(),
            'supervisoryReviewComment' => $this->supervisory_review_comment,
            'returnedBy' => $this->whenLoaded('returner', fn () => $this->safeUser($this->returner)),
            'returnedAt' => $this->returned_at?->toISOString(),
            'returnReason' => $this->return_reason,
            'finalizedBy' => $this->whenLoaded('finalizer', fn () => $this->safeUser($this->finalizer)),
            'finalizedAt' => $this->finalized_at?->toISOString(),
            'finalizationComment' => $this->finalization_comment,
            'supervisoryOverrideReason' => $this->supervisory_override_reason,
            'revisionReason' => $this->revision_reason,
            'hasSubmissionSnapshot' => $this->submission_snapshot !== null,
            'lockVersion' => $this->lock_version,
            'validationItems' => CmsValidationItemResource::collection(
                $this->whenLoaded('items'),
            ),
            'evidenceAssessments' => CmsValidationEvidenceAssessmentResource::collection(
                $this->whenLoaded('evidenceAssessments'),
            ),
            'validatorEvidence' => CmsValidationEvidenceResource::collection(
                $this->whenLoaded('activeEvidenceLinks'),
            ),
            'completeness' => $this->getAttribute('completeness'),
            'availableActions' => $this->getAttribute('available_actions') ?? [],
            'isCurrent' => $review && (int) $review->current_version_id === $this->id,
            'isFinalizedCurrent' => $review
                && (int) $review->finalized_version_id === $this->id,
            'isHistorical' => $review
                && (int) $review->current_version_id !== $this->id,
            'createdAt' => $this->created_at?->toISOString(),
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
