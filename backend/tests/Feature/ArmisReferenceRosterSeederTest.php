<?php

namespace Tests\Feature;

use App\Models\ArmisResourceProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArmisReferenceRosterSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_armis_reference_roster_seeds_eleven_idempotent_profiles(): void
    {
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(11, ArmisResourceProfile::query()->count());
        $this->assertSame(10, User::query()->where('office_id', User::query()->where('username', 'departmenthead')->value('office_id'))->whereIn('employee_id', [
            'CIAS-AUD-001', 'CIAS-AUD-002', 'CIAS-AUD-003', 'CIAS-AUD-004',
            'CIAS-ADM-001', 'CIAS-ADM-002', 'CIAS-ADM-003', 'CIAS-ADM-004', 'CIAS-ADM-005',
            'CIAS-HEAD-001',
        ])->count());

        $this->assertDatabaseHas('users', [
            'username' => 'auditor',
            'name' => 'Marissa Barcellona',
            'employee_id' => 'CIAS-AUD-004',
            'position' => 'Lead Auditor',
            'employment_type' => 'Plantilla',
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'jhonel.mira',
            'position' => 'Information System Analyst',
            'employment_type' => 'JO',
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'agisadmin',
            'name' => 'Kim V. Lao',
            'position' => 'Project Manager',
            'employment_type' => 'Consultant',
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'cias.employee',
            'employee_id' => 'CIAS-COMPAT-001',
            'position' => 'Internal Auditor',
        ]);
        $this->assertSame(0, ArmisResourceProfile::query()
            ->where('user_id', User::query()->where('username', 'cias.employee')->value('id'))
            ->count());
        $this->assertDatabaseHas('armis_resource_profiles', [
            'resource_code' => 'ARMIS-CIAS-HEAD-001',
            'category' => 'REVIEWER',
            'status' => 'ACTIVE',
        ]);
        $this->assertDatabaseHas('armis_resource_profiles', [
            'resource_code' => 'ARMIS-AGIS-PM-001',
            'category' => 'SPECIALIST',
            'status' => 'ACTIVE',
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(11, ArmisResourceProfile::query()->count());
        $this->assertSame(11, ArmisResourceProfile::query()->distinct('resource_code')->count('resource_code'));
    }
}
