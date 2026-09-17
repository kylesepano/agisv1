<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsValidationAssignmentEndRequest;
use App\Http\Requests\Cms\CmsValidationAssignmentRequest;
use App\Http\Requests\Cms\CmsValidationCreateRequest;
use App\Http\Requests\Cms\CmsValidationDraftRequest;
use App\Http\Requests\Cms\CmsValidationEvidenceRemoveRequest;
use App\Http\Requests\Cms\CmsValidationEvidenceRequest;
use App\Http\Requests\Cms\CmsValidationFinalizeRequest;
use App\Http\Requests\Cms\CmsValidationReturnRequest;
use App\Http\Requests\Cms\CmsValidationReviewRequest;
use App\Http\Requests\Cms\CmsValidationRevisionRequest;
use App\Http\Requests\Cms\CmsValidationSubmitRequest;
use App\Http\Resources\CmsValidationAssignmentResource;
use App\Http\Resources\CmsValidationEvidenceResource;
use App\Http\Resources\CmsValidationReviewResource;
use App\Models\CmsRecommendationCase;
use App\Models\CmsValidationReview;
use App\Models\CmsValidationVersion;
use App\Services\Cms\CmsValidationEvidenceService;
use App\Services\Cms\CmsValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Thin HTTP boundary for CMS-5A independent professional validation. */
class CmsValidationController extends Controller
{
    public function __construct(
        private readonly CmsValidationService $validations,
        private readonly CmsValidationEvidenceService $evidence,
    ) {}

    public function forRecommendation(Request $request, int $recommendation): JsonResponse
    {
        $result = $this->validations->forRecommendation($request->user(), $recommendation);

        return response()->json([
            'success' => true,
            'data' => [
                'validations' => $result['reviews']->map(
                    fn (CmsValidationReview $review) => $this->resource(
                        $request,
                        $result['case'],
                        $review,
                    ),
                )->values(),
                'caseContext' => $this->caseContext($result['case']),
                'permittedActions' => $result['permittedActions'],
            ],
        ]);
    }

    public function validationOptions(Request $request, int $recommendation): JsonResponse
    {
        $result = $this->validations->validationOptions($request->user(), $recommendation);

        return response()->json([
            'success' => true,
            'data' => [
                'caseContext' => $this->caseContext($result['case']),
                'eligibleRecordedProgressUpdates' => $result['eligibleRecordedProgressUpdates'],
                'eligibleValidators' => $result['eligibleValidators'],
                'unavailableReasons' => $result['unavailableReasons'],
            ],
        ]);
    }

    public function show(Request $request, int $validation): JsonResponse
    {
        $result = $this->validations->show($request->user(), $validation);

        return response()->json([
            'success' => true,
            'data' => [
                'validation' => $this->resource(
                    $request,
                    $result['case'],
                    $result['review'],
                ),
            ],
        ]);
    }

    public function store(
        CmsValidationCreateRequest $request,
        int $recommendation,
    ): JsonResponse {
        $review = $this->validations->create(
            $request,
            $recommendation,
            $request->validated(),
        );

        return $this->mutation($request, $review, 'Validation Review created and assigned.', 201);
    }

    public function assignments(Request $request, int $validation): JsonResponse
    {
        $result = $this->validations->show($request->user(), $validation);

        return response()->json([
            'success' => true,
            'data' => [
                'assignments' => CmsValidationAssignmentResource::collection(
                    $result['review']->assignments,
                ),
                'lockVersion' => $result['review']->lock_version,
            ],
        ]);
    }

    public function assign(
        CmsValidationAssignmentRequest $request,
        int $validation,
    ): JsonResponse {
        $review = $this->validations->assign(
            $request,
            $validation,
            $request->validated(),
        );

        return $this->mutation($request, $review, 'Primary Validator assignment updated.');
    }

    public function endAssignment(
        CmsValidationAssignmentEndRequest $request,
        int $validation,
        int $assignment,
    ): JsonResponse {
        $review = $this->validations->endAssignment(
            $request,
            $validation,
            $assignment,
            (int) $request->validated('lockVersion'),
            $request->validated('endReason'),
        );

        return $this->mutation($request, $review, 'Primary Validator assignment ended.');
    }

    public function update(
        CmsValidationDraftRequest $request,
        int $validation,
        int $version,
    ): JsonResponse {
        $review = $this->validations->update(
            $request,
            $validation,
            $version,
            $request->validated(),
        );

        return $this->mutation($request, $review, 'Validation draft updated.');
    }

    public function submit(
        CmsValidationSubmitRequest $request,
        int $validation,
        int $version,
    ): JsonResponse {
        $review = $this->validations->submit(
            $request,
            $validation,
            $version,
            (int) $request->validated('lockVersion'),
        );

        return $this->mutation($request, $review, 'Validation submitted for supervisory review.');
    }

