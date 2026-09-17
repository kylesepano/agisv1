<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArmisSecurityReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_every_armis_api_route_is_authenticated_and_permission_protected(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/armis'))
            ->values();

        $this->assertGreaterThan(70, $routes->count());

        foreach ($routes as $route) {
            $middleware = $route->middleware();
            $this->assertContains('auth:sanctum', $middleware, "{$route->methods()[0]} {$route->uri()} must require Sanctum authentication.");
            $this->assertTrue(
                collect($middleware)->contains(fn (string $item): bool => str_starts_with($item, 'permission:armis.')),
                "{$route->methods()[0]} {$route->uri()} must require a granular ARMIS permission.",
            );
        }
    }

    public function test_representative_armis_endpoints_reject_anonymous_and_read_only_mutations(): void
    {
        foreach ([
            '/api/armis/resources',
            '/api/armis/reports',
            '/api/armis/provider/status',
            '/api/armis/provider/monitoring/status',
        ] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }

        Sanctum::actingAs($this->readOnlyUser());
        $this->postJson('/api/armis/resources', [
            'userId' => $this->user('auditor')->id,
            'officeId' => $this->user('auditor')->office_id,
            'category' => 'AUDIT_RESOURCE',
        ])->assertForbidden();
        $this->postJson('/api/armis/provider/monitoring/checks')->assertForbidden();
        $this->postJson('/api/armis/provider/rollback', ['reason' => 'Read-only user cannot change provider authority.'])->assertForbidden();
    }

    public function test_monitoring_and_provider_authority_permissions_remain_separate(): void
    {
        $admin = $this->user('agisadmin');
        $departmentHead = $this->user('departmenthead');
        $readOnly = $this->readOnlyUser();

        $this->assertTrue($admin->hasPermission('armis.provider.view'));
        $this->assertTrue($admin->hasPermission('armis.provider.monitor'));
        $this->assertFalse($admin->hasPermission('armis.provider.switch'));
        $this->assertTrue($departmentHead->hasPermission('armis.provider.switch'));
        $this->assertTrue($departmentHead->hasPermission('armis.provider.monitor'));
        $this->assertTrue($readOnly->hasPermission('armis.provider.view'));
        $this->assertFalse($readOnly->hasPermission('armis.provider.monitor'));
        $this->assertFalse($readOnly->hasPermission('armis.provider.rollback'));
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }

    private function readOnlyUser(): User
    {
        $role = Role::query()->where('code', 'read_only')->firstOrFail();

        $user = User::factory()->create([
            'role_id' => $role->id,
            'office_id' => $this->user('auditor')->office_id,
            'username' => 'armis-security-read-only',
            'employee_id' => 'ARMIS-SEC-RO-001',
        ]);

        $user->syncRoleAssignments([$role->id], $role->id);

        return $user->fresh();
    }
}
