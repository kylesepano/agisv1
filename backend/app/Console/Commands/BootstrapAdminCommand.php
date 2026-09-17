<?php

namespace App\Console\Commands;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Guarded one-time administrator bootstrap for environments without an
 * existing account-management path. Credentials are read from environment
 * variables and are never printed.
 */
class BootstrapAdminCommand extends Command
{
    protected $signature = 'agis:bootstrap-admin';

    protected $description = 'Create the explicitly enabled initial AGIS administrator once';

    public function handle(): int
    {
        if (! filter_var(env('BOOTSTRAP_ADMIN_ENABLED', false), FILTER_VALIDATE_BOOL)) {
            $this->line('Initial administrator bootstrap is disabled.');

            return self::SUCCESS;
        }

        $values = [
            'employee_id' => trim((string) env('BOOTSTRAP_ADMIN_EMPLOYEE_ID')),
            'username' => trim((string) env('BOOTSTRAP_ADMIN_USERNAME')),
            'email' => trim((string) env('BOOTSTRAP_ADMIN_EMAIL')),
            'first_name' => trim((string) env('BOOTSTRAP_ADMIN_FIRST_NAME')),
            'last_name' => trim((string) env('BOOTSTRAP_ADMIN_LAST_NAME')),
            'password' => (string) env('BOOTSTRAP_ADMIN_PASSWORD'),
        ];

        $validator = Validator::make($values, [
            'employee_id' => ['required', 'string', 'max:40'],
            'username' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:12', 'max:255'],
        ]);

        if ($validator->fails()) {
            $this->error('Initial administrator variables are incomplete or invalid.');

            return self::FAILURE;
        }

        $roleCode = trim((string) env('BOOTSTRAP_ADMIN_ROLE', 'agis_admin'));
        $officeCode = trim((string) env('BOOTSTRAP_ADMIN_OFFICE', 'AGIS-SYS'));
        $role = Role::query()->where('code', $roleCode)->first();
        $office = Office::query()->where('code', $officeCode)->first();

        if (! $role || ! $office) {
            $this->error('The configured bootstrap role or office does not exist. Run the approved production seeders first.');

            return self::FAILURE;
        }

        $existing = User::query()
            ->where('employee_id', $values['employee_id'])
            ->orWhere('username', $values['username'])
            ->orWhere('email', $values['email'])
            ->first();

        if ($existing) {
            $this->line('An account already exists for the configured bootstrap identity; no changes were made.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($values, $role, $office): void {
            $name = trim($values['first_name'].' '.$values['last_name']);
            $user = User::query()->create([
                'role_id' => $role->id,
                'office_id' => $office->id,
                'employee_id' => $values['employee_id'],
                'username' => $values['username'],
                'email' => $values['email'],
                'first_name' => $values['first_name'],
                'last_name' => $values['last_name'],
                'name' => $name,
                'position' => 'AGIS Administrator',
                'password' => $values['password'],
                'is_active' => true,
            ]);

            $user->syncRoleAssignments([$role->id], $role->id);
        });

        $this->info('Initial administrator created. Remove the bootstrap flag and credential variables now.');

        return self::SUCCESS;
    }
}
