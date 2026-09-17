<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Models\AuditEngagement;
use App\Models\AuditEngagementPlan;
use App\Services\AemsAccessService;
use App\Services\AemsAepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Exposes the versioned AEP workspace and its controlled review and approval
 * actions. Business constraints remain in AemsAepService.
 */
class AemsAepController extends Controller
{
    public function __construct(
        private readonly AemsAepService $plans,
        private readonly AemsAccessService $access,
    ) {}

    public function show(Request $request, AuditEngagement $engagement): JsonResponse
    {
        Gate::authorize('view', $engagement);
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.aep.view');

        return response()->json(['success' => true, 'data' => $this->plans->workspace($engagement)]);
    }

    public function store(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $plan = $this->plans->create($request, $engagement, $this->content($request));

        return response()->json([
            'success' => true,
            'message' => 'Draft Audit Engagement Plan created.',
            'data' => ['plan' => $plan],
        ], 201);
    }

    public function update(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementPlan $plan,
    ): JsonResponse {
        $validated = $this->content($request);
        $validated['lockVersion'] = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
        ])['lockVersion'];
        $plan = $this->plans->update($request, $engagement, $plan, $validated);

        return response()->json([
            'success' => true,
            'message' => 'A new immutable AEP version was created.',
            'data' => ['plan' => $plan],
        ]);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementPlan $plan,
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['SUBMIT', 'REVIEW', 'RETURN', 'RESUBMIT', 'APPROVE'])],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:4000'],
        ]);
        $plan = $this->plans->transition(
            $request,
            $engagement,
            $plan,
            $validated['action'],
            $validated['lockVersion'],
            $validated['comment'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'AEP workflow action completed.',
            'data' => ['plan' => $plan],
        ]);
    }

    public function revise(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementPlan $plan,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:4000'],
        ]);
        $plan = $this->plans->revise(
            $request,
            $engagement,
            $plan,
            $validated['lockVersion'],
            $validated['reason'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Formal AEP revision started.',
            'data' => ['plan' => $plan],
        ]);
    }

    /** @return array<string, mixed> */
    private function content(Request $request): array
    {
        return $request->validate([
            'objectives' => ['required', 'string', 'min:5', 'max:20000'],
            'scope' => ['required', 'string', 'min:5', 'max:20000'],
            'exclusions' => ['nullable', 'string', 'max:10000'],
            'methodology' => ['required', 'string', 'min:5', 'max:20000'],
            'auditCriteria' => ['required', 'string', 'min:5', 'max:20000'],
            'materiality' => ['nullable', 'string', 'max:10000'],
            'samplingApproach' => ['nullable', 'string', 'max:10000'],
            'plannedStartDate' => ['required', 'date'],
            'plannedEndDate' => ['required', 'date', 'after_or_equal:plannedStartDate'],
            'expectedReportDate' => ['nullable', 'date', 'after_or_equal:plannedEndDate'],
            'plannedPersonDays' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'resourceRequirements' => ['nullable', 'array'],
            'resourceRequirements.staffing' => ['nullable', 'string', 'max:4000'],
            'resourceRequirements.skills' => ['nullable', 'string', 'max:4000'],
            'resourceRequirements.tools' => ['nullable', 'string', 'max:4000'],
            'resourceRequirements.logistics' => ['nullable', 'string', 'max:4000'],
            'managementCoordination' => ['nullable', 'array'],
            'managementCoordination.contactPerson' => ['nullable', 'string', 'max:255'],
            'managementCoordination.contactDetails' => ['nullable', 'string', 'max:1000'],
            'managementCoordination.kickoffDetails' => ['nullable', 'string', 'max:4000'],
            'managementCoordination.recordsDeadline' => ['nullable', 'date'],
            'managementCoordination.notes' => ['nullable', 'string', 'max:4000'],
            'confidentialityLevelId' => [
                'nullable',
                'integer',
                Rule::exists('master_list_items', 'id')->whereNull('deleted_at'),
            ],
            'changeReason' => ['nullable', 'string', 'max:4000'],
        ]);
    }
}
