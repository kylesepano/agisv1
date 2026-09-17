<?php

namespace App\Http\Resources;

use App\Models\ArmisWorkloadAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Serializes an ARMIS planned workload allocation and its revision metadata. */
class ArmisWorkloadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ArmisWorkloadAllocation $workload */
        $workload = $this->resource;

        return [
            'id' => $workload->id,
            'workloadFamilyUuid' => $workload->workload_family_uuid,
            'resourceProfileId' => $workload->resource_profile_id,
            'resourceCode' => $workload->resourceProfile?->resource_code,
            'resourceUser' => $workload->resourceProfile?->user?->only(['id', 'employee_id', 'name', 'initials']),
            'office' => $workload->resourceProfile?->office?->only(['id', 'code', 'name']),
            'versionNumber' => $workload->version_number,
            'supersedesId' => $workload->supersedes_id,
            'isCurrentRevision' => (bool) $workload->is_current_revision,
            'requirementId' => $workload->requirement_id,
            'requirement' => $workload->requirement?->only(['id', 'title', 'status']),
            'sourceModule' => $workload->source_module,
            'sourceType' => $workload->source_type,
            'sourceId' => $workload->source_id,
            'fiscalYear' => $workload->fiscal_year,
            'plannedPersonDays' => (float) $workload->planned_person_days,
            'status' => $workload->status,
            'notes' => $workload->notes,
            'submittedBy' => $workload->submitter?->only(['id', 'employee_id', 'name', 'initials']),
            'submittedAt' => $workload->submitted_at?->toIso8601String(),
            'reviewedBy' => $workload->reviewer?->only(['id', 'employee_id', 'name', 'initials']),
            'reviewedAt' => $workload->reviewed_at?->toIso8601String(),
            'approvedBy' => $workload->approver?->only(['id', 'employee_id', 'name', 'initials']),
            'approvedAt' => $workload->approved_at?->toIso8601String(),
            'lockVersion' => $workload->lock_version,
            'createdAt' => $workload->created_at?->toIso8601String(),
            'updatedAt' => $workload->updated_at?->toIso8601String(),
        ];
    }
}
