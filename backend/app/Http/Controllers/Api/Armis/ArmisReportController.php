<?php

namespace App\Http\Controllers\Api\Armis;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArmisReportExportResource;
use App\Http\Resources\ArmisReportRunResource;
use App\Services\ArmisReportService;
use Illuminate\Http\Request;

/** Protected ARMIS report catalog, snapshots, exports, and administration. */
class ArmisReportController extends Controller
{
    public function __construct(private readonly ArmisReportService $reports) {}

    public function catalog(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->reports->catalog($request->user()),
        ]);
    }

    public function runs(Request $request)
    {
        $runs = $this->reports->runs($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'runs' => ArmisReportRunResource::collection($runs),
                'meta' => ['total' => $runs->count()],
            ],
        ]);
    }

    public function generate(Request $request, string $report)
    {
        $run = $this->reports->generate($request, $report, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'ARMIS report generated from an immutable snapshot.',
            'data' => ['run' => new ArmisReportRunResource($run)],
        ], 201);
    }

    public function show(Request $request, int $run)
    {
        return response()->json([
            'success' => true,
            'data' => ['run' => new ArmisReportRunResource($this->reports->show($request->user(), $run))],
        ]);
    }

    public function export(Request $request, int $run)
    {
        $validated = $request->validate(['format' => ['required', 'in:csv,pdf']]);
        $export = $this->reports->export($request, $run, $validated['format']);

        return response()->json([
            'success' => true,
            'message' => 'Protected ARMIS report export generated.',
            'data' => ['export' => new ArmisReportExportResource($export->load('run'))],
        ], 201);
    }

    public function download(Request $request, int $export)
    {
        return $this->reports->download($request, $export);
    }

    public function administration(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->reports->administration($request->user()),
        ]);
    }
}
