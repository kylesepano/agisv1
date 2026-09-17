<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Serializes the engagement registry, coverage, lineage, source snapshot, and history. */
class AemsEngagementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'engagementCode' => $this->engagement_code,
            'title' => $this->title,
            'sourceType' => $this->source_type,
            'status' => $this->status,
            'phase' => $this->phase,
            'administrativeStatus' => $this->administrative_status,
            'engagementOfficeId' => $this->engagement_office_id,
            'iapPlanEngagementId' => $this->iap_plan_engagement_id,
            'iapPlanId' => $this->iap_plan_id,
            'iapPrioritizationItemId' => $this->iap_prioritization_item_id,
            'iapRiskAssessmentId' => $this->iap_risk_assessment_id,
            'iapRiskSourceType' => $this->iap_risk_source_type,
            'iapLegacyRiskAssessmentId' => $this->iap_legacy_risk_assessment_id,
            'iapAuditUniverseItemId' => $this->iap_audit_universe_item_id,
            'sourceSnapshot' => $this->source_snapshot,
            'specialAuthorityReference' => $this->special_authority_reference,
            'specialAuthorityTypeCode' => $this->special_authority_type_code,
            'specialAuthorityClass' => $this->special_authority_class,
            'specialAuthorityDate' => $this->special_authority_date?->toDateString(),
            'specialAuthorityApprovedBy' => $this->special_authority_approved_by,
            'auditTypeId' => $this->audit_type_id,
            'engagementApproachId' => $this->engagement_approach_id,
            'background' => $this->background,
            'objectives' => $this->objectives,
            'scope' => $this->scope,
            'scopeBoundaries' => $this->scope_boundaries,
            'scopeLimitations' => $this->scope_limitations,
            'scopeSourceVariance' => $this->scope_source_variance,
            'exclusions' => $this->exclusions,
            'plannedStartDate' => $this->planned_start_date?->toDateString(),
            'plannedEndDate' => $this->planned_end_date?->toDateString(),
            'actualStartDate' => $this->actual_start_date?->toDateString(),
            'actualEndDate' => $this->actual_end_date?->toDateString(),
            'expectedReportDate' => $this->expected_report_date?->toDateString(),
            'plannedPersonDays' => (float) $this->planned_person_days,
            'actualPersonDays' => (float) $this->actual_person_days,
            'lockVersion' => $this->lock_version,
            'isActive' => $this->is_active,
            'isArchived' => $this->trashed(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'auditType' => $this->whenLoaded('auditType', fn () => $this->item($this->auditType)),
            'engagementApproach' => $this->whenLoaded(
                'engagementApproach',
                fn () => $this->item($this->engagementApproach),
            ),
            'engagementOrder' => $this->whenLoaded('engagementOrder', fn () => $this->engagementOrder ? [
                'id' => $this->engagementOrder->id,
                'status' => $this->engagementOrder->status,
                'currentVersionNumber' => $this->engagementOrder->current_version_number,
                'approvedAt' => $this->engagementOrder->approved_at?->toISOString(),
                'issuedAt' => $this->engagementOrder->issued_at?->toISOString(),
                'approver' => $this->engagementOrder->relationLoaded('approver') && $this->engagementOrder->approver ? ['id' => $this->engagementOrder->approver->id, 'name' => $this->engagementOrder->approver->name] : null,
                'issuer' => $this->engagementOrder->relationLoaded('issuer') && $this->engagementOrder->issuer ? ['id' => $this->engagementOrder->issuer->id, 'name' => $this->engagementOrder->issuer->name] : null,
            ] : null),
            'specialAuthorityApprover' => $this->whenLoaded(
                'specialAuthorityApprover',
                fn () => $this->user($this->specialAuthorityApprover),
            ),
            'creator' => $this->whenLoaded('creator', fn () => $this->user($this->creator)),
            'updater' => $this->whenLoaded('updater', fn () => $this->user($this->updater)),
            'offices' => $this->whenLoaded('offices', fn () => $this->offices
                ->map(fn ($office): array => [
                    'id' => $office->id,
                    'code' => $office->code,
                    'name' => $office->name,
                    'isPrimary' => (bool) $office->pivot?->is_primary,
                ])->values()),
            'officeRule' => $this->whenLoaded('offices', fn (): array => [
                'requiredCount' => 1,
                'actualCount' => $this->offices->count(),
                'state' => $this->offices->count() === 0
                    ? 'MISSING'
                    : ($this->offices->count() === 1 ? 'VALID' : 'LEGACY_MULTI_OFFICE'),
            ]),
            'scopeBackfillReview' => $this->whenLoaded('scopeBackfillReview', fn () => $this->scopeBackfillReview ? [
                'officeCount' => $this->scopeBackfillReview->office_count,
                'legacyOfficeIds' => $this->scopeBackfillReview->legacy_office_ids,
                'canonicalOfficeId' => $this->scopeBackfillReview->canonical_office_id,
                'resolutionStatus' => $this->scopeBackfillReview->resolution_status,
                'resolutionNotes' => $this->scopeBackfillReview->resolution_notes,
                'reviewedAt' => $this->scopeBackfillReview->reviewed_at?->toISOString(),
            ] : null),
            'auditAreas' => $this->whenLoaded('auditAreas', fn () => $this->auditAreas
                ->map(fn ($area): array => [
                    'id' => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                    'coverageMetadata' => $this->pivotMetadata($area->pivot?->coverage_metadata),
                ])->values()),
            'auditFocuses' => $this->whenLoaded('auditFocuses', fn () => $this->auditFocuses
                ->map(fn ($focus): array => [
                    'id' => $focus->id,
                    'code' => $focus->code,
                    'name' => $focus->name,
                    'auditAreaId' => $focus->audit_area_id,
                    'coverageMetadata' => $this->pivotMetadata($focus->pivot?->coverage_metadata),
                ])->values()),
            'teamMembers' => $this->whenLoaded('teamMembers', fn () => $this->teamMembers
                ->map(fn ($member): array => [
                    'id' => $member->id,
                    'assignmentRoleCode' => $member->assignment_role_code,
                    'plannedPersonDays' => (float) $member->planned_person_days,
                    'assignedFrom' => $member->assigned_from?->toDateString(),
                    'assignedUntil' => $member->assigned_until?->toDateString(),
                    'isActive' => $member->is_active,
                    'user' => $member->relationLoaded('user')
                        ? $this->user($member->user) : null,
                ])->values()),
            'events' => $this->whenLoaded('events', fn () => $this->events
                ->map(fn ($event): array => [
                    'id' => $event->id,
                    'action' => $event->action,
                    'fromStatus' => $event->from_status,
                    'toStatus' => $event->to_status,
                    'comment' => $event->comment,
                    'oldValues' => $event->old_values,
                    'newValues' => $event->new_values,
                    'createdAt' => $event->created_at?->toISOString(),
                    'actor' => $event->relationLoaded('actor')
                        ? $this->user($event->actor) : null,
                ])->values()),
            'counts' => [
                'teamMembers' => (int) ($this->team_members_count ?? 0),
                'workingPapers' => (int) ($this->working_papers_count ?? 0),
                'findings' => (int) ($this->findings_count ?? 0),
                'reports' => (int) ($this->reports_count ?? 0),
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function item(mixed $item): ?array
    {
        return $item ? [
            'id' => $item->id,
            'code' => $item->code,
            'label' => $item->label,
        ] : null;
    }

    /** @return array<string, mixed>|null */
    private function user(mixed $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'name' => $user->name,
            'initials' => $user->initials,
        ] : null;
    }

    private function pivotMetadata(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
