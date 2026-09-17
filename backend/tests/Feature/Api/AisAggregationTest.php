<?php

namespace Tests\Feature\Api;

use App\Models\AisAggregationSnapshot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AisAggregationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_aggregation_overview_is_scope_aware_and_read_only(): void
    {
        Sanctum::actingAs($this->user('agisadmin'));

        $response = $this->getJson('/api/ais/aggregations')
            ->assertOk()
            ->assertJsonPath('data.contractVersion', 'AIS-1.0')
            ->assertJsonPath('data.sourceQueryVersion', 'AIS-1-v1')
            ->assertJsonCount(5, 'data.sourceModes')
            ->assertJsonPath('data.scope.officeScope', 'ALL')
            ->assertJsonStructure(['data' => ['metrics' => [
                'core' => ['offices', 'activeUsers'],
                'iap' => ['plansByStatus', 'approvedPlans'],
                'aems' => ['engagementsByStatus', 'activeEngagements', 'findingsByStatus', 'evidenceByOutcome'],
                'cms' => ['casesByStatus', 'openCases', 'overdueCases'],
                'armis' => ['resourcesByStatus', 'approvedAssignments', 'plannedPersonDays'],
            ]]]);

        $this->assertNull($response->json('data.latestSnapshot'));
        $this->assertDatabaseCount('ais_aggregation_snapshots', 0);
    }

    public function test_dashboard_returns_scope_aware_analytical_views_without_source_writes(): void
    {
        Sanctum::actingAs($actor = $this->user('agisadmin'));

        $this->postJson('/api/ais/aggregations/snapshots')->assertCreated();

        $response = $this->getJson('/api/ais/dashboard')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=30, must-revalidate, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertJsonPath('data.dashboardVersion', 'AIS-2.0')
            ->assertJsonPath('data.scope.officeScope', 'ALL')
            ->assertJsonStructure([
                'data' => [
                    'headline' => [
                        'approvedIapPlans', 'activeEngagements', 'findingsAwaitingReview',
                        'findingsAwaitingResponse', 'evidenceAwaitingAssessment',
                        'openCmsCases', 'overdueCmsCases', 'approvedArmisAssignments',
                        'plannedPersonDays',
                    ],
                    'distributions' => [
                        'engagementStatuses', 'findingStatuses', 'evidenceOutcomes',
                        'cmsStatuses', 'armisResourceStatuses',
                    ],
                    'snapshotTrend', 'attention', 'limitations',
                ],
            ]);

        $this->assertCount(1, $response->json('data.snapshotTrend'));
        $this->assertSame($actor->id, AisAggregationSnapshot::query()->firstOrFail()->generated_by);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ais.dashboard.viewed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ais.dashboard.viewed']);
    }

    public function test_dashboard_requires_ais_view_permission(): void
    {
        Sanctum::actingAs($this->user('auditee'));

        $this->getJson('/api/ais/dashboard')->assertForbidden();
    }

    public function test_snapshot_is_immutable_and_activity_and_audit_logged(): void
    {
        Sanctum::actingAs($actor = $this->user('agisadmin'));

        $response = $this->postJson('/api/ais/aggregations/snapshots')
            ->assertCreated()
            ->assertJsonPath('data.snapshot.contractVersion', 'AIS-1.0')
            ->assertJsonPath('data.snapshot.sourceVersions.AEMS', 'AEMS-G10E-v1');

        $snapshot = AisAggregationSnapshot::query()->firstOrFail();
        $this->assertSame($actor->id, $snapshot->generated_by);
        $this->assertSame(
            hash('sha256', json_encode($snapshot->metrics, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $snapshot->metrics_checksum_sha256,
        );
        $this->assertSame($snapshot->snapshot_code, $response->json('data.snapshot.displayCode'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'ais.aggregation.snapshot_generated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ais.aggregation.snapshot_generated']);

        $this->expectException(LogicException::class);
        $snapshot->update(['metrics' => []]);
    }

    public function test_snapshot_history_is_limited_to_the_generating_actor(): void
    {
        Sanctum::actingAs($this->user('agisadmin'));
        $this->postJson('/api/ais/aggregations/snapshots')->assertCreated();

        Sanctum::actingAs($this->user('auditor'));
        $this->getJson('/api/ais/aggregations/snapshots')
            ->assertOk()
            ->assertJsonCount(0, 'data.snapshots');
    }

    public function test_aggregation_requires_ais_view_permission(): void
    {
        Sanctum::actingAs($this->user('auditee'));

        $this->getJson('/api/ais/aggregations')->assertForbidden();
        $this->postJson('/api/ais/aggregations/snapshots')->assertForbidden();
    }

    private function user(string $username): User
    {
        return User::query()->with(['role.permissions', 'roles.permissions'])->where('username', $username)->firstOrFail();
    }
}
