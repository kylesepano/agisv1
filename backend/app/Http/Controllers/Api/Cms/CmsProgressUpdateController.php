<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsProgressEvidenceRemoveRequest;
use App\Http\Requests\Cms\CmsProgressEvidenceRequest;
use App\Http\Requests\Cms\CmsProgressRecordRequest;
use App\Http\Requests\Cms\CmsProgressReturnRequest;
use App\Http\Requests\Cms\CmsProgressReviewRequest;
use App\Http\Requests\Cms\CmsProgressRevisionRequest;
use App\Http\Requests\Cms\CmsProgressSubmitRequest;
use App\Http\Requests\Cms\CmsProgressUpdateDraftRequest;
use App\Http\Resources\CmsProgressEvidenceResource;
use App\Http\Resources\CmsProgressUpdateResource;
use App\Models\CmsProgressUpdate;
use App\Models\CmsRecommendationCase;
use App\Services\Cms\CmsProgressEvidenceService;
use App\Services\Cms\CmsProgressUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Thin HTTP boundary for CMS-4A management-reported progress. */
class CmsProgressUpdateController extends Controller
{
    public function __construct(
        private readonly CmsProgressUpdateService $progress,
        private readonly CmsProgressEvidenceService $evidence,
    ) {}

    public function forRecommendation(Request $request, int $recommendation): JsonResponse
    {
        $result = $this->progress->forRecommendation($request->user(), $recommendation);

        return response()->json([
            'success' => true,
            'data' => [
                'progressUpdates' => $result['updates']->map(
                    fn(CmsProgressUpdate $update) => $this->resource(
                        $request,
                        $result['case'],
                        $update,
                    ),
                )->values(),
                'caseContext' => $this->caseContext($result['case']),
                'permittedActions' => $result['permittedActions'],
                'notIndependentlyValidated' => true,
            ],
        ]);
    }

    public function show(Request $request, int $progressUpdate): JsonResponse
    {
        $result = $this->progress->show($request->user(), $progressUpdate);

        return response()->json([
            'success' => true,
            'data' => [
                'progressUpdate' => $this->resource(
                    $request,
                    $result['case'],
                    $result['update'],
                ),
            ],
        ]);
    }

    public function store(
        CmsProgressUpdateDraftRequest $request,
        int $recommendation,
    ): JsonResponse {
        $update = $this->progress->create(
            $request,
            $recommendation,
            $request->validated(),
        );

        return $this->mutation($request, $update, 'Progress Update draft created.', 201);
    }

    public function update(
        CmsProgressUpdateDraftRequest $request,
        int $progressUpdate,
        int $version,
    ): JsonResponse {
        $update = $this->progress->update(
            $request,
            $progressUpdate,
            $version,
            $request->validated(),
        );

        return $this->mutation($request, $update, 'Progress Update draft updated.');
    }

    public function submit(
        CmsProgressSubmitRequest $request,
        int $progressUpdate,
        int $version,
    ): JsonResponse {
        $update = $this->progress->submit(
            $request,
            $progressUpdate,
            $version,
            (int) $request->validated('lockVersion'),
        );

        return $this->mutation($request, $update, 'Progress Update submitted.');
    }

    public function startReview(
        CmsProgressReviewRequest $request,
        int $progressUpdate,
        int $version,
    ): JsonResponse {
        $update = $this->progress->startReview(
            $request,
            $progressUpdate,
            $version,
            (int) $request->validated('lockVersion'),
            $request->validated('reviewComment'),
        );

        return $this->mutation($request, $update, 'Progress Update review started.');
    }

    public function return(
        CmsProgressReturnRequest $request,
        int $progressUpdate,
        int $version,
    ): JsonResponse {
        $update = $this->progress->return(
            $request,
            $progressUpdate,
            $version,
            (int) $request->validated('lockVersion'),
            $request->validated('returnReason'),
        );

        return $this->mutation($request, $update, 'Progress Update returned.');
    }

    public function record(
        CmsProgressRecordRequest $request,
        int $progressUpdate,
        int $version,
    ): JsonResponse {
        $update = $this->progress->recordUpdate(
            $request,
            $progressUpdate,
            $version,
            (int) $request->validated('lockVersion'),
            $request->validated('recordingComment'),
        );

        return $this->mutation(
            $request,
            $update,
            'Management-reported Progress Update recorded; not independently validated.',
        );
    }

