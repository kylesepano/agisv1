<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsRecommendationIndexRequest;
use App\Http\Resources\CmsRecommendationDetailResource;
use App\Http\Resources\CmsRecommendationResource;
use App\Models\CmsRecommendationCase;
use App\Services\Cms\CmsActionPlanService;
use App\Services\Cms\CmsDispositionService;
use App\Services\Cms\CmsProgressUpdateService;
use App\Services\Cms\CmsRecommendationRegistryService;
use App\Services\Cms\CmsRecommendationScopeService;
use App\Services\Cms\CmsReopeningService;
use App\Services\Cms\CmsTargetDateExtensionService;
use App\Services\Cms\CmsValidationService;
use App\Support\ActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Read-only CMS recommendation registry backed by AEMS-created cases. */
class CmsRecommendationController extends Controller
{
    public function __construct(
        private readonly CmsRecommendationRegistryService $registry,
        private readonly CmsRecommendationScopeService $scope,
        private readonly CmsActionPlanService $actionPlans,
        private readonly CmsProgressUpdateService $progressUpdates,
        private readonly CmsValidationService $validations,
        private readonly CmsTargetDateExtensionService $extensions,
        private readonly CmsDispositionService $dispositions,
        private readonly CmsReopeningService $reopenings,
    ) {}

    public function index(CmsRecommendationIndexRequest $request): JsonResponse
    {
        $cases = $this->registry->paginate($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'recommendations' => CmsRecommendationResource::collection(
                    $cases->getCollection(),
                ),
                'filters' => $this->registry->filterOptions($request->user()),
                'pagination' => [
                    'currentPage' => $cases->currentPage(),
                    'lastPage' => $cases->lastPage(),
                    'perPage' => $cases->perPage(),
                    'total' => $cases->total(),
                    'from' => $cases->firstItem(),
                    'to' => $cases->lastItem(),
                ],
                'evaluationDate' => now()->toDateString(),
            ],
        ]);
    }

    public function show(Request $request, int $recommendation): JsonResponse
    {
        $case = $this->scope->resolveVisibleCase($request->user(), $recommendation);
        $case->load($this->detailRelations());
        if ($case->actionPlan?->currentVersion) {
            $case->actionPlan->currentVersion->setAttribute(
                'available_actions',
                $this->actionPlans->permittedActions(
                    $request->user(),
                    $case->actionPlan,
                    $case->actionPlan->currentVersion,
                ),
            );
        }
        foreach ($case->progressUpdates as $progressUpdate) {
            if (! $progressUpdate->currentVersion) {
                continue;
            }
            $progressUpdate->currentVersion->setAttribute(
                'available_actions',
                $this->progressUpdates->permittedActions(
                    $request->user(),
                    $progressUpdate,
                    $progressUpdate->currentVersion,
                ),
            );
        }
        foreach ($case->validationReviews as $validationReview) {
            if (! $validationReview->currentVersion) {
                continue;
            }
            $validationReview->currentVersion->setAttribute(
                'available_actions',
                $this->validations->permittedActions(
                    $request->user(),
                    $validationReview,
                    $validationReview->currentVersion,
                ),
            );
        }
        foreach ($case->targetDateExtensionRequests as $extension) {
            foreach ($extension->versions as $version) {
                $version->setAttribute(
                    'available_actions',
                    $this->extensions->permittedActions($request->user(), $extension, $version),
                );
            }
        }
        foreach ($case->dispositionRequests as $disposition) {
            if ($disposition->currentVersion) {
                $disposition->currentVersion->setAttribute(
                    'available_actions',
                    $this->dispositions->permittedActions($request->user(), $disposition, $disposition->currentVersion),
                );
            }
        }
        foreach ($case->reopeningRequests as $reopening) {
            if ($reopening->currentVersion) {
                $reopening->currentVersion->setAttribute(
                    'available_actions',
                    $this->reopenings->permittedActions($request->user(), $reopening, $reopening->currentVersion),
                );
            }
        }
        $this->recordView($request, $case);

        return response()->json([
            'success' => true,
            'data' => [
                'recommendation' => new CmsRecommendationDetailResource($case),
            ],
        ]);
    }

    /** @return list<string> */
    private function detailRelations(): array
    {
        return [
            'recommendation.transferActor',
            'leadResponsibleOffice',
            'currentAssignment.user',
            'currentAssignment.assigner',
            'assignments.user',
            'assignments.assigner',
            'assignments.ender',
            'events.actor',
            'actionPlan.currentVersion.milestones',
            'actionPlan.acceptedVersion',
            'progressUpdates.currentVersion.activeEvidenceLinks',
            'progressUpdates.recordedVersion.activeEvidenceLinks',
            'validationReviews.currentAssignment.user',
            'validationReviews.currentVersion',
            'validationReviews.finalizedVersion',
            'targetDateExtensionRequests.versions.assessment.assessor',
            'targetDateExtensionRequests.versions.decision.decider',
            'targetDateExtensionRequests.versions.activeEvidenceLinks.documentVersion',
            'targetDateExtensionRequests.currentVersion',
            'targetDateExtensionRequests.resolvedVersion',
            'targetDateHistory.actor',
            'escalations.currentNotice.preparer',
            'escalations.currentNotice.recipients',
            'escalations.currentNotice.acknowledgements',
            'escalations.response.currentVersion.preparer',
            'escalations.resolution.resolver',
            'closureRequests.currentVersion.assessment.reviewer',
            'closureRequests.currentVersion.decision.decider',
            'closureRequests.resolvedVersion.decision.decider',
            'dispositionRequests.currentVersion.assessment.reviewer',
            'dispositionRequests.currentVersion.decision.decider',
            'dispositionRequests.resolvedVersion.decision.decider',
            'dispositionRequests.currentVersion.evidenceLinks.documentVersion',
            'reopeningRequests.currentVersion.assessment.reviewer',
            'reopeningRequests.currentVersion.decision.decider',
            'reopeningRequests.resolvedVersion.decision.decider',
            'reopeningRequests.currentVersion.evidenceLinks.documentVersion',
            'reopeningRequests.currentVersion.activeEvidenceLinks.documentVersion',
            'reopeningRequests.sourceClosureDecision.version',
            'reopeningRequests.sourceDispositionDecision.version',
            'closureCandidates.reviewer',
            'escalationCandidates.reviewer',
        ];
    }

    private function recordView(Request $request, CmsRecommendationCase $case): void
    {
        $recorded = Cache::add(
            "agis:record-view:{$request->user()->id}:CMS_RECOMMENDATION:{$case->id}",
            true,
            now()->addMinutes(5),
        );
        if (! $recorded) {
            return;
        }

        ActivityRecorder::record(
            $request,
            'cms.recommendation.viewed',
            'Viewed an authorized CMS recommendation.',
            metadata: [
                'module' => 'CMS',
                'recordType' => 'CMS_RECOMMENDATION',
                'recordId' => $case->id,
                'recordCode' => sprintf('CMS-REC-%06d', $case->id),
            ],
        );
    }
}
