<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class CmsActionPlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $case = $this->case;
        $intake = $case?->recommendation;
        $currentVersion = $this->whenLoaded('currentVersion');
        $currentVisible = $currentVersion
            && ! $currentVersion instanceof MissingValue;

        return [
            'id' => $this->id,
            'caseId' => $this->cms_recommendation_case_id,
            'displayCode' => sprintf(
                'CAP-CMS-REC-%06d',
                $this->cms_recommendation_case_id,
            ),
            'ownerOffice' => $this->whenLoaded(
                'ownerOffice',
                fn () => $this->ownerOffice?->only(['id', 'code', 'name', 'acronym']),
            ),
            'createdBy' => $this->whenLoaded('creator', fn () => $this->safeUser($this->creator)),
            'currentVersionId' => $currentVisible ? $this->current_version_id : null,
            'acceptedVersionId' => $this->accepted_version_id,
            'lockVersion' => $this->lock_version,
            'currentVersion' => $currentVisible
                ? new CmsActionPlanVersionResource($currentVersion)
                : null,
            'acceptedVersion' => $this->accepted_version_id
                ? new CmsActionPlanVersionResource($this->whenLoaded('acceptedVersion'))
                : null,
            'versions' => CmsActionPlanVersionResource::collection(
                $this->whenLoaded('versions'),
            ),
            'caseContext' => $case ? [
                'id' => $case->id,
                'cmsRecommendationCode' => sprintf('CMS-REC-%06d', $case->id),
                'status' => $case->status_code,
                'recommendationCode' => $intake?->recommendation_code,
                'recommendation' => data_get(
                    $intake?->source_snapshot,
                    'recommendation.wording',
                ),
                'responsibleOffice' => $case->leadResponsibleOffice?->only([
                    'id', 'code', 'name', 'acronym',
                ]),
                'originalTargetDate' => $intake
                    ?->original_target_implementation_date
                    ?->toDateString(),
                'effectiveTargetDate' => $case
                    ->effective_target_implementation_date
                    ?->toDateString(),
                'currentMonitor' => $case->currentAssignment?->user
                    ? $this->safeUser($case->currentAssignment->user)
                    : null,
            ] : null,
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