    public function revise(
        CmsProgressRevisionRequest $request,
        int $progressUpdate,
        int $version,
    ): JsonResponse {
        $update = $this->progress->revise(
            $request,
            $progressUpdate,
            $version,
            (int) $request->validated('lockVersion'),
            $request->validated('revisionReason'),
        );

        return $this->mutation($request, $update, 'Progress Update revision created.', 201);
    }

    public function uploadEvidence(
        CmsProgressEvidenceRequest $request,
        int $progressUpdate,
        int $version,
    ): JsonResponse {
        $evidence = $this->evidence->upload(
            $request,
            $progressUpdate,
            $version,
            $request->validated(),
            $request->file('file'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Supporting evidence linked to the Progress Update draft.',
            'data' => ['evidence' => new CmsProgressEvidenceResource($evidence)],
        ], 201);
    }

    public function downloadEvidence(
        Request $request,
        int $evidence,
    ): StreamedResponse {
        return $this->evidence->download($request, $evidence);
    }

    public function removeEvidence(
        CmsProgressEvidenceRemoveRequest $request,
        int $evidence,
    ): JsonResponse {
        $update = $this->evidence->remove(
            $request,
            $evidence,
            (int) $request->validated('lockVersion'),
            $request->validated('removalReason'),
        );

        return $this->mutation(
            $request,
            $update,
            'Draft evidence link removed; the Core document was retained.',
        );
    }

    private function mutation(
        Request $request,
        CmsProgressUpdate $update,
        string $message,
        int $status = 200,
    ): JsonResponse {
        $update = $update->fresh($this->relations());
        $case = $update->case;

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'progressUpdate' => $this->resource($request, $case, $update),
            ],
        ], $status);
    }

    private function resource(
        Request $request,
        CmsRecommendationCase $case,
        CmsProgressUpdate $update,
    ): CmsProgressUpdateResource {
        foreach ($update->versions as $version) {
            $version->setRelation('progressUpdate', $update);
            $version->setAttribute(
                'available_actions',
                $this->progress->permittedActions($request->user(), $update, $version),
            );
            $version->setAttribute(
                'completeness',
                $this->progress->completeness($update, $version),
            );
        }
        foreach ([$update->currentVersion, $update->recordedVersion] as $version) {
            if (! $version) {
                continue;
            }
            $version->setRelation('progressUpdate', $update);
            $version->setAttribute(
                'available_actions',
                $this->progress->permittedActions($request->user(), $update, $version),
            );
            $version->setAttribute(
                'completeness',
                $this->progress->completeness($update, $version),
            );
        }

        return new CmsProgressUpdateResource($update);
    }

    /** @return array<string, mixed> */
    private function caseContext(CmsRecommendationCase $case): array
    {
        return [
            'id' => $case->id,
            'cmsRecommendationCode' => sprintf('CMS-REC-%06d', $case->id),
            'status' => $case->status_code,
            'responsibleOffice' => $case->leadResponsibleOffice?->only([
                'id',
                'code',
                'name',
                'acronym',
            ]),
            'effectiveTargetDate' => $case
                ->effective_target_implementation_date
                ?->toDateString(),
            'acceptedActionPlanVersionId' => $case->actionPlan?->accepted_version_id,
        ];
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'case.recommendation',
            'case.leadResponsibleOffice',
            'case.currentAssignment.user',
            'actionPlan',
            'acceptedActionPlanVersion.milestones',
            'creator',
            'versions.preparer',
            'versions.submitter',
            'versions.reviewStarter',
            'versions.returner',
            'versions.recorder',
            'versions.milestoneProgress.evidenceLinks',
            'versions.activeEvidenceLinks.documentVersion',
            'versions.activeEvidenceLinks.confidentialityLevel',
            'versions.activeEvidenceLinks.linker',
            'currentVersion.preparer',
            'currentVersion.submitter',
            'currentVersion.reviewStarter',
            'currentVersion.returner',
            'currentVersion.recorder',
            'currentVersion.milestoneProgress.evidenceLinks',
            'currentVersion.activeEvidenceLinks.documentVersion',
            'currentVersion.activeEvidenceLinks.confidentialityLevel',
            'currentVersion.activeEvidenceLinks.linker',
            'recordedVersion.preparer',
            'recordedVersion.submitter',
            'recordedVersion.reviewStarter',
            'recordedVersion.returner',
            'recordedVersion.recorder',
            'recordedVersion.milestoneProgress.evidenceLinks',
            'recordedVersion.activeEvidenceLinks.documentVersion',
            'recordedVersion.activeEvidenceLinks.confidentialityLevel',
            'recordedVersion.activeEvidenceLinks.linker',
        ];
    }
}
