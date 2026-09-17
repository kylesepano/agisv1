<?php

namespace Database\Seeders;

use App\Models\IapAuditorCapacity;
use App\Models\IapAuditorSkill;
use App\Models\IapAuditorUnavailability;
use App\Models\IapEngagementSkillRequirement;
use App\Models\IapEngagementTeamMember;
use App\Models\IapPlanEngagement;
use App\Models\IapPrioritizationRun;
use App\Models\IapScheduleEvent;
use App\Models\InternalAuditPlan;
use App\Models\MasterListItem;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds plan engagements, teams, and representative schedules. The capacity,
 * skills, and availability rows remain historical IAP lineage and are copied
 * into ARMIS by ArmisResourcePlanningSeeder.
 */
class IapSchedulingSeeder extends Seeder
{
    public const DEMO_PLAN_CODE = 'IAP-2026-DEMO';

    public static function clearDemoPlan(): void
    {
        InternalAuditPlan::withTrashed()
            ->where('plan_code', self::DEMO_PLAN_CODE)
            ->get()
            ->each(function (InternalAuditPlan $plan): void {
                $engagementIds = IapPlanEngagement::withTrashed()
                    ->where('plan_id', $plan->id)
                    ->pluck('id');
                IapScheduleEvent::query()
                    ->whereIn('plan_engagement_id', $engagementIds)
                    ->delete();
                IapEngagementTeamMember::query()
                    ->whereIn('plan_engagement_id', $engagementIds)
                    ->delete();
                IapPlanEngagement::withTrashed()
                    ->whereIn('id', $engagementIds)
                    ->get()
                    ->each->forceDelete();
                $plan->forceDelete();
            });
    }

