<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Models\AuditEngagement;
use App\Models\CompletionAssessment;
use App\Models\CompletionAssessmentItem;
use App\Services\AemsCompletionAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AemsCompletionAssessmentController extends Controller
{
    public function __construct(
        private readonly AemsCompletionAssessmentService $assessments,
    ) {}

    public function index(Request $request, AuditEngagement $engagement): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->assessments->workspace($request, $engagement),
        ]);
    }

    public function store(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $assessment = $this->assessments->create(
            $request,
            $engagement,
            $request->validate($this->rules(false)),
        );

        return response()->json([
            'success' => true,
            'message' => 'Completion Assessment created.',
            'data' => ['assessment' => $this->assessments->assessmentData($assessment)],
        ], 201);
    }

    public function update(
        Request $request,
        AuditEngagement $engagement,
        CompletionAssessment $assessment,
    ): JsonResponse {
        $assessment = $this->assessments->update(
            $request,
            $engagement,
            $assessment,
            $request->validate($this->rules(true)),
        );

        return response()->json([
            'success' => true,
            'message' => 'Completion Assessment updated.',
            'data' => ['assessment' => $this->assessments->assessmentData($assessment)],
        ]);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        CompletionAssessment $assessment,
        string $action,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:10000'],
        ]);
        $assessment = $this->assessments->transition(
            $request,
            $engagement,
            $assessment,
            $action,
            $validated['lockVersion'],
            $validated['comment'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Completion Assessment workflow action completed.',
            'data' => ['assessment' => $this->assessments->assessmentData($assessment)],
        ]);
    }

    public function acceptBlocker(
        Request $request,
        AuditEngagement $engagement,
        CompletionAssessment $assessment,
        CompletionAssessmentItem $item,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:10000'],
        ]);
        $assessment = $this->assessments->acceptBlocker(
            $request,
            $engagement,
            $assessment,
            $item,
            $validated['lockVersion'],
            $validated['reason'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Blocking assessment item formally accepted.',
            'data' => ['assessment' => $this->assessments->assessmentData($assessment)],
        ]);
    }

    public function revise(
        Request $request,
        AuditEngagement $engagement,
        CompletionAssessment $assessment,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:10000'],
        ]);
        $revision = $this->assessments->revise(
            $request,
            $engagement,
            $assessment,
            $validated['reason'],
        );

        return response()->json([
            'success' => true,
            'message' => 'A controlled Completion Assessment revision was created.',
            'data' => ['assessment' => $this->assessments->assessmentData($revision)],
        ], 201);
    }

    /** @return array<string, mixed> */
    private function rules(bool $update): array
    {
        return [
            'lockVersion' => [$update ? 'required' : 'nullable', 'integer', 'min:1'],
            'periodFrom' => ['nullable', 'date'],
            'periodTo' => ['nullable', 'date', 'after_or_equal:periodFrom'],
            'overallResultCode' => ['required', Rule::in(['SATISFACTORY', 'PARTIALLY_SATISFACTORY', 'UNSATISFACTORY'])],
            'objectivesAchievementSummary' => ['required', 'string', 'max:20000'],
            'scopeCompletionSummary' => ['required', 'string', 'max:20000'],
            'methodologyAssessment' => ['required', 'string', 'max:20000'],
            'standardsComplianceAssessment' => ['required', 'string', 'max:20000'],
            'evidenceSufficiencyAssessment' => ['required', 'string', 'max:20000'],
            'supervisionAssessment' => ['required', 'string', 'max:20000'],
            'reportTimelinessAssessment' => ['required', 'string', 'max:20000'],
            'managementResponseAssessment' => ['required', 'string', 'max:20000'],
            'recommendationTransferAssessment' => ['required', 'string', 'max:20000'],
            'resourceUtilizationAssessment' => ['required', 'string', 'max:20000'],
            'limitationsSummary' => ['nullable', 'string', 'max:20000'],
            'lessonsSummary' => ['nullable', 'string', 'max:20000'],
            'recommendationForClosure' => ['required', 'string', 'max:20000'],
            'items' => ['sometimes', 'array', 'max:50'],
            'items.*.criterionCode' => ['required_with:items', 'string', 'max:80'],
            'items.*.plannedValue' => ['nullable', 'string', 'max:10000'],
            'items.*.actualValue' => ['nullable', 'string', 'max:10000'],
            'items.*.resultCode' => ['required_with:items', Rule::in([
                'PASS', 'FAIL', 'PARTIAL', 'NOT_APPLICABLE', 'PENDING',
            ])],
            'items.*.varianceValue' => ['nullable', 'numeric'],
            'items.*.explanation' => ['required_with:items', 'string', 'max:20000'],
            'items.*.blockingFlag' => ['sometimes', 'boolean'],
            'items.*.relatedRecordType' => ['nullable', 'string', 'max:120'],
            'items.*.relatedRecordId' => ['nullable', 'integer', 'min:1'],
            'items.*.responsibleUserId' => ['nullable', 'exists:users,id'],
        ];
    }
}
