<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Services\Cms\CmsDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Returns live metrics from only the actor's visible CMS case population. */
class CmsDashboardController extends Controller
{
    public function __invoke(Request $request, CmsDashboardService $dashboard): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $dashboard->dashboard($request->user()),
        ]);
    }
}
