<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ArmisAvailabilityPeriod;
use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisResourceProfile;
use App\Models\ArmisWorkloadAllocation;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArmisPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_planning_schema_permissions_metadata_and_scope_routes_are_available(): void
    {
        foreach (['availability_family_uuid', 'version_number', 'supersedes_id', 'is_current_revision', 'created_by', 'updated_by'] as $column) {
            $this->assertTrue(Schema::hasColumn('armis_availability_periods', $column));
        }
        foreach (['is_current_revision', 'created_by', 'updated_by'] as $column) {
            $this->assertTrue(Schema::hasColumn('armis_capacity_submissions', $column));
        }
        foreach (['workload_family_uuid', 'version_number', 'supersedes_id', 'is_current_revision', 'updated_by'] as $column) {
            $this->assertTrue(Schema::hasColumn('armis_workload_allocations', $column));
        }
        foreach (['armis.availability.review', 'armis.availability.approve', 'armis.capacity.review', 'armis.capacity.approve', 'armis.workload.review', 'armis.workload.approve'] as $permission) {
            $this->assertDatabaseHas('permissions', ['code' => $permission]);
        }

        Sanctum::actingAs($this->user('agisadmin'));
        $this->getJson('/api/armis/planning/metadata')->assertOk()->assertJsonPath('data.workflow.reviewStatus', 'SUBMITTED')->assertJsonPath('data.provider.mode', 'ARMIS_AUTHORITATIVE');
        $this->getJson('/api/armis/utilization?fiscalYear=2026')->assertOk()->assertJsonStructure(['data' => ['rows', 'summary']]);
        Sanctum::actingAs($this->user('auditee'));
        $this->getJson('/api/armis/availability')->assertForbidden();
    }

    public function test_capacity_is_submitted_independently_approved_locked_and_stale_updates_are_rejected(): void
    {
        [$admin, $reviewer, $profile] = $this->planningActors();
        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/armis/capacity', [
            'resourceProfileId' => $profile->id, 'fiscalYear' => 2026, 'availablePersonDays' => 100, 'notes' => 'Annual capacity',
        ])->assertCreated()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.versionNumber', 1);
        $capacityId = (int) $created->json('data.id');
        $this->postJson("/api/armis/capacity/{$capacityId}/submit", ['lockVersion' => 1])->assertOk()->assertJsonPath('data.status', 'SUBMITTED');

        Sanctum::actingAs($reviewer);
        $this->postJson("/api/armis/capacity/{$capacityId}/review", ['decision' => 'APPROVE', 'lockVersion' => 2])
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->postJson("/api/armis/capacity/{$capacityId}/lock", ['lockVersion' => 3])
            ->assertOk()->assertJsonPath('data.status', 'LOCKED');
        $this->assertDatabaseHas('armis_workflow_events', ['subject_id' => $capacityId, 'event_code' => 'CAPACITY_LOCKED']);
        $this->assertSame(4, ActivityLog::query()->where('action', 'like', 'armis.capacity.%')->count());
        $this->assertSame(4, AuditLog::query()->where('action', 'like', 'armis.capacity.%')->count());

        Sanctum::actingAs($admin);
        $this->putJson("/api/armis/capacity/{$capacityId}", [
            'availablePersonDays' => 80, 'lockVersion' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('lockVersion');
    }

    public function test_availability_rejects_overlap_and_supports_returned_correction_and_lock(): void
    {
        [$admin, $reviewer, $profile] = $this->planningActors();
        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/armis/availability', [
            'resourceProfileId' => $profile->id, 'availabilityType' => 'LEAVE', 'startDate' => '2026-06-01', 'endDate' => '2026-06-05', 'personDays' => 5,
        ])->assertCreated()->assertJsonPath('data.status', 'DRAFT');
        $id = (int) $created->json('data.id');
        $this->postJson('/api/armis/availability', [
            'resourceProfileId' => $profile->id, 'availabilityType' => 'TRAINING', 'startDate' => '2026-06-03', 'endDate' => '2026-06-10', 'personDays' => 8,
        ])->assertUnprocessable();
        $this->postJson("/api/armis/availability/{$id}/submit", ['lockVersion' => 1])->assertOk()->assertJsonPath('data.status', 'SUBMITTED');

        Sanctum::actingAs($reviewer);
        $this->postJson("/api/armis/availability/{$id}/review", ['decision' => 'RETURN', 'lockVersion' => 2, 'notes' => 'Clarify the leave record.'])
            ->assertOk()->assertJsonPath('data.status', 'RETURNED');
        Sanctum::actingAs($admin);
        $updated = $this->putJson("/api/armis/availability/{$id}", ['personDays' => 4, 'lockVersion' => 3])
            ->assertOk()->assertJsonPath('data.lockVersion', 4);
        $this->postJson("/api/armis/availability/{$id}/submit", ['lockVersion' => 4])->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        Sanctum::actingAs($reviewer);
        $this->postJson("/api/armis/availability/{$id}/review", ['decision' => 'APPROVE', 'lockVersion' => 5])->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->postJson("/api/armis/availability/{$id}/lock", ['lockVersion' => 6])->assertOk()->assertJsonPath('data.status', 'LOCKED');
        $revision = $this->postJson("/api/armis/availability/{$id}/revisions", ['personDays' => 3, 'lockVersion' => 7])
            ->assertCreated()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.versionNumber', 2);
        $this->assertDatabaseHas('armis_availability_periods', ['id' => $id, 'is_current_revision' => false, 'status' => 'LOCKED']);
        $this->assertDatabaseHas('armis_availability_periods', ['id' => $revision->json('data.id'), 'supersedes_id' => $id, 'is_current_revision' => true]);
    }

    public function test_workload_requires_capacity_and_utilization_uses_only_approved_current_plans(): void
    {
        [$admin, $reviewer, $profile] = $this->planningActors();
        Sanctum::actingAs($admin);
        $workload = $this->postJson('/api/armis/workload', [
            'resourceProfileId' => $profile->id, 'sourceType' => 'TEST_PLAN', 'sourceId' => 101, 'fiscalYear' => 2026, 'plannedPersonDays' => 60,
        ])->assertCreated();
        $workloadId = (int) $workload->json('data.id');
        $this->postJson("/api/armis/workload/{$workloadId}/submit", ['lockVersion' => 1])->assertOk();
        Sanctum::actingAs($reviewer);
        $this->postJson("/api/armis/workload/{$workloadId}/review", ['decision' => 'APPROVE', 'lockVersion' => 2])->assertUnprocessable();

        Sanctum::actingAs($admin);
        $capacity = $this->postJson('/api/armis/capacity', ['resourceProfileId' => $profile->id, 'fiscalYear' => 2026, 'availablePersonDays' => 100])->assertCreated();
        $capacityId = (int) $capacity->json('data.id');
        $this->postJson("/api/armis/capacity/{$capacityId}/submit", ['lockVersion' => 1])->assertOk();
        Sanctum::actingAs($reviewer);
        $this->postJson("/api/armis/capacity/{$capacityId}/review", ['decision' => 'APPROVE', 'lockVersion' => 2])->assertOk();
        $this->postJson("/api/armis/capacity/{$capacityId}/lock", ['lockVersion' => 3])->assertOk();
        Sanctum::actingAs($reviewer);
        $this->postJson("/api/armis/workload/{$workloadId}/review", ['decision' => 'APPROVE', 'lockVersion' => 2])->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->postJson("/api/armis/workload/{$workloadId}/lock", ['lockVersion' => 3])->assertOk();
        Sanctum::actingAs($admin);
        $this->getJson('/api/armis/utilization?fiscalYear=2026&resourceProfileId='.$profile->id)
            ->assertOk()->assertJsonPath('data.rows.0.capacityPersonDays', 100)->assertJsonPath('data.rows.0.plannedPersonDays', 60)->assertJsonPath('data.rows.0.utilizationPercent', 60);
        $this->assertDatabaseHas('armis_workload_allocations', ['id' => $workloadId, 'status' => 'LOCKED', 'is_current_revision' => true]);
    }

    public function test_submitter_or_resource_owner_cannot_review_planning_record(): void
    {
        [$admin, $reviewer, $profile] = $this->planningActors();
        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/armis/capacity', ['resourceProfileId' => $profile->id, 'fiscalYear' => 2027, 'availablePersonDays' => 10])->assertCreated();
        $id = (int) $created->json('data.id');
        $this->postJson("/api/armis/capacity/{$id}/submit", ['lockVersion' => 1])->assertOk();
        $this->postJson("/api/armis/capacity/{$id}/review", ['decision' => 'APPROVE', 'lockVersion' => 2])->assertUnprocessable();

        Sanctum::actingAs($reviewer);
        $this->postJson("/api/armis/capacity/{$id}/review", ['decision' => 'APPROVE', 'lockVersion' => 2])->assertOk();
    }

    /** @return array{0: User, 1: User, 2: ArmisResourceProfile} */
    private function planningActors(): array
    {
        $admin = $this->user('agisadmin');
        $reviewer = $this->user('departmenthead');
        $owner = $this->user('auditor');
        $profile = ArmisResourceProfile::query()->create([
            'resource_code' => 'ARMIS-PLAN-'.fake()->unique()->numerify('####'), 'user_id' => $owner->id, 'office_id' => $owner->office_id,
            'category' => 'AUDIT_RESOURCE', 'status' => 'ACTIVE', 'created_by' => $admin->id, 'updated_by' => $admin->id,
        ]);

        return [$admin, $reviewer, $profile];
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
