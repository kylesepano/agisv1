<?php

namespace Tests\Feature\Api;

use App\Models\IapPrioritizationRun;
use App\Models\IapRiskPeriod;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IapPrioritizationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_prioritization_ranks_decides_reviews_finalizes_and_recovers(): void
    {
        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $approver = $this->user('admin');
        $mayor = $this->user('mayor');
        $period = IapRiskPeriod::query()
            ->where('period_code', 'RISK-2025')
            ->firstOrFail();

        Sanctum::actingAs($approver);
        $seeded = IapPrioritizationRun::query()
            ->where('run_code', 'PRIO-2025')
            ->firstOrFail();
        $this->deleteJson("/api/iap/prioritizations/{$seeded->id}")
            ->assertOk();

        Sanctum::actingAs($management);
        $created = $this->postJson('/api/iap/prioritizations', [
            'runCode' => 'PRIO-2025-TEST',
            'name' => 'Test 2025 Audit Universe Prioritization',
            'riskPeriodId' => $period->id,
            'methodology' => 'Residual risk is normalized to 100 and ranked with documented overrides.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.prioritization.status', 'DRAFT')
            ->assertJsonCount(3, 'data.prioritization.items')
            ->json('data.prioritization');

        $runId = $created['id'];
        $this->assertSame([1, 2, 3], array_column($created['items'], 'systemRank'));
        $this->assertSame([1, 2, 3], array_column($created['items'], 'finalRank'));
        $priorityScores = array_column($created['items'], 'priorityScore');
        $sortedPriorityScores = $priorityScores;
        rsort($sortedPriorityScores);
        $this->assertSame($sortedPriorityScores, $priorityScores);
        foreach ($created['items'] as $item) {
            $this->assertEqualsWithDelta(
                $item['residualRiskScore'] * 20,
                $item['priorityScore'],
                0.01,
            );
            $this->assertNotEmpty($item['subjectName']);
            $this->assertNotEmpty($item['officeCode']);
            $this->assertNotEmpty($item['auditAreaCode']);
        }

        $first = $created['items'][0];
        Sanctum::actingAs($auditor);
        $this->putJson(
            "/api/iap/prioritizations/{$runId}/items/{$first['id']}",
            [
                'finalRank' => 2,
                'decision' => 'NOT_SELECTED',
                'lockVersion' => $first['lockVersion'],
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decisionReason');

        $this->putJson(
            "/api/iap/prioritizations/{$runId}/items/{$first['id']}",
            [
                'finalRank' => 2,
                'decision' => 'NOT_SELECTED',
                'decisionReason' => 'Deferred in favor of a legally mandated special audit.',
                'lockVersion' => $first['lockVersion'],
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('overrideReason');

        $current = $this->putJson(
            "/api/iap/prioritizations/{$runId}/items/{$first['id']}",
            [
                'finalRank' => 2,
                'decision' => 'NOT_SELECTED',
                'decisionReason' => 'Deferred in favor of a legally mandated special audit.',
                'overrideReason' => 'CIAS management approved moving this subject below the mandated audit.',
                'lockVersion' => $first['lockVersion'],
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.prioritization.items.1.isManualOverride', true)
            ->json('data.prioritization');

        foreach ($current['items'] as $item) {
            $needsReason = in_array($item['decision'], ['DEFERRED', 'NOT_SELECTED'], true)
                || $item['decision'] !== $item['recommendedDecision'];
            if (! $needsReason || $item['decisionReason']) {
                continue;
            }
            $current = $this->putJson(
                "/api/iap/prioritizations/{$runId}/items/{$item['id']}",
                [
                    'finalRank' => $item['finalRank'],
                    'decision' => $item['decision'],
                    'decisionReason' => 'Lower residual risk; retain for the next planning cycle.',
                    'overrideReason' => $item['finalRank'] !== $item['systemRank']
                        ? ($item['overrideReason']
                            ?: 'Rank shifted when the higher-priority manual override was recorded.')
                        : $item['overrideReason'],
                    'lockVersion' => $item['lockVersion'],
                ],
            )->assertOk()->json('data.prioritization');
        }

        Sanctum::actingAs($management);
        $submitted = $this->postJson(
            "/api/iap/prioritizations/{$runId}/transitions/submit",
            ['lockVersion' => $current['lockVersion']],
        )
            ->assertOk()
            ->assertJsonPath('data.prioritization.status', 'PENDING_REVIEW')
            ->json('data.prioritization');

        $this->postJson(
            "/api/iap/prioritizations/{$runId}/transitions/finalize",
            ['lockVersion' => $submitted['lockVersion']],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('finalizer');

        Sanctum::actingAs($approver);
        $returned = $this->postJson(
            "/api/iap/prioritizations/{$runId}/transitions/return",
            [
                'lockVersion' => $submitted['lockVersion'],
                'comment' => 'Clarify the manual ranking override.',
            ],
        )
            ->assertOk()
            ->assertJsonPath(
                'data.prioritization.status',
                'RETURNED_FOR_REVISION',
            )
            ->json('data.prioritization');

        Sanctum::actingAs($management);
        $resubmitted = $this->postJson(
            "/api/iap/prioritizations/{$runId}/transitions/resubmit",
            ['lockVersion' => $returned['lockVersion']],
        )
            ->assertOk()
            ->assertJsonPath('data.prioritization.status', 'RESUBMITTED')
            ->json('data.prioritization');

        Sanctum::actingAs($approver);
        $finalized = $this->postJson(
            "/api/iap/prioritizations/{$runId}/transitions/finalize",
            [
                'lockVersion' => $resubmitted['lockVersion'],
                'comment' => 'Final ranking accepted for annual plan preparation.',
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.prioritization.status', 'FINALIZED')
            ->json('data.prioritization');

        Sanctum::actingAs($auditor);
        $lockedItem = $finalized['items'][0];
        $this->putJson(
            "/api/iap/prioritizations/{$runId}/items/{$lockedItem['id']}",
            [
                'finalRank' => $lockedItem['finalRank'],
                'decision' => $lockedItem['decision'],
                'decisionReason' => $lockedItem['decisionReason'],
                'overrideReason' => $lockedItem['overrideReason'],
                'lockVersion' => $lockedItem['lockVersion'],
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        Sanctum::actingAs($mayor);
        $this->getJson('/api/iap/prioritizations')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.prioritizations.0.status', 'FINALIZED');

        Sanctum::actingAs($approver);
        $this->deleteJson("/api/iap/prioritizations/{$runId}")->assertOk();
        $this->assertSoftDeleted('iap_prioritization_runs', ['id' => $runId]);
        $this->postJson("/api/iap/prioritizations/{$runId}/restore")
            ->assertOk()
            ->assertJsonPath('data.prioritization.isArchived', false);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'iap.prioritization.finalize',
        ]);
        $this->assertDatabaseHas('iap_prioritization_events', [
            'prioritization_run_id' => $runId,
            'action' => 'FINALIZE',
        ]);
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