    public function run(): void
    {
        $renderSafe = (bool) config('demo.full_render_seeders');
        if (! $renderSafe) {
            self::clearDemoPlan();
        }
        $management = User::query()
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'iap.manage_universe'))
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'aems.review.own_submission'))
            ->first();
        $auditor = User::query()
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'iap.assign_team'))
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'aems.aeo.prepare'))
            ->when($management, fn ($query) => $query->where('id', '<>', $management->id))
            ->first();
        $run = IapPrioritizationRun::query()
            ->where('run_code', 'PRIO-2025')
            ->where('status', 'FINALIZED')
            ->with('items.auditUniverseItem')
            ->first();
        if (! $management || ! $auditor || ! $run) {
            return;
        }
        $existingDemoPlan = InternalAuditPlan::withTrashed()
            ->where('plan_code', self::DEMO_PLAN_CODE)
            ->first();
        if ($renderSafe && $existingDemoPlan) {
            if ($existingDemoPlan->trashed()) {
                $existingDemoPlan->restore();
            }
            return;
        }
        if (InternalAuditPlan::query()
            ->where('fiscal_year', 2026)
            ->where('is_current_revision', true)
            ->exists()) {
            return;
        }

        $annual = $this->item('IAP_PLANNING_PERIOD_TYPE', 'ANNUAL');
        $type = $this->item('IAP_ENGAGEMENT_TYPE', 'OPERATIONAL');
        $approach = $this->item('IAP_AUDIT_APPROACH', 'RISK_BASED');
        $lead = $this->item('IAP_TEAM_ROLE', 'LEAD_AUDITOR');
        $reviewer = $this->item('IAP_TEAM_ROLE', 'REVIEWER');
        $dataAnalytics = $this->item('IAP_AUDITOR_SPECIALIZATION', 'DATA_ANALYTICS');
        $compliance = $this->item('IAP_AUDITOR_SPECIALIZATION', 'COMPLIANCE');
        $governance = $this->item('IAP_AUDITOR_SPECIALIZATION', 'GOVERNANCE_RISK');
        $training = $this->item('IAP_UNAVAILABILITY_TYPE', 'TRAINING');
        if (! $annual || ! $type || ! $approach || ! $lead || ! $reviewer) {
            return;
        }

        $plan = InternalAuditPlan::query()->create([
            'plan_code' => self::DEMO_PLAN_CODE,
            'fiscal_year' => 2026,
            'planning_period_type_id' => $annual->id,
            'prioritization_run_id' => $run->id,
            'planning_period_start' => '2026-01-01',
            'planning_period_end' => '2026-12-31',
            'title' => '2026 Demonstration Annual Internal Audit Plan',
            'executive_summary' => 'Demonstration risk-based annual plan generated from the finalized Audit Universe prioritization.',
            'planning_methodology' => 'Residual-risk ranking, available person-days, office coordination, and conflict-checked scheduling.',
            'overall_objective' => 'Provide timely assurance over the highest-priority city operations.',
            'overall_scope' => 'Selected Audit Universe subjects carried from PRIO-2025.',
            'status' => 'DRAFT',
            'revision_number' => 0,
            'is_current_revision' => true,
            'prepared_by' => $management->id,
            'coordinator_id' => $management->id,
            'lock_version' => 1,
            'is_active' => true,
        ]);

        $selected = $run->items
            ->where('decision', 'SELECTED')
            ->take(2)
            ->values();
        foreach ($selected as $index => $source) {
            $universe = $source->auditUniverseItem;
            if (! $universe?->responsible_office_id || ! $universe?->primary_audit_area_id) {
                continue;
            }
            $sequence = $index + 1;
            $personDays = $index === 0 ? 20 : 18;
            $dates = $index === 0
                ? ['2026-08-03', '2026-08-28', '2026-09-15']
                : ['2026-09-07', '2026-10-02', '2026-10-20'];
            $priority = $this->item(
                'IAP_PLANNING_PRIORITY',
                $source->risk_level_code === 'CRITICAL' ? 'IMMEDIATE' : 'HIGH',
            );
            $risk = $this->item('RISK_LEVEL', $source->risk_level_code);
            if (! $priority || ! $risk) {
                continue;
            }

            $engagement = IapPlanEngagement::query()->create([
                'plan_id' => $plan->id,
                'engagement_code' => sprintf('IAP-2026-%03d', $sequence),
                'title' => $source->subject_name,
                'engagement_type_id' => $type->id,
                'audit_approach_id' => $approach->id,
                'priority_id' => $priority->id,
                'risk_level_id' => $risk->id,
                'prioritization_item_id' => $source->id,
                'audit_universe_item_id' => $source->audit_universe_item_id,
                'universe_risk_assessment_id' => $source->risk_assessment_id,
                'source_inherent_risk_score' => $source->inherent_risk_score,
                'source_residual_risk_score' => $source->residual_risk_score,
                'source_priority_score' => $source->priority_score,
                'source_risk_level_code' => $source->risk_level_code,
                'source_decision' => $source->decision,
                'source_final_rank' => $source->final_rank,
                'background' => 'Selected from the finalized 2025 Audit Universe prioritization.',
                'objectives' => 'Assess whether key governance and operational controls address the prioritized risks.',
                'scope' => 'The responsible office, primary audit area, relevant systems, records, and 2026 transactions.',
                'proposed_methodology' => 'Risk-based interviews, walkthroughs, control testing, document review, and targeted data analysis.',
                'planned_start_date' => $dates[0],
                'planned_end_date' => $dates[1],
                'expected_report_date' => $dates[2],
                'schedule_status' => 'SCHEDULED',
                'estimated_person_days' => $personDays,
                'sequence_number' => $sequence,
                'target_quarter' => $index === 0 ? 3 : 4,
                'imported_at' => now()->subDays(30),
                'imported_by' => $management->id,
                'scheduled_at' => now()->subDays(20),
                'scheduled_by' => $management->id,
                'is_active' => true,
            ]);
            $engagement->offices()->sync([$universe->responsible_office_id]);
            $engagement->auditAreas()->sync([$universe->primary_audit_area_id]);
            $leadDays = round($personDays * .8, 2);
            foreach ([
                [$auditor, $lead, $leadDays],
                [$management, $reviewer, $personDays - $leadDays],
            ] as [$member, $role, $days]) {
                IapEngagementTeamMember::query()->create([
                    'plan_engagement_id' => $engagement->id,
                    'user_id' => $member->id,
                    'team_role_id' => $role->id,
                    'planned_person_days' => $days,
                ]);
            }
            IapScheduleEvent::query()->create([
                'plan_engagement_id' => $engagement->id,
                'action' => 'SCHEDULE',
                'from_status' => 'UNSCHEDULED',
                'to_status' => 'SCHEDULED',
                'new_start_date' => $dates[0],
                'new_end_date' => $dates[1],
                'new_expected_report_date' => $dates[2],
                'new_team' => [
                    ['userId' => $auditor->id, 'teamRoleCode' => 'LEAD_AUDITOR', 'plannedPersonDays' => $leadDays],
                    ['userId' => $management->id, 'teamRoleCode' => 'REVIEWER', 'plannedPersonDays' => $personDays - $leadDays],
                ],
                'reason' => 'Initial demonstration schedule based on priority rank and available capacity.',
                'actor_id' => $management->id,
            ]);
            $requiredSkill = $index === 0 ? $dataAnalytics : $compliance;
            if ($requiredSkill) {
                IapEngagementSkillRequirement::query()->create([
                    'plan_engagement_id' => $engagement->id,
                    'specialization_id' => $requiredSkill->id,
                    'minimum_auditors' => 1,
                    'minimum_proficiency' => 'INTERMEDIATE',
                    'notes' => 'Demonstration technical requirement carried into resource planning.',
                ]);
            }
        }

        foreach ([[$auditor, 180], [$management, 120]] as [$user, $days]) {
            IapAuditorCapacity::query()->updateOrCreate(
                ['fiscal_year' => 2026, 'user_id' => $user->id],
                [
                    'available_person_days' => $days,
                    'notes' => 'Demonstration IAP capacity baseline.',
                    'set_by' => $management->id,
                ],
            );
        }

        foreach ([
            [$auditor, $dataAnalytics, 'ADVANCED'],
            [$auditor, $compliance, 'ADVANCED'],
            [$management, $governance, 'EXPERT'],
            [$management, $compliance, 'ADVANCED'],
        ] as [$user, $specialization, $level]) {
            if (! $specialization) {
                continue;
            }
            IapAuditorSkill::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'specialization_id' => $specialization->id,
                ],
                [
                    'proficiency_level' => $level,
                    'notes' => 'Demonstration verified IAP specialization.',
                    'verified_by' => $management->id,
                    'verified_at' => now(),
                ],
            );
        }

        if ($training) {
            $attributes = [
                'unavailability_type_id' => $training->id,
                'start_date' => '2026-07-13',
                'end_date' => '2026-07-17',
                'notes' => 'Temporary ARMIS-compatible demonstration availability record.',
                'created_by' => $management->id,
            ];
            if ($renderSafe) {
                $unavailability = IapAuditorUnavailability::withTrashed()->updateOrCreate(
                    [
                        'user_id' => $auditor->id,
                        'title' => 'Government audit data analytics training',
                    ],
                    $attributes,
                );
                if ($unavailability->trashed()) {
                    $unavailability->restore();
                }
            } else {
                IapAuditorUnavailability::withTrashed()
                    ->where('user_id', $auditor->id)
                    ->where('title', 'Government audit data analytics training')
                    ->get()
                    ->each->forceDelete();
                IapAuditorUnavailability::query()->create([
                    'user_id' => $auditor->id,
                    'title' => 'Government audit data analytics training',
                    ...$attributes,
                ]);
            }
        }
    }

    private function item(string $listCode, string $itemCode): ?MasterListItem
    {
        return MasterListItem::query()
            ->where('code', $itemCode)
            ->whereHas('masterList', fn ($query) => $query->where('code', $listCode))
            ->first();
    }
}
