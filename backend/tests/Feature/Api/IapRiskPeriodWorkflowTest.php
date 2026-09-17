<?php

namespace Tests\Feature\Api;

use App\Models\IapAuditUniverseItem;
use App\Models\MasterListItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IapRiskPeriodWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_risk_period_scores_evidence_workflow_lock_and_recovery(): void
    {
        Storage::fake('local');
        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $approver = $this->user('admin');
        $criteria = MasterListItem::query()
            ->whereHas('masterList', fn ($query) => $query->where('code', 'IAP_RISK_CRITERION'))
            ->orderBy('display_order')
            ->get();
        $weights = [15, 15, 15, 10, 10, 10, 10, 5, 5, 5];

        Sanctum::actingAs($management);
        $created = $this->postJson('/api/iap/risk-periods', [
            'periodCode' => 'RISK-2027',
            'name' => '2027 Audit Universe Risk Assessment',
            'assessmentYear' => 2027,
            'startDate' => '2027-01-02',
            'endDate' => '2027-03-31',
            'instructions' => 'Use current evidence and explain every significant risk score.',
            'criteria' => $criteria->values()->map(fn ($criterion, $index) => [
                'criterionId' => $criterion->id,
                'weight' => $weights[$index],
            ])->all(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.riskPeriod.status', 'DRAFT')
            ->assertJsonCount(10, 'data.riskPeriod.criteria')
            ->json('data.riskPeriod');

        $periodId = $created['id'];
        $opened = $this->postJson("/api/iap/risk-periods/{$periodId}/transitions/open", [
            'lockVersion' => 1,
        ])->assertOk()->assertJsonPath('data.riskPeriod.status', 'OPEN')
            ->json('data.riskPeriod');

        Sanctum::actingAs($auditor);
        $subject = IapAuditUniverseItem::query()->firstOrFail();
        $assessment = $this->postJson("/api/iap/risk-periods/{$periodId}/assessments", [
            'auditUniverseItemId' => $subject->id,
            'assessmentDate' => '2027-02-15',
            'controlEffectivenessPercent' => 25,
            'controlEffectivenessNotes' => 'Controls exist but are not consistently monitored.',
            'justification' => 'Material exposure and service impact support the assigned ratings.',
            'evidenceSummary' => 'Prior reports and current monitoring records.',
            'scores' => $criteria->map(fn ($criterion) => [
                'criterionId' => $criterion->id,
                'rating' => 4,
                'comment' => 'High exposure supported by available records.',
            ])->all(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.riskPeriod.assessments.0.inherentRiskScore', 4)
            ->assertJsonPath('data.riskPeriod.assessments.0.residualRiskScore', 3)
            ->assertJsonPath('data.riskPeriod.assessments.0.residualRiskLevel.code', 'HIGH')
            ->json('data.riskPeriod.assessments.0');

        $this->post(
            "/api/iap/risk-periods/{$periodId}/assessments/{$assessment['id']}/evidence",
            ['file' => UploadedFile::fake()->create('risk-support.pdf', 64, 'application/pdf')],
            ['Accept' => 'application/json'],
        )->assertCreated();

        $detail = $this->getJson("/api/iap/risk-periods/{$periodId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.riskPeriod.assessments.0.evidence')
            ->json('data.riskPeriod');
        $evidence = $detail['assessments'][0]['evidence'][0];
        $this->get(
            "/api/iap/risk-periods/{$periodId}/assessments/{$assessment['id']}/evidence/{$evidence['id']}",
        )->assertOk();

        Sanctum::actingAs($management);
        $submitted = $this->postJson("/api/iap/risk-periods/{$periodId}/transitions/submit", [
            'lockVersion' => $opened['lockVersion'],
        ])->assertOk()->assertJsonPath('data.riskPeriod.status', 'PENDING_VALIDATION')
            ->json('data.riskPeriod');

        $this->postJson("/api/iap/risk-periods/{$periodId}/transitions/validate", [
            'lockVersion' => $submitted['lockVersion'],
        ])->assertUnprocessable()->assertJsonValidationErrors('validator');

        Sanctum::actingAs($approver);
        $validated = $this->postJson("/api/iap/risk-periods/{$periodId}/transitions/validate", [
            'lockVersion' => $submitted['lockVersion'],
            'comment' => 'Scores and supporting evidence validated.',
        ])->assertOk()->assertJsonPath('data.riskPeriod.status', 'VALIDATED')
            ->json('data.riskPeriod');
        $locked = $this->postJson("/api/iap/risk-periods/{$periodId}/transitions/lock", [
            'lockVersion' => $validated['lockVersion'],
        ])->assertOk()->assertJsonPath('data.riskPeriod.status', 'LOCKED')
            ->json('data.riskPeriod');

        Sanctum::actingAs($auditor);
        $this->putJson(
            "/api/iap/risk-periods/{$periodId}/assessments/{$assessment['id']}",
            [
                'auditUniverseItemId' => $subject->id,
                'assessmentDate' => '2027-02-16',
                'controlEffectivenessPercent' => 30,
                'controlEffectivenessNotes' => 'Attempted locked change.',
                'justification' => 'This update must be rejected.',
                'scores' => $criteria->map(fn ($criterion) => [
                    'criterionId' => $criterion->id,
                    'rating' => 3,
                ])->all(),
                'lockVersion' => 1,
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('status');

        Sanctum::actingAs($approver);
        $this->deleteJson("/api/iap/risk-periods/{$periodId}")->assertOk();
        $this->assertSoftDeleted('iap_risk_periods', ['id' => $periodId]);
        $this->postJson("/api/iap/risk-periods/{$periodId}/restore")
            ->assertOk()
            ->assertJsonPath('data.riskPeriod.isArchived', false)
            ->assertJsonPath('data.riskPeriod.status', 'LOCKED');

        $this->assertDatabaseHas('audit_logs', ['action' => 'iap.risk_period.lock']);
        $this->assertDatabaseHas('iap_risk_period_events', [
            'period_id' => $periodId,
            'action' => 'LOCK',
        ]);
        $this->assertSame(3.0, (float) $locked['assessments'][0]['residualRiskScore']);
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
