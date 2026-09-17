<?php

namespace Tests\Feature;

use App\Models\ArmisProviderMonitoringCheck;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class ArmisProviderMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_provider_check_is_immutable_audited_and_reports_read_path_diagnostics(): void
    {
        $admin = $this->user('agisadmin');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/armis/provider/monitoring/checks')
            ->assertCreated()
            ->assertJsonPath('data.check.sourceQueryVersion', 'ARMIS-6D-v1')
            ->assertJsonPath('data.check.scopeSnapshot.globalOfficeScope', true);
        $check = $response->json('data.check');

        $this->getJson('/api/armis/provider/monitoring/status')
            ->assertOk()
            ->assertJsonPath('data.latestCheck.id', $check['id'])
            ->assertJsonPath('data.monitoringControls.checkIsReadOnly', true);
        $this->getJson('/api/armis/provider/monitoring/checks/'.$check['id'])
            ->assertOk()
            ->assertJsonPath('data.check.resultChecksumSha256', $check['resultChecksumSha256']);

        $model = ArmisProviderMonitoringCheck::query()->findOrFail($check['id']);
        $this->expectException(LogicException::class);
        $model->overall_status = $model->overall_status === 'FAILED' ? 'PASSED' : 'FAILED';
        $model->save();
    }

    public function test_armis_monitoring_does_not_require_a_provider_switch_decision(): void
    {
        $admin = $this->user('agisadmin');
        Sanctum::actingAs($admin);

        $this->postJson('/api/armis/provider/monitoring/checks')
            ->assertCreated()
            ->assertJsonPath('data.check.overallStatus', 'HEALTHY')
            ->assertJsonPath('data.check.providerMode', 'ARMIS_AUTHORITATIVE')
            ->assertJsonPath('data.check.configuredMode', 'ARMIS_AUTHORITATIVE');
    }

    public function test_monitoring_run_requires_the_new_permission_and_global_scope(): void
    {
        Sanctum::actingAs($this->user('auditor'));
        $this->postJson('/api/armis/provider/monitoring/checks')->assertForbidden();

        $admin = $this->user('agisadmin');
        $this->assertTrue($admin->hasPermission('armis.provider.monitor'));
        $this->assertFalse($this->user('auditor')->hasPermission('armis.provider.monitor'));
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
