<?php

namespace Tests\Feature\Api;

use App\Models\AuditEngagement;
use App\Models\AuditEngagementPlan;
use App\Models\AuditEvidence;
use App\Models\AuditProgram;
use App\Models\AuditProgramProcedure;
use App\Models\DocumentVersion;
use App\Models\EngagementTeam;
use App\Models\MasterList;
use App\Models\User;
use App\Models\WorkingPaperVersion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AemsWorkingPaperEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_working_paper_approval_locks_exact_verified_evidence_and_corrections_create_versions(): void
    {
        [$management, $auditor, $engagement, $program, $procedure] = $this->fieldwork();
        $evidence = $this->uploadEvidence($auditor, $engagement);

        Sanctum::actingAs($auditor);
        $paper = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/working-papers",
            $this->paperPayload($procedure, [$evidence['id']]),
        )->assertCreated()
            ->assertJsonPath('data.workingPaper.status', 'DRAFT')
            ->json('data.workingPaper');
        $this->assertStringStartsWith(
            "WP-{$engagement->engagement_code}-",
            $paper['workingPaperCode'],
        );

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/working-papers/{$paper['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $paper['lockVersion']],
        )->assertUnprocessable()->assertJsonValidationErrors('evidenceIds');

        Sanctum::actingAs($management);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/evidence/{$evidence['id']}/transition",
            [
                'action' => 'VERIFY',
                'lockVersion' => $evidence['lockVersion'],
            ],
        )->assertOk()->assertJsonPath('data.evidence.status', 'VERIFIED');

        Sanctum::actingAs($auditor);
        $paper = $this->paper($engagement, $paper['id']);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/working-papers/{$paper['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $paper['lockVersion']],
        )->assertOk()->assertJsonPath('data.workingPaper.status', 'SUBMITTED');

        $paper = $this->paper($engagement, $paper['id']);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/working-papers/{$paper['id']}/transition",
            ['action' => 'APPROVE', 'lockVersion' => $paper['lockVersion']],
        )->assertForbidden();

        Sanctum::actingAs($management);
        $paper = $this->paper($engagement, $paper['id']);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/working-papers/{$paper['id']}/transition",
            [
                'action' => 'APPROVE',
                'lockVersion' => $paper['lockVersion'],
                'comment' => 'Procedure, results, conclusion, and cited evidence reviewed.',
            ],
        )->assertOk()
            ->assertJsonPath('data.workingPaper.status', 'APPROVED')
            ->assertJsonPath('data.workingPaper.latestVersion.evidence.0.status', 'LOCKED');
        $this->assertDatabaseHas('audit_evidence', [
            'id' => $evidence['id'],
            'status' => 'LOCKED',
        ]);
        $this->assertDatabaseHas('working_paper_version_evidence', [
            'audit_evidence_id' => $evidence['id'],
        ]);

        Sanctum::actingAs($auditor);
        $paper = $this->paper($engagement, $paper['id']);
        $this->putJson(
            "/api/aems/engagements/{$engagement->id}/working-papers/{$paper['id']}",
            [
                ...$this->paperPayload($procedure, [$evidence['id']]),
                'lockVersion' => $paper['lockVersion'],
                'changeReason' => 'Attempted overwrite of an approved paper.',
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/working-papers/{$paper['id']}/revise",
            [
                'lockVersion' => $paper['lockVersion'],
                'reason' => 'Correct the documented sample range without changing prior history.',
            ],
        )->assertOk()
            ->assertJsonPath('data.workingPaper.status', 'DRAFT')
            ->assertJsonPath('data.workingPaper.currentVersionNumber', 2);
        $this->assertDatabaseCount('working_paper_versions', 2);
        $this->assertDatabaseCount('working_paper_version_evidence', 2);

        $version = WorkingPaperVersion::query()->oldest('version_number')->firstOrFail();
        $this->expectException(LogicException::class);
        $version->update(['result' => 'Historical approved content cannot be overwritten.']);
    }

    public function test_evidence_replacement_is_immutable_and_program_completion_requires_approved_working_papers(): void
    {
        [$management, $auditor, $engagement, $program, $procedure] = $this->fieldwork();
        $evidence = $this->uploadEvidence($auditor, $engagement);

        Sanctum::actingAs($management);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/evidence/{$evidence['id']}/transition",
            ['action' => 'VERIFY', 'lockVersion' => $evidence['lockVersion']],
        )->assertOk();

        $procedure->update([
            'status' => 'COMPLETED',
            'reviewer_result' => 'SATISFACTORY',
            'reviewed_by' => $management->id,
            'reviewed_at' => now(),
            'completed_by' => $auditor->id,
            'completed_at' => now(),
        ]);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program->id}/transition",
            ['action' => 'COMPLETE', 'lockVersion' => $program->lock_version],
        )->assertUnprocessable()->assertJsonValidationErrors('procedures');

        Sanctum::actingAs($auditor);
        $paper = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/working-papers",
            $this->paperPayload($procedure, [$evidence['id']]),
        )->assertCreated()->json('data.workingPaper');
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/working-papers/{$paper['id']}/transition",
            ['action' => 'SUBMIT', 'lockVersion' => $paper['lockVersion']],
        )->assertOk();

        Sanctum::actingAs($management);
        $paper = $this->paper($engagement, $paper['id']);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/working-papers/{$paper['id']}/transition",
            ['action' => 'APPROVE', 'lockVersion' => $paper['lockVersion']],
        )->assertOk();

        $program->refresh();
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/programs/{$program->id}/transition",
            ['action' => 'COMPLETE', 'lockVersion' => $program->lock_version],
        )->assertOk()->assertJsonPath('data.program.status', 'COMPLETED');

        Sanctum::actingAs($auditor);
        $lockedEvidence = $this->currentEvidence($engagement);
        $documentId = DocumentVersion::query()
            ->findOrFail($lockedEvidence['documentVersionId'])
            ->document_id;
        $replacement = $this->post(
            "/api/aems/engagements/{$engagement->id}/evidence/{$lockedEvidence['id']}/revisions",
            [
                ...$this->evidenceMetadata(),
                'lockVersion' => $lockedEvidence['lockVersion'],
                'changeReason' => 'The custodian supplied a complete signed source file.',
                'file' => UploadedFile::fake()->createWithContent(
                    'signed-receipts.csv',
                    "receipt,total,approved\n1,100,yes\n",
                ),
            ],
        )->assertCreated()
            ->assertJsonPath('data.evidence.versionNumber', 2)
            ->assertJsonPath('data.evidence.status', 'DRAFT')
            ->json('data.evidence');

        $this->assertDatabaseHas('audit_evidence', [
            'id' => $lockedEvidence['id'],
            'is_current_revision' => false,
            'status' => 'LOCKED',
            'outcome' => 'SUPERSEDED',
        ]);
        $this->assertDatabaseHas('audit_evidence', [
            'id' => $replacement['id'],
            'is_current_revision' => true,
            'supersedes_evidence_id' => $lockedEvidence['id'],
            'outcome' => 'REGISTERED',
        ]);
        $this->assertNotSame(
            $lockedEvidence['documentVersionId'],
            $replacement['documentVersionId'],
        );
        $this->assertSame(
            2,
            DocumentVersion::query()->where('document_id', $documentId)->count(),
        );

        $current = AuditEvidence::query()->findOrFail($replacement['id']);
        $this->post(
            "/api/aems/engagements/{$engagement->id}/evidence/{$current->id}/revisions",
            [
                ...$this->evidenceMetadata(),
                'lockVersion' => $current->lock_version,
                'changeReason' => 'Attempt to upload the exact same replacement again.',
                'file' => UploadedFile::fake()->createWithContent(
                    'signed-receipts-copy.csv',
                    "receipt,total,approved\n1,100,yes\n",
                ),
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('audit_evidence', 2);
        $this->assertSame(
            2,
            DocumentVersion::query()->where('document_id', $documentId)->count(),
        );
    }

    /** @return array{User, User, AuditEngagement, AuditProgram, AuditProgramProcedure} */
    private function fieldwork(): array
    {
        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => 'AEMS-WP-001',
            'title' => 'Working Paper Controls Test',
            'source_type' => 'SPECIAL',
            'special_authority_reference' => 'AUTH-WP-001',
            'special_authority_date' => now()->toDateString(),
            'special_authority_approved_by' => $management->id,
            'objectives' => 'Test the controlled Working Paper and Evidence lifecycle.',
            'scope' => 'Receipt processing and reconciliation records.',
            'status' => 'FIELDWORK',
            'created_by' => $management->id,
            'updated_by' => $management->id,
        ]);
        $engagement->offices()->attach($auditor->office_id, ['is_primary' => true]);
        EngagementTeam::query()->create([
            'audit_engagement_id' => $engagement->id,
            'user_id' => $auditor->id,
            'assignment_role_code' => 'AUDITOR',
            'assigned_by' => $management->id,
            'is_active' => true,
        ]);
        $plan = AuditEngagementPlan::query()->create([
            'audit_engagement_id' => $engagement->id,
            'plan_code' => 'AEP-WP-001',
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
            'program_code' => 'AP-WP-001',
            'title' => 'Receipt Controls Program',
            'objective' => 'Test receipt completeness and reconciliation.',
            'status' => 'ACTIVE',
            'revision_number' => 0,
            'is_current_revision' => true,
            'prepared_by' => $auditor->id,
            'approved_by' => $management->id,
            'approved_at' => now(),
            'activated_at' => now(),
            'lock_version' => 1,
            'is_active' => true,
        ]);
        $procedure = AuditProgramProcedure::query()->create([
            'audit_program_id' => $program->id,
            'procedure_code' => 'RC-01',
            'sequence_number' => 1,
            'objective' => 'Test daily receipt reconciliation.',
            'procedure_description' => 'Select receipt days and reconcile collections to deposits.',
            'expected_evidence' => 'Receipt register, collection report, and deposit confirmation.',
            'assigned_to' => $auditor->id,
            'target_date' => now()->addWeek()->toDateString(),
            'status' => 'IN_PROGRESS',
            'lock_version' => 1,
        ]);

        return [$management, $auditor, $engagement, $program, $procedure];
    }

    /** @return array<string, mixed> */
    private function uploadEvidence(User $auditor, AuditEngagement $engagement): array
    {
        Sanctum::actingAs($auditor);

        return $this->post(
            "/api/aems/engagements/{$engagement->id}/evidence",
            [
                ...$this->evidenceMetadata(),
                'file' => UploadedFile::fake()->createWithContent(
                    'receipts.csv',
                    "receipt,total\n1,100\n",
                ),
            ],
        )->assertCreated()
            ->assertJsonPath('data.evidence.status', 'DRAFT')
            ->assertJsonPath('data.evidence.versionNumber', 1)
            ->json('data.evidence');
    }

    /** @return array<string, mixed> */
    private function evidenceMetadata(): array
    {
        return [
            'title' => 'Daily receipt reconciliation',
            'evidenceCategoryId' => $this->masterItem('AEMS_EVIDENCE_CATEGORY', 'ANALYTICAL'),
            'evidenceSourceTypeId' => $this->masterItem('AEMS_EVIDENCE_SOURCE_TYPE', 'AUDITEE'),
            'sourceDescription' => 'Official receipt register supplied by the auditee custodian.',
            'dateObtained' => now()->toDateString(),
            'custodianName' => 'Revenue Records Custodian',
            'confidentialityLevelId' => $this->masterItem('DOCUMENT_CONFIDENTIALITY', 'INTERNAL'),
        ];
    }

    /** @param list<int> $evidenceIds
     * @return array<string, mixed>
     */
    private function paperPayload(
        AuditProgramProcedure $procedure,
        array $evidenceIds,
    ): array {
        return [
            'procedureId' => $procedure->id,
            'title' => 'Daily Receipt Reconciliation Test',
            'objective' => 'Determine whether daily receipts are completely reconciled and deposited.',
            'procedurePerformed' => 'Selected five collection days and reconciled receipt registers, collection reports, and validated deposits.',
            'populationDescription' => 'All daily collection reports issued during July 2026.',
            'sampleDescription' => 'Five judgmentally selected high-value collection days.',
            'result' => 'All selected receipts agreed to the collection reports and validated deposits.',
            'conclusion' => 'The tested daily reconciliation control operated effectively for the selected sample.',
            'crossReferences' => ['AP-WP-001/RC-01', 'AEP-WP-001'],
            'evidenceIds' => $evidenceIds,
        ];
    }

    /** @return array<string, mixed> */
    private function paper(AuditEngagement $engagement, int $paperId): array
    {
        return collect(
            $this->getJson("/api/aems/engagements/{$engagement->id}/working-papers")
                ->assertOk()
                ->json('data.workingPapers'),
        )->firstWhere('id', $paperId);
    }

    /** @return array<string, mixed> */
    private function currentEvidence(AuditEngagement $engagement): array
    {
        return collect(
            $this->getJson("/api/aems/engagements/{$engagement->id}/working-papers")
                ->assertOk()
                ->json('data.evidence'),
        )->firstWhere('isCurrentRevision', true);
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
        return User::query()
            ->with(['role.permissions', 'roles.permissions', 'office'])
            ->where('username', $username)
            ->firstOrFail();
    }
}
