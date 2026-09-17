<?php

namespace App\Http\Controllers\Api\Ais;

use App\Http\Controllers\Controller;
use App\Services\Ais\AisAuditService;
use App\Services\Ais\AisReportService;
use App\Support\AisResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AisReportController extends Controller
{
    public function __construct(private readonly AisReportService $reports, private readonly AisAuditService $audit) {}

    public function catalog(Request $request): JsonResponse
    {
        $this->audit->view($request, 'ais.reports.catalog.viewed', 'Viewed the AIS report catalog.');

        return AisResponse::json(['success' => true, 'data' => $this->reports->catalog($request->user())], cacheable: true);
    }

    public function alerts(Request $request): JsonResponse
    {
        $this->audit->view($request, 'ais.alerts.viewed', 'Viewed AIS review-only attention indicators.');

        return AisResponse::json(['success' => true, 'data' => $this->reports->alerts($request->user())], cacheable: true);
    }

    public function runs(Request $request): JsonResponse
    {
        $this->audit->view($request, 'ais.report.runs.viewed', 'Viewed actor-owned AIS report runs.');
        $runs = $this->reports->runs($request->user());

        return AisResponse::json(['success' => true, 'data' => ['runs' => $runs->map(fn ($run): array => $this->reports->runData($run))->values(), 'meta' => ['total' => $runs->count()]]], cacheable: true);
    }

    public function show(Request $request, int $run): JsonResponse
    {
        $this->audit->view($request, 'ais.report.viewed', 'Viewed an immutable AIS report run.', ['runId' => $run]);

        return AisResponse::json(['success' => true, 'data' => ['run' => $this->reports->runData($this->reports->show($request->user(), $run))]], cacheable: true);
    }

    public function generate(Request $request, string $report): JsonResponse
    {
        $run = $this->reports->generate($request, $report, $request->all());

        return AisResponse::json(['success' => true, 'message' => 'AIS report generated from an immutable analytical snapshot.', 'data' => ['run' => $this->reports->runData($run)]], 201);
    }

    public function export(Request $request, int $run): JsonResponse
    {
        $validated = $request->validate(['format' => ['required', 'in:csv,pdf']]);
        $export = $this->reports->export($request, $run, $validated['format']);

        return AisResponse::json(['success' => true, 'message' => 'Protected AIS report export generated.', 'data' => ['export' => $this->reports->exportData($export)]], 201);
    }

    public function download(Request $request, int $export)
    {
        return $this->reports->download($request, $export);
    }
}
