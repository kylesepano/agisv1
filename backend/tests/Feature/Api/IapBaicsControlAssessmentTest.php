<?php

namespace Tests\Feature\Api;

use App\Models\AuditArea;
use App\Models\AuditFocus;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\IapAuditUniverseItem;
use App\Models\IapBaicsAssessment;
use App\Models\IapBaicsComponent;
use App\Models\MasterListItem;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IapBaicsControlAssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_component_requires_independent_methods_and_exact_evidence_before_approval(): void
    {
        $reviewer = User::query()->where('username', 'departmenthead')->firstOrFail();
        $office = Office::query()->whereHas('auditAreas')->firstOrFail();
        $area = $office->auditAreas()->firstOrFail();
        $focus = AuditFocus::query()->where('audit_area_id', $area->id)->firstOrFail();
        $source = IapAuditUniverseItem::query()->where('responsible_office_id', $office->id)->firstOrFail();
        Sanctum::actingAs($reviewer);

        $assessment = $this->postJson('/api/iap/baics', [
            'assessmentYear' => 2026, 'name' => 'Control Assessment', 'responsibleOfficeId' => $office->id,
            'scopeSummary' => 'Scope', 'objectives' => 'Objective', 'methodology' => 'Documented BAICS methods.',
            'scopeItems' => [['auditUniverseItemId' => $source->id, 'officeId' => $office->id, 'auditAreaId' => $area->id, 'auditFocusId' => $focus->id]],
        ])->assertCreated()->json('data.assessment');

        $component = IapBaicsComponent::query()->where('assessment_id', $assessment['id'])->where('component_code', 'CONTROL_ENVIRONMENT')->firstOrFail();
        $performers = User::factory()->count(3)->create(['office_id' => $office->id]);
        $component = $this->putJson("/api/iap/baics/{$assessment['id']}/components/{$component->id}", [
            'conclusion' => 'Controls are designed and operating with documented oversight.', 'assessorId' => $performers[0]->id,
            'reviewerId' => $reviewer->id, 'lockVersion' => $component->lock_version,
        ])->assertOk()->json('data.component');

        foreach ($performers as $index => $performer) {
            $method = $this->postJson("/api/iap/baics/{$assessment['id']}/components/{$component['id']}/methods", [
                'methodType' => ['ICQ', 'INTERVIEW_INQUIRY_FGD', 'WALKTHROUGH_OBSERVATION'][$index], 'title' => "Method {$index}",
                'performedBy' => $performer->id, 'performedOn' => '2026-08-14', 'procedure' => 'Perform the documented procedure.',
                'result' => 'Evidence supports the stated conclusion.', 'reviewerId' => $reviewer->id,
            ])->assertCreated()->json('data.method');
            $document = Document::query()->create(['document_code' => 'BAICS-DOC-'.Str::upper(Str::random(8)), 'document_type_id' => MasterListItem::query()->firstOrFail()->id, 'title' => "BAICS evidence {$index}", 'original_file_name' => "baics-{$index}.txt", 'storage_path' => 'test/'.Str::uuid().'.txt', 'mime_type' => 'text/plain', 'file_extension' => 'txt', 'file_size' => 10, 'checksum_sha256' => hash('sha256', "baics-{$index}"), 'uploaded_by' => $performer->id, 'is_active' => true]);
            $version = DocumentVersion::query()->create(['document_id' => $document->id, 'version_number' => 1, 'version_label' => 'Evidence 1', 'change_summary' => 'Initial BAICS evidence.', 'original_file_name' => $document->original_file_name, 'storage_path' => $document->storage_path, 'mime_type' => 'text/plain', 'file_extension' => 'txt', 'file_size' => 10, 'checksum_sha256' => $document->checksum_sha256, 'uploaded_by' => $performer->id]);
            $this->postJson("/api/iap/baics/{$assessment['id']}/components/{$component['id']}/evidence", ['methodId' => $method['id'], 'documentVersionId' => $version->id, 'description' => 'Exact supporting version.'])->assertCreated();
            $this->postJson("/api/iap/baics/{$assessment['id']}/components/{$component['id']}/methods/{$method['id']}/transitions/SUBMIT", ['lockVersion' => $method['lockVersion']])->assertOk();
            $method = \App\Models\IapBaicsMethod::query()->findOrFail($method['id']);
            $this->postJson("/api/iap/baics/{$assessment['id']}/components/{$component['id']}/methods/{$method->id}/transitions/APPROVE", ['lockVersion' => $method->lock_version])->assertOk();
        }

        $component = \App\Models\IapBaicsComponent::query()->findOrFail($component['id']);
        $this->postJson("/api/iap/baics/{$assessment['id']}/components/{$component->id}/transitions/SUBMIT", ['lockVersion' => $component->lock_version])->assertOk();
        $component = $component->fresh();
        $this->postJson("/api/iap/baics/{$assessment['id']}/components/{$component->id}/transitions/APPROVE", ['lockVersion' => $component->lock_version])->assertOk();

        $readiness = $this->getJson("/api/iap/baics/{$assessment['id']}/readiness")->assertOk()->json('data.readiness');
        $this->assertFalse($readiness['ready']);
        $this->assertSame('APPROVED', IapBaicsComponent::query()->findOrFail($component['id'])->status);
        $this->assertGreaterThanOrEqual(7, \App\Models\IapBaicsComponentVersion::query()->count());
    }
}
