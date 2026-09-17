<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\CmsDispositionRequestResource;
use App\Services\Cms\CmsDispositionService;
use Illuminate\Http\Request;

class CmsDispositionController extends Controller
{
    public function __construct(private readonly CmsDispositionService $dispositions) {}

    public function index(Request $request, int $recommendation)
    {
        $data = $this->dispositions->forRecommendation($request->user(), $recommendation);
        return response()->json(['success' => true, 'data' => ['requests' => CmsDispositionRequestResource::collection($data['requests']), 'caseContext' => $this->context($data['case']), 'permittedActions' => $data['permittedActions']]]);
    }

    public function options(Request $request, int $recommendation)
    {
        $data = $this->dispositions->options($request->user(), $recommendation);
        return response()->json(['success' => true, 'data' => ['caseContext' => $this->context($data['case']), ...collect($data)->except('case')->all()]]);
    }

    public function store(Request $request, int $recommendation)
    {
        return $this->response($this->dispositions->create($request, $recommendation, $request->all()), 'Disposition request draft created.', 201);
    }

    public function show(Request $request, int $id)
    {
        $data = $this->dispositions->show($request->user(), $id);
        return response()->json(['success' => true, 'data' => ['request' => new CmsDispositionRequestResource($data['request']), 'caseContext' => $this->context($data['case'])]]);
    }

    public function update(Request $request, int $id, int $version) { return $this->response($this->dispositions->update($request, $id, $version, $request->all()), 'Disposition draft updated.'); }
    public function submit(Request $request, int $id, int $version) { return $this->response($this->dispositions->submit($request, $id, $version, $request->all()), 'Disposition request submitted.'); }
    public function startReview(Request $request, int $id, int $version) { return $this->response($this->dispositions->startReview($request, $id, $version, $request->all()), 'Disposition review started.'); }
    public function returnVersion(Request $request, int $id, int $version) { return $this->response($this->dispositions->returnVersion($request, $id, $version, $request->all()), 'Disposition request returned.'); }
    public function recommend(Request $request, int $id, int $version) { return $this->response($this->dispositions->recommend($request, $id, $version, $request->all()), 'Disposition assessment completed.'); }
    public function approve(Request $request, int $id, int $version) { return $this->response($this->dispositions->decide($request, $id, $version, $request->all(), 'APPROVED'), 'Disposition approved.'); }
    public function reject(Request $request, int $id, int $version) { return $this->response($this->dispositions->decide($request, $id, $version, $request->all(), 'REJECTED'), 'Disposition rejected.'); }
    public function revise(Request $request, int $id, int $version) { return $this->response($this->dispositions->revise($request, $id, $version, $request->all()), 'Disposition revision created.', 201); }
    public function uploadEvidence(Request $request, int $id, int $version) { return $this->response($this->dispositions->linkEvidence($request, $id, $version, $request->all()), 'Disposition evidence linked.'); }
    public function removeEvidence(Request $request, int $evidence) { return $this->response($this->dispositions->removeEvidence($request, $evidence, $request->all()), 'Disposition draft evidence removed.'); }
    public function downloadEvidence(Request $request, int $evidence) { return $this->dispositions->downloadEvidence($request, $evidence); }

    private function response($requestModel, string $message, int $status = 200)
    {
        $requestModel->loadMissing(['case', 'currentVersion.assessment', 'currentVersion.decision']);
        return response()->json(['success' => true, 'message' => $message, 'data' => [
            'request' => new CmsDispositionRequestResource($requestModel),
            'caseContext' => $this->context($requestModel->case),
        ]], $status);
    }

    private function context($case): array { return ['id' => $case->id, 'status' => $case->status_code, 'lockVersion' => $case->lock_version, 'dispositionStatus' => $case->status_code, 'resolvedAt' => $case->closed_at]; }
}
