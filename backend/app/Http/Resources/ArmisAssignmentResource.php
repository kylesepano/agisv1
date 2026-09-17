<?php

namespace App\Http\Resources;

use App\Models\ArmisEngagementAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Serializes an ARMIS engagement assignment and its required competencies. */
class ArmisAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ArmisEngagementAssignment $assignment */
        $assignment = $this->resource;

        return [
            'id' => $assignment->id,
            'assignmentFamilyUuid' => $assignment->assignment_family_uuid,
            'auditEngagementId' => $assignment->audit_engagement_id,
            'engagement' => $assignment->engagement?->only(['id', 'engagement_code', 'title', 'status', 'planned_start_date', 'planned_end_date', 'planned_person_days']),
            'resourceProfileId' => $assignment->resource_profile_id,
            'resourceCode' => $assignment->resourceProfile?->resource_code,
            'resourceUser' => $assignment->resourceProfile?->user?->only(['id', 'employee_id', 'name', 'initials']),
            'office' => $assignment->resourceProfile?->office?->only(['id', 'code', 'name']),
            'requirementId' => $assignment->requirement_id,
            'requirement' => $assignment->requirement?->only(['id', 'title', 'status']),
            'versionNumber' => $assignment->version_number,
            'supersedesId' => $assignment->supersedes_id,
            'isCurrentRevision' => (bool) $assignment->is_current_revision,
            'assignmentRoleCode' => $assignment->assignment_role_code,
            'assignedFrom' => $assignment->assigned_from?->toDateString(),
            'assignedUntil' => $assignment->assigned_until?->toDateString(),
            'plannedPersonDays' => (float) $assignment->planned_person_days,
            'status' => $assignment->status,
            'notes' => $assignment->notes,
            'requiredCompetencies' => $assignment->competencies?->map(fn ($item): array => [
                'id' => $item->id,
                'competencyId' => $item->competency_id,
                'code' => $item->competency?->code,
                'label' => $item->competency?->label,
                'minimumProficiency' => $item->minimum_proficiency,
                'notes' => $item->notes,
            ])->values(),
            'submittedBy' => $assignment->submitter?->only(['id', 'employee_id', 'name', 'initials']),
            'submittedAt' => $assignment->submitted_at?->toIso8601String(),
            'reviewedBy' => $assignment->reviewer?->only(['id', 'employee_id', 'name', 'initials']),
            'reviewedAt' => $assignment->reviewed_at?->toIso8601String(),
            'approvedBy' => $assignment->approver?->only(['id', 'employee_id', 'name', 'initials']),
            'approvedAt' => $assignment->approved_at?->toIso8601String(),
            'lockVersion' => $assignment->lock_version,
            'createdAt' => $assignment->created_at?->toIso8601String(),
            'updatedAt' => $assignment->updated_at?->toIso8601String(),
        ];
    }
}
