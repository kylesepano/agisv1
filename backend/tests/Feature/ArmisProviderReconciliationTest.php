<?php

namespace Tests\Feature;

use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisResourceProfile;
use App\Models\IapAuditorCapacity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemConfiguration;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArmisProviderReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_reconciliation_snapshot_is_immutable_scope_pinned_and_reviewable(): void
    {
        [$generator, $reviewer, $authority, $profile] = $this->context();
        Sanctum::actingAs($generator);
        $this->setProviderMode('ARMIS_AUTHORITATIVE');

        $response = $this->postJson('/api/armis/provider/reconciliations', ['fiscalYear' => 2026])
            ->assertCreated()
            ->assertJsonPath('data.run.providerMode', 'ARMIS_AUTHORITATIVE')
            ->assertJsonPath('data.run.summary.reviewRequired', true);
        $run = $response->json('data.run');
        $decisions = collect($run['resultSnapshot'])
            ->filter(fn (array $item): bool => ($item['status'] ?? null) === 'DISCREPANCY')
            ->mapWithKeys(fn (array $item): array => [$item['key'] => 'ACCEPT'])
            ->all();

        Sanctum::actingAs($reviewer);
        $this->postJson('/api/armis/provider/reconciliations/'.$run['id'].'/review', [
            'decision' => 'ACCEPTED',
            'comment' => 'The approved ARMIS capacity difference is understood and accepted for authority review.',
            'discrepancyDecisions' => $decisions,
        ])->assertCreated()
            ->assertJsonPath('data.review.decision', 'ACCEPTED');

        Sanctum::actingAs($authority);
        $this->getJson('/api/armis/provider/reconciliations/'.$run['id'])
            ->assertOk()
            ->assertJsonPath('data.run.resultChecksumSha256', $run['resultChecksumSha256']);

        $this->assertDatabaseHas('armis_provider_reconciliation_reviews', [
            'reconciliation_run_id' => $run['id'],
            'decision' => 'ACCEPTED',
        ]);
        $this->assertDatabaseHas('armis_workflow_events', [
            'subject_type' => 'App\\Models\\ArmisProviderReconciliationRun',
            'event_code' => 'ARMIS_RECONCILIATION_REVIEW_ACCEPTED',
        ]);
    }

    public function test_provider_switch_actions_are_disabled_after_armis_cutover(): void
    {
        [$generator, $reviewer, $authority, $profile] = $this->context();
        Sanctum::actingAs($authority);
        $this->postJson('/api/armis/provider/rollback', [
            'reason' => 'Provider switching is intentionally disabled after ARMIS cutover.',
        ])->assertStatus(409);
        Sanctum::actingAs($generator);
        $run = $this->postJson('/api/armis/provider/reconciliations', ['fiscalYear' => 2026])
            ->assertCreated()
            ->json('data.run');
        $decisions = collect($run['resultSnapshot'])
            ->filter(fn (array $item): bool => ($item['status'] ?? null) === 'DISCREPANCY')
            ->mapWithKeys(fn (array $item): array => [$item['key'] => 'ACCEPT'])
            ->all();

        Sanctum::actingAs($reviewer);
        $this->postJson('/api/armis/provider/reconciliations/'.$run['id'].'/review', [
            'decision' => 'ACCEPTED',
            'comment' => 'Independent review completed with the documented capacity difference accepted.',
            'discrepancyDecisions' => $decisions,
        ])->assertCreated();

        Sanctum::actingAs($authority);
        $this->postJson('/api/armis/provider/reconciliations/'.$run['id'].'/activate', [
            'reason' => 'Provider switching is intentionally disabled after ARMIS cutover.',
        ])->assertStatus(409);

        $this->getJson('/api/armis/provider/status')
            ->assertOk()
            ->assertJsonPath('data.provider.mode', 'ARMIS_AUTHORITATIVE')
            ->assertJsonPath('data.provider.authoritative', true)
            ->assertJsonPath('data.provider.activeProvider', 'App\\Integrations\\Aems\\ArmisResourcePlanningGateway');

        $this->assertDatabaseCount('armis_provider_authority_decisions', 1);
        $this->assertSame('ARMIS_AUTHORITATIVE', app(\App\Services\RuntimeConfiguration::class)->armisProviderMode());
    }

    public function test_generator_reviewer_and_authority_are_separated_and_direct_config_remains_armis_only(): void
    {
        [$generator, $reviewer, $authority] = $this->context();
        Sanctum::actingAs($this->user('agisadmin'));
        $this->putJson('/api/system-configurations', [
            'configurations' => [
                ['key' => 'armis_provider_mode', 'value' => 'ARMIS_AUTHORITATIVE'],
            ],
        ])->assertOk();

        $this->assertFalse($generator->is($reviewer));
        $this->assertFalse($reviewer->is($authority));
        $this->assertTrue($authority->hasPermission('armis.provider.switch'));
        $this->assertFalse($generator->hasPermission('armis.provider.switch'));
    }

    /** @return array{User, User, User, ArmisResourceProfile} */
    private function context(): array
    {
        $cias = $this->user('departmenthead');
        $operatorRole = Role::factory()->create([
            'code' => 'armis_reconciliation_operator',
            'name' => 'ARMIS Reconciliation Operator',
            'office_access_scope' => 'ALL',
            'engagement_access_scope' => 'ALL',
        ]);
        $operatorRole->permissions()->sync(
            Permission::query()->whereIn('code', ['notifications.view', 'armis.provider.view', 'armis.provider.reconcile'])->pluck('id'),
        );
        $generator = User::factory()->create([
            'role_id' => $operatorRole->id,
            'office_id' => $cias->office_id,
            'name' => 'ARMIS Reconciliation Generator',
        ]);
        $generator->syncRoleAssignments([$operatorRole->id], $operatorRole->id, $cias->id);
        $role = Role::query()->where('code', 'cias_management')->firstOrFail();
        $reviewer = User::factory()->create([
            'role_id' => $role->id,
            'office_id' => $generator->office_id,
            'name' => 'Independent ARMIS Reviewer',
        ]);
        $reviewer->syncRoleAssignments([$role->id], $role->id, $generator->id);
        $authority = User::factory()->create([
            'role_id' => $role->id,
            'office_id' => $generator->office_id,
            'name' => 'ARMIS Authority',
        ]);
        $authority->syncRoleAssignments([$role->id], $role->id, $generator->id);
        $resourceUser = $this->user('auditor');
        $profile = ArmisResourceProfile::query()->create([
            'resource_code' => 'ARMIS-6B-'.str()->upper(str()->random(6)),
            'user_id' => $resourceUser->id,
            'office_id' => $resourceUser->office_id,
            'category' => 'AUDIT_RESOURCE',
            'status' => 'ACTIVE',
            'created_by' => $generator->id,
            'updated_by' => $generator->id,
        ]);
        IapAuditorCapacity::query()->updateOrCreate(
            ['fiscal_year' => 2026, 'user_id' => $resourceUser->id],
            ['available_person_days' => 80, 'set_by' => $generator->id],
        );
        ArmisCapacitySubmission::query()->create([
            'resource_profile_id' => $profile->id,
            'fiscal_year' => 2026,
            'version_number' => 1,
            'available_person_days' => 120,
            'status' => 'APPROVED',
            'is_current_revision' => true,
            'created_by' => $generator->id,
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
        ]);

        return [$generator, $reviewer, $authority, $profile];
    }

    private function setProviderMode(string $mode): void
    {
        $configuration = SystemConfiguration::query()->where('key', 'armis_provider_mode')->firstOrFail();
        $configuration->value = $mode;
        $configuration->save();
        app(\App\Services\RuntimeConfiguration::class)->forget();
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
