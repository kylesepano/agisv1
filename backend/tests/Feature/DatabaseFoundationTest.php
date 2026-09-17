<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_users_are_not_seeded_when_demo_mode_is_disabled(): void
    {
        config(['demo.enabled' => false]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 6);
        $this->assertDatabaseCount('permissions', 483);
    }

    public function test_demo_accounts_are_hashed_and_receive_role_permissions(): void
    {
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);

        $accounts = collect(config('demo.accounts'));

        $this->assertDatabaseCount('users', 97);

        Role::query()
            ->withCount('users')
            ->get()
            ->each(fn (Role $role) => $this->assertGreaterThanOrEqual(
                1,
                $role->users_count,
                "The {$role->name} role must have at least one seeded user.",
            ));

        foreach ($accounts as $account) {
            $user = User::query()
                ->with(['office', 'role.permissions'])
                ->where('username', $account['username'])
                ->firstOrFail();

            $this->assertTrue(Hash::check($account['password'], $user->password));
            $this->assertSame($account['name'], $user->name);
            $this->assertSame($account['roleCode'], $user->role->code);
            $this->assertSame($account['office'], $user->office->name);
        }

        $employeeIds = User::query()->pluck('employee_id');
        $this->assertNotContains(null, $employeeIds);
        $this->assertSame($employeeIds->count(), $employeeIds->unique()->count());

        $administrator = User::query()->where('username', 'admin')->firstOrFail();
        $agisAdministrator = User::query()->where('username', 'agisadmin')->firstOrFail();
        $departmentHead = User::query()->where('username', 'departmenthead')->firstOrFail();
        $auditor = User::query()->where('username', 'auditor')->firstOrFail();
        $auditee = User::query()->where('username', 'auditee')->firstOrFail();
        $mayor = User::query()->where('username', 'mayor')->firstOrFail();

        $this->assertTrue($administrator->hasPermission('system_configuration.manage'));
        $this->assertTrue($agisAdministrator->hasPermission('master_lists.manage'));
        $this->assertTrue($departmentHead->hasPermission('iap.approve'));
        $this->assertFalse($departmentHead->hasPermission('system_configuration.manage'));
        $this->assertFalse($departmentHead->hasPermission('roles.view'));
        $this->assertTrue($auditor->hasPermission('afr.create'));
        $this->assertFalse($auditor->hasPermission('afr.approve'));
        $this->assertFalse($auditor->hasPermission('users.view'));
        $this->assertFalse($auditor->hasPermission('audit_logs.view'));
        $this->assertTrue($auditee->hasPermission('cms.submit_evidence'));
        $this->assertFalse($auditee->hasPermission('iap.create'));
        $this->assertTrue($mayor->hasPermission('administrative_reports.view'));
        $this->assertFalse($mayor->hasPermission('offices.update'));
    }

    public function test_audit_log_values_are_cast_and_related_to_the_actor(): void
    {
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('username', 'admin')->firstOrFail();
        $log = AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => 'user.updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => ['is_active' => false],
            'new_values' => ['is_active' => true],
            'metadata' => ['source' => 'test'],
        ]);

        $this->assertSame($user->id, $log->user->id);
        $this->assertSame(['is_active' => false], $log->old_values);
        $this->assertSame(['source' => 'test'], $log->metadata);
        $this->assertTrue($log->auditable->is($user));
    }
}
