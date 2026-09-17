<?php

namespace App\Http\Resources;

use App\Models\ArmisActualPersonDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Serializes an ARMIS actual person-day submission and revision metadata. */
class ArmisActualPersonDayResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ArmisActualPersonDay $actual */
        $actual = $this->resource;

        return [
            'id' => $actual->id,
            'actualFamilyUuid' => $actual->actual_family_uuid,
            'resourceProfileId' => $actual->resource_profile_id,
            'assignmentId' => $actual->assignment_id,
            'engagement' => $actual->assignment?->engagement?->only(['id', 'engagement_code', 'title', 'status']),
            'resourceCode' => $actual->assignment?->resourceProfile?->resource_code,
            'resourceUser' => $actual->assignment?->resourceProfile?->user?->only(['id', 'employee_id', 'name', 'initials']),
            'sourceModule' => $actual->source_module,
            'sourceType' => $actual->source_type,
            'sourceId' => $actual->source_id,
            'periodStart' => $actual->period_start?->toDateString(),
            'periodEnd' => $actual->period_end?->toDateString(),
            'versionNumber' => $actual->version_number,
            'supersedesId' => $actual->supersedes_id,
            'isCurrentRevision' => (bool) $actual->is_current_revision,
            'actualPersonDays' => (float) $actual->actual_person_days,
            'plannedPersonDays' => (float) ($actual->assignment?->planned_person_days ?? 0),
            'status' => $actual->status,
            'notes' => $actual->notes,
            'varianceReason' => $actual->variance_reason,
            'submittedBy' => $actual->submitter?->only(['id', 'employee_id', 'name', 'initials']),
            'submittedAt' => $actual->submitted_at?->toIso8601String(),
            'reviewedBy' => $actual->reviewer?->only(['id', 'employee_id', 'name', 'initials']),
            'reviewedAt' => $actual->reviewed_at?->toIso8601String(),
            'approvedBy' => $actual->approver?->only(['id', 'employee_id', 'name', 'initials']),
            'approvedAt' => $actual->approved_at?->toIso8601String(),
            'lockVersion' => $actual->lock_version,
            'createdAt' => $actual->created_at?->toIso8601String(),
            'updatedAt' => $actual->updated_at?->toIso8601String(),
        ];
    }
}
