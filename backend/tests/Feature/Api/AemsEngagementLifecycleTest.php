<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\AuditArea;
use App\Models\AuditEngagement;
use App\Models\AuditEngagementOrder;
use App\Models\AuditEngagementPlan;
use App\Models\AuditLog;
use App\Models\AuditProgram;
use App\Models\AemsPlanningPackage;
use App\Models\AemsPlanningPackageVersion;
use App\Models\AemsPlanningKpi;
use App\Models\AemsPlanningObjective;
use App\Models\AemsPlannedWorkingPaperRequirement;
use App\Models\AemsProcessFlowDocument;
use App\Models\AemsRiskMatrix;
use App\Models\AemsRiskMatrixItem;
use App\Models\AemsRiskWorkingPaperLink;
use App\Models\AemsAeoDistribution;
use App\Models\AemsTeamSafeguardDeclaration;
use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisResourceProfile;
use App\Models\EngagementEvent;
use App\Models\EngagementTeam;
use App\Models\EntryConference;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AemsEngagementLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_lifecycle_workspace_is_readable_through_the_engagement_policy(): void
    {
        [$management, , , $engagement] = $this->engagement('DRAFT');
        Sanctum::actingAs($management);

        $this->getJson("/api/aems/engagements/{$engagement->id}/lifecycle")
            ->assertOk()
            ->assertJsonPath('data.engagement.status', 'DRAFT')
            ->assertJsonStructure(['data' => ['actions', 'states', 'timeline']]);
    }

    public function test_approved_unissued_order_allows_own_authorization_with_self_review_permission(): void
    {
        [$management, $auditor, , $engagement] = $this->engagement('AUTHORIZATION_PREPARATION');
        $engagement->update(['created_by' => $management->id]);
        AuditEngagementOrder::create([
            'audit_engagement_id' => $engagement->id,
            'order_code' => 'AEO-APPROVED-ONLY',
            'status' => 'APPROVED',
            'current_version_number' => 1,
            'prepared_by' => $auditor->id,
            'approved_by' => $management->id,
            'approved_at' => now(),
            'is_active' => true,
        ]);
        Sanctum::actingAs($management);
        $actions = $this->getJson("/api/aems/engagements/{$engagement->id}/lifecycle")
            ->assertOk()->json('data.actions');
        $this->assertTrue(collect($actions)->firstWhere('action', 'ISSUE_AUTHORIZATION')['canExecute']);
        $this->postJson("/api/aems/engagements/{$engagement->id}/transitions/ISSUE_AUTHORIZATION", [
            'lockVersion' => 1,
        ])->assertOk()->assertJsonPath('data.engagement.status', 'AUTHORIZED');
        $this->postJson("/api/aems/engagements/{$engagement->id}/transitions/START_PLANNING", [
            'lockVersion' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('requirements');
    }

    public function test_planning_requires_current_version_acknowledgement_for_canonical_office(): void
    {
        [$management, $auditor, $auditee, $engagement] = $this->engagement('AUTHORIZED');
        $this->installRequiredTeamAndAeo($engagement, $management, $auditor);
        $engagement->update(['engagement_office_id' => $auditee->office_id]);
        $engagement->offices()->detach();
        $order = AuditEngagementOrder::where('audit_engagement_id', $engagement->id)->firstOrFail();
        $order->update(['current_version_number' => 2]);
        $distribution = $order->distributions()->firstOrFail();
        Sanctum::actingAs($management);
        $url = "/api/aems/engagements/{$engagement->id}/transitions/START_PLANNING";
        $this->postJson($url, ['lockVersion' => 1])->assertUnprocessable();
        $distribution->update([
            'version_number' => 2,
            'recipient_office_id' => Office::where('id', '!=', $auditee->office_id)->firstOrFail()->id,
        ]);
        $this->postJson($url, ['lockVersion' => 1])->assertUnprocessable();
        $distribution->update([
            'recipient_type' => 'USER',
            'recipient_office_id' => null,
            'recipient_user_id' => $auditee->id,
        ]);
        $this->postJson($url, ['lockVersion' => 1])->assertOk()
            ->assertJsonPath('data.engagement.status', 'ENGAGEMENT_PLANNING');
    }

    public function test_sole_cias_head_can_authorize_an_issued_aeo_and_start_planning(): void
    {
        [$management, $auditor, , $engagement] = $this->engagement('DRAFT');
        $engagement->update([
            'created_by' => $management->id,
            'updated_by' => $management->id,
        ]);
        Sanctum::actingAs($management);

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/PREPARE_AUTHORIZATION",
            ['lockVersion' => 1],
        )->assertOk()->assertJsonPath('data.engagement.status', 'AUTHORIZATION_PREPARATION');

        $this->installRequiredTeamAndAeo($engagement, $management, $auditor);
        AuditEngagementOrder::query()
            ->where('audit_engagement_id', $engagement->id)
            ->update([
                'prepared_by' => $management->id,
                'approved_by' => $management->id,
                'issued_by' => $management->id,
            ]);

        $workspace = $this->getJson("/api/aems/engagements/{$engagement->id}/lifecycle")
            ->assertOk()
            ->json('data');
        $authorize = collect($workspace['actions'])->firstWhere('action', 'ISSUE_AUTHORIZATION');
        $this->assertNotNull($authorize);
        $this->assertTrue($authorize['canExecute']);

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/ISSUE_AUTHORIZATION",
            ['lockVersion' => 2],
        )->assertOk()->assertJsonPath('data.engagement.status', 'AUTHORIZED');

        $planningWorkspace = $this->getJson("/api/aems/engagements/{$engagement->id}/lifecycle")
            ->assertOk()
            ->json('data');
        $startPlanning = collect($planningWorkspace['actions'])->firstWhere('action', 'START_PLANNING');
        $this->assertNotNull($startPlanning);
        $this->assertTrue($startPlanning['canExecute']);

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/START_PLANNING",
            ['lockVersion' => 3],
        )->assertOk()->assertJsonPath('data.engagement.status', 'ENGAGEMENT_PLANNING');
    }

    public function test_planning_is_blocked_until_the_auditee_office_acknowledges_the_issued_aeo(): void
    {
        [$management, $auditor, , $engagement] = $this->engagement('DRAFT');
        Sanctum::actingAs($management);

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/PREPARE_AUTHORIZATION",
            ['lockVersion' => 1],
        )->assertOk();

        $this->installRequiredTeamAndAeo($engagement, $management, $auditor);
        AemsAeoDistribution::query()
            ->whereHas('order', fn ($query) => $query->where('audit_engagement_id', $engagement->id))
            ->delete();

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/ISSUE_AUTHORIZATION",
            ['lockVersion' => 2],
        )->assertOk();

        $workspace = $this->getJson("/api/aems/engagements/{$engagement->id}/lifecycle")
            ->assertOk()
            ->json('data');
        $startPlanning = collect($workspace['actions'])->firstWhere('action', 'START_PLANNING');
        $this->assertNotNull($startPlanning);
        $this->assertFalse($startPlanning['canExecute']);
        $this->assertContains('Auditee office has acknowledged the issued AEO', $startPlanning['blockers']);

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/START_PLANNING",
            ['lockVersion' => 3],
        )->assertUnprocessable()->assertJsonValidationErrors('requirements');
    }

    public function test_authoritative_lifecycle_enforces_child_gates_locking_and_records_all_logs(): void
    {
        [$management, $auditor, $auditee, $engagement] = $this->engagement('DRAFT');
        Sanctum::actingAs($management);

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/PREPARE_AUTHORIZATION",
            ['lockVersion' => 1],
        )->assertOk()->assertJsonPath('data.engagement.status', 'AUTHORIZATION_PREPARATION');

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/ISSUE_AUTHORIZATION",
            ['lockVersion' => 2],
        )->assertUnprocessable()->assertJsonValidationErrors('requirements');

        $this->installRequiredTeamAndAeo($engagement, $management, $auditor);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/ISSUE_AUTHORIZATION",
            ['lockVersion' => 2],
        )->assertOk()->assertJsonPath('data.engagement.status', 'AUTHORIZED');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/START_PLANNING",
            ['lockVersion' => 3],
        )->assertOk()->assertJsonPath('data.engagement.status', 'ENGAGEMENT_PLANNING');

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/START_ENTRY_CONFERENCE",
            ['lockVersion' => 4],
        )->assertUnprocessable()->assertJsonValidationErrors('requirements');

        $this->installApprovedPlanningRecords($engagement, $management, $auditor);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/START_ENTRY_CONFERENCE",
            ['lockVersion' => 4],
        )->assertOk()->assertJsonPath('data.engagement.status', 'ENTRY_CONFERENCE');

        Sanctum::actingAs($auditor);
        $conference = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/entry-conference",
            $this->conferenceRecord($auditor, $auditee),
        )->assertCreated()->assertJsonPath('data.conference.status', 'DRAFT')
            ->json('data.conference');

        Sanctum::actingAs($management);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/START_FIELDWORK",
            ['lockVersion' => 5],
        )->assertUnprocessable()->assertJsonValidationErrors('requirements');

        Sanctum::actingAs($auditor);
        $conference = $this->conferenceTransition(
            $engagement,
            $conference,
            'SCHEDULE',
            [
                'scheduledStartAt' => now()->addDay()->toISOString(),
                'scheduledEndAt' => now()->addDay()->addHour()->toISOString(),
                'venue' => 'CIAS Conference Room',
            ],
        )->assertOk()->json('data.conference');
        $attendance = collect($conference['participants'])->map(fn (array $participant): array => [
            'participantId' => $participant['id'],
            'attendanceStatus' => 'ATTENDED',
        ])->all();
        $conference = $this->conferenceTransition(
            $engagement,
            $conference,
            'MARK_HELD',
            ['heldAt' => now()->toISOString(), 'participantAttendance' => $attendance],
        )->assertOk()->json('data.conference');
        $conference = $this->conferenceTransition(
            $engagement,
            $conference,
            'CIRCULATE_NOTES',
        )->assertOk()->json('data.conference');
        $conference = $this->conferenceTransition(
            $engagement,
            $conference,
            'COMPLETE',
        )->assertOk()->assertJsonPath('data.conference.immutable', true)
            ->json('data.conference');

        Sanctum::actingAs($management);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/START_FIELDWORK",
            ['lockVersion' => 5],
        )->assertOk()->assertJsonPath('data.engagement.status', 'FIELDWORK');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/SUSPEND",
            [
                'lockVersion' => 5,
                'comment' => 'Awaiting legal clarification.',
                'authority' => 'City Internal Auditor',
                'effectiveDate' => today()->toDateString(),
                'expectedReviewDate' => today()->addWeek()->toDateString(),
                'resumeRequirements' => 'Written legal clarification received.',
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('lockVersion');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/SUSPEND",
            [
                'lockVersion' => 6,
                'comment' => 'Awaiting legal clarification.',
                'authority' => 'City Internal Auditor',
                'effectiveDate' => today()->toDateString(),
                'expectedReviewDate' => today()->addWeek()->toDateString(),
                'resumeRequirements' => 'Written legal clarification received.',
            ],
        )->assertOk()
            ->assertJsonPath('data.engagement.status', 'SUSPENDED')
            ->assertJsonPath('data.engagement.suspendedFromStatus', 'FIELDWORK');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/RESUME",
            ['lockVersion' => 7, 'comment' => 'Legal clarification received.'],
        )->assertOk()->assertJsonPath('data.engagement.status', 'FIELDWORK');

        $this->assertDatabaseHas('engagement_events', [
            'audit_engagement_id' => $engagement->id,
            'subject_type' => 'ENTRY_CONFERENCE',
            'action' => 'aems.entry-conference.complete',
        ]);
        $this->assertGreaterThanOrEqual(
            8,
            EngagementEvent::query()->where('audit_engagement_id', $engagement->id)->count(),
        );
        $this->assertGreaterThanOrEqual(
            8,
            ActivityLog::query()->where('action', 'like', 'aems.%')->count(),
        );
        $this->assertGreaterThanOrEqual(
            8,
            AuditLog::query()->where('auditable_id', $engagement->id)->count(),
        );
        $this->assertDatabaseHas('notifications', [
            'type' => 'AEMS_ENTRY_CONFERENCE_COMPLETE',
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => 'AEMS_ENGAGEMENT_SUSPEND',
        ]);
    }

    public function test_entry_conference_completion_requires_approved_records_and_both_attendance_types(): void
    {
        [$management, $auditor, $auditee, $engagement] = $this->engagement('ENTRY_CONFERENCE');
        $this->installRequiredTeamAndAeo($engagement, $management, $auditor);
        Sanctum::actingAs($auditor);
        $conference = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/entry-conference",
            $this->conferenceRecord($auditor, $auditee),
        )->assertCreated()->json('data.conference');
        $conference = $this->conferenceTransition($engagement, $conference, 'SCHEDULE', [
            'scheduledStartAt' => now()->addDay()->toISOString(),
            'venue' => 'Online',
        ])->assertOk()->json('data.conference');
        $attendance = collect($conference['participants'])->map(fn (array $participant): array => [
            'participantId' => $participant['id'],
            'attendanceStatus' => $participant['participantType'] === 'AUDITEE'
                ? 'ABSENT'
                : 'ATTENDED',
        ])->all();
        $conference = $this->conferenceTransition($engagement, $conference, 'MARK_HELD', [
            'heldAt' => now()->toISOString(),
            'participantAttendance' => $attendance,
        ])->assertOk()->json('data.conference');
        $conference = $this->conferenceTransition(
            $engagement,
            $conference,
            'CIRCULATE_NOTES',
        )->assertOk()->json('data.conference');
        $this->conferenceTransition($engagement, $conference, 'COMPLETE')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requirements')
            ->assertJsonFragment(['The AEP must remain approved.'])
            ->assertJsonFragment(['The current Audit Program must remain approved.'])
            ->assertJsonFragment(['At least one auditee participant must attend.']);
    }

    public function test_planning_controls_are_evaluated_from_the_approved_baseline(): void
    {
        [$management, $auditor, $auditee, $engagement] = $this->engagement('ENGAGEMENT_PLANNING');
        $this->installRequiredTeamAndAeo($engagement, $management, $auditor);
        $this->installApprovedPlanningRecords($engagement, $management, $auditor);
        $package = AemsPlanningPackage::query()->where('audit_engagement_id', $engagement->id)->firstOrFail();
        AemsPlanningPackageVersion::query()->create([
            'planning_package_id' => $package->id,
            'version_number' => 2,
            'planning_attributes' => [
                'kpis' => [
                    'decision' => 'REQUIRED',
                    'items' => [],
                ],
            ],
            'iap_lineage_snapshot' => [],
            'created_by' => $auditor->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($management);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/START_ENTRY_CONFERENCE",
            ['lockVersion' => 1],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('requirements');
    }

    public function test_entry_conference_waiver_requires_elevated_authority_reason_and_separation(): void
    {
        [$management, $auditor, $auditee, $engagement] = $this->engagement('ENTRY_CONFERENCE');
        Sanctum::actingAs($auditor);
        $conference = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/entry-conference",
            $this->conferenceRecord($auditor, $auditee),
        )->assertCreated()->json('data.conference');

        Sanctum::actingAs($auditee);
        $this->getJson('/api/aems/entry-conference-workspaces')
            ->assertOk()
            ->assertJsonPath('data.engagements.0.id', $engagement->id);

        Sanctum::actingAs($auditor);
        $this->conferenceTransition($engagement, $conference, 'WAIVE', [
            'reason' => 'Urgent statutory fieldwork.',
            'authority' => 'City Internal Auditor',
        ])->assertForbidden();

        Sanctum::actingAs($management);
        $this->conferenceTransition($engagement, $conference, 'WAIVE', [
            'authority' => 'City Internal Auditor',
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->conferenceTransition($engagement, $conference, 'WAIVE', [
            'reason' => 'Urgent statutory fieldwork approved under the cited authority.',
            'authority' => 'City Internal Auditor',
            'supportingDocumentRequired' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('supportingDocument');

        Sanctum::actingAs($auditor);
        $this->post(
            "/api/aems/engagements/{$engagement->id}/entry-conference/{$conference['id']}/attachments",
            [
                'file' => UploadedFile::fake()->create('waiver-authority.pdf', 20, 'application/pdf'),
                'category' => 'WAIVER_SUPPORT',
                'caption' => 'Approved waiver authority',
                'lockVersion' => $conference['lockVersion'],
            ],
            ['Accept' => 'application/json'],
        )->assertCreated()
            ->assertJsonPath('data.attachment.fileVersionNumber', 1);
        $conference['lockVersion']++;

        Sanctum::actingAs($management);
        $this->conferenceTransition($engagement, $conference, 'WAIVE', [
            'reason' => 'Urgent statutory fieldwork approved under the cited authority.',
            'authority' => 'City Internal Auditor',
            'supportingDocumentRequired' => true,
        ])->assertOk()
            ->assertJsonPath('data.conference.status', 'WAIVED')
            ->assertJsonPath('data.conference.immutable', true)
            ->assertJsonPath('data.conference.attachments.0.category', 'WAIVER_SUPPORT');
    }

    public function test_cancellation_is_terminal_preserves_children_and_direct_status_update_is_rejected(): void
    {
        [$management, $auditor, $auditee, $engagement] = $this->engagement('FIELDWORK');
        $this->installRequiredTeamAndAeo($engagement, $management, $auditor);
        $entry = EntryConference::query()->create([
            'audit_engagement_id' => $engagement->id,
            'conference_code' => 'ENTRY-'.$engagement->engagement_code,
            'status' => 'WAIVED',
            'waiver_reason' => 'Authorized test waiver.',
            'waiver_authority' => 'City Internal Auditor',
            'waived_at' => now(),
            'waived_by' => $management->id,
            'created_by' => $auditor->id,
        ]);
        Sanctum::actingAs($management);

        $this->putJson("/api/aems/engagements/{$engagement->id}", [
            'status' => 'CLOSED',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('audit_engagements', [
            'id' => $engagement->id,
            'status' => 'FIELDWORK',
        ]);

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/CANCEL",
            [
                'lockVersion' => 1,
                'comment' => 'Engagement authority withdrawn.',
                'authority' => 'City Internal Auditor',
                'effectOnIap' => 'Retain the approved plan history and record cancellation.',
                'workProductDisposition' => 'Retain all working papers, evidence, findings, and documents.',
            ],
        )->assertOk()
            ->assertJsonPath('data.engagement.status', 'CANCELLED')
            ->assertJsonPath('data.engagement.cancellationMetadata.authority', 'City Internal Auditor');
        $this->assertDatabaseHas('entry_conferences', ['id' => $entry->id, 'status' => 'WAIVED']);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/RESUME",
            ['lockVersion' => 2, 'comment' => 'Attempt terminal transition.'],
        )->assertUnprocessable();
    }

    public function test_technical_administrator_cannot_execute_professional_lifecycle_actions(): void
    {
        [, , , $engagement] = $this->engagement('DRAFT');
        Sanctum::actingAs(User::query()->where('username', 'admin')->firstOrFail());

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/PREPARE_AUTHORIZATION",
            ['lockVersion' => 1],
        )->assertForbidden();
    }

    /** @return array{User, User, User, AuditEngagement} */
    private function engagement(string $status): array
    {
        $management = User::query()->where('username', 'departmenthead')->firstOrFail();
        $auditor = User::query()->where('username', 'auditor')->firstOrFail();
        $auditee = User::query()->where('username', 'auditee')->firstOrFail();
        $office = Office::query()->findOrFail($auditee->office_id);
        $area = AuditArea::query()->firstOrFail();
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => 'AEMS-LIFE-'.str()->random(6),
            'title' => 'Lifecycle Control Audit',
            'source_type' => 'SPECIAL',
            'special_authority_reference' => 'AUTH-LIFE-001',
            'special_authority_date' => today()->subMonth(),
            'special_authority_approved_by' => $management->id,
            'objectives' => 'Test the controlled aggregate lifecycle.',
            'scope' => 'Lifecycle records and workflow gates.',
            'planned_start_date' => today(),
            'planned_end_date' => today()->addMonth(),
            'planned_person_days' => 20,
            'status' => $status,
            'created_by' => $auditor->id,
            'updated_by' => $management->id,
        ]);
        $engagement->offices()->attach($office->id, ['is_primary' => true]);
        $engagement->auditAreas()->attach($area->id);
        EngagementTeam::query()->create([
            'audit_engagement_id' => $engagement->id,
            'user_id' => $auditor->id,
            'assignment_role_code' => 'TEAM_LEADER',
            'assigned_by' => $management->id,
            'is_active' => true,
        ]);

        return [$management, $auditor, $auditee, $engagement];
    }

    private function installRequiredTeamAndAeo(
        AuditEngagement $engagement,
        User $management,
        User $auditor,
    ): void {
        $users = [
            'SUPERVISOR' => $management,
            'AUDITOR' => User::query()->where('username', 'cias.employee')->firstOrFail(),
            'REVIEWER' => User::query()->where('username', 'agisadmin')->firstOrFail(),
        ];
        foreach ($users as $role => $user) {
            EngagementTeam::query()->firstOrCreate(
                ['audit_engagement_id' => $engagement->id, 'user_id' => $user->id],
                [
                    'assignment_role_code' => $role,
                    'assigned_by' => $management->id,
                    'is_active' => true,
                ],
            );
        }

        // The lifecycle gate now reads ARMIS as the authoritative resource
        // provider. Keep this fixture explicit about the provider records and
        // accepted safeguards it needs; the legacy IAP ledgers are not used
        // to make an engagement authorization decision anymore.
        $team = EngagementTeam::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('is_active', true)
            ->get();
        foreach ($team as $member) {
            $member->update(['planned_person_days' => 5]);
            $profile = ArmisResourceProfile::withTrashed()
                ->where('user_id', $member->user_id)
                ->latest('id')
                ->first();
            if (! $profile) {
                $profile = ArmisResourceProfile::query()->create([
                    'resource_code' => 'ARMIS-LIFECYCLE-'.$member->user_id,
                    'user_id' => $member->user_id,
                    'office_id' => $member->user?->office_id,
                    'category' => $member->assignment_role_code === 'REVIEWER' ? 'REVIEWER' : 'AUDIT_RESOURCE',
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
                    'office_id' => $member->user?->office_id,
                    'updated_by' => $management->id,
                ]);
            }
            $capacityExists = ArmisCapacitySubmission::query()
                ->where('resource_profile_id', $profile->id)
                ->where('fiscal_year', today()->year)
                ->where('is_current_revision', true)
                ->whereIn('status', ['APPROVED', 'LOCKED'])
                ->exists();
            if (! $capacityExists) {
                ArmisCapacitySubmission::query()->create([
                    'resource_profile_id' => $profile->id,
                    'fiscal_year' => today()->year,
                    'version_number' => 1,
                    'available_person_days' => 180,
                    'status' => 'APPROVED',
                    'is_current_revision' => true,
                    'approved_by' => $management->id,
                    'approved_at' => now(),
                    'created_by' => $management->id,
                    'updated_by' => $management->id,
                ]);
            }
            foreach (AemsTeamSafeguardDeclaration::TYPES as $type) {
                AemsTeamSafeguardDeclaration::query()->create([
                    'declaration_family_uuid' => (string) str()->uuid(),
                    'audit_engagement_id' => $engagement->id,
                    'engagement_team_id' => $member->id,
                    'user_id' => $member->user_id,
                    'declaration_type' => $type,
                    'version_number' => 1,
                    'is_current_revision' => true,
                    'outcome' => 'CLEAR',
                    'statement' => 'Lifecycle test declaration is clear.',
                    'status' => 'ACCEPTED',
                    'submitted_by' => $member->user_id,
                    'submitted_at' => now(),
                    'reviewed_by' => $member->user_id === $management->id ? $auditor->id : $management->id,
                    'reviewed_at' => now(),
                    'review_notes' => 'Accepted for lifecycle gate testing.',
                    'created_by' => $management->id,
                    'updated_by' => $management->id,
                ]);
            }
        }
        $issuer = $users['REVIEWER'];
        $order = AuditEngagementOrder::query()->create([
            'audit_engagement_id' => $engagement->id,
            'order_code' => 'AEO-'.$engagement->engagement_code,
            'status' => 'ISSUED',
            'prepared_by' => $auditor->id,
            'approved_by' => $management->id,
            'approved_at' => now(),
            'issued_by' => $issuer->id,
            'issued_at' => now(),
            'is_active' => true,
        ]);
        $office = $engagement->offices()->firstOrFail();
        $acknowledger = User::query()
            ->where('office_id', $office->id)
            ->whereKeyNot($management->id)
            ->first() ?? $management;
        AemsAeoDistribution::query()->create([
            'audit_engagement_order_id' => $order->id,
            'version_number' => 1,
            'recipient_type' => 'OFFICE',
            'recipient_office_id' => $office->id,
            'recipient_name' => $office->name,
            'transmittal_method' => 'SECURE_PORTAL',
            'transmittal_reference' => 'LIFECYCLE-ACK-'.$engagement->engagement_code,
            'status' => 'ACKNOWLEDGED',
            'sent_at' => now(),
            'acknowledged_at' => now(),
            'acknowledged_by' => $acknowledger->id,
            'acknowledgement_note' => 'Lifecycle fixture acknowledgement.',
            'created_by' => $management->id,
        ]);
    }

    private function installApprovedPlanningRecords(
        AuditEngagement $engagement,
        User $management,
        User $auditor,
    ): void {
        $area = $engagement->auditAreas()->firstOrFail();
        $focus = $area->focuses()->firstOrFail();
        $plan = AuditEngagementPlan::query()->create([
            'audit_engagement_id' => $engagement->id,
            'plan_code' => 'AEP-'.$engagement->engagement_code,
            'status' => 'APPROVED',
            'prepared_by' => $auditor->id,
            'approved_by' => $management->id,
            'approved_at' => now(),
            'is_active' => true,
        ]);
        AemsPlanningPackage::query()->create([
            'audit_engagement_id' => $engagement->id,
            'package_code' => 'APP-'.$engagement->engagement_code,
            'status' => 'APPROVED',
            'current_version_number' => 1,
            'approved_version_number' => 1,
            'source_type' => $engagement->source_type,
            'prepared_by' => $auditor->id,
            'approved_by' => $management->id,
            'approved_at' => now(),
            'is_active' => true,
        ]);
        $program = AuditProgram::query()->create([
            'audit_engagement_id' => $engagement->id,
            'audit_engagement_plan_id' => $plan->id,
            'program_code' => 'AP-'.$engagement->engagement_code,
            'title' => 'Lifecycle Audit Program',
            'objective' => 'Complete controlled fieldwork.',
            'status' => 'APPROVED',
            'prepared_by' => $auditor->id,
            'approved_by' => $management->id,
            'approved_at' => now(),
            'is_current_revision' => true,
            'is_active' => true,
            'audit_area_id' => $area->id,
            'audit_period_start' => today()->subMonth(),
            'audit_period_end' => today()->addMonth(),
            'audit_criteria' => 'Applicable internal control policy.',
            'sampling_approach' => 'Judgmental sample of the approved population.',
        ]);
        $procedure = $program->procedures()->create([
            'procedure_code' => 'P-01',
            'objective' => 'Confirm the key control operates as designed.',
            'procedure_description' => 'Inspect the authoritative transaction register.',
            'status' => 'NOT_STARTED',
            'audit_area_id' => $area->id,
            'audit_focus_id' => $focus->id,
            'process_name' => 'Controlled transaction processing',
            'audit_method' => 'Inspection and inquiry',
            'audit_criteria' => 'Applicable internal control policy.',
            'planned_person_days' => 2,
            'sampling_requirement' => ['method' => 'judgmental', 'population' => 'Approved transaction register'],
            'planned_working_paper_requirement' => ['reference' => 'WP-01', 'title' => 'Control test', 'requiredEvidence' => 'Register and approval evidence'],
        ]);
        $package = AemsPlanningPackage::query()->where('audit_engagement_id', $engagement->id)->firstOrFail();
        $version = AemsPlanningPackageVersion::query()->create([
            'planning_package_id' => $package->id,
            'version_number' => 1,
            'preliminary_survey' => [
                'purpose' => 'Understand the process.',
                'background' => 'Background reviewed.',
                'informationSources' => 'Policies and interviews.',
                'observations' => 'Walkthrough completed.',
                'planningImplications' => 'Test controls and evidence.',
            ],
            'planning_attributes' => [
                'kpis' => [
                    'decision' => 'DEFINED',
                    'items' => [[
                        'name' => 'Procedure completion',
                        'target' => '100%',
                        'measurementMethod' => 'Approved procedure status review',
                    ]],
                ],
            ],
            'iap_lineage_snapshot' => ['sourceType' => $engagement->source_type],
            'checksum_sha256' => hash('sha256', 'lifecycle-planning-package'),
            'created_by' => $auditor->id,
            'created_at' => now(),
        ]);
        $objective = AemsPlanningObjective::query()->create([
            'planning_package_version_id' => $version->id,
            'objective_code' => 'OBJ-1',
            'objective_statement' => 'Assess control design and operation.',
            'source_type' => 'AEMS',
            'sequence' => 0,
        ]);
        $flow = AemsProcessFlowDocument::query()->create([
            'planning_package_version_id' => $version->id,
            'flow_code' => 'FLOW-1',
            'title' => 'Controlled transaction flow',
            'description' => 'Documented walkthrough.',
            'audit_area_id' => $area->id,
            'audit_focus_id' => $focus->id,
            'scope_statement' => 'Current transaction process.',
            'steps' => ['Receive', 'Review', 'Approve'],
            'inputs' => ['Transaction request'],
            'outputs' => ['Approved transaction'],
            'records_systems' => ['Transaction register'],
            'controls' => ['Approval control'],
            'decision_points' => ['Approval required'],
            'risk_points' => ['Unauthorized transaction'],
            'sequence' => 0,
        ]);
        $procedure->update(['process_flow_id' => $flow->id]);
        $matrix = AemsRiskMatrix::query()->create([
            'planning_package_version_id' => $version->id,
            'matrix_code' => 'RM-1',
            'title' => 'Lifecycle risk matrix',
            'audit_area_id' => $area->id,
            'audit_focus_id' => $focus->id,
            'status' => 'APPROVED',
        ]);
        $risk = AemsRiskMatrixItem::query()->create([
            'risk_matrix_id' => $matrix->id,
            'risk_code' => 'R-1',
            'risk_statement' => 'A control may fail.',
            'risk_response' => 'Test the control and corroborate evidence.',
            'audit_area_id' => $area->id,
            'audit_focus_id' => $focus->id,
            'process_flow_id' => $flow->id,
            'process_name' => 'Controlled transaction processing',
            'risk_area' => 'Transaction authorization',
            'planned_audit_approach' => 'Inspect a judgmental sample.',
            'criteria' => 'Applicable internal control policy.',
            'status' => 'OPEN',
        ]);
        DB::table('aems_risk_objective_links')->insert([
            'risk_matrix_item_id' => $risk->id,
            'planning_objective_id' => $objective->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('aems_risk_procedure_links')->insert([
            'risk_matrix_item_id' => $risk->id,
            'audit_program_procedure_id' => $procedure->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        AemsRiskWorkingPaperLink::query()->create([
            'risk_matrix_item_id' => $risk->id,
            'working_paper_reference' => 'WP-01',
            'relationship_basis' => 'Required for procedure conclusion.',
        ]);
        AemsPlanningKpi::query()->create([
            'planning_package_version_id' => $version->id,
            'kpi_code' => 'KPI-1',
            'name' => 'Procedure completion',
            'target' => '100%',
            'measurement_method' => 'Approved procedure status review',
            'status' => 'DEFINED',
            'sequence' => 0,
        ]);
        AemsPlannedWorkingPaperRequirement::query()->create([
            'planning_package_version_id' => $version->id,
            'audit_program_procedure_id' => $procedure->id,
            'risk_matrix_item_id' => $risk->id,
            'working_paper_reference' => 'WP-01',
            'title' => 'Control test',
            'objective' => 'Document procedure results.',
            'required_evidence' => 'Register and approval evidence',
            'is_required' => true,
            'sequence' => 0,
        ]);
        $package->update(['current_version_number' => 1, 'approved_version_number' => 1]);
    }

    /** @return array<string, mixed> */
    private function conferenceRecord(User $auditor, User $auditee): array
    {
        return [
            'agenda' => 'Present authority, objectives, scope, methodology, timing, and information requests.',
            'briefingPaper' => [
                'auditSelectionBackground' => 'Approved special engagement.',
                'auditAuthority' => 'City Internal Auditor authority.',
                'preliminaryObjectives' => 'Assess lifecycle controls.',
                'scopeAndExclusions' => 'Current process only.',
                'methodology' => 'Inspection and inquiry.',
                'auditCriteria' => 'Applicable control policy.',
                'plannedTiming' => 'One month.',
                'teamMembersAndRoles' => 'Assigned team.',
                'previousAuditMatters' => 'None.',
                'engagementMilestones' => 'Fieldwork and reporting.',
                'expectedDeliverables' => 'Final Audit Report.',
                'initialInformationRequirements' => 'Policies and registers.',
            ],
            'auditeeViews' => 'The office confirmed the process context.',
            'auditeeExpectations' => 'Timely communication of matters.',
            'conferenceNotes' => 'Authority, scope, timing, and document access were discussed.',
            'materialMattersDisposition' => 'The material access matter was resolved.',
            'participants' => [
                [
                    'userId' => $auditor->id,
                    'participantType' => 'AUDIT_TEAM',
                    'participantRole' => 'Team Leader',
                ],
                [
                    'userId' => $auditee->id,
                    'officeId' => $auditee->office_id,
                    'participantType' => 'AUDITEE',
                    'participantRole' => 'Auditee Representative',
                ],
            ],
            'matters' => [[
                'matterType' => 'DOCUMENT_ACCESS',
                'description' => 'Confirm access to the transaction register.',
                'isMaterial' => true,
                'dispositionStatus' => 'RESOLVED',
                'disposition' => 'Access will be provided through the designated custodian.',
                'responsibleOfficeId' => $auditee->office_id,
                'dueDate' => today()->addDays(3)->toDateString(),
            ]],
            'agreements' => [[
                'agreement' => 'The office will provide the requested register.',
                'responsibleOfficeId' => $auditee->office_id,
                'dueDate' => today()->addDays(3)->toDateString(),
                'status' => 'OPEN',
            ]],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function conferenceTransition(
        AuditEngagement $engagement,
        array $conference,
        string $action,
        array $payload = [],
    ) {
        return $this->postJson(
            "/api/aems/engagements/{$engagement->id}/entry-conference/{$conference['id']}/transitions/{$action}",
            [...$payload, 'lockVersion' => $conference['lockVersion']],
        );
    }
}
