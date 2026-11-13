<?php

namespace Tests\Feature\Api;

use App\Models\AemsPlanningPackage;
use App\Models\AemsPlanningPackageVersion;
use App\Models\AuditEngagement;
use App\Models\EngagementTeam;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AemsSurveyReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_auditor_submits_and_assigned_leader_approves_without_changing_input_authorship(): void
    {
        [$head, $leader, $member, , $engagement] = $this->fixture();
        $url = $this->url($engagement);
        Sanctum::actingAs($member);
        $saved = $this->putJson($url, ['section' => 'meeting', 'lockVersion' => null, 'data' => ['venue' => 'CIAS Office']])
            ->assertOk()->assertJsonPath('data.sectionReviews.meeting.status', 'DRAFT')
            ->assertJsonPath('data.sectionReviews.meeting.fieldSaves.venue.user.id', $member->id)->json('data');
        $this->postJson($url.'/review', ['section' => 'meeting', 'action' => 'APPROVE', 'lockVersion' => $saved['lockVersion']])->assertForbidden();
        $pending = $this->postJson($url.'/review', ['section' => 'meeting', 'action' => 'SUBMIT', 'lockVersion' => $saved['lockVersion']])
            ->assertOk()->assertJsonPath('data.sectionReviews.meeting.status', 'PENDING_REVIEW')
            ->assertJsonPath('data.sectionCapabilities.meeting.canEdit', false)->json('data');
        $this->putJson($url, ['section' => 'meeting', 'lockVersion' => $pending['lockVersion'], 'data' => ['venue' => 'Changed while pending']])->assertUnprocessable();
        Sanctum::actingAs($leader);
        $this->getJson($url)->assertOk()->assertJsonPath('data.sectionCapabilities.meeting.canApprove', true);
        $approved = $this->postJson($url.'/review', ['section' => 'meeting', 'action' => 'APPROVE', 'lockVersion' => $pending['lockVersion']])
            ->assertOk()->assertJsonPath('data.sectionReviews.meeting.status', 'APPROVED')
            ->assertJsonPath('data.sectionReviews.meeting.approvedBy.id', $leader->id)
            ->assertJsonPath('data.sectionReviews.meeting.lastSavedBy.id', $member->id)->json('data');
        $package = $engagement->planningPackage()->firstOrFail();
        $this->assertSame('DRAFT', $package->versions()->oldest('version_number')->firstOrFail()->preliminary_survey['workspaceReviews']['meeting']['status']);
        $this->assertSame(3, $package->versions()->count());
        $this->assertDatabaseHas('engagement_events', ['audit_engagement_id' => $engagement->id, 'action' => 'SURVEY_SECTION_APPROVE', 'actor_id' => $leader->id]);
        Sanctum::actingAs($member);
        $this->putJson($url, ['section' => 'meeting', 'lockVersion' => $approved['lockVersion'], 'data' => ['venue' => 'Revised venue']])
            ->assertOk()->assertJsonPath('data.sectionReviews.meeting.status', 'DRAFT')
            ->assertJsonPath('data.sectionReviews.meeting.approvedBy', null);
    }

    public function test_leader_can_approve_own_draft_then_optionally_submit_it_to_head(): void
    {
        [$head, $leader, $member, , $engagement] = $this->fixture();
        $url = $this->url($engagement);
        Sanctum::actingAs($leader);
        $saved = $this->putJson($url, ['section' => 'meeting', 'lockVersion' => null, 'data' => ['venue' => 'Leader venue']])
            ->assertOk()->assertJsonPath('data.sectionCapabilities.meeting.canApprove', true)
            ->assertJsonPath('data.sectionCapabilities.meeting.canSubmit', true)
            ->assertJsonPath('data.sectionCapabilities.meeting.submitToHead', true)->json('data');
        $approved = $this->postJson($url.'/review', ['section' => 'meeting', 'action' => 'APPROVE', 'lockVersion' => $saved['lockVersion']])
            ->assertOk()->assertJsonPath('data.sectionReviews.meeting.status', 'APPROVED')
            ->assertJsonPath('data.sectionReviews.meeting.approvedBy.id', $leader->id)
            ->assertJsonPath('data.sectionReviews.meeting.fieldSaves.venue.user.id', $leader->id)
            ->assertJsonPath('data.sectionCapabilities.meeting.canSubmit', true)->json('data');
        $approved = $this->putJson($url, ['section' => 'meeting', 'lockVersion' => $approved['lockVersion'], 'data' => ['venue' => 'Leader venue']])
            ->assertOk()->assertJsonPath('data.sectionReviews.meeting.status', 'APPROVED')
            ->assertJsonPath('data.sectionCapabilities.meeting.autoApprove', false)->json('data');
        $pending = $this->postJson($url.'/review', ['section' => 'meeting', 'action' => 'SUBMIT', 'lockVersion' => $approved['lockVersion']])
            ->assertOk()->assertJsonPath('data.sectionReviews.meeting.reviewTarget', 'CIAS_HEAD')
            ->assertJsonPath('data.sectionReviews.meeting.approvedBy', null)->json('data');
        $this->getJson($url)->assertOk()->assertJsonPath('data.sectionCapabilities.meeting.canApprove', false);
        $this->postJson($url.'/review', ['section' => 'meeting', 'action' => 'APPROVE', 'lockVersion' => $pending['lockVersion']])->assertForbidden();
        Sanctum::actingAs($head);
        $this->postJson($url.'/review', ['section' => 'meeting', 'action' => 'APPROVE', 'lockVersion' => $pending['lockVersion']])
            ->assertOk()->assertJsonPath('data.sectionReviews.meeting.approvedBy.id', $head->id);
        $selfApprovedVersion = $engagement->planningPackage()->firstOrFail()->versions()->where('version_number', 2)->firstOrFail();
        $this->assertSame($leader->id, $selfApprovedVersion->preliminary_survey['workspaceReviews']['meeting']['approvedBy']['id']);

        Sanctum::actingAs($leader);
        $saved = $this->putJson($url, ['section' => 'auditee', 'lockVersion' => $pending['lockVersion'] + 1, 'data' => ['mandate' => 'Leader mandate']])->assertOk()->json('data');
        $this->postJson($url.'/review', ['section' => 'auditee', 'action' => 'SUBMIT', 'lockVersion' => $saved['lockVersion']])
            ->assertOk()->assertJsonPath('data.sectionReviews.auditee.reviewTarget', 'CIAS_HEAD');
    }

    public function test_leader_can_approve_submitted_work_with_their_own_contribution(): void
    {
        [, $leader, $member, , $engagement] = $this->fixture();
        $url = $this->url($engagement);
        Sanctum::actingAs($leader);
        $saved = $this->putJson($url, ['section' => 'meeting', 'lockVersion' => null, 'data' => ['venue' => 'Leader venue']])->assertOk()->json('data');
        Sanctum::actingAs($member);
        $saved = $this->putJson($url, ['section' => 'meeting', 'lockVersion' => $saved['lockVersion'], 'data' => ['venue' => 'Leader venue', 'focalPerson' => 'Auditor focal person']])
            ->assertOk()->assertJsonPath('data.sectionReviews.meeting.fieldSaves.venue.user.id', $leader->id)
            ->assertJsonPath('data.sectionReviews.meeting.fieldSaves.focalPerson.user.id', $member->id)->json('data');
        $pending = $this->postJson($url.'/review', ['section' => 'meeting', 'action' => 'SUBMIT', 'lockVersion' => $saved['lockVersion']])->assertOk()->json('data');
        Sanctum::actingAs($leader);
        $this->postJson($url.'/review', ['section' => 'meeting', 'action' => 'APPROVE', 'lockVersion' => $pending['lockVersion']])
            ->assertOk()->assertJsonPath('data.sectionReviews.meeting.approvedBy.id', $leader->id);
    }

    public function test_head_saves_are_automatically_approved_and_other_section_drafts_remain_independent(): void
    {
        [$head, , $member, , $engagement] = $this->fixture();
        $url = $this->url($engagement);
        Sanctum::actingAs($member);
        $draft = $this->putJson($url, ['section' => 'meeting', 'lockVersion' => null, 'data' => ['venue' => 'Auditor draft']])->assertOk()->json('data');
        Sanctum::actingAs($head);
        $saved = $this->putJson($url, ['section' => 'auditee', 'lockVersion' => $draft['lockVersion'], 'data' => ['mandate' => 'Public service']])
            ->assertOk()->assertJsonPath('data.sectionReviews.auditee.status', 'APPROVED')
            ->assertJsonPath('data.sectionReviews.auditee.approvedBy.id', $head->id)
            ->assertJsonPath('data.sectionReviews.meeting.status', 'DRAFT')
            ->assertJsonPath('data.sectionCapabilities.auditee.autoApprove', true)->json('data');
        $this->putJson($url, ['section' => 'auditee', 'lockVersion' => $saved['lockVersion'], 'data' => ['mandate' => 'Updated public service']])
            ->assertOk()->assertJsonPath('data.sectionReviews.auditee.status', 'APPROVED')
            ->assertJsonPath('data.sectionReviews.auditee.fieldSaves.mandate.user.id', $head->id);
    }

    public function test_return_instructions_and_stale_review_protection(): void
    {
        [, $leader, $member, , $engagement] = $this->fixture();
        $url = $this->url($engagement);
        Sanctum::actingAs($member);
        $saved = $this->putJson($url, ['section' => 'meeting', 'lockVersion' => null, 'data' => ['venue' => 'Draft venue']])->assertOk()->json('data');
        $pending = $this->postJson($url.'/review', ['section' => 'meeting', 'action' => 'SUBMIT', 'lockVersion' => $saved['lockVersion']])->assertOk()->json('data');
        Sanctum::actingAs($leader);
        $this->postJson($url.'/review', ['section' => 'meeting', 'action' => 'APPROVE', 'lockVersion' => $saved['lockVersion']])->assertUnprocessable()->assertJsonValidationErrors('lockVersion');
        $this->postJson($url.'/review', ['section' => 'meeting', 'action' => 'RETURN', 'lockVersion' => $pending['lockVersion']])->assertUnprocessable()->assertJsonValidationErrors('comment');
        $returned = $this->postJson($url.'/review', ['section' => 'meeting', 'action' => 'RETURN', 'lockVersion' => $pending['lockVersion'], 'comment' => 'Please confirm the venue.'])
            ->assertOk()->assertJsonPath('data.sectionReviews.meeting.status', 'RETURNED_FOR_REVISION')
            ->assertJsonPath('data.sectionReviews.meeting.returnComment', 'Please confirm the venue.')->json('data');
        Sanctum::actingAs($member);
        $this->putJson($url, ['section' => 'meeting', 'lockVersion' => $returned['lockVersion'], 'data' => ['venue' => 'Confirmed venue']])->assertOk()->assertJsonPath('data.sectionReviews.meeting.status', 'DRAFT');
    }

    public function test_unassigned_and_support_users_cannot_review_or_edit_and_generic_api_cannot_forge_approval(): void
    {
        [$head, $leader, $member, $outsider, $engagement] = $this->fixture();
        $url = $this->url($engagement);
        Sanctum::actingAs($member);
        $saved = $this->putJson($url, ['section' => 'meeting', 'lockVersion' => null, 'data' => ['venue' => 'Draft']])->assertOk()->json('data');
        Sanctum::actingAs($outsider);
        $this->getJson($url)->assertForbidden();
        $this->postJson($url.'/review', ['section' => 'meeting', 'action' => 'APPROVE', 'lockVersion' => $saved['lockVersion']])->assertForbidden();
        EngagementTeam::create(['audit_engagement_id' => $engagement->id, 'user_id' => $outsider->id, 'assignment_role_code' => 'AUTHORIZED_PARTICIPANT', 'assigned_by' => $head->id, 'is_active' => true]);
        $this->postJson($url.'/review', ['section' => 'meeting', 'action' => 'APPROVE', 'lockVersion' => $saved['lockVersion']])->assertForbidden();
        $this->putJson($url, ['section' => 'meeting', 'lockVersion' => $saved['lockVersion'], 'data' => ['venue' => 'Support edit']])->assertForbidden();
        Sanctum::actingAs($member);
        $package = $engagement->planningPackage()->firstOrFail();
        $packageUrl = "/api/aems/engagements/{$engagement->id}/planning-package/{$package->id}";
        // Unknown metadata is stripped; authoritative review data is retained.
        $this->putJson($packageUrl, ['lockVersion' => $saved['lockVersion'], 'preliminarySurvey' => ['workspaceReviews' => ['meeting' => ['status' => 'APPROVED']]]])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('data.sectionReviews.meeting.status', 'DRAFT');
        $this->putJson($packageUrl, ['lockVersion' => $package->fresh()->lock_version, 'preliminarySurvey' => ['purpose' => 'Legacy package narrative']])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('data.survey.meeting.venue', 'Draft')->assertJsonPath('data.sectionReviews.meeting.status', 'DRAFT');
    }

    public function test_existing_versions_recover_saved_by_information_without_mutating_history(): void
    {
        [, $leader, $member, , $engagement] = $this->fixture();
        $package = AemsPlanningPackage::create(['audit_engagement_id' => $engagement->id, 'package_code' => 'APP-LEGACY', 'status' => 'DRAFT', 'current_version_number' => 1, 'source_type' => 'PLANNED', 'prepared_by' => $member->id, 'lock_version' => 1]);
        $version = AemsPlanningPackageVersion::create(['planning_package_id' => $package->id, 'version_number' => 1, 'preliminary_survey' => ['workspace' => ['meeting' => ['venue' => 'Earlier venue']]], 'created_by' => $member->id]);
        Sanctum::actingAs($member);
        $this->getJson($this->url($engagement))->assertOk()->assertJsonPath('data.sectionReviews.meeting.lastSavedBy.id', $member->id)->assertJsonPath('data.sectionReviews.meeting.fieldSaves.venue.user.id', $member->id);
        $this->assertArrayNotHasKey('workspaceReviews', $version->fresh()->preliminary_survey);
    }

    private function fixture(): array
    {
        $head = User::where('username', 'departmenthead')->firstOrFail();
        $role = Role::where('code', 'agis_user')->firstOrFail();
        $leader = User::factory()->create(['role_id' => $role->id, 'office_id' => $head->office_id]);
        $member = User::factory()->create(['role_id' => $role->id, 'office_id' => $head->office_id]);
        $outsider = User::factory()->create(['role_id' => $role->id, 'office_id' => $head->office_id]);
        $engagement = AuditEngagement::create(['engagement_code' => 'AEMS-SURVEY-REVIEW', 'title' => 'Survey review', 'source_type' => 'PLANNED', 'objectives' => 'Review controls.', 'scope' => 'Selected office processes.', 'status' => 'ENGAGEMENT_PLANNING', 'created_by' => $head->id, 'updated_by' => $head->id, 'is_active' => true]);
        foreach ([[$leader, 'TEAM_LEADER'], [$member, 'AUDITOR']] as [$person, $assignment]) {
            EngagementTeam::create(['audit_engagement_id' => $engagement->id, 'user_id' => $person->id, 'assignment_role_code' => $assignment, 'assigned_by' => $head->id, 'is_active' => true]);
        }

        return [$head, $leader, $member, $outsider, $engagement];
    }

    private function url(AuditEngagement $engagement): string
    {
        return "/api/aems/engagements/{$engagement->id}/preliminary-survey";
    }
}
