<?php

namespace Tests\Feature\Api;

use App\Models\AemsEvidenceAssessment;
use App\Models\AuditEngagement;
use App\Models\AuditEngagementPlan;
use App\Models\AuditEvidence;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\IapPlanEngagement;
use App\Models\MasterList;
use App\Models\MasterListItem;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AemsEvidenceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_evidence_request_lifecycle_and_exact_assessment_versions(): void
    {
        [$management, $auditor, $reviewer, $engagement, $evidence] = $this->fixture();
        Sanctum::actingAs($auditor);
        $request = $this->postJson("/api/aems/engagements/{$engagement->id}/evidence-requests", [
            'title' => 'July reconciliation support',
            'purpose' => 'Obtain and assess the evidence supporting July reconciliation testing.',
            'requestedFromOfficeId' => $auditor->office_id,
            'dueDate' => now()->addWeek()->toDateString(),
            'requestedItems' => ['Signed reconciliation', 'Exception explanation'],
        ])->assertCreated()->assertJsonPath('data.evidenceRequest.status', 'DRAFT')->json('data.evidenceRequest');

        $request = $this->transition($engagement, $request, 'SUBMIT')->assertJsonPath('data.evidenceRequest.status', 'SUBMITTED')->json('data.evidenceRequest');
        Sanctum::actingAs($management);
        $request = $this->transition($engagement, $request, 'SEND')->assertJsonPath('data.evidenceRequest.status', 'SENT')->json('data.evidenceRequest');
        Sanctum::actingAs($auditor);
        $this->postJson("/api/aems/engagements/{$engagement->id}/evidence-requests/{$request['id']}/evidence", [
            'evidenceId' => $evidence->id,
            'documentVersionId' => $evidence->document_version_id,
            'lockVersion' => $request['lockVersion'],
            'receiptNotes' => 'Received from records custodian.',
        ])->assertOk();
        $request = $this->getJson("/api/aems/engagements/{$engagement->id}/evidence-requests")->assertOk()->json('data.requests.0');
        $request = $this->transition($engagement, $request, 'MARK_RECEIVED')->assertJsonPath('data.evidenceRequest.status', 'RECEIVED')->json('data.evidenceRequest');

        Sanctum::actingAs($reviewer);
        $assessment = $this->postJson("/api/aems/engagements/{$engagement->id}/evidence-assessments", [
            'evidenceId' => $evidence->id,
            'evidenceRequestId' => $request['id'],
            'documentVersionId' => $evidence->document_version_id,
            'sufficiency' => 'YES', 'appropriateness' => 'YES', 'relevance' => 'YES',
            'reliability' => 'HIGH', 'competence' => 'HIGH', 'accuracy' => 'YES',
            'completeness' => 'YES', 'corroboration' => 'YES', 'contradiction' => 'NO',
            'authenticity' => 'YES', 'integrity' => 'YES', 'confidentiality' => 'INTERNAL',
        ])->assertCreated()->assertJsonPath('data.assessment.eligibleForFinalizedFinding', true)->json('data.assessment');

        Sanctum::actingAs($reviewer);
        $request = $this->getJson("/api/aems/engagements/{$engagement->id}/evidence-requests")->json('data.requests.0');
        $request = $this->transition($engagement, $request, 'ASSESS')->assertJsonPath('data.evidenceRequest.status', 'ASSESSED')->json('data.evidenceRequest');
        Sanctum::actingAs($management);
        $this->transition($engagement, $request, 'CLOSE', 'All requested evidence was received and assessed.')->assertJsonPath('data.evidenceRequest.status', 'CLOSED');

        $this->assertDatabaseHas('aems_evidence_assessments', ['id' => $assessment['id'], 'document_version_id' => $evidence->document_version_id, 'status' => 'ASSESSED']);
        $this->assertDatabaseHas('engagement_events', ['subject_type' => 'AEMS_EVIDENCE_REQUEST', 'subject_id' => $request['id'], 'action' => 'EVIDENCE_REQUEST_CLOSE']);
    }

    public function test_restricted_assessed_evidence_requires_separate_exception_before_finding_validation(): void
    {
        [$management, $auditor, $reviewer, $engagement, $evidence] = $this->fixture();
        $evidence->update(['assessment_required' => true]);
        Sanctum::actingAs($reviewer);
        $assessment = $this->postJson("/api/aems/engagements/{$engagement->id}/evidence-assessments", [
            'evidenceId' => $evidence->id,
            'documentVersionId' => $evidence->document_version_id,
            'sufficiency' => 'YES', 'appropriateness' => 'YES', 'relevance' => 'YES',
            'reliability' => 'HIGH', 'competence' => 'HIGH', 'accuracy' => 'YES',
            'completeness' => 'YES', 'corroboration' => 'YES', 'contradiction' => 'NO',
            'authenticity' => 'YES', 'integrity' => 'YES', 'confidentiality' => 'INTERNAL',
            'isRestricted' => true,
            'accessRestrictions' => 'Restricted personnel only.',
            'exceptionRequired' => true, 'exceptionReason' => 'The source file contains protected personnel data.',
        ])->assertCreated()->json('data.assessment');
        $this->assertFalse($assessment['eligibleForFinalizedFinding']);
        $initialAssessmentId = $assessment['id'];

        Sanctum::actingAs($management);
        $assessment = $this->postJson("/api/aems/engagements/{$engagement->id}/evidence-assessments/{$assessment['id']}/approve-exception", [
            'lockVersion' => $assessment['lockVersion'],
            'comment' => 'Approved restricted-use exception for the finalized finding.',
        ])->assertOk()->assertJsonPath('data.assessment.eligibleForFinalizedFinding', true)->json('data.assessment');
        $this->assertNotNull($assessment['exceptionApprovedAt']);
        $this->assertDatabaseHas('aems_evidence_assessments', [
            'id' => $assessment['id'],
            'is_current_revision' => true,
            'exception_approved_by' => $management->id,
        ]);
        $this->assertDatabaseHas('aems_evidence_assessments', [
            'id' => $initialAssessmentId,
            'is_current_revision' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'aems.evidence.exception_approved']);
        $this->expectException(\LogicException::class);
        AemsEvidenceAssessment::query()->findOrFail($assessment['id'])->update(['confidentiality' => 'PUBLIC']);
    }

    public function test_evidence_request_versions_are_immutable(): void
    {
        [$management, $auditor, $reviewer, $engagement, $evidence] = $this->fixture();
        Sanctum::actingAs($auditor);
        $request = $this->postJson("/api/aems/engagements/{$engagement->id}/evidence-requests", [
            'title' => 'Immutable request version',
            'purpose' => 'Verify request snapshots cannot be overwritten.',
            'requestedItems' => ['Signed register'],
        ])->assertCreated()->json('data.evidenceRequest');
        $version = \App\Models\AemsEvidenceRequestVersion::query()
            ->where('evidence_request_id', $request['id'])
            ->firstOrFail();

        $this->expectException(\LogicException::class);
        $version->update(['title' => 'Tampered request']);
    }

    public function test_auditee_can_view_sent_request_and_submit_evidence_response_from_cms(): void
    {
        [$management, $auditor, , $engagement] = $this->fixture();
        $auditeeRole = Role::query()->where('code', 'auditee_representative')->firstOrFail();
        $auditee = User::factory()->create([
            'role_id' => $auditeeRole->id,
            'office_id' => $management->office_id,
            'employee_id' => 'ERQ-AUDITEE-001',
        ]);
        $auditee->syncRoleAssignments([$auditeeRole->id], $auditeeRole->id);

        Sanctum::actingAs($auditor);
        $request = $this->postJson("/api/aems/engagements/{$engagement->id}/evidence-requests", [
            'title' => 'CMS evidence response',
            'purpose' => 'Receive the signed records needed for testing.',
            'requestedFromOfficeId' => $auditee->office_id,
            'requestedItems' => ['Signed register'],
        ])->assertCreated()->json('data.evidenceRequest');
        $request = $this->transition($engagement, $request, 'SUBMIT')->assertOk()->json('data.evidenceRequest');

        Sanctum::actingAs($management);
        $request = $this->transition($engagement, $request, 'SEND')->assertOk()->json('data.evidenceRequest');

        Sanctum::actingAs($auditee);
        $this->getJson('/api/cms/evidence-requests')
            ->assertOk()
            ->assertJsonPath('data.requests.0.requestCode', $request['requestCode']);
        $response = $this->post("/api/cms/evidence-requests/{$request['id']}/responses", [
            'lockVersion' => $request['lockVersion'],
            'title' => 'Signed register response',
            'sourceDescription' => 'Signed register supplied through the CMS recipient portal.',
            'dateObtained' => now()->toDateString(),
            'responseNote' => 'Submitted for auditor receipt.',
            'file' => UploadedFile::fake()->create('signed-register.pdf', 20, 'application/pdf'),
        ])->assertCreated()->json('data.response');

        $this->assertDatabaseHas('aems_evidence_request_responses', [
            'evidence_request_id' => $request['id'],
            'audit_evidence_id' => $response['evidenceId'],
            'submitted_by' => $auditee->id,
        ]);
        $this->assertDatabaseHas('audit_evidence', [
            'id' => $response['evidenceId'],
            'evidence_source_type_id' => $this->masterItem('AEMS_EVIDENCE_SOURCE_TYPE', 'AUDITEE'),
            'status' => 'DRAFT',
        ]);
    }

    private function transition(AuditEngagement $engagement, array $request, string $action, ?string $comment = null)
    {
        return $this->postJson("/api/aems/engagements/{$engagement->id}/evidence-requests/{$request['id']}/transition", [
            'action' => $action, 'lockVersion' => $request['lockVersion'], 'comment' => $comment,
        ]);
    }

    /** @return array{User, User, User, AuditEngagement, AuditEvidence} */
    private function fixture(): array
    {
        $management = User::query()->where('username', 'departmenthead')->firstOrFail();
        $role = Role::query()->where('code', 'agis_user')->firstOrFail();
        $auditor = User::factory()->create(['role_id' => $role->id, 'office_id' => $management->office_id, 'employee_id' => 'ERQ-AUD-001']);
        $auditor->syncRoleAssignments([$role->id], $role->id);
        $reviewer = User::factory()->create(['role_id' => $role->id, 'office_id' => $management->office_id, 'employee_id' => 'ERQ-REV-001']);
        $reviewer->syncRoleAssignments([$role->id], $role->id);
        $source = IapPlanEngagement::query()->with('plan')->firstOrFail();
        $source->plan->update(['status' => 'ACTIVE', 'approved_at' => now()->subDay(), 'approved_by' => $management->id, 'activated_at' => now(), 'activated_by' => $management->id]);
        Sanctum::actingAs($management);
        $engagement = AuditEngagement::query()->findOrFail($this->postJson('/api/aems/engagements/import', ['iapPlanEngagementId' => $source->id])->assertCreated()->json('data.engagement.id'));
        $engagement->update(['status' => 'FIELDWORK', 'phase' => 'EXECUTION', 'administrative_status' => 'ACTIVE']);
        // G2 enforces exactly one engagement office; make the fixture explicit
        // instead of appending a second pivot to the imported source office.
        $engagement->offices()->sync([$management->office_id]);
        \App\Models\EngagementTeam::query()->create(['audit_engagement_id' => $engagement->id, 'user_id' => $auditor->id, 'assignment_role_code' => 'AUDITOR', 'assigned_by' => $management->id, 'is_active' => true]);
        \App\Models\EngagementTeam::query()->create(['audit_engagement_id' => $engagement->id, 'user_id' => $reviewer->id, 'assignment_role_code' => 'REVIEWER', 'assigned_by' => $management->id, 'is_active' => true]);
        $plan = AuditEngagementPlan::query()->create(['audit_engagement_id' => $engagement->id, 'plan_code' => 'AEP-ERQ-'.Str::upper(Str::random(5)), 'status' => 'APPROVED', 'prepared_by' => $auditor->id, 'approved_by' => $management->id, 'approved_at' => now(), 'is_active' => true]);
        $program = \App\Models\AuditProgram::query()->create(['audit_engagement_id' => $engagement->id, 'audit_engagement_plan_id' => $plan->id, 'program_code' => 'AP-ERQ-'.Str::upper(Str::random(5)), 'title' => 'Evidence request program', 'objective' => 'Assess evidence request controls.', 'status' => 'ACTIVE', 'is_current_revision' => true, 'is_active' => true, 'prepared_by' => $auditor->id]);
        $document = Document::query()->create(['document_code' => 'DOC-ERQ-'.Str::upper(Str::random(5)), 'document_type_id' => MasterListItem::query()->firstOrFail()->id, 'title' => 'Request evidence', 'original_file_name' => 'request.txt', 'storage_path' => 'test/request-'.Str::uuid().'.txt', 'mime_type' => 'text/plain', 'file_extension' => 'txt', 'file_size' => 10, 'checksum_sha256' => hash('sha256', 'request evidence'), 'uploaded_by' => $auditor->id, 'updated_by' => $auditor->id, 'is_active' => true]);
        $documentVersion = DocumentVersion::query()->create(['document_id' => $document->id, 'version_number' => 1, 'version_label' => 'Evidence version 1', 'change_summary' => 'Initial evidence.', 'original_file_name' => 'request.txt', 'storage_path' => 'test/request-version.txt', 'mime_type' => 'text/plain', 'file_extension' => 'txt', 'file_size' => 10, 'checksum_sha256' => hash('sha256', 'request evidence'), 'uploaded_by' => $auditor->id]);
        $document->update(['current_version_id' => $documentVersion->id]);
        $evidence = AuditEvidence::query()->create(['evidence_family_uuid' => Str::uuid(), 'audit_engagement_id' => $engagement->id, 'evidence_code' => 'EVD-ERQ-01', 'title' => 'Requested reconciliation', 'evidence_category_id' => $this->masterItem('AEMS_EVIDENCE_CATEGORY', 'DOCUMENTARY'), 'evidence_source_type_id' => $this->masterItem('AEMS_EVIDENCE_SOURCE_TYPE', 'AUDITEE'), 'source_description' => 'Records custodian submission.', 'date_obtained' => now()->toDateString(), 'document_version_id' => $documentVersion->id, 'checksum_sha256' => hash('sha256', 'request evidence'), 'status' => 'VERIFIED', 'assessment_required' => false, 'uploaded_by' => $auditor->id, 'verified_by' => $reviewer->id, 'verified_at' => now()]);
        return [$management, $auditor, $reviewer, $engagement->fresh(), $evidence];
    }

    private function masterItem(string $list, string $code): int
    {
        return (int) MasterList::query()->where('code', $list)->firstOrFail()->items()->where('code', $code)->value('id');
    }
}
