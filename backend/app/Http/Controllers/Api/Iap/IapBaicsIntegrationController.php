<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iap\IapBaicsIntegrationRequest;
use App\Http\Resources\IapBaicsIntegrationResource;
use App\Models\IapBaicsAssessment;
use App\Models\IapBaicsIntegration;
use App\Services\IapBaicsIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** BAICS-4 approved baseline, legacy exception, and IAP lineage decisions. */
class IapBaicsIntegrationController extends Controller
{
    public function __construct(private readonly IapBaicsIntegrationService $service) {}

    public function candidates(Request $request): JsonResponse
    {
        $this->authorizeAny($request, ['iap.baics.integration.view', 'iap.baics.view']);
        return response()->json(['success' => true, 'data' => $this->service->candidates($request->user())]);
    }

    public function readiness(Request $request): JsonResponse
    {
        $this->authorizeAny($request, ['iap.baics.integration.view', 'iap.baics.view']);
        $data = $request->validate(['consumerType' => ['required', 'string'], 'consumerId' => ['required', 'integer', 'min:1']]);
        return response()->json(['success' => true, 'data' => ['readiness' => $this->service->readinessFor($request->user(), $data['consumerType'], (int) $data['consumerId'])]]);
    }

    public function index(Request $request, IapBaicsAssessment $assessment): JsonResponse
    {
        $this->authorizeAny($request, ['iap.baics.integration.view', 'iap.baics.view']);
        return response()->json(['success' => true, 'data' => ['integrations' => IapBaicsIntegrationResource::collection($this->service->listForAssessment($request->user(), $assessment))]]);
    }

    public function store(IapBaicsIntegrationRequest $request, IapBaicsAssessment $assessment): JsonResponse
    {
        $this->authorizeAny($request, ['iap.baics.integration.create', 'iap.baics.manage-controls', 'iap.baics.update']);
        $record = $this->service->save($request, $assessment, $request->validated());
        return response()->json(['success' => true, 'message' => 'BAICS-to-IAP integration decision drafted.', 'data' => ['integration' => new IapBaicsIntegrationResource($record)]], 201);
    }

    public function update(IapBaicsIntegrationRequest $request, IapBaicsAssessment $assessment, IapBaicsIntegration $integration): JsonResponse
    {
        $this->authorizeAny($request, ['iap.baics.integration.update', 'iap.baics.manage-controls', 'iap.baics.update']);
        abort_unless((int) $integration->assessment_id === (int) $assessment->id, 404);
        $record = $this->service->save($request, $assessment, $request->validated(), $integration);
        return response()->json(['success' => true, 'message' => 'BAICS-to-IAP integration decision updated.', 'data' => ['integration' => new IapBaicsIntegrationResource($record)]]);
    }

    public function transition(Request $request, IapBaicsIntegration $integration, string $action): JsonResponse
    {
        $permission = match (strtoupper($action)) {
            'SUBMIT' => ['iap.baics.integration.submit', 'iap.baics.submit', 'iap.baics.review'],
            'REVIEW' => ['iap.baics.integration.review', 'iap.baics.review'],
            'RETURN' => ['iap.baics.integration.return', 'iap.baics.return', 'iap.baics.review'],
            'APPROVE' => ['iap.baics.integration.approve', 'iap.baics.approve'],
            'RETIRE' => ['iap.baics.integration.retire', 'iap.baics.update', 'iap.baics.archive'],
            default => null,
        };
        abort_unless($permission && $request->user()->hasAnyPermission($permission), 403);
        $validated = $request->validate(['comment' => ['nullable', 'string', 'max:10000']]);
        $record = $this->service->transition($request, $integration, $action, $validated['comment'] ?? null);
        return response()->json(['success' => true, 'message' => 'BAICS-to-IAP integration workflow updated.', 'data' => ['integration' => new IapBaicsIntegrationResource($record)]]);
    }

    /** @param list<string> $permissions */
    private function authorizeAny(Request $request, array $permissions): void
    {
        abort_unless($request->user()->hasAnyPermission($permissions), 403);
    }
}
