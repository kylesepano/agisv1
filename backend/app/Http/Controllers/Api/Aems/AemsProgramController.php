<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Models\AuditEngagement;
use App\Models\AuditProgram;
use App\Models\AuditProgramProcedure;
use App\Services\AemsAccessService;
use App\Services\AemsProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Provides the versioned Audit Program workspace and procedure operations. */
class AemsProgramController extends Controller
{
    public function __construct(
        private readonly AemsProgramService $programs,
        private readonly AemsAccessService $access,
    ) {}

    public function index(Request $request, AuditEngagement $engagement): JsonResponse
    {
        Gate::authorize('view', $engagement);
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.program.view');

        return response()->json(['success' => true, 'data' => $this->programs->workspace($engagement)]);
    }

    public function store(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $program = $this->programs->create($request, $engagement, $this->programContent($request));

        return response()->json([
            'success' => true,
            'message' => 'Draft Audit Program created.',
            'data' => ['program' => $program],
        ], 201);
    }

    public function update(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
    ): JsonResponse {
        $validated = [
            ...$this->programContent($request),
            ...$request->validate(['lockVersion' => ['required', 'integer', 'min:1']]),
        ];
        $program = $this->programs->update($request, $engagement, $program, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Audit Program updated.',
            'data' => ['program' => $program],
        ]);
    }

    public function storeProcedure(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
    ): JsonResponse {
        $procedure = $this->programs->addProcedure(
            $request,
            $engagement,
            $program,
            $this->procedureContent($request, true),
        );

        return response()->json([
            'success' => true,
            'message' => 'Audit procedure added.',
            'data' => ['procedure' => $procedure],
        ], 201);
    }

    public function updateProcedure(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
        AuditProgramProcedure $procedure,
    ): JsonResponse {
        $validated = [
            ...$this->procedureContent($request, true),
            ...$request->validate(['lockVersion' => ['required', 'integer', 'min:1']]),
        ];
        $procedure = $this->programs->updateProcedure(
            $request,
            $engagement,
            $program,
            $procedure,
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Audit procedure updated.',
            'data' => ['procedure' => $procedure],
        ]);
    }

    public function destroyProcedure(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
        AuditProgramProcedure $procedure,
    ): JsonResponse {
        $validated = $request->validate([
            'programLockVersion' => ['required', 'integer', 'min:1'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);
        $this->programs->removeProcedure(
            $request,
            $engagement,
            $program,
            $procedure,
            $validated['programLockVersion'],
            $validated['lockVersion'],
        );

        return response()->json(['success' => true, 'message' => 'Audit procedure archived.']);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['SUBMIT', 'REVIEW', 'RETURN', 'RESUBMIT', 'APPROVE', 'START', 'COMPLETE'])],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:4000'],
        ]);
        $program = $this->programs->transition(
            $request,
            $engagement,
            $program,
            $validated['action'],
            $validated['lockVersion'],
            $validated['comment'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Audit Program workflow action completed.',
            'data' => ['program' => $program],
        ]);
    }

    public function progressProcedure(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
        AuditProgramProcedure $procedure,
    ): JsonResponse {
        $validated = $request->validate([
            'programLockVersion' => ['required', 'integer', 'min:1'],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in(AuditProgramProcedure::STATUSES)],
            'workingPaperReference' => ['nullable', 'string', 'max:120'],
            'waiverReason' => ['nullable', 'string', 'max:4000'],
            'results' => ['nullable', 'string', 'max:20000'],
            'conclusion' => ['nullable', 'string', 'max:20000'],
            'reviewState' => ['nullable', 'string', 'max:40'],
            'relatedTasks' => ['sometimes', 'array', 'max:100'],
            'relatedTasks.*' => ['string', 'max:255'],
            'relatedRecords' => ['sometimes', 'array', 'max:100'],
            'relatedRecords.*' => ['string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:4000'],
        ]);
        $procedure = $this->programs->progressProcedure(
            $request,
            $engagement,
            $program,
            $procedure,
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Procedure progress updated.',
            'data' => ['procedure' => $procedure],
        ]);
    }

    public function reviewProcedure(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
        AuditProgramProcedure $procedure,
    ): JsonResponse {
        $validated = $request->validate([
            'programLockVersion' => ['required', 'integer', 'min:1'],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reviewerResult' => ['required', Rule::in(AuditProgramProcedure::REVIEWER_RESULTS)],
            'reviewerComments' => ['nullable', 'string', 'max:4000'],
        ]);
        $procedure = $this->programs->reviewProcedure(
            $request,
            $engagement,
            $program,
            $procedure,
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Procedure reviewer result recorded.',
            'data' => ['procedure' => $procedure],
        ]);
    }

    public function revise(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:4000'],
        ]);
        $program = $this->programs->revise(
            $request,
            $engagement,
            $program,
            $validated['lockVersion'],
            $validated['reason'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Documented Audit Program revision started.',
            'data' => ['program' => $program],
        ]);
    }

    /** @return array<string, mixed> */
    private function programContent(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'objective' => ['required', 'string', 'min:5', 'max:10000'],
            'auditAreaId' => ['nullable', 'integer', 'exists:audit_areas,id'],
            'auditTypeId' => ['nullable', 'integer', 'exists:master_list_items,id'],
            'auditPeriodStart' => ['nullable', 'date'],
            'auditPeriodEnd' => ['nullable', 'date', 'after_or_equal:auditPeriodStart'],
            'auditCriteria' => ['nullable', 'string', 'max:20000'],
            'riskStatementSet' => ['sometimes', 'array', 'max:500'],
            'riskStatementSet.*' => ['string', 'max:255'],
            'samplingApproach' => ['nullable', 'string', 'max:10000'],
            'plannedWorkingPaperRequirements' => ['sometimes', 'array', 'max:500'],
        ]);
    }

    /** @return array<string, mixed> */
    private function procedureContent(Request $request, bool $includeProgramLock): array
    {
        $rules = [
            'procedureCode' => ['required', 'string', 'max:80'],
            'sequenceNumber' => ['required', 'integer', 'min:1', 'max:9999'],
            'objective' => ['required', 'string', 'min:3', 'max:10000'],
            'procedureDescription' => ['required', 'string', 'min:5', 'max:20000'],
            'expectedEvidence' => ['required', 'string', 'min:3', 'max:10000'],
            'workingPaperReference' => ['nullable', 'string', 'max:120'],
            'assignedTo' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'targetDate' => ['required', 'date'],
            'auditAreaId' => ['nullable', 'integer', 'exists:audit_areas,id'],
            'auditFocusId' => ['nullable', 'integer', 'exists:audit_focuses,id'],
            'processFlowId' => ['nullable', 'integer', 'exists:aems_process_flow_documents,id'],
            'processName' => ['nullable', 'string', 'max:255'],
            'auditMethod' => ['nullable', 'string', 'max:100'],
            'auditCriteria' => ['nullable', 'string', 'max:20000'],
            'plannedPersonDays' => ['nullable', 'numeric', 'min:0'],
            'samplingRequirement' => ['sometimes', 'array'],
            'samplingRequirement.method' => ['nullable', 'string', 'max:255'],
            'samplingRequirement.population' => ['nullable', 'string', 'max:10000'],
            'samplingRequirement.sampleSize' => ['nullable', 'numeric', 'min:0'],
            'plannedWorkingPaperRequirement' => ['sometimes', 'array'],
            'plannedWorkingPaperRequirement.reference' => ['nullable', 'string', 'max:120'],
            'plannedWorkingPaperRequirement.requiredEvidence' => ['nullable', 'string', 'max:10000'],
            'riskStatementIds' => ['sometimes', 'array', 'max:500'],
            'riskStatementIds.*' => ['string', 'max:120'],
        ];
        if ($includeProgramLock) {
            $rules['programLockVersion'] = ['required', 'integer', 'min:1'];
        }

        return $request->validate($rules);
    }
}
