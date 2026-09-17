<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\CoreDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoreDashboardController extends Controller
{
    public function __construct(private readonly CoreDashboardService $dashboard) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->dashboard->dashboard($request->user())]);
    }
}
