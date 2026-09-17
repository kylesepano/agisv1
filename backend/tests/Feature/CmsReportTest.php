<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\CmsReportExport;
use App\Models\CmsReportRun;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class CmsReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_schema_permissions_and_protected_routes_are_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('cms_report_runs'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('cms_report_exports'));
        $this->assertSame(2, Permission::query()->where('code', 'like', 'cms.report.%')->count());
        $this->assertTrue($this->user('departmenthead')->hasPermission('cms.report.view'));
        $this->assertTrue($this->user('departmenthead')->hasPermission('cms.report.export'));
        $this->assertTrue($this->user('auditor')->hasPermission('cms.report.export'));
        $this->assertTrue($this->user('auditee')->hasPermission('cms.report.view'));
        $this->assertFalse($this->user('auditee')->hasPermission('cms.report.export'));
        $this->assertFalse($this->user('admin')->hasPermission('cms.report.view'));
        $this->assertGreaterThanOrEqual(
            6,
            collect(Route::getRoutes())->filter(fn ($route): bool => str_contains($route->uri(), 'cms/report'))->count(),
        );
    }

    public function test_catalog_generation_and_empty_scope_snapshot_are_backend_contracts(): void
    {
        $actor = $this->user('departmenthead');
        Sanctum::actingAs($actor);

        $this->getJson('/api/cms/reports')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'data.reports')
            ->assertJsonPath('data.canExport', true);

        $response = $this->postJson('/api/cms/reports/portfolio-status/generate', [
            'status' => 'IMPLEMENTED',
        ]);
        $response
            ->assertCreated()
            ->assertJsonPath('data.run.reportCode', 'portfolio-status')
            ->assertJsonPath('data.run.rowCount', 0)
            ->assertJsonPath('data.run.sourceQueryVersion', 'CMS-12A-v1');

        $run = CmsReportRun::query()->firstOrFail();
        $this->assertSame([], data_get($run->scope_snapshot, 'caseIds'));
        $this->assertSame(
            hash('sha256', json_encode($run->result_snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $run->result_checksum_sha256,
        );

        $this->getJson('/api/cms/reports/runs/'.$run->id)
            ->assertOk()
            ->assertJsonPath('data.run.resultChecksumSha256', $run->result_checksum_sha256);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'cms.report.generated',
            'user_id' => $actor->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cms.report.generated',
            'user_id' => $actor->id,
        ]);
    }

    public function test_csv_is_formula_safe_reproducible_and_versioned(): void
    {
        $actor = $this->user('departmenthead');
        $run = $this->createRun($actor, [
            'columns' => [
                ['key' => 'caseCode', 'label' => 'Case'],
                ['key' => 'note', 'label' => 'Note'],
            ],
            'rows' => [[
                'caseCode' => 'CMS-REC-000001',
                'note' => '=HYPERLINK("https://example.test")',
            ]],
        ]);

        Sanctum::actingAs($actor);
        $first = $this->postJson('/api/cms/reports/runs/'.$run->id.'/exports', ['format' => 'csv'])
            ->assertCreated()
            ->json('data.export');
        $second = $this->postJson('/api/cms/reports/runs/'.$run->id.'/exports', ['format' => 'csv'])
            ->assertCreated()
            ->json('data.export');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, $first['versionNumber']);
        $this->assertSame($first['checksumSha256'], $second['checksumSha256']);
        $this->assertSame(1, CmsReportExport::query()->where('format', 'CSV')->count());
        $contents = Storage::disk('local')->get(CmsReportExport::query()->firstOrFail()->storage_path);
        $this->assertStringContainsString("'=HYPERLINK", $contents);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contents);

        Sanctum::actingAs($this->user('auditee'));
        $this->getJson('/api/cms/report-exports/'.$first['id'].'/download')
            ->assertForbidden();
    }

    public function test_pdf_export_is_private_and_preserves_checksum_and_version(): void
    {
        $actor = $this->user('departmenthead');
        $run = $this->createRun($actor, [
            'columns' => [['key' => 'caseCode', 'label' => 'Case']],
            'rows' => [['caseCode' => 'CMS-REC-000001']],
        ]);

        Sanctum::actingAs($actor);
        $payload = $this->postJson('/api/cms/reports/runs/'.$run->id.'/exports', ['format' => 'pdf'])
            ->assertCreated()
            ->json('data.export');
        $export = CmsReportExport::query()->findOrFail($payload['id']);
        $contents = Storage::disk('local')->get($export->storage_path);

        $this->assertSame('PDF', $export->format);
        $this->assertSame(1, $export->version_number);
        $this->assertStringStartsWith('%PDF', $contents);
        $this->assertSame(hash('sha256', $contents), $export->checksum_sha256);
        $this->assertSame(strlen($contents), $export->file_size);

        $this->get('/api/cms/report-exports/'.$export->id.'/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertGreaterThanOrEqual(
            1,
            ActivityLog::query()->where('action', 'cms.report.exported')->count(),
        );
        $this->assertGreaterThanOrEqual(
            1,
            AuditLog::query()->where('action', 'cms.report.exported')->count(),
        );
        $this->assertGreaterThanOrEqual(
            1,
            ActivityLog::query()->where('action', 'cms.report.downloaded')->count(),
        );
    }

    public function test_scope_is_rechecked_for_existing_runs_and_models_are_immutable(): void
    {
        $actor = $this->user('departmenthead');
        $run = $this->createRun($actor, ['columns' => [], 'rows' => []], [999999]);

        Sanctum::actingAs($this->user('auditee'));
        $this->getJson('/api/cms/reports/runs/'.$run->id)->assertNotFound();

        try {
            $run->report_title = 'Changed';
            $run->save();
            $this->fail('Report runs must be immutable.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function createRun(User $actor, array $snapshot, array $caseIds = []): CmsReportRun
    {
        return CmsReportRun::query()->create([
            'report_code' => 'portfolio-status',
            'report_title' => 'CMS Recommendation Portfolio Status',
            'source_query_version' => 'CMS-12A-v1',
            'filters' => [],
            'scope_snapshot' => [
                'caseIds' => $caseIds,
                'visibility' => ['portfolioWide' => true],
                'generatedByUserId' => $actor->id,
            ],
            'result_snapshot' => $snapshot,
            'row_count' => count($snapshot['rows'] ?? []),
            'result_checksum_sha256' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'generated_by' => $actor->id,
            'generated_at' => now(),
        ]);
    }

    private function user(string $username): User
    {
        return User::query()->with(['role.permissions', 'roles.permissions'])->where('username', $username)->firstOrFail();
    }
}
