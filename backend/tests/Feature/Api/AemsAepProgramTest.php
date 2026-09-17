<?php

namespace Tests\Feature\Api;

use App\Models\AuditEngagement;
use App\Models\AemsAeoDistribution;
use App\Models\AuditEngagementPlanVersion;
use App\Models\AemsTeamSafeguardDeclaration;
use App\Models\AemsFieldworkRecord;
use App\Models\AemsFieldworkRecordVersion;
use App\Models\AuditArea;
use App\Models\AuditFocus;
use App\Models\AuditProgram;
use App\Models\AuditProgramProcedure;
use App\Models\AemsPlanningPackage;
use App\Models\AemsPlanningPackageVersion;
use App\Models\AemsProcessFlowDocument;
use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisResourceProfile;
use App\Models\IapPlanEngagement;
use App\Models\MasterListItem;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AemsAepProgramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_aep_requires_an_issued_aeo_and_preserves_immutable_risk_linked_versions(): void
    {
        [$management, $engagement, $team] = $this->preparedEngagement();
        $preparer = $team['TEAM_LEADER'];
        Sanctum::actingAs($preparer);

        $payload = $this->aepPayload($engagement);
        $this->postJson("/api/aems/engagements/{$engagement->id}/aep", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('engagement');

        $this->issueAeo($management, $engagement, $team, $preparer);
        Sanctum::actingAs($preparer);
        $this->postJson("/api/aems/engagements/{$engagement->id}/aep", $payload)
            ->assertCreated();
        $workspace = $this->getJson("/api/aems/engagements/{$engagement->id}/aep")
            ->assertOk()
            ->assertJsonPath('data.plan.status', 'DRAFT')
            ->json('data');
        $plan = $workspace['plan'];
        $this->assertSame(
            data_get($engagement->source_snapshot, 'riskAssessment.id'),
            data_get($plan, 'latestVersion.linkedRiskSnapshot.riskAssessment.id'),
        );

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aep/{$plan['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $plan['lockVersion']],
        )->assertOk();

        Sanctum::actingAs($management);
        $plan = $this->aepWorkspace($engagement)['plan'];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aep/{$plan['id']}/transition",
            [
                'action' => 'APPROVE',
                'lockVersion' => $plan['lockVersion'],
                'comment' => 'Attempted approval without independent review.',
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('action');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aep/{$plan['id']}/transition",
            [
                'action' => 'REVIEW',
                'lockVersion' => $plan['lockVersion'],
                'comment' => 'Objectives, scope, methodology, risks, and resources reviewed.',
            ],
        )->assertOk();
        $plan = $this->aepWorkspace($engagement)['plan'];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aep/{$plan['id']}/transition",
            [
                'action' => 'APPROVE',
                'lockVersion' => $plan['lockVersion'],
                'comment' => 'Approved as the engagement planning baseline.',
            ],
        )->assertOk()->assertJsonPath('data.plan.status', 'APPROVED');

        $plan = $this->aepWorkspace($engagement)['plan'];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aep/{$plan['id']}/revise",
            [
                'lockVersion' => $plan['lockVersion'],
                'reason' => 'Management coordination and sampling requirements changed.',
            ],
        )->assertOk()->assertJsonPath('data.plan.status', 'DRAFT');
        $this->assertDatabaseCount('audit_engagement_plan_versions', 2);

        $version = AuditEngagementPlanVersion::query()->oldest('version_number')->firstOrFail();
        $this->expectException(LogicException::class);
        $version->update(['scope' => 'An approved historical version cannot be overwritten.']);
    }

    public function test_audit_program_approval_creates_a_baseline_and_formal_revision_preserves_it(): void
    {
        [$management, $engagement, $team] = $this->preparedEngagement();
        $preparer = $team['TEAM_LEADER'];
        $this->issueAeo($management, $engagement, $team, $preparer);
        $this->approveAep($management, $engagement, $preparer);

        Sanctum::actingAs($preparer);
        $this->postJson("/api/aems/engagements/{$engagement->id}/programs", [
            'title' => 'Revenue Collection Audit Program',
            'objective' => 'Determine whether collection, reconciliation, and deposit controls operate effectively.',
            'auditAreaId' => $engagement->auditAreas()->value('audit_areas.id'),
            'auditTypeId' => $engagement->audit_type_id,
        ])->assertCreated();
        $program = $this->currentProgram($engagement);

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/procedures",
            [
                'programLockVersion' => $program['lockVersion'],
                'procedureCode' => 'RC-01',
                'sequenceNumber' => 1,
                'objective' => 'Test daily collection reconciliation.',
                'procedureDescription' => 'Select collection days and reconcile official receipts, reports, and deposits.',
                'expectedEvidence' => 'Official receipts, daily collection reports, and validated deposit slips.',
                'assignedTo' => $team['AUDITOR']->id,
                'targetDate' => '2026-08-14',
            ],
        )->assertCreated();
        $this->seedFinalizedFieldworkRecord(
            $engagement,
            AuditProgramProcedure::query()->where('audit_program_id', $program['id'])->firstOrFail(),
            $team['AUDITOR'],
        );
        $program = $this->currentProgram($engagement);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $program['lockVersion']],
        )->assertOk();

        Sanctum::actingAs($management);
        $program = $this->currentProgram($engagement);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/transition",
            [
                'action' => 'REVIEW',
                'lockVersion' => $program['lockVersion'],
                'comment' => 'Procedures and expected evidence independently reviewed.',
            ],
        )->assertOk();
        $program = $this->currentProgram($engagement);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/transition",
            [
                'action' => 'APPROVE',
                'lockVersion' => $program['lockVersion'],
                'comment' => 'Approved as the fieldwork baseline.',
            ],
        )->assertOk()->assertJsonPath('data.program.status', 'APPROVED');

        $program = $this->currentProgram($engagement);
        $this->putJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}",
            [
                'title' => 'Attempted baseline overwrite',
                'objective' => $program['objective'],
                'lockVersion' => $program['lockVersion'],
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/transition",
            ['action' => 'START', 'lockVersion' => $program['lockVersion']],
        )->assertOk()->assertJsonPath('data.program.status', 'ACTIVE');

        Sanctum::actingAs($team['AUDITOR']);
        $program = $this->currentProgram($engagement);
        $procedure = $program['procedures'][0];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/procedures/{$procedure['id']}/progress",
            [
                'programLockVersion' => $program['lockVersion'],
                'lockVersion' => $procedure['lockVersion'],
                'status' => 'COMPLETED',
                'workingPaperReference' => 'WP-RC-01',
                'comment' => 'Reconciliation test completed and cross-referenced.',
            ],
        )->assertOk()->assertJsonPath('data.procedure.status', 'COMPLETED');

        Sanctum::actingAs($team['REVIEWER']);
        $program = $this->currentProgram($engagement);
        $procedure = $program['procedures'][0];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/procedures/{$procedure['id']}/review",
            [
                'programLockVersion' => $program['lockVersion'],
                'lockVersion' => $procedure['lockVersion'],
                'reviewerResult' => 'SATISFACTORY',
                'reviewerComments' => 'Evidence and working-paper reference are sufficient.',
            ],
        )->assertOk()->assertJsonPath('data.procedure.reviewer_result', 'SATISFACTORY');

        Sanctum::actingAs($management);
        $program = $this->currentProgram($engagement);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/revise",
            [
                'lockVersion' => $program['lockVersion'],
                'reason' => 'Additional high-risk transactions require an expanded test procedure.',
            ],
        )->assertOk()
            ->assertJsonPath('data.program.status', 'DRAFT')
            ->assertJsonPath('data.program.revision_number', 1);

        $this->assertDatabaseHas('audit_programs', [
            'id' => $program['id'],
            'status' => 'SUPERSEDED',
            'is_current_revision' => false,
        ]);
        $this->assertDatabaseCount('audit_programs', 2);
        $this->assertDatabaseCount('audit_program_procedures', 2);
        $revision = AuditProgram::query()->where('is_current_revision', true)->firstOrFail();
        $this->assertSame(
            'Additional high-risk transactions require an expanded test procedure.',
            $revision->revision_reason,
        );
    }

    public function test_audit_program_scope_must_match_the_engagement_configuration(): void
    {
        [$management, $engagement, $team] = $this->preparedEngagement();
        $preparer = $team['TEAM_LEADER'];
        $this->issueAeo($management, $engagement, $team, $preparer);
        $this->approveAep($management, $engagement, $preparer);

        $selectedAreaIds = $engagement->auditAreas()->pluck('audit_areas.id');
        $otherArea = AuditArea::query()->whereNotIn('id', $selectedAreaIds)->firstOrFail();
        Sanctum::actingAs($preparer);
        $this->postJson("/api/aems/engagements/{$engagement->id}/programs", [
            'title' => 'Out-of-scope program',
            'objective' => 'This program must be rejected because its area is outside the engagement.',
            'auditAreaId' => $otherArea->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('auditAreaId');

        $otherType = MasterListItem::query()
            ->where('id', '<>', $engagement->audit_type_id)
            ->whereHas('masterList', fn ($query) => $query->where('code', 'IAP_ENGAGEMENT_TYPE'))
            ->firstOrFail();
        $this->postJson("/api/aems/engagements/{$engagement->id}/programs", [
            'title' => 'Wrong-type program',
            'objective' => 'This program must be rejected because its type differs from the engagement.',
            'auditAreaId' => $selectedAreaIds->first(),
            'auditTypeId' => $otherType->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('auditTypeId');
    }

    public function test_program_workspace_exposes_current_planning_flows_and_procedures_must_use_them(): void
    {
        [$management, $engagement, $team] = $this->preparedEngagement();
        $preparer = $team['TEAM_LEADER'];
        $this->issueAeo($management, $engagement, $team, $preparer);
        $this->approveAep($management, $engagement, $preparer);

        $package = AemsPlanningPackage::query()->create([
            'audit_engagement_id' => $engagement->id,
            'package_code' => 'APP-'.$engagement->engagement_code,
            'status' => 'DRAFT',
            'current_version_number' => 1,
            'source_type' => $engagement->source_type,
            'prepared_by' => $preparer->id,
            'lock_version' => 1,
            'is_active' => true,
        ]);
        $version = AemsPlanningPackageVersion::query()->create([
            'planning_package_id' => $package->id,
            'version_number' => 1,
            'preliminary_survey' => [],
            'planning_attributes' => [],
            'iap_lineage_snapshot' => [],
            'created_by' => $preparer->id,
            'created_at' => now(),
        ]);
        $area = $engagement->auditAreas()->firstOrFail();
        $focus = $engagement->auditFocuses()->where('audit_area_id', $area->id)->first();
        $flow = AemsProcessFlowDocument::query()->create([
            'planning_package_version_id' => $version->id,
            'flow_code' => 'FLOW-CURRENT',
            'title' => 'Current controlled flow',
            'sequence' => 0,
            'audit_area_id' => $area->id,
            'audit_focus_id' => $focus?->id,
        ]);

        Sanctum::actingAs($preparer);
        $this->getJson("/api/aems/engagements/{$engagement->id}/programs")
            ->assertOk()
            ->assertJsonPath('data.planningProcessFlows.0.id', $flow->id)
            ->assertJsonPath('data.planningProcessFlows.0.code', 'FLOW-CURRENT');

        $this->postJson("/api/aems/engagements/{$engagement->id}/programs", [
            'title' => 'Controlled flow program',
            'objective' => 'Test the controlled process-flow relationship.',
            'auditAreaId' => $area->id,
            'auditTypeId' => $engagement->audit_type_id,
        ])->assertCreated();
        $program = $this->currentProgram($engagement);

        $this->postJson("/api/aems/engagements/{$engagement->id}/programs/{$program['id']}/procedures", [
            'programLockVersion' => $program['lockVersion'],
            'procedureCode' => 'FLOW-TEST-01',
            'sequenceNumber' => 1,
            'objective' => 'Test the current controlled flow.',
            'procedureDescription' => 'Inspect records against the controlled flow.',
            'expectedEvidence' => 'Process records and approved flow documentation.',
            'assignedTo' => $team['AUDITOR']->id,
            'targetDate' => '2026-08-14',
            'auditAreaId' => $area->id,
            'auditFocusId' => $focus?->id,
            'processFlowId' => $flow->id,
        ])->assertCreated();

        $this->assertDatabaseHas('audit_program_procedures', [
            'procedure_code' => 'FLOW-TEST-01',
            'process_flow_id' => $flow->id,
        ]);
    }

    /** @return array{User, AuditEngagement, array<string, User>} */
    private function preparedEngagement(): array
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
        $this->postJson(
            "/api/aems/engagements/{$id}/transitions/PREPARE_AUTHORIZATION",
            ['lockVersion' => 1],
        )->assertOk();
        $engagement = AuditEngagement::query()->findOrFail($id);
        $users = $this->auditors(4);
        $team = [];
        foreach (['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'] as $index => $role) {
            $this->postJson("/api/aems/engagements/{$engagement->id}/team", [
                'userId' => $users[$index]->id,
                'assignmentRoleCode' => $role,
                'plannedPersonDays' => 5,
                'assignedFrom' => '2026-08-03',
                'assignedUntil' => '2026-08-21',
            ])->assertCreated();
            $team[$role] = $users[$index];
        }

        // AEO approval now consumes authoritative ARMIS capacity and accepted
        // safeguard declarations. Seed those prerequisites explicitly for
        // this planning-focused fixture.
        foreach ($engagement->teamMembers()->where('is_active', true)->get() as $member) {
            $profile = ArmisResourceProfile::query()
                ->where('user_id', $member->user_id)
                ->where('status', 'ACTIVE')
                ->firstOrFail();
            ArmisCapacitySubmission::query()->firstOrCreate(
                ['resource_profile_id' => $profile->id, 'fiscal_year' => 2026, 'is_current_revision' => true],
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
            foreach (AemsTeamSafeguardDeclaration::TYPES as $type) {
                AemsTeamSafeguardDeclaration::query()->create([
                    'declaration_family_uuid' => (string) Str::uuid(),
                    'audit_engagement_id' => $engagement->id,
                    'engagement_team_id' => $member->id,
                    'user_id' => $member->user_id,
                    'declaration_type' => $type,
                    'version_number' => 1,
                    'is_current_revision' => true,
                    'outcome' => 'CLEAR',
                    'statement' => 'Fixture declaration accepted after independent review.',
                    'status' => 'ACCEPTED',
                    'submitted_by' => $member->user_id,
                    'submitted_at' => now(),
                    'reviewed_by' => $management->id,
                    'reviewed_at' => now(),
                    'review_notes' => 'Fixture prerequisite for AEO workflow.',
                    'created_by' => $member->user_id,
                    'updated_by' => $management->id,
                    'lock_version' => 1,
                ]);
            }
        }

        return [$management, $engagement->fresh(), $team];
    }

    /** @param array<string, User> $team */
    private function issueAeo(
        User $management,
        AuditEngagement $engagement,
        array $team,
        User $preparer,
    ): void {
        Sanctum::actingAs($preparer);
        $this->postJson("/api/aems/engagements/{$engagement->id}/aeo", [
            'authority' => 'Authority is granted under the approved annual plan and the CIAS mandate.',
            'objectives' => 'Assess whether the audited controls operate effectively.',
            'scope' => 'Approved records, transactions, personnel, and systems.',
            'plannedStartDate' => '2026-08-03',
            'plannedEndDate' => '2026-08-21',
        ])->assertCreated();
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $order['lockVersion']],
        )->assertOk();

        Sanctum::actingAs($team['REVIEWER']);
        foreach (['REVIEW'] as $action) {
            $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")->json('data.order');
            $this->postJson(
                "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
                [
                    'action' => $action,
                    'lockVersion' => $order['lockVersion'],
                    'comment' => $action === 'REVIEW' ? 'Independent authorization review completed.' : null,
                ],
            )->assertOk();
        }
        Sanctum::actingAs($management);
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'APPROVE', 'lockVersion' => $order['lockVersion'], 'comment' => 'Independent authorization approved.'],
        )->assertOk();
        $issuer = $this->newManagement('AEP-ISSUER-'.uniqid());
        Sanctum::actingAs($issuer);
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")->json('data.order');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aeo/{$order['id']}/transition",
            ['action' => 'ISSUE', 'lockVersion' => $order['lockVersion'], 'comment' => 'Issued by the separate issuing authority.'],
        )->assertOk();

        // The aggregate planning workspace is available only after the
        // issued AEO has been acknowledged by the auditee office.
        $order = $this->getJson("/api/aems/engagements/{$engagement->id}/aeo")
            ->assertOk()->json('data.order');
        $office = $engagement->offices()->firstOrFail();
        AemsAeoDistribution::query()->create([
            'audit_engagement_order_id' => $order['id'],
            'version_number' => $order['currentVersionNumber'],
            'recipient_type' => 'OFFICE',
            'recipient_office_id' => $office->id,
            'recipient_name' => $office->name,
            'transmittal_method' => 'SECURE_PORTAL',
            'transmittal_reference' => 'AEP-ACK-'.$engagement->engagement_code,
            'status' => 'ACKNOWLEDGED',
            'sent_at' => now(),
            'acknowledged_at' => now(),
            'acknowledged_by' => $management->id,
            'acknowledgement_note' => 'Fixture auditee-office acknowledgement.',
            'created_by' => $management->id,
        ]);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/ISSUE_AUTHORIZATION",
            ['lockVersion' => 2],
        )->assertOk();
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/transitions/START_PLANNING",
            ['lockVersion' => 3],
        )->assertOk();
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

    private function approveAep(
        User $management,
        AuditEngagement $engagement,
        User $preparer,
    ): void {
        Sanctum::actingAs($preparer);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aep",
            $this->aepPayload($engagement),
        )->assertCreated();
        $plan = $this->aepWorkspace($engagement)['plan'];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/aep/{$plan['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $plan['lockVersion']],
        )->assertOk();

        Sanctum::actingAs($management);
        foreach (['REVIEW', 'APPROVE'] as $action) {
            $plan = $this->aepWorkspace($engagement)['plan'];
            $this->postJson(
                "/api/aems/engagements/{$engagement->id}/aep/{$plan['id']}/transition",
                [
                    'action' => $action,
                    'lockVersion' => $plan['lockVersion'],
                    'comment' => $action === 'REVIEW' ? 'Independent AEP review completed.' : null,
                ],
            )->assertOk();
        }
    }

    /** @return array<string, mixed> */
    private function aepPayload(AuditEngagement $engagement): array
    {
        return [
            'objectives' => $engagement->objectives ?: 'Evaluate control design and operating effectiveness.',
            'scope' => $engagement->scope ?: 'Approved engagement scope.',
            'exclusions' => $engagement->exclusions,
            'methodology' => 'Inquiry, walkthrough, inspection, analytical review, and substantive testing.',
            'auditCriteria' => 'Applicable laws, policies, approved procedures, and internal-control standards.',
            'materiality' => 'Prioritize transactions and exceptions with significant financial or service exposure.',
            'samplingApproach' => 'Risk-based judgmental sampling supplemented by random selections.',
            'plannedStartDate' => '2026-08-03',
            'plannedEndDate' => '2026-08-21',
            'expectedReportDate' => '2026-09-04',
            'plannedPersonDays' => 20,
            'resourceRequirements' => [
                'staffing' => 'Supervisor, Team Leader, Auditor, and independent Reviewer.',
                'skills' => 'Revenue operations and control testing.',
                'tools' => 'AGIS working papers and spreadsheet analysis.',
                'logistics' => 'Read-only source-record access and meeting room.',
            ],
            'managementCoordination' => [
                'contactPerson' => 'Office Head',
                'contactDetails' => 'Coordinate through the designated auditee representative.',
                'kickoffDetails' => 'Entrance conference before fieldwork.',
                'recordsDeadline' => '2026-08-05',
                'notes' => 'Escalate unavailable records to the Team Leader.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function aepWorkspace(AuditEngagement $engagement): array
    {
        return $this->getJson("/api/aems/engagements/{$engagement->id}/aep")
            ->assertOk()->json('data');
    }

    /** @return array<string, mixed> */
    private function currentProgram(AuditEngagement $engagement): array
    {
        $programs = $this->getJson("/api/aems/engagements/{$engagement->id}/programs")
            ->assertOk()->json('data.programs');

        return collect($programs)->firstWhere('isCurrentRevision', true);
    }

    /** @return list<User> */
    private function auditors(int $count): array
    {
        $users = User::query()
            ->whereHas('role', fn ($role) => $role->where('code', 'agis_user'))
            ->take($count)
            ->get();
        while ($users->count() < $count) {
            $users->push($this->newAuditor('CIAS-AEP-'.($users->count() + 100)));
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

    private function user(string $username): User
    {
        return User::query()
            ->with(['role.permissions', 'roles.permissions', 'office'])
            ->where('username', $username)
            ->firstOrFail();
    }

    private function seedFinalizedFieldworkRecord(
        AuditEngagement $engagement,
        AuditProgramProcedure $procedure,
        User $auditor,
    ): void {
        $area = AuditArea::query()->where('is_active', true)->firstOrFail();
        $focus = AuditFocus::query()->where('audit_area_id', $area->id)->where('is_active', true)->firstOrFail();
        $engagement->auditAreas()->syncWithoutDetaching([$area->id]);
        $engagement->auditFocuses()->syncWithoutDetaching([$focus->id]);
        $record = AemsFieldworkRecord::query()->create([
            'record_family_uuid' => (string) Str::uuid(),
            'audit_engagement_id' => $engagement->id,
            'audit_program_procedure_id' => $procedure->id,
            'audit_area_id' => $area->id,
            'audit_focus_id' => $focus->id,
            'record_code' => 'FWR-'.$engagement->engagement_code.'-001',
            'record_type' => 'TESTING',
            'status' => 'FINALIZED',
            'current_version_number' => 1,
            'prepared_by' => $auditor->id,
            'finalized_by' => $this->user('departmenthead')->id,
            'finalized_at' => now(),
            'lock_version' => 1,
            'is_active' => true,
        ]);
        AemsFieldworkRecordVersion::query()->create([
            'fieldwork_record_id' => $record->id,
            'version_number' => 1,
            'record_type' => 'TESTING',
            'audit_program_procedure_id' => $procedure->id,
            'audit_area_id' => $area->id,
            'audit_focus_id' => $focus->id,
            'performed_on' => '2026-08-14',
            'procedure_performed' => 'Reconciled the selected collection transactions.',
            'result' => 'No unexplained differences were identified.',
            'conclusion' => 'The procedure was satisfactorily completed.',
            'execution_status' => 'COMPLETED',
            'created_by' => $auditor->id,
        ]);
    }
}
