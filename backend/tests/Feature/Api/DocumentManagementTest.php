<?php

namespace Tests\Feature\Api;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_authorized_user_can_manage_private_reference_documents(): void
    {
        Sanctum::actingAs($this->user('agisadmin'));
        $documentTypeId = MasterList::query()
            ->where('code', 'DOCUMENT_TYPE')
            ->firstOrFail()
            ->items()
            ->where('code', 'INTERNAL_AUDIT_MANUAL')
            ->value('id');
        $office = Office::query()->where('code', 'CIAS')->firstOrFail();

        $this->post('/api/documents', [
            'documentTypeId' => $documentTypeId,
            'title' => 'Philippine Government Internal Audit Manual — Volume I',
            'referenceNumber' => 'PGIAM-VOL-1',
            'issuingAuthority' => 'Department of Budget and Management',
            'publicationDate' => '2020-01-15',
            'version' => 'Volume I',
            'description' => 'Reference manual for government internal auditing.',
            'isActive' => true,
            'links' => [[
                'module' => 'CORE',
                'recordType' => 'OFFICE',
                'recordId' => $office->id,
            ]],
            'file' => UploadedFile::fake()->create(
                'PGIAM-Volume-I.pdf',
                120,
                'application/pdf',
            ),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.document.documentType', 'Internal Audit Manual / PGIAM')
            ->assertJsonPath('data.document.referenceNumber', 'PGIAM-VOL-1')
            ->assertJsonPath('data.document.isArchived', false)
            ->assertJsonPath('data.document.currentVersionNumber', 1)
            ->assertJsonPath('data.document.versionCount', 1)
            ->assertJsonPath('data.document.links.0.recordCode', 'CIAS');

        $document = Document::query()->firstOrFail();
        $initialVersion = DocumentVersion::query()->firstOrFail();
        Storage::disk('local')->assertExists($document->storage_path);
        $this->assertSame($initialVersion->id, $document->current_version_id);

        $this->getJson('/api/documents?include_archived=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.documents')
            ->assertJsonCount(10, 'data.documentTypes')
            ->assertJsonPath('data.linkOptions.0.key', fn ($key): bool => is_string($key))
            ->assertJsonPath('data.documents.0.uploadedBy', 'Kim V. Lao');

        $this->putJson("/api/documents/{$document->id}", [
            'documentTypeId' => $documentTypeId,
            'title' => 'Philippine Government Internal Audit Manual — Volume 1',
            'referenceNumber' => 'PGIAM-VOL-1',
            'issuingAuthority' => 'Department of Budget and Management',
            'publicationDate' => '2020-01-15',
            'description' => 'Updated reference description.',
            'isActive' => true,
            'links' => [[
                'module' => 'IAP',
                'recordType' => 'MODULE',
                'recordId' => 0,
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.document.version', 'Volume I')
            ->assertJsonPath('data.document.fileName', 'PGIAM-Volume-I.pdf')
            ->assertJsonPath('data.document.links.0.module', 'IAP');

        $this->post("/api/documents/{$document->id}/versions", [
            'versionLabel' => '2026 Revised Edition',
            'changeSummary' => 'Updated the manual to reflect the approved 2026 guidance.',
            'file' => UploadedFile::fake()->createWithContent(
                'PGIAM-Volume-I-2026.pdf',
                "%PDF-1.7\nRevised AGIS document content.",
            ),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.document.currentVersionNumber', 2)
            ->assertJsonPath('data.document.versionCount', 2)
            ->assertJsonPath('data.document.version', '2026 Revised Edition')
            ->assertJsonPath('data.document.versions.0.isCurrent', true)
            ->assertJsonPath('data.document.versions.1.isCurrent', false);

        $document->refresh()->load('currentVersion');
        $this->assertNotSame($initialVersion->id, $document->current_version_id);
        Storage::disk('local')->assertExists($initialVersion->storage_path);
        Storage::disk('local')->assertExists($document->currentVersion->storage_path);

        $this->post("/api/documents/{$document->id}/versions", [
            'versionLabel' => 'Duplicate edition',
            'changeSummary' => 'This exact file must be rejected.',
            'file' => UploadedFile::fake()->createWithContent(
                'duplicate.pdf',
                "%PDF-1.7\nRevised AGIS document content.",
            ),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('document_versions', 2);

        $this->get("/api/documents/{$document->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->get("/api/documents/{$document->id}/versions/{$initialVersion->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->deleteJson("/api/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Document archived successfully. Its complete version history was retained.',
            );
        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        Storage::disk('local')->assertExists($initialVersion->storage_path);
        Storage::disk('local')->assertExists($document->currentVersion->storage_path);

        $this->postJson("/api/documents/{$document->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.document.isArchived', false);
        $this->assertNotSoftDeleted('documents', ['id' => $document->id]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'document.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.version_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.archived']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.restored']);

        $this->expectException(\LogicException::class);
        $initialVersion->update(['change_summary' => 'Attempted tampering']);
    }

    public function test_document_actions_are_protected_by_specific_permissions(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $documentTypeId = MasterList::query()
            ->where('code', 'DOCUMENT_TYPE')
            ->firstOrFail()
            ->items()
            ->where('code', 'LAW_STATUTE')
            ->value('id');
        $this->post('/api/documents', [
            'documentTypeId' => $documentTypeId,
            'title' => 'Reference Law',
            'isActive' => true,
            'file' => UploadedFile::fake()->create(
                'reference-law.pdf',
                50,
                'application/pdf',
            ),
        ], ['Accept' => 'application/json'])->assertCreated();
        $document = Document::query()->firstOrFail();
        $version = DocumentVersion::query()->firstOrFail();

        Sanctum::actingAs($this->user('mayor'));

        $this->getJson('/api/documents')->assertOk();
        $this->postJson('/api/documents', [])->assertForbidden();
        $this->putJson("/api/documents/{$document->id}", [])->assertForbidden();
        $this->postJson("/api/documents/{$document->id}/versions", [])->assertForbidden();
        $this->deleteJson("/api/documents/{$document->id}")->assertForbidden();
        $this->postJson("/api/documents/{$document->id}/restore")->assertForbidden();
        $this->getJson("/api/documents/{$document->id}/download")->assertForbidden();
        $this->getJson("/api/documents/{$document->id}/versions/{$version->id}/download")
            ->assertForbidden();
    }

    public function test_confidentiality_controls_discovery_assignment_and_downloads(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $documentTypeId = MasterList::query()
            ->where('code', 'DOCUMENT_TYPE')
            ->firstOrFail()
            ->items()
            ->where('code', 'POLICY_GUIDELINE')
            ->value('id');
        $confidentiality = MasterList::query()
            ->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->firstOrFail()
            ->items()
            ->pluck('id', 'code');

        $this->post('/api/documents', [
            'documentTypeId' => $documentTypeId,
            'confidentialityLevelId' => $confidentiality['RESTRICTED'],
            'title' => 'Restricted audit directive',
            'file' => UploadedFile::fake()->create('restricted.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.document.confidentialityCode', 'RESTRICTED')
            ->assertJsonPath('data.document.documentCode', fn ($code): bool => str_starts_with($code, 'DOC-'));

        $document = Document::query()->firstOrFail();
        $this->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data.documents')
            ->assertJsonCount(4, 'data.confidentialityLevels');

        Sanctum::actingAs($this->user('auditor'));
        $this->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(0, 'data.documents')
            ->assertJsonCount(3, 'data.confidentialityLevels');
        $this->getJson("/api/documents/{$document->id}/download")->assertForbidden();

        Sanctum::actingAs($this->user('auditee'));
        $this->post('/api/documents', [
            'documentTypeId' => $documentTypeId,
            'confidentialityLevelId' => $confidentiality['CONFIDENTIAL'],
            'title' => 'Unauthorized classification',
            'file' => UploadedFile::fake()->create('unauthorized.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertForbidden();
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
