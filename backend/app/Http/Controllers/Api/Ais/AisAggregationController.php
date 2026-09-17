<?php

namespace App\Http\Controllers\Api\Ais;

use App\Http\Controllers\Controller;
use App\Services\Ais\AisAuditService;
use App\Services\Ais\AisAggregationService;
use App\Support\AisResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AisAggregationController extends Controller
{
    public function __construct(private readonly AisAggregationService $aggregations, private readonly AisAuditService $audit) {}

    public function overview(Request $request): JsonResponse
    {
        $this->audit->view($request, 'ais.aggregation.viewed', 'Viewed AIS scope-aware aggregation metrics.', ['view' => 'overview']);

        return AisResponse::json(['success' => true, 'data' => $this->aggregations->overview($request->user())], cacheable: true);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->audit->view($request, 'ais.dashboard.viewed', 'Viewed the AIS analytical dashboard.');

        return AisResponse::json(['success' => true, 'data' => $this->aggregations->dashboard($request->user())], cacheable: true);
    }

    public function snapshots(Request $request): JsonResponse
    {
        $this->audit->view($request, 'ais.snapshots.viewed', 'Viewed actor-owned immutable AIS snapshots.');
        $snapshots = $this->aggregations->snapshots($request->user())
            ->map(fn ($snapshot): array => $this->aggregations->snapshotData($snapshot))
            ->values();

        return AisResponse::json(['success' => true, 'data' => ['snapshots' => $snapshots]], cacheable: true);
    }

    public function generate(Request $request): JsonResponse
    {
        $snapshot = $this->aggregations->generate($request);

        return AisResponse::json([
            'success' => true,
            'message' => 'AIS read-only aggregation snapshot generated.',
            'data' => ['snapshot' => $this->aggregations->snapshotData($snapshot)],
        ], 201);
    }
}
