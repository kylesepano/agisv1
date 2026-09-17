<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Models\AuditEngagement;
use App\Services\AemsCompletionTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AemsCompletionTransferController extends Controller
{
    public function __construct(private readonly AemsCompletionTransferService $transfers) {}

    public function show(Request $request, AuditEngagement $engagement): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->transfers->workspace($request, $engagement)]);
    }

    public function reconcile(Request $request, AuditEngagement $engagement): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'CMS transfer and resource-effort reconciliation completed.',
            'data' => $this->transfers->reconcile($request, $engagement),
        ]);
    }

    public function approve(Request $request, AuditEngagement $engagement, string $type, int $id): JsonResponse
    {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['required', 'string', 'min:10', 'max:10000'],
        ]);
        abort_unless(in_array(strtoupper($type), ['MANIFEST', 'EFFORT'], true), 422, 'Unsupported reconciliation type.');

        return response()->json([
            'success' => true,
            'message' => 'Completion reconciliation approved.',
            'data' => $this->transfers->approve($request, $engagement, strtoupper($type), $id, $validated['lockVersion'], $validated['comment']),
        ]);
    }
}
