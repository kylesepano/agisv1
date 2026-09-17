<?php

namespace Tests\Feature\Api;

use App\Models\CoreReportRun;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoreDashboardAndAdministrativeReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_dashboard_is_live_scope_aware_and_protected(): void
    {
        $actor = $this->user('agisadmin');
        Sanctum::actingAs($actor);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.modules.0.code', 'IAP')
            ->assertJsonStructure(['data' => ['asOf', 'scope', 'modules', 'tasks', 'recentEngagements', 'quickActions']]);

        Sanctum::actingAs($this->user('auditee'));
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.scope.engagement', 'ASSIGNED');
    }

    public function test_administrative_report_snapshot_exports_are_protected_and_immutable(): void
    {
        $actor = $this->user('agisadmin');
        Sanctum::actingAs($actor);

        $this->getJson('/api/administrative-reports')
            ->assertOk()
            ->assertJsonCount(4, 'data.reports')
            ->assertJsonPath('data.canExport', true);

        $response = $this->postJson('/api/administrative-reports/office-directory/generate');
        $response->assertCreated()->assertJsonPath('data.run.report_code', 'office-directory');
        $run = CoreReportRun::query()->firstOrFail();
        $this->assertGreaterThan(0, $run->row_count);
        $this->assertSame(hash('sha256', json_encode($run->result_snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), $run->result_checksum_sha256);

        $export = $this->postJson('/api/administrative-reports/runs/'.$run->id.'/exports', ['format' => 'csv'])
            ->assertCreated()->json('data.export');
        $this->get('/api/administrative-report-exports/'.$export['id'].'/download')->assertOk()->assertHeader('X-AGIS-Checksum-SHA256', $export['checksum_sha256']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'core.report.generated', 'user_id' => $actor->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'core.report.exported', 'user_id' => $actor->id]);
        $this->expectException(\LogicException::class);
        $run->update(['report_title' => 'Tampered']);
    }

    public function test_reports_require_the_core_permission(): void
    {
        Sanctum::actingAs($this->user('auditee'));
        $this->getJson('/api/administrative-reports')->assertForbidden();
    }

    private function user(string $username): User
    {
        return User::query()->with(['role.permissions', 'roles.permissions'])->where('username', $username)->firstOrFail();
    }
}
