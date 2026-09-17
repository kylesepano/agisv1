<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAccountControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_multiple_roles_combine_permissions_and_preserve_a_primary_role(): void
    {
        $administrator = $this->user('admin');
        $target = $this->user('auditee');
        $primaryRole = Role::query()->where('code', 'auditee_representative')->firstOrFail();
        $secondaryRole = Role::query()->where('code', 'agis_user')->firstOrFail();
        Sanctum::actingAs($administrator);

        $this->putJson("/api/users/{$target->id}", [
            ...$this->payload($target),
            'roleIds' => [$primaryRole->id, $secondaryRole->id],
            'primaryRoleId' => $primaryRole->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.primaryRoleId', $primaryRole->id)
            ->assertJsonCount(2, 'data.user.roles')
            ->assertJsonFragment([
                'code' => 'auditee_representative',
                'isPrimary' => true,
            ])
            ->assertJsonFragment([
                'code' => 'agis_user',
                'isPrimary' => false,
            ]);

        $target->refresh()->load(['role.permissions', 'roles.permissions']);
        $this->assertTrue($target->hasRole('auditee_representative'));
        $this->assertTrue($target->hasRole('agis_user'));
        $this->assertTrue($target->hasPermission('cms.submit_evidence'));
        $this->assertTrue($target->hasPermission('iap.assess_risk'));
        $this->assertSame($primaryRole->id, $target->role_id);
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $target->id,
            'role_id' => $secondaryRole->id,
            'is_primary' => false,
        ]);

        $this->getJson("/api/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.user.permissionsCount', count($target->fresh()->effectiveRoles()
                ->flatMap(fn (Role $role) => $role->permissions)
                ->unique('id')))
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'roles',
                        'permissionDetails',
                        'activeAssignments',
                        'activityHistory',
                    ],
                ],
            ]);
    }

    public function test_account_activation_locking_unlocking_and_disabling_are_separate_logged_controls(): void
    {
        $administrator = $this->user('admin');
        $target = $this->user('auditor');
        Sanctum::actingAs($administrator);

        $this->putJson("/api/users/{$target->id}", [
            ...$this->payload($target),
            'roleId' => $target->role_id,
            'isActive' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isActive');

        $this->postJson("/api/users/{$target->id}/lock")
            ->assertOk()
            ->assertJsonPath('data.user.isLocked', true)
            ->assertJsonPath('data.user.isManuallyLocked', true);
        $this->assertDatabaseHas('activity_logs', [
            'subject_user_id' => $target->id,
            'action' => 'user.locked',
        ]);

        $this->postJson('/api/login', [
            'employeeId' => $target->employee_id,
            'password' => 'lala',
        ])->assertStatus(423);

        Sanctum::actingAs($administrator);
        $this->postJson("/api/users/{$target->id}/unlock")
            ->assertOk()
            ->assertJsonPath('data.user.isLocked', false)
            ->assertJsonPath('data.user.failedLoginAttempts', 0);

        $this->postJson("/api/users/{$target->id}/disable")
            ->assertOk()
            ->assertJsonPath('data.user.isActive', false)
            ->assertJsonPath('data.user.isArchived', false);
        $this->assertNotSoftDeleted('users', ['id' => $target->id]);

        $this->postJson('/api/login', [
            'employeeId' => $target->employee_id,
            'password' => 'lala',
        ])->assertUnprocessable();

        Sanctum::actingAs($administrator);
        $this->postJson("/api/users/{$target->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.user.isActive', true)
            ->assertJsonPath('data.user.isArchived', false);

        $this->getJson("/api/users/{$target->id}")
            ->assertOk()
            ->assertJsonFragment(['action' => 'user.locked'])
            ->assertJsonFragment(['action' => 'user.unlocked'])
            ->assertJsonFragment(['action' => 'user.disabled'])
            ->assertJsonFragment(['action' => 'user.activated']);
    }

    /** @return array<string, mixed> */
    private function payload(User $user): array
    {
        return [
            'employeeId' => $user->employee_id,
            'email' => $user->email,
            'firstName' => $user->first_name,
            'middleName' => $user->middle_name,
            'lastName' => $user->last_name,
            'extension' => $user->name_extension,
            'position' => $user->position,
            'employmentType' => $user->employment_type,
            'contactNumber' => $user->contact_number,
            'birthDate' => $user->birth_date?->toDateString(),
            'isOfficeHead' => $user->is_office_head,
            'officeId' => $user->office_id,
        ];
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
