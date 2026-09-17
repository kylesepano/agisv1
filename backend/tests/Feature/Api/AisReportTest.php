<?php

namespace Tests\Feature\Api;

use App\Models\AisReportExport;
use App\Models\AisReportRun;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AisReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_catalog_report_generation_and_review_alerts_are_scope_aware(): void
    {
        Sanctum::actingAs($actor = $this->user('departmenthead'));

        $this->getJson('/api/ais/reports')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=30, must-revalidate, private')
            ->assertJsonCount(4, 'data.reports')
            ->assertJsonPath('data.canExport', true)
            ->assertJsonPath('data.professionalControls.automatedDecisionsDisabled', true);

        $run = $this->postJson('/api/ais/reports/portfolio-overview/generate')
            ->assertCreated()
            ->assertJsonPath('data.run.reportCode', 'portfolio-overview')
            ->assertJsonPath('data.run.sourceQueryVersion', 'AIS-3-v1')
            ->assertJsonPath('data.run.scope.generatedByUserId', $actor->id)
            ->json('data.run');

        $this->assertSame(8, $run['rowCount']);
        $this->getJson('/api/ais/reports/runs')
            ->assertOk()
            ->assertJsonCount(1, 'data.runs')
            ->assertJsonPath('data.runs.0.id', $run['id']);
        $this->getJson('/api/ais/alerts')
            ->assertOk()
            ->assertJsonStructure(['data' => ['generatedAt', 'scope', 'alerts']]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ais.report.generated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ais.report.generated']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ais.reports.catalog.viewed']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ais.report.runs.viewed']);
    }

    public function test_protected_csv_and_pdf_exports_are_private_checksumed_and_immutable(): void
    {
        Sanctum::actingAs($actor = $this->user('departmenthead'));
        $run = $this->postJson('/api/ais/reports/attention-register/generate')->json('data.run');

        $csv = $this->postJson("/api/ais/reports/runs/{$run['id']}/exports", ['format' => 'csv'])
            ->assertCreated()
            ->assertJsonPath('data.export.format', 'csv')
            ->json('data.export');
        $pdf = $this->postJson("/api/ais/reports/runs/{$run['id']}/exports", ['format' => 'pdf'])
            ->assertCreated()
            ->assertJsonPath('data.export.format', 'pdf')
            ->json('data.export');

        foreach ([$csv, $pdf] as $export) {
            $record = AisReportExport::query()->findOrFail($export['id']);
            $contents = Storage::disk('local')->get($record->storage_path);
            $this->assertSame(hash('sha256', $contents), $record->checksum_sha256);
            $this->assertSame($record->checksum_sha256, $export['checksumSha256']);
        }

        $this->getJson("/api/ais/report-exports/{$csv['id']}/download")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-AGIS-Checksum-SHA256', $csv['checksumSha256']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ais.report.exported']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ais.report.downloaded']);

        $this->expectException(LogicException::class);
        AisReportRun::query()->findOrFail($run['id'])->update(['result_snapshot' => []]);
    }

    public function test_export_permission_is_separate_from_report_view(): void
    {
        Sanctum::actingAs($this->user('agisadmin'));
        $run = $this->postJson('/api/ais/reports/status-register/generate')
            ->assertCreated()
            ->json('data.run');

        $this->postJson("/api/ais/reports/runs/{$run['id']}/exports", ['format' => 'csv'])
            ->assertForbidden();
    }

    private function user(string $username): User
    {
        return User::query()->with(['role.permissions', 'roles.permissions'])->where('username', $username)->firstOrFail();
    }
}
