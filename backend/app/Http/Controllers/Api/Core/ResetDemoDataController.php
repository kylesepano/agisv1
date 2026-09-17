<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\OfficeResource;
use App\Models\AuditLog;
use App\Models\Office;
use App\Support\ActivityRecorder;
use Database\Seeders\AuditAreaSeeder;
use Database\Seeders\AuditUniverseSeeder;
use Database\Seeders\CoreUserSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\IapPrioritizationSeeder;
use Database\Seeders\IapRiskPeriodSeeder;
use Database\Seeders\IapSchedulingSeeder;
use Database\Seeders\MasterListSeeder;
use Database\Seeders\OfficeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SiapSeeder;
use Database\Seeders\SystemConfigurationSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Restores deterministic demonstration records for authorized administrators.
 */
class ResetDemoDataController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(config('demo.enabled'), 404);

        $actorId = $request->user()?->id;
        $demoCodes = array_column(OfficeSeeder::DEMO_OFFICES, 'code');

        DB::transaction(function () use ($request, $actorId, $demoCodes): void {
            Office::query()
                ->whereNotIn('code', $demoCodes)
                ->each(function (Office $office): void {
                    $office->forceFill(['is_active' => false])->save();
                    $office->delete();
                });

            app(OfficeSeeder::class)->run();
            app(RolePermissionSeeder::class)->run();
            app(AuditAreaSeeder::class)->run();
            app(MasterListSeeder::class)->run();
            app(SystemConfigurationSeeder::class)->run();
            app(DemoUserSeeder::class)->run();
            app(CoreUserSeeder::class)->run();
            app(AuditUniverseSeeder::class)->run();
            app(SiapSeeder::class)->run();
            app(IapRiskPeriodSeeder::class)->run();
            app(IapPrioritizationSeeder::class)->run();
            app(IapSchedulingSeeder::class)->run();

            AuditLog::query()->create([
                'user_id' => $actorId,
                'action' => 'demo.reset',
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'metadata' => [
                    'restored_offices' => $demoCodes,
                    'restored_accounts' => collect(config('demo.accounts', []))
                        ->pluck('employeeId')
                        ->values()
                        ->all(),
                ],
            ]);

            ActivityRecorder::record(
                $request,
                'demo.reset',
                "{$request->user()->name} restored the AGIS demonstration data.",
                $request->user(),
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Demo data restored successfully.',
            'data' => [
                'offices' => OfficeResource::collection(
                    Office::query()->orderBy('name')->get(),
                ),
            ],
        ]);
    }
}
