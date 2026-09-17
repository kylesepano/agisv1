<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\AuditEngagement;
use App\Models\AuditEngagementOrder;
use App\Models\AuditEngagementPlan;
use App\Models\AuditFinding;
use App\Models\AuditLog;
use App\Models\AemsEffortReconciliation;
use App\Models\ArmisActualPersonDay;
use App\Models\ArmisEngagementAssignment;
use App\Models\ArmisResourceProfile;
use App\Models\AuditProgram;
use App\Models\AuditProgramProcedure;
use App\Models\AuditRecommendation;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Models\EngagementTeam;
use App\Models\EntryConference;
use App\Models\ExitConference;
use App\Models\ManagementResponse;
use App\Models\Office;
use App\Models\ReportRecipient;
use App\Models\User;
use App\Models\WorkingPaper;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AemsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_dashboard_derives_portfolio_cards_stage_progress_and_closure_gates(): void
    {
        $management = User::query()->where('username', 'departmenthead')->firstOrFail();
        $auditor = User::query()->where('username', 'auditor')->firstOrFail();
        $office = Office::query()->firstOrFail();
        $ready = $this->createEngagement(
            'AEMS-TRACK-READY',
            'Issued Engagement Ready for Closure',
            'ISSUED',
            $management,
            $office,
            ['actual_person_days' => 18],
        );
        $this->completeWorkflow($ready, $management, $office);

        $fieldwork = $this->createEngagement(
            'AEMS-TRACK-LATE',
            'Overdue Fieldwork Engagement',
            'FIELDWORK',
            $management,
            $office,
        );
        $plan = AuditEngagementPlan::query()->create([
            'audit_engagement_id' => $fieldwork->id,
            'plan_code' => 'AEP-TRACK-LATE',
            'status' => 'APPROVED',
            'prepared_by' => $auditor->id,
        ]);
        $program = AuditProgram::query()->create([
            'audit_engagement_id' => $fieldwork->id,
            'audit_engagement_plan_id' => $plan->id,
            'program_code' => 'AP-TRACK-LATE',
            'title' => 'Late Procedures',
            'objective' => 'Test overdue tracker metrics.',
            'status' => 'ACTIVE',
            'is_current_revision' => true,
            'prepared_by' => $auditor->id,
        ]);
        $procedure = AuditProgramProcedure::query()->create([
            'audit_program_id' => $program->id,
            'procedure_code' => 'PROC-LATE-01',
            'objective' => 'Test timeliness.',
            'procedure_description' => 'Perform the overdue procedure.',
            'target_date' => now()->subDay()->toDateString(),
            'status' => 'IN_PROGRESS',
        ]);
        WorkingPaper::query()->create([
            'audit_engagement_id' => $fieldwork->id,
            'audit_program_procedure_id' => $procedure->id,
            'working_paper_code' => 'WP-TRACK-LATE',
            'title' => 'Submitted working paper',
            'status' => 'SUBMITTED',
            'prepared_by' => $auditor->id,
        ]);
        AuditFinding::query()->create([
            'finding_family_uuid' => (string) Str::uuid(),
            'audit_engagement_id' => $fieldwork->id,
            'finding_code' => 'F-TRACK-LATE',
            'title' => 'Response overdue',
            'criteria' => 'Responses are timely.',
            'condition' => 'The response remains outstanding.',
            'cause' => 'Delayed coordination.',
            'effect' => 'Dialogue is delayed.',
            'responsible_office_id' => $office->id,
            'status' => 'AWAITING_MANAGEMENT_RESPONSE',
            'authored_by' => $auditor->id,
            'management_response_due_date' => now()->subDay()->toDateString(),
        ]);
        ExitConference::query()->create([
            'audit_engagement_id' => $fieldwork->id,
            'conference_code' => 'EXIT-TRACK-UPCOMING',
            'scheduled_start_at' => now()->addDays(10),
            'agenda' => 'Discuss current findings.',
            'status' => 'SCHEDULED',
            'created_by' => $management->id,
        ]);
        AuditReport::query()->create([
            'audit_engagement_id' => $fieldwork->id,
            'report_code' => 'RPT-TRACK-PENDING',
            'title' => 'Pending Report',
            'report_stage' => 'DRAFT_REPORT',
            'status' => 'PENDING_REVIEW',
            'prepared_by' => $auditor->id,
        ]);

        Sanctum::actingAs($management);
        $response = $this->getJson('/api/aems/dashboard?perPage=10')
            ->assertOk()
            ->assertJsonPath('data.cards.activeEngagements', 2)
            ->assertJsonPath('data.cards.engagementsInFieldwork', 1)
            ->assertJsonPath('data.cards.overdueProcedures', 1)
            ->assertJsonPath('data.cards.workingPapersAwaitingReview', 1)
            ->assertJsonPath('data.cards.findingsAwaitingResponse', 1)
            ->assertJsonPath('data.cards.upcomingExitConferences', 1)
            ->assertJsonPath('data.cards.upcomingConferences', 1)
            ->assertJsonPath('data.cards.reportsPendingApproval', 1)
            ->assertJsonPath('data.cards.engagementsReadyForClosure', 1)
            ->assertJsonPath('data.cards.evidenceRequestsAwaitingResponse', 0)
            ->assertJsonPath('data.cards.findingsAwaitingReview', 0)
            ->assertJsonPath('data.workQueues.overdueProcedures.count', 1)
            ->assertJsonPath('data.workQueues.evidenceGaps.count', 0)
            ->assertJsonPath('data.phaseCounts.0.key', 'planning')
            ->assertJsonPath('data.integrations.core.available', true)
            ->assertJsonPath('data.integrations.iap.mode', 'APPROVED_PLAN_IMPORT')
            ->assertJsonPath('data.integrations.cms.mode', 'IMMUTABLE_INTAKE')
            ->assertJsonPath('data.integrations.armis.mode', 'ARMIS_AUTHORITATIVE')
            ->assertJsonPath('data.integrations.armis.authoritative', true)
            ->assertJsonCount(2, 'data.engagements');
        $this->assertIsInt($response->json('data.notifications.unread'));

        $readyData = collect($response->json('data.engagements'))
            ->firstWhere('engagementCode', 'AEMS-TRACK-READY');
        $this->assertTrue($readyData['closure']['isReady']);
        $this->assertSame('READY_FOR_CLOSURE', $readyData['health']);
        $this->assertCount(14, $readyData['stages']);
        $this->assertSame(
            'READY',
            collect($readyData['stages'])->firstWhere('key', 'engagementClosure')['status'],
        );

        $lateData = collect($response->json('data.engagements'))
            ->firstWhere('engagementCode', 'AEMS-TRACK-LATE');
        $this->assertSame('OVERDUE', $lateData['health']);
        $this->assertSame(
            1,
            collect($lateData['stages'])->firstWhere('key', 'fieldworkProcedures')['overdue'],
        );
        $this->assertContains('Final report issued', $lateData['closure']['blockers']);
    }

    public function test_dashboard_respects_assignment_scope_and_filters(): void
    {
        $management = User::query()->where('username', 'departmenthead')->firstOrFail();
        $auditor = User::query()->where('username', 'auditor')->firstOrFail();
        $office = Office::query()->firstOrFail();
        $assigned = $this->createEngagement(
            'AEMS-TRACK-MINE',
            'Assigned Revenue Audit',
            'ENGAGEMENT_PLANNING',
            $management,
            $office,
        );
        $this->createEngagement(
            'AEMS-TRACK-OTHER',
            'Unassigned Procurement Audit',
            'FIELDWORK',
            $management,
            $office,
        );
        EngagementTeam::query()->create([
            'audit_engagement_id' => $assigned->id,
            'user_id' => $auditor->id,
            'assignment_role_code' => 'AUDITOR',
            'assigned_from' => now()->toDateString(),
            'assigned_by' => $management->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($auditor);
        $this->getJson('/api/aems/dashboard')
            ->assertOk()
            ->assertJsonPath('data.cards.activeEngagements', 1)
            ->assertJsonPath('data.cards.engagementsInPlanning', 1)
            ->assertJsonPath('data.cards.engagementsInFieldwork', 0)
            ->assertJsonPath('data.integrations.iap.eligibleEngagements', null)
            ->assertJsonPath('data.integrations.cms.transferredRecommendations', null)
            ->assertJsonCount(1, 'data.engagements')
            ->assertJsonPath('data.engagements.0.engagementCode', 'AEMS-TRACK-MINE');

        $this->getJson('/api/aems/dashboard?search=procurement')
            ->assertOk()
            ->assertJsonCount(0, 'data.engagements')
            ->assertJsonPath('data.pagination.total', 0);

        $this->getJson('/api/aems/dashboard?phase=fieldwork')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_progress_report_export_is_scoped_permissioned_and_logged(): void
    {
        $management = User::query()->where('username', 'departmenthead')->firstOrFail();
        $auditor = User::query()->where('username', 'auditor')->firstOrFail();
        $office = Office::query()->firstOrFail();
        $engagement = $this->createEngagement(
            'AEMS-TRACK-EXPORT',
            'Exportable Engagement',
            'FIELDWORK',
            $management,
            $office,
        );
        $engagement->update(['title' => '=SUM(1,1)']);
        EngagementTeam::query()->create([
            'audit_engagement_id' => $engagement->id,
            'user_id' => $auditor->id,
            'assignment_role_code' => 'AUDITOR',
            'assigned_from' => now()->toDateString(),
            'assigned_by' => $management->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($auditor);
        $this->getJson('/api/aems/dashboard')
            ->assertOk()
            ->assertJsonPath('data.capabilities.canExport', false);
        $this->get('/api/aems/dashboard/export')->assertForbidden();
        $this->get('/api/aems/dashboard/queues/export')->assertForbidden();

        Sanctum::actingAs($management);
        $response = $this->get('/api/aems/dashboard/export?search=AEMS-TRACK-EXPORT')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('AEMS Engagement Progress Report', $content);
        $this->assertStringContainsString('AEMS-TRACK-EXPORT', $content);
        $this->assertStringContainsString("'=SUM(1,1)", $content);
        $this->assertStringNotContainsString('AEMS-TRACK-OTHER', $content);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $management->id,
            'action' => 'aems.dashboard.exported',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $management->id,
            'action' => 'aems.dashboard.exported',
        ]);
        $audit = AuditLog::query()
            ->where('action', 'aems.dashboard.exported')
            ->latest('id')
            ->firstOrFail();
        $activity = ActivityLog::query()
            ->where('action', 'aems.dashboard.exported')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(1, $audit->metadata['row_count']);
        $this->assertSame(1, $activity->metadata['rowCount']);

        $queueExport = $this->get('/api/aems/dashboard/queues/export?search=AEMS-TRACK-EXPORT')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('AEMS Operational Work Queues', $queueExport->streamedContent());
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $management->id,
            'action' => 'aems.dashboard.queues_exported',
        ]);
    }

    private function createEngagement(
        string $code,
        string $title,
        string $status,
        User $actor,
        Office $office,
        array $attributes = [],
    ): AuditEngagement {
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => $code,
            'title' => $title,
            'source_type' => 'SPECIAL',
            'special_authority_reference' => "AUTH-{$code}",
            'special_authority_date' => now()->toDateString(),
            'special_authority_approved_by' => $actor->id,
            'objectives' => 'Evaluate the effectiveness of the selected controls.',
            'scope' => 'The current operating period and responsible office.',
            'planned_start_date' => now()->subMonth()->toDateString(),
            'planned_end_date' => now()->addMonth()->toDateString(),
            'expected_report_date' => now()->addMonths(2)->toDateString(),
            'status' => $status,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'is_active' => true,
            ...$attributes,
        ]);
        $engagement->offices()->attach($office->id, ['is_primary' => true]);

        return $engagement;
    }

    private function completeWorkflow(
        AuditEngagement $engagement,
        User $actor,
        Office $office,
    ): void {
        EntryConference::query()->create([
            'audit_engagement_id' => $engagement->id,
            'conference_code' => 'ENTRY-TRACK-READY',
            'status' => 'WAIVED',
            'waiver_reason' => 'Formally waived for the completed tracker fixture.',
            'waiver_authority' => 'City Internal Auditor',
            'waived_at' => now()->subMonth(),
            'waived_by' => $actor->id,
            'created_by' => $actor->id,
        ]);
        AuditEngagementOrder::query()->create([
            'audit_engagement_id' => $engagement->id,
            'order_code' => 'AEO-TRACK-READY',
            'status' => 'ISSUED',
            'prepared_by' => $actor->id,
            'issued_by' => $actor->id,
            'issued_at' => now(),
        ]);
        $plan = AuditEngagementPlan::query()->create([
            'audit_engagement_id' => $engagement->id,
            'plan_code' => 'AEP-TRACK-READY',
            'status' => 'APPROVED',
            'prepared_by' => $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);
        $program = AuditProgram::query()->create([
            'audit_engagement_id' => $engagement->id,
            'audit_engagement_plan_id' => $plan->id,
            'program_code' => 'AP-TRACK-READY',
            'title' => 'Completed Program',
            'objective' => 'Complete the audit.',
            'status' => 'COMPLETED',
            'is_current_revision' => true,
            'prepared_by' => $actor->id,
            'completed_at' => now(),
        ]);
        $procedure = AuditProgramProcedure::query()->create([
            'audit_program_id' => $program->id,
            'procedure_code' => 'PROC-TRACK-READY',
            'objective' => 'Complete testing.',
            'procedure_description' => 'Perform the procedure.',
            'status' => 'COMPLETED',
            'completed_at' => now(),
            'completed_by' => $actor->id,
        ]);
        WorkingPaper::query()->create([
            'audit_engagement_id' => $engagement->id,
            'audit_program_procedure_id' => $procedure->id,
            'working_paper_code' => 'WP-TRACK-READY',
            'title' => 'Approved working paper',
            'status' => 'APPROVED',
            'prepared_by' => $actor->id,
            'approved_at' => now(),
            'approved_by' => $actor->id,
        ]);
        $finding = AuditFinding::query()->create([
            'finding_family_uuid' => (string) Str::uuid(),
            'audit_engagement_id' => $engagement->id,
            'finding_code' => 'F-TRACK-READY',
            'title' => 'Finalized finding',
            'criteria' => 'Controls are documented.',
            'condition' => 'Documentation was incomplete.',
            'cause' => 'No checklist.',
            'effect' => 'Review delays.',
            'responsible_office_id' => $office->id,
            'status' => 'FINALIZED',
            'authored_by' => $actor->id,
            'finalized_at' => now(),
            'finalized_by' => $actor->id,
        ]);
        AuditRecommendation::query()->create([
            'audit_finding_id' => $finding->id,
            'recommendation_code' => 'REC-TRACK-READY',
            'recommendation' => 'Adopt the checklist.',
            'responsible_office_id' => $office->id,
            'status' => 'TRANSFERRED',
            'created_by' => $actor->id,
            'transferred_to_cms_at' => now(),
            'transferred_to_cms_by' => $actor->id,
        ]);
        ManagementResponse::query()->create([
            'response_family_uuid' => (string) Str::uuid(),
            'audit_finding_id' => $finding->id,
            'response_code' => 'RESP-TRACK-READY',
            'agreement_position' => 'AGREE',
            'management_comment' => 'Management agrees.',
            'responsible_office_id' => $office->id,
            'status' => 'DIALOGUE_FINALIZED',
            'authored_by' => $actor->id,
            'finalized_at' => now(),
            'finalized_by' => $actor->id,
        ]);
        ExitConference::query()->create([
            'audit_engagement_id' => $engagement->id,
            'conference_code' => 'EXIT-TRACK-READY',
            'scheduled_start_at' => now()->subWeek(),
            'agenda' => 'Discuss final results.',
            'minutes' => 'Results were acknowledged.',
            'status' => 'COMPLETED',
            'created_by' => $actor->id,
            'completed_at' => now()->subWeek(),
            'completed_by' => $actor->id,
        ]);
        $report = AuditReport::query()->create([
            'audit_engagement_id' => $engagement->id,
            'report_code' => 'RPT-TRACK-READY',
            'title' => 'Issued Final Report',
            'report_stage' => 'FINAL_REPORT',
            'status' => 'DRAFT',
            'prepared_by' => $actor->id,
        ]);
        $version = AuditReportVersion::query()->create([
            'audit_report_id' => $report->id,
            'version_number' => 1,
            'report_stage' => 'FINAL_REPORT',
            'content_snapshot' => ['title' => 'Issued Final Report'],
            'checksum_sha256' => str_repeat('a', 64),
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => $actor->id,
            'created_by' => $actor->id,
        ]);
        ReportRecipient::query()->create([
            'audit_report_version_id' => $version->id,
            'office_id' => $office->id,
            'recipient_type' => 'AUDITEE',
            'delivery_method' => 'SYSTEM',
            'delivery_status' => 'SENT',
            'sent_at' => now(),
        ]);
        $report->forceFill([
            'status' => 'ISSUED',
            'current_version_number' => 1,
            'current_version_id' => $version->id,
            'issued_at' => now(),
            'issued_by' => $actor->id,
        ])->save();

        // ARMIS is authoritative after the resource ownership cutover. Keep
        // the ready-for-closure fixture explicit about its approved effort
        // reconciliation instead of relying on the retired IAP fallback.
        AemsEffortReconciliation::query()->create([
            'audit_engagement_id' => $engagement->id,
            'version_number' => 1,
            'provider_mode' => 'ARMIS_AUTHORITATIVE',
            'status' => 'APPROVED',
            'planned_person_days' => (float) $engagement->planned_person_days,
            'aems_actual_person_days' => (float) $engagement->actual_person_days,
            'provider_actual_person_days' => (float) $engagement->actual_person_days,
            'variance_person_days' => 0,
            'source_snapshot_json' => ['fixture' => true, 'provider' => 'ARMIS'],
            'generated_by' => $actor->id,
            'generated_at' => now(),
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);
        $profile = ArmisResourceProfile::query()
            ->where('user_id', $actor->id)
            ->where('status', 'ACTIVE')
            ->firstOrFail();
        $assignment = ArmisEngagementAssignment::query()->create([
            'assignment_family_uuid' => (string) Str::uuid(),
            'audit_engagement_id' => $engagement->id,
            'resource_profile_id' => $profile->id,
            'version_number' => 1,
            'is_current_revision' => true,
            'assignment_role_code' => 'SUPERVISOR',
            'assigned_from' => now()->subMonth()->toDateString(),
            'assigned_until' => now()->addMonth()->toDateString(),
            'planned_person_days' => (float) $engagement->planned_person_days,
            'status' => 'LOCKED',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);
        ArmisActualPersonDay::query()->create([
            'actual_family_uuid' => (string) Str::uuid(),
            'resource_profile_id' => $profile->id,
            'assignment_id' => $assignment->id,
            'source_module' => 'AEMS',
            'source_type' => 'AEMS_ENGAGEMENT',
            'source_id' => $engagement->id,
            'period_start' => now()->subMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'version_number' => 1,
            'actual_person_days' => (float) $engagement->actual_person_days,
            'status' => 'LOCKED',
            'is_current_revision' => true,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);
    }
}
