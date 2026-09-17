<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Support\PersonName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

/**
 * Seeds city offices, office heads, employees, auditors, and administration users.
 */
class CoreUserSeeder extends Seeder
{
    /**
     * The CIAS reference roster used by ARMIS and the engagement seed data.
     * The existing departmenthead/auditor/cias.employee usernames are
     * retained as stable compatibility accounts for tests and local
     * demonstration login.
     *
     * @var list<array{username:string,name:string,employee_id:string,role:string,position:string,employment_type:string,is_office_head:bool}>
     */
    private const CIAS_REFERENCE_EMPLOYEES = [
        ['username' => 'charry.bagongon', 'name' => 'Charry Bagongon', 'employee_id' => 'CIAS-AUD-001', 'role' => 'agis_user', 'position' => 'Auditor', 'employment_type' => 'Plantilla', 'is_office_head' => false],
        ['username' => 'kristine.yare', 'name' => 'Kristine Yare', 'employee_id' => 'CIAS-AUD-002', 'role' => 'agis_user', 'position' => 'Auditor', 'employment_type' => 'Plantilla', 'is_office_head' => false],
        ['username' => 'michele.dampog', 'name' => 'Michele Dampog', 'employee_id' => 'CIAS-AUD-003', 'role' => 'agis_user', 'position' => 'Auditor', 'employment_type' => 'Plantilla', 'is_office_head' => false],
        ['username' => 'auditor', 'name' => 'Marissa Barcellona', 'employee_id' => 'CIAS-AUD-004', 'role' => 'agis_user', 'position' => 'Lead Auditor', 'employment_type' => 'Plantilla', 'is_office_head' => false],
        ['username' => 'daphny.roa', 'name' => 'Daphny Roa', 'employee_id' => 'CIAS-ADM-001', 'role' => 'agis_user', 'position' => 'Supervising Admin Officer', 'employment_type' => 'Plantilla', 'is_office_head' => false],
        ['username' => 'sherly.lasacar', 'name' => 'Sherly Lasacar', 'employee_id' => 'CIAS-ADM-002', 'role' => 'agis_user', 'position' => 'Admin Officer', 'employment_type' => 'Plantilla', 'is_office_head' => false],
        ['username' => 'cookie.lee', 'name' => 'Cookie Lee', 'employee_id' => 'CIAS-ADM-003', 'role' => 'agis_user', 'position' => 'Liaison Officer', 'employment_type' => 'Plantilla', 'is_office_head' => false],
        ['username' => 'jhonel.mira', 'name' => 'Jhonel Mira', 'employee_id' => 'CIAS-ADM-004', 'role' => 'agis_user', 'position' => 'Information System Analyst', 'employment_type' => 'JO', 'is_office_head' => false],
        ['username' => 'kyle.dacalos', 'name' => 'Kyle Dacalos', 'employee_id' => 'CIAS-ADM-005', 'role' => 'agis_user', 'position' => 'Information System Analyst', 'employment_type' => 'JO', 'is_office_head' => false],
        ['username' => 'departmenthead', 'name' => 'Cherrybelle A. Lao', 'employee_id' => 'CIAS-HEAD-001', 'role' => 'cias_management', 'position' => 'CIAS Head', 'employment_type' => 'Plantilla', 'is_office_head' => true],
    ];

    public function run(): void
    {
        $password = config('demo.default_password');

        if (! is_string($password) || $password === '') {
            throw new LogicException('DEMO_DEFAULT_PASSWORD is required before seeding Core users.');
        }

        $auditeeRole = Role::query()->where('code', 'auditee_representative')->firstOrFail();

        foreach (OfficeSeeder::DEMO_OFFICES as $officeData) {
            if ($officeData['code'] === 'AGIS-SYS') {
                continue;
            }

            $office = Office::query()->where('code', $officeData['code'])->firstOrFail();
            $usernamePrefix = Str::of($officeData['code'])->lower()->replace('-', '_')->toString();

            if ($officeData['code'] === 'CIAS') {
                foreach (self::CIAS_REFERENCE_EMPLOYEES as $employee) {
                    $role = Role::query()->where('code', $employee['role'])->firstOrFail();
                    $this->upsertUser(
                        $employee['username'],
                        $employee['name'],
                        $office,
                        $role,
                        $employee['position'],
                        $employee['is_office_head'],
                        $password,
                        $employee['employee_id'],
                        $employee['employment_type'],
                        $officeData['contact_number'],
                    );
                }

                // Keep the historical test/demo login, but do not create an
                // ARMIS profile for it: it is a compatibility alias, not an
                // additional reference-roster employee.
                $this->upsertUser(
                    'cias.employee',
                    'CIAS Demo Auditor',
                    $office,
                    Role::query()->where('code', 'agis_user')->firstOrFail(),
                    'Internal Auditor',
                    false,
                    $password,
                    'CIAS-COMPAT-001',
                    'Permanent',
                    $officeData['contact_number'],
                );

                continue;
            }

            $this->upsertUser(
                "{$usernamePrefix}.head",
                $officeData['head_name'],
                $office,
                $auditeeRole,
                'Office Head',
                true,
                $password,
            );
            $this->upsertUser(
                "{$usernamePrefix}.employee",
                "{$officeData['acronym']} Demo Employee",
                $office,
                $auditeeRole,
                'Office Employee',
                false,
                $password,
            );
        }
    }

    private function upsertUser(
        string $username,
        string $name,
        Office $office,
        Role $role,
        string $position,
        bool $isOfficeHead,
        string $password,
        ?string $employeeId = null,
        string $employmentType = 'Permanent',
        ?string $contactNumber = null,
    ): void {
        $normalizedName = PersonName::parse($name);

        $user = User::withTrashed()->updateOrCreate(
            ['username' => $username],
            [
                'role_id' => $role->id,
                'office_id' => $office->id,
                'employee_id' => $employeeId ?? Str::upper(str_replace('.', '-', $username)),
                'email' => str_replace('.', '-', $username) . '@agis.local',
                ...$normalizedName,
                'position' => $position,
                'employment_type' => $employmentType,
                'contact_number' => $contactNumber,
                'password' => Hash::make($password),
                'is_office_head' => $isOfficeHead,
                'is_active' => true,
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'lock_version' => 0,
            ],
        );

        if ($user->trashed()) {
            $user->restore();
        }

        $user->syncRoleAssignments([$role->id], $role->id);
    }
}
