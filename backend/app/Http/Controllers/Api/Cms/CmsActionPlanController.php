<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsActionPlanAcceptRequest;
use App\Http\Requests\Cms\CmsActionPlanDraftRequest;
use App\Http\Requests\Cms\CmsActionPlanReturnRequest;
use App\Http\Requests\Cms\CmsActionPlanReviewRequest;
use App\Http\Requests\Cms\CmsActionPlanRevisionRequest;
use App\Http\Requests\Cms\CmsActionPlanSubmitRequest;
use App\Http\Resources\CmsActionPlanResource;
use App\Models\CmsCorrectiveActionPlan;
use App\Models\CmsRecommendationCase;
use App\Services\Cms\CmsActionPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Thin HTTP boundary for the CMS-3A controlled Action Plan aggregate. */
class CmsActionPlanController extends Controller
{
    public function __construct(private readonly CmsActionPlanService $actionPlans) {}

    public function forRecommendation(Request $request, int $recommendation): JsonResponse
    {
        $result = $this->actionPlans->showForCase($request->user(), $recommendation);

        return response()->json([
            'success' => true,
            'data' => [
                'actionPlan' => $result['plan']
                    ? $this->resource($request, $result['case'], $result['plan'])
                    : null,
                'caseContext' => $this->caseContext($result['case']),
                'permittedActions' => $result['permittedActions'],
            ],
        ]);
    }

    public function show(Request $request, int $actionPlan): JsonResponse
    {
        $result = $this->actionPlans->show($request->user(), $actionPlan);

        return response()->json([
            'success' => true,
            'data' => [
                'actionPlan' => $this->resource(
                    $request,
                    $result['case'],
                    $result['plan'],
                ),
            ],
        ]);
    }

    public function store(
        CmsActionPlanDraftRequest $request,
        int $recommendation,
    ): JsonResponse {
        $plan = $this->actionPlans->create(
            $request,
            $recommendation,
            $request->validated(),
        );

        return $this->mutation($request, $plan, 'Action Plan draft created.', 201);
    }

    public function update(
        CmsActionPlanDraftRequest $request,
        int $actionPlan,
        int $version,
    ): JsonResponse {
        $plan = $this->actionPlans->update(
            $request,
            $actionPlan,
            $version,
            $request->validated(),
        );

        return $this->mutation($request, $plan, 'Action Plan draft updated.');
    }

    public function submit(
        CmsActionPlanSubmitRequest $request,
        int $actionPlan,
        int $version,
    ): JsonResponse {
        $plan = $this->actionPlans->submit(
            $request,
            $actionPlan,
            $version,
            (int) $request->validated('lockVersion'),
            $request->validated('reviewComment'),
        );

        return $this->mutation($request, $plan, 'Action Plan submitted.');
    }

    public function startReview(
        CmsActionPlanReviewRequest $request,
        int $actionPlan,
        int $version,
    ): JsonResponse {
        $plan = $this->actionPlans->startReview(
            $request,
            $actionPlan,
            $version,
            (int) $request->validated('lockVersion'),
        );

        return $this->mutation($request, $plan, 'Action Plan review started.');
    }

    public function return(
        CmsActionPlanReturnRequest $request,
        int $actionPlan,
        int $version,
    ): JsonResponse {
        $plan = $this->actionPlans->return(
            $request,
            $actionPlan,
            $version,
            (int) $request->validated('lockVersion'),
            $request->validated('returnReason'),
        );

        return $this->mutation($request, $plan, 'Action Plan returned.');
    }

    public function accept(
        CmsActionPlanAcceptRequest $request,
        int $actionPlan,
        int $version,
    ): JsonResponse {
        $plan = $this->actionPlans->accept(
            $request,
            $actionPlan,
            $version,
            (int) $request->validated('lockVersion'),
            $request->validated('acceptanceComment'),
        );

        return $this->mutation($request, $plan, 'Action Plan accepted.');
    }

    public function revise(
        CmsActionPlanRevisionRequest $request,
        int $actionPlan,
        int $version,
    ): JsonResponse {
        $plan = $this->actionPlans->revise(
            $request,
            $actionPlan,
            $version,
            (int) $request->validated('lockVersion'),
            $request->validated('revisionReason'),
        );

        return $this->mutation($request, $plan, 'Action Plan revision created.', 201);
    }

    private function mutation(
        Request $request,
        CmsCorrectiveActionPlan $plan,
        string $message,
        int $status = 200,
    ): JsonResponse {
        $case = $plan->case;

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'actionPlan' => $this->resource($request, $case, $plan),
            ],
        ], $status);
    }

    private function resource(
        Request $request,
        CmsRecommendationCase $case,
        CmsCorrectiveActionPlan $plan,
    ): CmsActionPlanResource {
        $plan->loadMissing([
            'case.recommendation',
            'case.leadResponsibleOffice',
            'case.currentAssignment.user',
        ]);
        if ($request->user()->hasPermission('access.read_only_scope')) {
            $visibleStatuses = ['SUBMITTED', 'UNDER_REVIEW', 'ACCEPTED'];
            $plan->setRelation(
                'versions',
                $plan->versions
                    ->whereIn('status_code', $visibleStatuses)
                    ->values(),
            );
            if ($plan->currentVersion
                && ! in_array($plan->currentVersion->status_code, $visibleStatuses, true)) {
                $plan->setRelation('currentVersion', null);
            }
        }
        foreach ($plan->versions as $version) {
            $version->setRelation('plan', $plan);
            $version->setAttribute(
                'available_actions',
                $this->actionPlans->permittedActions($request->user(), $plan, $version),
            );
            $version->setAttribute(
                'completeness',
                $this->actionPlans->completeness($case, $version),
            );
        }
        foreach ([$plan->currentVersion, $plan->acceptedVersion] as $version) {
            if (! $version) {
                continue;
            }
            $version->setRelation('plan', $plan);
            $version->setAttribute(
                'available_actions',
                $this->actionPlans->permittedActions($request->user(), $plan, $version),
            );
            $version->setAttribute(
                'completeness',
                $this->actionPlans->completeness($case, $version),
            );
        }

        return new CmsActionPlanResource($plan);
    }

    /** @return array<string, mixed> */
    private function caseContext(CmsRecommendationCase $case): array
    {
        $case->loadMissing(['recommendation', 'leadResponsibleOffice', 'currentAssignment.user']);

        return [
            'id' => $case->id,
            'cmsRecommendationCode' => sprintf('CMS-REC-%06d', $case->id),
            'status' => $case->status_code,
            'responsibleOffice' => $case->leadResponsibleOffice?->only([
                'id', 'code', 'name', 'acronym',
            ]),
            'originalTargetDate' => $case->recommendation
                ?->original_target_implementation_date
                ?->toDateString(),
            'effectiveTargetDate' => $case
                ->effective_target_implementation_date
                ?->toDateString(),
        ];
    }
}
