<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ArmisActualPersonDay;
use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisCompetency;
use App\Models\ArmisEngagementAssignment;
use App\Models\ArmisResourceProfile;
use App\Models\AuditEngagement;
use App\Models\AuditLog;
use App\Models\EngagementTeam;
use App\Models\MasterListItem;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArmisAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_assignment_and_actual_foundation_permissions_metadata_and_scope_are_available(): void
    {
        foreach (['assignment_family_uuid', 'version_number', 'supersedes_id', 'is_current_revision', 'lock_version'] as $column) {
            $this->assertTrue(Schema::hasColumn('armis_engagement_assignments', $column));
        }
        foreach (['actual_family_uuid', 'assignment_id', 'is_current_revision', 'variance_reason', 'created_by', 'updated_by'] as $column) {
            $this->assertTrue(Schema::hasColumn('armis_actual_person_days', $column));
        }
        foreach ([
            'armis.assignment.view', 'armis.assignment.manage', 'armis.assignment.review', 'armis.assignment.approve',
            'armis.actuals.view', 'armis.actuals.record', 'armis.actuals.review', 'armis.actuals.approve', 'armis.actuals.revise',
        ] as $permission) {
            $this->assertDatabaseHas('permissions', ['code' => $permission]);
        }

        Sanctum::actingAs($this->user('agisadmin'));
        $this->getJson('/api/armis/assignments/metadata')
            ->assertOk()
            ->assertJsonPath('data.workflow.approvedStatus', 'APPROVED')
            ->assertJsonPath('data.rules.aemsProviderAuthority', 'UNCHANGED');

        Sanctum::actingAs($this->user('auditee'));
        $this->getJson('/api/armis/assignments')->assertForbidden();
        $this->getJson('/api/armis/actuals')->assertForbidden();
    }

    public function test_assignment_workflow_enforces_capacity_competency_separation_and_immutable_revision(): void
    {
        [$admin, $reviewer, $profile, $engagement] = $this->context(20);
        $competency = $this->competency('FINANCIAL_AUDIT');
        ArmisCompetency::query()->create([
            'competency_family_uuid' => (string) Str::uuid(), 'resource_profile_id' => $profile->id,
            'competency_id' => $competency->id, 'version_number' => 1, 'is_current_revision' => true,
            'proficiency_level' => 'ADVANCED', 'status' => 'VERIFIED', 'verified_by' => $reviewer->id,
            'verified_at' => now(), 'created_by' => $admin->id, 'updated_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/armis/assignments', [
            'auditEngagementId' => $engagement->id, 'resourceProfileId' => $profile->id,
            'assignmentRoleCode' => 'AUDITOR', 'assignedFrom' => '2026-08-03', 'assignedUntil' => '2026-08-21',
            'plannedPersonDays' => 8,
            'requiredCompetencies' => [['competencyId' => $competency->id, 'minimumProficiency' => 'INTERMEDIATE']],
        ])->assertCreated()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.requiredCompetencies.0.competencyId', $competency->id);
        $assignmentId = (int) $created->json('data.id');

        $this->postJson("/api/armis/assignments/{$assignmentId}/submit", ['lockVersion' => 1])
            ->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->postJson("/api/armis/assignments/{$assignmentId}/review", ['decision' => 'APPROVE', 'lockVersion' => 2])
            ->assertUnprocessable()->assertJsonValidationErrors('review');

        Sanctum::actingAs($reviewer);
        $this->postJson("/api/armis/assignments/{$assignmentId}/review", ['decision' => 'APPROVE', 'lockVersion' => 2])
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->postJson("/api/armis/assignments/{$assignmentId}/lock", ['lockVersion' => 3])
            ->assertOk()->assertJsonPath('data.status', 'LOCKED');

        Sanctum::actingAs($admin);
        $revision = $this->postJson("/api/armis/assignments/{$assignmentId}/revisions", [
            'lockVersion' => 4, 'plannedPersonDays' => 9, 'notes' => 'Corrected allocation.',
        ])->assertCreated()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.versionNumber', 2);
        $revisionId = (int) $revision->json('data.id');
        $this->assertDatabaseHas('armis_engagement_assignments', ['id' => $assignmentId, 'is_current_revision' => false, 'status' => 'LOCKED']);
        $this->assertDatabaseHas('armis_assignment_competencies', ['assignment_id' => $revisionId, 'competency_id' => $competency->id]);
        $this->assertDatabaseHas('armis_workflow_events', ['subject_type' => ArmisEngagementAssignment::class, 'event_code' => 'ASSIGNMENT_REVISED']);
        $this->assertGreaterThanOrEqual(5, ActivityLog::query()->where('action', 'like', 'armis.assignment.%')->count());
        $this->assertGreaterThanOrEqual(5, AuditLog::query()->where('action', 'like', 'armis.assignment.%')->count());
    }

    public function test_assignment_conflicts_block_overlap_and_capacity_overrun(): void
    {
        [$admin, , $profile, $engagement] = $this->context(5);
        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/armis/assignments', [
            'auditEngagementId' => $engagement->id, 'resourceProfileId' => $profile->id,
            'assignmentRoleCode' => 'AUDITOR', 'assignedFrom' => '2026-08-03', 'assignedUntil' => '2026-08-21',
            'plannedPersonDays' => 8,
        ])->assertCreated();
        $id = (int) $created->json('data.id');
        $this->postJson("/api/armis/assignments/{$id}/submit", ['lockVersion' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('conflicts');

        $engagementTwo = $this->engagement($profile->office_id, 'ARMIS-4A-OVERLAP');
        $existing = ArmisEngagementAssignment::query()->create([
            'assignment_family_uuid' => (string) Str::uuid(), 'audit_engagement_id' => $engagementTwo->id,
            'resource_profile_id' => $profile->id, 'version_number' => 1, 'is_current_revision' => true,
            'assignment_role_code' => 'AUDITOR', 'assigned_from' => '2026-08-03', 'assigned_until' => '2026-08-10',
            'planned_person_days' => 2, 'status' => 'APPROVED', 'created_by' => $admin->id,
        ]);
        $this->getJson("/api/armis/assignments/{$id}/conflicts")
            ->assertOk()->assertJsonFragment(['type' => 'ENGAGEMENT_OVERLAP']);
        $this->assertNotNull($existing->id);

        // Cancellation is retained for audit history but must release the
        // resource for future assignments and conflict checks.
        $engagementTwo->forceFill([
            'status' => 'CANCELLED',
            'administrative_status' => 'CANCELLED',
            'is_active' => false,
        ])->save();
        $conflicts = $this->getJson("/api/armis/assignments/{$id}/conflicts")
            ->assertOk()
            ->json('data.conflicts');
        $this->assertFalse(collect($conflicts)->contains('type', 'ENGAGEMENT_OVERLAP'));
    }

    public function test_actual_person_days_require_approved_assignment_support_variance_and_revision(): void
    {
        [$admin, $reviewer, $profile, $engagement] = $this->context(20);
        $assignment = ArmisEngagementAssignment::query()->create([
            'assignment_family_uuid' => (string) Str::uuid(), 'audit_engagement_id' => $engagement->id,
            'resource_profile_id' => $profile->id, 'version_number' => 1, 'is_current_revision' => true,
            'assignment_role_code' => 'AUDITOR', 'assigned_from' => '2026-08-03', 'assigned_until' => '2026-08-21',
            'planned_person_days' => 8, 'status' => 'APPROVED', 'created_by' => $admin->id,
            'approved_by' => $reviewer->id, 'approved_at' => now(),
        ]);

        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/armis/actuals', [
            'assignmentId' => $assignment->id, 'periodStart' => '2026-08-03', 'periodEnd' => '2026-08-07', 'actualPersonDays' => 6,
        ])->assertCreated()->assertJsonPath('data.status', 'DRAFT');
        $actualId = (int) $created->json('data.id');
        $this->postJson("/api/armis/actuals/{$actualId}/submit", ['lockVersion' => 1])
            ->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->postJson("/api/armis/actuals/{$actualId}/review", ['decision' => 'APPROVE', 'lockVersion' => 2])
            ->assertUnprocessable()->assertJsonValidationErrors('review');

        Sanctum::actingAs($reviewer);
        $this->postJson("/api/armis/actuals/{$actualId}/review", ['decision' => 'APPROVE', 'lockVersion' => 2])
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->postJson("/api/armis/actuals/{$actualId}/lock", ['lockVersion' => 3])
            ->assertOk()->assertJsonPath('data.status', 'LOCKED');

        Sanctum::actingAs($admin);
        $this->postJson("/api/armis/actuals/{$actualId}/revisions", [
            'lockVersion' => 4, 'actualPersonDays' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors('varianceReason');
        $revision = $this->postJson("/api/armis/actuals/{$actualId}/revisions", [
            'lockVersion' => 4, 'actualPersonDays' => 10, 'varianceReason' => 'Fieldwork required additional validated procedures.',
        ])->assertCreated()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.versionNumber', 2);
        $this->assertDatabaseHas('armis_actual_person_days', ['id' => $actualId, 'is_current_revision' => false, 'status' => 'LOCKED']);
        $this->assertDatabaseHas('armis_actual_person_days', ['id' => $revision->json('data.id'), 'supersedes_id' => $actualId, 'is_current_revision' => true]);
    }

    /** @return array{User, User, ArmisResourceProfile, AuditEngagement} */
    private function context(float $capacity): array
    {
        $admin = $this->user('agisadmin');
        $reviewer = $this->user('departmenthead');
        $resourceUser = $this->user('auditor');
        $engagement = $this->engagement($resourceUser->office_id, 'ARMIS-4A-'.str()->upper(str()->random(5)));
        $profile = ArmisResourceProfile::query()->create([
            'resource_code' => 'ARMIS-4A-'.str()->upper(str()->random(5)), 'user_id' => $resourceUser->id,
            'office_id' => $resourceUser->office_id, 'category' => 'AUDIT_RESOURCE', 'status' => 'ACTIVE',
            'created_by' => $admin->id, 'updated_by' => $admin->id,
        ]);
        ArmisCapacitySubmission::query()->create([
            'resource_profile_id' => $profile->id, 'fiscal_year' => 2026, 'version_number' => 1,
            'available_person_days' => $capacity, 'status' => 'LOCKED', 'is_current_revision' => true,
            'created_by' => $admin->id, 'approved_by' => $reviewer->id, 'approved_at' => now(),
        ]);

        return [$admin, $reviewer, $profile, $engagement];
    }

    private function engagement(int $officeId, string $code): AuditEngagement
    {
        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => $code, 'title' => 'ARMIS assignment control audit', 'source_type' => 'SPECIAL',
            'special_authority_reference' => 'ARMIS-4A-AUTH', 'special_authority_date' => today(),
            'special_authority_approved_by' => $management->id, 'objectives' => 'Test ARMIS assignment controls.',
            'scope' => 'Resource assignment and actual person-days.', 'planned_start_date' => '2026-08-01',
            'planned_end_date' => '2026-08-31', 'planned_person_days' => 20, 'status' => 'FIELDWORK',
            'created_by' => $auditor->id, 'updated_by' => $management->id,
        ]);
        $engagement->offices()->attach($officeId, ['is_primary' => true]);
        EngagementTeam::query()->create([
            'audit_engagement_id' => $engagement->id, 'user_id' => $auditor->id,
            'assignment_role_code' => 'TEAM_LEADER', 'assigned_by' => $management->id, 'is_active' => true,
        ]);
        return $engagement;
    }

    private function competency(string $code): MasterListItem
    {
        return MasterListItem::query()->where('code', $code)->whereHas('masterList', fn ($query) => $query->where('code', 'IAP_AUDITOR_SPECIALIZATION'))->firstOrFail();
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
