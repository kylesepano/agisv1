<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes Annual Plan revisions and their workflow and engagement summaries.
 */
class IapPlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'planCode' => $this->plan_code,
            'fiscalYear' => $this->fiscal_year,
            'planningPeriodTypeId' => $this->planning_period_type_id,
            'prioritizationRunId' => $this->prioritization_run_id,
            'planningPeriodStart' => $this->planning_period_start?->toDateString(),
            'planningPeriodEnd' => $this->planning_period_end?->toDateString(),
            'title' => $this->title,
            'executiveSummary' => $this->executive_summary,
            'planningMethodology' => $this->planning_methodology,
            'overallObjective' => $this->overall_objective,
            'overallScope' => $this->overall_scope,
            'limitations' => $this->limitations,
            'status' => $this->status,
            'revisionNumber' => $this->revision_number,
            'supersedesPlanId' => $this->supersedes_plan_id,
            'isCurrentRevision' => $this->is_current_revision,
            'preparedBy' => $this->prepared_by,
            'coordinatorId' => $this->coordinator_id,
            'submittedAt' => $this->submitted_at?->toISOString(),
            'submittedBy' => $this->submitted_by,
            'approvedAt' => $this->approved_at?->toISOString(),
            'approvedBy' => $this->approved_by,
            'activatedAt' => $this->activated_at?->toISOString(),
            'activatedBy' => $this->activated_by,
            'completedAt' => $this->completed_at?->toISOString(),
            'completedBy' => $this->completed_by,
            'lockVersion' => $this->lock_version,
            'isActive' => $this->is_active,
            'isArchived' => $this->trashed(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];

        if (isset($this->risk_assessments_count)) {
            $data['riskAssessmentCount'] = (int) $this->risk_assessments_count;
        }
        if (isset($this->engagements_count)) {
            $data['engagementCount'] = (int) $this->engagements_count;
        }

        if ($this->relationLoaded('planningPeriodType')) {
            $data['planningPeriodType'] = $this->item($this->planningPeriodType);
        }

        if ($this->relationLoaded('prioritizationRun')) {
            $plannedBySource = $this->relationLoaded('engagements')
                ? $this->engagements
                    ->whereNotNull('prioritization_item_id')
                    ->keyBy('prioritization_item_id')
                : collect();
            $run = $this->prioritizationRun;
            $data['prioritizationRun'] = $run ? [
                'id' => $run->id,
                'runCode' => $run->run_code,
                'name' => $run->name,
                'status' => $run->status,
                'riskPeriod' => $run->relationLoaded('riskPeriod') && $run->riskPeriod ? [
                    'id' => $run->riskPeriod->id,
                    'periodCode' => $run->riskPeriod->period_code,
                    'name' => $run->riskPeriod->name,
                    'assessmentYear' => $run->riskPeriod->assessment_year,
                ] : null,
                'items' => $run->relationLoaded('items')
                    ? $run->items->map(function ($item) use ($plannedBySource): array {
                        $engagement = $plannedBySource->get($item->id);

                        return [
                            'id' => $item->id,
                            'riskAssessmentId' => $item->risk_assessment_id,
                            'auditUniverseItemId' => $item->audit_universe_item_id,
                            'subjectCode' => $item->subject_code,
                            'subjectName' => $item->subject_name,
                            'officeCode' => $item->office_code,
                            'officeName' => $item->office_name,
                            'auditAreaCode' => $item->audit_area_code,
                            'auditAreaName' => $item->audit_area_name,
                            'inherentRiskScore' => (float) $item->inherent_risk_score,
                            'residualRiskScore' => (float) $item->residual_risk_score,
                            'riskLevelCode' => $item->risk_level_code,
                            'riskLevelLabel' => $item->risk_level_label,
                            'priorityScore' => (float) $item->priority_score,
                            'finalRank' => $item->final_rank,
                            'decision' => $item->decision,
                            'decisionReason' => $item->decision_reason,
                            'planningState' => $engagement
                                ? 'PLANNED'
                                : ($item->decision === 'SELECTED'
                                    ? 'UNPLANNED'
                                    : $item->decision),
                            'engagementId' => $engagement?->id,
                        ];
                    })->values()
                    : [],
            ] : null;
        }

        foreach ([
            'preparer' => 'preparer',
            'coordinator' => 'coordinator',
            'submitter' => 'submitter',
            'approver' => 'approver',
            'activator' => 'activator',
            'completer' => 'completer',
        ] as $relation => $key) {
            if ($this->relationLoaded($relation)) {
                $data[$key] = $this->user($this->{$relation});
            }
        }

        if ($this->relationLoaded('riskAssessments')) {
            $data['riskAssessments'] = $this->riskAssessments->map(fn ($risk) => [
                'id' => $risk->id,
                'officeId' => $risk->office_id,
                'office' => $risk->relationLoaded('office') ? [
                    'id' => $risk->office?->id,
                    'code' => $risk->office?->code,
                    'name' => $risk->office?->name,
                ] : null,
                'auditAreaId' => $risk->audit_area_id,
                'auditArea' => $risk->relationLoaded('auditArea') ? [
                    'id' => $risk->auditArea?->id,
                    'code' => $risk->auditArea?->code,
                    'name' => $risk->auditArea?->name,
                ] : null,
                'assessedBy' => $risk->assessed_by,
                'assessmentDate' => $risk->assessment_date?->toDateString(),
                'lastAuditDate' => $risk->last_audit_date?->toDateString(),
                'inherentRiskNotes' => $risk->inherent_risk_notes,
                'controlEnvironmentNotes' => $risk->control_environment_notes,
                'totalWeightedScore' => (float) $risk->total_weighted_score,
                'calculatedRiskLevel' => $risk->relationLoaded('calculatedRiskLevel')
                    ? $this->item($risk->calculatedRiskLevel) : null,
                'overrideRiskLevel' => $risk->relationLoaded('overrideRiskLevel')
                    ? $this->item($risk->overrideRiskLevel) : null,
                'overrideReason' => $risk->override_reason,
                'finalRiskLevel' => $risk->relationLoaded('finalRiskLevel')
                    ? $this->item($risk->finalRiskLevel) : null,
                'justification' => $risk->justification,
                'lockVersion' => $risk->lock_version,
                'scores' => $risk->relationLoaded('scores')
                    ? $risk->scores->map(fn ($score) => [
                        'id' => $score->id,
                        'criterionId' => $score->risk_criterion_id,
                        'criterion' => $score->relationLoaded('criterion')
                            ? $this->item($score->criterion) : null,
                        'weight' => (float) $score->criterion_weight,
                        'rating' => (float) $score->rating,
                        'weightedScore' => (float) $score->weighted_score,
                        'comment' => $score->comment,
                    ])->values()
                    : [],
            ])->values();
        }

        if ($this->relationLoaded('engagements')) {
            $data['engagements'] = $this->engagements->map(fn ($engagement) => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'engagementType' => $engagement->relationLoaded('engagementType')
                    ? $this->item($engagement->engagementType) : null,
                'auditApproach' => $engagement->relationLoaded('auditApproach')
                    ? $this->item($engagement->auditApproach) : null,
                'priority' => $engagement->relationLoaded('priority')
                    ? $this->item($engagement->priority) : null,
                'riskLevel' => $engagement->relationLoaded('riskLevel')
                    ? $this->item($engagement->riskLevel) : null,
                'riskAssessmentId' => $engagement->risk_assessment_id,
                'prioritizationItemId' => $engagement->prioritization_item_id,
                'auditUniverseItemId' => $engagement->audit_universe_item_id,
                'universeRiskAssessmentId' => $engagement->universe_risk_assessment_id,
                'sourceInherentRiskScore' => $engagement->source_inherent_risk_score === null
                    ? null : (float) $engagement->source_inherent_risk_score,
                'sourceResidualRiskScore' => $engagement->source_residual_risk_score === null
                    ? null : (float) $engagement->source_residual_risk_score,
                'sourcePriorityScore' => $engagement->source_priority_score === null
                    ? null : (float) $engagement->source_priority_score,
                'sourceRiskLevelCode' => $engagement->source_risk_level_code,
                'sourceDecision' => $engagement->source_decision,
                'sourceFinalRank' => $engagement->source_final_rank,
                'targetQuarter' => $engagement->target_quarter,
                'importedAt' => $engagement->imported_at?->toISOString(),
                'background' => $engagement->background,
                'objectives' => $engagement->objectives,
                'scope' => $engagement->scope,
                'exclusions' => $engagement->exclusions,
                'auditCriteria' => $engagement->audit_criteria,
                'proposedMethodology' => $engagement->proposed_methodology,
                'plannedStartDate' => $engagement->planned_start_date?->toDateString(),
                'plannedEndDate' => $engagement->planned_end_date?->toDateString(),
                'expectedReportDate' => $engagement->expected_report_date?->toDateString(),
                'scheduleStatus' => $engagement->schedule_status,
                'estimatedPersonDays' => (float) $engagement->estimated_person_days,
                'estimatedCost' => $engagement->estimated_cost === null
                    ? null : (float) $engagement->estimated_cost,
                'sequenceNumber' => $engagement->sequence_number,
                'planningNotes' => $engagement->planning_notes,
                'isActive' => $engagement->is_active,
                'offices' => $engagement->relationLoaded('offices')
                    ? $engagement->offices->map->only(['id', 'code', 'name'])->values()
                    : [],
                'auditAreas' => $engagement->relationLoaded('auditAreas')
                    ? $engagement->auditAreas->map->only(['id', 'code', 'name'])->values()
                    : [],
                'auditFocuses' => $engagement->relationLoaded('auditFocuses')
                    ? $engagement->auditFocuses->map->only(['id', 'code', 'name'])->values()
                    : [],
                'teamMembers' => $engagement->relationLoaded('teamMembers')
                    ? $engagement->teamMembers->map(fn ($member) => [
                        'id' => $member->id,
                        'userId' => $member->user_id,
                        'user' => $member->relationLoaded('user') ? $this->user($member->user) : null,
                        'teamRole' => $member->relationLoaded('teamRole')
                            ? $this->item($member->teamRole) : null,
                        'plannedPersonDays' => (float) $member->planned_person_days,
                        'notes' => $member->assignment_notes,
                    ])->values()
                    : [],
            ])->values();
        }

        if ($this->relationLoaded('workflowEvents')) {
            $data['workflowEvents'] = $this->workflowEvents->map(fn ($event) => [
                'id' => $event->id,
                'action' => $event->action,
                'fromStatus' => $event->from_status,
                'toStatus' => $event->to_status,
                'actor' => $event->relationLoaded('actor') ? $this->user($event->actor) : null,
                'actorRoleCode' => $event->actor_role_code,
                'comment' => $event->comment,
                'oldValues' => $event->old_values,
                'newValues' => $event->new_values,
                'planLockVersion' => $event->plan_lock_version,
                'metadata' => $event->metadata,
                'createdAt' => $event->created_at?->toISOString(),
            ])->values();
        }

        return $data;
    }

    /** @return array<string, mixed>|null */
    private function item(mixed $item): ?array
    {
        if (! $item) {
            return null;
        }

        return [
            'id' => $item->id,
            'code' => $item->code,
            'label' => $item->label,
        ];
    }

    /** @return array<string, mixed>|null */
    private function user(mixed $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'name' => $user->name,
            'initials' => $user->initials,
        ];
    }
}
