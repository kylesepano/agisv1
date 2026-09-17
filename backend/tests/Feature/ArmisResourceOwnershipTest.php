<?php

namespace Tests\Feature;

use App\Models\ArmisProviderAuthorityDecision;
use App\Models\ArmisResourceProfile;
use App\Models\SystemConfiguration;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArmisResourceOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_demo_cutover_seeds_armis_as_authoritative_and_is_idempotent(): void
    {
        $this->assertDatabaseHas('system_configurations', [
            'key' => 'armis_provider_mode',
            'value' => json_encode('ARMIS_AUTHORITATIVE'),
        ]);
        $this->assertDatabaseHas('armis_provider_authority_decisions', [
            'decision_code' => 'ARMIS_RESOURCE_CUTOVER',
            'to_mode' => 'ARMIS_AUTHORITATIVE',
        ]);
        $this->assertGreaterThan(0, ArmisResourceProfile::query()->count());

        $counts = [
            'decisions' => ArmisProviderAuthorityDecision::query()->count(),
            'profiles' => ArmisResourceProfile::query()->count(),
        ];
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($counts['decisions'], ArmisProviderAuthorityDecision::query()->count());
        $this->assertSame($counts['profiles'], ArmisResourceProfile::query()->count());
    }

    public function test_iap_resource_mutations_are_rejected_with_armis_replacement(): void
    {
        Sanctum::actingAs(User::query()->where('username', 'departmenthead')->firstOrFail());
        $auditor = User::query()->where('username', 'auditor')->firstOrFail();

        $this->putJson("/api/iap/resources/auditors/{$auditor->id}/capacity", [
            'fiscalYear' => 2026,
            'availablePersonDays' => 120,
        ])->assertStatus(409)
            ->assertJsonPath('sourceOfTruth', 'ARMIS')
            ->assertJsonPath('replacementPath', '/audit-resource-management/planning');

        $this->assertDatabaseMissing('iap_auditor_capacities', [
            'user_id' => $auditor->id,
            'available_person_days' => 120,
        ]);
    }

    public function test_legacy_iap_resource_read_contract_identifies_armis_authority(): void
    {
        Sanctum::actingAs(User::query()->where('username', 'departmenthead')->firstOrFail());

        $this->getJson('/api/iap/resources?fiscalYear=2026')
            ->assertOk()
            ->assertJsonPath('data.authoritativeProvider', 'ARMIS')
            ->assertJsonPath('data.legacyResourceWritesDisabled', true);
    }
}
