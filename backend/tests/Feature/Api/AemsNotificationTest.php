<?php

namespace Tests\Feature\Api;

use App\Models\AuditEngagement;
use App\Models\AuditEngagementPlan;
use App\Models\AuditFinding;
use App\Models\AuditProgram;
use App\Models\AuditProgramProcedure;
use App\Models\EngagementTeam;
use App\Models\ExitConference;
use App\Models\ExitConferenceParticipant;
use App\Models\Office;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\WorkingPaper;
use App\Services\AemsNotificationService;
use App\Services\NotificationReminderService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class AemsNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_required_aems_workflow_events_use_core_notifications(): void
    {
        [$management, $auditor, $auditee, $office, $engagement] = $this->actors();
        EngagementTeam::query()->create([
            'audit_engagement_id' => $engagement->id,
            'user_id' => $auditor->id,
            'assignment_role_code' => 'TEAM_LEADER',
            'assigned_from' => today(),
            'assigned_by' => $management->id,
            'is_active' => true,
        ]);
        $notifications = app(AemsNotificationService::class);

        $notifications->controlledDocumentTransition(
            $this->request($auditor),
            $engagement,
            'AEO',
            91,
            'AEO-NOTIFY-01',
            'Engagement Order',
            'SUBMIT',
            1,
            $auditor->id,
            $auditor->id,
            'aems.aeo.review',
            "/audit-engagement-management/aeo?engagementId={$engagement->id}",
        );

        $paper = WorkingPaper::query()->create([
            'audit_engagement_id' => $engagement->id,
            'working_paper_code' => 'WP-NOTIFY-01',
            'title' => 'Returned Working Paper',
            'status' => 'RETURNED_FOR_REVISION',
            'prepared_by' => $auditor->id,
            'submitted_by' => $auditor->id,
        ]);
        $notifications->workingPaperReturned(
            $this->request($management),
            $engagement,
            $paper,
            2,
            'Clarify the sample basis.',
        );

        $finding = AuditFinding::query()->create([
            'finding_family_uuid' => (string) Str::uuid(),
            'audit_engagement_id' => $engagement->id,
            'finding_code' => 'F-NOTIFY-01',
            'title' => 'Communicated Finding',
            'criteria' => 'Controls are documented.',
            'condition' => 'Documentation was incomplete.',
            'cause' => 'No checklist was used.',
            'effect' => 'Review was delayed.',
            'responsible_office_id' => $office->id,
            'status' => 'COMMUNICATED',
            'authored_by' => $auditor->id,
            'management_response_due_date' => today()->addDays(5),
        ]);
        $notifications->findingCommunicated(
            $this->request($auditor),
            $engagement,
            $finding,
        );

        $conference = ExitConference::query()->create([
            'audit_engagement_id' => $engagement->id,
            'conference_code' => 'EXIT-NOTIFY-01',
            'scheduled_start_at' => now()->addDays(3),
            'agenda' => 'Discuss communicated findings.',
            'status' => 'SCHEDULED',
            'created_by' => $management->id,
        ]);
        ExitConferenceParticipant::query()->create([
            'exit_conference_id' => $conference->id,
            'user_id' => $auditee->id,
            'office_id' => $office->id,
            'participant_role' => 'AUDITEE_REPRESENTATIVE',
            'attendance_status' => 'INVITED',
        ]);
        $notifications->exitConferenceScheduled(
            $this->request($management),
            $engagement,
            $conference,
            'SCHEDULED',
        );

        $notifications->controlledDocumentTransition(
            $this->request($management),
            $engagement,
            'REPORT',
            72,
            'FAR-NOTIFY-01',
            'Final Audit Report',
            'APPROVE',
            3,
            $auditor->id,
            $auditor->id,
            'aems.report.review',
            "/audit-engagement-management/reports?engagementId={$engagement->id}",
        );

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $management->id,
            'type' => 'AEMS_AEO_SUBMIT',
        ]);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $auditor->id,
            'type' => 'AEMS_WORKING_PAPER_RETURNED',
        ]);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $auditee->id,
            'type' => 'AEMS_FINDING_COMMUNICATED',
        ]);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $auditee->id,
            'type' => 'AEMS_EXIT_CONFERENCE_SCHEDULED',
        ]);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $auditor->id,
            'type' => 'AEMS_REPORT_APPROVE',
        ]);
    }

    public function test_aems_deadline_reminders_are_complete_and_idempotent(): void
    {
        [$management, $auditor, $auditee, $office, $engagement] = $this->actors();
        EngagementTeam::query()->create([
            'audit_engagement_id' => $engagement->id,
            'user_id' => $management->id,
            'assignment_role_code' => 'SUPERVISOR',
            'assigned_from' => today(),
            'assigned_by' => $management->id,
            'is_active' => true,
        ]);
        $plan = AuditEngagementPlan::query()->create([
            'audit_engagement_id' => $engagement->id,
            'plan_code' => 'AEP-NOTIFY-01',
            'status' => 'APPROVED',
            'prepared_by' => $auditor->id,
        ]);
        $program = AuditProgram::query()->create([
            'audit_engagement_id' => $engagement->id,
            'audit_engagement_plan_id' => $plan->id,
            'program_code' => 'AP-NOTIFY-01',
            'title' => 'Notification Program',
            'objective' => 'Test overdue notifications.',
            'status' => 'ACTIVE',
            'is_current_revision' => true,
            'prepared_by' => $auditor->id,
        ]);
        AuditProgramProcedure::query()->create([
            'audit_program_id' => $program->id,
            'procedure_code' => 'PROC-NOTIFY-01',
            'objective' => 'Test timeliness.',
            'procedure_description' => 'Complete the notification procedure.',
            'assigned_to' => $auditor->id,
            'target_date' => today()->subDay(),
            'status' => 'IN_PROGRESS',
        ]);
        AuditFinding::query()->create([
            'finding_family_uuid' => (string) Str::uuid(),
            'audit_engagement_id' => $engagement->id,
            'finding_code' => 'F-DUE-01',
            'title' => 'Response Deadline Finding',
            'criteria' => 'Responses are timely.',
            'condition' => 'The response remains outstanding.',
            'cause' => 'Delayed coordination.',
            'effect' => 'Dialogue is delayed.',
            'responsible_office_id' => $office->id,
            'status' => 'AWAITING_MANAGEMENT_RESPONSE',
            'authored_by' => $auditor->id,
            'management_response_due_date' => today()->subDay(),
        ]);
        $conference = ExitConference::query()->create([
            'audit_engagement_id' => $engagement->id,
            'conference_code' => 'EXIT-DUE-01',
            'scheduled_start_at' => now()->addDays(2),
            'agenda' => 'Discuss findings.',
            'status' => 'SCHEDULED',
            'created_by' => $management->id,
        ]);
        ExitConferenceParticipant::query()->create([
            'exit_conference_id' => $conference->id,
            'user_id' => $auditee->id,
            'office_id' => $office->id,
            'participant_role' => 'AUDITEE_REPRESENTATIVE',
            'attendance_status' => 'INVITED',
        ]);

        $reminders = app(NotificationReminderService::class);
        $reminders->dispatch();
        $reminders->dispatch();

        foreach ([
            'AEMS_PROCEDURE_OVERDUE',
            'AEMS_MANAGEMENT_RESPONSE_OVERDUE',
            'AEMS_EXIT_CONFERENCE_REMINDER',
        ] as $type) {
            $this->assertSame(
                1,
                SystemNotification::query()->where('type', $type)
                    ->where('recipient_id', $type === 'AEMS_PROCEDURE_OVERDUE'
                        ? $auditor->id
                        : $auditee->id)
                    ->count(),
                "{$type} should remain deduplicated.",
            );
        }
    }

    /** @return array{User, User, User, Office, AuditEngagement} */
    private function actors(): array
    {
        $management = User::query()->where('username', 'departmenthead')->firstOrFail();
        $auditor = User::query()->where('username', 'auditor')->firstOrFail();
        $auditee = User::query()
            ->whereHas('roles', fn ($roles) => $roles->where('code', 'auditee_representative'))
            ->firstOrFail();
        $office = $auditee->office;
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => 'AEMS-NOTIFY-01',
            'title' => 'Notification Coverage Engagement',
            'source_type' => 'SPECIAL',
            'special_authority_reference' => 'AUTH-NOTIFY-01',
            'special_authority_date' => today(),
            'special_authority_approved_by' => $management->id,
            'objectives' => 'Verify notification coverage.',
            'scope' => 'AEMS workflow events and deadlines.',
            'planned_start_date' => today()->subWeek(),
            'planned_end_date' => today()->addMonth(),
            'status' => 'FIELDWORK',
            'created_by' => $management->id,
            'updated_by' => $management->id,
            'is_active' => true,
        ]);
        $engagement->offices()->attach($office->id, ['is_primary' => true]);

        return [$management, $auditor, $auditee, $office, $engagement];
    }

    private function request(User $actor): Request
    {
        $request = Request::create('/api/aems/test-notification', 'POST');
        $request->setUserResolver(fn (): User => $actor);

        return $request;
    }
}
