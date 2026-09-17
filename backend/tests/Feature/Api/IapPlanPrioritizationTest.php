<?php

namespace Tests\Feature\Api;

use App\Models\IapPrioritizationRun;
use App\Models\IapUniverseRiskAssessment;
use App\Models\MasterListItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IapPlanPrioritizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Sanctum::actingAs(
            User::query()->where('username', 'departmenthead')->firstOrFail(),
        );
    }

    public function test_finalized_prioritization_import_preserves_lineage_and_blocks_duplicates(): void
    {
        $plan = $this->postJson('/api/iap/plans', [
            'fiscalYear' => 2028,
            'planningPeriodTypeId' => $this->item('IAP_PLANNING_PERIOD_TYPE', 'ANNUAL')->id,
            'planningPeriodStart' => '2028-01-01',
            'planningPeriodEnd' => '2028-12-31',
            'title' => '2028 Annual Internal Audit Plan',
            'overallObjective' => 'Provide assurance over the highest-priority city risks.',
            'overallScope' => 'Selected Audit Universe subjects in the finalized prioritization.',
            'preparedBy' => User::query()->where('username', 'departmenthead')->value('id'),
        ])->assertCreated()->json('data.plan');

        $run = IapPrioritizationRun::query()
            ->where('run_code', 'PRIO-2025')
            ->where('status', 'FINALIZED')
            ->firstOrFail();

        $run->forceFill(['status' => 'DRAFT'])->save();
        $this->putJson("/api/iap/plans/{$plan['id']}/prioritization", [
            'prioritizationRunId' => $run->id,
            'lockVersion' => $plan['lockVersion'],
        ])->assertUnprocessable()->assertJsonValidationErrors('prioritizationRunId');
        $run->forceFill(['status' => 'FINALIZED'])->save();

        $linked = $this->putJson("/api/iap/plans/{$plan['id']}/prioritization", [
            'prioritizationRunId' => $run->id,
            'lockVersion' => $plan['lockVersion'],
        ])
            ->assertOk()
            ->assertJsonPath('data.plan.prioritizationRun.runCode', 'PRIO-2025')
            ->json('data.plan');

        $selected = collect($linked['prioritizationRun']['items'])
            ->firstWhere('planningState', 'UNPLANNED');
        $this->assertNotNull($selected);

        $payload = [
            'prioritizationItemId' => $selected['id'],
            'engagementCode' => 'IAP-2028-001',
            'title' => $selected['subjectName'],
            'engagementTypeId' => $this->item('IAP_ENGAGEMENT_TYPE', 'OPERATIONAL')->id,
            'auditApproachId' => $this->item('IAP_AUDIT_APPROACH', 'RISK_BASED')->id,
            'priorityId' => $this->item('IAP_PLANNING_PRIORITY', 'HIGH')->id,
            'objectives' => 'Assess whether the key controls address the prioritized risks.',
            'scope' => 'The responsible office, primary audit area, and relevant 2028 transactions.',
            'plannedStartDate' => '2028-01-15',
            'plannedEndDate' => '2028-03-31',
            'targetQuarter' => 1,
            'estimatedPersonDays' => 20,
            'lockVersion' => $linked['lockVersion'],
        ];

        $assessment = IapUniverseRiskAssessment::query()
            ->findOrFail($selected['riskAssessmentId']);
        $assessment->forceFill(['status' => 'DRAFT'])->save();
        $this->postJson(
            "/api/iap/plans/{$plan['id']}/engagements",
            $payload,
        )->assertUnprocessable()->assertJsonValidationErrors('prioritizationItemId');
        $assessment->forceFill(['status' => 'VALIDATED'])->save();

        $engagement = $this->postJson(
            "/api/iap/plans/{$plan['id']}/engagements",
            $payload,
        )
            ->assertCreated()
            ->assertJsonPath('data.engagement.engagementCode', 'IAP-2028-001')
            ->json('data.engagement');

        $this->assertDatabaseHas('iap_plan_engagements', [
            'id' => $engagement['id'],
            'plan_id' => $plan['id'],
            'prioritization_item_id' => $selected['id'],
            'audit_universe_item_id' => $selected['auditUniverseItemId'],
            'universe_risk_assessment_id' => $selected['riskAssessmentId'],
            'source_decision' => 'SELECTED',
            'target_quarter' => 1,
        ]);
        $this->assertSame(
            1,
            $this->getConnection()->table('iap_engagement_offices')
                ->where('plan_engagement_id', $engagement['id'])
                ->count(),
        );
        $this->assertSame(
            1,
            $this->getConnection()->table('iap_engagement_audit_areas')
                ->where('plan_engagement_id', $engagement['id'])
                ->count(),
        );

        $shown = $this->getJson("/api/iap/plans/{$plan['id']}")
            ->assertOk()
            ->assertJsonPath(
                'data.plan.prioritizationRun.items.0.planningState',
                'PLANNED',
            )
            ->json('data.plan');

        $this->postJson("/api/iap/plans/{$plan['id']}/engagements", [
            ...$payload,
            'engagementCode' => 'IAP-2028-002',
            'lockVersion' => $shown['lockVersion'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('prioritizationItemId');
    }

    private function item(string $listCode, string $itemCode): MasterListItem
    {
        return MasterListItem::query()
            ->where('code', $itemCode)
            ->whereHas('masterList', fn ($query) => $query->where('code', $listCode))
            ->firstOrFail();
    }
}
