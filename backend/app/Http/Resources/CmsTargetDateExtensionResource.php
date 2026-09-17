<?php

namespace App\Http\Resources;

use App\Models\CmsTargetDateExtensionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsTargetDateExtensionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CmsTargetDateExtensionRequest $family */
        $family = $this->resource;
        $case = $family->case;

        return [
            'id' => $family->id,
            'caseId' => $family->cms_recommendation_case_id,
            'requestSequence' => $family->request_sequence,
            'displayCode' => $family->display_code,
            'baselineEffectiveTargetDate' => $family->baseline_effective_target_date?->toDateString(),
            'currentVersionId' => $family->current_version_id,
            'resolvedVersionId' => $family->resolved_version_id,
            'resolvedAt' => $family->resolved_at?->toISOString(),
            'lockVersion' => $family->lock_version,
            'currentVersion' => $family->currentVersion ? new CmsTargetDateExtensionVersionResource($family->currentVersion) : null,
            'resolvedVersion' => $family->resolvedVersion ? new CmsTargetDateExtensionVersionResource($family->resolvedVersion) : null,
            'versions' => CmsTargetDateExtensionVersionResource::collection($family->whenLoaded('versions')),
            'createdBy' => $family->creator ? [
                'id' => $family->creator->id,
                'employeeId' => $family->creator->employee_id,
                'name' => $family->creator->name,
                'initials' => $family->creator->initials,
            ] : null,
            'caseContext' => $case ? [
                'id' => $case->id,
                'status' => $case->status_code,
                'originalTargetDate' => $case->recommendation?->original_target_implementation_date?->toDateString(),
                'effectiveTargetDate' => $case->effective_target_implementation_date?->toDateString(),
                'responsibleOffice' => $case->leadResponsibleOffice?->only(['id', 'code', 'name', 'acronym']),
            ] : null,
        ];
    }
}
