<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IapResourceCapacityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_resource_read_contract_uses_armis_and_legacy_writes_are_blocked(): void
    {
        $manager = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        Sanctum::actingAs($manager);

        $resource = $this->getJson('/api/iap/resources?fiscalYear=2026')
            ->assertOk()
            ->assertJsonPath('data.fiscalYear', 2026)
            ->assertJsonPath('data.authoritativeProvider', 'ARMIS')
            ->assertJsonPath('data.legacyResourceWritesDisabled', true)
            ->json('data');
        $this->assertGreaterThanOrEqual(2, $resource['summary']['totalAuditors']);
        $this->assertGreaterThan(0, $resource['summary']['availablePersonDays']);

        $this->putJson("/api/iap/resources/auditors/{$auditor->id}/capacity", [
            'fiscalYear' => 2026,
            'availablePersonDays' => 12,
            'notes' => 'Reduced for leave and mandatory training.',
        ])->assertStatus(409)
            ->assertJsonPath('sourceOfTruth', 'ARMIS');
    }

    public function test_resource_mutations_require_management_access(): void
    {
        $auditor = $this->user('auditor');
        Sanctum::actingAs($auditor);

        $this->getJson('/api/iap/resources?fiscalYear=2026')->assertOk();
        $this->putJson("/api/iap/resources/auditors/{$auditor->id}/capacity", [
            'fiscalYear' => 2026,
            'availablePersonDays' => 150,
        ])->assertForbidden();
    }

    private function user(string $username): User
    {
        return User::query()
            ->where('username', $username)
            ->with('role.permissions')
            ->firstOrFail();
    }

}
