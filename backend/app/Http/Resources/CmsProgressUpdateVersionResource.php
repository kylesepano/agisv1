<?php

namespace App\Http\Resources;

use App\Models\CmsProgressUpdateVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsProgressUpdateVersionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $family = $this->progressUpdate;
        $caseId = $family?->cms_recommendation_case_id;

        return [
            'id' => $this->id,
            'displayCode' => $caseId ? sprintf(
                'CMS-UPD-%06d-%03d-V%d',
                $caseId,
                $family->reporting_sequence,
                $this->version_number,
            ) : null,
            'versionNumber' => $this->version_number,
            'previousVersionId' => $this->previous_version_id,
            'status' => $this->status_code,
            'statusMeaning' => $this->status_code === CmsProgressUpdateVersion::STATUS_RECORDED
                ? 'Reviewed for completeness and recorded for follow-up monitoring; not independently validated.'
                : null,
            'accomplishmentSummary' => $this->accomplishment_summary,
            'managementReportedOverallPercentage' => $this
                ->management_reported_overall_percentage,
            'systemCalculatedWeightedReportedPercentage' => $this
                ->system_calculated_weighted_percentage,
            'baselineWeighted' => $this->baseline_weighted,
            'issuesAndConstraints' => $this->issues_and_constraints,
            'correctiveActionsForDelays' => $this->corrective_actions_for_delays,
            'nextSteps' => $this->next_steps,
            'forecastCompletionDate' => $this->forecast_completion_date?->toDateString(),
            'managementDeclaration' => $this->management_declaration,
            'generalEvidenceExplanation' => $this->general_evidence_explanation,
            'preparedBy' => $this->whenLoaded('preparer', fn () => $this->safeUser($this->preparer)),
            'submittedBy' => $this->whenLoaded('submitter', fn () => $this->safeUser($this->submitter)),
            'submittedAt' => $this->submitted_at?->toISOString(),
            'reviewStartedBy' => $this->whenLoaded(
                'reviewStarter',
                fn () => $this->safeUser($this->reviewStarter),
            ),
            'reviewStartedAt' => $this->review_started_at?->toISOString(),
            'reviewComment' => $this->review_comment,
            'returnedBy' => $this->whenLoaded('returner', fn () => $this->safeUser($this->returner)),
            'returnedAt' => $this->returned_at?->toISOString(),
            'returnReason' => $this->return_reason,
            'recordedBy' => $this->whenLoaded('recorder', fn () => $this->safeUser($this->recorder)),
            'recordedAt' => $this->recorded_at?->toISOString(),
            'recordingComment' => $this->recording_comment,
            'revisionReason' => $this->revision_reason,
            'hasSubmissionSnapshot' => $this->submission_snapshot !== null,
            'lockVersion' => $this->lock_version,
            'milestoneProgress' => CmsMilestoneProgressResource::collection(
                $this->whenLoaded('milestoneProgress'),
            ),
            'evidence' => CmsProgressEvidenceResource::collection(
                $this->whenLoaded('activeEvidenceLinks'),
            ),
            'completeness' => $this->getAttribute('completeness'),
            'availableActions' => $this->getAttribute('available_actions') ?? [],
            'isCurrent' => $family && (int) $family->current_version_id === $this->id,
            'isRecordedCurrent' => $family
                && (int) $family->recorded_version_id === $this->id,
            'isSuperseded' => $this->status_code === CmsProgressUpdateVersion::STATUS_RECORDED
                && $family
                && $family->recorded_version_id !== null
                && (int) $family->recorded_version_id !== $this->id,
            'managementReportsComplete' => $this->management_reported_overall_percentage !== null
                && (float) $this->management_reported_overall_percentage >= 100,
            'reportedCompleteAwaitingValidation' => $this
                ->management_reported_overall_percentage !== null
                && (float) $this->management_reported_overall_percentage >= 100,
            'notIndependentlyValidated' => true,
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
