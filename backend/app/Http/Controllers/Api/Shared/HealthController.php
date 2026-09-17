<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reports application and database readiness for operational health checks.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'healthy',
                    'database' => DB::connection()->getDriverName(),
                    'checkedAt' => now()->toIso8601String(),
                ],
            ]);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'The database is currently unavailable.',
            ], 503);
        }
    }
}
