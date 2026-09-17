<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Http\Requests\Aems\AemsEvidenceRequest;
use App\Models\AuditEngagement;
use App\Models\AuditEvidence;
use App\Services\AemsEvidenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exposes protected immutable evidence uploads, transitions, and downloads. */
class AemsEvidenceController extends Controller
{
    public function __construct(private readonly AemsEvidenceService $evidence) {}

    public function store(
        AemsEvidenceRequest $request,
        AuditEngagement $engagement,
    ): JsonResponse {
        Gate::authorize('upload', [AuditEvidence::class, $engagement]);
        $record = $this->evidence->create(
            $request,
            $engagement,
            $request->validated(),
            $request->file('file'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Evidence uploaded as an immutable draft version.',
            'data' => ['evidence' => $this->evidence->data($record)],
        ], 201);
    }

    public function replace(
        AemsEvidenceRequest $request,
        AuditEngagement $engagement,
        AuditEvidence $evidence,
    ): JsonResponse {
        Gate::authorize('upload', [AuditEvidence::class, $engagement]);
        Gate::authorize('view', $evidence);
        $record = $this->evidence->replace(
            $request,
            $engagement,
            $evidence,
            $request->validated(),
            $request->file('file'),
        );

        return response()->json([
            'success' => true,
            'message' => 'A new immutable evidence version was uploaded.',
            'data' => ['evidence' => $this->evidence->data($record)],
        ], 201);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AuditEvidence $evidence,
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['VERIFY', 'ACCEPT', 'LIMIT', 'ADDITIONAL_REQUIRED', 'REJECT', 'DUPLICATE', 'VOID'])],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:4000'],
        ]);
        Gate::authorize(in_array($validated['action'], ['VERIFY', 'VOID'], true) ? strtolower($validated['action']) : 'outcome', $evidence);
        $record = $this->evidence->transition(
            $request,
            $engagement,
            $evidence,
            $validated['action'],
            $validated['lockVersion'],
            $validated['reason'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Evidence status updated.',
            'data' => ['evidence' => $this->evidence->data($record)],
        ]);
    }

    public function download(
        Request $request,
        AuditEngagement $engagement,
        AuditEvidence $evidence,
    ): StreamedResponse {
        Gate::authorize('view', $evidence);
        $version = $this->evidence->downloadVersion($request, $engagement, $evidence);

        return Storage::disk('local')->download(
            $version->storage_path,
            $version->original_file_name,
            ['Content-Type' => $version->mime_type],
        );
    }

    public function linkReport(Request $request, AuditEngagement $engagement, AuditEvidence $evidence): JsonResponse
    {
        $validated = $request->validate([
            'reportVersionId' => ['required', 'integer', Rule::exists('audit_report_versions', 'id')],
            'reason' => ['nullable', 'string', 'max:4000'],
            'sequence' => ['nullable', 'integer', 'min:0'],
        ]);
        $record = $this->evidence->linkReport($request, $engagement, $evidence, (int) $validated['reportVersionId'], $validated['reason'] ?? null, (int) ($validated['sequence'] ?? 0));
        return response()->json(['success' => true, 'message' => 'Evidence linked to the report version.', 'data' => ['evidence' => $this->evidence->data($record)]]);
    }
}
