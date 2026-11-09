<?php

namespace Tests\Feature\Api;

use App\Models\AuditEngagement;
use App\Models\EngagementTeam;
use App\Models\IapPlanEngagement;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AemsInitialTeamAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_selected_leader_and_members_can_see_and_save_planning_but_unassigned_users_cannot(): void
    {
        [$head, $leader, $member, $support, $outsider] = $this->people();
        Sanctum::actingAs($head);
        $created = $this->postJson('/api/aems/engagements', $this->payload($head, $leader, $member, $support))
            ->assertCreated()->assertJsonPath('data.engagement.status', 'ENGAGEMENT_PLANNING')
            ->json('data.engagement');
        $id = $created['id'];
        $this->assertDatabaseHas('engagement_teams', ['audit_engagement_id' => $id, 'user_id' => $leader->id, 'assignment_role_code' => 'TEAM_LEADER', 'is_active' => true]);
        $this->assertDatabaseHas('engagement_teams', ['audit_engagement_id' => $id, 'user_id' => $member->id, 'assignment_role_code' => 'AUDITOR', 'is_active' => true]);

        $lock = null;
        foreach ([$leader, $member] as $auditor) {
            Sanctum::actingAs($auditor);
            $this->getJson('/api/aems/engagements')->assertOk()->assertJsonFragment(['id' => $id, 'engagementCode' => $created['engagementCode']]);
            $this->getJson("/api/aems/engagements/{$id}")->assertOk();
            $this->getJson("/api/aems/engagements/{$id}/preliminary-survey")->assertOk()->assertJsonPath('data.canEdit', true);
            $lock = $this->putJson("/api/aems/engagements/{$id}/preliminary-survey", [
                'section' => 'meeting', 'lockVersion' => $lock,
                'data' => ['venue' => 'Saved by '.$auditor->name],
            ])->assertOk()->assertJsonPath('data.survey.meeting.venue', 'Saved by '.$auditor->name)->json('data.lockVersion');
        }

        Sanctum::actingAs($outsider);
        $this->getJson('/api/aems/engagements')->assertOk()->assertJsonMissing(['engagementCode' => $created['engagementCode']]);
        $this->getJson("/api/aems/engagements/{$id}")->assertForbidden();
        $this->putJson("/api/aems/engagements/{$id}/preliminary-survey", ['section' => 'meeting', 'lockVersion' => $lock, 'data' => ['venue' => 'Unauthorized']])->assertForbidden();

        Sanctum::actingAs($support);
        $this->getJson("/api/aems/engagements/{$id}")->assertOk();
        $this->getJson("/api/aems/engagements/{$id}/preliminary-survey")->assertOk()->assertJsonPath('data.canEdit', false);
        $this->putJson("/api/aems/engagements/{$id}/preliminary-survey", ['section' => 'meeting', 'lockVersion' => $lock, 'data' => ['venue' => 'Support']])->assertForbidden();
    }

    public function test_edit_replaces_assignments_and_metadata_only_edit_preserves_the_team(): void
    {
        [$head, $leader, $member, $support, $replacement] = $this->people();
        Sanctum::actingAs($head);
        $payload = $this->payload($head, $leader, $member, $support);
        $created = $this->postJson('/api/aems/engagements', $payload)->assertCreated()->json('data.engagement');
        $id = $created['id'];
        $payload['initialTeamPlan']['teamMemberIds'] = [$replacement->id];
        $updated = $this->putJson("/api/aems/engagements/{$id}", [...$payload, 'lockVersion' => $created['lockVersion']])
            ->assertOk()->json('data.engagement');
        $this->assertDatabaseHas('aems_team_access_history', ['audit_engagement_id' => $id, 'user_id' => $member->id, 'action' => 'REVOKED']);
        unset($payload['initialTeamPlan']);
        $this->putJson("/api/aems/engagements/{$id}", [...$payload, 'lockVersion' => $updated['lockVersion']])->assertOk();
        $this->assertSame([$replacement->id], AuditEngagement::findOrFail($id)->initial_team_plan['teamMemberIds']);
        $this->assertSame(4, EngagementTeam::where('audit_engagement_id', $id)->where('is_active', true)->count());
        Sanctum::actingAs($member);
        $this->getJson("/api/aems/engagements/{$id}")->assertForbidden();
        Sanctum::actingAs($replacement);
        $this->getJson("/api/aems/engagements/{$id}")->assertOk();
        $this->putJson("/api/aems/engagements/{$id}/preliminary-survey", ['section' => 'meeting', 'lockVersion' => null, 'data' => ['venue' => 'Replacement auditor']])->assertOk();
    }

    public function test_iap_import_also_activates_the_selected_audit_team(): void
    {
        [$head, $leader, $member, $support] = $this->people();
        $source = IapPlanEngagement::with('plan')->firstOrFail();
        $source->plan->update([
            'status' => 'ACTIVE', 'approved_at' => now()->subDay(),
            'approved_by' => $head->id, 'activated_at' => now(), 'activated_by' => $head->id,
        ]);
        Sanctum::actingAs($head);
        $id = $this->postJson('/api/aems/engagements/import', [
            'iapPlanEngagementId' => $source->id,
            'initialTeamPlan' => $this->payload($head, $leader, $member, $support)['initialTeamPlan'],
        ])->assertCreated()->json('data.engagement.id');
        Sanctum::actingAs($member);
        $this->getJson("/api/aems/engagements/{$id}")->assertOk();
        $this->putJson("/api/aems/engagements/{$id}/preliminary-survey", [
            'section' => 'meeting', 'lockVersion' => null, 'data' => ['venue' => 'IAP team planning'],
        ])->assertOk();
    }

    public function test_repair_is_idempotent_and_never_restores_a_revoked_assignment(): void
    {
        [$head, $leader, $member, $support] = $this->people();
        Sanctum::actingAs($head);
        $payload = $this->payload($head, $leader, $member, $support);
        $plan = $payload['initialTeamPlan'];
        unset($payload['initialTeamPlan']);
        $id = $this->postJson('/api/aems/engagements', $payload)->assertCreated()->json('data.engagement.id');
        AuditEngagement::findOrFail($id)->update(['initial_team_plan' => $plan]);
        $this->artisan('aems:sync-initial-teams', ['--dry-run' => true])->assertExitCode(0);
        $this->assertSame(0, EngagementTeam::where('audit_engagement_id', $id)->count());
        $this->artisan('aems:sync-initial-teams')->assertExitCode(0);
        $this->artisan('aems:sync-initial-teams')->assertExitCode(0);
        $this->assertSame(4, EngagementTeam::where('audit_engagement_id', $id)->count());
        $assignment = EngagementTeam::where('audit_engagement_id', $id)->where('user_id', $member->id)->firstOrFail();
        $assignment->update(['is_active' => false, 'ended_at' => now()]);
        $assignment->delete();
        $this->artisan('aems:sync-initial-teams')->assertExitCode(0);
        // An unchanged form must not revive a member removed in Audit Team.
        $payload['initialTeamPlan'] = $plan;
        $this->putJson("/api/aems/engagements/{$id}", [...$payload, 'lockVersion' => 1])->assertOk();
        Sanctum::actingAs($leader);
        $this->getJson("/api/aems/engagements/{$id}")->assertOk();
        Sanctum::actingAs($member);
        $this->getJson("/api/aems/engagements/{$id}")->assertForbidden();
    }

    public function test_overlapping_team_roles_reject_creation_without_partial_assignments(): void
    {
        [$head, $leader, $member, $support] = $this->people();
        Sanctum::actingAs($head);
        $payload = $this->payload($head, $leader, $member, $support);
        $payload['initialTeamPlan']['teamMemberIds'] = [$leader->id];
        $before = AuditEngagement::count();
        $this->postJson('/api/aems/engagements', $payload)->assertUnprocessable()->assertJsonValidationErrors('initialTeamPlan');
        $this->assertSame($before, AuditEngagement::count());
    }

    private function people(): array
    {
        $head = User::where('username', 'departmenthead')->firstOrFail();
        $role = Role::where('code', 'agis_user')->firstOrFail();
        $people = [$head];
        for ($i = 0; $i < 4; $i++) {
            $people[] = User::factory()->create(['role_id' => $role->id, 'office_id' => $head->office_id, 'is_active' => true]);
        }

        return $people;
    }

    private function payload(User $head, User $leader, User $member, User $support): array
    {
        $source = IapPlanEngagement::with(['offices', 'auditAreas'])->firstOrFail();

        return [
            'title' => 'Assigned team planning access',
            'specialAuthorityReference' => 'AUTH-TEAM-ACCESS',
            'specialAuthorityTypeCode' => 'MAYOR_DIRECTIVE',
            'specialAuthorityDate' => '2026-11-09',
            'specialAuthorityApprovedBy' => User::where('username', 'mayor')->value('id'),
            'plannedStartDate' => '2026-11-09', 'plannedEndDate' => '2026-11-30',
            'plannedPersonDays' => 10,
            'officeIds' => [$source->offices->firstOrFail()->id],
            'auditAreaIds' => [$source->auditAreas->firstOrFail()->id],
            'initialTeamPlan' => [
                'departmentHeadId' => $head->id, 'teamLeaderId' => $leader->id,
                'teamMemberIds' => [$member->id], 'supportStaffIds' => [$support->id],
            ],
        ];
    }
}
