<?php

namespace Tests\Feature;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Models\EngagementTeam;
use App\Models\ExitConference;
use App\Models\Office;
use App\Models\ReportRecipient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class AemsAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_standard_roles_receive_deliberately_separated_aems_permissions(): void
    {
        $platform = $this->user('admin');
        $agisAdministrator = $this->user('agisadmin');
        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $auditee = $this->user('auditee');
        $mayor = $this->user('mayor');

        $this->assertTrue($management->hasPermission('aems.aeo.approve'));
        $this->assertTrue($management->hasPermission('aems.report.issue'));
        $this->assertTrue($management->hasPermission('aems.engagement.close'));

        $this->assertTrue($auditor->hasPermission('aems.working-paper.create'));
        $this->assertTrue($auditor->hasPermission('aems.evidence.upload'));
        $this->assertFalse($auditor->hasPermission('aems.aeo.approve'));
        $this->assertFalse($auditor->hasPermission('aems.report.issue'));

        $this->assertTrue($auditee->hasPermission('aems.management-response.submit'));
        $this->assertTrue($auditee->hasPermission('aems.conference.acknowledge'));
        $this->assertFalse($auditee->hasPermission('aems.engagement.view'));
        $this->assertFalse($auditee->hasPermission('aems.finding.create'));

        $this->assertTrue($mayor->hasPermission('aems.report.view_issued'));
        $this->assertFalse($mayor->hasPermission('aems.report.view'));

        foreach ([$platform, $agisAdministrator] as $administrator) {
            $this->assertTrue($administrator->hasPermission('aems.engagement.view'));
            $this->assertTrue($administrator->hasPermission('aems.report.view_issued'));
            $this->assertFalse($administrator->hasPermission('aems.aeo.approve'));
            $this->assertFalse($administrator->hasPermission('aems.report.issue'));
            $this->assertFalse($administrator->hasPermission('aem.close'));
        }
    }

    public function test_engagement_queries_and_policies_enforce_assignment_scope(): void
    {
        $platform = $this->user('admin');
        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $otherAuditor = User::query()
            ->where('id', '<>', $auditor->id)
            ->whereHas('roles', fn ($query) => $query->where('code', 'agis_user'))
            ->firstOrFail();

        $first = $this->engagement($auditor, $management, $auditor->office);
        $second = $this->engagement($otherAuditor, $management, $otherAuditor->office);
        $this->assign($first, $auditor, $management, 'TEAM_LEADER');
        $this->assign($second, $otherAuditor, $management, 'AUDITOR');

        $this->assertEquals(
            [$first->id],
            AuditEngagement::query()->visibleTo($auditor)->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            AuditEngagement::query()->visibleTo($management)->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            AuditEngagement::query()->visibleTo($platform)->pluck('id')->all(),
        );

        $this->assertTrue(Gate::forUser($auditor)->allows('view', $first));
        $this->assertFalse(Gate::forUser($auditor)->allows('view', $second));
        $this->assertTrue(Gate::forUser($auditor)->allows('update', $first));
        $this->assertFalse(Gate::forUser($platform)->allows('authorize', $first));
        $this->assertTrue(Gate::forUser($management)->allows('authorize', $first));

        $managementOriginated = $this->engagement(
            $management,
            $management,
            $management->office,
        );
        $this->assertFalse(
            Gate::forUser($management)->allows('authorize', $managementOriginated),
            'The originator must not authorize their own engagement.',
        );
    }

    public function test_auditee_only_sees_communicated_findings_for_their_office(): void
    {
        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $auditee = $this->user('auditee');
        $otherOffice = Office::query()->whereKeyNot($auditee->office_id)->firstOrFail();
        $engagement = $this->engagement($auditor, $management, $auditee->office);
        $this->assign($engagement, $auditor, $management, 'AUDITOR');

        $draft = $this->finding($engagement, $auditor, $auditee->office, 'DRAFT');
        $communicated = $this->finding(
            $engagement,
            $auditor,
            $auditee->office,
            'COMMUNICATED',
        );
        $otherOfficeFinding = $this->finding(
            $engagement,
            $auditor,
            $otherOffice,
            'COMMUNICATED',
        );

        $this->assertEquals(
            [$communicated->id],
            AuditFinding::query()->visibleTo($auditee)->pluck('id')->all(),
        );
        $this->assertFalse(Gate::forUser($auditee)->allows('view', $draft));
        $this->assertTrue(Gate::forUser($auditee)->allows('view', $communicated));
        $this->assertFalse(Gate::forUser($auditee)->allows('view', $otherOfficeFinding));
        $this->assertTrue(
            Gate::forUser($auditee)->allows('submitManagementResponse', $communicated),
        );
        $this->assertFalse(
            Gate::forUser($auditee)->allows('submitManagementResponse', $otherOfficeFinding),
        );
    }

    public function test_issued_reports_require_recipient_authorization_for_read_only_users(): void
    {
        $platform = $this->user('admin');
        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $auditee = $this->user('auditee');
        $mayor = $this->user('mayor');

        $issuedForMayor = $this->report(
            $this->engagement($auditor, $management, $auditee->office),
            $auditor,
            'ISSUED',
            $mayor,
        );
        $issuedForOffice = $this->report(
            $this->engagement($auditor, $management, $auditee->office),
            $auditor,
            'ISSUED',
            null,
            $auditee->office,
        );
        $draftForMayor = $this->report(
            $this->engagement($auditor, $management, $auditee->office),
            $auditor,
            'DRAFT',
            $mayor,
        );

        $this->assertEquals(
            [$issuedForMayor->id],
            AuditReport::query()->visibleTo($mayor)->pluck('id')->all(),
        );
        $this->assertEquals(
            [$issuedForOffice->id],
            AuditReport::query()->visibleTo($auditee)->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$issuedForMayor->id, $issuedForOffice->id],
            AuditReport::query()->visibleTo($platform)->pluck('id')->all(),
            'Administrators may monitor issued reports but not draft report content.',
        );
        $this->assertFalse(Gate::forUser($mayor)->allows('view', $draftForMayor));
        $this->assertTrue(Gate::forUser($mayor)->allows('view', $issuedForMayor));
    }

    public function test_auditee_conference_access_is_limited_to_engagement_offices(): void
    {
        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $auditee = $this->user('auditee');
        $otherOffice = Office::query()->whereKeyNot($auditee->office_id)->firstOrFail();
        $covered = $this->engagement($auditor, $management, $auditee->office);
        $notCovered = $this->engagement($auditor, $management, $otherOffice);

        $coveredConference = $this->conference($covered, $management);
        $otherConference = $this->conference($notCovered, $management);

        $this->assertTrue(Gate::forUser($auditee)->allows('view', $coveredConference));
        $this->assertTrue(Gate::forUser($auditee)->allows('acknowledge', $coveredConference));
        $this->assertFalse(Gate::forUser($auditee)->allows('view', $otherConference));
    }

    public function test_auditee_scope_behavior_is_driven_by_permission_not_role_code(): void
    {
        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $auditee = $this->user('auditee');
        $covered = $this->engagement($auditor, $management, $auditee->office);
        $conference = $this->conference($covered, $management);

        $sourceRole = Role::query()->where('code', 'auditee_representative')->firstOrFail();
        $customRole = Role::query()->create([
            'code' => 'custom_auditee_scope',
            'name' => 'Custom Auditee Scope Role',
            'description' => 'Regression role with duplicated auditee permissions.',
            'is_system' => false,
            'is_active' => true,
            'office_access_scope' => 'OWN_OFFICE',
            'engagement_access_scope' => 'ASSIGNED',
        ]);
        $customRole->permissions()->sync($sourceRole->permissions()->pluck('permissions.id'));
        $auditee->forceFill(['role_id' => $customRole->id])->save();
        $auditee->roles()->sync([
            $customRole->id => [
                'is_primary' => true,
                'assigned_by' => $management->id,
                'assigned_at' => now(),
            ],
        ]);
        $auditee->refresh()->load(['role.permissions', 'roles.permissions', 'office']);

        $this->assertFalse($auditee->hasRole('auditee_representative'));
        $this->assertTrue($auditee->hasPermission('access.auditee_scope'));
        $this->assertTrue(Gate::forUser($auditee)->allows('view', $conference));
        $this->assertTrue(Gate::forUser($auditee)->allows('acknowledge', $conference));
    }

    private function user(string $username): User
    {
        return User::query()
            ->with(['role.permissions', 'roles.permissions', 'office'])
            ->where('username', $username)
            ->firstOrFail();
    }

    private function engagement(
        User $creator,
        User $authority,
        Office $office,
    ): AuditEngagement {
        $number = ++$this->sequence;
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => "AEMS-TEST-{$number}",
            'title' => "Controlled engagement {$number}",
            'source_type' => 'SPECIAL',
            'special_authority_reference' => "AUTH-{$number}",
            'special_authority_date' => now()->toDateString(),
            'special_authority_approved_by' => $authority->id,
            'objectives' => 'Test the scoped AEMS authorization foundation.',
            'scope' => 'Access-control test scope.',
            'status' => 'AUTHORIZATION_PREPARATION',
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
        $engagement->offices()->attach($office->id, ['is_primary' => true]);

        return $engagement;
    }

    private function assign(
        AuditEngagement $engagement,
        User $user,
        User $assigner,
        string $role,
    ): EngagementTeam {
        return EngagementTeam::query()->create([
            'audit_engagement_id' => $engagement->id,
            'user_id' => $user->id,
            'assignment_role_code' => $role,
            'assigned_by' => $assigner->id,
            'is_active' => true,
        ]);
    }

    private function finding(
        AuditEngagement $engagement,
        User $author,
        Office $office,
        string $status,
    ): AuditFinding {
        $number = ++$this->sequence;

        return AuditFinding::query()->create([
            'finding_family_uuid' => (string) Str::uuid(),
            'revision_number' => 1,
            'is_current_revision' => true,
            'audit_engagement_id' => $engagement->id,
            'finding_code' => "F-{$number}",
            'title' => "Finding {$number}",
            'criteria' => 'Approved control criteria.',
            'condition' => 'Observed test condition.',
            'cause' => 'Test root cause.',
            'effect' => 'Test effect.',
            'responsible_office_id' => $office->id,
            'status' => $status,
            'authored_by' => $author->id,
        ]);
    }

    private function report(
        AuditEngagement $engagement,
        User $preparer,
        string $status,
        ?User $recipientUser = null,
        ?Office $recipientOffice = null,
    ): AuditReport {
        $number = ++$this->sequence;
        $report = AuditReport::query()->create([
            'audit_engagement_id' => $engagement->id,
            'report_code' => "REPORT-{$number}",
            'title' => "Audit report {$number}",
            'report_stage' => $status === 'ISSUED' ? 'FINAL_REPORT' : 'DRAFT_REPORT',
            'status' => $status,
            'current_version_number' => 1,
            'prepared_by' => $preparer->id,
            'issued_at' => $status === 'ISSUED' ? now() : null,
        ]);
        $version = AuditReportVersion::query()->create([
            'audit_report_id' => $report->id,
            'version_number' => 1,
            'report_stage' => $report->report_stage,
            'content_snapshot' => ['title' => $report->title],
            'created_by' => $preparer->id,
        ]);
        ReportRecipient::query()->create([
            'audit_report_version_id' => $version->id,
            'user_id' => $recipientUser?->id,
            'office_id' => $recipientOffice?->id,
            'recipient_type' => $recipientUser ? 'USER' : 'OFFICE',
            'delivery_status' => $status === 'ISSUED' ? 'SENT' : 'PENDING',
        ]);

        return $report;
    }

    private function conference(
        AuditEngagement $engagement,
        User $creator,
    ): ExitConference {
        $number = ++$this->sequence;

        return ExitConference::query()->create([
            'audit_engagement_id' => $engagement->id,
            'conference_code' => "EXIT-{$number}",
            'scheduled_start_at' => now()->addDays($number),
            'agenda' => 'Discuss validated findings and agreed next actions.',
            'status' => 'SCHEDULED',
            'created_by' => $creator->id,
        ]);
    }
}
