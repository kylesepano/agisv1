<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Serializes BAICS cycle scope, assignments, readiness, and immutable history. */
class IapBaicsAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'familyUuid' => $this->family_uuid,
            'assessmentCode' => $this->assessment_code,
            'versionNumber' => $this->version_number,
            'assessmentYear' => $this->assessment_year,
            'name' => $this->name,
            'status' => $this->status,
            'responsibleOfficeId' => $this->responsible_office_id,
            'responsibleOffice' => $this->responsibleOffice ? $this->responsibleOffice->only(['id', 'code', 'name']) : null,
            'scopeSummary' => $this->scope_summary,
            'objectives' => $this->objectives,
            'boundaries' => $this->boundaries,
            'exclusions' => $this->exclusions,
            'limitations' => $this->limitations,
            'methodology' => $this->methodology,
            'plannedStartDate' => $this->planned_start_date?->toDateString(),
            'plannedEndDate' => $this->planned_end_date?->toDateString(),
            'reviewDate' => $this->review_date?->toDateString(),
            'reportDate' => $this->report_date?->toDateString(),
            'legacyStatus' => $this->legacy_status,
            'legacyReason' => $this->legacy_reason,
            'legacyExpiresAt' => $this->legacy_expires_at?->toDateString(),
            'preparedBy' => $this->person($this->preparer),
            'submittedBy' => $this->person($this->submitter),
            'reviewedBy' => $this->person($this->reviewer),
            'approvedBy' => $this->person($this->approver),
            'publishedBy' => $this->person($this->publisher),
            'submittedAt' => $this->submitted_at?->toISOString(),
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'approvedAt' => $this->approved_at?->toISOString(),
            'publishedAt' => $this->published_at?->toISOString(),
            'supersedesId' => $this->supersedes_id,
            'isCurrentRevision' => (bool) $this->is_current_revision,
            'isArchived' => $this->trashed(),
            'lockVersion' => $this->lock_version,
            'scopeItems' => $this->whenLoaded('scopeItems', fn () => $this->scopeItems->map(fn ($item) => [
                'id' => $item->id,
                'auditUniverseItemId' => $item->audit_universe_item_id,
                'auditUniverseItem' => $item->auditUniverseItem ? $item->auditUniverseItem->only(['id', 'subject_code', 'name']) : null,
                'officeId' => $item->office_id,
                'office' => $item->office?->only(['id', 'code', 'name']),
                'auditAreaId' => $item->audit_area_id,
                'auditArea' => $item->auditArea?->only(['id', 'code', 'name']),
                'auditFocusId' => $item->audit_focus_id,
                'auditFocus' => $item->auditFocus?->only(['id', 'code', 'name']),
                'sourceSnapshot' => $item->source_snapshot,
                'scopeNotes' => $item->scope_notes,
                'boundaries' => $item->boundaries,
                'exclusions' => $item->exclusions,
                'limitations' => $item->limitations,
            ])->values()),
            'assignments' => $this->whenLoaded('assignments', fn () => $this->assignments->map(fn ($assignment) => [
                'id' => $assignment->id,
                'userId' => $assignment->user_id,
                'user' => $this->person($assignment->user),
                'roleCode' => $assignment->role_code,
                'authorityLevel' => $assignment->authority_level,
                'assignmentReason' => $assignment->assignment_reason,
                'status' => $assignment->status,
                'assignedAt' => $assignment->assigned_at?->toISOString(),
                'endedAt' => $assignment->ended_at?->toISOString(),
                'lockVersion' => $assignment->lock_version,
            ])->values()),
            'versions' => $this->whenLoaded('versions', fn () => $this->versions->map(fn ($version) => [
                'id' => $version->id,
                'assessmentId' => $version->assessment_id,
                'versionNumber' => $version->version_number,
                'status' => $version->status,
                'snapshotHash' => $version->snapshot_hash,
                'reason' => $version->reason,
                'createdBy' => $this->person($version->creator),
                'createdAt' => $version->created_at?->toISOString(),
            ])->values()),
            'components' => $this->whenLoaded('components', fn () => $this->components->map(fn ($component) => [
                'id' => $component->id,
                'componentCode' => $component->component_code,
                'status' => $component->status,
                'conclusion' => $component->conclusion,
                'assessorId' => $component->assessor_id,
                'reviewerId' => $component->reviewer_id,
                'versionNumber' => $component->version_number,
                'lockVersion' => $component->lock_version,
            ])->values()),
            'readiness' => $this->when(isset($this->readiness), $this->readiness),
            'availableActions' => $this->when(isset($this->available_actions), $this->available_actions),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    private function person(mixed $person): ?array
    {
        return $person ? $person->only(['id', 'employee_id', 'name', 'initials', 'position']) : null;
    }
}
