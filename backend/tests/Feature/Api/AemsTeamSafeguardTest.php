<?php

namespace Tests\Feature\Api;

use App\Models\AemsTeamSafeguardAssessment;
use App\Models\AemsTeamSafeguardDeclaration;
use App\Models\ArmisProviderAuthorityDecision;
use App\Models\ArmisProviderReconciliationReview;
use App\Models\ArmisProviderReconciliationRun;
use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisResourceProfile;
use App\Models\AuditEngagement;
use App\Models\EngagementTeam;
use App\Models\IapPlanEngagement;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemConfiguration;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AemsTeamSafeguardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_declarations_require_independent_review_and_approved_assessment_is_immutable(): void
    {
        [$management, $engagement, $team] = $this->engagementWithTeam();
        $requiredDays = max(1, (float) $engagement->planned_person_days / 4);

        foreach ($team as $member) {
            Sanctum::actingAs($member->user);
            $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations", [
                'declarationType' => 'OBJECTIVITY',
                'outcome' => 'CLEAR',
                'statement' => 'I have assessed my objectivity and have no disqualifying matter.',
            ])->assertCreated();
            foreach (['CONFLICT_OF_INTEREST', 'INDEPENDENCE'] as $type) {
                $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations", [
                    'declarationType' => $type,
                    'outcome' => 'CLEAR',
                    'statement' => "I confirm my {$type} declaration for this engagement.",
                ])->assertCreated();
            }
        }

        Sanctum::actingAs($management);
        foreach (AemsTeamSafeguardDeclaration::query()->get() as $declaration) {
            $this->postJson(
                "/api/aems/engagements/{$engagement->id}/team/{$declaration->engagement_team_id}/safeguards/declarations/{$declaration->id}/review",
                ['decision' => 'ACCEPT', 'reviewNotes' => 'Independently reviewed and accepted.'],
            )->assertOk();
        }

        $overview = $this->getJson("/api/aems/engagements/{$engagement->id}/team/safeguards")
            ->assertOk()
            ->assertJsonPath('data.provider.mode', 'ARMIS_AUTHORITATIVE')
            ->json('data');
        $this->assertTrue($overview['approvalReady']);
        $this->assertSame($requiredDays * 4, (float) $overview['reconciliation']['planned']['team']);

        Sanctum::actingAs($team[0]->user);
        $assessment = $this->postJson("/api/aems/engagements/{$engagement->id}/team/safeguards/assess")
            ->assertCreated()
            ->json('data.assessment');
        Sanctum::actingAs($management);
        $approved = $this->postJson("/api/aems/engagements/{$engagement->id}/team/safeguards/approve", [
            'comment' => 'Approved after independent review of provider and safeguards.',
        ])->assertOk()->json('data.assessment');
        $this->assertSame('APPROVED', $approved['status']);
        $this->assertDatabaseHas('aems_team_safeguard_assessments', [
            'id' => $approved['id'],
            'status' => 'APPROVED',
            'is_current_revision' => true,
        ]);

        $this->expectException(LogicException::class);
        AemsTeamSafeguardAssessment::query()->findOrFail($approved['id'])->update(['decision_comment' => 'overwrite']);
    }

    public function test_accepted_declaration_correction_creates_a_new_revision(): void
    {
        [$management, $engagement, $team] = $this->engagementWithTeam();
        $member = $team[0];
        Sanctum::actingAs($member->user);
        $first = $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations", [
            'declarationType' => 'OBJECTIVITY',
            'outcome' => 'CLEAR',
            'statement' => 'My objectivity declaration is complete and accurate.',
        ])->assertCreated()->json('data.declaration');
        Sanctum::actingAs($management);
        $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations/{$first['id']}/review", [
            'decision' => 'ACCEPT',
            'reviewNotes' => 'Accepted after independent review.',
        ])->assertOk();

        Sanctum::actingAs($member->user);
        $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations", [
            'declarationType' => 'OBJECTIVITY',
            'outcome' => 'CLEAR',
            'statement' => 'Correction is submitted as a new immutable declaration version.',
        ])->assertStatus(422);
        $revision = $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations", [
            'declarationType' => 'OBJECTIVITY',
            'outcome' => 'CLEAR',
            'statement' => 'Correction is submitted as a new immutable declaration version.',
            'revisionReason' => 'Corrected the statement to reflect the current assignment.',
        ])->assertCreated()->json('data.declaration');
        $this->assertSame(2, $revision['version_number'] ?? $revision['versionNumber']);
        $this->assertDatabaseHas('aems_team_safeguard_declarations', [
            'id' => $first['id'],
            'is_current_revision' => false,
            'status' => 'ACCEPTED',
        ]);
    }

    public function test_cias_management_may_review_a_declaration_it_prepared_but_assigned_members_are_view_only(): void
    {
        [$management, $engagement, $team] = $this->engagementWithTeam();
        $member = $team[0];

        Sanctum::actingAs($management);
        $prepared = $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations", [
            'declarationType' => 'OBJECTIVITY',
            'outcome' => 'CLEAR',
            'statement' => 'CIAS Management prepared this declaration for the assigned resource.',
        ])->assertCreated()->json('data.declaration');

        $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations/{$prepared['id']}/review", [
            'decision' => 'ACCEPT',
            'reviewNotes' => 'Reviewed by CIAS Management after preparing the declaration.',
        ])->assertOk()->assertJsonPath('data.declaration.status', 'ACCEPTED');

        Sanctum::actingAs($member->user);
        $own = $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations", [
            'declarationType' => 'CONFLICT_OF_INTEREST',
            'outcome' => 'CLEAR',
            'statement' => 'I have reviewed my own conflict-of-interest declaration.',
        ])->assertCreated()->json('data.declaration');

        // The assigned resource can read this version through the safeguards
        // overview, but cannot perform the independent review action.
        $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations/{$own['id']}/review", [
            'decision' => 'ACCEPT',
            'reviewNotes' => 'A member must not self-review.',
        ])->assertStatus(422);
    }

    public function test_cias_head_may_review_her_own_declaration_under_the_explicit_head_exception(): void
    {
        [$management, $engagement] = $this->engagementWithTeam();
        $member = EngagementTeam::query()->create([
            'audit_engagement_id' => $engagement->id,
            'user_id' => $management->id,
            'assignment_role_code' => 'SUPERVISOR',
            'planned_person_days' => 1,
            'assigned_from' => $engagement->planned_start_date,
            'assigned_until' => $engagement->planned_end_date,
            'assigned_by' => $management->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($management);
        $declaration = $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations", [
            'declarationType' => 'OBJECTIVITY',
            'outcome' => 'CLEAR',
            'statement' => 'The CIAS Head records her authorized objectivity declaration.',
        ])->assertCreated()->json('data.declaration');

        $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations/{$declaration['id']}/review", [
            'decision' => 'ACCEPT',
            'reviewNotes' => 'CIAS Head exception applied and recorded in the audit trail.',
        ])->assertOk()->assertJsonPath('data.declaration.status', 'ACCEPTED');
    }

    public function test_self_review_is_controlled_by_permission_not_a_cias_role(): void
    {
        [, $engagement, $team] = $this->engagementWithTeam();
        $member = $team[0];
        $role = Role::query()->where('code', 'agis_user')->firstOrFail();
        $permission = Permission::query()->where('code', 'aems.review.own_submission')->firstOrFail();
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $member->user->unsetRelation('roles');

        Sanctum::actingAs($member->user);
        $declaration = $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations", [
            'declarationType' => 'OBJECTIVITY',
            'outcome' => 'CLEAR',
            'statement' => 'This non-CIAS reviewer has an explicit self-review permission.',
        ])->assertCreated()->json('data.declaration');

        $this->postJson("/api/aems/engagements/{$engagement->id}/team/{$member->id}/safeguards/declarations/{$declaration['id']}/review", [
            'decision' => 'ACCEPT',
            'reviewNotes' => 'Accepted under the explicitly granted self-review permission.',
        ])->assertOk()->assertJsonPath('data.declaration.status', 'ACCEPTED');
    }

    public function test_cancelled_or_archived_engagements_do_not_block_resource_overlap(): void
    {
        [$management, $engagement, $team] = $this->engagementWithTeam();
        $member = $team[2];

        $other = $engagement->replicate();
        $other->forceFill([
            'engagement_code' => 'AEMS-ARCHIVED-OVERLAP-TEST',
            'iap_plan_engagement_id' => null,
            ...AuditEngagement::lifecycleProjectionForStatus('CANCELLED'),
            'is_active' => false,
            'created_by' => $management->id,
            'updated_by' => $management->id,
            'lock_version' => 1,
        ])->save();

        EngagementTeam::query()->create([
            'audit_engagement_id' => $other->id,
            'user_id' => $member->user_id,
            'assignment_role_code' => 'AUDITOR',
            'planned_person_days' => 2,
            'assigned_from' => $engagement->planned_start_date,
            'assigned_until' => $engagement->planned_end_date,
            'assigned_by' => $management->id,
            'is_active' => true,
        ]);

        $evaluation = app(\App\Services\AemsTeamSafeguardService::class)->evaluate($engagement);
        $this->assertEmpty(collect($evaluation['blockers'])->where('code', 'WORKLOAD_OVERLAP')->all());

        $other->forceFill([
            'status' => 'AUTHORIZED',
            'phase' => 'FOUNDATION',
            'administrative_status' => 'ACTIVE',
            'is_active' => true,
        ])->save();
        $evaluation = app(\App\Services\AemsTeamSafeguardService::class)->evaluate($engagement);
        $this->assertNotEmpty(collect($evaluation['blockers'])->where('code', 'WORKLOAD_OVERLAP')->all());

        $other->delete();
        $evaluation = app(\App\Services\AemsTeamSafeguardService::class)->evaluate($engagement);
        $this->assertEmpty(collect($evaluation['blockers'])->where('code', 'WORKLOAD_OVERLAP')->all());
    }

    public function test_authoritative_armis_mode_blocks_missing_resource_data(): void
    {
        [$management, $engagement] = $this->engagementWithTeam();
        $missingMember = $engagement->teamMembers()->firstOrFail();
        ArmisResourceProfile::query()
            ->where('user_id', $missingMember->user_id)
            ->update(['status' => 'INACTIVE']);
        $run = ArmisProviderReconciliationRun::query()->create([
            'run_uuid' => (string) str()->uuid(),
            'source_query_version' => 'ARMIS-6B-v1',
            'fiscal_year' => 2026,
            'provider_mode' => 'ARMIS_SHADOW',
            'status' => 'GENERATED',
            'filters' => ['fiscalYear' => 2026],
            'scope_snapshot' => ['officeIds' => [$management->office_id]],
            'result_snapshot' => [],
            'summary' => ['reviewRequired' => false],
            'result_checksum_sha256' => hash('sha256', 'aems-3a-test'),
            'generated_by' => $management->id,
            'generated_at' => now(),
        ]);
        ArmisProviderReconciliationReview::query()->create([
            'reconciliation_run_id' => $run->id,
            'decision' => 'ACCEPTED',
            'discrepancy_decisions' => [],
            'comment' => 'Accepted for provider authority test.',
            'reviewed_by' => $management->id,
            'reviewed_at' => now(),
        ]);
        ArmisProviderAuthorityDecision::query()->create([
            'reconciliation_run_id' => $run->id,
            'decision_code' => 'ACTIVATE_AUTHORITY',
            'from_mode' => 'ARMIS_SHADOW',
            'to_mode' => 'ARMIS_AUTHORITATIVE',
            'reason' => 'Authority gate test.',
            'decided_by' => $management->id,
            'decided_at' => now(),
        ]);
        $configuration = SystemConfiguration::query()->where('key', 'armis_provider_mode')->firstOrFail();
        $configuration->value = 'ARMIS_AUTHORITATIVE';
        $configuration->save();
        app(\App\Services\RuntimeConfiguration::class)->forget();

        Sanctum::actingAs($management);
        $overview = $this->getJson("/api/aems/engagements/{$engagement->id}/team/safeguards")
            ->assertOk()
            ->assertJsonPath('data.provider.mode', 'ARMIS_AUTHORITATIVE')
            ->json('data');
        $this->assertFalse($overview['approvalReady']);
        $this->assertNotEmpty(collect($overview['blockers'])->where('code', 'ARMIS_PROFILE_MISSING')->all());
    }

    public function test_armis_sole_provider_does_not_require_an_authority_switch_decision(): void
    {
        [$management, $engagement] = $this->engagementWithTeam();
        ArmisProviderAuthorityDecision::query()->delete();
        $configuration = SystemConfiguration::query()->where('key', 'armis_provider_mode')->firstOrFail();
        $configuration->value = 'ARMIS_AUTHORITATIVE';
        $configuration->save();
        app(\App\Services\RuntimeConfiguration::class)->forget();

        Sanctum::actingAs($management);
        $overview = $this->getJson("/api/aems/engagements/{$engagement->id}/team/safeguards")
            ->assertOk()
            ->assertJsonPath('data.provider.mode', 'ARMIS_AUTHORITATIVE')
            ->json('data');
        $this->assertFalse($overview['approvalReady']);
        $this->assertEmpty(collect($overview['blockers'])->where('code', 'RESOURCE_AUTHORITY_NOT_ACTIVE')->all());
        $this->assertEmpty(collect($overview['blockers'])->where('code', 'RESOURCE_PROVIDER_STALE')->all());
        $this->assertArrayNotHasKey('providerFreshness', $overview['checks']);
    }

    /** @return array{User, AuditEngagement, list<\App\Models\EngagementTeam>} */
    private function engagementWithTeam(): array
    {
        $management = User::query()->where('username', 'departmenthead')->firstOrFail();
        $source = IapPlanEngagement::query()->with('plan')->firstOrFail();
        $source->plan->update([
            'status' => 'ACTIVE',
            'approved_at' => now()->subDay(),
            'approved_by' => $management->id,
            'activated_at' => now(),
            'activated_by' => $management->id,
        ]);
        Sanctum::actingAs($management);
        $id = $this->postJson('/api/aems/engagements/import', ['iapPlanEngagementId' => $source->id])
            ->assertCreated()->json('data.engagement.id');
        $this->postJson(
            "/api/aems/engagements/{$id}/transitions/PREPARE_AUTHORIZATION",
            ['lockVersion' => 1],
        )->assertOk();
        $engagement = AuditEngagement::query()->findOrFail($id);
        $users = User::query()->whereHas('role', fn ($role) => $role->where('code', 'agis_user'))->take(4)->get();
        $role = Role::query()->where('code', 'agis_user')->firstOrFail();
        $officeId = $management->office_id;
        while ($users->count() < 4) {
            $user = User::factory()->create([
                'role_id' => $role->id,
                'office_id' => $officeId,
                'employee_id' => 'AEMS-SG-'.($users->count() + 1),
                'position' => 'Internal Auditor',
            ]);
            $user->syncRoleAssignments([$role->id], $role->id);
            $users->push($user->fresh(['role.permissions', 'roles.permissions']));
        }
        $roles = ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'];
        $team = [];
        foreach ($roles as $index => $roleCode) {
            Sanctum::actingAs($management);
            $memberId = $this->postJson("/api/aems/engagements/{$engagement->id}/team", [
                'userId' => $users[$index]->id,
                'assignmentRoleCode' => $roleCode,
                'plannedPersonDays' => max(1, (float) $engagement->planned_person_days / 4),
            ])->assertCreated()->json('data.teamMember.id');
            $team[] = $engagement->teamMembers()->findOrFail($memberId)->load('user');
        }

        // Each dynamically created team member needs an approved ARMIS
        // capacity ledger before an authoritative assignment can be approved.
        foreach ($team as $member) {
            $profile = ArmisResourceProfile::query()
                ->where('user_id', $member->user_id)
                ->where('status', 'ACTIVE')
                ->firstOrFail();
            ArmisCapacitySubmission::query()->firstOrCreate(
                ['resource_profile_id' => $profile->id, 'fiscal_year' => now()->year, 'is_current_revision' => true],
                [
                    'version_number' => 1,
                    'available_person_days' => 180,
                    'status' => 'APPROVED',
                    'approved_by' => $management->id,
                    'approved_at' => now(),
                    'created_by' => $management->id,
                    'updated_by' => $management->id,
                    'lock_version' => 1,
                ],
            );
        }

        return [$management, $engagement->fresh(), $team];
    }
}
