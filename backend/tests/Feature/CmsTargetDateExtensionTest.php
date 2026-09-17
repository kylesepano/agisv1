<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CmsTargetDateExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_schema_permissions_and_route_contract_are_registered(): void
    {
        foreach ([
            'cms_target_date_extension_requests',
            'cms_target_date_extension_versions',
            'cms_target_date_extension_assessments',
            'cms_target_date_extension_decisions',
            'cms_target_date_extension_evidence_links',
            'cms_recommendation_target_date_history',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertSame(10, Permission::query()->where('code', 'like', 'cms.extension.%')->count());
        $this->assertSame(4, Permission::query()->where('code', 'like', 'cms.extension-evidence.%')->count());
        $this->assertSame(125, Permission::query()->where('code', 'like', 'cms.%')->count());

        $routes = collect(Route::getRoutes())->filter(
            fn ($route): bool => str_contains($route->uri(), 'cms/extensions')
                || str_contains($route->uri(), 'cms/extension-evidence')
                || str_contains($route->uri(), 'cms/recommendations/{recommendation}/extensions'),
        );
        $this->assertGreaterThanOrEqual(12, $routes->count());
        $this->assertSame(1, $routes->filter(fn ($route): bool => str_ends_with($route->uri(), 'cms/extensions/{extension}'))->count());
    }

    public function test_role_separation_preserves_responsible_preparer_and_decision_permissions(): void
    {
        $management = $this->user('departmenthead');
        $auditee = $this->user('auditee');
        $readOnly = $this->user('mayor');
        $administrator = $this->user('admin');

        $this->assertTrue($management->hasPermission('cms.extension.approve'));
        $this->assertTrue($management->hasPermission('cms.extension-evidence.download'));
        $this->assertTrue($auditee->hasPermission('cms.extension.create'));
        $this->assertTrue($auditee->hasPermission('cms.extension.submit'));
        $this->assertTrue($auditee->hasPermission('cms.extension-evidence.upload'));
        $this->assertTrue($readOnly->hasPermission('cms.extension.view'));
        $this->assertFalse($readOnly->hasPermission('cms.extension.approve'));
        $this->assertTrue($administrator->hasPermission('cms.extension.approve'));
    }

    private function user(string $username): User
    {
        return User::query()
            ->with(['office', 'role.permissions', 'roles.permissions'])
            ->where('username', $username)
            ->firstOrFail();
    }
}
