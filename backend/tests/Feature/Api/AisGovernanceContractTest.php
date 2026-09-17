<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AisGovernanceContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_ais_contract_is_analytics_read_only_and_scope_aware(): void
    {
        Sanctum::actingAs($this->user('agisadmin'));

        $this->getJson('/api/ais/contract')
            ->assertOk()
            ->assertJsonPath('data.contractVersion', 'AIS-5D.0')
            ->assertJsonPath('data.status', 'READ_ONLY_VERIFIED')
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.readOnlyDashboardEnabled', true)
            ->assertJsonPath('data.readOnlyReportsEnabled', true)
            ->assertJsonPath('data.professionalControls.noOperationalWrites', true)
            ->assertJsonPath('data.professionalControls.noProfessionalDecisions', true)
            ->assertJsonCount(5, 'data.sourceModules')
            ->assertJsonPath('data.plannedCapabilities.0.code', 'AIS-1')
            ->assertJsonPath('data.plannedCapabilities.0.status', 'IMPLEMENTED')
            ->assertJsonPath('data.plannedCapabilities.1.status', 'IMPLEMENTED')
            ->assertJsonPath('data.plannedCapabilities.2.status', 'IMPLEMENTED')
            ->assertJsonPath('data.plannedCapabilities.3.status', 'IMPLEMENTED')
            ->assertJsonPath('data.plannedCapabilities.4.code', 'AIS-5A')
            ->assertJsonPath('data.plannedCapabilities.4.status', 'IMPLEMENTED')
            ->assertJsonPath('data.plannedCapabilities.5.code', 'AIS-5B')
            ->assertJsonPath('data.plannedCapabilities.5.status', 'IMPLEMENTED')
            ->assertJsonPath('data.plannedCapabilities.6.code', 'AIS-5C')
            ->assertJsonPath('data.plannedCapabilities.6.status', 'IMPLEMENTED')
            ->assertJsonPath('data.plannedCapabilities.7.code', 'AIS-5D')
            ->assertJsonPath('data.plannedCapabilities.7.status', 'IMPLEMENTED')
            ->assertJsonPath('data.integration.integrationContractVersion', 'AIS-5A.0')
            ->assertJsonPath('data.integration.reconciliation.eligible', true)
            ->assertJsonPath('data.hardening.status', 'ENFORCED')
            ->assertJsonPath('data.hardening.checks.noPublicUrls', true)
            ->assertJsonPath('data.hardening.checks.namedRateLimits', true);

        $this->assertDatabaseHas('activity_logs', ['action' => 'ais.contract.viewed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ais.contract.viewed']);
    }

    public function test_ais_contract_requires_ais_view_permission(): void
    {
        Sanctum::actingAs($this->user('auditee'));

        $this->getJson('/api/ais/contract')->assertForbidden();
    }

    public function test_hardening_contract_is_authenticated_scope_aware_and_private(): void
    {
        Sanctum::actingAs($this->user('agisadmin'));

        $this->getJson('/api/ais/hardening')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=30, must-revalidate, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertJsonPath('data.version', 'AIS-4.0')
            ->assertJsonPath('data.rateLimits.readPerMinute', 120);
    }

    private function user(string $username): User
    {
        return User::query()->with(['role.permissions', 'roles.permissions'])->where('username', $username)->firstOrFail();
    }
}
