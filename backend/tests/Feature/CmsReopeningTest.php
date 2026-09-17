<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CmsReopeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_reopening_schema_permission_catalogue_and_routes_are_registered(): void
    {
        foreach ([
            'cms_reopening_requests',
            'cms_reopening_request_versions',
            'cms_reopening_review_assessments',
            'cms_reopening_decisions',
            'cms_reopening_evidence_links',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertSame(10, Permission::where('code', 'like', 'cms.reopening.%')->count());
        $this->assertSame(4, Permission::where('code', 'like', 'cms.reopening-evidence.%')->count());
        $this->assertSame(125, Permission::where('code', 'like', 'cms.%')->count());
        $this->assertTrue($this->user('auditee')->hasPermission('cms.reopening.request'));
        $this->assertTrue($this->user('departmenthead')->hasPermission('cms.reopening.approve'));
        $this->assertFalse($this->user('auditee')->hasPermission('cms.reopening.approve'));
        $this->assertFalse($this->user('agisadmin')->hasPermission('cms.reopening.approve'));

        $routes = collect(Route::getRoutes())->filter(fn ($route): bool => str_contains($route->uri(), 'cms/reopening') || str_contains($route->uri(), '/reopenings'));
        $this->assertSame(14, $routes->count());
    }

    private function user(string $username): User
    {
        return User::query()->with(['role.permissions', 'roles.permissions'])->where('username', $username)->firstOrFail();
    }
}
