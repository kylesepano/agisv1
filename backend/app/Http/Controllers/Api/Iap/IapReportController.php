<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\IapReportService;
use App\Services\RuntimeConfiguration;
use App\Support\ActivityRecorder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates role-scoped IAP reports and supported export representations.
 */
class IapReportController extends Controller
{
    public function __construct(
        private readonly IapReportService $reports,
        private readonly RuntimeConfiguration $configuration,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->reports->catalog($request->user()->loadMissing('role.permissions')),
        ]);
    }

    public function show(Request $request, string $report): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'report' => $this->reports->generate(
                    $report,
                    $this->filters($request),
                    $request->user(),
                ),
            ],
        ]);
    }

    public function export(Request $request, string $report): Response|StreamedResponse
    {
        $validated = $request->validate([
            'format' => ['required', 'in:pdf,excel,csv,print'],
        ]);
        $filters = $this->filters($request);
        $data = $this->reports->generate($report, $filters, $request->user());
        $format = $validated['format'];

        $response = match ($format) {
            'pdf' => Pdf::loadView('reports.iap', ['report' => $data, 'print' => false, 'configuration' => $this->configuration->publicValues()])
                ->setPaper('a4', count($data['columns']) > 7 ? 'landscape' : 'portrait')
                ->download($data['fileName'].'.pdf'),
            'excel' => response(
                "\xEF\xBB\xBF".$this->excelHtml($data),
                200,
                [
                    'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="'.$data['fileName'].'.xls"',
                ],
            ),
            'csv' => $this->csv($data),
            'print' => response()
                ->view('reports.iap', ['report' => $data, 'print' => true, 'configuration' => $this->configuration->publicValues()]),
        };
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'iap.report.exported',
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => [
                'report' => $report,
                'format' => $format,
                'filters' => $filters,
                'file_name' => $data['fileName'],
            ],
        ]);
        ActivityRecorder::record(
            $request,
            'iap.report.exported',
            "Exported {$data['title']} as {$format}.",
            metadata: ['module' => 'IAP', 'report' => $report, 'format' => $format, 'filters' => $filters],
        );

        return $response;
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'strategicPlanId' => ['nullable', 'integer'],
            'planId' => ['nullable', 'integer'],
            'riskPeriodId' => ['nullable', 'integer'],
            'prioritizationId' => ['nullable', 'integer'],
            'fiscalYear' => ['nullable', 'integer', 'min:2000', 'max:2200'],
        ]);
    }

    /** @param array<string, mixed> $report */
    private function csv(array $report): StreamedResponse
    {
        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [$report['title']]);
            fputcsv($handle, [$report['description']]);
            foreach ($report['meta'] as $meta) {
                fputcsv($handle, [$meta['label'], $meta['value']]);
            }
            fputcsv($handle, []);
            fputcsv($handle, array_column($report['columns'], 'label'));
            foreach ($report['rows'] as $row) {
                fputcsv(
                    $handle,
                    collect($report['columns'])
                        ->map(fn ($column) => $row[$column['key']] ?? '')
                        ->all(),
                );
            }
            fclose($handle);
        }, $report['fileName'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param array<string, mixed> $report */
    private function excelHtml(array $report): string
    {
        return view('reports.excel', ['report' => $report])->render();
    }
}
