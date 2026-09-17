<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\CmsReopeningRequestResource;
use App\Services\Cms\CmsReopeningService;
use Illuminate\Http\Request;

class CmsReopeningController extends Controller
{
    public function __construct(private readonly CmsReopeningService $reopening) {}

    public function index(Request $request, int $recommendation)
    {
        $data = $this->reopening->forRecommendation($request->user(), $recommendation);

        return response()->json(['success' => true, 'data' => ['requests' => CmsReopeningRequestResource::collection($data['requests']), 'caseContext' => $this->context($data['case']), 'permittedActions' => $data['permittedActions']]]);
    }

    public function options(Request $request, int $recommendation)
    {
        $data = $this->reopening->options($request->user(), $recommendation);

        return response()->json(['success' => true, 'data' => ['caseContext' => $this->context($data['case']), ...collect($data)->except('case')->all()]]);
    }

    public function store(Request $request, int $recommendation)
    {
        return $this->response($this->reopening->create($request, $recommendation, $request->all()), 'Reopening request draft created.', 201);
    }

    public function show(Request $request, int $id)
    {
        $data = $this->reopening->show($request->user(), $id);

        return response()->json(['success' => true, 'data' => ['request' => new CmsReopeningRequestResource($data['request']), 'caseContext' => $this->context($data['case'])]]);
    }

    public function update(Request $request, int $id, int $version)
    {
        return $this->response($this->reopening->update($request, $id, $version, $request->all()), 'Reopening draft updated.');
    }

    public function submit(Request $request, int $id, int $version)
    {
        return $this->response($this->reopening->submit($request, $id, $version, $request->all()), 'Reopening request submitted.');
    }

    public function startReview(Request $request, int $id, int $version)
    {
        return $this->response($this->reopening->startReview($request, $id, $version, $request->all()), 'Reopening review started.');
    }

    public function returnVersion(Request $request, int $id, int $version)
    {
        return $this->response($this->reopening->returnVersion($request, $id, $version, $request->all()), 'Reopening request returned.');
    }

    public function recommend(Request $request, int $id, int $version)
    {
        return $this->response($this->reopening->recommend($request, $id, $version, $request->all()), 'Reopening assessment completed.');
    }

    public function approve(Request $request, int $id, int $version)
    {
        return $this->response($this->reopening->decide($request, $id, $version, $request->all(), 'APPROVED'), 'Recommendation reopened.');
    }

    public function reject(Request $request, int $id, int $version)
    {
        return $this->response($this->reopening->decide($request, $id, $version, $request->all(), 'REJECTED'), 'Reopening request rejected.');
    }

    public function revise(Request $request, int $id, int $version)
    {
        return $this->response($this->reopening->revise($request, $id, $version, $request->all()), 'Reopening revision created.', 201);
    }

    public function uploadEvidence(Request $request, int $id, int $version)
    {
        return $this->response($this->reopening->linkEvidence($request, $id, $version, $request->all()), 'Reopening evidence linked.');
    }

    public function removeEvidence(Request $request, int $evidence)
    {
        return $this->response($this->reopening->removeEvidence($request, $evidence, $request->all()), 'Reopening draft evidence removed.');
    }

    public function downloadEvidence(Request $request, int $evidence)
    {
        return $this->reopening->downloadEvidence($request, $evidence);
    }

    private function response($model, string $message, int $status = 200)
    {
        $model->loadMissing(['case', 'currentVersion.assessment', 'currentVersion.decision']);

        return response()->json(['success' => true, 'message' => $message, 'data' => ['request' => new CmsReopeningRequestResource($model), 'caseContext' => $this->context($model->case)]], $status);
    }

    private function context($case): array
    {
        return ['id' => $case->id, 'status' => $case->status_code, 'lockVersion' => $case->lock_version, 'activeCycleNumber' => $case->active_cycle_number, 'reopeningCount' => $case->reopening_count, 'lastReopenedAt' => $case->last_reopened_at?->toISOString(), 'lastReopeningDecisionId' => $case->last_reopening_decision_id];
    }
}
