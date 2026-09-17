<?php

namespace App\Http\Resources;

use App\Models\ArmisCapacitySubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Serializes an ARMIS annual capacity submission and its revision metadata. */
class ArmisCapacityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ArmisCapacitySubmission $capacity */
        $capacity = $this->resource;

        return [
            'id' => $capacity->id,
            'resourceProfileId' => $capacity->resource_profile_id,
            'resourceCode' => $capacity->resourceProfile?->resource_code,
            'resourceUser' => $capacity->resourceProfile?->user?->only(['id', 'employee_id', 'name', 'initials']),
            'office' => $capacity->resourceProfile?->office?->only(['id', 'code', 'name']),
            'fiscalYear' => $capacity->fiscal_year,
            'versionNumber' => $capacity->version_number,
            'supersedesId' => $capacity->supersedes_id,
            'isCurrentRevision' => (bool) $capacity->is_current_revision,
            'availablePersonDays' => (float) $capacity->available_person_days,
            'status' => $capacity->status,
            'notes' => $capacity->notes,
            'submittedBy' => $capacity->submitter?->only(['id', 'employee_id', 'name', 'initials']),
            'submittedAt' => $capacity->submitted_at?->toIso8601String(),
            'reviewedBy' => $capacity->reviewer?->only(['id', 'employee_id', 'name', 'initials']),
            'reviewedAt' => $capacity->reviewed_at?->toIso8601String(),
            'approvedBy' => $capacity->approver?->only(['id', 'employee_id', 'name', 'initials']),
            'approvedAt' => $capacity->approved_at?->toIso8601String(),
            'lockVersion' => $capacity->lock_version,
            'createdAt' => $capacity->created_at?->toIso8601String(),
            'updatedAt' => $capacity->updated_at?->toIso8601String(),
        ];
    }
}
