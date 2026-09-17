<?php

namespace Tests\Feature;

use App\Models\AuditEngagement;
use App\Models\CmsRecommendation;
use App\Models\Document;
use App\Models\IapPrioritizationRun;
use App\Models\IapRiskPeriod;
use App\Models\InternalAuditPlan;
use App\Models\StrategicInternalAuditPlan;
use App\Models\SystemNotification;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Database\Seeders\RenderDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RenderDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_render_demo_seeding_is_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('demo.full_render_seeders'));
    }

    public function test_render_demo_seeder_is_complete_and_repeatable(): void
    {
        $accounts = collect(config('demo.accounts'))
            ->map(fn (array $account): array => [...$account, 'password' => 'test-password'])
            ->all();
        config([
            'demo.enabled' => true,
            'demo.full_render_seeders' => true,
            'demo.default_password' => 'test-password',
            'demo.accounts' => $accounts,
        ]);

        $this->seed(ProductionSeeder::class);
        $this->seed(RenderDemoSeeder::class);

        $firstCounts = $this->demoCounts();
        $this->assertSame(count($accounts), User::query()->whereIn('username', collect($accounts)->pluck('username'))->count());
        $this->assertTrue(Hash::check('test-password', User::query()->where('username', 'admin')->firstOrFail()->password));
        $this->assertGreaterThan(0, IapRiskPeriod::query()->count());
        $this->assertNotNull(IapPrioritizationRun::query()->where('run_code', 'PRIO-2025')->first());
        $this->assertNotNull(InternalAuditPlan::query()->where('plan_code', 'IAP-2026-DEMO')->first());
        $this->assertNotNull(StrategicInternalAuditPlan::query()->where('plan_code', 'SIAP-2026-2030-R00')->first());
        $this->assertGreaterThan(0, SystemNotification::query()->count());
        $this->assertSame(0, AuditEngagement::query()->count());
        $this->assertSame(0, CmsRecommendation::query()->count());

        $this->seed(RenderDemoSeeder::class);

        $this->assertSame($firstCounts, $this->demoCounts());
        $this->assertDatabaseHas('offices', ['code' => 'AGIS-SYS', 'is_active' => true]);
        $this->assertSame(0, Document::query()->where('storage_path', 'like', 'C:%')->count());
    }

    /** @return array<string, int> */
    private function demoCounts(): array
    {
        return [
            'users' => User::query()->count(),
            'riskPeriods' => IapRiskPeriod::query()->count(),
            'prioritizationRuns' => IapPrioritizationRun::query()->count(),
            'plans' => InternalAuditPlan::query()->count(),
            'notifications' => SystemNotification::query()->count(),
        ];
    }
}
