<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Models\AuditEngagement;
use App\Services\AemsEngagementTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Exposes the controlled aggregate AEMS lifecycle without accepting target states. */
class AemsEngagementLifecycleController extends Controller
{
    public function __construct(
        private readonly AemsEngagementTransitionService $transitions,
    ) {}

    public function show(Request $request, AuditEngagement $engagement): JsonResponse
    {
        Gate::authorize('view', $engagement);

        return response()->json([
            'success' => true,
            'data' => $this->transitions->workspace($request->user(), $engagement),
        ]);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        string $action,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:10000'],
            'authority' => ['nullable', 'string', 'max:255'],
            'effectiveDate' => ['nullable', 'date'],
            'expectedReviewDate' => ['nullable', 'date'],
            'resumeRequirements' => ['nullable', 'string', 'max:10000'],
            'effectOnIap' => ['nullable', 'string', 'max:10000'],
            'workProductDisposition' => ['nullable', 'string', 'max:10000'],
        ]);
        $engagement = $this->transitions->transition(
            $request,
            $engagement,
            $action,
            $validated['lockVersion'],
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Engagement lifecycle transition completed.',
            'data' => $this->transitions->workspace($request->user(), $engagement),
        ]);
    }
}
