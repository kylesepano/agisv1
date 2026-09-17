<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iap\IapBaicsControlRequest;
use App\Http\Requests\Iap\IapBaicsInterimAnalysisRequest;
use App\Http\Requests\Iap\IapBaicsReportRequest;
use App\Http\Resources\IapBaicsControlResource;
use App\Http\Resources\IapBaicsInterimAnalysisResource;
use App\Http\Resources\IapBaicsReportResource;
use App\Models\IapBaicsAssessment;
use App\Models\IapBaicsControl;
use App\Models\IapBaicsInterimAnalysis;
use App\Models\IapBaicsReport;
use App\Services\IapBaicsControlUniverseService;
use App\Services\IapSupport;
use App\Services\RuntimeConfiguration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** BAICS-3 Control Universe, interim analysis, BAR assembly, and exports. */
class IapBaicsControlUniverseController extends Controller
{
    public function __construct(
        private readonly IapBaicsControlUniverseService $service,
        private readonly IapSupport $support,
        private readonly RuntimeConfiguration $configuration,
    ) {}

    public function controls(Request $request, IapBaicsAssessment $assessment): JsonResponse
    {
        $this->visible($request, $assessment);
        $assessment = $this->service->loadControls($assessment);
        return response()->json(['success' => true, 'data' => ['controls' => IapBaicsControlResource::collection($assessment->controls), 'readiness' => $this->service->readiness($assessment)]]);
    }

    public function storeControl(IapBaicsControlRequest $request, IapBaicsAssessment $assessment): JsonResponse
    {
        $this->manage($request); $this->visible($request, $assessment);
        $control = $this->service->saveControl($request, $assessment, $request->validated());
        return response()->json(['success' => true, 'message' => 'Control Universe record created.', 'data' => ['control' => new IapBaicsControlResource($control)]], 201);
    }

    public function updateControl(IapBaicsControlRequest $request, IapBaicsAssessment $assessment, IapBaicsControl $control): JsonResponse
    {
        $this->manage($request); $this->controlVisible($request, $assessment, $control);
        $updated = $this->service->saveControl($request, $assessment, $request->validated(), $control);
        return response()->json(['success' => true, 'message' => 'Control Universe record updated.', 'data' => ['control' => new IapBaicsControlResource($updated)]]);
    }

    public function transitionControl(Request $request, IapBaicsAssessment $assessment, IapBaicsControl $control, string $action): JsonResponse
    {
        $permission = match (strtoupper($action)) { 'SUBMIT' => 'iap.baics.submit', 'RETURN' => 'iap.baics.return', 'APPROVE' => 'iap.baics.approve', default => null };
        abort_unless($permission && $request->user()->hasPermission($permission), 403);
        $this->controlVisible($request, $assessment, $control);
        $validated = $request->validate(['lockVersion' => ['required', 'integer', 'min:1'], 'comment' => ['nullable', 'string', 'max:10000']]);
        $updated = $this->service->transitionControl($request, $control, $action, $validated['comment'] ?? null);
        return response()->json(['success' => true, 'message' => 'Control Universe workflow updated.', 'data' => ['control' => new IapBaicsControlResource($updated)]]);
    }

    public function interimAnalyses(Request $request, IapBaicsAssessment $assessment): JsonResponse
    {
        $this->visible($request, $assessment);
        return response()->json(['success' => true, 'data' => ['interimAnalyses' => IapBaicsInterimAnalysisResource::collection($assessment->interimAnalyses()->with(['preparer:id,employee_id,name,initials,position', 'reviewer:id,employee_id,name,initials,position', 'approver:id,employee_id,name,initials,position'])->get())]]);
    }

    public function storeInterimAnalysis(IapBaicsInterimAnalysisRequest $request, IapBaicsAssessment $assessment): JsonResponse
    {
        $this->manage($request); $this->visible($request, $assessment);
        $analysis = $this->service->saveInterimAnalysis($request, $assessment, $request->validated());
        return response()->json(['success' => true, 'message' => 'Interim analysis drafted.', 'data' => ['interimAnalysis' => new IapBaicsInterimAnalysisResource($analysis)]], 201);
    }

    public function updateInterimAnalysis(IapBaicsInterimAnalysisRequest $request, IapBaicsAssessment $assessment, IapBaicsInterimAnalysis $analysis): JsonResponse
    {
        $this->manage($request); $this->analysisVisible($request, $assessment, $analysis);
        $updated = $this->service->saveInterimAnalysis($request, $assessment, $request->validated(), $analysis);
        return response()->json(['success' => true, 'message' => 'Interim analysis updated.', 'data' => ['interimAnalysis' => new IapBaicsInterimAnalysisResource($updated)] ]);
    }

    public function transitionInterimAnalysis(Request $request, IapBaicsAssessment $assessment, IapBaicsInterimAnalysis $analysis, string $action): JsonResponse
    {
        $permission = match (strtoupper($action)) { 'SUBMIT' => 'iap.baics.submit', 'RETURN' => 'iap.baics.return', 'APPROVE' => 'iap.baics.approve', default => null };
        abort_unless($permission && $request->user()->hasPermission($permission), 403); $this->analysisVisible($request, $assessment, $analysis);
        $validated = $request->validate(['comment' => ['nullable', 'string', 'max:10000']]);
        $updated = $this->service->transitionInterimAnalysis($request, $analysis, $action, $validated['comment'] ?? null);
        return response()->json(['success' => true, 'message' => 'Interim analysis workflow updated.', 'data' => ['interimAnalysis' => new IapBaicsInterimAnalysisResource($updated)]]);
    }

