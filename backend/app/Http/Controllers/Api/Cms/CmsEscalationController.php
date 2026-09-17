<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsEscalationAcknowledgementRequest;
use App\Http\Requests\Cms\CmsEscalationEvidenceRemoveRequest;
use App\Http\Requests\Cms\CmsEscalationEvidenceRequest;
use App\Http\Requests\Cms\CmsEscalationIssueRequest;
use App\Http\Requests\Cms\CmsEscalationNoticeDraftRequest;
use App\Http\Requests\Cms\CmsEscalationResolutionRequest;
use App\Http\Requests\Cms\CmsEscalationResponseAcceptRequest;
use App\Http\Requests\Cms\CmsEscalationResponseRequest;
use App\Http\Requests\Cms\CmsEscalationResponseReturnRequest;
use App\Http\Requests\Cms\CmsEscalationResponseReviewRequest;
use App\Http\Requests\Cms\CmsEscalationResponseSubmitRequest;
use App\Http\Requests\Cms\CmsEscalationReturnRequest;
use App\Http\Requests\Cms\CmsEscalationReviewRequest;
use App\Http\Requests\Cms\CmsEscalationSubmitRequest;
use App\Http\Resources\CmsEscalationResource;
use App\Http\Resources\CmsEscalationResponseResource;
use App\Services\Cms\CmsEscalationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsEscalationController extends Controller
{
    public function __construct(private readonly CmsEscalationService $escalations) {}

    public function forRecommendation(Request $request, int $recommendation): JsonResponse
    {
        $result = $this->escalations->forRecommendation($request->user(), $recommendation);

        return response()->json(['success' => true, 'data' => ['escalations' => CmsEscalationResource::collection($result['escalations']), 'caseContext' => $this->context($result['case']), 'permittedActions' => $result['permittedActions']]]);
    }

    public function options(Request $request, int $recommendation): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->escalations->options($request->user(), $recommendation)]);
    }

    public function show(Request $request, int $escalation): JsonResponse
    {
        $record = $this->escalations->show($request->user(), $escalation);
        $this->escalations->decorateAvailableActions($request->user(), $record);
        return response()->json(['success' => true, 'data' => ['escalation' => new CmsEscalationResource($record)]]);
    }

    public function store(CmsEscalationNoticeDraftRequest $request, int $recommendation): JsonResponse
    {
        $e = $this->escalations->createNotice($request, $recommendation, $request->validated());

        return $this->mutation($e, 'Escalation notice draft created.', 201);
    }

    public function update(CmsEscalationNoticeDraftRequest $request, int $escalation, int $version): JsonResponse
    {
        return $this->mutation($this->escalations->updateNotice($request, $escalation, $version, $request->validated()), 'Escalation notice draft updated.');
    }

    public function submit(CmsEscalationSubmitRequest $request, int $escalation, int $version): JsonResponse
    {
        return $this->mutation($this->escalations->submitNotice($request, $escalation, $version, (int) $request->validated('lockVersion')), 'Escalation notice submitted.');
    }

    public function startReview(CmsEscalationReviewRequest $request, int $escalation, int $version): JsonResponse
    {
        return $this->mutation($this->escalations->startNoticeReview($request, $escalation, $version, (int) $request->validated('lockVersion')), 'Escalation notice review started.');
    }

    public function returnNotice(CmsEscalationReturnRequest $request, int $escalation, int $version): JsonResponse
    {
        return $this->mutation($this->escalations->returnNotice($request, $escalation, $version, (int) $request->validated('lockVersion'), $request->validated('returnReason')), 'Escalation notice returned.');
    }

    public function issue(CmsEscalationIssueRequest $request, int $escalation, int $version): JsonResponse
    {
        return $this->mutation($this->escalations->issueNotice($request, $escalation, $version, (int) $request->validated('lockVersion'), $request->validated('issuanceComment')), 'Escalation notice issued.');
    }

    public function revise(CmsEscalationReturnRequest $request, int $escalation, int $version): JsonResponse
    {
        return $this->mutation($this->escalations->reviseNotice($request, $escalation, $version, (int) $request->validated('lockVersion'), $request->validated('returnReason')), 'Escalation notice revision created.', 201);
    }

    public function acknowledge(CmsEscalationAcknowledgementRequest $request, int $escalation): JsonResponse
    {
        return $this->mutation($this->escalations->acknowledge($request, $escalation, $request->validated('acknowledgementComment')), 'Escalation notice acknowledged.');
    }

    public function response(Request $request, int $escalation): JsonResponse
    {
        $e = $this->escalations->show($request->user(), $escalation);

        return response()->json(['success' => true, 'data' => ['response' => $e->response ? new CmsEscalationResponseResource($e->response->load(['versions', 'currentVersion', 'acceptedVersion'])) : null]]);
    }

    public function createResponse(CmsEscalationResponseRequest $request, int $escalation): JsonResponse
    {
        $r = $this->escalations->createResponse($request, $escalation, $request->validated());

        return response()->json(['success' => true, 'message' => 'Escalation response draft created.', 'data' => ['response' => new CmsEscalationResponseResource($r)]], 201);
    }

    public function updateResponse(CmsEscalationResponseRequest $request, int $response, int $version): JsonResponse
    {
        $r = $this->escalations->updateResponse($request, $response, $version, $request->validated());

        return response()->json(['success' => true, 'message' => 'Escalation response draft updated.', 'data' => ['response' => new CmsEscalationResponseResource($r)]]);
    }

    public function submitResponse(CmsEscalationResponseSubmitRequest $request, int $response, int $version): JsonResponse
    {
        return $this->responseMutation($this->escalations->submitResponse($request, $response, $version, (int) $request->validated('lockVersion')), 'Escalation response submitted.');
    }

    public function startResponseReview(CmsEscalationResponseReviewRequest $request, int $response, int $version): JsonResponse
    {
        return $this->responseMutation($this->escalations->startResponseReview($request, $response, $version, (int) $request->validated('lockVersion')), 'Escalation response review started.');
    }

    public function returnResponse(CmsEscalationResponseReturnRequest $request, int $response, int $version): JsonResponse
    {
        return $this->responseMutation($this->escalations->returnResponse($request, $response, $version, (int) $request->validated('lockVersion'), $request->validated('returnReason')), 'Escalation response returned.');
    }

    public function acceptResponse(CmsEscalationResponseAcceptRequest $request, int $response, int $version): JsonResponse
    {
        return $this->responseMutation($this->escalations->acceptResponse($request, $response, $version, (int) $request->validated('lockVersion'), $request->validated('acceptanceComment')), 'Escalation response accepted for follow-up.');
    }

    public function reviseResponse(CmsEscalationResponseReturnRequest $request, int $response, int $version): JsonResponse
    {
        return $this->responseMutation($this->escalations->reviseResponse($request, $response, $version, (int) $request->validated('lockVersion'), $request->validated('returnReason')), 'Escalation response revision created.', 201);
    }

    public function resolve(CmsEscalationResolutionRequest $request, int $escalation): JsonResponse
    {
        return $this->mutation($this->escalations->resolve($request, $escalation, (int) $request->validated('lockVersion'), $request->validated()), 'Escalation process resolved.');
    }

    public function uploadNoticeEvidence(CmsEscalationEvidenceRequest $request, int $escalation, int $version): JsonResponse
    {
        return $this->mutation($this->escalations->uploadNoticeEvidence($request, $escalation, $version, $request->validated(), $request->file('file')), 'Escalation notice evidence linked.');
    }

    public function downloadNoticeEvidence(Request $request, int $evidence)
    {
        return $this->escalations->downloadNoticeEvidence($request, $evidence);
    }

    public function removeNoticeEvidence(CmsEscalationEvidenceRemoveRequest $request, int $evidence): JsonResponse
    {
        return $this->mutation($this->escalations->removeNoticeEvidence($request, $evidence, (int) $request->validated('lockVersion'), $request->validated('reason')), 'Escalation notice evidence removed.');
    }

    public function uploadResponseEvidence(CmsEscalationEvidenceRequest $request, int $response, int $version): JsonResponse
    {
        return $this->responseMutation($this->escalations->uploadResponseEvidence($request, $response, $version, $request->validated(), $request->file('file')), 'Escalation response evidence linked.');
    }

    public function downloadResponseEvidence(Request $request, int $evidence)
    {
        return $this->escalations->downloadResponseEvidence($request, $evidence);
    }

    public function removeResponseEvidence(CmsEscalationEvidenceRemoveRequest $request, int $evidence): JsonResponse
    {
        return $this->responseMutation($this->escalations->removeResponseEvidence($request, $evidence, (int) $request->validated('lockVersion'), $request->validated('reason')), 'Escalation response evidence removed.');
    }

    private function mutation($e, string $message, int $status = 200): JsonResponse
    {
        if ($e instanceof \App\Models\CmsEscalation) {
            $this->escalations->decorateAvailableActions(request()->user(), $e);
        }
        return response()->json(['success' => true, 'message' => $message, 'data' => ['escalation' => new CmsEscalationResource($e)]], $status);
    }

    private function responseMutation($r, string $message, int $status = 200): JsonResponse
    {
        if ($r?->escalation) {
            $this->escalations->decorateAvailableActions(request()->user(), $r->escalation);
        }
        return response()->json(['success' => true, 'message' => $message, 'data' => ['response' => new CmsEscalationResponseResource($r)]], $status);
    }

    private function context($case): array
    {
        return ['id' => $case->id, 'status' => $case->status_code, 'lockVersion' => $case->lock_version, 'originalTargetDate' => $case->recommendation?->original_target_implementation_date?->toDateString(), 'effectiveTargetDate' => $case->effective_target_implementation_date?->toDateString(), 'responsibleOffice' => $case->leadResponsibleOffice?->only(['id', 'code', 'name', 'acronym'])];
    }
}
