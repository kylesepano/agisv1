<?php

namespace App\Http\Controllers\Api\Ais;

use App\Http\Controllers\Controller;
use App\Services\Ais\AisAuditService;
use App\Services\Ais\AisContractService;
use App\Services\Ais\AisIntegrationContractService;
use App\Services\Ais\AisIntegrationHealthService;
use App\Support\AisResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AisContractController extends Controller
{
    public function __construct(
        private readonly AisContractService $contract,
        private readonly AisIntegrationContractService $integration,
        private readonly AisIntegrationHealthService $health,
        private readonly AisAuditService $audit,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->audit->view($request, 'ais.contract.viewed', 'Viewed the AIS governance and hardening contract.');

        return AisResponse::json(['success' => true, 'data' => $this->contract->contract($request->user())], cacheable: true);
    }

    public function hardening(Request $request): JsonResponse
    {
        $this->audit->view($request, 'ais.hardening.viewed', 'Viewed the AIS deployment hardening controls.');

        return AisResponse::json(['success' => true, 'data' => $this->contract->hardening($request->user())], cacheable: true);
    }

    public function integration(Request $request): JsonResponse
    {
        $this->audit->view($request, 'ais.integration.viewed', 'Viewed AIS read-only cross-module integration status.');

        return AisResponse::json(['success' => true, 'data' => $this->integration->contract($request->user())], cacheable: true);
    }

    public function integrationHealth(Request $request): JsonResponse
    {
        $this->audit->view($request, 'ais.integration.health.viewed', 'Viewed AIS cross-module integration health diagnostics.');

        return AisResponse::json(['success' => true, 'data' => $this->health->health($request->user())], cacheable: true);
    }

    public function integrationSnapshots(Request $request): JsonResponse
    {
        $this->audit->view($request, 'ais.integration.snapshots.viewed', 'Viewed actor-owned immutable AIS integration snapshots.');
        $snapshots = $this->health->snapshots($request->user())
            ->map(fn ($snapshot): array => $this->health->snapshotData($snapshot))
            ->values();

        return AisResponse::json(['success' => true, 'data' => ['snapshots' => $snapshots]], cacheable: true);
    }

    public function captureIntegrationSnapshot(Request $request): JsonResponse
    {
        $snapshot = $this->health->capture($request->user());
        $this->audit->view($request, 'ais.integration.snapshot.generated', 'Generated an immutable AIS integration health snapshot.', [
            'snapshotId' => $snapshot->id,
            'snapshotCode' => $snapshot->snapshot_code,
            'status' => $snapshot->status,
        ]);

        return AisResponse::json([
            'success' => true,
            'message' => 'AIS integration health snapshot generated.',
            'data' => ['snapshot' => $this->health->snapshotData($snapshot)],
        ], 201);
    }
}
