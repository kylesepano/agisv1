<?php

namespace Tests\Feature\Api;

use App\Models\AemsDialogueDueProcess;
use App\Models\AemsEngagementTask;
use App\Models\AemsEscalationCandidate;
use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\EngagementEvent;
use App\Models\EngagementTeam;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AemsWorkQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_tasks_record_versioned_exchanges_due_state_and_optimistic_locking(): void
    {
        [$management, $auditor, , $engagement] = $this->context();
        Sanctum::actingAs($auditor);
        $task = $this->postJson("/api/aems/engagements/{$engagement->id}/tasks", [
            'taskType' => 'REVIEW_NOTE',
            'title' => 'Review the conference minutes',
            'description' => 'Confirm all agreements are accurately recorded.',
            'assignedTo' => $management->id,
            'dueAt' => now()->addDay()->toISOString(),
        ])->assertCreated()->assertJsonPath('data.task.status', 'OPEN')->json('data.task');

        Sanctum::actingAs($management);
        $task = $this->postJson("/api/aems/engagements/{$engagement->id}/tasks/{$task['id']}/transition", [
            'action' => 'START', 'lockVersion' => $task['lockVersion'],
        ])->assertOk()->assertJsonPath('data.task.status', 'IN_PROGRESS')->json('data.task');
        $this->postJson("/api/aems/engagements/{$engagement->id}/tasks/{$task['id']}/transition", [
            'action' => 'COMPLETE', 'lockVersion' => $task['lockVersion'], 'comment' => 'Minutes reviewed and accepted.',
        ])->assertOk()->assertJsonPath('data.task.status', 'COMPLETED');
        $this->postJson("/api/aems/engagements/{$engagement->id}/tasks/{$task['id']}/transition", [
            'action' => 'REOPEN', 'lockVersion' => 3, 'comment' => 'A revised conference exchange needs review.',
        ])->assertOk()->assertJsonPath('data.task.status', 'OPEN');
        $this->postJson("/api/aems/engagements/{$engagement->id}/tasks/{$task['id']}/transition", [
            'action' => 'START', 'lockVersion' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('lockVersion');

        $this->assertDatabaseCount('aems_engagement_task_events', 4);
        $this->assertDatabaseHas('engagement_events', ['subject_type' => 'AEMS_TASK', 'subject_id' => $task['id']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'aems.task.created']);
    }

    public function test_review_notes_require_independent_finalization_and_due_process_creates_reviewable_candidate(): void
    {
        [$management, $auditor, , $engagement, $finding] = $this->context();
        Sanctum::actingAs($auditor);
        $note = $this->postJson("/api/aems/engagements/{$engagement->id}/review-notes", [
            'findingId' => $finding->id,
            'noteType' => 'QUALITY_REVIEW',
            'content' => 'The communicated finding is supported by the linked work papers.',
        ])->assertCreated()->json('data.reviewNote');
        $this->postJson("/api/aems/engagements/{$engagement->id}/review-notes/{$note['id']}/transition", [
            'action' => 'FINALIZE', 'lockVersion' => $note['lockVersion'],
        ])->assertForbidden();

        Sanctum::actingAs($management);
        $this->postJson("/api/aems/engagements/{$engagement->id}/review-notes/{$note['id']}/transition", [
            'action' => 'FINALIZE', 'lockVersion' => $note['lockVersion'],
        ])->assertOk()->assertJsonPath('data.reviewNote.status', 'FINALIZED');

        $due = $this->postJson("/api/aems/engagements/{$engagement->id}/findings/{$finding->id}/transition", [
            'action' => 'RECORD_NON_RESPONSE',
            'lockVersion' => $finding->lock_version,
            'comment' => 'No response was received by the formally communicated deadline.',
        ])->assertOk()->assertJsonPath('data.finding.status', 'UNDER_DIALOGUE')->json('data.finding.dueProcess.0');

        $this->assertDatabaseHas('aems_dialogue_due_process', ['id' => $due['id'], 'actor_id' => $management->id]);
        $candidate = AemsEscalationCandidate::query()->where('audit_engagement_id', $engagement->id)->firstOrFail();
        $this->assertSame('OPEN', $candidate->status);
        $candidateData = $this->postJson("/api/aems/engagements/{$engagement->id}/escalation-candidates/{$candidate->id}/review", [
            'action' => 'ACKNOWLEDGE', 'lockVersion' => $candidate->lock_version, 'comment' => 'Candidate accepted for supervisory review.',
        ])->assertOk()->json('data.candidate');
        $this->assertSame('ACKNOWLEDGED', $candidateData['status']);
        $this->assertDatabaseHas('engagement_events', ['subject_type' => 'AEMS_DUE_PROCESS', 'subject_id' => $due['id']]);
    }

    /** @return array{User, User, User, AuditEngagement, AuditFinding} */
    private function context(): array
    {
        $management = User::query()->where('username', 'departmenthead')->firstOrFail();
        $auditor = User::query()->where('username', 'auditor')->firstOrFail();
        $auditee = User::query()->where('username', 'auditee')->firstOrFail();
        $office = Office::query()->findOrFail($auditee->office_id);
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => 'AEMS-WQ-'.Str::upper(Str::random(5)), 'title' => 'AEMS Work Queue Test',
            'source_type' => 'SPECIAL', 'special_authority_reference' => 'AUTH-WQ-001', 'special_authority_date' => today(),
            'special_authority_approved_by' => $management->id, 'objectives' => 'Test work queue controls.', 'scope' => 'AEMS exchange traceability.',
            'planned_start_date' => today()->subDay(), 'planned_end_date' => today()->addMonth(), 'status' => 'FIELDWORK',
            'created_by' => $management->id, 'updated_by' => $management->id, 'is_active' => true,
        ]);
        $engagement->offices()->attach($office->id, ['is_primary' => true]);
        EngagementTeam::query()->create(['audit_engagement_id' => $engagement->id, 'user_id' => $auditor->id, 'assignment_role_code' => 'AUDITOR', 'assigned_by' => $management->id, 'assigned_from' => today(), 'is_active' => true]);
        $finding = AuditFinding::query()->create([
            'finding_family_uuid' => (string) Str::uuid(), 'audit_engagement_id' => $engagement->id, 'finding_code' => 'F-WQ-001',
            'title' => 'Management response is outstanding', 'criteria' => 'Responses are due on time.', 'condition' => 'No response was received.',
            'cause' => 'The office did not submit a response.', 'effect' => 'Corrective action cannot be assessed.', 'responsible_office_id' => $office->id,
            'status' => 'AWAITING_MANAGEMENT_RESPONSE', 'authored_by' => $auditor->id, 'management_response_due_date' => today()->subDay(), 'lock_version' => 1,
        ]);
        return [$management, $auditor, $auditee, $engagement, $finding];
    }
}
