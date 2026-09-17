<?php

namespace App\Http\Controllers\Api\Armis;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArmisProviderMonitoringCheckResource;
use App\Services\ArmisProviderMonitoringService;
use Illuminate\Http\Request;

/** Protected ARMIS-6D provider health and cutover-verification API. */
class ArmisProviderMonitoringController extends Controller
{
    public function __construct(private readonly ArmisProviderMonitoringService $monitoring) {}

    public function status(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->monitoring->status($request->user()),
        ]);
    }

    public function index(Request $request)
    {
        $checks = $this->monitoring->checks($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'checks' => ArmisProviderMonitoringCheckResource::collection($checks),
                'meta' => ['total' => $checks->count()],
            ],
        ]);
    }

    public function store(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'ARMIS provider monitoring check recorded.',
            'data' => ['check' => new ArmisProviderMonitoringCheckResource($this->monitoring->run($request))],
        ], 201);
    }

    public function show(Request $request, int $check)
    {
        return response()->json([
            'success' => true,
            'data' => ['check' => new ArmisProviderMonitoringCheckResource($this->monitoring->show($request->user(), $check))],
        ]);
    }
}
