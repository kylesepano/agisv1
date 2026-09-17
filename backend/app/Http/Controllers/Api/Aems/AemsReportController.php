<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Models\AuditEngagement;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Services\AemsReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exposes immutable Draft and Final Audit Report generation and issuance. */
class AemsReportController extends Controller
{
    public function __construct(private readonly AemsReportService $reports) {}

    public function engagements(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasAnyPermission(['aems.report.view', 'aems.report.view_issued']),
            403,
            'You cannot view AEMS reports.',
        );

        return response()->json([
            'success' => true,
            'data' => ['engagements' => $this->reports->engagements($request)],
        ]);
    }

    public function index(Request $request, AuditEngagement $engagement): JsonResponse
    {
        abort_unless(
            $request->user()->hasAnyPermission(['aems.report.view', 'aems.report.view_issued']),
            403,
            'You cannot view AEMS reports.',
        );

        return response()->json([
            'success' => true,
            'data' => $this->reports->workspace($request, $engagement),
        ]);
    }

    public function store(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $report = $this->reports->createDraft(
            $request,
            $engagement,
            $this->content($request, false, false),
        );

        return response()->json([
            'success' => true,
            'message' => 'Draft Report generated.',
            'data' => ['report' => $this->reports->reportData($report, $request->user())],
        ], 201);
    }

    public function interim(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $report = $this->reports->createInterim(
            $request,
            $engagement,
            $this->content($request, false, false),
        );

        return response()->json([
            'success' => true,
            'message' => 'Interim Audit Report generated.',
            'data' => ['report' => $this->reports->reportData($report, $request->user())],
        ], 201);
    }

    public function revise(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
    ): JsonResponse {
        $report = $this->reports->revise(
            $request,
            $engagement,
            $report,
            $this->content($request, $report->report_stage === 'FINAL_REPORT', true),
        );

        return response()->json([
            'success' => true,
            'message' => 'Immutable report revision generated.',
            'data' => ['report' => $this->reports->reportData($report, $request->user())],
        ]);
    }

    public function createFinal(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
    ): JsonResponse {
        $report = $this->reports->createFinal(
            $request,
            $engagement,
            $report,
            $this->content($request, true, true),
        );

        return response()->json([
            'success' => true,
            'message' => 'Final Report draft generated from finalized Findings.',
            'data' => ['report' => $this->reports->reportData($report, $request->user())],
        ]);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['SUBMIT', 'RETURN', 'APPROVE', 'ISSUE'])],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:20000'],
            'issuanceDate' => ['nullable', 'date'],
        ]);
        $report = $this->reports->transition(
            $request,
            $engagement,
            $report,
            $validated['action'],
            $validated['lockVersion'],
            $validated['comment'] ?? null,
            $validated['issuanceDate'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Report workflow action completed.',
            'data' => ['report' => $this->reports->reportData($report, $request->user())],
        ]);
    }

    public function transferRecommendations(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);
        $transfers = $this->reports->retryCmsTransfer(
            $request,
            $engagement,
            $report,
            $validated['lockVersion'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Recommendation transfer is synchronized idempotently.',
            'data' => ['transfers' => $transfers],
        ]);
    }

    public function distributionDecision(Request $request, AuditEngagement $engagement, AuditReport $report, AuditReportVersion $version, \App\Models\ReportRecipient $recipient): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['DELIVERED', 'ACKNOWLEDGED', 'REJECTED'])],
            'comment' => ['nullable', 'string', 'max:10000'],
        ]);
        $decision = $this->reports->distributionDecision($request, $engagement, $report, $version, $recipient, $validated['decision'], $validated['comment'] ?? null);

        return response()->json(['success' => true, 'message' => 'Report distribution decision recorded.', 'data' => ['decision' => [
            'id' => $decision->id, 'decision' => $decision->decision_code, 'comment' => $decision->comment,
            'decidedAt' => $decision->decided_at?->toISOString(),
        ]]]);
    }

    public function successor(Request $request, AuditEngagement $engagement, AuditReport $report): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['AMEND', 'SUPERSEDE'])],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:10000'],
        ]);
        $successor = $this->reports->createSuccessor($request, $engagement, $report, $validated['lockVersion'], $validated['action'], $validated['reason']);

        return response()->json(['success' => true, 'message' => 'Controlled report successor generated.', 'data' => ['report' => $this->reports->reportData($successor, $request->user())]]);
    }

    public function withdraw(Request $request, AuditEngagement $engagement, AuditReport $report): JsonResponse
    {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:10000'],
        ]);
        $withdrawn = $this->reports->withdraw($request, $engagement, $report, $validated['lockVersion'], $validated['reason']);

        return response()->json(['success' => true, 'message' => 'Issued report withdrawn without altering its immutable version.', 'data' => ['report' => $this->reports->reportData($withdrawn, $request->user())]]);
    }

    public function authorityDecision(Request $request, AuditEngagement $engagement, AuditReport $report, AuditReportVersion $version): JsonResponse
    {
        $decision = $this->reports->recordAuthorityDecision($request, $engagement, $report, $version, $request->validate([
            'authorityRole' => ['required', Rule::in(['IAU_HEAD_RECOMMENDATION', 'LCE_APPROVAL', 'PRESIDING_OFFICER_APPROVAL'])],
            'decisionCode' => ['required', Rule::in(['RECOMMEND', 'APPROVE', 'RETURN', 'REJECT'])],
            'comment' => ['nullable', 'string', 'max:10000'], 'decisionReference' => ['nullable', 'string', 'max:160'],
        ]));
        return response()->json(['success' => true, 'data' => ['authorityDecision' => $decision->load('decider')]]);
    }

    public function signatory(Request $request, AuditEngagement $engagement, AuditReport $report, AuditReportVersion $version): JsonResponse
    {
        $signatory = $this->reports->recordSignatory($request, $engagement, $report, $version, $request->validate([
            'signatoryRole' => ['required', Rule::in(['IAU_HEAD', 'LCE', 'PRESIDING_OFFICER', 'REPORT_ISSUER'])],
            'userId' => ['nullable', 'integer', 'required_without:signatoryName'], 'signatoryName' => ['nullable', 'string', 'max:255', 'required_without:userId'],
            'signatureMethod' => ['required', Rule::in(['CONTROLLED_WORKFLOW', 'DIGITAL', 'WET_SIGNATURE', 'E_SIGNATURE'])],
            'signatureReference' => ['nullable', 'string', 'max:160'], 'signedAt' => ['nullable', 'date'],
        ]));
        return response()->json(['success' => true, 'data' => ['signatory' => $signatory->load('user')]]);
    }

    public function transmittal(Request $request, AuditEngagement $engagement, AuditReport $report, AuditReportVersion $version): JsonResponse
    {
        $transmittal = $this->reports->createTransmittal($request, $engagement, $report, $version, $request->validate([
            'transmittalReference' => ['required', 'string', 'max:160'],
            'transmittalMethod' => ['required', Rule::in(['CONTROLLED_SYSTEM', 'EMAIL', 'HAND_DELIVERY', 'REGISTERED_MAIL'])],
            'deliveryStatus' => ['nullable', Rule::in(['PREPARED', 'SENT', 'DELIVERED', 'ACKNOWLEDGED'])], 'note' => ['nullable', 'string', 'max:10000'], 'sentAt' => ['nullable', 'date'],
        ]));
        return response()->json(['success' => true, 'data' => ['transmittal' => $transmittal]]);
    }

    public function administrativeClose(Request $request, AuditEngagement $engagement, AuditReport $report): JsonResponse
    {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:5', 'max:10000'], 'reference' => ['nullable', 'string', 'max:160'],
        ]);
        $closed = $this->reports->administrativeClose($request, $engagement, $report, $validated['lockVersion'], $validated['reason'], $validated['reference'] ?? null);
        return response()->json(['success' => true, 'data' => ['report' => $this->reports->reportData($closed, $request->user())]]);
    }

    public function export(Request $request, AuditEngagement $engagement, AuditReport $report, AuditReportVersion $version, string $format): StreamedResponse
    {
        $export = $this->reports->export($request, $engagement, $report, $version, $format);
        return Storage::disk('local')->download($export->storage_path, $export->file_name, ['Content-Type' => strtoupper($format) === 'PDF' ? 'application/pdf' : 'text/csv']);
    }

    public function download(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
    ): StreamedResponse {
        $documentVersion = $this->reports->download(
            $request,
            $engagement,
            $report,
            $version,
        );

        return Storage::disk('local')->download(
            $documentVersion->storage_path,
            $documentVersion->original_file_name,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /** @return array<string, mixed> */
    private function content(Request $request, bool $final, bool $revision): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'executiveSummary' => ['required', 'string', 'min:10', 'max:60000'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.content' => ['required', 'string', 'max:60000'],
            'qualityChecklist' => ['nullable', 'array'],
            'qualityChecklist.*.code' => ['required', 'string', 'max:80'],
            'qualityChecklist.*.label' => ['required', 'string', 'max:255'],
            'qualityChecklist.*.completed' => ['required', 'boolean'],
            'findingIds' => ['required', 'array', 'min:1'],
            'findingIds.*' => ['required', 'integer', 'distinct'],
            'issueIds' => ['nullable', 'array'], 'issueIds.*' => ['integer', 'distinct'],
            'workingPaperVersionIds' => ['nullable', 'array'], 'workingPaperVersionIds.*' => ['integer', 'distinct'],
            'evidenceIds' => ['nullable', 'array'], 'evidenceIds.*' => ['integer', 'distinct'],
            'sourceInterimReportVersionId' => ['nullable', 'integer'],
            'interimTreatment' => ['nullable', Rule::in(['RETAINED_WITH_REVIEW', 'REVISED', 'OMITTED', 'RESOLVED'])],
            'sourceTreatments' => ['nullable', 'array'], 'linkReasons' => ['nullable', 'array'],
            'confidentialityLevelId' => ['required', 'integer'],
            'approvingAuthority' => [
                $final ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'recipients' => [$final ? 'required' : 'nullable', 'array', $final ? 'min:1' : 'max:100'],
            'recipients.*.recipientType' => [
                'required',
                Rule::in(['USER', 'OFFICE', 'EXTERNAL']),
            ],
            'recipients.*.userId' => ['nullable', 'integer'],
            'recipients.*.officeId' => ['nullable', 'integer'],
            'recipients.*.externalName' => ['nullable', 'string', 'max:255'],
            'recipients.*.externalEmail' => ['nullable', 'email', 'max:255'],
            'recipients.*.deliveryMethod' => [
                'nullable',
                Rule::in(['SYSTEM', 'EMAIL', 'HAND_DELIVERY', 'REGISTERED_MAIL']),
            ],
            'changeReason' => [
                $revision ? 'required' : 'nullable',
                'string',
                'max:10000',
            ],
            'lockVersion' => [$revision ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
    }
}
