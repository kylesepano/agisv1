<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the complete, non-destructive demonstration dataset for Render Free.
 *
 * Baseline reference data is owned by ProductionSeeder and is intentionally
 * not called here. This runner is invoked only when RUN_FULL_DEMO_SEEDERS is
 * explicitly enabled by the deployment startup script.
 */
class RenderDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
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
        });
    }
}
