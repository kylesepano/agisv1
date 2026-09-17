<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ArmisCompetency;
use App\Models\ArmisResourceProfile;
use App\Models\ArmisWorkflowEvent;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\MasterListItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArmisCompetencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_competency_metadata_and_routes_are_protected(): void
    {
        $this->assertDatabaseHas('permissions', ['code' => 'armis.competency.view']);
        $this->assertDatabaseHas('permissions', ['code' => 'armis.competency.manage']);
        $this->assertDatabaseHas('permissions', ['code' => 'armis.competency.verify']);

        Sanctum::actingAs($this->user('auditee'));
        $this->getJson('/api/armis/competencies/metadata')->assertForbidden();
        $this->getJson('/api/armis/competencies')->assertForbidden();

        Sanctum::actingAs($this->user('agisadmin'));
        $this->getJson('/api/armis/competencies/metadata')
            ->assertOk()
            ->assertJsonPath('data.proficiencyLevels.0.code', 'BASIC')
            ->assertJsonFragment(['code' => 'FINANCIAL_AUDIT']);
    }

    public function test_competency_can_be_submitted_verified_returned_and_revised_immutably(): void
    {
        $admin = $this->user('agisadmin');
        $reviewer = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $profile = $this->profile($admin, $auditor);
        $catalog = $this->catalogItem('FINANCIAL_AUDIT');
        $evidence = $this->documentVersion($admin);

        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/armis/competencies', [
            'resourceProfileId' => $profile->id,
            'competencyId' => $catalog->id,
            'proficiencyLevel' => 'ADVANCED',
            'credentialType' => 'Professional certification',
            'credentialReference' => 'CERT-ARMIS-001',
            'issuer' => 'Professional Institute',
            'issuedAt' => '2026-01-01',
            'expiresAt' => '2028-01-01',
            'evidenceDocumentVersionId' => $evidence->id,
            'notes' => 'Initial competency claim.',
        ]);
        $created->assertCreated()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.versionNumber', 1);
        $competencyId = (int) $created->json('data.id');

        $this->postJson("/api/armis/competencies/{$competencyId}/submit", ['lockVersion' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'PENDING_VERIFICATION')
            ->assertJsonPath('data.evidenceDocumentVersionId', $evidence->id);
        $this->assertDatabaseHas('armis_workflow_events', [
            'subject_type' => ArmisCompetency::class,
            'subject_id' => $competencyId,
            'event_code' => 'COMPETENCY_SUBMITTED',
        ]);

        // The submitter cannot perform the independent verification.
        $this->postJson("/api/armis/competencies/{$competencyId}/review", [
            'decision' => 'VERIFY', 'lockVersion' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('review');

        Sanctum::actingAs($reviewer);
        $this->postJson("/api/armis/competencies/{$competencyId}/review", [
            'decision' => 'VERIFY', 'lockVersion' => 2, 'notes' => 'Evidence verified independently.',
        ])->assertOk()->assertJsonPath('data.status', 'VERIFIED')->assertJsonPath('data.lockVersion', 3);
        $this->assertDatabaseHas('armis_workflow_events', [
            'subject_id' => $competencyId,
            'event_code' => 'COMPETENCY_VERIFIED',
            'to_status' => 'VERIFIED',
        ]);

        Sanctum::actingAs($admin);
        $this->putJson("/api/armis/competencies/{$competencyId}", [
            'proficiencyLevel' => 'EXPERT', 'lockVersion' => 3,
        ])->assertStatus(409);

        $revision = $this->postJson("/api/armis/competencies/{$competencyId}/revisions", [
            'lockVersion' => 3,
            'proficiencyLevel' => 'EXPERT',
            'notes' => 'Corrected certification level.',
        ])->assertOk()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.versionNumber', 2);
        $revisionId = (int) $revision->json('data.id');
        $this->assertNotSame($competencyId, $revisionId);
        $this->assertDatabaseHas('armis_competencies', [
            'id' => $competencyId,
            'is_current_revision' => false,
            'status' => 'VERIFIED',
        ]);
        $this->assertDatabaseHas('armis_competencies', [
            'id' => $revisionId,
            'supersedes_id' => $competencyId,
            'is_current_revision' => true,
            'status' => 'DRAFT',
        ]);

        $this->postJson("/api/armis/competencies/{$revisionId}/submit", ['lockVersion' => 1])
            ->assertOk()->assertJsonPath('data.status', 'PENDING_VERIFICATION');
        Sanctum::actingAs($reviewer);
        $this->postJson("/api/armis/competencies/{$revisionId}/review", [
            'decision' => 'RETURN', 'lockVersion' => 2, 'notes' => 'Attach the renewed certificate.',
        ])->assertOk()->assertJsonPath('data.status', 'RETURNED');

        $this->assertGreaterThanOrEqual(5, ArmisWorkflowEvent::query()->where('subject_type', ArmisCompetency::class)->count());
        $this->assertGreaterThanOrEqual(3, ActivityLog::query()->where('action', 'like', 'armis.competency.%')->count());
        $this->assertGreaterThanOrEqual(3, AuditLog::query()->where('action', 'like', 'armis.competency.%')->count());

        Sanctum::actingAs($admin);
        $this->getJson('/api/armis/competencies?includeHistory=1&resourceProfileId='.$profile->id)
            ->assertOk()
            ->assertJsonPath('meta.currentOnly', false)
            ->assertJsonCount(2, 'data');
        $this->getJson("/api/armis/competencies/{$revisionId}/events")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_competency_requires_exact_active_document_version_and_stale_lock_is_rejected(): void
    {
        $admin = $this->user('agisadmin');
        $auditor = $this->user('auditor');
        $profile = $this->profile($admin, $auditor);
        $catalog = $this->catalogItem('INFORMATION_SYSTEMS');

        Sanctum::actingAs($admin);
        $this->postJson('/api/armis/competencies', [
            'resourceProfileId' => $profile->id,
            'competencyId' => $catalog->id,
            'evidenceDocumentVersionId' => 999999,
        ])->assertUnprocessable()->assertJsonValidationErrors('evidenceDocumentVersionId');

        $evidence = $this->documentVersion($admin);
        $response = $this->postJson('/api/armis/competencies', [
            'resourceProfileId' => $profile->id,
            'competencyId' => $catalog->id,
            'evidenceDocumentVersionId' => $evidence->id,
        ])->assertCreated();
        $id = (int) $response->json('data.id');

        $this->putJson("/api/armis/competencies/{$id}", [
            'notes' => 'first update', 'lockVersion' => 1,
        ])->assertOk()->assertJsonPath('data.lockVersion', 2);
        $this->putJson("/api/armis/competencies/{$id}", [
            'notes' => 'stale update', 'lockVersion' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('lockVersion');
    }

    private function profile(User $creator, User $resourceUser): ArmisResourceProfile
    {
        return ArmisResourceProfile::query()->create([
            'resource_code' => 'ARMIS-COMP-'.Str::upper(Str::random(5)),
            'user_id' => $resourceUser->id,
            'office_id' => $resourceUser->office_id,
            'category' => 'AUDIT_RESOURCE',
            'status' => 'ACTIVE',
            'effective_from' => '2026-01-01',
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
    }

    private function catalogItem(string $code): MasterListItem
    {
        return MasterListItem::query()
            ->where('code', $code)
            ->whereHas('masterList', fn ($query) => $query->where('code', 'IAP_AUDITOR_SPECIALIZATION'))
            ->firstOrFail();
    }

    private function documentVersion(User $uploader): DocumentVersion
    {
        $documentType = MasterListItem::query()
            ->where('code', 'POLICY_GUIDELINE')
            ->whereHas('masterList', fn ($query) => $query->where('code', 'DOCUMENT_TYPE'))
            ->firstOrFail();
        $confidentiality = MasterListItem::query()
            ->where('code', 'INTERNAL')
            ->whereHas('masterList', fn ($query) => $query->where('code', 'DOCUMENT_CONFIDENTIALITY'))
            ->firstOrFail();
        $document = Document::query()->create([
            'document_code' => 'DOC-ARMIS-'.Str::upper(Str::random(5)),
            'document_type_id' => $documentType->id,
            'confidentiality_level_id' => $confidentiality->id,
            'title' => 'ARMIS Certification Evidence',
            'original_file_name' => 'certificate.pdf',
            'storage_path' => 'armis/certificate-'.Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 2048,
            'checksum_sha256' => hash('sha256', 'armis-certificate'),
            'uploaded_by' => $uploader->id,
            'updated_by' => $uploader->id,
            'owner_module' => 'ARMIS',
            'library_visible' => false,
            'is_active' => true,
        ]);

        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'version_label' => '1.0',
            'change_summary' => 'Certification evidence.',
            'original_file_name' => 'certificate.pdf',
            'storage_path' => 'armis/certificate-version-'.Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 2048,
            'checksum_sha256' => hash('sha256', 'armis-certificate-version'),
            'uploaded_by' => $uploader->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->save();

        return $version;
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
