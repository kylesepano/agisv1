<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CmsEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_escalation_schema_routes_and_permissions_are_registered(): void
    {
        foreach ([
            'cms_escalations', 'cms_escalation_notice_versions', 'cms_escalation_recipients',
            'cms_escalation_acknowledgements', 'cms_escalation_responses',
            'cms_escalation_response_versions', 'cms_escalation_notice_evidence_links',
            'cms_escalation_response_evidence_links', 'cms_escalation_resolutions',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertSame(14, Permission::query()->where('code', 'like', 'cms.escalation.%')->count());
        $this->assertSame(4, Permission::query()->where('code', 'like', 'cms.escalation-evidence.%')->count());
        $this->assertTrue($this->user('departmenthead')->hasPermission('cms.escalation.issue'));
        $this->assertTrue($this->user('auditee')->hasPermission('cms.escalation.respond'));
        $this->assertFalse($this->user('auditee')->hasPermission('cms.escalation.issue'));
        $this->assertFalse($this->user('agisadmin')->hasPermission('cms.escalation.issue'));

        $routes = collect(Route::getRoutes())->filter(fn ($route): bool => str_contains($route->uri(), 'cms/escalation'));
        $this->assertGreaterThanOrEqual(23, $routes->count());
    }

    private function user(string $username): User
    {
        return User::query()->with(['role.permissions', 'roles.permissions'])->where('username', $username)->firstOrFail();
    }
}
