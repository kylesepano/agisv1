<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Support\PersonName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

/**
 * Seeds the primary role-based accounts displayed on the login page.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = config('demo.accounts', []);

        foreach ($accounts as $account) {
            $password = $account['password'] ?? null;

            if (! is_string($password) || $password === '') {
                throw new LogicException(
                    "A password is required before seeding the {$account['username']} demo account.",
                );
            }

            $role = Role::query()->where('code', $account['roleCode'])->firstOrFail();
            $office = Office::query()->where('name', $account['office'])->firstOrFail();
            $name = PersonName::fromParts(
                $account['firstName'],
                $account['middleName'] ?? null,
                $account['lastName'],
                $account['extension'] ?? null,
            );

            $user = User::withTrashed()->updateOrCreate(
                ['username' => $account['username']],
                [
                    'role_id' => $role->id,
                    'office_id' => $office->id,
                    'employee_id' => $account['employeeId'],
                    'email' => "{$account['username']}@agis.local",
                    ...$name,
                    'position' => $account['position'] ?? $role->name,
                    'employment_type' => $account['employmentType'] ?? 'Permanent',
                    'password' => Hash::make($password),
                    'is_active' => true,
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                    'is_manually_locked' => false,
                    'manually_locked_at' => null,
                    'manually_locked_by' => null,
                    'lock_version' => 0,
                ],
            );

            if ($user->trashed()) {
                $user->restore();
            }

            $user->syncRoleAssignments([$role->id], $role->id);
        }
    }
}
