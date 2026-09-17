<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\CoreAdministrativeReportService;
use Illuminate\Http\Request;

class CoreAdministrativeReportController extends Controller
{
    public function __construct(private readonly CoreAdministrativeReportService $reports) {}

    public function catalog(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->reports->catalog($request->user())]);
    }

    public function runs(Request $request)
    {
        $runs = $this->reports->runs($request->user());
        return response()->json(['success' => true, 'data' => ['runs' => $runs, 'meta' => ['total' => $runs->count()]]]);
    }

    public function show(Request $request, int $run)
    {
        return response()->json(['success' => true, 'data' => ['run' => $this->reports->show($request->user(), $run)]]);
    }

    public function generate(Request $request, string $report)
    {
        $run = $this->reports->generate($request, $report, $request->all());
        return response()->json(['success' => true, 'message' => 'Core report generated from an immutable snapshot.', 'data' => ['run' => $run]], 201);
    }

    public function export(Request $request, int $run)
    {
        $validated = $request->validate(['format' => ['required', 'in:csv,pdf']]);
        $export = $this->reports->export($request, $run, $validated['format']);
        return response()->json(['success' => true, 'message' => 'Protected Core report export generated.', 'data' => ['export' => $export]], 201);
    }

    public function download(Request $request, int $export)
    {
        return $this->reports->download($request, $export);
    }
}
