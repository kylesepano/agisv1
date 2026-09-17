<?php

namespace Tests\Feature\Api;

use App\Models\AemsFieldworkRecord;
use App\Models\AemsFieldworkRecordVersion;
use App\Models\AemsEvidenceAssessment;
use App\Models\AuditEngagement;
use App\Models\AuditEvidence;
use App\Models\AuditEngagementPlan;
use App\Models\AuditProgram;
use App\Models\AuditProgramProcedure;
use App\Models\AuditRecommendation;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\EngagementTeam;
use App\Models\MasterList;
use App\Models\User;
use App\Models\WorkingPaper;
use App\Models\WorkingPaperVersion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AemsIssueFindingRecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_issue_is_independently_validated_and_converts_idempotently(): void
    {
        [$management, $auditor, $auditee, $engagement, $version, $evidence] = $this->supportedEngagement();
        Sanctum::actingAs($auditor);
        $issue = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/issues",
            $this->issuePayload($auditee, $version, $evidence),
        )->assertCreated()
            ->assertJsonPath('data.issue.status', 'DRAFT')
            ->json('data.issue');

        $issue = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/issues/{$issue['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $issue['lockVersion']],
        )->assertOk()
            ->assertJsonPath('data.issue.status', 'SUBMITTED')
            ->json('data.issue');

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/issues/{$issue['id']}/transition",
            ['action' => 'VALIDATE', 'lockVersion' => $issue['lockVersion']],
        )->assertForbidden();

        Sanctum::actingAs($management);
        $issue = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/issues/{$issue['id']}/transition",
            ['action' => 'VALIDATE', 'lockVersion' => $issue['lockVersion']],
        )->assertOk()
            ->assertJsonPath('data.issue.status', 'VALIDATED')
            ->json('data.issue');
        $finding = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/issues/{$issue['id']}/transition",
            ['action' => 'CONVERT', 'lockVersion' => $issue['lockVersion']],
        )->assertOk()
            ->assertJsonPath('data.finding.status', 'DRAFT')
            ->assertJsonPath('data.finding.sourceIssueId', $issue['id'])
            ->json('data.finding');

        $lockedIssue = \App\Models\AuditIssue::query()->findOrFail($issue['id']);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/issues/{$issue['id']}/transition",
            ['action' => 'CONVERT', 'lockVersion' => $lockedIssue->lock_version],
        )->assertOk()->assertJsonPath('data.finding.id', $finding['id']);
        $this->assertDatabaseCount('audit_findings', 1);
    }

    public function test_finding_dialogue_finalizes_and_locks_recommendations(): void
    {
        [$management, $auditor, $auditee, $engagement, $version, $evidence] = $this->supportedEngagement();
        Sanctum::actingAs($auditor);
        $finding = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/findings",
            $this->findingPayload($auditee, $version, $evidence),
        )->assertCreated()
            ->assertJsonPath('data.finding.status', 'DRAFT')
            ->json('data.finding');

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/findings/{$finding['id']}/recommendations",
            [
                'recommendation' => 'Require a signed supervisory reconciliation before posting each daily collection batch.',
                'responsibleOfficeId' => $auditee->office_id,
                'targetImplementationDate' => now()->addMonths(2)->toDateString(),
                'findingLockVersion' => $finding['lockVersion'],
            ],
        )->assertCreated()->assertJsonPath('data.recommendation.status', 'DRAFT');
        $finding = $this->finding($engagement, $finding['id']);

        $finding = $this->findingTransition($engagement, $finding, 'SUBMIT')
            ->assertJsonPath('data.finding.status', 'PENDING_REVIEW')
            ->json('data.finding');
        Sanctum::actingAs($management);
        $finding = $this->findingTransition($engagement, $finding, 'VALIDATE')
            ->assertJsonPath('data.finding.status', 'VALIDATED')
            ->assertJsonPath('data.finding.evidence.0.status', 'LOCKED')
            ->json('data.finding');
        $finding = $this->findingTransition($engagement, $finding, 'COMMUNICATE', [
            'recipients' => ['Records Custodian', 'Office Director'],
            'dueDate' => now()->addWeeks(2)->toDateString(),
            'confidentiality' => 'INTERNAL',
        ])->assertJsonPath('data.finding.status', 'COMMUNICATED')
            ->json('data.finding');
        $finding = $this->findingTransition($engagement, $finding, 'REQUEST_RESPONSE')
            ->assertJsonPath('data.finding.status', 'AWAITING_MANAGEMENT_RESPONSE')
            ->json('data.finding');

        Sanctum::actingAs($auditee);
        $this->getJson('/api/aems/findings-workspaces')
            ->assertOk()
            ->assertJsonPath('data.engagements.0.id', $engagement->id);
        $this->getJson(
            "/api/aems/engagements/{$engagement->id}/findings-workspace",
        )->assertOk()
            ->assertJsonCount(0, 'data.issues')
            ->assertJsonCount(1, 'data.findings');
        $response = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/findings/{$finding['id']}/responses",
            [
                'agreementPosition' => 'PARTIALLY_AGREE',
                'managementComment' => 'The office accepts the exception and requests clarification on the implementation scope.',
                'proposedAction' => 'Add supervisory sign-off to the daily reconciliation checklist.',
                'responsibleUserId' => $auditee->id,
                'proposedTargetDate' => now()->addMonths(2)->toDateString(),
                'findingLockVersion' => $finding['lockVersion'],
            ],
        )->assertCreated()->json('data.response');
        $responseAttachment = $this->post(
            "/api/aems/engagements/{$engagement->id}/findings/{$finding['id']}/responses/{$response['id']}/attachments",
            [
                'caption' => 'Approved corrective-action memorandum',
                'lockVersion' => $response['lockVersion'],
                'file' => UploadedFile::fake()->createWithContent(
                    'corrective-action.pdf',
                    'signed management corrective action',
                ),
            ],
        )->assertCreated()
            ->assertJsonPath('data.attachment.fileVersionNumber', 1)
            ->assertJsonPath('data.attachment.uploadedBy.id', $auditee->id)
            ->json('data.attachment');
        $finding = $this->finding($engagement, $finding['id']);
        $response = collect($finding['managementResponses'])
            ->firstWhere('isCurrentRevision', true);
        $this->assertCount(1, $response['attachments']);
        $this->assertNotNull($response['attachments'][0]['uploadedAt']);
        $response = $this->responseTransition($engagement, $finding, $response, 'SUBMIT')
            ->assertJsonPath('data.response.status', 'SUBMITTED')
            ->json('data.response');

        Sanctum::actingAs($auditor);
        $response = $this->responseTransition($engagement, $finding, $response, 'START_REVIEW')
            ->assertJsonPath('data.response.status', 'UNDER_AUDITOR_REVIEW')
            ->json('data.response');
        $response = $this->responseTransition(
            $engagement,
            $finding,
            $response,
            'REQUEST_CLARIFICATION',
            'Identify the accountable supervisory position and confirm the target date.',
        )->assertJsonPath('data.response.status', 'CLARIFICATION_REQUESTED')
            ->json('data.response');

        Sanctum::actingAs($auditee);
        $response = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/findings/{$finding['id']}/responses/{$response['id']}/revisions",
            ['lockVersion' => $response['lockVersion']],
        )->assertCreated()
            ->assertJsonPath('data.response.versionNumber', 2)
            ->json('data.response');
        $response = $this->putJson(
            "/api/aems/engagements/{$engagement->id}/findings/{$finding['id']}/responses/{$response['id']}",
            [
                'agreementPosition' => 'AGREE',
                'managementComment' => 'The Records Supervisor will own the corrective action.',
                'proposedAction' => 'Require Records Supervisor sign-off before daily posting.',
                'responsibleUserId' => $auditee->id,
                'proposedTargetDate' => now()->addMonths(2)->toDateString(),
                'lockVersion' => $response['lockVersion'],
            ],
        )->assertOk()->json('data.response');
        $response = $this->responseTransition($engagement, $finding, $response, 'SUBMIT')
            ->assertJsonPath('data.response.status', 'RESUBMITTED')
            ->json('data.response');

        Sanctum::actingAs($auditor);
        $response = $this->responseTransition($engagement, $finding, $response, 'START_REVIEW')
            ->json('data.response');
        $rejoinder = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/findings/{$finding['id']}/responses/{$response['id']}/rejoinders",
            [
                'disposition' => 'ACCEPT',
                'rejoinder' => 'The accountable position, corrective action, and target date are acceptable.',
                'responseLockVersion' => $response['lockVersion'],
            ],
        )->assertCreated()->json('data.rejoinder');
        $rejoinderAttachment = $this->post(
            "/api/aems/engagements/{$engagement->id}/findings/{$finding['id']}/responses/{$response['id']}/rejoinders/{$rejoinder['id']}/attachments",
            [
                'caption' => 'Auditor assessment of the proposed control',
                'lockVersion' => $rejoinder['lockVersion'],
                'file' => UploadedFile::fake()->createWithContent(
                    'auditor-assessment.pdf',
                    'auditor assessment and disposition support',
                ),
            ],
        )->assertCreated()
            ->assertJsonPath('data.attachment.fileVersionNumber', 1)
            ->assertJsonPath('data.attachment.uploadedBy.id', $auditor->id)
            ->json('data.attachment');
        $finding = $this->finding($engagement, $finding['id']);
        $response = collect($finding['managementResponses'])
            ->firstWhere('isCurrentRevision', true);
        $rejoinder = collect($response['rejoinders'])->firstWhere(
            'id',
            $rejoinder['id'],
        );

        Sanctum::actingAs($management);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/findings/{$finding['id']}/responses/{$response['id']}/rejoinders/{$rejoinder['id']}/finalize",
            [
                'responseLockVersion' => $response['lockVersion'],
                'lockVersion' => $rejoinder['lockVersion'],
            ],
        )->assertOk();
        $this->get(
            "/api/aems/engagements/{$engagement->id}/findings/{$finding['id']}/dialogue-attachments/{$responseAttachment['id']}/download",
        )->assertOk();
        $this->get(
            "/api/aems/engagements/{$engagement->id}/findings/{$finding['id']}/dialogue-attachments/{$rejoinderAttachment['id']}/download",
        )->assertOk();
        $finding = $this->finding($engagement, $finding['id']);
        $finding = $this->findingTransition($engagement, $finding, 'FINALIZE')
            ->assertJsonPath('data.finding.status', 'FINALIZED')
            ->assertJsonPath('data.finding.recommendations.0.status', 'FINALIZED')
            ->json('data.finding');

        $recommendation = AuditRecommendation::query()->firstOrFail();
        $this->expectException(LogicException::class);
        $recommendation->update(['recommendation' => 'Historical finalized text cannot be overwritten.']);
    }

    public function test_direct_finding_requires_authority_and_conclusion(): void
    {
        [$management, $auditor, $auditee, $engagement, $version, $evidence] = $this->supportedEngagement();
        Sanctum::actingAs($auditor);
        $payload = $this->findingPayload($auditee, $version, $evidence);
        unset($payload['directAuthorityReason'], $payload['directAuthorityReference']);

        $this->postJson("/api/aems/engagements/{$engagement->id}/findings", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('directAuthorityReason');

        $withoutConclusion = [
            ...$payload,
            'directAuthorityReason' => 'URGENT_OR_MATERIAL_RISK',
            'directAuthorityReference' => 'Risk committee directive AEMS-G1-001.',
        ];
        unset($withoutConclusion['conclusion']);
        $this->postJson("/api/aems/engagements/{$engagement->id}/findings", $withoutConclusion)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conclusion');

        $finding = $this->postJson("/api/aems/engagements/{$engagement->id}/findings", [
            ...$payload,
            'directAuthorityReason' => 'URGENT_OR_MATERIAL_RISK',
            'directAuthorityReference' => 'Risk committee directive AEMS-G1-001.',
        ])->assertCreated()->json('data.finding');

        $this->assertSame('URGENT_OR_MATERIAL_RISK', $finding['directCreationReason']);
        $this->assertSame('Risk committee directive AEMS-G1-001.', $finding['directCreationAuthority']);
        $this->assertDatabaseHas('audit_findings', [
            'id' => $finding['id'],
            'direct_creation_by' => $auditor->id,
        ]);
    }

    public function test_finding_author_cannot_validate_and_revision_preserves_immutable_snapshot(): void
    {
        [$management, $auditor, $auditee, $engagement, $version, $evidence] = $this->supportedEngagement();
        Sanctum::actingAs($auditor);
        $finding = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/findings",
            [...$this->findingPayload($auditee, $version, $evidence), 'noRecommendationReason' => 'Tracked as a control correction with no separate recommendation.'],
        )->assertCreated()->json('data.finding');
        $finding = $this->findingTransition($engagement, $finding, 'SUBMIT')
            ->assertJsonPath('data.finding.status', 'PENDING_REVIEW')->json('data.finding');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/findings/{$finding['id']}/transition",
            ['action' => 'VALIDATE', 'lockVersion' => $finding['lockVersion']],
        )->assertForbidden();

        Sanctum::actingAs($management);
        $revision = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/findings/{$finding['id']}/revisions",
            [
                'action' => 'AMEND',
                'reason' => 'Update the effect classification after supervisory review.',
                'lockVersion' => $finding['lockVersion'],
            ],
        )->assertCreated()
            ->assertJsonPath('data.finding.revisionNumber', 1)
            ->assertJsonPath('data.finding.revisionType', 'AMENDMENT')
            ->assertJsonPath('data.finding.status', 'DRAFT')
            ->json('data.finding');
        $this->assertNotNull($revision['revisionSnapshot']);
        $this->assertDatabaseHas('audit_findings', [
            'id' => $finding['id'],
            'is_current_revision' => false,
        ]);

        $withdrawn = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/findings/{$revision['id']}/revisions",
            [
                'action' => 'WITHDRAW',
                'reason' => 'Withdraw the amended finding because the exception was corrected during audit.',
                'lockVersion' => $revision['lockVersion'],
            ],
        )->assertCreated()
            ->assertJsonPath('data.finding.status', 'WITHDRAWN')
            ->assertJsonPath('data.finding.revisionType', 'WITHDRAWAL')
            ->json('data.finding');
        $this->assertNotNull($withdrawn['withdrawnAt']);
    }

    public function test_issue_disposition_metadata_supports_non_finding_resolution(): void
    {
        [$management, $auditor, $auditee, $engagement, $version, $evidence] = $this->supportedEngagement();
        Sanctum::actingAs($auditor);
        $issue = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/issues",
            $this->issuePayload($auditee, $version, $evidence),
        )->assertCreated()->json('data.issue');
        $issue = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/issues/{$issue['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $issue['lockVersion']],
        )->assertOk()->json('data.issue');
        Sanctum::actingAs($management);
        $issue = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/issues/{$issue['id']}/transition",
            ['action' => 'VALIDATE', 'lockVersion' => $issue['lockVersion']],
        )->assertOk()->json('data.issue');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/issues/{$issue['id']}/transition",
            [
                'action' => 'RESOLVE',
                'lockVersion' => $issue['lockVersion'],
                'comment' => 'Corrected and verified during fieldwork.',
                'resolutionDetails' => 'The office implemented the control before the closing meeting.',
            ],
        )->assertOk()
            ->assertJsonPath('data.issue.status', 'DISMISSED')
            ->assertJsonPath('data.issue.disposition', 'RESOLVED_DURING_AUDIT')
            ->assertJsonPath('data.issue.resolutionDetails', 'The office implemented the control before the closing meeting.');
    }

    public function test_issue_withdrawal_is_independent_and_terminal(): void
    {
        [$management, $auditor, $auditee, $engagement, $version, $evidence] = $this->supportedEngagement();
        Sanctum::actingAs($auditor);
        $issue = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/issues",
            $this->issuePayload($auditee, $version, $evidence),
        )->assertCreated()->json('data.issue');

        $issue = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/issues/{$issue['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $issue['lockVersion']],
        )->assertOk()->json('data.issue');

        Sanctum::actingAs($management);
        $withdrawn = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/issues/{$issue['id']}/transition",
            [
                'action' => 'WITHDRAW',
                'lockVersion' => $issue['lockVersion'],
                'comment' => 'Duplicate issue withdrawn after supervisory review.',
            ],
        )->assertOk()
            ->assertJsonPath('data.issue.status', 'WITHDRAWN')
            ->assertJsonPath('data.issue.statusCompatibility.canonical', 'WITHDRAWN')
            ->assertJsonPath('data.issue.statusCompatibility.terminal', true)
            ->json('data.issue');

        $this->assertSame('WITHDRAWN', $withdrawn['disposition']);
        $this->assertNotNull($withdrawn['withdrawnAt']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'issue.withdraw']);
    }

    public function test_finding_can_pin_exact_finalized_fieldwork_record_version(): void
    {
        [$management, $auditor, $auditee, $engagement, $version, $evidence] = $this->supportedEngagement();
        $procedure = AuditProgramProcedure::query()->firstOrFail();
        $record = AemsFieldworkRecord::query()->create([
            'record_family_uuid' => (string) Str::uuid(),
            'audit_engagement_id' => $engagement->id,
            'audit_program_procedure_id' => $procedure->id,
            'record_code' => 'FWR-AEMS-FND-001-001',
            'record_type' => 'TESTING',
            'status' => 'FINALIZED',
            'prepared_by' => $auditor->id,
            'finalized_by' => $management->id,
            'finalized_at' => now(),
            'lock_version' => 1,
            'is_active' => true,
        ]);
        $recordVersion = AemsFieldworkRecordVersion::query()->create([
            'fieldwork_record_id' => $record->id,
            'version_number' => 1,
            'record_type' => 'TESTING',
            'audit_program_procedure_id' => $procedure->id,
            'performed_on' => now()->toDateString(),
            'procedure_performed' => 'Inspected the daily reconciliation control.',
            'result' => 'Control operated as designed.',
            'conclusion' => 'No exception identified.',
            'execution_status' => 'COMPLETED',
            'created_by' => $auditor->id,
        ]);
        Sanctum::actingAs($auditor);
        $finding = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/findings",
            [
                ...$this->findingPayload($auditee, $version, $evidence),
                'noRecommendationReason' => 'No separate recommendation is required for this observation.',
                'fieldworkRecordVersionIds' => [$recordVersion->id],
            ],
        )->assertCreated()
            ->assertJsonPath('data.finding.fieldworkRecords.0.versionId', $recordVersion->id)
            ->json('data.finding');
        $finding = $this->findingTransition($engagement, $finding, 'SUBMIT')
            ->assertJsonPath('data.finding.status', 'PENDING_REVIEW')->json('data.finding');
        Sanctum::actingAs($management);
        $this->findingTransition($engagement, $finding, 'VALIDATE')
            ->assertJsonPath('data.finding.status', 'VALIDATED');
    }

    public function test_finding_exposes_explicit_procedure_criteria_traceability(): void
    {
        [$management, $auditor, $auditee, $engagement, $version, $evidence] = $this->supportedEngagement();
        $procedure = AuditProgramProcedure::query()->firstOrFail();

        Sanctum::actingAs($auditor);
        $finding = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/findings",
            [
                ...$this->findingPayload($auditee, $version, $evidence),
                'noRecommendationReason' => 'The existing control owner will address this through monitoring.',
            ],
        )->assertCreated()
            ->assertJsonPath('data.finding.procedures.0.id', $procedure->id)
            ->assertJsonPath('data.finding.procedures.0.procedureCode', $procedure->procedure_code)
            ->json('data.finding');

        $this->assertDatabaseHas('audit_finding_procedure', [
            'audit_finding_id' => $finding['id'],
            'audit_program_procedure_id' => $procedure->id,
        ]);
    }

    public function test_extended_fieldwork_record_taxonomy_is_available_to_the_api_contract(): void
    {
        $this->assertContains('INQUIRY', AemsFieldworkRecord::TYPES);
        $this->assertContains('MEETING', AemsFieldworkRecord::TYPES);
        $this->assertContains('FIELD_NOTE', AemsFieldworkRecord::TYPES);
        $this->assertContains('OTHER', AemsFieldworkRecord::TYPES);
    }

    public function test_finding_rejects_an_unscoped_procedure_traceability_link(): void
    {
        [, $auditor, $auditee, $engagement, $version, $evidence] = $this->supportedEngagement();

        Sanctum::actingAs($auditor);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/findings",
            [
                ...$this->findingPayload($auditee, $version, $evidence),
                'noRecommendationReason' => 'No separate recommendation is required.',
                'procedureIds' => [999999],
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('procedureIds');
    }

    /** @return array{User, User, User, AuditEngagement, WorkingPaperVersion, AuditEvidence} */
    private function supportedEngagement(): array
    {
        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $auditee = $this->user('auditee');
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => 'AEMS-FND-001',
            'title' => 'Issue and Finding Controls Test',
            'source_type' => 'SPECIAL',
            'special_authority_reference' => 'AUTH-FND-001',
            'special_authority_date' => now()->toDateString(),
            'special_authority_approved_by' => $management->id,
            'objectives' => 'Test issue and finding controls.',
            'scope' => 'Daily collection reconciliation.',
            'status' => 'FINDINGS_COMMUNICATION',
            'created_by' => $management->id,
            'updated_by' => $management->id,
        ]);
        $engagement->offices()->attach($auditee->office_id, ['is_primary' => true]);
        EngagementTeam::query()->create([
            'audit_engagement_id' => $engagement->id,
            'user_id' => $auditor->id,
            'assignment_role_code' => 'AUDITOR',
            'assigned_by' => $management->id,
            'is_active' => true,
        ]);
        $plan = AuditEngagementPlan::query()->create([
            'audit_engagement_id' => $engagement->id,
            'plan_code' => 'AEP-FND-001',
            'status' => 'APPROVED',
            'current_version_number' => 1,
            'prepared_by' => $auditor->id,
            'approved_by' => $management->id,
            'approved_at' => now(),
            'is_active' => true,
        ]);
        $program = AuditProgram::query()->create([
            'audit_engagement_id' => $engagement->id,
            'audit_engagement_plan_id' => $plan->id,
            'program_code' => 'AP-FND-001',
            'title' => 'Collection Controls Program',
            'objective' => 'Test daily reconciliation.',
            'status' => 'COMPLETED',
            'revision_number' => 0,
            'is_current_revision' => true,
            'prepared_by' => $auditor->id,
            'approved_by' => $management->id,
            'completed_at' => now(),
            'is_active' => true,
        ]);
        $procedure = AuditProgramProcedure::query()->create([
            'audit_program_id' => $program->id,
            'procedure_code' => 'COL-01',
            'sequence_number' => 1,
            'objective' => 'Test daily reconciliation.',
            'procedure_description' => 'Inspect reconciliation and posting support.',
            'expected_evidence' => 'Registers and sign-off records.',
            'assigned_to' => $auditor->id,
            'status' => 'COMPLETED',
        ]);
        $paper = WorkingPaper::query()->create([
            'audit_engagement_id' => $engagement->id,
            'audit_program_procedure_id' => $procedure->id,
            'working_paper_code' => 'WP-AEMS-FND-001-001',
            'title' => 'Daily Collection Reconciliation',
            'status' => 'APPROVED',
            'current_version_number' => 1,
            'prepared_by' => $auditor->id,
            'reviewer_id' => $management->id,
            'reviewed_at' => now(),
            'approved_at' => now(),
            'lock_version' => 1,
        ]);
        $version = WorkingPaperVersion::query()->create([
            'working_paper_id' => $paper->id,
            'version_number' => 1,
            'objective' => 'Determine whether reconciliations are independently reviewed.',
            'procedure_performed' => 'Inspected a sample of daily collection reconciliations.',
            'population_description' => 'All daily collection batches.',
            'sample_description' => 'Ten high-value collection batches.',
            'result' => 'One batch lacked supervisory sign-off.',
            'conclusion' => 'The exception warrants formal review.',
            'created_by' => $auditor->id,
        ]);
        $document = Document::query()->create([
            'document_code' => 'DOC-FND-001',
            'document_type_id' => $this->masterItem('DOCUMENT_TYPE', 'OTHER'),
            'title' => 'Finding Evidence',
            'owner_module' => 'AEMS',
            'library_visible' => false,
            'original_file_name' => 'reconciliation.pdf',
            'storage_path' => 'tests/reconciliation.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 2048,
            'checksum_sha256' => str_repeat('a', 64),
            'uploaded_by' => $auditor->id,
            'updated_by' => $auditor->id,
            'is_active' => true,
        ]);
        $documentVersion = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'version_label' => 'Evidence version 1',
            'change_summary' => 'Initial supported finding evidence.',
            'original_file_name' => 'reconciliation.pdf',
            'storage_path' => 'tests/versions/reconciliation.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 2048,
            'checksum_sha256' => str_repeat('a', 64),
            'uploaded_by' => $auditor->id,
        ]);
        $document->update(['current_version_id' => $documentVersion->id]);
        $evidence = AuditEvidence::query()->create([
            'evidence_family_uuid' => (string) Str::uuid(),
            'version_number' => 1,
            'is_current_revision' => true,
            'audit_engagement_id' => $engagement->id,
            'evidence_code' => 'EVD-AEMS-FND-001-001',
            'title' => 'Unsigned reconciliation batch',
            'evidence_category_id' => $this->masterItem('AEMS_EVIDENCE_CATEGORY', 'DOCUMENTARY'),
            'evidence_source_type_id' => $this->masterItem('AEMS_EVIDENCE_SOURCE_TYPE', 'AUDITEE'),
            'source_description' => 'Daily collection register from the auditee office.',
            'date_obtained' => now()->toDateString(),
            'custodian_name' => 'Records Custodian',
            'custodian_office_id' => $auditee->office_id,
            'confidentiality_level_id' => $this->masterItem('DOCUMENT_CONFIDENTIALITY', 'INTERNAL'),
            'document_version_id' => $documentVersion->id,
            'checksum_sha256' => str_repeat('a', 64),
            'status' => 'VERIFIED',
            'outcome' => 'ACCEPTED',
            'uploaded_by' => $auditor->id,
            'verified_by' => $management->id,
            'verified_at' => now(),
        ]);
        $version->evidence()->attach($evidence->id);
        AemsEvidenceAssessment::query()->create([
            'assessment_family_uuid' => (string) Str::uuid(),
            'audit_engagement_id' => $engagement->id,
            'audit_evidence_id' => $evidence->id,
            'document_version_id' => $documentVersion->id,
            'version_number' => 1,
            'is_current_revision' => true,
            'status' => 'ASSESSED',
            'outcome' => 'ACCEPTED',
            'sufficiency' => 'YES',
            'appropriateness' => 'YES',
            'relevance' => 'YES',
            'reliability' => 'HIGH',
            'competence' => 'HIGH',
            'accuracy' => 'YES',
            'completeness' => 'YES',
            'corroboration' => 'YES',
            'contradiction' => 'NO',
            'authenticity' => 'YES',
            'integrity' => 'YES',
            'confidentiality' => 'INTERNAL',
            'assessed_by' => $management->id,
            'assessed_at' => now(),
            'lock_version' => 1,
        ]);

        return [$management, $auditor, $auditee, $engagement, $version, $evidence];
    }

    /** @return array<string, mixed> */
    private function issuePayload(User $auditee, WorkingPaperVersion $version, AuditEvidence $evidence): array
    {
        return [
            'title' => 'Missing supervisory reconciliation sign-off',
            'exceptionDescription' => 'A sampled collection batch was posted without documented supervisory review.',
            'responsibleOfficeId' => $auditee->office_id,
            'riskRatingId' => $this->masterItem('RISK_LEVEL', 'HIGH'),
            'workingPaperVersionIds' => [$version->id],
            'evidenceIds' => [$evidence->id],
        ];
    }

    /** @return array<string, mixed> */
    private function findingPayload(User $auditee, WorkingPaperVersion $version, AuditEvidence $evidence): array
    {
        return [
            'title' => 'Daily collections posted without supervisory sign-off',
            'criteria' => 'Daily collection reconciliations must be reviewed before posting.',
            'condition' => 'One sampled high-value batch was posted without supervisory sign-off.',
            'cause' => 'The posting process does not enforce completion of the review checklist.',
            'effect' => 'Errors or irregular collections may be posted without timely detection.',
            'conclusion' => 'The control deficiency requires management remediation and follow-up testing.',
            'directAuthorityReason' => 'URGENT_OR_MATERIAL_RISK',
            'directAuthorityReference' => 'Risk committee directive AEMS-G1-001.',
            'riskRatingId' => $this->masterItem('RISK_LEVEL', 'HIGH'),
            'responsibleOfficeId' => $auditee->office_id,
            'workingPaperVersionIds' => [$version->id],
            'evidenceIds' => [$evidence->id],
        ];
    }

    private function finding(AuditEngagement $engagement, int $findingId): array
    {
        return collect(
            $this->getJson("/api/aems/engagements/{$engagement->id}/findings-workspace")
                ->assertOk()
                ->json('data.findings'),
        )->firstWhere('id', $findingId);
    }

    private function findingTransition(
        AuditEngagement $engagement,
        array $finding,
        string $action,
        array $extra = [],
    ) {
        return $this->postJson(
            "/api/aems/engagements/{$engagement->id}/findings/{$finding['id']}/transition",
            ['action' => $action, 'lockVersion' => $finding['lockVersion'], ...$extra],
        )->assertOk();
    }

    private function responseTransition(
        AuditEngagement $engagement,
        array $finding,
        array $response,
        string $action,
        ?string $comment = null,
    ) {
        return $this->postJson(
            "/api/aems/engagements/{$engagement->id}/findings/{$finding['id']}/responses/{$response['id']}/transition",
            [
                'action' => $action,
                'lockVersion' => $response['lockVersion'],
                'comment' => $comment,
            ],
        )->assertOk();
    }

    private function masterItem(string $listCode, string $itemCode): int
    {
        return (int) MasterList::query()
            ->where('code', $listCode)
            ->firstOrFail()
            ->items()
            ->where('code', $itemCode)
            ->value('id');
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
