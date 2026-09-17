<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ArmisResourceProfile;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArmisFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_armis_foundation_schema_and_permissions_are_seeded(): void
    {
        foreach ([
            'armis_resource_profiles', 'armis_competencies', 'armis_availability_periods',
            'armis_capacity_submissions', 'armis_resource_requirements',
            'armis_requirement_competencies', 'armis_workload_allocations',
            'armis_actual_person_days', 'armis_workflow_events',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} should exist.");
        }

        foreach ([
            'armis.resource.view', 'armis.resource.create', 'armis.resource.update',
            'armis.resource.archive', 'armis.resource.restore', 'armis.competency.verify',
            'armis.capacity.approve', 'armis.actuals.approve', 'arms.view', 'arms.manage',
        ] as $permission) {
            $this->assertDatabaseHas('permissions', ['code' => $permission]);
        }

        Sanctum::actingAs($this->user('agisadmin'));
        $this->getJson('/api/armis/metadata')
            ->assertOk()
            ->assertJsonPath('data.categories.0.label', 'Audit Resource')
            ->assertJsonPath('data.categories.1.label', 'Specialist')
            ->assertJsonPath('data.categories.2.label', 'Reviewer')
            ->assertJsonPath('data.categories.3.label', 'Support');
    }

    public function test_resource_registry_is_scoped_audited_and_optimistically_locked(): void
    {
        $admin = $this->user('agisadmin');
        $auditor = $this->user('auditor');
        $initialProfileCount = ArmisResourceProfile::query()->count();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/armis/resources', [
            'userId' => $auditor->id,
            'officeId' => $auditor->office_id,
            'category' => 'AUDIT_RESOURCE',
            'effectiveFrom' => '2026-08-01',
            'notes' => 'ARMIS foundation test profile',
        ]);
        $response->assertCreated()->assertJsonPath('data.status', 'DRAFT');
        $profileId = (int) $response->json('data.id');
        $this->assertDatabaseHas('armis_workflow_events', [
            'subject_id' => $profileId,
            'event_code' => 'RESOURCE_CREATED',
            'to_status' => 'DRAFT',
        ]);
        $this->assertSame(1, ActivityLog::query()->where('action', 'armis.resource.created')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'armis.resource.created')->count());

        $this->postJson("/api/armis/resources/{$profileId}/transition", [
            'status' => 'ACTIVE',
            'lockVersion' => 1,
        ])->assertOk()->assertJsonPath('data.status', 'ACTIVE')->assertJsonPath('data.lockVersion', 2);

        $this->putJson("/api/armis/resources/{$profileId}", [
            'userId' => $auditor->id,
            'officeId' => $auditor->office_id,
            'notes' => 'stale update',
            'lockVersion' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('lockVersion');

        $this->getJson('/api/armis/resources')->assertOk()->assertJsonPath('meta.total', $initialProfileCount + 1);
        $this->getJson('/api/armis/foundation')
            ->assertOk()
            ->assertJsonPath('meta.profileCount', $initialProfileCount + 1)
            ->assertJsonPath('meta.provider.mode', 'ARMIS_AUTHORITATIVE');
        $this->getJson("/api/armis/resources/{$profileId}/events")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_profile_archive_and_restore_preserve_auditable_lifecycle(): void
    {
        $admin = $this->user('agisadmin');
        $auditor = $this->user('auditor');
        Sanctum::actingAs($admin);
        $profile = ArmisResourceProfile::query()->create([
            'resource_code' => 'ARMIS-TEST-RESTORE',
            'user_id' => $auditor->id,
            'office_id' => $auditor->office_id,
            'category' => 'AUDIT_RESOURCE',
            'status' => 'ACTIVE',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->postJson("/api/armis/resources/{$profile->id}/transition", [
            'status' => 'INACTIVE', 'lockVersion' => 1,
        ])->assertOk()->assertJsonPath('data.lockVersion', 2);
        $this->postJson("/api/armis/resources/{$profile->id}/transition", [
            'status' => 'ARCHIVED', 'lockVersion' => 2, 'reason' => 'No longer assigned',
        ])->assertOk()->assertJsonPath('data.status', 'ARCHIVED');
        $archived = ArmisResourceProfile::withTrashed()->findOrFail($profile->id);
        $this->assertNotNull($archived->deleted_at);

        $this->postJson("/api/armis/resources/{$profile->id}/restore", [
            'lockVersion' => 3,
        ])->assertOk()->assertJsonPath('data.status', 'INACTIVE')->assertJsonPath('data.lockVersion', 4);
        $this->assertDatabaseHas('armis_workflow_events', [
            'subject_id' => $profile->id,
            'event_code' => 'RESOURCE_RESTORED',
            'from_status' => 'ARCHIVED',
            'to_status' => 'INACTIVE',
        ]);
    }

    public function test_armis_resource_api_requires_granular_permission(): void
    {
        Sanctum::actingAs($this->user('auditee'));
        $this->getJson('/api/armis/resources')->assertForbidden();
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
