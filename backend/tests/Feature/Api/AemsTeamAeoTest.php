<?php

namespace Tests\Feature\Api;

use App\Models\AuditEngagement;
use App\Models\AuditEngagementOrderVersion;
use App\Models\AemsTeamSafeguardDeclaration;
use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisResourceProfile;
use App\Models\EngagementTeam;
use App\Models\IapPlanEngagement;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AemsTeamAeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_draft_engagement_cannot_be_assigned_audit_team(): void
    {
        [$management, $engagement] = $this->engagement();
        $engagement->update(['status' => 'DRAFT']);
        $candidate = $this->auditors(1)[0];
        Sanctum::actingAs($management);

        $this->postJson("/api/aems/engagements/{$engagement->id}/team", [
            'userId' => $candidate->id,
            'assignmentRoleCode' => 'AUDITOR',
            'plannedPersonDays' => 2,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['engagement'])
            ->assertJsonPath(
                'errors.engagement.0',
                'Move the engagement from Draft to Authorization Preparation before assigning or amending the Audit Team.',
            );

        $this->assertDatabaseMissing('engagement_teams', [
            'audit_engagement_id' => $engagement->id,
            'user_id' => $candidate->id,
        ]);
    }

    public function test_team_assignment_reassignment_warnings_and_history_are_controlled(): void
    {
        [$management, $engagement] = $this->engagement();
        $auditors = $this->auditors(4);
        Sanctum::actingAs($management);

        $roles = ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'];
        $assignments = collect($roles)->map(function (string $role, int $index) use ($engagement, $auditors) {
            return $this->postJson("/api/aems/engagements/{$engagement->id}/team", [
                'userId' => $auditors[$index]->id,
                'assignmentRoleCode' => $role,
                'plannedPersonDays' => 4,
                'assignedFrom' => '2026-08-03',
                'assignedUntil' => '2026-08-21',
            ])->assertCreated()->json('data.teamMember');
        });

        $overview = $this->getJson("/api/aems/engagements/{$engagement->id}/team")
            ->assertOk()
            ->assertJsonPath('data.summary.members', 4)
            ->assertJsonPath('data.summary.rolesFilled', 4)
            ->json('data');
        $this->assertNotEmpty($overview['warnings']);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $auditors[0]->id,
            'type' => 'AEMS_TEAM_ASSIGNED',
            'module_code' => 'AEMS',
        ]);
        $this->assertDatabaseHas('engagement_events', [
            'audit_engagement_id' => $engagement->id,
            'subject_type' => 'TEAM',
            'action' => 'ASSIGNED',
        ]);

        $replacement = $this->newAuditor('CIAS-AUD-REPLACEMENT');
        $oldAuditor = $assignments->firstWhere('assignment_role_code', 'AUDITOR')
            ?? $assignments[2];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/team/{$oldAuditor['id']}/reassign",
            [
                'replacementUserId' => $replacement->id,
                'reason' => 'Reassigned because of an overlapping field assignment.',
            ],
        )->assertOk()
            ->assertJsonPath('data.teamMember.user_id', $replacement->id);

        $this->assertSoftDeleted('engagement_teams', ['id' => $oldAuditor['id']]);
        $this->assertDatabaseHas('engagement_team_history', [
            'audit_engagement_id' => $engagement->id,
            'action' => 'REASSIGNED_FROM',
        ]);
        $this->assertDatabaseHas('engagement_team_history', [
            'audit_engagement_id' => $engagement->id,
            'action' => 'REASSIGNED_TO',
        ]);
    }

    public function test_aeo_requires_independent_review_uses_immutable_versions_and_exports_approved_pdf(): void
    {
        [$management, $engagement] = $this->engagement();
        $auditors = $this->auditors(4);
        Sanctum::actingAs($management);
        foreach (['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'] as $index => $role) {
            $this->postJson("/api/aems/engagements/{$engagement->id}/team", [
                'userId' => $auditors[$index]->id,
                'assignmentRoleCode' => $role,
                'plannedPersonDays' => 5,
                'assignedFrom' => '2026-08-03',
                'assignedUntil' => '2026-08-21',
            ])->assertCreated();
        }
        $this->installArmisAuthorityControls($engagement, $management, $auditors);
        $preparer = $auditors[1];
        $issuer = $this->newManagement('CIAS-ISSUER-001');
        Sanctum::actingAs($preparer);
        $payload = [
            'authority' => 'Authority is granted under the approved annual plan and CIAS mandate.',
            'objectives' => 'Assess whether the audited controls are properly designed and operating.',
            'scope' => 'Transactions, records, personnel, and systems within the approved period.',
            'effectivityDate' => '2026-08-01',
            'plannedStartDate' => '2026-08-03',
            'plannedEndDate' => '2026-08-21',
        ];
        $this->postJson("/api/aems/engagements/{$engagement->id}/aeo", $payload)
            ->assertCreated();
        $workspace = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->assertOk()->json('data');
        $order = $workspace['order'];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $order['lockVersion']],
        )->assertOk();

        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'REVIEW', 'lockVersion' => $order['lockVersion'], 'comment' => 'Self review'],
        )->assertForbidden();

        Sanctum::actingAs($auditors[3]);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'REVIEW', 'lockVersion' => $order['lockVersion'], 'comment' => 'Reviewed against the approved IAP and current team.'],
        )->assertOk();
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->json('data.order');
        Sanctum::actingAs($management);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'APPROVE', 'lockVersion' => $order['lockVersion'], 'comment' => 'Approved for issuance.'],
        )->assertOk();
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->json('data.order');
        Sanctum::actingAs($issuer);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'ISSUE', 'lockVersion' => $order['lockVersion'], 'comment' => 'Issued to the audit team and auditee office.'],
        )->assertOk();

        $pdf = $this->get("/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $version = AuditEngagementOrderVersion::query()->firstOrFail();
        $this->expectException(LogicException::class);
        $version->update(['authority' => 'This must never overwrite the version.']);
    }

    public function test_cias_head_may_review_approve_and_issue_own_aeo_when_sole_authority(): void
    {
        [$management, $engagement] = $this->engagement();
        $auditors = $this->auditors(4);
        Sanctum::actingAs($management);
        foreach (['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'] as $index => $role) {
            $this->postJson("/api/aems/engagements/{$engagement->id}/team", [
                'userId' => $auditors[$index]->id,
                'assignmentRoleCode' => $role,
                'plannedPersonDays' => 5,
                'assignedFrom' => '2026-08-03',
                'assignedUntil' => '2026-08-21',
            ])->assertCreated();
        }
        $this->installArmisAuthorityControls($engagement, $management, $auditors);

        $payload = [
            'authority' => 'Authority under the approved annual plan and CIAS mandate.',
            'objectives' => 'Review the approved control objectives and operating effectiveness.',
            'scope' => 'Approved BPLD records, transactions, personnel, and systems.',
            'effectivityDate' => '2026-08-01',
            'plannedStartDate' => '2026-08-03',
            'plannedEndDate' => '2026-08-21',
        ];
        $this->postJson("/api/aems/engagements/{$engagement->id}/aeo", $payload)
            ->assertCreated();
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->assertOk()
            ->assertJsonPath('data.authorityMatrix.preparedBy.id', $management->id)
            ->json('data.order');

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $order['lockVersion']],
        )->assertOk();

        // The sole active CIAS Head may perform the complete AEO authority
        // sequence when no alternate CIAS Management authority is designated.
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'REVIEW', 'lockVersion' => $order['lockVersion'], 'comment' => 'CIAS Head review exception recorded.'],
        )->assertOk();

        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->assertJsonPath('data.authorityMatrix.roles.0.status', 'SIGNED')
            ->assertJsonPath('data.authorityMatrix.roles.0.signedBy.id', $management->id)
            ->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'APPROVE', 'lockVersion' => $order['lockVersion'], 'comment' => 'Approved by the sole CIAS Head authority.'],
        )->assertOk();
        $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->assertOk()
            ->assertJsonPath('data.authorityMatrix.roles.1.status', 'SIGNED')
            ->assertJsonPath('data.authorityMatrix.roles.1.signedBy.id', $management->id)
            ->assertJsonPath('data.authorityMatrix.roles.1.signedBy.username', $management->username);
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'ISSUE', 'lockVersion' => $order['lockVersion'], 'comment' => 'Issued by the sole CIAS Head authority.'],
        )->assertOk();
        $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->assertJsonPath('data.authorityMatrix.roles.2.status', 'SIGNED')
            ->assertJsonPath('data.authorityMatrix.roles.2.signedBy.id', $management->id)
            ->assertJsonPath('data.authorityMatrix.roles.2.signedBy.username', $management->username);
    }

    public function test_formal_revision_preserves_the_issued_version_as_pdf_source(): void
    {
        [$management, $engagement] = $this->engagement();
        $auditors = $this->auditors(4);
        Sanctum::actingAs($management);
        foreach (['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'] as $index => $role) {
            $this->postJson("/api/aems/engagements/{$engagement->id}/team", [
                'userId' => $auditors[$index]->id,
                'assignmentRoleCode' => $role,
                'plannedPersonDays' => 5,
            ])->assertCreated();
        }
        $this->installArmisAuthorityControls($engagement, $management, $auditors);
        $payload = [
            'authority' => 'Authority under the approved annual plan.',
            'objectives' => 'Review the engagement objectives and controls.',
            'scope' => 'Approved engagement scope and audit period.',
        ];
        $preparer = $auditors[1];
        $issuer = $this->newManagement('CIAS-ISSUER-002');
        Sanctum::actingAs($preparer);
        $this->postJson("/api/aems/engagements/{$engagement->id}/aeo", $payload)
            ->assertCreated();
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $order['lockVersion']],
        )->assertOk();

        Sanctum::actingAs($auditors[3]);
        foreach (['REVIEW'] as $action) {
            $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
                ->json('data.order');
            $this->postJson(
                "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
                [
                    'action' => $action,
                    'lockVersion' => $order['lockVersion'],
                    'comment' => $action === 'REVIEW' ? 'Independent review completed.' : null,
                ],
            )->assertOk();
        }
        Sanctum::actingAs($management);
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'APPROVE', 'lockVersion' => $order['lockVersion'], 'comment' => 'Independent approval completed.'],
        )->assertOk();
        Sanctum::actingAs($issuer);
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'ISSUE', 'lockVersion' => $order['lockVersion'], 'comment' => 'Issued by a separate authority.'],
        )->assertOk();
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/revise",
            ['lockVersion' => $order['lockVersion'], 'reason' => 'The authorized schedule and scope require revision.'],
        )->assertOk()
            ->assertJsonPath('data.order.status', 'DRAFT');

        $this->get("/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseCount('audit_engagement_order_versions', 2);
    }

    /** @return array{User, AuditEngagement} */
    private function engagement(): array
    {
        $management = $this->user('departmenthead');
        $source = IapPlanEngagement::query()->with('plan')->firstOrFail();
        $source->plan->update([
            'status' => 'ACTIVE',
            'approved_at' => now()->subDay(),
            'approved_by' => $management->id,
            'activated_at' => now(),
            'activated_by' => $management->id,
        ]);
        Sanctum::actingAs($management);
        $id = $this->postJson('/api/aems/engagements/import', [
            'iapPlanEngagementId' => $source->id,
        ])->assertCreated()->json('data.engagement.id');

        // Team assignment opens only after the aggregate lifecycle leaves
        // Draft and enters Authorization Preparation.
        $this->postJson(
            "/api/aems/engagements/{$id}/transitions/PREPARE_AUTHORIZATION",
            ['lockVersion' => 1],
        )->assertOk();

        return [$management, AuditEngagement::query()->findOrFail($id)];
    }

    /** @return list<User> */
    private function auditors(int $count): array
    {
        $users = User::query()
            ->whereHas('role', fn ($role) => $role->where('code', 'agis_user'))
            ->take($count)
            ->get();
        while ($users->count() < $count) {
            $users->push($this->newAuditor('CIAS-AUD-'.($users->count() + 100)));
        }

        return $users->take($count)->values()->all();
    }

    private function newAuditor(string $employeeId): User
    {
        $role = Role::query()->where('code', 'agis_user')->firstOrFail();
        $office = $this->user('auditor')->office;
        $user = User::factory()->create([
            'role_id' => $role->id,
            'office_id' => $office->id,
            'employee_id' => $employeeId,
            'position' => 'Internal Auditor',
        ]);
        $user->syncRoleAssignments([$role->id], $role->id);

        return $user->fresh(['role.permissions', 'roles.permissions', 'office']);
    }

    private function newManagement(string $employeeId): User
    {
        $role = Role::query()->where('code', 'cias_management')->firstOrFail();
        $office = $this->user('departmenthead')->office;
        $user = User::factory()->create([
            'role_id' => $role->id,
            'office_id' => $office->id,
            'employee_id' => $employeeId,
            'position' => 'CIAS Management',
        ]);
        $user->syncRoleAssignments([$role->id], $role->id);

        return $user->fresh(['role.permissions', 'roles.permissions', 'office']);
    }

    /**
     * Seed the ARMIS records required by the authoritative AEO readiness gate.
     * These tests intentionally do not use the retired IAP resource ledgers.
     *
     * @param list<User> $users
     */
    private function installArmisAuthorityControls(AuditEngagement $engagement, User $management, array $users): void
    {
        foreach ($users as $user) {
            $profile = ArmisResourceProfile::withTrashed()
                ->where('user_id', $user->id)
                ->latest('id')
                ->first();
            if (! $profile) {
                $profile = ArmisResourceProfile::query()->create([
                    'resource_code' => 'ARMIS-AEO-'.$user->id,
                    'user_id' => $user->id,
                    'office_id' => $user->office_id,
                    'category' => 'AUDIT_RESOURCE',
                    'status' => 'ACTIVE',
                    'effective_from' => today(),
                    'created_by' => $management->id,
                    'updated_by' => $management->id,
                ]);
            } else {
                if ($profile->trashed()) {
                    $profile->restore();
                }
                $profile->update([
                    'status' => 'ACTIVE',
                    'office_id' => $user->office_id,
                    'updated_by' => $management->id,
                ]);
            }
            $capacity = ArmisCapacitySubmission::query()
                ->where('resource_profile_id', $profile->id)
                ->where('fiscal_year', today()->year)
                ->where('is_current_revision', true)
                ->whereIn('status', ['APPROVED', 'LOCKED'])
                ->first();
            if ($capacity) {
                $capacity->update(['available_person_days' => 10000, 'updated_by' => $management->id]);
            } else {
                ArmisCapacitySubmission::query()->create([
                    'resource_profile_id' => $profile->id,
                    'fiscal_year' => today()->year,
                    'version_number' => 1,
                    'available_person_days' => 10000,
                    'status' => 'APPROVED',
                    'is_current_revision' => true,
                    'approved_by' => $management->id,
                    'approved_at' => now(),
                    'created_by' => $management->id,
                    'updated_by' => $management->id,
                ]);
            }
            $member = EngagementTeam::query()
                ->where('audit_engagement_id', $engagement->id)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->firstOrFail();
            foreach (AemsTeamSafeguardDeclaration::TYPES as $type) {
                AemsTeamSafeguardDeclaration::query()->create([
                    'declaration_family_uuid' => (string) str()->uuid(),
                    'audit_engagement_id' => $engagement->id,
                    'engagement_team_id' => $member->id,
                    'user_id' => $user->id,
                    'declaration_type' => $type,
                    'version_number' => 1,
                    'is_current_revision' => true,
                    'outcome' => 'CLEAR',
                    'statement' => 'AEO authority test declaration is clear.',
                    'status' => 'ACCEPTED',
                    'submitted_by' => $user->id,
                    'submitted_at' => now(),
                    'reviewed_by' => $management->id,
                    'reviewed_at' => now(),
                    'review_notes' => 'Accepted for AEO readiness testing.',
                    'created_by' => $management->id,
                    'updated_by' => $management->id,
                ]);
            }
        }
    }

    private function user(string $username): User
    {
        return User::query()
            ->with(['role.permissions', 'roles.permissions', 'office'])
            ->where('username', $username)
            ->firstOrFail();
    }
}
