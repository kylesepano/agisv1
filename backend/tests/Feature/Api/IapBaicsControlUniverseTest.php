<?php

namespace Tests\Feature\Api;

use App\Models\AuditArea;
use App\Models\AuditFocus;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\IapAuditUniverseItem;
use App\Models\IapBaicsAssessment;
use App\Models\IapBaicsComponent;
use App\Models\IapBaicsControl;
use App\Models\IapBaicsEvidenceLink;
use App\Models\IapBaicsReport;
use App\Models\MasterListItem;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IapBaicsControlUniverseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_control_requires_approved_method_and_exact_evidence_before_approval(): void
    {
        [$actor, $approver, $assessment, $component, $office] = $this->cycle();
        Sanctum::actingAs($actor);
        $payload = $this->controlPayload($assessment, $component, $office);
        $created = $this->postJson("/api/iap/baics/{$assessment->id}/controls", $payload)
            ->assertCreated()->json('data.control');

        $this->postJson("/api/iap/baics/{$assessment->id}/controls/{$created['id']}/transitions/SUBMIT", ['lockVersion' => $created['lockVersion']])->assertOk();
        Sanctum::actingAs($approver);
        $pending = IapBaicsControl::query()->findOrFail($created['id']);
        $this->postJson("/api/iap/baics/{$assessment->id}/controls/{$pending->id}/transitions/APPROVE", ['lockVersion' => $pending->lock_version])
            ->assertUnprocessable()->assertJsonValidationErrors('control');
        $this->assertDatabaseHas('iap_baics_controls', ['id' => $created['id'], 'status' => 'PENDING_REVIEW']);
    }

    public function test_bar_cannot_submit_until_all_approved_sources_are_available(): void
    {
        [$actor, , $assessment, , ] = $this->cycle();
        Sanctum::actingAs($actor);
        $control = $this->postJson("/api/iap/baics/{$assessment->id}/controls", $this->controlPayload($assessment, $assessment->components()->firstOrFail(), $assessment->responsibleOffice))
            ->assertCreated()->json('data.control');
        $report = $this->postJson("/api/iap/baics/{$assessment->id}/reports", [
            'title' => 'Baseline Assessment Report', 'executiveSummary' => 'Summary', 'objectivesScopeMethodology' => 'Scope and methods',
            'overallFindings' => 'Findings', 'controlGapSummary' => 'Gaps', 'recommendationsSummary' => 'Recommendations',
            'limitationsExceptions' => 'Limitations', 'controlIds' => [$control['id']], 'reviewerId' => User::query()->where('username', 'admin')->value('id'),
        ])->assertCreated()->json('data.report');
        $this->postJson("/api/iap/baics/{$assessment->id}/reports/{$report['id']}/transitions/SUBMIT", [])
            ->assertUnprocessable()->assertJsonValidationErrors('components');
        $this->assertDatabaseHas('iap_baics_reports', ['id' => $report['id'], 'status' => 'DRAFT']);
    }

    public function test_approved_bar_has_protected_reproducible_csv_export(): void
    {
        [$actor, , $assessment, , ] = $this->cycle();
        $report = IapBaicsReport::query()->create([
            'assessment_id' => $assessment->id,
            'report_code' => 'BAR-2026-001',
            'title' => 'Baseline Assessment Report',
            'status' => 'APPROVED',
            'executive_summary' => 'Summary',
            'objectives_scope_methodology' => 'Scope and methods',
            'overall_findings' => 'Findings',
            'control_gap_summary' => 'Gaps',
            'prepared_by' => $actor->id,
            'approved_by' => User::query()->where('username', 'admin')->value('id'),
            'version_number' => 1,
            'lock_version' => 1,
            'is_current_revision' => true,
            'source_manifest' => ['assessment' => ['id' => $assessment->id]],
        ]);
        $snapshot = [
            'meta' => [['label' => 'BAR', 'value' => $report->report_code]],
            'columns' => [['key' => 'controlCode', 'label' => 'Control Code']],
            'rows' => [['controlCode' => '=formula']],
            'sections' => [['title' => 'Summary', 'text' => 'Summary']],
        ];
        $canonical = json_encode($snapshot, JSON_THROW_ON_ERROR);
        $report->versions()->create([
            'version_number' => 1,
            'status' => 'APPROVED',
            'snapshot' => $snapshot,
            'source_manifest' => $report->source_manifest,
            'source_manifest_sha256' => hash('sha256', json_encode($report->source_manifest, JSON_THROW_ON_ERROR)),
            'content_sha256' => hash('sha256', $canonical),
            'pdf_checksum_sha256' => hash('sha256', 'PDF|'.$canonical),
            'csv_checksum_sha256' => hash('sha256', 'CSV|'.$canonical),
            'file_version' => 'BAR-2026-001-v1',
            'created_by' => $actor->id,
        ]);

        Sanctum::actingAs(User::query()->where('username', 'admin')->firstOrFail());
        $this->get("/api/iap/baics/{$assessment->id}/reports/{$report->id}/export?format=csv")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('BAR-2026-001-v1.csv');
    }

    private function cycle(): array
    {
        $actor = User::query()->where('username', 'departmenthead')->firstOrFail();
        $approver = User::query()->where('username', 'admin')->firstOrFail();
        $office = Office::query()->whereHas('auditAreas')->firstOrFail();
        $area = $office->auditAreas()->firstOrFail();
        $focus = AuditFocus::query()->where('audit_area_id', $area->id)->firstOrFail();
        $source = IapAuditUniverseItem::query()->where('responsible_office_id', $office->id)->firstOrFail();
        Sanctum::actingAs($actor);
        $created = $this->postJson('/api/iap/baics', [
            'assessmentYear' => 2026, 'name' => 'Control Universe Test', 'responsibleOfficeId' => $office->id,
            'scopeSummary' => 'Scope', 'objectives' => 'Objective', 'methodology' => 'Methods',
            'scopeItems' => [['auditUniverseItemId' => $source->id, 'officeId' => $office->id, 'auditAreaId' => $area->id, 'auditFocusId' => $focus->id]],
        ])->assertCreated()->json('data.assessment');
        $assessment = IapBaicsAssessment::query()->with('components', 'scopeItems', 'responsibleOffice')->findOrFail($created['id']);
        return [$actor, $approver, $assessment, $assessment->components->firstOrFail(), $office];
    }

    private function controlPayload(IapBaicsAssessment $assessment, IapBaicsComponent $component, Office $office): array
    {
        return ['scopeItemId' => $assessment->scopeItems->firstOrFail()->id, 'componentId' => $component->id, 'controlCode' => 'CTRL-'.Str::upper(Str::random(5)), 'processStep' => 'Review and approve transaction', 'controlOwnerOfficeId' => $office->id, 'objective' => 'Ensure transactions are authorized.', 'relatedRisk' => 'Unauthorized transactions may be processed.', 'controlDescription' => 'A designated reviewer checks and approves each transaction.', 'expectedResult' => 'Only authorized transactions proceed.', 'controlType' => 'PREVENTIVE', 'executionMode' => 'MANUAL', 'designAssessment' => 'Design assessment recorded.', 'operatingAssessment' => 'Operating assessment recorded.', 'controlStatus' => 'Existing', 'reviewerId' => User::query()->where('username', 'admin')->value('id')];
    }
}