    public function startReview(
        CmsValidationReviewRequest $request,
        int $validation,
        int $version,
    ): JsonResponse {
        $review = $this->validations->startReview(
            $request,
            $validation,
            $version,
            (int) $request->validated('lockVersion'),
            $request->validated('reviewComment'),
        );

        return $this->mutation($request, $review, 'Supervisory validation review started.');
    }

    public function return(
        CmsValidationReturnRequest $request,
        int $validation,
        int $version,
    ): JsonResponse {
        $review = $this->validations->return(
            $request,
            $validation,
            $version,
            (int) $request->validated('lockVersion'),
            $request->validated('returnReason'),
        );

        return $this->mutation($request, $review, 'Validation returned for controlled revision.');
    }

    public function finalize(
        CmsValidationFinalizeRequest $request,
        int $validation,
        int $version,
    ): JsonResponse {
        $review = $this->validations->finalize(
            $request,
            $validation,
            $version,
            $request->validated(),
        );

        return $this->mutation($request, $review, 'Professional validation conclusion finalized.');
    }

    public function revise(
        CmsValidationRevisionRequest $request,
        int $validation,
        int $version,
    ): JsonResponse {
        $review = $this->validations->revise(
            $request,
            $validation,
            $version,
            (int) $request->validated('lockVersion'),
            $request->validated('revisionReason'),
        );

        return $this->mutation($request, $review, 'Validation draft revision created.', 201);
    }

    public function uploadEvidence(
        CmsValidationEvidenceRequest $request,
        int $validation,
        int $version,
    ): JsonResponse {
        $evidence = $this->evidence->upload(
            $request,
            $validation,
            $version,
            $request->validated(),
            $request->file('file'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Validator evidence linked to the Validation draft.',
            'data' => ['evidence' => new CmsValidationEvidenceResource($evidence)],
        ], 201);
    }

    public function downloadEvidence(Request $request, int $evidence): StreamedResponse
    {
        return $this->evidence->download($request, $evidence);
    }

    public function removeEvidence(
        CmsValidationEvidenceRemoveRequest $request,
        int $evidence,
    ): JsonResponse {
        $review = $this->evidence->remove(
            $request,
            $evidence,
            (int) $request->validated('lockVersion'),
            $request->validated('removalReason'),
        );

        return $this->mutation(
            $request,
            $review,
            'Draft validator-evidence link removed; the Core document was retained.',
        );
    }

    private function mutation(
        Request $request,
        CmsValidationReview $review,
        string $message,
        int $status = 200,
    ): JsonResponse {
        $result = $this->validations->show($request->user(), $review->id);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'validation' => $this->resource(
                    $request,
                    $result['case'],
                    $result['review'],
                ),
            ],
        ], $status);
    }

    private function resource(
        Request $request,
        CmsRecommendationCase $case,
        CmsValidationReview $review,
    ): CmsValidationReviewResource {
        foreach ($review->versions as $version) {
            $this->decorateVersion($request, $review, $version);
        }
        foreach ([$review->currentVersion, $review->finalizedVersion] as $version) {
            if ($version) {
                $this->decorateVersion($request, $review, $version);
            }
        }
        $review->setRelation('case', $case);

        return new CmsValidationReviewResource($review);
    }

    private function decorateVersion(
        Request $request,
        CmsValidationReview $review,
        CmsValidationVersion $version,
    ): void {
        $version->setRelation('review', $review);
        $version->setAttribute(
            'available_actions',
            $this->validations->permittedActions($request->user(), $review, $version),
        );
        $version->setAttribute(
            'completeness',
            $this->validations->completeness($review, $version),
        );
    }

    /** @return array<string, mixed> */
    private function caseContext(CmsRecommendationCase $case): array
    {
        return [
            'id' => $case->id,
            'cmsRecommendationCode' => sprintf('CMS-REC-%06d', $case->id),
            'status' => $case->status_code,
            'responsibleOffice' => $case->leadResponsibleOffice?->only([
                'id', 'code', 'name', 'acronym',
            ]),
            'effectiveTargetDate' => $case
                ->effective_target_implementation_date
                ?->toDateString(),
            'lockVersion' => $case->lock_version,
            'currentComplianceMonitor' => $case->currentAssignment?->user
                ? [
                    'id' => $case->currentAssignment->user->id,
                    'employeeId' => $case->currentAssignment->user->employee_id,
                    'name' => $case->currentAssignment->user->name,
                    'initials' => $case->currentAssignment->user->initials,
                ]
                : null,
        ];
    }
}
