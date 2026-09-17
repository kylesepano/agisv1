<?php

namespace Tests\Feature\Api;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Models\AisIntegrationSnapshot;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AisIntegrationContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_read_only_integration_contract_reports_scope_freshness_and_boundaries(): void
    {
        Sanctum::actingAs($this->user('agisadmin'));

        $response = $this->getJson('/api/ais/integration-contract')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=30, must-revalidate, private')
            ->assertJsonPath('data.integrationContractVersion', 'AIS-5A.0')
            ->assertJsonPath('data.status', 'READ_ONLY_READY')
            ->assertJsonPath('data.mode', 'READ_ONLY')
            ->assertJsonPath('data.reconciliation.status', 'PASS')
            ->assertJsonPath('data.reconciliation.eligible', true)
            ->assertJsonPath('data.controls.sourceWrites', false)
            ->assertJsonPath('data.controls.professionalDecisions', false)
            ->assertJsonPath('data.controls.failureMode', 'FAIL_CLOSED')
            ->assertJsonPath('data.scope.confidentiality.restricted', true)
            ->assertJsonCount(5, 'data.sourceModules');

        foreach (['CORE', 'IAP', 'AEMS', 'CMS', 'ARMIS'] as $module) {
            $source = collect($response->json('data.sourceModules'))->firstWhere('module', $module);
            $this->assertNotNull($source);
            $this->assertTrue($source['scopeRevalidated']);
            $this->assertTrue($source['confidentialityRevalidated']);
            $this->assertTrue($source['reconciliation']['eligible']);
        }

        $this->assertDatabaseHas('activity_logs', ['action' => 'ais.integration.viewed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ais.integration.viewed']);
    }

    public function test_integration_contract_requires_ais_view_permission(): void
    {
        Sanctum::actingAs($this->user('auditee'));

        $this->getJson('/api/ais/integration-contract')->assertForbidden();
    }

    public function test_ais_contract_includes_integration_and_aggregation_pins_the_ready_contract(): void
    {
        Sanctum::actingAs($actor = $this->user('agisadmin'));

        $this->getJson('/api/ais/contract')
            ->assertOk()
            ->assertJsonPath('data.integration.status', 'READ_ONLY_READY')
            ->assertJsonPath('data.integration.controls.lineagePinned', true);

        $this->getJson('/api/ais/aggregations')
            ->assertOk()
            ->assertJsonPath('data.integration.contractVersion', 'AIS-5A.0')
            ->assertJsonPath('data.integration.status', 'READ_ONLY_READY');

        $this->assertDatabaseHas('activity_logs', ['user_id' => $actor->id, 'action' => 'ais.aggregation.viewed']);
    }

    public function test_non_global_actor_receives_office_and_confidentiality_scoped_contract(): void
    {
        // Exercise the configurable own-office branch without changing the
        // production seed (AGIS User is global-office by default).
        Role::query()->where('code', 'agis_user')->update(['office_access_scope' => 'OWN_OFFICE']);
        $actor = $this->user('auditor');
        Sanctum::actingAs($actor);

        $response = $this->getJson('/api/ais/integration-health')
            ->assertOk()
            ->assertJsonPath('data.scope.officeScope', 'OWN_OFFICE')
            ->assertJsonPath('data.scope.officeId', $actor->office_id)
            ->assertJsonPath('data.scope.engagementScope', 'ASSIGNED')
            ->assertJsonPath('data.scope.confidentiality.restricted', false);

        $this->assertDatabaseHas('activity_logs', ['user_id' => $actor->id, 'action' => 'ais.integration.health.viewed']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $actor->id, 'action' => 'ais.integration.health.viewed']);

        foreach ($response->json('data.sourceModules') as $source) {
            $this->assertTrue($source['scopeRevalidated']);
            $this->assertTrue($source['confidentialityRevalidated']);
        }
    }

    public function test_source_adapters_expose_iap_aems_cms_and_armis_lineage_controls(): void
    {
        Sanctum::actingAs($this->user('agisadmin'));

        $response = $this->getJson('/api/ais/integration-contract')->assertOk();
        $sources = collect($response->json('data.sourceModules'))->keyBy('module');

        $this->assertSame('IAP_APPROVED_PLAN_GATE', $sources['IAP']['adapter']);
        $this->assertArrayHasKey('duplicatePrevention', $sources['IAP']['details']);
        $this->assertSame('AEMS_SCOPED_ENGAGEMENT_GATE', $sources['AEMS']['adapter']);
        $this->assertSame(0, $sources['AEMS']['details']['lineageGaps']);
        $this->assertSame('CMS_FINALIZED_RECOMMENDATION_GATE', $sources['CMS']['adapter']);
        $this->assertSame(0, $sources['CMS']['details']['sourceGaps']);
        $this->assertSame('ARMIS_PROVIDER_GATE', $sources['ARMIS']['adapter']);
        $this->assertArrayHasKey('providerMode', $sources['ARMIS']['details']);
        $this->assertArrayHasKey('reconciliation', $sources['ARMIS']['details']);
    }

    public function test_stale_authoritative_provider_fails_closed_for_integration_and_aggregation(): void
    {
        $provider = $this->createMock(ResourcePlanningGateway::class);
        $provider->method('status')->willReturn([
            'module' => 'ARMIS',
            'mode' => 'ARMIS_AUTHORITATIVE',
            'available' => true,
            'authorityEligible' => false,
            'dataFreshness' => 'STALE',
            'fallback' => ['explicit' => false],
        ]);
        app()->instance(ResourcePlanningGateway::class, $provider);

        Sanctum::actingAs($this->user('agisadmin'));

        $this->getJson('/api/ais/integration-contract')
            ->assertOk()
            ->assertJsonPath('data.status', 'READ_ONLY_BLOCKED')
            ->assertJsonPath('data.reconciliation.status', 'BLOCKED')
            ->assertJsonPath('data.reconciliation.eligible', false)
            ->assertJsonPath('data.reconciliation.blockedSources.0', 'ARMIS');

        $this->getJson('/api/ais/aggregations')->assertStatus(503);
    }

    public function test_missing_provider_fails_closed_and_can_be_preserved_as_a_blocked_snapshot(): void
    {
        $provider = $this->createMock(ResourcePlanningGateway::class);
        $provider->method('status')->willReturn([
            'module' => 'ARMIS',
            'mode' => 'ARMIS_AUTHORITATIVE',
            'available' => false,
            'authorityEligible' => false,
            'dataFreshness' => 'UNKNOWN',
            'fallback' => ['explicit' => false],
        ]);
        app()->instance(ResourcePlanningGateway::class, $provider);
        Sanctum::actingAs($actor = $this->user('agisadmin'));

        $this->getJson('/api/ais/integration-health')
            ->assertOk()
            ->assertJsonPath('data.status', 'BLOCKED')
            ->assertJsonPath('data.validation.eligible', false)
            ->assertJsonPath('data.diagnostics.4.status', 'BLOCKED')
            ->assertJsonPath('data.diagnostics.4.issues.0', 'SOURCE_UNAVAILABLE');

        $created = $this->postJson('/api/ais/integration-health/snapshots')
            ->assertCreated()
            ->assertJsonPath('data.snapshot.status', 'BLOCKED');

        $this->assertDatabaseHas('ais_integration_snapshots', [
            'id' => $created->json('data.snapshot.id'),
            'generated_by' => $actor->id,
            'status' => 'BLOCKED',
        ]);
    }

    public function test_integration_health_exposes_diagnostics_and_immutable_actor_owned_snapshots(): void
    {
        Sanctum::actingAs($actor = $this->user('agisadmin'));
        $engagementsBefore = \App\Models\AuditEngagement::query()->pluck('updated_at', 'id')->all();

        $this->getJson('/api/ais/integration-health')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=30, must-revalidate, private')
            ->assertJsonPath('data.healthContractVersion', 'AIS-5B.0')
            ->assertJsonPath('data.integrationStatus', 'READ_ONLY_READY')
            ->assertJsonPath('data.status', 'HEALTHY')
            ->assertJsonPath('data.validation.status', 'PASS')
            ->assertJsonPath('data.controls.immutableSnapshots', true)
            ->assertJsonPath('data.controls.sourceWrites', false)
            ->assertJsonPath('data.controls.duplicateOwnershipTables', false)
            ->assertJsonCount(5, 'data.diagnostics');

        $created = $this->postJson('/api/ais/integration-health/snapshots')
            ->assertCreated()
            ->assertJsonPath('data.snapshot.contractVersion', 'AIS-5B.0')
            ->assertJsonPath('data.snapshot.integrationContractVersion', 'AIS-5A.0')
            ->assertJsonPath('data.snapshot.status', 'HEALTHY')
            ->assertJsonPath('data.snapshot.immutable', true);

        $snapshotId = $created->json('data.snapshot.id');
        $this->assertDatabaseHas('ais_integration_snapshots', [
            'id' => $snapshotId,
            'generated_by' => $actor->id,
            'status' => 'HEALTHY',
        ]);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $actor->id, 'action' => 'ais.integration.snapshot.generated']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $actor->id, 'action' => 'ais.integration.snapshot.generated']);
        $this->assertSame($engagementsBefore, \App\Models\AuditEngagement::query()->pluck('updated_at', 'id')->all());

        $this->getJson('/api/ais/integration-health/snapshots')
            ->assertOk()
            ->assertJsonCount(1, 'data.snapshots')
            ->assertJsonPath('data.snapshots.0.id', $snapshotId);

        $snapshot = AisIntegrationSnapshot::query()->findOrFail($snapshotId);
        $this->expectException(\LogicException::class);
        $snapshot->update(['status' => 'ALTERED']);
    }

    public function test_integration_snapshots_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->user('agisadmin'));
        $this->postJson('/api/ais/integration-health/snapshots')->assertCreated();

        $this->expectException(\LogicException::class);
        AisIntegrationSnapshot::query()->firstOrFail()->delete();
    }

    public function test_integration_health_snapshots_require_ais_view_permission(): void
    {
        Sanctum::actingAs($this->user('auditee'));

        $this->getJson('/api/ais/integration-health')->assertForbidden();
        $this->getJson('/api/ais/integration-health/snapshots')->assertForbidden();
        $this->postJson('/api/ais/integration-health/snapshots')->assertForbidden();
    }

    private function user(string $username): User
    {
        return User::query()->with(['role.permissions', 'roles.permissions'])->where('username', $username)->firstOrFail();
    }
}
