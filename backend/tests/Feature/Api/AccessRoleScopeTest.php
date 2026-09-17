<?php

namespace Tests\Feature\Api;

use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessRoleScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_role_cloning_copies_permissions_and_configurable_scopes(): void
    {
        $administrator = $this->user('admin');
        $source = Role::query()
            ->where('code', 'agis_user')
            ->with('permissions:id')
            ->firstOrFail();
        Sanctum::actingAs($administrator);

        $response = $this->postJson("/api/roles/{$source->id}/clone", [
            'code' => 'field auditor',
            'name' => 'Field Auditor',
            'description' => 'A scoped copy for field audit work.',
            'isActive' => true,
            'officeAccessScope' => 'OWN_OFFICE',
            'engagementAccessScope' => 'ASSIGNED',
            'permissionIds' => $source->permissions->pluck('id')->all(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.role.code', 'field_auditor')
            ->assertJsonPath('data.role.isSystem', false)
            ->assertJsonPath('data.role.officeAccessScope', 'OWN_OFFICE')
            ->assertJsonPath('data.role.engagementAccessScope', 'ASSIGNED')
            ->assertJsonCount($source->permissions->count(), 'data.role.permissionIds');

        $cloneId = $response->json('data.role.id');
        $this->assertDatabaseHas('roles', [
            'id' => $cloneId,
            'office_access_scope' => 'OWN_OFFICE',
            'engagement_access_scope' => 'ASSIGNED',
        ]);
        $this->assertSame(
            $source->permissions->count(),
            Role::query()->findOrFail($cloneId)->permissions()->count(),
        );
        $this->assertDatabaseHas('activity_logs', ['action' => 'role.cloned']);

        Sanctum::actingAs($this->user('agisadmin'));
        $platform = Role::query()->where('code', 'platform_admin')->firstOrFail();
        $this->postJson("/api/roles/{$platform->id}/clone", [
            'code' => 'platform copy',
            'name' => 'Platform Copy',
            'isActive' => true,
            'permissionIds' => $platform->permissions()->pluck('permissions.id')->all(),
        ])->assertForbidden();
    }

    public function test_office_scope_is_enforced_and_multiple_roles_use_the_broader_scope(): void
    {
        $target = $this->user('auditee');
        $ownOfficeId = $target->office_id;
        $permissions = Permission::query()
            ->whereIn('code', ['offices.view', 'users.view'])
            ->get();
        $narrowRole = Role::query()->create([
            'code' => 'office_reviewer',
            'name' => 'Office Reviewer',
            'is_active' => true,
            'office_access_scope' => 'OWN_OFFICE',
            'engagement_access_scope' => 'ASSIGNED',
        ]);
        $narrowRole->permissions()->sync($permissions->pluck('id'));
        $target->syncRoleAssignments([$narrowRole->id], $narrowRole->id);

        Sanctum::actingAs($target->fresh());
        $this->getJson('/api/offices')
            ->assertOk()
            ->assertJsonCount(1, 'data.offices')
            ->assertJsonPath('data.offices.0.id', $ownOfficeId);
        $users = $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonMissingPath('data.users.0.password')
            ->json('data.users');
        $this->assertNotEmpty($users);
        $this->assertTrue(
            collect($users)->every(
                fn (array $user): bool => (int) $user['officeId'] === (int) $ownOfficeId,
            ),
        );
        $this->getJson('/api/users/'.$this->user('admin')->id)->assertForbidden();

        $globalRole = Role::query()->create([
            'code' => 'citywide_reviewer',
            'name' => 'Citywide Reviewer',
            'is_active' => true,
            'office_access_scope' => 'ALL',
            'engagement_access_scope' => 'ASSIGNED',
        ]);
        $globalRole->permissions()->sync($permissions->pluck('id'));
        $target->syncRoleAssignments(
            [$narrowRole->id, $globalRole->id],
            $narrowRole->id,
        );

        $broaderUser = $target->fresh();
        $this->assertTrue($broaderUser->hasGlobalOfficeAccess());
        Sanctum::actingAs($broaderUser);
        $this->getJson('/api/offices')
            ->assertOk()
            ->assertJsonCount(
                Office::query()->count(),
                'data.offices',
            );
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
