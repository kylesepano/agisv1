<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsExtensionDecisionRequest;
use App\Http\Requests\Cms\CmsExtensionDraftRequest;
use App\Http\Requests\Cms\CmsExtensionEvidenceRemoveRequest;
use App\Http\Requests\Cms\CmsExtensionEvidenceRequest;
use App\Http\Requests\Cms\CmsExtensionRecommendationRequest;
use App\Http\Requests\Cms\CmsExtensionReturnRequest;
use App\Http\Requests\Cms\CmsExtensionReviewRequest;
use App\Http\Requests\Cms\CmsExtensionRevisionRequest;
use App\Http\Requests\Cms\CmsExtensionSubmitRequest;
use App\Http\Resources\CmsRecommendationTargetDateHistoryResource;
use App\Http\Resources\CmsTargetDateExtensionResource;
use App\Models\CmsRecommendationCase;
use App\Models\CmsTargetDateExtensionRequest;
use App\Services\Cms\CmsTargetDateExtensionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Thin HTTP boundary for CMS-6A target-date extension workflow. */
class CmsTargetDateExtensionController extends Controller
{
    public function __construct(private readonly CmsTargetDateExtensionService $extensions) {}

    public function forRecommendation(Request $request, int $recommendation): JsonResponse
    {
        $result = $this->extensions->forRecommendation($request->user(), $recommendation);

        return response()->json([
            'success' => true,
            'data' => [
                'extensions' => CmsTargetDateExtensionResource::collection($result['extensions']),
                'caseContext' => $this->caseContext($result['case']),
                'permittedActions' => $result['permittedActions'],
            ],
        ]);
    }

    public function options(Request $request, int $recommendation): JsonResponse
    {
        $result = $this->extensions->options($request->user(), $recommendation);

        return response()->json([
            'success' => true,
            'data' => [
                'caseContext' => $this->caseContext($result['case']),
                ...collect($result)->except('case')->all(),
            ],
        ]);
    }

    public function show(Request $request, int $extension): JsonResponse
    {
        $result = $this->extensions->show($request->user(), $extension);
        $this->decorate($request, $result['extension']);

        return response()->json([
            'success' => true,
            'data' => [
                'extension' => new CmsTargetDateExtensionResource($result['extension']),
                'caseContext' => $this->caseContext($result['case']),
            ],
        ]);
    }

    public function store(CmsExtensionDraftRequest $request, int $recommendation): JsonResponse
    {
        $extension = $this->extensions->create($request, $recommendation, $request->validated());

        return $this->mutation($request, $extension, 'Target-date extension draft created.', 201);
    }

    public function update(CmsExtensionDraftRequest $request, int $extension, int $version): JsonResponse
    {
        $family = $this->extensions->update($request, $extension, $version, $request->validated());

        return $this->mutation($request, $family, 'Target-date extension draft updated.');
    }

    public function submit(CmsExtensionSubmitRequest $request, int $extension, int $version): JsonResponse
    {
        $family = $this->extensions->submit($request, $extension, $version, (int) $request->validated('lockVersion'));

        return $this->mutation($request, $family, 'Target-date extension submitted.');
    }

    public function startReview(CmsExtensionReviewRequest $request, int $extension, int $version): JsonResponse
    {
        $family = $this->extensions->startReview($request, $extension, $version, (int) $request->validated('lockVersion'), $request->validated('reviewComment'));

        return $this->mutation($request, $family, 'Target-date extension review started.');
    }

    public function return(CmsExtensionReturnRequest $request, int $extension, int $version): JsonResponse
    {
        $family = $this->extensions->returnVersion($request, $extension, $version, (int) $request->validated('lockVersion'), $request->validated('returnReason'));

        return $this->mutation($request, $family, 'Target-date extension returned.');
    }

    public function recommend(CmsExtensionRecommendationRequest $request, int $extension, int $version): JsonResponse
    {
        $family = $this->extensions->recommend($request, $extension, $version, $request->validated());

        return $this->mutation($request, $family, 'Target-date extension assessment completed.');
    }

    public function approve(CmsExtensionDecisionRequest $request, int $extension, int $version): JsonResponse
    {
        $family = $this->extensions->approve($request, $extension, $version, (int) $request->validated('lockVersion'), (string) $request->validated('decisionComment'), $request->validated('overrideReason'));

        return $this->mutation($request, $family, 'Target-date extension approved.');
    }

    public function reject(CmsExtensionDecisionRequest $request, int $extension, int $version): JsonResponse
    {
        $family = $this->extensions->reject($request, $extension, $version, (int) $request->validated('lockVersion'), (string) ($request->validated('rejectionReason') ?? $request->validated('decisionComment')), $request->validated('overrideReason'));

        return $this->mutation($request, $family, 'Target-date extension rejected.');
    }

    public function revise(CmsExtensionRevisionRequest $request, int $extension, int $version): JsonResponse
    {
        $family = $this->extensions->revise($request, $extension, $version, (int) $request->validated('lockVersion'), $request->validated('revisionReason'));

        return $this->mutation($request, $family, 'Target-date extension revision created.', 201);
    }

