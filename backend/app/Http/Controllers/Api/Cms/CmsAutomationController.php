<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\CmsAutomationRuleResource;
use App\Http\Resources\CmsAutomationRunResource;
use App\Http\Resources\CmsClosureCandidateResource;
use App\Http\Resources\CmsEscalationCandidateResource;
use App\Models\CmsAutomationRun;
use App\Services\Cms\CmsAutomationService;
use Illuminate\Http\Request;

class CmsAutomationController extends Controller
{
    public function __construct(private readonly CmsAutomationService $automation) {}

    public function rules(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['rules' => CmsAutomationRuleResource::collection($this->automation->rules($request->user()))]]);
    }

    public function storeRule(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'Automation rule created.', 'data' => ['rule' => new CmsAutomationRuleResource($this->automation->saveRule($request->user(), $request->all()))]], 201);
    }

    public function updateRule(Request $request, int $rule)
    {
        return response()->json(['success' => true, 'message' => 'Automation rule version created.', 'data' => ['rule' => new CmsAutomationRuleResource($this->automation->saveRule($request->user(), $request->all(), $rule))]]);
    }

    public function run(Request $request)
    {
        $count = $this->automation->run($request->user(), $request->input('ruleCode'));
        return response()->json(['success' => true, 'message' => 'CMS automation run completed.', 'data' => ['createdCount' => $count]]);
    }

    public function runs(Request $request)
    {
        $runs = $this->automation->runs($request->user());
        return response()->json(['success' => true, 'data' => ['runs' => CmsAutomationRunResource::collection($runs), 'meta' => ['currentPage' => $runs->currentPage(), 'lastPage' => $runs->lastPage(), 'total' => $runs->total()]]]);
    }

    public function dashboard(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->automation->dashboard($request->user())]);
    }

    public function candidates(Request $request)
    {
        $data = $this->automation->candidates($request->user());
        return response()->json(['success' => true, 'data' => ['closureCandidates' => CmsClosureCandidateResource::collection($data['closureCandidates']), 'escalationCandidates' => CmsEscalationCandidateResource::collection($data['escalationCandidates'])]]);
    }

    public function readiness(Request $request, int $recommendation)
    {
        return response()->json(['success' => true, 'data' => $this->automation->readiness($request->user(), $recommendation)]);
    }

    public function reviewClosureCandidate(Request $request, int $candidate)
    {
        return response()->json(['success' => true, 'message' => 'Closure-readiness candidate reviewed.', 'data' => ['candidate' => new CmsClosureCandidateResource($this->automation->reviewClosureCandidate($request, $candidate, (string) $request->input('action')))] ]);
    }

    public function reviewEscalationCandidate(Request $request, int $candidate)
    {
        return response()->json(['success' => true, 'message' => 'Escalation candidate reviewed; no notice was issued automatically.', 'data' => ['candidate' => new CmsEscalationCandidateResource($this->automation->reviewEscalationCandidate($request, $candidate, (string) $request->input('action')))] ]);
    }
}
