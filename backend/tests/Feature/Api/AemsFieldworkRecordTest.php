<?php

namespace Tests\Feature\Api;

use App\Models\AemsFieldworkRecord;
use App\Models\AuditArea;
use App\Models\AuditEngagement;
use App\Models\AuditEngagementPlan;
use App\Models\AuditEvidence;
use App\Models\AuditFocus;
use App\Models\AuditProgram;
use App\Models\AuditProgramProcedure;
use App\Models\Document;
use App\Models\EngagementTeam;
use App\Models\IapPlanEngagement;
use App\Models\MasterListItem;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkingPaper;
use App\Models\WorkingPaperVersion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AemsFieldworkRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_fieldwork_record_is_versioned_reviewed_finalized_and_traceable_to_procedure(): void
    {
        [$management, $auditor, $reviewer, $engagement, $program, $procedure, $paper, $evidence, $area, $focus] = $this->fixture();
        $payload = [
            'recordType' => 'TESTING',
            'procedureId' => $procedure->id,
            'auditAreaId' => $area->id,
            'auditFocusId' => $focus->id,
            'performedOn' => '2026-08-14',
            'location' => 'Revenue records room',
            'objective' => 'Test reconciliation controls.',
            'procedurePerformed' => 'Selected transactions and reconciled receipts to deposit records.',
            'populationDescription' => 'All collection transactions in July.',
            'sampleDescription' => 'Twenty-five risk-based selections.',
            'analysis' => 'Compared source records and investigated exceptions.',
            'result' => 'No unexplained differences were identified.',
            'conclusion' => 'The control operated effectively for the selected sample.',
            'executionStatus' => 'COMPLETED',
            'participants' => [
                ['userId' => $auditor->id, 'participantRole' => 'Responsible auditor'],
                ['participantName' => 'Revenue custodian', 'participantRole' => 'Auditee participant'],
            ],
            'workingPaperIds' => [$paper->id],
            'evidenceIds' => [$evidence->id],
            'relatedTasks' => ['Reconcile daily collections'],
            'relatedRecords' => ['Collection register July 2026'],
        ];

        Sanctum::actingAs($auditor);
        $record = $this->postJson("/api/aems/engagements/{$engagement->id}/fieldwork", $payload)
            ->assertCreated()
            ->assertJsonPath('data.fieldworkRecord.status', 'DRAFT')
            ->assertJsonPath('data.fieldworkRecord.latestVersion.executionStatus', 'COMPLETED')
            ->json('data.fieldworkRecord');

        $this->postJson("/api/aems/engagements/{$engagement->id}/fieldwork/{$record['id']}/transition", [
            'action' => 'SUBMIT', 'lockVersion' => $record['lockVersion'],
        ])->assertOk()->assertJsonPath('data.fieldworkRecord.status', 'SUBMITTED');

        Sanctum::actingAs($reviewer);
        $record = $this->getJson("/api/aems/engagements/{$engagement->id}/fieldwork")
            ->assertOk()->json('data.records.0');
        $this->postJson("/api/aems/engagements/{$engagement->id}/fieldwork/{$record['id']}/transition", [
            'action' => 'REVIEW', 'lockVersion' => $record['lockVersion'], 'comment' => 'Execution and traceability reviewed.',
        ])->assertOk()->assertJsonPath('data.fieldworkRecord.reviewedBy.id', $reviewer->id);

        Sanctum::actingAs($management);
        $record = $this->getJson("/api/aems/engagements/{$engagement->id}/fieldwork")->json('data.records.0');
        $finalized = $this->postJson("/api/aems/engagements/{$engagement->id}/fieldwork/{$record['id']}/transition", [
            'action' => 'FINALIZE', 'lockVersion' => $record['lockVersion'], 'comment' => 'Finalized after independent review.',
        ])->assertOk()
            ->assertJsonPath('data.fieldworkRecord.status', 'FINALIZED')
            ->json('data.fieldworkRecord');

        $this->assertDatabaseHas('audit_program_procedures', [
            'id' => $procedure->id,
            'fieldwork_status' => 'COMPLETED',
            'fieldwork_review_state' => 'FINALIZED',
        ]);

        Sanctum::actingAs($auditor);
        $procedure = $procedure->fresh();
        $this->postJson("/api/aems/engagements/{$engagement->id}/programs/{$program->id}/procedures/{$procedure->id}/progress", [
            'programLockVersion' => $program->fresh()->lock_version,
            'lockVersion' => $procedure->lock_version,
            'status' => 'COMPLETED',
            'workingPaperReference' => $paper->working_paper_code,
        ])->assertOk()->assertJsonPath('data.procedure.status', 'COMPLETED');

        $this->assertSame(1, $finalized['currentVersionNumber']);
        $this->assertDatabaseHas('engagement_events', [
            'subject_type' => 'FIELDWORK_RECORD',
            'subject_id' => $record['id'],
            'action' => 'FIELDWORK_FINALIZE',
        ]);
    }

    public function test_completed_procedure_is_blocked_without_a_finalized_fieldwork_record(): void
    {
        [, $auditor, , $engagement, $program, $procedure, $paper] = $this->fixture();
        Sanctum::actingAs($auditor);
        $this->postJson("/api/aems/engagements/{$engagement->id}/programs/{$program->id}/procedures/{$procedure->id}/progress", [
            'programLockVersion' => $program->fresh()->lock_version,
            'lockVersion' => $procedure->fresh()->lock_version,
            'status' => 'COMPLETED',
            'workingPaperReference' => $paper->working_paper_code,
        ])->assertUnprocessable()->assertJsonValidationErrors('fieldwork');
    }

    /** @return array{User, User, User, AuditEngagement, AuditProgram, AuditProgramProcedure, WorkingPaper, AuditEvidence, AuditArea, AuditFocus} */
    private function fixture(): array
    {
        $management = User::query()->where('username', 'departmenthead')->firstOrFail();
        $role = Role::query()->where('code', 'agis_user')->firstOrFail();
        $office = $management->office;
        $auditor = User::factory()->create(['role_id' => $role->id, 'office_id' => $office->id, 'employee_id' => 'FWR-AUD-001']);
        $auditor->syncRoleAssignments([$role->id], $role->id);
        $reviewer = User::factory()->create(['role_id' => $role->id, 'office_id' => $office->id, 'employee_id' => 'FWR-REV-001']);
        $reviewer->syncRoleAssignments([$role->id], $role->id);

        $source = IapPlanEngagement::query()->with('plan')->firstOrFail();
        $source->plan->update(['status' => 'ACTIVE', 'approved_at' => now()->subDay(), 'approved_by' => $management->id, 'activated_at' => now(), 'activated_by' => $management->id]);
        Sanctum::actingAs($management);
        $engagement = AuditEngagement::query()->findOrFail($this->postJson('/api/aems/engagements/import', ['iapPlanEngagementId' => $source->id])->assertCreated()->json('data.engagement.id'));
        $engagement->update(['status' => 'FIELDWORK', 'phase' => 'EXECUTION', 'administrative_status' => 'ACTIVE']);
        $area = AuditArea::query()->where('is_active', true)->firstOrFail();
        $focus = AuditFocus::query()->where('audit_area_id', $area->id)->where('is_active', true)->firstOrFail();
        $engagement->auditAreas()->syncWithoutDetaching([$area->id]);
        $engagement->auditFocuses()->syncWithoutDetaching([$focus->id]);
        EngagementTeam::query()->create(['audit_engagement_id' => $engagement->id, 'user_id' => $auditor->id, 'assignment_role_code' => 'AUDITOR', 'planned_person_days' => 5, 'assigned_from' => '2026-08-01', 'assigned_until' => '2026-08-31', 'assigned_by' => $management->id, 'is_active' => true]);
        EngagementTeam::query()->create(['audit_engagement_id' => $engagement->id, 'user_id' => $reviewer->id, 'assignment_role_code' => 'REVIEWER', 'planned_person_days' => 5, 'assigned_from' => '2026-08-01', 'assigned_until' => '2026-08-31', 'assigned_by' => $management->id, 'is_active' => true]);

        $plan = AuditEngagementPlan::query()->create(['audit_engagement_id' => $engagement->id, 'plan_code' => 'AEP-FWR-'.Str::upper(Str::random(5)), 'status' => 'APPROVED', 'prepared_by' => $management->id, 'approved_by' => $management->id, 'approved_at' => now(), 'is_active' => true]);
        $program = AuditProgram::query()->create(['audit_engagement_id' => $engagement->id, 'audit_engagement_plan_id' => $plan->id, 'program_code' => 'AP-FWR-001', 'title' => 'Fieldwork test program', 'objective' => 'Test fieldwork traceability.', 'status' => 'ACTIVE', 'prepared_by' => $management->id, 'approved_by' => $management->id, 'approved_at' => now(), 'activated_at' => now(), 'is_current_revision' => true, 'is_active' => true]);
        $procedure = AuditProgramProcedure::query()->create(['audit_program_id' => $program->id, 'procedure_code' => 'FWR-01', 'sequence_number' => 1, 'objective' => 'Test reconciliation.', 'procedure_description' => 'Inspect and test collection records.', 'expected_evidence' => 'Collection records and reconciliation.', 'assigned_to' => $auditor->id, 'target_date' => '2026-08-21', 'status' => 'IN_PROGRESS']);
        $paper = WorkingPaper::query()->create(['audit_engagement_id' => $engagement->id, 'audit_program_procedure_id' => $procedure->id, 'working_paper_code' => 'WP-FWR-01', 'title' => 'Reconciliation working paper', 'status' => 'APPROVED', 'current_version_number' => 1, 'prepared_by' => $auditor->id, 'approved_by' => $management->id, 'approved_at' => now(), 'is_active' => true]);
        WorkingPaperVersion::query()->create(['working_paper_id' => $paper->id, 'version_number' => 1, 'objective' => 'Test reconciliation.', 'procedure_performed' => 'Reconciled selected records.', 'result' => 'No exceptions.', 'conclusion' => 'Satisfactory.', 'created_by' => $auditor->id]);
        $documentType = MasterListItem::query()->firstOrFail();
        $document = Document::query()->create(['document_code' => 'DOC-FWR-'.Str::upper(Str::random(5)), 'document_type_id' => $documentType->id, 'title' => 'Fieldwork evidence file', 'original_file_name' => 'fieldwork.txt', 'storage_path' => 'test/fieldwork-'.Str::uuid().'.txt', 'mime_type' => 'text/plain', 'file_extension' => 'txt', 'file_size' => 16, 'checksum_sha256' => hash('sha256', 'fieldwork evidence'), 'uploaded_by' => $auditor->id, 'updated_by' => $auditor->id, 'is_active' => true]);
        $documentVersion = $document->versions()->create(['version_number' => 1, 'version_label' => '1.0', 'change_summary' => 'Initial test evidence.', 'original_file_name' => 'fieldwork.txt', 'storage_path' => 'test/fieldwork-version-'.Str::uuid().'.txt', 'mime_type' => 'text/plain', 'file_extension' => 'txt', 'file_size' => 16, 'checksum_sha256' => hash('sha256', 'fieldwork evidence'), 'uploaded_by' => $auditor->id]);
        $document->update(['current_version_id' => $documentVersion->id]);
        $evidence = AuditEvidence::query()->create(['evidence_family_uuid' => Str::uuid(), 'audit_engagement_id' => $engagement->id, 'evidence_code' => 'EVD-FWR-01', 'title' => 'Reconciliation records', 'source_description' => 'Auditee source records.', 'date_obtained' => '2026-08-14', 'document_version_id' => $documentVersion->id, 'checksum_sha256' => hash('sha256', 'fieldwork evidence'), 'status' => 'VERIFIED', 'uploaded_by' => $auditor->id, 'verified_by' => $reviewer->id, 'verified_at' => now()]);

        return [$management, $auditor, $reviewer, $engagement->fresh(), $program, $procedure, $paper, $evidence, $area, $focus];
    }
}
