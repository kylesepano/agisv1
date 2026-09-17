<?php

namespace Tests\Feature;

use App\Models\CmsAutomationRule;
use App\Models\CmsAutomationRuleVersion;
use App\Models\CmsAutomationRun;
use App\Models\Permission;
use App\Models\User;
use App\Services\Cms\CmsAutomationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class CmsAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_schema_defaults_permissions_and_routes_are_registered(): void
    {
        foreach ([
            'cms_automation_rules', 'cms_automation_rule_versions', 'cms_automation_runs',
            'cms_automation_actions', 'cms_closure_candidates', 'cms_escalation_candidates',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertSame(5, Permission::query()->where('code', 'like', 'cms.automation.%')->count());
        $this->assertTrue($this->user('agisadmin')->hasPermission('cms.automation.manage'));
        $this->assertTrue($this->user('departmenthead')->hasPermission('cms.automation.review'));
        $this->assertTrue($this->user('auditor')->hasPermission('cms.automation.view'));
        $this->assertFalse($this->user('auditee')->hasPermission('cms.automation.review'));
        $this->assertSame(3, CmsAutomationRule::query()->count());
        $this->assertSame(3, CmsAutomationRuleVersion::query()->count());
        $this->assertGreaterThanOrEqual(9, collect(Route::getRoutes())->filter(fn ($route): bool => str_contains($route->uri(), 'cms/automation'))->count());
    }

    public function test_rule_versions_are_immutable_and_runs_are_idempotent(): void
    {
        $rule = CmsAutomationRule::query()->where('rule_code', 'CMS_CLOSURE_READINESS_DAILY')->firstOrFail();
        try {
            $rule->currentVersion->name = 'invalid';
            $rule->currentVersion->save();
            $this->fail('Automation rule versions must be immutable.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $actor = $this->user('departmenthead');
        $service = app(CmsAutomationService::class);
        $this->assertSame(0, $service->run($actor, 'CMS_CLOSURE_READINESS_DAILY'));
        $this->assertSame(0, $service->run($actor, 'CMS_CLOSURE_READINESS_DAILY'));
        $this->assertSame(1, CmsAutomationRun::query()->where('run_key', 'cms-automation:CMS_CLOSURE_READINESS_DAILY:'.now()->toDateString())->count());
    }

    public function test_automation_contract_is_scope_and_permission_protected(): void
    {
        Sanctum::actingAs($this->user('departmenthead'));
        $this->getJson('/api/cms/automation/dashboard')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/cms/automation/rules')->assertOk()->assertJsonCount(3, 'data.rules');

        Sanctum::actingAs($this->user('auditee'));
        $this->postJson('/api/cms/automation/run')->assertForbidden();
    }

    private function user(string $username): User
    {
        return User::query()->with(['role.permissions', 'roles.permissions'])->where('username', $username)->firstOrFail();
    }
}