    public function reports(Request $request, IapBaicsAssessment $assessment): JsonResponse
    {
        $this->visible($request, $assessment); $assessment = $this->service->loadReports($assessment);
        return response()->json(['success' => true, 'data' => ['reports' => IapBaicsReportResource::collection($assessment->reports), 'readiness' => $this->service->readiness($assessment)]]);
    }

    public function storeReport(IapBaicsReportRequest $request, IapBaicsAssessment $assessment): JsonResponse
    {
        $this->manage($request); $this->visible($request, $assessment);
        $report = $this->service->saveReport($request, $assessment, $request->validated());
        return response()->json(['success' => true, 'message' => 'Baseline Assessment Report drafted.', 'data' => ['report' => new IapBaicsReportResource($report)]], 201);
    }

    public function updateReport(IapBaicsReportRequest $request, IapBaicsAssessment $assessment, IapBaicsReport $report): JsonResponse
    {
        $this->manage($request); $this->reportVisible($request, $assessment, $report);
        $updated = $this->service->saveReport($request, $assessment, $request->validated(), $report);
        return response()->json(['success' => true, 'message' => 'Baseline Assessment Report updated.', 'data' => ['report' => new IapBaicsReportResource($updated)]]);
    }

    public function transitionReport(Request $request, IapBaicsAssessment $assessment, IapBaicsReport $report, string $action): JsonResponse
    {
        $permission = match (strtoupper($action)) { 'SUBMIT' => 'iap.baics.submit', 'RETURN' => 'iap.baics.return', 'APPROVE', 'ISSUE', 'SUPERSEDE' => 'iap.baics.approve', default => null };
        abort_unless($permission && $request->user()->hasPermission($permission), 403); $this->reportVisible($request, $assessment, $report);
        $validated = $request->validate(['comment' => ['nullable', 'string', 'max:10000']]);
        $updated = $this->service->transitionReport($request, $report, $action, $validated['comment'] ?? null);
        return response()->json(['success' => true, 'message' => 'Baseline Assessment Report workflow updated.', 'data' => ['report' => new IapBaicsReportResource($updated)]]);
    }

    public function export(Request $request, IapBaicsAssessment $assessment, IapBaicsReport $report): StreamedResponse|\Illuminate\Http\Response
    {
        abort_unless($request->user()->hasPermission('iap.baics.export'), 403); $this->reportVisible($request, $assessment, $report);
        $format = $request->validate(['format' => ['required', 'in:pdf,csv']])['format']; $data = $this->service->exportData($report);
        $this->support->audit($request, 'iap.baics.report.exported', $report, null, ['format' => $format, 'fileVersion' => $data['fileVersion'], 'contentSha256' => $data['contentSha256']]);
        if ($format === 'pdf') {
            $sections = collect($data['sections'])->map(fn ($section) => ['title' => $section['title'], 'items' => [['heading' => '', 'text' => $section['text']]]])->all();
            return Pdf::loadView('reports.iap', ['report' => ['title' => $data['title'], 'description' => 'Baseline Assessment Report', 'meta' => $data['meta'], 'sections' => $sections, 'columns' => $data['columns'], 'rows' => $data['rows'], 'visualization' => null], 'print' => false, 'configuration' => $this->configuration->publicValues()])->setPaper('a4', 'landscape')->download($data['fileVersion'].'.pdf');
        }
        return response()->streamDownload(function () use ($data): void {
            $handle = fopen('php://output', 'w'); fwrite($handle, "\xEF\xBB\xBF"); fputcsv($handle, [$data['title']]); fputcsv($handle, ['Content checksum', $data['contentSha256']]); fputcsv($handle, ['Source manifest checksum', $data['sourceManifestSha256']]); fputcsv($handle, []); fputcsv($handle, array_column($data['columns'], 'label')); foreach ($data['rows'] as $row) fputcsv($handle, array_map(fn ($column) => $this->safeCsv($row[$column['key']] ?? ''), $data['columns'])); fclose($handle);
        }, $data['fileVersion'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCsv(mixed $value): mixed { $value = (string) $value; return in_array($value[0] ?? '', ['=', '+', '-', '@'], true) ? "'{$value}" : $value; }
    private function manage(Request $request): void { abort_unless($request->user()->hasPermission('iap.baics.manage-controls') || $request->user()->hasPermission('iap.baics.update'), 403); }
    private function visible(Request $request, IapBaicsAssessment $assessment): void { abort_unless($request->user()->hasGlobalOfficeAccess() || (int) $assessment->responsible_office_id === (int) $request->user()->office_id || $assessment->scopeItems()->where('office_id', $request->user()->office_id)->exists(), 403, 'This BAICS cycle is outside your office scope.'); }
    private function controlVisible(Request $request, IapBaicsAssessment $assessment, IapBaicsControl $control): void { $this->visible($request, $assessment); abort_unless((int) $control->assessment_id === (int) $assessment->id, 404); }
    private function analysisVisible(Request $request, IapBaicsAssessment $assessment, IapBaicsInterimAnalysis $analysis): void { $this->visible($request, $assessment); abort_unless((int) $analysis->assessment_id === (int) $assessment->id, 404); }
    private function reportVisible(Request $request, IapBaicsAssessment $assessment, IapBaicsReport $report): void { $this->visible($request, $assessment); abort_unless((int) $report->assessment_id === (int) $assessment->id, 404); }
}
