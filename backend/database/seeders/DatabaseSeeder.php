<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Runs seeders in dependency order to create a coherent demonstration dataset.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MasterListSeeder::class,
            OfficeSeeder::class,
            RolePermissionSeeder::class,
            CmsAutomationRuleSeeder::class,
            WorkflowSeeder::class,
            AuditAreaSeeder::class,
            SystemConfigurationSeeder::class,
        ]);

        if (config('demo.enabled')) {
            $this->call([
                DemoUserSeeder::class,
                CoreUserSeeder::class,
                ArmisResourceProfileSeeder::class,
                AuditUniverseSeeder::class,
                SiapSeeder::class,
                IapRiskPeriodSeeder::class,
                IapPrioritizationSeeder::class,
                IapSchedulingSeeder::class,
                ArmisResourcePlanningSeeder::class,
                NotificationSeeder::class,
            ]);
        }
    }
}
