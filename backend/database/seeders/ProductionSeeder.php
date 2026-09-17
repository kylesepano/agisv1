<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Idempotent, non-demo reference data approved for a first Render deployment.
 * This deliberately excludes all demo users and sample operational records.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MasterListSeeder::class,
            OfficeSeeder::class,
            RolePermissionSeeder::class,
            WorkflowSeeder::class,
            AuditAreaSeeder::class,
            SystemConfigurationSeeder::class,
        ]);
    }
}
