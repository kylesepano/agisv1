<?php

namespace Tests\Feature\Api;

use App\Models\AuditArea;
use App\Models\AuditLog;
use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisResourceProfile;
use App\Models\IapPlanEngagement;
use App\Models\InternalAuditPlan;
use App\Models\MasterListItem;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IapSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_scheduling_detects_conflicts_tracks_capacity_and_preserves_cancellation_history(): void
    {
        $manager = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        Sanctum::actingAs($manager);
        [$office, $area] = $this->coverage();
        $plan = $this->plan($manager);
        $first = $this->engagement($plan, $office, $area, 'IAP-2028-001');
        $second = $this->engagement($plan, $office, $area, 'IAP-2028-002');
        $members = [
            [
                'userId' => $auditor->id,
                'teamRoleId' => $this->item('IAP_TEAM_ROLE', 'LEAD_AUDITOR')->id,
                'plannedPersonDays' => 8,
            ],
            [
                'userId' => $manager->id,
                'teamRoleId' => $this->item('IAP_TEAM_ROLE', 'REVIEWER')->id,
                'plannedPersonDays' => 2,
            ],
        ];
        foreach ([$auditor, $manager] as $resourceUser) {
            $profile = ArmisResourceProfile::query()
                ->where('user_id', $resourceUser->id)
                ->where('status', 'ACTIVE')
                ->firstOrFail();
            ArmisCapacitySubmission::query()->create([
                'resource_profile_id' => $profile->id,
                'fiscal_year' => 2028,
                'version_number' => 1,
                'available_person_days' => 180,
                'status' => 'APPROVED',
                'is_current_revision' => true,
                'approved_by' => $manager->id,
                'approved_at' => now(),
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
                'lock_version' => 1,
            ]);
        }

        $firstSchedule = $this->putJson("/api/iap/schedules/{$first->id}", [
            'plannedStartDate' => '2028-02-01',
            'plannedEndDate' => '2028-02-28',
            'expectedReportDate' => '2028-03-15',
            'members' => $members,
            'lockVersion' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('data.schedule.scheduleStatus', 'SCHEDULED')
            ->json('data.schedule');

        $this->putJson("/api/iap/schedules/{$second->id}", [
            'plannedStartDate' => '2028-04-01',
            'plannedEndDate' => '2028-04-30',
            'expectedReportDate' => '2028-05-15',
            'members' => $members,
            'lockVersion' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lockVersion');

        $preview = $this->postJson("/api/iap/schedules/{$second->id}/conflicts", [
            'plannedStartDate' => '2028-02-15',
            'plannedEndDate' => '2028-03-15',
            'expectedReportDate' => '2028-03-31',
            'members' => $members,
            'lockVersion' => $firstSchedule['plan']['lockVersion'],
        ])
            ->assertOk()
            ->json('data.conflicts');
        $this->assertContains('AUDITOR_OVERLAP', array_column($preview, 'type'));
        $this->assertContains('OFFICE_OVERLAP', array_column($preview, 'type'));

        $this->putJson("/api/iap/schedules/{$second->id}", [
            'plannedStartDate' => '2028-02-15',
            'plannedEndDate' => '2028-03-15',
            'expectedReportDate' => '2028-03-31',
            'members' => $members,
            'lockVersion' => $firstSchedule['plan']['lockVersion'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conflicts');

        $secondSchedule = $this->putJson("/api/iap/schedules/{$second->id}", [
            'plannedStartDate' => '2028-02-15',
            'plannedEndDate' => '2028-03-15',
            'expectedReportDate' => '2028-03-31',
            'members' => $members,
            'acknowledgeConflicts' => true,
            'lockVersion' => $firstSchedule['plan']['lockVersion'],
        ])->assertOk()->json('data.schedule');

        $this->putJson("/api/iap/schedules/{$second->id}", [
            'plannedStartDate' => '2028-04-01',
            'plannedEndDate' => '2028-04-30',
            'expectedReportDate' => '2028-05-15',
            'members' => $members,
            'lockVersion' => $secondSchedule['plan']['lockVersion'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $rescheduled = $this->putJson("/api/iap/schedules/{$second->id}", [
            'plannedStartDate' => '2028-04-01',
            'plannedEndDate' => '2028-04-30',
            'expectedReportDate' => '2028-05-15',
            'members' => $members,
            'reason' => 'Moved to remove the auditor and office scheduling conflicts.',
            'lockVersion' => $secondSchedule['plan']['lockVersion'],
        ])
            ->assertOk()
            ->assertJsonPath('data.schedule.history.1.action', 'RESCHEDULE')
            ->json('data.schedule');

        $this->postJson("/api/iap/schedules/{$second->id}/cancel", [
            'reason' => 'Management cancelled the audit after a material change in scope.',
            'lockVersion' => $rescheduled['plan']['lockVersion'],
        ])->assertOk();

        $this->assertDatabaseHas('iap_plan_engagements', [
            'id' => $second->id,
            'schedule_status' => 'CANCELLED',
        ]);
        $this->assertDatabaseHas('iap_schedule_events', [
            'plan_engagement_id' => $second->id,
            'action' => 'CANCEL',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'iap.schedule.created',
            'auditable_type' => IapPlanEngagement::class,
            'auditable_id' => $first->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'iap.schedule.rescheduled',
            'auditable_type' => IapPlanEngagement::class,
            'auditable_id' => $second->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'iap.schedule.cancelled',
            'auditable_type' => IapPlanEngagement::class,
            'auditable_id' => $second->id,
        ]);
        $this->assertSame(
            4,
            AuditLog::query()
                ->where('user_id', $manager->id)
                ->whereIn('action', [
                    'iap.schedule.created',
                    'iap.schedule.rescheduled',
                    'iap.schedule.cancelled',
                ])
                ->count(),
        );
        $this->assertSame(
            2,
            IapPlanEngagement::query()->where('plan_id', $plan->id)->count(),
        );

        $this->getJson('/api/iap/schedules?fiscalYear=2028')
            ->assertOk()
            ->assertJsonCount(2, 'data.schedules')
            ->assertJsonPath('data.schedules.1.scheduleStatus', 'CANCELLED');
    }

    private function plan(User $manager): InternalAuditPlan
    {
        return InternalAuditPlan::query()->create([
            'plan_code' => 'IAP-2028',
            'fiscal_year' => 2028,
            'planning_period_type_id' => $this->item('IAP_PLANNING_PERIOD_TYPE', 'ANNUAL')->id,
            'planning_period_start' => '2028-01-01',
            'planning_period_end' => '2028-12-31',
            'title' => '2028 Annual Internal Audit Plan',
            'overall_objective' => 'Schedule risk-based audit coverage.',
            'overall_scope' => 'Selected city operations.',
            'status' => 'DRAFT',
            'revision_number' => 0,
            'is_current_revision' => true,
            'prepared_by' => $manager->id,
            'lock_version' => 1,
            'is_active' => true,
        ]);
    }

    private function engagement(
        InternalAuditPlan $plan,
        Office $office,
        AuditArea $area,
        string $code,
    ): IapPlanEngagement {
        $engagement = IapPlanEngagement::query()->create([
            'plan_id' => $plan->id,
            'engagement_code' => $code,
            'title' => "{$office->code} Audit",
            'engagement_type_id' => $this->item('IAP_ENGAGEMENT_TYPE', 'OPERATIONAL')->id,
            'audit_approach_id' => $this->item('IAP_AUDIT_APPROACH', 'RISK_BASED')->id,
            'priority_id' => $this->item('IAP_PLANNING_PRIORITY', 'HIGH')->id,
            'risk_level_id' => $this->item('RISK_LEVEL', 'HIGH')->id,
            'objectives' => 'Assess control effectiveness.',
            'scope' => 'Selected systems and transactions.',
            'planned_start_date' => '2028-01-01',
            'planned_end_date' => '2028-01-31',
            'estimated_person_days' => 10,
            'sequence_number' => 1,
            'is_active' => true,
        ]);
        $engagement->offices()->sync([$office->id]);
        $engagement->auditAreas()->sync([$area->id]);

        return $engagement;
    }

    /** @return array{Office, AuditArea} */
    private function coverage(): array
    {
        $office = Office::query()->whereHas('auditAreas')->firstOrFail();

        return [$office, $office->auditAreas()->firstOrFail()];
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }

    private function item(string $listCode, string $itemCode): MasterListItem
    {
        return MasterListItem::query()
            ->where('code', $itemCode)
            ->whereHas('masterList', fn ($query) => $query->where('code', $listCode))
            ->firstOrFail();
    }
}
