<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ArmisReportExport;
use App\Models\ArmisReportRun;
use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisResourceProfile;
use App\Models\ArmisWorkloadAllocation;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class ArmisReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_schema_permissions_routes_and_administration_contract_are_registered(): void
    {
        $this->assertTrue(Schema::hasTable('armis_report_runs'));
        $this->assertTrue(Schema::hasTable('armis_report_exports'));
        $this->assertSame(2, Permission::query()->where('code', 'like', 'armis.report.%')->count());
        $this->assertTrue($this->user('departmenthead')->hasPermission('armis.report.view'));
        $this->assertTrue($this->user('departmenthead')->hasPermission('armis.report.export'));
        $this->assertGreaterThanOrEqual(
            6,
            collect(Route::getRoutes())->filter(fn ($route): bool => str_contains($route->uri(), 'armis/report'))->count(),
        );

        Sanctum::actingAs($this->user('departmenthead'));
        $this->getJson('/api/armis/administration')
            ->assertOk()
            ->assertJsonPath('data.provider.mode', 'ARMIS_AUTHORITATIVE')
            ->assertJsonPath('data.hardening.privateDownloads', true)
            ->assertJsonPath('data.hardening.csvFormulaMitigation', true);
    }

    public function test_catalog_and_empty_scope_snapshot_are_protected_contracts(): void
    {
        $actor = $this->user('departmenthead');
        Sanctum::actingAs($actor);

        $this->getJson('/api/armis/reports')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'data.reports')
            ->assertJsonPath('data.canExport', true);

        $response = $this->postJson('/api/armis/reports/resource-utilization/generate', [
            'fiscalYear' => 2027,
        ]);
        $visibleProfileCount = ArmisResourceProfile::query()->count();
        $response
            ->assertCreated()
            ->assertJsonPath('data.run.reportCode', 'resource-utilization')
            ->assertJsonPath('data.run.rowCount', $visibleProfileCount)
            ->assertJsonPath('data.run.sourceQueryVersion', 'ARMIS-5A-v1');

        $run = ArmisReportRun::query()->firstOrFail();
        $this->assertCount($visibleProfileCount, data_get($run->scope_snapshot, 'profileIds'));
        $this->assertSame(
            hash('sha256', json_encode($run->result_snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $run->result_checksum_sha256,
        );
        $this->getJson('/api/armis/reports/runs/'.$run->id)
            ->assertOk()
            ->assertJsonPath('data.run.resultChecksumSha256', $run->result_checksum_sha256);

        $this->assertDatabaseHas('activity_logs', ['action' => 'armis.report.generated', 'user_id' => $actor->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'armis.report.generated', 'user_id' => $actor->id]);
    }

    public function test_csv_is_formula_safe_private_and_idempotent(): void
    {
        $actor = $this->user('departmenthead');
        $run = $this->createRun($actor, [
            'meta' => [],
            'columns' => [
                ['key' => 'resourceCode', 'label' => 'Resource'],
                ['key' => 'note', 'label' => 'Note'],
            ],
            'rows' => [[
                'resourceCode' => 'ARMIS-RES-001',
                'note' => '=HYPERLINK("https://example.test")',
            ]],
        ]);

        Sanctum::actingAs($actor);
        $first = $this->postJson('/api/armis/reports/runs/'.$run->id.'/exports', ['format' => 'csv'])
            ->assertCreated()
            ->json('data.export');
        $second = $this->postJson('/api/armis/reports/runs/'.$run->id.'/exports', ['format' => 'csv'])
            ->assertCreated()
            ->json('data.export');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, $first['versionNumber']);
        $this->assertSame($first['checksumSha256'], $second['checksumSha256']);
        $contents = Storage::disk('local')->get(ArmisReportExport::query()->firstOrFail()->storage_path);
        $this->assertStringContainsString("'=HYPERLINK", $contents);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contents);

        Sanctum::actingAs($this->user('auditee'));
        $this->getJson('/api/armis/report-exports/'.$first['id'].'/download')->assertForbidden();
    }

    public function test_pdf_is_private_checksumed_and_audited(): void
    {
        $actor = $this->user('departmenthead');
        $run = $this->createRun($actor, [
            'meta' => [],
            'columns' => [['key' => 'resourceCode', 'label' => 'Resource']],
            'rows' => [['resourceCode' => 'ARMIS-RES-001']],
        ]);

        Sanctum::actingAs($actor);
        $payload = $this->postJson('/api/armis/reports/runs/'.$run->id.'/exports', ['format' => 'pdf'])
            ->assertCreated()
            ->json('data.export');
        $export = ArmisReportExport::query()->findOrFail($payload['id']);
        $contents = Storage::disk('local')->get($export->storage_path);

        $this->assertSame('PDF', $export->format);
        $this->assertSame(1, $export->version_number);
        $this->assertStringStartsWith('%PDF', $contents);
        $this->assertSame(hash('sha256', $contents), $export->checksum_sha256);
        $this->assertSame(strlen($contents), $export->file_size);
        $this->get('/api/armis/report-exports/'.$export->id.'/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-AGIS-Checksum-SHA256', $export->checksum_sha256);

        $this->assertDatabaseHas('activity_logs', ['action' => 'armis.report.exported']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'armis.report.exported']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'armis.report.downloaded']);
    }

    public function test_report_runs_and_exports_are_immutable(): void
    {
        $run = $this->createRun($this->user('departmenthead'), ['meta' => [], 'columns' => [], 'rows' => []]);
        $export = ArmisReportExport::query()->create([
            'armis_report_run_id' => $run->id,
            'format' => 'CSV',
            'version_number' => 1,
            'file_name' => 'report.csv',
            'storage_path' => 'armis/reports/test.csv',
            'mime_type' => 'text/csv',
            'file_size' => 1,
            'checksum_sha256' => str_repeat('a', 64),
            'generated_by' => $run->generated_by,
            'generated_at' => now(),
        ]);

        try {
            $run->report_title = 'Changed';
            $run->save();
            $this->fail('Report runs must be immutable.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
        try {
            $export->file_name = 'changed.csv';
            $export->save();
            $this->fail('Report exports must be immutable.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
    }

    public function test_resource_and_capacity_reports_read_approved_scoped_ledgers(): void
    {
        $admin = $this->user('agisadmin');
        $actor = $this->user('departmenthead');
        $owner = $this->user('auditor');
        $profile = ArmisResourceProfile::query()->create([
            'resource_code' => 'ARMIS-RPT-001',
            'user_id' => $owner->id,
            'office_id' => $owner->office_id,
            'category' => 'AUDIT_RESOURCE',
            'status' => 'ACTIVE',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        ArmisCapacitySubmission::query()->create([
            'resource_profile_id' => $profile->id,
            'fiscal_year' => 2027,
            'version_number' => 1,
            'available_person_days' => 100,
            'status' => 'APPROVED',
            'is_current_revision' => true,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'created_by' => $admin->id,
        ]);
        ArmisWorkloadAllocation::query()->create([
            'workload_family_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'resource_profile_id' => $profile->id,
            'version_number' => 1,
            'is_current_revision' => true,
            'source_module' => 'ARMIS',
            'source_type' => 'TEST',
            'source_id' => 1,
            'fiscal_year' => 2027,
            'planned_person_days' => 40,
            'status' => 'APPROVED',
            'created_by' => $admin->id,
        ]);

        Sanctum::actingAs($actor);
        $this->postJson('/api/armis/reports/resource-utilization/generate', ['fiscalYear' => 2027, 'search' => 'ARMIS-RPT-001'])
            ->assertCreated()
            ->assertJsonPath('data.run.rowCount', 1)
            ->assertJsonPath('data.run.rows.0.resourceCode', 'ARMIS-RPT-001')
            ->assertJsonPath('data.run.rows.0.plannedWorkload', '40.00');
        $this->postJson('/api/armis/reports/capacity-workload/generate', ['fiscalYear' => 2027, 'search' => 'ARMIS-RPT-001'])
            ->assertCreated()
            ->assertJsonPath('data.run.rowCount', 1)
            ->assertJsonPath('data.run.rows.0.capacityStatus', 'WITHIN_CAPACITY');
    }

    /** @param array<string, mixed> $snapshot */
    private function createRun(User $actor, array $snapshot, array $profileIds = []): ArmisReportRun
    {
        return ArmisReportRun::query()->create([
            'report_code' => 'resource-utilization',
            'report_title' => 'ARMIS Resource Utilization Report',
            'source_query_version' => 'ARMIS-5A-v1',
            'filters' => [],
            'scope_snapshot' => [
                'profileIds' => $profileIds,
                'visibility' => ['officeScope' => 'ALL'],
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
