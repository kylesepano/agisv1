<?php

namespace Tests\Feature\Api;

use App\Models\AuditArea;
use App\Models\AuditEngagement;
use App\Models\AuditEngagementOrder;
use App\Models\AuditEngagementPlan;
use App\Models\ArmisActualPersonDay;
use App\Models\ArmisEngagementAssignment;
use App\Models\ArmisResourceProfile;
use App\Models\AuditProgram;
use App\Models\AuditProgramProcedure;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\EngagementTeam;
use App\Models\EntryConference;
use App\Models\ExitConference;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\ReportRecipient;
use App\Models\User;
use App\Models\WorkingPaper;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AemsCompletionClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_formal_assessment_closure_and_atomic_closed_transition(): void
    {
        [$management, $auditor, $engagement] = $this->closureReadyEngagement();
        Sanctum::actingAs($auditor);

        $assessment = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/completion-assessments",
            $this->assessmentPayload(),
        )->assertCreated()
            ->assertJsonPath('data.assessment.statusCode', 'DRAFT')
            ->json('data.assessment');
        $items = collect($assessment['items'])->map(fn (array $item): array => [
            'criterionCode' => $item['criterionCode'],
            'plannedValue' => $item['plannedValue'],
            'actualValue' => $item['actualValue'],
            'resultCode' => 'PASS',
            'explanation' => 'Verified against the authoritative engagement record.',
            'blockingFlag' => false,
        ])->all();
        $assessment = $this->putJson(
            "/api/aems/engagements/{$engagement->id}/completion-assessments/{$assessment['id']}",
            [...$this->assessmentPayload(), 'items' => $items, 'lockVersion' => $assessment['lockVersion']],
        )->assertOk()->json('data.assessment');
        $assessment = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/completion-assessments/{$assessment['id']}/transitions/SUBMIT",
            ['lockVersion' => $assessment['lockVersion']],
        )->assertOk()
            ->assertJsonPath('data.assessment.statusCode', 'PENDING_REVIEW')
            ->json('data.assessment');

        Sanctum::actingAs($management);
        $assessment = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/completion-assessments/{$assessment['id']}/transitions/APPROVE",
            ['lockVersion' => $assessment['lockVersion']],
        )->assertOk()
            ->assertJsonPath('data.assessment.statusCode', 'APPROVED')
            ->json('data.assessment');
        $this->assertDatabaseHas('completion_assessment_versions', [
            'completion_assessment_id' => $assessment['id'],
            'version_no' => 3,
        ]);

        Sanctum::actingAs($auditor);
        $this->putJson("/api/aems/engagements/{$engagement->id}/retention", [
            'retentionClassificationCode' => 'AUDIT_ENGAGEMENT_RECORD',
            'retentionTriggerCode' => 'ENGAGEMENT_CLOSED',
            'retentionStartDate' => today()->toDateString(),
            'retentionPeriodValue' => 10,
            'retentionPeriodUnit' => 'YEARS',
            'permanentFlag' => false,
            'custodianUserId' => $auditor->id,
            'custodianOfficeId' => $auditor->office_id,
            'storageLocationDescription' => 'Protected AGIS private storage.',
            'legalHoldFlag' => false,
        ])->assertOk();
        $retention = $engagement->retentionRecord()->firstOrFail();
        Sanctum::actingAs($management);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/retention/{$retention->id}/approve",
            ['lockVersion' => $retention->lock_version],
        )->assertOk();

        // ARMIS is authoritative after the resource cutover. Reconcile the
        // provider snapshot before creating the formal Closure record.
        Sanctum::actingAs($auditor);
        $this->postJson("/api/aems/engagements/{$engagement->id}/completion-transfer/reconcile", [])
            ->assertOk()
            ->assertJsonPath('data.effortReconciliation.status', 'RECONCILED');

        $workspace = $this->postJson("/api/aems/engagements/{$engagement->id}/closure", [
            'completionAssessmentId' => $assessment['id'],
            'closureSummary' => 'The engagement objectives and reporting obligations are complete.',
        ])->assertCreated()->json('data');
        $this->assertTrue($workspace['readiness']['ready']);
        $closure = $workspace['closure'];
        $workspace = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/closures/{$closure['id']}/transitions/SUBMIT_CLOSURE",
            ['lockVersion' => $closure['lockVersion']],
        )->assertOk()
            ->assertJsonPath('data.closure.statusCode', 'PENDING_REVIEW')
            ->json('data');

        Sanctum::actingAs($management);
        $workspace = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/closures/{$closure['id']}/transitions/APPROVE_CLOSURE",
            ['lockVersion' => $workspace['closure']['lockVersion']],
        )->assertOk()
            ->assertJsonPath('data.closure.statusCode', 'APPROVED')
            ->json('data');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/closures/{$closure['id']}/transitions/CLOSE_ENGAGEMENT",
            [
                'lockVersion' => $workspace['closure']['lockVersion'],
                'engagementLockVersion' => $workspace['engagement']['lockVersion'],
            ],
        )->assertOk()
            ->assertJsonPath('data.engagement.status', 'CLOSED')
            ->assertJsonPath('data.closure.statusCode', 'CLOSED');

        $this->assertDatabaseHas('audit_engagements', [
            'id' => $engagement->id,
            'status' => 'CLOSED',
        ]);
        $this->assertDatabaseHas('engagement_closures', [
            'id' => $closure['id'],
            'status_code' => 'CLOSED',
        ]);
        $this->assertDatabaseHas('engagement_closure_events', [
            'engagement_closure_id' => $closure['id'],
            'action_code' => 'CLOSE_ENGAGEMENT',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'aems.engagement.closed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'aems.engagement.closed']);
        $this->assertNotNull($engagement->fresh()->closure->document_index_locked_at);

        Sanctum::actingAs($auditor);
        $this->postJson("/api/aems/engagements/{$engagement->id}/lessons-learned", [
            'categoryCode' => 'OTHER',
            'observation' => 'Attempt to mutate a closed official record.',
            'impact' => 'This must be rejected.',
            'recommendedImprovement' => 'Use the exceptional reopening workflow.',
            'confidentialityCode' => 'INTERNAL',
        ])->assertUnprocessable()->assertJsonValidationErrors('engagement');
    }

    public function test_completion_transfer_manifest_is_reconciled_idempotently_and_approved_snapshots_are_locked(): void
    {
        [$management, $auditor, $engagement] = $this->closureReadyEngagement();
        Sanctum::actingAs($auditor);

        $first = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/completion-transfer/reconcile",
            [],
        )->assertOk()
            ->assertJsonPath('data.manifest.status', 'RECONCILED')
            ->assertJsonPath('data.manifest.expectedCount', 0)
            ->assertJsonPath('data.effortReconciliation.status', 'RECONCILED')
            ->json('data');
        $second = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/completion-transfer/reconcile",
            [],
        )->assertOk()->json('data');

        $this->assertSame($first['manifest']['id'], $second['manifest']['id']);
        $this->assertSame(1, DB::table('aems_completion_transfer_manifests')
            ->where('audit_engagement_id', $engagement->id)->count());
        $this->assertSame(2, DB::table('aems_effort_reconciliations')
            ->where('audit_engagement_id', $engagement->id)->count());

        Sanctum::actingAs($management);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/completion-transfer/MANIFEST/{$second['manifest']['id']}/approve",
            ['lockVersion' => $second['manifest']['lockVersion'], 'comment' => 'Independent transfer manifest review completed.'],
        )->assertOk()->assertJsonPath('data.manifest.status', 'APPROVED');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/completion-transfer/EFFORT/{$second['effortReconciliation']['id']}/approve",
            ['lockVersion' => $second['effortReconciliation']['lockVersion'], 'comment' => 'Independent effort reconciliation review completed.'],
        )->assertOk()->assertJsonPath('data.effortReconciliation.status', 'APPROVED');

        Sanctum::actingAs($auditor);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/completion-transfer/reconcile",
            [],
        )->assertUnprocessable();
        $this->assertDatabaseHas('aems_completion_transfer_manifests', [
            'id' => $second['manifest']['id'],
            'status' => 'APPROVED',
        ]);
    }

    public function test_records_calendar_and_disposition_controls_are_scope_aware_and_auditable(): void
    {
        [$management, $auditor, $engagement] = $this->closureReadyEngagement();
        Sanctum::actingAs($auditor);
        $this->putJson("/api/aems/engagements/{$engagement->id}/retention", [
            'retentionClassificationCode' => 'AUDIT_ENGAGEMENT_RECORD',
            'retentionTriggerCode' => 'ENGAGEMENT_CLOSED',
            'retentionStartDate' => today()->toDateString(),
            'retentionPeriodValue' => 10,
            'retentionPeriodUnit' => 'YEARS',
            'custodianUserId' => $auditor->id,
            'custodianOfficeId' => $auditor->office_id,
        ])->assertOk();
        $retention = $engagement->retentionRecord()->firstOrFail();
        Sanctum::actingAs($management);
        $this->postJson("/api/aems/engagements/{$engagement->id}/retention/{$retention->id}/approve", ['lockVersion' => $retention->lock_version])->assertOk();

        Sanctum::actingAs($auditor);
        $this->postJson("/api/aems/engagements/{$engagement->id}/calendar/milestones", [
            'milestoneCode' => 'FINAL_RECORDS_REVIEW', 'title' => 'Final records review', 'dueDate' => today()->addDay()->toDateString(),
        ])->assertCreated();
        $this->getJson("/api/aems/engagements/{$engagement->id}/calendar")
            ->assertOk()->assertJsonPath('data.summary.total', 1);
        $this->getJson("/api/aems/engagements/{$engagement->id}/records?q=closure")
            ->assertOk()->assertJsonStructure(['data' => ['items', 'blockers', 'retention']]);

        $engagement->forceFill(['status' => 'CLOSED'])->save();
        $this->postJson("/api/aems/engagements/{$engagement->id}/retention/{$retention->id}/archive", ['reason' => 'Transfer to protected archive'])->assertForbidden();
        Sanctum::actingAs($management);
        $review = $this->postJson("/api/aems/engagements/{$engagement->id}/retention/{$retention->id}/destruction-review", ['reason' => 'Annual eligibility review'])->assertOk()->json('data');
        $this->assertFalse($review['eligible']);
        $this->assertDatabaseHas('aems_record_disposition_actions', ['action_code' => 'DESTRUCTION_REVIEW', 'audit_engagement_id' => $engagement->id]);

        DB::table('engagement_retention_records')->where('id', $retention->id)->update([
            'legal_hold_flag' => true,
            'legal_hold_reference' => 'HOLD-001',
        ]);
        $this->postJson("/api/aems/engagements/{$engagement->id}/retention/{$retention->id}/legal-hold-release", [
            'reason' => 'Court release received', 'reference' => 'RELEASE-001',
        ])->assertOk();
        $this->postJson("/api/aems/engagements/{$engagement->id}/retention/{$retention->id}/archive", [
            'reason' => 'Move to protected archive',
        ])->assertOk();
        $this->assertDatabaseHas('engagement_retention_records', ['id' => $retention->id, 'archive_status' => 'ARCHIVED', 'legal_hold_flag' => false]);
    }

    public function test_authoritative_blockers_separation_retention_and_reopening_controls(): void
    {
        [$management, $auditor, $engagement] = $this->closureReadyEngagement();
        $engagement->workingPapers()->update(['status' => 'DRAFT']);
        Sanctum::actingAs($auditor);
        $closure = $this->postJson("/api/aems/engagements/{$engagement->id}/closure", [
            'closureSummary' => 'Draft closure cannot override source failures.',
        ])->assertCreated()->json('data.closure');
        $this->assertDatabaseHas('notifications', [
            'type' => 'AEMS_CLOSURE_RECORDS_BLOCKED',
            'subject_id' => $closure['id'],
        ]);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/closures/{$closure['id']}/transitions/SUBMIT_CLOSURE",
            [
                'lockVersion' => $closure['lockVersion'],
                'checklist' => [['checklistCode' => 'WORKING_PAPERS_TERMINAL', 'resultCode' => 'PASS']],
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('checklist');

        $this->putJson("/api/aems/engagements/{$engagement->id}/retention", [
            'retentionClassificationCode' => 'PERMANENT_AUDIT',
            'retentionTriggerCode' => 'ENGAGEMENT_CLOSED',
            'retentionStartDate' => today()->toDateString(),
            'retentionPeriodValue' => 10,
            'retentionPeriodUnit' => 'YEARS',
            'permanentFlag' => true,
            'scheduledDispositionDate' => today()->addYears(10)->toDateString(),
            'custodianUserId' => $auditor->id,
            'custodianOfficeId' => $auditor->office_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('scheduledDispositionDate');

        $closed = $engagement->fresh();
        $closed->forceFill(['status' => 'CLOSED', 'closed_by' => $management->id, 'closed_at' => now()])->save();
        DB::table('engagement_closures')->where('id', $closure['id'])->update([
            'status_code' => 'CLOSED',
            'closed_by' => $management->id,
            'closed_at' => now(),
            'closed_snapshot_json' => ['preserved' => true],
            'updated_at' => now(),
        ]);
        $authorityVersion = $this->documentVersion('Written reopening authority');
        Sanctum::actingAs(User::query()->where('username', 'admin')->firstOrFail());
        $this->postJson("/api/aems/engagements/{$engagement->id}/reopen-requests", [
            'reasonCode' => 'SIGNIFICANT_ERROR',
            'reasonText' => 'A significant error requires controlled correction.',
            'authorityDocumentVersionId' => $authorityVersion->id,
        ])->assertForbidden();

        Sanctum::actingAs($auditor);
        $reopen = $this->postJson("/api/aems/engagements/{$engagement->id}/reopen-requests", [
            'reasonCode' => 'SIGNIFICANT_ERROR',
            'reasonText' => 'A significant error requires controlled correction.',
            'authorityDocumentVersionId' => $authorityVersion->id,
        ])->assertCreated()->json('data.request');
        $reopen = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/reopen-requests/{$reopen['id']}/transitions/SUBMIT_REOPEN_REQUEST",
            ['lockVersion' => $reopen['lock_version'] ?? $reopen['lockVersion']],
        )->assertOk()->json('data.request');
        Sanctum::actingAs($management);
        $reopen = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/reopen-requests/{$reopen['id']}/transitions/APPROVE_REOPEN_REQUEST",
            ['lockVersion' => $reopen['lock_version'] ?? $reopen['lockVersion']],
        )->assertOk()->json('data.request');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/reopen-requests/{$reopen['id']}/transitions/IMPLEMENT_REOPEN_REQUEST",
            [
                'lockVersion' => $reopen['lock_version'] ?? $reopen['lockVersion'],
                'comment' => 'Implement the independently approved controlled correction revision.',
            ],
        )->assertOk();
        $this->assertDatabaseHas('engagement_reopen_requests', [
            'id' => $reopen['id'],
            'status_code' => 'IMPLEMENTED',
        ]);
        $this->assertDatabaseHas('audit_engagements', [
            'id' => $engagement->id,
            'status' => 'CLOSURE_REVIEW',
            'reopen_revision_number' => 1,
        ]);
        $this->assertSame(
            ['preserved' => true],
            json_decode(
                DB::table('engagement_closures')
                    ->where('id', $closure['id'])
                    ->value('closed_snapshot_json'),
                true,
            ),
        );
    }

    public function test_completion_assessment_validation_separation_immutability_and_revision_history(): void
    {
        [$management, , $engagement] = $this->closureReadyEngagement();
        $independentApprover = User::factory()->create([
            'office_id' => $management->office_id,
        ]);
        $independentApprover->roles()->sync($management->roles()->pluck('roles.id'));

        Sanctum::actingAs($management);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/completion-assessments",
            [],
        )->assertUnprocessable()
            ->assertJsonValidationErrors([
                'overallResultCode',
                'objectivesAchievementSummary',
                'recommendationForClosure',
            ]);

        $assessment = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/completion-assessments",
            $this->assessmentPayload(),
        )->assertCreated()->json('data.assessment');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/completion-assessments/{$assessment['id']}/transitions/SUBMIT",
            ['lockVersion' => $assessment['lockVersion']],
        )->assertUnprocessable()->assertJsonValidationErrors('items');

        $items = collect($assessment['items'])->map(fn (array $item): array => [
            'criterionCode' => $item['criterionCode'],
            'plannedValue' => $item['plannedValue'],
            'actualValue' => $item['actualValue'],
            'resultCode' => 'PASS',
            'explanation' => 'Authoritative source record verified.',
            'blockingFlag' => false,
        ])->all();
        $assessment = $this->putJson(
            "/api/aems/engagements/{$engagement->id}/completion-assessments/{$assessment['id']}",
            [...$this->assessmentPayload(), 'items' => $items, 'lockVersion' => $assessment['lockVersion']],
        )->assertOk()->json('data.assessment');
        $assessment = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/completion-assessments/{$assessment['id']}/transitions/SUBMIT",
            ['lockVersion' => $assessment['lockVersion']],
        )->assertOk()->json('data.assessment');

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/completion-assessments/{$assessment['id']}/transitions/APPROVE",
            ['lockVersion' => $assessment['lockVersion']],
        )->assertUnprocessable()->assertJsonValidationErrors('action');

        Sanctum::actingAs($independentApprover);
        $assessment = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/completion-assessments/{$assessment['id']}/transitions/APPROVE",
            ['lockVersion' => $assessment['lockVersion']],
        )->assertOk()->json('data.assessment');

        Sanctum::actingAs($management);
        $this->putJson(
            "/api/aems/engagements/{$engagement->id}/completion-assessments/{$assessment['id']}",
            [...$this->assessmentPayload(), 'items' => $items, 'lockVersion' => $assessment['lockVersion']],
        )->assertUnprocessable()->assertJsonValidationErrors('assessment');

        $revision = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/completion-assessments/{$assessment['id']}/revisions",
            ['reason' => 'Correct a formally documented post-review variance.'],
        )->assertCreated()
            ->assertJsonPath('data.assessment.revisionNumber', 2)
            ->assertJsonPath('data.assessment.statusCode', 'DRAFT')
            ->json('data.assessment');

        $this->assertDatabaseHas('completion_assessments', [
            'id' => $assessment['id'],
            'is_current_revision' => false,
            'status_code' => 'APPROVED',
        ]);
        $this->assertDatabaseHas('completion_assessments', [
            'id' => $revision['id'],
            'is_current_revision' => true,
            'revision_number' => 2,
        ]);
        $this->assertGreaterThanOrEqual(
            3,
            DB::table('completion_assessment_versions')
                ->where('completion_assessment_id', $assessment['id'])
                ->count(),
        );
    }

    /** @return array{User, User, AuditEngagement} */
    private function closureReadyEngagement(): array
    {
        $management = User::query()->where('username', 'departmenthead')->firstOrFail();
        $auditor = User::query()->where('username', 'auditor')->firstOrFail();
        $office = Office::query()->findOrFail($auditor->office_id);
        $authorityVersion = $this->documentVersion('Special engagement authority');
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => 'AEMS-CLOSE-'.str()->random(6),
            'title' => 'Formal Closure Control Audit',
            'source_type' => 'SPECIAL',
            'special_authority_reference' => 'AUTH-CLOSE-001',
            'special_authority_date' => today()->subMonths(2),
            'special_authority_approved_by' => $management->id,
            'special_authority_document_version_id' => $authorityVersion->id,
            'objectives' => 'Verify controlled completion and closure.',
            'scope' => 'All formal closure gates.',
            'planned_start_date' => today()->subMonth(),
            'planned_end_date' => today()->subDay(),
            'actual_start_date' => today()->subMonth(),
            'actual_end_date' => today(),
            'expected_report_date' => today(),
            'planned_person_days' => 20,
            'actual_person_days' => 19,
            'status' => 'CLOSURE_REVIEW',
            'created_by' => $auditor->id,
            'updated_by' => $management->id,
        ]);
        $engagement->offices()->attach($office->id, ['is_primary' => true]);
        $engagement->auditAreas()->attach(AuditArea::query()->firstOrFail()->id);
        EngagementTeam::query()->create([
            'audit_engagement_id' => $engagement->id,
            'user_id' => $auditor->id,
            'assignment_role_code' => 'TEAM_LEADER',
            'assigned_by' => $management->id,
            'planned_person_days' => 20,
            'actual_person_days' => 19,
            'is_active' => true,
        ]);
        $profile = ArmisResourceProfile::query()
            ->where('user_id', $auditor->id)
            ->where('status', 'ACTIVE')
            ->firstOrFail();
        $assignment = ArmisEngagementAssignment::query()->create([
            'assignment_family_uuid' => (string) str()->uuid(),
            'audit_engagement_id' => $engagement->id,
            'resource_profile_id' => $profile->id,
            'version_number' => 1,
            'is_current_revision' => true,
            'assignment_role_code' => 'TEAM_LEADER',
            'assigned_from' => today()->subMonth(),
            'assigned_until' => today()->addDay(),
            'planned_person_days' => 20,
            'status' => 'LOCKED',
            'created_by' => $auditor->id,
            'updated_by' => $management->id,
            'approved_by' => $management->id,
            'approved_at' => now(),
        ]);
        ArmisActualPersonDay::query()->create([
            'actual_family_uuid' => (string) str()->uuid(),
            'resource_profile_id' => $profile->id,
            'assignment_id' => $assignment->id,
            'source_module' => 'AEMS',
            'source_type' => 'AEMS_ENGAGEMENT',
            'source_id' => $engagement->id,
            'period_start' => today()->subMonth(),
            'period_end' => today(),
            'version_number' => 1,
            'actual_person_days' => 19,
            'status' => 'APPROVED',
            'is_current_revision' => true,
            'created_by' => $auditor->id,
            'updated_by' => $management->id,
            'approved_by' => $management->id,
            'approved_at' => now(),
        ]);
        AuditEngagementOrder::query()->create([
            'audit_engagement_id' => $engagement->id,
            'order_code' => 'AEO-'.$engagement->engagement_code,
            'status' => 'ISSUED',
            'prepared_by' => $auditor->id,
            'approved_by' => $management->id,
            'approved_at' => now(),
            'issued_by' => $management->id,
            'issued_at' => now(),
            'is_active' => true,
        ]);
        $plan = AuditEngagementPlan::query()->create([
            'audit_engagement_id' => $engagement->id,
            'plan_code' => 'AEP-'.$engagement->engagement_code,
            'status' => 'APPROVED',
            'prepared_by' => $auditor->id,
            'approved_by' => $management->id,
            'approved_at' => now(),
            'is_active' => true,
        ]);
        $program = AuditProgram::query()->create([
            'audit_engagement_id' => $engagement->id,
            'audit_engagement_plan_id' => $plan->id,
            'program_code' => 'AP-'.$engagement->engagement_code,
            'title' => 'Closure Audit Program',
            'objective' => 'Complete all required procedures.',
            'status' => 'COMPLETED',
            'prepared_by' => $auditor->id,
            'approved_by' => $management->id,
            'approved_at' => now(),
            'completed_at' => now(),
            'is_current_revision' => true,
            'is_active' => true,
        ]);
        $procedure = AuditProgramProcedure::query()->create([
            'audit_program_id' => $program->id,
            'procedure_code' => 'PROC-001',
            'sequence_number' => 1,
            'objective' => 'Complete the test.',
            'procedure_description' => 'Inspect authoritative records.',
            'status' => 'COMPLETED',
            'completed_at' => now(),
            'completed_by' => $auditor->id,
        ]);
        WorkingPaper::query()->create([
            'audit_engagement_id' => $engagement->id,
            'audit_program_procedure_id' => $procedure->id,
            'working_paper_code' => 'WP-'.$engagement->engagement_code,
            'title' => 'Closure support',
            'status' => 'APPROVED',
            'prepared_by' => $auditor->id,
            'approved_by' => $management->id,
            'approved_at' => now(),
            'is_active' => true,
        ]);
        EntryConference::query()->create([
            'audit_engagement_id' => $engagement->id,
            'conference_code' => 'ENTRY-'.$engagement->engagement_code,
            'status' => 'WAIVED',
            'waiver_reason' => 'Authorized waiver.',
            'waiver_authority' => 'City Internal Auditor',
            'waived_at' => now(),
            'waived_by' => $management->id,
            'created_by' => $auditor->id,
        ]);
        ExitConference::query()->create([
            'audit_engagement_id' => $engagement->id,
            'conference_code' => 'EXIT-'.$engagement->engagement_code,
            'scheduled_start_at' => now(),
            'agenda' => 'Authorized waiver of the Exit Conference.',
            'status' => 'WAIVED',
            'waiver_reason' => 'Authorized waiver.',
            'created_by' => $auditor->id,
        ]);
        $reportDocument = $this->documentVersion('Issued Final Report');
        $report = AuditReport::query()->create([
            'audit_engagement_id' => $engagement->id,
            'report_code' => 'FAR-'.$engagement->engagement_code,
            'title' => 'Final Audit Report',
            'report_stage' => 'FINAL_REPORT',
            'status' => 'ISSUED',
            'current_version_number' => 1,
            'document_id' => $reportDocument->document_id,
            'prepared_by' => $auditor->id,
            'approved_by' => $management->id,
            'approved_at' => now(),
            'issued_by' => $management->id,
            'issued_at' => now(),
            'is_active' => true,
        ]);
        $reportVersion = AuditReportVersion::query()->create([
            'audit_report_id' => $report->id,
            'version_number' => 1,
            'report_stage' => 'FINAL_REPORT',
            'content_snapshot' => ['issued' => true],
            'document_version_id' => $reportDocument->id,
            'checksum_sha256' => $reportDocument->checksum_sha256,
            'pdf_file_name' => $reportDocument->original_file_name,
            'file_size' => $reportDocument->file_size,
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => $management->id,
            'created_by' => $auditor->id,
        ]);
        $report->forceFill(['current_version_id' => $reportVersion->id])->saveQuietly();
        ReportRecipient::query()->create([
            'audit_report_version_id' => $reportVersion->id,
            'user_id' => $management->id,
            'office_id' => $management->office_id,
            'recipient_type' => 'APPROVING_AUTHORITY',
            'delivery_method' => 'AGIS',
            'delivery_status' => 'SENT',
            'sent_at' => now(),
        ]);

        return [$management, $auditor, $engagement];
    }

    /** @return array<string, string> */
    private function assessmentPayload(): array
    {
        return [
            'overallResultCode' => 'SATISFACTORY',
            'objectivesAchievementSummary' => 'Approved objectives were achieved.',
            'scopeCompletionSummary' => 'The approved scope was completed.',
            'methodologyAssessment' => 'Approved methodology was followed.',
            'standardsComplianceAssessment' => 'Applicable standards were followed.',
            'evidenceSufficiencyAssessment' => 'Evidence was sufficient and reliable.',
            'supervisionAssessment' => 'Supervision and review were completed.',
            'reportTimelinessAssessment' => 'The report was issued on time.',
            'managementResponseAssessment' => 'Required dialogue was completed.',
            'recommendationTransferAssessment' => 'CMS disposition is complete.',
            'resourceUtilizationAssessment' => 'Actual effort was recorded.',
            'limitationsSummary' => '',
            'lessonsSummary' => 'Use the controlled closure checklist.',
            'recommendationForClosure' => 'Proceed to formal Closure review.',
        ];
    }

    private function documentVersion(string $title): DocumentVersion
    {
        $type = MasterList::query()->where('code', 'DOCUMENT_TYPE')
            ->firstOrFail()->items()->where('code', 'OTHER')->firstOrFail();
        $confidentiality = MasterList::query()->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->firstOrFail()->items()->where('code', 'INTERNAL')->firstOrFail();
        $user = User::query()->where('username', 'departmenthead')->firstOrFail();
        $payload = $title.' '.str()->uuid();
        $path = 'aems/tests/'.str()->uuid().'.pdf';
        Storage::disk('local')->put($path, $payload);
        $document = Document::query()->create([
            'document_code' => 'DOC-'.str()->random(10),
            'document_type_id' => $type->id,
            'confidentiality_level_id' => $confidentiality->id,
            'title' => $title,
            'owner_module' => 'AEMS',
            'library_visible' => false,
            'original_file_name' => str($title)->slug().'.pdf',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => strlen($payload),
            'checksum_sha256' => hash('sha256', $payload),
            'uploaded_by' => $user->id,
            'updated_by' => $user->id,
            'is_active' => true,
        ]);
        $version = $document->versions()->create([
            'version_number' => 1,
            'version_label' => 'Version 1',
            'change_summary' => 'Test immutable source.',
            'original_file_name' => $document->original_file_name,
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => strlen($payload),
            'checksum_sha256' => hash('sha256', $payload),
            'uploaded_by' => $user->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->save();

        return $version;
    }
}
