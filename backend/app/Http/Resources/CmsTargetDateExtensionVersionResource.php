<?php

namespace App\Http\Resources;

use App\Models\CmsTargetDateExtensionVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsTargetDateExtensionVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CmsTargetDateExtensionVersion $version */
        $version = $this->resource;
        $family = $version->request;

        return [
            'id' => $version->id,
            'displayCode' => $version->display_code,
            'versionNumber' => $version->version_number,
            'previousVersionId' => $version->previous_version_id,
            'status' => $version->status_code,
            'requestedTargetDate' => $version->requested_target_date?->toDateString(),
            'extensionDays' => $family ? $family->baseline_effective_target_date->diffInDays($version->requested_target_date) : null,
            'extensionJustification' => $version->extension_justification,
            'causeOfDelay' => $version->cause_of_delay,
            'actionsAlreadyTaken' => $version->actions_already_taken,
            'remainingActions' => $version->remaining_actions,
            'recoveryPlan' => $version->recovery_plan,
            'impactIfNotApproved' => $version->impact_if_not_approved,
            'revisedScheduleSummary' => $version->revised_schedule_summary,
            'managementProgressSummary' => $version->management_progress_summary,
            'noEvidenceExplanation' => $version->no_evidence_explanation,
            'acceptedActionPlanVersionId' => $version->accepted_action_plan_version_id,
            'recordedProgressUpdateVersionId' => $version->recorded_progress_update_version_id,
            'preparedBy' => $this->safeUser($version->preparer),
            'submittedBy' => $this->safeUser($version->submitter),
            'submittedAt' => $version->submitted_at?->toISOString(),
            'reviewStartedBy' => $this->safeUser($version->reviewStarter),
            'reviewStartedAt' => $version->review_started_at?->toISOString(),
            'returnedBy' => $this->safeUser($version->returner),
            'returnedAt' => $version->returned_at?->toISOString(),
            'returnReason' => $version->return_reason,
            'revisionReason' => $version->revision_reason,
            'submissionSnapshot' => $version->submission_snapshot,
            'assessment' => $version->assessment ? new CmsTargetDateExtensionAssessmentResource($version->assessment) : null,
            'decision' => $version->decision ? new CmsTargetDateExtensionDecisionResource($version->decision) : null,
            'evidenceLinks' => CmsTargetDateExtensionEvidenceResource::collection($version->whenLoaded('evidenceLinks')),
            'activeEvidenceLinks' => CmsTargetDateExtensionEvidenceResource::collection($version->whenLoaded('activeEvidenceLinks')),
            'lockVersion' => $version->lock_version,
            'isCurrent' => $family && (int) $family->current_version_id === (int) $version->id,
            'isResolved' => $version->status_code === CmsTargetDateExtensionVersion::STATUS_APPROVED
                || $version->status_code === CmsTargetDateExtensionVersion::STATUS_REJECTED,
            'availableActions' => $version->getAttribute('available_actions') ?? [],
            'completeness' => $version->getAttribute('completeness'),
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
