<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsAemsEvidenceRequestResponseRequest;
use App\Models\AemsEvidenceRequest;
use App\Services\AemsEvidenceRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CMS recipient portal for receiving and submitting responses to AEMS requests. */
class CmsAemsEvidenceRequestController extends Controller
{
    public function __construct(private readonly AemsEvidenceRequestService $requests) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->requests->cmsWorkspace($request),
        ]);
    }

    public function acknowledge(Request $request, AemsEvidenceRequest $evidenceRequest): JsonResponse
    {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);
        $record = $evidenceRequest->load('engagement');
        $saved = $this->requests->transition(
            $request,
            $record->engagement,
            $record,
            'ACKNOWLEDGE',
            (int) $validated['lockVersion'],
            $validated['comment'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Evidence Request acknowledged.',
            'data' => ['evidenceRequest' => $this->requests->requestData($saved)],
        ]);
    }

    public function respond(
        CmsAemsEvidenceRequestResponseRequest $request,
        AemsEvidenceRequest $evidenceRequest,
    ): JsonResponse {
        $response = $this->requests->respondWithEvidence(
            $request,
            $evidenceRequest,
            $request->validated(),
            $request->file('file'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Evidence submitted to the auditor for receipt and assessment.',
            'data' => ['response' => [
                'id' => $response->id,
                'evidenceId' => $response->audit_evidence_id,
                'documentVersionId' => $response->document_version_id,
                'evidenceCode' => $response->evidence?->evidence_code,
                'fileName' => $response->documentVersion?->original_file_name,
                'submittedAt' => $response->submitted_at?->toIso8601String(),
            ]],
        ], 201);
    }
}
