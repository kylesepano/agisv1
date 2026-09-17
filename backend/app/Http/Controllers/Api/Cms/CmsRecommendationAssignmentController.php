<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsMonitorAssignmentEndRequest;
use App\Http\Requests\Cms\CmsMonitorAssignmentRequest;
use App\Http\Resources\CmsRecommendationAssignmentResource;
use App\Services\Cms\CmsRecommendationAssignmentService;
use App\Services\Cms\CmsRecommendationScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Exposes assignment history and controlled monitor assignment actions. */
class CmsRecommendationAssignmentController extends Controller
{
    public function __construct(
        private readonly CmsRecommendationScopeService $scope,
        private readonly CmsRecommendationAssignmentService $assignments,
    ) {}

    public function index(Request $request, int $recommendation): JsonResponse
    {
        $case = $this->scope->resolveVisibleCase($request->user(), $recommendation);
        $case->load([
            'assignments.user',
            'assignments.assigner',
            'assignments.ender',
        ]);
        $canAssign = $request->user()->hasPermission('cms.recommendation.assign');
        $options = $canAssign
            ? $this->assignments->eligibleMonitors($request->user(), $case)
                ->map(fn ($user): array => [
                    'id' => $user->id,
                    'employeeId' => $user->employee_id,
                    'name' => $user->name,
                    'officeId' => $user->office_id,
                ])->values()
            : collect();

        return response()->json([
            'success' => true,
            'data' => [
                'caseId' => $case->id,
                'lockVersion' => $case->lock_version,
                'assignments' => CmsRecommendationAssignmentResource::collection(
                    $case->assignments,
                ),
                'eligibleMonitors' => $options,
            ],
        ]);
    }

    public function store(
        CmsMonitorAssignmentRequest $request,
        int $recommendation,
    ): JsonResponse {
        $result = $this->assignments->assign(
            $request,
            $recommendation,
            $request->validated(),
        );
        $result['assignment']->load(['user', 'assigner', 'ender']);

        return response()->json([
            'success' => true,
            'message' => $result['replaced']
                ? 'Compliance Monitor replaced successfully.'
                : 'Compliance Monitor assigned successfully.',
            'data' => [
                'assignment' => new CmsRecommendationAssignmentResource(
                    $result['assignment'],
                ),
                'caseLockVersion' => $result['caseLockVersion'],
            ],
        ], 201);
    }

    public function end(
        CmsMonitorAssignmentEndRequest $request,
        int $recommendation,
        int $assignment,
    ): JsonResponse {
        $result = $this->assignments->end(
            $request,
            $recommendation,
            $assignment,
            (int) $request->validated('lockVersion'),
            $request->validated('reason'),
        );
        $result['assignment']->load(['user', 'assigner', 'ender']);

        return response()->json([
            'success' => true,
            'message' => 'Compliance Monitor assignment ended successfully.',
            'data' => [
                'assignment' => new CmsRecommendationAssignmentResource(
                    $result['assignment'],
                ),
                'caseLockVersion' => $result['caseLockVersion'],
            ],
        ]);
    }
}
