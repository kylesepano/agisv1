<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iap\IapTransitionRequest;
use App\Http\Resources\IapPlanResource;
use App\Models\InternalAuditPlan;
use App\Services\IapPlanGuard;
use App\Services\IapWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Executes audited Annual Plan workflow transitions through the IAP workflow service.
 */
class IapWorkflowController extends Controller
{
    public function __construct(
        private readonly IapPlanGuard $guard,
        private readonly IapWorkflowService $workflow,
    ) {}

    public function completeness(Request $request, InternalAuditPlan $plan): JsonResponse
    {
        $this->guard->assertCanView($request->user(), $plan);

        return response()->json([
            'success' => true,
            'data' => ['completeness' => $this->workflow->completeness($plan)],
        ]);
    }

    public function transition(
        IapTransitionRequest $request,
        InternalAuditPlan $plan,
        string $action,
    ): JsonResponse {
        $updated = $this->workflow->transition(
            $request,
            $plan,
            $action,
            (int) $request->validated('lockVersion'),
            $request->validated('comment'),
            (bool) $request->validated('completionConfirmed', false),
        );

        return response()->json([
            'success' => true,
            'message' => 'Plan workflow updated successfully.',
            'data' => ['plan' => new IapPlanResource($updated->fresh())],
        ]);
    }

    public function revision(Request $request, InternalAuditPlan $plan): JsonResponse
    {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:10000'],
        ]);

        $revision = $this->workflow->createRevision(
            $request,
            $plan,
            (int) $validated['lockVersion'],
            $validated['reason'],
        );

        return response()->json([
            'success' => true,
            'message' => 'A new draft plan revision was created successfully.',
            'data' => ['plan' => new IapPlanResource($revision)],
        ], 201);
    }
}
