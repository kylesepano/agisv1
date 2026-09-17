<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\CmsClosureRequestResource;
use App\Services\Cms\CmsClosureService;
use Illuminate\Http\Request;

class CmsClosureController extends Controller
{
    public function __construct(private readonly CmsClosureService $closures) {}

    public function index(Request $r, int $recommendation)
    {
        $x = $this->closures->forRecommendation($r->user(), $recommendation);

        return response()->json(['success' => true, 'data' => ['requests' => CmsClosureRequestResource::collection($x['requests']), 'caseContext' => $this->context($x['case']), 'permittedActions' => $x['permittedActions']]]);
    }

    public function options(Request $r, int $recommendation)
    {
        $x = $this->closures->options($r->user(), $recommendation);

        return response()->json(['success' => true, 'data' => ['caseContext' => $this->context($x['case']), ...collect($x)->except('case')->all()]]);
    }

    public function store(Request $r, int $recommendation)
    {
        $x = $this->closures->create($r, $recommendation, $r->all());

        return $this->response($x, 'Closure request draft created.', 201);
    }

    public function show(Request $r, int $id)
    {
        $x = $this->closures->show($r->user(), $id);

        return response()->json(['success' => true, 'data' => ['request' => new CmsClosureRequestResource($x['request']), 'caseContext' => $this->context($x['case'])]]);
    }

    public function update(Request $r, int $id, int $version)
    {
        $x = $this->closures->update($r, $id, $version, $r->all());

        return $this->response($x, 'Closure request draft updated.');
    }

    public function submit(Request $r, int $id, int $version)
    {
        $x = $this->closures->submit($r, $id, $version, $r->all());

        return $this->response($x, 'Closure request submitted.');
    }

    public function startReview(Request $r, int $id, int $version)
    {
        $x = $this->closures->startReview($r, $id, $version, $r->all());

        return $this->response($x, 'Closure review started.');
    }

    public function returnVersion(Request $r, int $id, int $version)
    {
        $x = $this->closures->returnVersion($r, $id, $version, $r->all());

        return $this->response($x, 'Closure request returned.');
    }

    public function recommend(Request $r, int $id, int $version)
    {
        $x = $this->closures->recommend($r, $id, $version, $r->all());

        return $this->response($x, 'Closure review assessment completed.');
    }

    public function approve(Request $r, int $id, int $version)
    {
        $x = $this->closures->decide($r, $id, $version, $r->all(), 'APPROVED');

        return $this->response($x, 'Recommendation formally closed.');
    }

    public function reject(Request $r, int $id, int $version)
    {
        $x = $this->closures->decide($r, $id, $version, $r->all(), 'REJECTED');

        return $this->response($x, 'Closure request rejected.');
    }

    public function revise(Request $r, int $id, int $version)
    {
        $x = $this->closures->revise($r, $id, $version, $r->all());

        return $this->response($x, 'Closure request revision created.', 201);
    }

    public function uploadEvidence(Request $r, int $id, int $version)
    {
        $x = $this->closures->linkEvidence($r, $id, $version, $r->all());

        return $this->response($x, 'Closure evidence linked.');
    }

    public function removeEvidence(Request $r, int $evidence)
    {
        $x = $this->closures->removeEvidence($r, $evidence, $r->all());

        return $this->response($x, 'Closure draft evidence removed.');
    }

    public function downloadEvidence(Request $r, int $evidence)
    {
        return $this->closures->downloadEvidence($r, $evidence);
    }

    private function response($x, string $message, int $status = 200)
    {
        $x->loadMissing(['currentVersion.assessment', 'currentVersion.decision']);

        return response()->json(['success' => true, 'message' => $message, 'data' => ['request' => new CmsClosureRequestResource($x)]], $status);
    }

    private function context($case): array
    {
        return ['id' => $case->id, 'status' => $case->status_code, 'lockVersion' => $case->lock_version, 'closedAt' => $case->closed_at];
    }
}
