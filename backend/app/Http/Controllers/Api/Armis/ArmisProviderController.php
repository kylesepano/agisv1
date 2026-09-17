<?php

namespace App\Http\Controllers\Api\Armis;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArmisProviderAuthorityDecisionResource;
use App\Http\Resources\ArmisProviderReconciliationReviewResource;
use App\Http\Resources\ArmisProviderReconciliationRunResource;
use App\Services\ArmisProviderReconciliationService;
use Illuminate\Http\Request;

/** Protected ARMIS-6B reconciliation and provider authority gate API. */
class ArmisProviderController extends Controller
{
    public function __construct(private readonly ArmisProviderReconciliationService $service) {}

    public function status(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->status($request->user()),
        ]);
    }

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'runs' => ArmisProviderReconciliationRunResource::collection($this->service->runs($request->user())),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fiscalYear' => ['nullable', 'integer', 'min:2000', 'max:2200'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ARMIS reconciliation snapshot generated.',
            'data' => ['run' => new ArmisProviderReconciliationRunResource($this->service->generate($request, $validated))],
        ], 201);
    }

    public function show(Request $request, int $run)
    {
        return response()->json([
            'success' => true,
            'data' => ['run' => new ArmisProviderReconciliationRunResource($this->service->show($request->user(), $run))],
        ]);
    }

    public function review(Request $request, int $run)
    {
        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:ACCEPTED,REJECTED'],
            'comment' => ['required', 'string', 'min:10', 'max:5000'],
            'discrepancyDecisions' => ['present', 'array'],
            'discrepancyDecisions.*' => ['required', 'string', 'in:ACCEPT,REJECT'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ARMIS reconciliation review recorded as an immutable decision.',
            'data' => ['review' => new ArmisProviderReconciliationReviewResource($this->service->review($request, $run, $validated))],
        ], 201);
    }

    public function activate(Request $request, int $run)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ARMIS is already the sole operational resource provider; provider activation is not available.',
            'data' => ['decision' => new ArmisProviderAuthorityDecisionResource($this->service->activate($request, $run, $validated['reason']))],
        ], 201);
    }

    public function rollback(Request $request)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ARMIS is the sole operational resource provider; provider rollback is not available.',
            'data' => ['decision' => new ArmisProviderAuthorityDecisionResource($this->service->rollback($request, $validated['reason']))],
        ], 201);
    }
}
