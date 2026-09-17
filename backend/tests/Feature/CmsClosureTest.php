<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CmsClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_closure_schema_permissions_and_routes_are_registered(): void
    {
        foreach (['cms_closure_requests', 'cms_closure_request_versions', 'cms_closure_review_assessments', 'cms_closure_decisions', 'cms_closure_evidence_links'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertSame(10, Permission::where('code', 'like', 'cms.closure.%')->count());
        $this->assertSame(4, Permission::where('code', 'like', 'cms.closure-evidence.%')->count());
        $this->assertTrue($this->user('departmenthead')->hasPermission('cms.closure.approve'));
        $this->assertTrue($this->user('auditee')->hasPermission('cms.closure.request'));
        $this->assertFalse($this->user('auditee')->hasPermission('cms.closure.approve'));
        $this->assertFalse($this->user('agisadmin')->hasPermission('cms.closure.approve'));

        $routes = collect(Route::getRoutes())->filter(fn ($route): bool => str_contains($route->uri(), 'cms/closure'));
        $this->assertSame(12, $routes->count());
    }

    private function user(string $username): User
    {
        return User::query()->with(['role.permissions', 'roles.permissions'])->where('username', $username)->firstOrFail();
    }
}
