<?php

namespace Tests\Feature\Api;

use App\Models\AuditArea;
use App\Models\StrategicInternalAuditPlan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiapWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_strategic_plan_supports_content_workflow_revision_and_recovery(): void
    {
        $management = $this->user('departmenthead');
        $approver = $this->user('admin');
        $auditor = $this->user('auditor');
        $areas = AuditArea::query()->orderBy('id')->limit(3)->pluck('id')->all();

        Sanctum::actingAs($management);
        $created = $this->postJson('/api/iap/strategic-plans', $this->payload(
            $auditor,
            $areas,
        ))
            ->assertCreated()
            ->assertJsonPath('data.strategicPlan.status', 'DRAFT')
            ->assertJsonCount(2, 'data.strategicPlan.objectives')
            ->assertJsonCount(2, 'data.strategicPlan.priorities')
            ->json('data.strategicPlan');

        $planId = $created['id'];
        $this->assertDatabaseHas('strategic_internal_audit_plans', [
            'id' => $planId,
            'plan_code' => 'SIAP-2027-2031-R00',
            'start_year' => 2027,
            'end_year' => 2031,
        ]);
        $this->assertSame(
            2,
            StrategicInternalAuditPlan::query()->findOrFail($planId)->objectives()->count(),
        );
        $this->assertSame(
            2,
            StrategicInternalAuditPlan::query()->findOrFail($planId)->priorities()->count(),
        );

        Sanctum::actingAs($auditor);
        $this->getJson("/api/iap/strategic-plans/{$planId}")
            ->assertOk()
            ->assertJsonPath('data.strategicPlan.coordinator.id', $auditor->id);

        Sanctum::actingAs($management);
        $this->postJson("/api/iap/strategic-plans/{$planId}/transitions/submit", [
            'lockVersion' => 1,
        ])->assertOk()->assertJsonPath('data.strategicPlan.status', 'PENDING_REVIEW');

        Sanctum::actingAs($approver);
        $this->postJson("/api/iap/strategic-plans/{$planId}/transitions/return", [
            'lockVersion' => 2,
            'comment' => 'Clarify the expected digital-governance outcome.',
        ])->assertOk()->assertJsonPath(
            'data.strategicPlan.status',
            'RETURNED_FOR_REVISION',
        );

        Sanctum::actingAs($management);
        $updatedPayload = $this->payload($auditor, $areas);
        $updatedPayload['lockVersion'] = 3;
        $updatedPayload['expectedOutcomes'] .= ' Measurable digital-governance maturity.';
        $this->putJson("/api/iap/strategic-plans/{$planId}", $updatedPayload)
            ->assertOk()
            ->assertJsonPath('data.strategicPlan.lockVersion', 4);
        $this->postJson("/api/iap/strategic-plans/{$planId}/transitions/resubmit", [
            'lockVersion' => 4,
        ])->assertOk()->assertJsonPath('data.strategicPlan.status', 'RESUBMITTED');

        Sanctum::actingAs($approver);
        $this->postJson("/api/iap/strategic-plans/{$planId}/transitions/approve", [
            'lockVersion' => 5,
            'comment' => 'Approved for the 2027–2031 planning period.',
        ])->assertOk()->assertJsonPath('data.strategicPlan.status', 'APPROVED');
        $this->postJson("/api/iap/strategic-plans/{$planId}/transitions/activate", [
            'lockVersion' => 6,
        ])->assertOk()->assertJsonPath('data.strategicPlan.status', 'ACTIVE');

        $immutablePayload = $this->payload($auditor, $areas);
        $immutablePayload['lockVersion'] = 7;
        $this->putJson("/api/iap/strategic-plans/{$planId}", $immutablePayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $revision = $this->postJson("/api/iap/strategic-plans/{$planId}/revisions", [
            'lockVersion' => 7,
            'reason' => 'Align the strategy with the updated city development plan.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.strategicPlan.status', 'DRAFT')
            ->assertJsonPath('data.strategicPlan.revisionNumber', 1)
            ->assertJsonCount(2, 'data.strategicPlan.objectives')
            ->json('data.strategicPlan');

        $this->assertFalse(
            StrategicInternalAuditPlan::query()->findOrFail($planId)->is_current_revision,
        );
        $this->deleteJson("/api/iap/strategic-plans/{$revision['id']}")
            ->assertOk();
        $this->assertSoftDeleted('strategic_internal_audit_plans', [
            'id' => $revision['id'],
        ]);
        $this->postJson("/api/iap/strategic-plans/{$revision['id']}/restore")
            ->assertOk()
            ->assertJsonPath('data.strategicPlan.isArchived', false);

        $this->assertGreaterThanOrEqual(
            9,
            $this->getConnection()->table('siap_workflow_events')->count(),
        );
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'iap.siap.revision_created',
        ]);
    }

    /** @param list<int> $areaIds
     * @return array<string, mixed>
     */
    private function payload(User $coordinator, array $areaIds): array
    {
        return [
            'startYear' => 2027,
            'endYear' => 2031,
            'title' => '2027–2031 Strategic Internal Audit Plan',
            'strategicContext' => 'Rapid digitalization and increasing demand for accountable city services.',
            'vision' => 'Trusted, risk-focused internal assurance supporting better public outcomes.',
            'missionAlignment' => 'Align CIAS assurance priorities with the city development strategy.',
            'planningMethodology' => 'Audit Universe review, weighted risk assessment, and resource-capacity analysis.',
            'expectedOutcomes' => 'Stronger revenue, procurement, and information-technology controls.',
            'coordinatorId' => $coordinator->id,
            'objectives' => [
                [
                    'objectiveCode' => 'OBJ-1',
                    'title' => 'Strengthen revenue collection controls',
                    'description' => 'Prioritize assurance over major local revenue streams.',
                    'expectedOutcome' => 'More complete, accurate, and timely city collections.',
                    'auditAreaIds' => [$areaIds[0]],
                ],
                [
                    'objectiveCode' => 'OBJ-2',
                    'title' => 'Improve IT governance and procurement compliance',
                    'description' => 'Address digital risk and high-value procurement exposure.',
                    'expectedOutcome' => 'Better-controlled systems and transparent procurement.',
                    'auditAreaIds' => [$areaIds[1], $areaIds[2]],
                ],
            ],
            'priorities' => [
                [
                    'priorityCode' => 'PRI-1',
                    'title' => 'Revenue assurance',
                    'theme' => 'Financial Sustainability',
                    'description' => 'Focus annual coverage on material collection processes.',
                    'expectedOutcome' => 'Reduced leakage and stronger accountability.',
                ],
                [
                    'priorityCode' => 'PRI-2',
                    'title' => 'Digital and procurement governance',
                    'theme' => 'Good Governance',
                    'description' => 'Review technology controls and procurement compliance.',
                    'expectedOutcome' => 'Secure services and defensible procurement decisions.',
                ],
            ],
        ];
    }

    private function user(string $employeeId): User
    {
        return User::query()->where('username', $employeeId)->firstOrFail();
    }
}
