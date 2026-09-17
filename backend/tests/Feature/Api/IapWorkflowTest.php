<?php

namespace Tests\Feature\Api;

use App\Models\AuditArea;
use App\Models\IapEngagementSkillRequirement;
use App\Models\IapPlanEngagement;
use App\Models\IapWorkflowEvent;
use App\Models\InternalAuditPlan;
use App\Models\MasterListItem;
use App\Models\Office;
use App\Models\User;
use App\Services\ArmisResourceBackfillService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\IapSchedulingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IapWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_plan_visibility_and_mutation_are_scoped_by_role_and_assignment(): void
    {
        Sanctum::actingAs($this->user('departmenthead'));
        $plan = $this->createPlan(2027, $this->user('auditor')->id);
        $auditor = $this->user('auditor');
        $outsideAuditor = User::query()->create([
            'role_id' => $auditor->role_id,
            'office_id' => $this->user('auditee')->office_id,
            'employee_id' => 'OUTSIDE-AUD-001',
            'username' => 'employee:outside-aud-001',
            'email' => 'outside-auditor@agis.local',
            'first_name' => 'Outside',
            'last_name' => 'Auditor',
            'name' => 'Outside Auditor',
            'initials' => 'OA',
            'position' => 'Internal Auditor',
            'employment_type' => 'Permanent',
            'password' => Hash::make('lala'),
            'is_active' => true,
        ]);

        Sanctum::actingAs($auditor);
        $this->getJson("/api/iap/plans/{$plan['id']}")
            ->assertOk()
            ->assertJsonPath('data.plan.status', 'DRAFT');

        Sanctum::actingAs($outsideAuditor);
        $this->getJson("/api/iap/plans/{$plan['id']}")->assertForbidden();
        $this->getJson('/api/iap/plans')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);

        Sanctum::actingAs($this->user('mayor'));
        $this->getJson("/api/iap/plans/{$plan['id']}")->assertForbidden();
        $this->getJson('/api/iap/plans')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);

        Sanctum::actingAs($this->user('auditee'));
        $this->getJson('/api/iap/plans')->assertForbidden();

        Sanctum::actingAs($this->user('agisadmin'));
        $this->getJson("/api/iap/plans/{$plan['id']}")->assertOk();
        $this->putJson("/api/iap/plans/{$plan['id']}", [
            ...$this->planPayload(2027, $this->user('auditor')->id),
            'lockVersion' => 1,
        ])->assertForbidden();

        InternalAuditPlan::query()->findOrFail($plan['id'])->forceFill([
            'status' => 'APPROVED',
            'approved_at' => now(),
            'approved_by' => $this->user('departmenthead')->id,
        ])->save();

        Sanctum::actingAs($outsideAuditor);
        $this->getJson("/api/iap/plans/{$plan['id']}")->assertForbidden();

        Sanctum::actingAs($this->user('mayor'));
        $this->getJson("/api/iap/plans/{$plan['id']}")->assertOk();
        $this->putJson("/api/iap/plans/{$plan['id']}", [])
            ->assertForbidden();
        $this->deleteJson("/api/iap/plans/{$plan['id']}")
            ->assertForbidden();
    }

    public function test_cias_management_controls_annual_plan_approval(): void
    {
        $plan = InternalAuditPlan::query()
            ->where('plan_code', IapSchedulingSeeder::DEMO_PLAN_CODE)
            ->firstOrFail();
        $auditor = $this->user('auditor');
        $management = $this->user('departmenthead');
        $plan->forceFill([
            'status' => 'PENDING_REVIEW',
            'submitted_at' => now(),
            'submitted_by' => $auditor->id,
            'lock_version' => 2,
        ])->save();

        Sanctum::actingAs($auditor);
        $this->postJson("/api/iap/plans/{$plan->id}/transitions/approve", [
            'lockVersion' => 2,
            'comment' => 'An auditor must not approve the annual plan.',
        ])->assertForbidden();

        Sanctum::actingAs($this->user('agisadmin'));
        $this->postJson("/api/iap/plans/{$plan->id}/transitions/approve", [
            'lockVersion' => 2,
            'comment' => 'An AGIS administrator must not approve audit content.',
        ])->assertForbidden();

        Sanctum::actingAs($management);
        $this->postJson("/api/iap/plans/{$plan->id}/transitions/approve", [
            'lockVersion' => 2,
            'comment' => 'CIAS Management approved the independently submitted plan.',
        ])
            ->assertOk()
            ->assertJsonPath('data.plan.status', 'APPROVED');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $management->id,
            'action' => 'iap.plan.approve',
            'auditable_id' => $plan->id,
        ]);
    }

    public function test_risk_engagement_and_team_rules_build_a_complete_plan(): void
    {
        Sanctum::actingAs($this->user('departmenthead'));
        $plan = $this->createPlan(2027, $this->user('auditor')->id);
        [$office, $area] = $this->coverage();

        $risk = $this->postJson("/api/iap/plans/{$plan['id']}/risk-assessments", [
            'officeId' => $office->id,
            'auditAreaId' => $area->id,
            'assessmentDate' => '2026-10-15',
            'justification' => 'Material public-service and control exposure requires assurance coverage.',
            'scores' => $this->riskScores(4),
            'lockVersion' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.riskAssessment.totalWeightedScore', 4)
            ->assertJsonPath('data.riskAssessment.finalRiskLevel.code', 'CRITICAL')
            ->json('data.riskAssessment');

        $engagement = $this->postJson("/api/iap/plans/{$plan['id']}/engagements", [
            ...$this->engagementPayload($office, $area, $risk['id']),
            'lockVersion' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('data.engagement.engagementCode', 'IAP-2027-001')
            ->json('data.engagement');

        $this->putJson("/api/iap/plans/{$plan['id']}/engagements/{$engagement['id']}/team", [
            'members' => [
                [
                    'userId' => $this->user('auditor')->id,
                    'teamRoleId' => $this->item('IAP_TEAM_ROLE', 'LEAD_AUDITOR')->id,
                    'plannedPersonDays' => 12,
                ],
                [
                    'userId' => $this->user('departmenthead')->id,
                    'teamRoleId' => $this->item('IAP_TEAM_ROLE', 'REVIEWER')->id,
                    'plannedPersonDays' => 3,
                ],
            ],
            'lockVersion' => 3,
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data.engagement.teamMembers');

        $this->getJson("/api/iap/plans/{$plan['id']}/completeness")
            ->assertOk()
            ->assertJsonPath('data.completeness.complete', true)
            ->assertJsonCount(0, 'data.completeness.errors');
    }

    public function test_archived_risk_assessments_remain_visible_and_recoverable_to_planners(): void
    {
        Sanctum::actingAs($this->user('departmenthead'));
        $plan = $this->createPlan(2028, $this->user('auditor')->id);
        [$office, $area] = $this->coverage();

        $risk = $this->postJson("/api/iap/plans/{$plan['id']}/risk-assessments", [
            'officeId' => $office->id,
            'auditAreaId' => $area->id,
            'assessmentDate' => '2027-10-15',
            'justification' => 'The assessment is retained to verify recoverable planning records.',
            'scores' => $this->riskScores(3),
            'lockVersion' => 1,
        ])->assertCreated()->json('data.riskAssessment');

        $this->deleteJson("/api/iap/plans/{$plan['id']}/risk-assessments/{$risk['id']}")
            ->assertOk();

        $this->getJson("/api/iap/plans/{$plan['id']}/risk-assessments?includeArchived=1")
            ->assertOk()
            ->assertJsonCount(1, 'data.riskAssessments')
            ->assertJsonPath('data.riskAssessments.0.isArchived', true)
            ->assertJsonPath('data.riskAssessments.0.office.code', $office->code)
            ->assertJsonPath('data.riskAssessments.0.auditArea.code', $area->code)
            ->assertJsonCount(10, 'data.riskAssessments.0.scores');

        $this->postJson("/api/iap/plans/{$plan['id']}/risk-assessments/{$risk['id']}/restore")
            ->assertOk()
            ->assertJsonPath('data.riskAssessment.isArchived', false);
    }

    public function test_incomplete_plan_cannot_be_submitted_for_approval(): void
    {
        Sanctum::actingAs($this->user('departmenthead'));
        $plan = $this->createPlan(2030, $this->user('auditor')->id);

        $this->postJson("/api/iap/plans/{$plan['id']}/transitions/submit", [
            'lockVersion' => $plan['lockVersion'],
            'comment' => 'Submitting the initial plan for review.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan');
    }

    public function test_workflow_enforces_completeness_comments_locks_and_separation_of_duties(): void
    {
        Sanctum::actingAs($this->user('departmenthead'));
        $plan = $this->buildCompletePlan(2027);

        $this->postJson("/api/iap/plans/{$plan->id}/transitions/submit", [
            'lockVersion' => $plan->lock_version,
            'comment' => 'Submitted after completing the annual plan review checklist.',
        ])
            ->assertOk()
            ->assertJsonPath('data.plan.status', 'PENDING_REVIEW');

        $pending = $plan->fresh();
        $this->postJson("/api/iap/plans/{$plan->id}/transitions/approve", [
            'lockVersion' => $pending->lock_version,
            'comment' => 'Attempting approval of the submitted plan.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('approver');

        Sanctum::actingAs($this->user('admin'));
        $this->postJson("/api/iap/plans/{$plan->id}/transitions/return", [
            'lockVersion' => $pending->lock_version,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('comment');

        $this->postJson("/api/iap/plans/{$plan->id}/transitions/return", [
            'lockVersion' => $pending->lock_version,
            'comment' => 'Clarify the planned coverage and resubmit.',
        ])
            ->assertOk()
            ->assertJsonPath('data.plan.status', 'RETURNED_FOR_REVISION');

        Sanctum::actingAs($this->user('departmenthead'));
        $returned = $plan->fresh();
        $this->postJson("/api/iap/plans/{$plan->id}/transitions/resubmit", [
            'lockVersion' => $returned->lock_version - 1,
            'comment' => 'Coverage clarification completed.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('lockVersion');

        $this->postJson("/api/iap/plans/{$plan->id}/transitions/resubmit", [
            'lockVersion' => $returned->lock_version,
            'comment' => 'Coverage clarification completed.',
        ])
            ->assertOk()
            ->assertJsonPath('data.plan.status', 'RESUBMITTED');

        Sanctum::actingAs($this->user('admin'));
        $resubmitted = $plan->fresh();
        $this->postJson("/api/iap/plans/{$plan->id}/transitions/approve", [
            'lockVersion' => $resubmitted->lock_version,
            'comment' => 'Approved for implementation.',
        ])
            ->assertOk()
            ->assertJsonPath('data.plan.status', 'APPROVED');

        $approved = $plan->fresh();
        $this->postJson("/api/iap/plans/{$plan->id}/transitions/activate", [
            'lockVersion' => $approved->lock_version,
            'comment' => 'Approved plan authorized for implementation.',
        ])
            ->assertOk()
            ->assertJsonPath('data.plan.status', 'ACTIVE');

        $active = $plan->fresh();
        $this->postJson("/api/iap/plans/{$plan->id}/transitions/complete", [
            'lockVersion' => $active->lock_version,
            'comment' => 'All planned engagements are complete.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('completionConfirmed');

        $this->postJson("/api/iap/plans/{$plan->id}/transitions/complete", [
            'lockVersion' => $active->lock_version,
            'comment' => 'All planned engagements are complete.',
            'completionConfirmed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.plan.status', 'COMPLETED');

        $this->assertDatabaseCount('iap_workflow_events', 7);
        IapWorkflowEvent::query()->each(function (IapWorkflowEvent $event): void {
            $this->assertNotNull($event->actor_id);
            $this->assertNotNull($event->created_at);
            $this->assertNotSame('', trim((string) $event->comment));
            $this->assertNotNull($event->old_values);
            $this->assertNotNull($event->new_values);
        });
        $this->assertDatabaseHas('audit_logs', ['action' => 'iap.plan.approve']);
        $this->assertDatabaseHas('iap_comments', ['body' => 'Clarify the planned coverage and resubmit.']);
    }

    public function test_revision_clones_planning_records_and_archive_is_recoverable(): void
    {
        Sanctum::actingAs($this->user('departmenthead'));
        $source = $this->buildCompletePlan(2027);
        $sourceEngagement = $source->engagements()->with('teamMembers')->firstOrFail();
        $members = $sourceEngagement->teamMembers->map(fn ($member) => [
            'userId' => $member->user_id,
            'teamRoleId' => $member->team_role_id,
            'plannedPersonDays' => (float) $member->planned_person_days,
        ])->values()->all();
        $specialization = $this->item('IAP_AUDITOR_SPECIALIZATION', 'COMPLIANCE');
        $this->putJson("/api/iap/resources/engagements/{$sourceEngagement->id}/requirements", [
            'requirements' => [[
                'specializationId' => $specialization->id,
                'minimumAuditors' => 1,
                'minimumProficiency' => 'INTERMEDIATE',
                'notes' => 'Required for the approved compliance audit schedule.',
            ]],
        ])->assertStatus(409)->assertJsonPath('sourceOfTruth', 'ARMIS');
        // Preserve this row as historical IAP lineage, then copy the demand
        // into ARMIS—the current resource authority used by scheduling.
        IapEngagementSkillRequirement::query()->create([
            'plan_engagement_id' => $sourceEngagement->id,
            'specialization_id' => $specialization->id,
            'minimum_auditors' => 1,
            'minimum_proficiency' => 'INTERMEDIATE',
            'notes' => 'Required for the approved compliance audit schedule.',
        ]);
        $this->app->make(ArmisResourceBackfillService::class)
            ->backfill($this->user('departmenthead'), true);
        $this->putJson("/api/iap/schedules/{$sourceEngagement->id}", [
            'plannedStartDate' => '2027-02-01',
            'plannedEndDate' => '2027-03-31',
            'expectedReportDate' => '2027-04-21',
            'members' => $members,
            'lockVersion' => $source->lock_version,
            'acknowledgeConflicts' => true,
        ])->assertOk();
        $source->refresh();
        $this->postJson("/api/iap/plans/{$source->id}/transitions/submit", [
            'lockVersion' => $source->lock_version,
            'comment' => 'Submitted as a complete annual plan.',
        ])->assertOk();

        Sanctum::actingAs($this->user('admin'));
        $source->refresh();
        $this->postJson("/api/iap/plans/{$source->id}/transitions/approve", [
            'lockVersion' => $source->lock_version,
            'comment' => 'Approved after independent management review.',
        ])->assertOk();

        $source->refresh();
        $this->putJson("/api/iap/plans/{$source->id}", [
            ...$this->planPayload(2027, $this->user('auditor')->id),
            'title' => 'Unauthorized approved-plan change',
            'lockVersion' => $source->lock_version,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->putJson("/api/iap/schedules/{$sourceEngagement->id}", [
            'plannedStartDate' => '2027-04-01',
            'plannedEndDate' => '2027-05-31',
            'expectedReportDate' => '2027-06-21',
            'members' => $members,
            'reason' => 'Attempted direct change after approval.',
            'lockVersion' => $source->lock_version,
            'acknowledgeConflicts' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->putJson("/api/iap/resources/engagements/{$sourceEngagement->id}/requirements", [
            'requirements' => [],
        ])->assertStatus(409)->assertJsonPath('sourceOfTruth', 'ARMIS');

        $revision = $this->postJson("/api/iap/plans/{$source->id}/revisions", [
            'lockVersion' => $source->lock_version,
            'reason' => 'A material change in city risk priorities requires a revised plan.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.plan.status', 'DRAFT')
            ->assertJsonPath('data.plan.revisionNumber', 1)
            ->json('data.plan');

        $this->assertDatabaseHas('internal_audit_plans', [
            'id' => $source->id,
            'is_current_revision' => false,
        ]);
        $this->assertDatabaseHas('internal_audit_plans', [
            'id' => $revision['id'],
            'plan_code' => 'IAP-2027-R01',
            'is_current_revision' => true,
        ]);
        $this->assertDatabaseCount('iap_risk_assessments', 2);
        $revisionEngagementIds = IapPlanEngagement::query()
            ->whereIn('plan_id', [$source->id, $revision['id']])
            ->pluck('id');
        $this->assertCount(2, $revisionEngagementIds);
        $this->assertSame(
            4,
            $this->getConnection()->table('iap_engagement_team_members')
                ->whereIn('plan_engagement_id', $revisionEngagementIds)
                ->count(),
        );
        $revisionEngagement = IapPlanEngagement::query()
            ->where('plan_id', $revision['id'])
            ->firstOrFail();
        $this->assertSame('SCHEDULED', $revisionEngagement->schedule_status);
        $this->assertSame('2027-04-21', $revisionEngagement->expected_report_date->toDateString());
        $this->assertDatabaseHas('iap_schedule_events', [
            'plan_engagement_id' => $revisionEngagement->id,
            'action' => 'REVISION_CARRY_FORWARD',
        ]);
        $this->assertDatabaseHas('iap_engagement_skill_requirements', [
            'plan_engagement_id' => $revisionEngagement->id,
            'minimum_proficiency' => 'INTERMEDIATE',
        ]);

        $this->deleteJson("/api/iap/plans/{$revision['id']}")
            ->assertOk();
        $this->assertSoftDeleted('internal_audit_plans', ['id' => $revision['id']]);

        $this->postJson("/api/iap/plans/{$revision['id']}/restore")
            ->assertOk()
            ->assertJsonPath('data.plan.isArchived', false)
            ->assertJsonPath('data.plan.isCurrentRevision', true);
    }

    /** @return array<string, mixed> */
    private function createPlan(int $year, int $preparedBy): array
    {
        return $this->postJson('/api/iap/plans', $this->planPayload($year, $preparedBy))
            ->assertCreated()
            ->json('data.plan');
    }

    private function buildCompletePlan(int $year): InternalAuditPlan
    {
        $created = $this->createPlan($year, $this->user('auditor')->id);
        [$office, $area] = $this->coverage();
        $risk = $this->postJson("/api/iap/plans/{$created['id']}/risk-assessments", [
            'officeId' => $office->id,
            'auditAreaId' => $area->id,
            'assessmentDate' => '2026-10-15',
            'justification' => 'The assessment supports risk-based inclusion in the annual plan.',
            'scores' => $this->riskScores(4),
            'lockVersion' => 1,
        ])->assertCreated()->json('data.riskAssessment');
        $engagement = $this->postJson("/api/iap/plans/{$created['id']}/engagements", [
            ...$this->engagementPayload($office, $area, $risk['id']),
            'lockVersion' => 2,
        ])->assertCreated()->json('data.engagement');
        $this->putJson("/api/iap/plans/{$created['id']}/engagements/{$engagement['id']}/team", [
            'members' => [
                [
                    'userId' => $this->user('auditor')->id,
                    'teamRoleId' => $this->item('IAP_TEAM_ROLE', 'LEAD_AUDITOR')->id,
                    'plannedPersonDays' => 12,
                ],
                [
                    'userId' => $this->user('departmenthead')->id,
                    'teamRoleId' => $this->item('IAP_TEAM_ROLE', 'REVIEWER')->id,
                    'plannedPersonDays' => 3,
                ],
            ],
            'lockVersion' => 3,
        ])->assertOk();

        return InternalAuditPlan::query()->findOrFail($created['id']);
    }

    /** @return array<string, mixed> */
    private function planPayload(int $year, int $preparedBy): array
    {
        return [
            'fiscalYear' => $year,
            'planningPeriodTypeId' => $this->item('IAP_PLANNING_PERIOD_TYPE', 'ANNUAL')->id,
            'planningPeriodStart' => "{$year}-01-01",
            'planningPeriodEnd' => "{$year}-12-31",
            'title' => "{$year} Annual Internal Audit Plan",
            'executiveSummary' => 'A risk-based annual plan for the city.',
            'planningMethodology' => 'Risk assessment, consultation, and resource matching.',
            'overallObjective' => 'Provide risk-based assurance over priority city operations.',
            'overallScope' => 'Selected offices, systems, programs, and governance processes.',
            'preparedBy' => $preparedBy,
            'coordinatorId' => $this->user('departmenthead')->id,
        ];
    }

    /** @return array<string, mixed> */
    private function engagementPayload(Office $office, AuditArea $area, int $riskId): array
    {
        return [
            'engagementCode' => 'IAP-2027-001',
            'title' => 'Procurement and Supply Management Audit',
            'engagementTypeId' => $this->item('IAP_ENGAGEMENT_TYPE', 'COMPLIANCE')->id,
            'auditApproachId' => $this->item('IAP_AUDIT_APPROACH', 'RISK_BASED')->id,
            'priorityId' => $this->item('IAP_PLANNING_PRIORITY', 'HIGH')->id,
            'riskLevelId' => $this->item('RISK_LEVEL', 'CRITICAL')->id,
            'riskAssessmentId' => $riskId,
            'objectives' => 'Assess procurement compliance and the effectiveness of key controls.',
            'scope' => 'Planning, sourcing, receipt, payment, inventory, and monitoring.',
            'plannedStartDate' => '2027-02-01',
            'plannedEndDate' => '2027-03-31',
            'estimatedPersonDays' => 15,
            'estimatedCost' => 25000,
            'sequenceNumber' => 1,
            'officeIds' => [$office->id],
            'auditAreaIds' => [$area->id],
            'auditFocusIds' => $area->focuses()->limit(2)->pluck('id')->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function riskScores(float $rating): array
    {
        return $this->items('IAP_RISK_CRITERION')
            ->map(fn ($criterion) => [
                'criterionId' => $criterion->id,
                'weight' => 10,
                'rating' => $rating,
            ])
            ->all();
    }

    /** @return array{Office, AuditArea} */
    private function coverage(): array
    {
        $office = Office::query()->whereHas('auditAreas')->firstOrFail();
        $area = $office->auditAreas()->whereHas('focuses')->firstOrFail();

        return [$office, $area];
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

    private function items(string $listCode)
    {
        return MasterListItem::query()
            ->whereHas('masterList', fn ($query) => $query->where('code', $listCode))
            ->orderBy('display_order')
            ->get();
    }
}
