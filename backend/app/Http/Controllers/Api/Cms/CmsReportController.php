<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\CmsReportExportResource;
use App\Http\Resources\CmsReportRunResource;
use App\Services\Cms\CmsReportService;
use Illuminate\Http\Request;

/** Protected CMS report catalog, snapshot, export, and download endpoints. */
class CmsReportController extends Controller
{
    public function __construct(private readonly CmsReportService $reports) {}

    public function catalog(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->reports->catalog($request->user())]);
    }

    public function runs(Request $request)
    {
        $runs = $this->reports->runs($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'runs' => CmsReportRunResource::collection($runs),
                'meta' => ['total' => $runs->count()],
            ],
        ]);
    }

    public function generate(Request $request, string $report)
    {
        $run = $this->reports->generate($request, $report, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'CMS report generated from an immutable snapshot.',
            'data' => ['run' => new CmsReportRunResource($run)],
        ], 201);
    }

    public function show(Request $request, int $run)
    {
        return response()->json([
            'success' => true,
            'data' => ['run' => new CmsReportRunResource($this->reports->show($request->user(), $run))],
        ]);
    }

    public function export(Request $request, int $run)
    {
        $validated = $request->validate(['format' => ['required', 'in:csv,pdf']]);
        $export = $this->reports->export($request, $run, $validated['format']);

        return response()->json([
            'success' => true,
            'message' => 'Protected CMS report export generated.',
            'data' => ['export' => new CmsReportExportResource($export->load('run'))],
        ], 201);
    }

    public function download(Request $request, int $export)
    {
        return $this->reports->download($request, $export);
    }
}