    public function uploadEvidence(CmsExtensionEvidenceRequest $request, int $extension, int $version): JsonResponse
    {
        $family = $this->extensions->uploadEvidence($request, $extension, $version, $request->validated(), $request->file('file'));

        return $this->mutation($request, $family, 'Target-date extension evidence linked.');
    }

    public function removeEvidence(CmsExtensionEvidenceRemoveRequest $request, int $evidence): JsonResponse
    {
        $family = $this->extensions->removeEvidence($request, $evidence, (int) $request->validated('lockVersion'), $request->validated('reason'));

        return $this->mutation($request, $family, 'Target-date extension evidence removed from the draft.');
    }

    public function downloadEvidence(Request $request, int $evidence)
    {
        return $this->extensions->downloadEvidence($request, $evidence);
    }

    public function history(Request $request, int $recommendation): JsonResponse
    {
        $result = $this->extensions->forRecommendation($request->user(), $recommendation);
        $history = $result['case']->load('targetDateHistory')->targetDateHistory;

        return response()->json([
            'success' => true,
            'data' => [
                'history' => CmsRecommendationTargetDateHistoryResource::collection($history),
                'caseContext' => $this->caseContext($result['case']),
            ],
        ]);
    }

    private function mutation(Request $request, CmsTargetDateExtensionRequest $extension, string $message, int $status = 200): JsonResponse
    {
        $extension->load($this->relationsForResource());
        $this->decorate($request, $extension);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['extension' => new CmsTargetDateExtensionResource($extension)],
        ], $status);
    }

    private function decorate(Request $request, CmsTargetDateExtensionRequest $extension): void
    {
        $extension->loadMissing($this->relationsForResource());
        foreach ($extension->versions as $version) {
            $version->setRelation('request', $extension);
            $version->setAttribute('available_actions', $this->extensions->permittedActions($request->user(), $extension, $version));
            $version->setAttribute('completeness', $this->completeness($version));
        }
        if ($extension->currentVersion) {
            $extension->currentVersion->setRelation('request', $extension);
            $extension->currentVersion->setAttribute('available_actions', $this->extensions->permittedActions($request->user(), $extension, $extension->currentVersion));
            $extension->currentVersion->setAttribute('completeness', $this->completeness($extension->currentVersion));
        }
    }

    /** @return array{complete: bool, errors: array<string, list<string>>} */
    private function completeness(mixed $version): array
    {
        $errors = [];
        foreach (['extension_justification' => 'extensionJustification', 'cause_of_delay' => 'causeOfDelay', 'actions_already_taken' => 'actionsAlreadyTaken', 'remaining_actions' => 'remainingActions', 'recovery_plan' => 'recoveryPlan', 'impact_if_not_approved' => 'impactIfNotApproved', 'revised_schedule_summary' => 'revisedScheduleSummary'] as $column => $field) {
            if (blank($version->{$column})) {
                $errors[$field][] = 'Complete this field before submission.';
            }
        }
        if ($version->activeEvidenceLinks()->count() === 0 && blank($version->no_evidence_explanation)) {
            $errors['evidence'][] = 'Link evidence or provide a no-evidence explanation.';
        }

        return ['complete' => $errors === [], 'errors' => $errors];
    }

    private function relationsForResource(): array
    {
        return [
            'case.recommendation', 'case.leadResponsibleOffice', 'case.currentAssignment.user',
            'creator', 'versions.previousVersion', 'versions.acceptedActionPlanVersion',
            'versions.recordedProgressUpdateVersion', 'versions.preparer', 'versions.submitter',
            'versions.reviewStarter', 'versions.returner', 'versions.assessment.assessor',
            'versions.decision.decider', 'versions.evidenceLinks.documentVersion',
            'versions.activeEvidenceLinks.documentVersion', 'currentVersion.assessment.assessor',
            'currentVersion.decision.decider', 'currentVersion.evidenceLinks.documentVersion',
            'currentVersion.activeEvidenceLinks.documentVersion', 'resolvedVersion.decision.decider',
        ];
    }

    /** @return array<string, mixed> */
    private function caseContext(CmsRecommendationCase $case): array
    {
        $case->loadMissing(['recommendation', 'leadResponsibleOffice', 'currentAssignment.user', 'targetDateHistory']);

        return [
            'id' => $case->id,
            'cmsRecommendationCode' => sprintf('CMS-REC-%06d', $case->id),
            'status' => $case->status_code,
            'lockVersion' => $case->lock_version,
            'originalTargetDate' => $case->recommendation?->original_target_implementation_date?->toDateString(),
            'effectiveTargetDate' => $case->effective_target_implementation_date?->toDateString(),
            'responsibleOffice' => $case->leadResponsibleOffice?->only(['id', 'code', 'name', 'acronym']),
            'currentComplianceMonitor' => $case->currentAssignment?->user ? [
                'id' => $case->currentAssignment->user->id,
                'employeeId' => $case->currentAssignment->user->employee_id,
                'name' => $case->currentAssignment->user->name,
                'initials' => $case->currentAssignment->user->initials,
            ] : null,
            'targetDateHistory' => CmsRecommendationTargetDateHistoryResource::collection($case->targetDateHistory),
        ];
    }
}
