<?php

namespace App\Http\Resources;

use App\Models\ArmisAvailabilityPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Serializes an ARMIS availability period and its revision metadata. */
class ArmisAvailabilityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ArmisAvailabilityPeriod $period */
        $period = $this->resource;

        return [
            'id' => $period->id,
            'availabilityFamilyUuid' => $period->availability_family_uuid,
            'resourceProfileId' => $period->resource_profile_id,
            'resourceCode' => $period->resourceProfile?->resource_code,
            'resourceUser' => $period->resourceProfile?->user?->only(['id', 'employee_id', 'name', 'initials']),
            'office' => $period->resourceProfile?->office?->only(['id', 'code', 'name']),
            'versionNumber' => $period->version_number,
            'supersedesId' => $period->supersedes_id,
            'isCurrentRevision' => (bool) $period->is_current_revision,
            'availabilityType' => $period->availability_type,
            'startDate' => $period->start_date?->toDateString(),
            'endDate' => $period->end_date?->toDateString(),
            'personDays' => $period->person_days !== null ? (float) $period->person_days : null,
            'status' => $period->status,
            'notes' => $period->notes,
            'submittedBy' => $period->submitter?->only(['id', 'employee_id', 'name', 'initials']),
            'submittedAt' => $period->submitted_at?->toIso8601String(),
            'reviewedBy' => $period->reviewer?->only(['id', 'employee_id', 'name', 'initials']),
            'reviewedAt' => $period->reviewed_at?->toIso8601String(),
            'approvedBy' => $period->approver?->only(['id', 'employee_id', 'name', 'initials']),
            'approvedAt' => $period->approved_at?->toIso8601String(),
            'lockVersion' => $period->lock_version,
            'createdAt' => $period->created_at?->toIso8601String(),
            'updatedAt' => $period->updated_at?->toIso8601String(),
        ];
    }
}
