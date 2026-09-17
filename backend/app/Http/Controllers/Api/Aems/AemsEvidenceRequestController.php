<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Http\Requests\Aems\AemsEvidenceAssessmentRequest;
use App\Http\Requests\Aems\AemsEvidenceRequestEvidenceRequest;
use App\Http\Requests\Aems\AemsEvidenceRequestRequest;
use App\Models\AemsEvidenceAssessment;
use App\Models\AemsEvidenceRequest;
use App\Models\AuditEngagement;
use App\Services\AemsEvidenceRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Exposes the separate Evidence Request and professional evidence assessment APIs. */
class AemsEvidenceRequestController extends Controller
{
    public function __construct(private readonly AemsEvidenceRequestService $requests) {}

    public function index(Request $request, AuditEngagement $engagement): JsonResponse
    {
        Gate::authorize('view', $engagement);
        return response()->json(['success' => true, 'data' => $this->requests->workspace($request, $engagement)]);
    }

    public function store(AemsEvidenceRequestRequest $request, AuditEngagement $engagement): JsonResponse
    {
        $record = $this->requests->create($request, $engagement, $request->validated());
        return response()->json(['success' => true, 'message' => 'Evidence Request draft created.', 'data' => ['evidenceRequest' => $this->requests->requestData($record)]], 201);
    }

    public function update(AemsEvidenceRequestRequest $request, AuditEngagement $engagement, AemsEvidenceRequest $evidenceRequest): JsonResponse
    {
        $record = $this->requests->update($request, $engagement, $evidenceRequest, $request->validated());
        return response()->json(['success' => true, 'message' => 'Evidence Request version saved.', 'data' => ['evidenceRequest' => $this->requests->requestData($record)]]);
    }

    public function transition(Request $request, AuditEngagement $engagement, AemsEvidenceRequest $evidenceRequest): JsonResponse
    {
        $validated = $request->validate(['action' => ['required', Rule::in(['SUBMIT', 'SEND', 'ACKNOWLEDGE', 'MARK_OVERDUE', 'REQUEST_EXTENSION', 'APPROVE_EXTENSION', 'REJECT_EXTENSION', 'ESCALATE', 'MARK_PARTIALLY_RECEIVED', 'MARK_RECEIVED', 'FOR_REVIEW', 'ASSESS', 'CLOSE_WITHOUT_SUBMISSION', 'CANCEL', 'CLOSE'])], 'lockVersion' => ['required', 'integer', 'min:1'], 'comment' => ['nullable', 'string', 'max:4000'], 'extensionDueDate' => ['nullable', 'date', 'after:today']]);
        $record = $this->requests->transition($request, $engagement, $evidenceRequest, $validated['action'], (int) $validated['lockVersion'], $validated['comment'] ?? null, $validated);
        return response()->json(['success' => true, 'message' => 'Evidence Request workflow action completed.', 'data' => ['evidenceRequest' => $this->requests->requestData($record)]]);
    }

    public function receiveEvidence(AemsEvidenceRequestEvidenceRequest $request, AuditEngagement $engagement, AemsEvidenceRequest $evidenceRequest): JsonResponse
    {
        $link = $this->requests->receiveEvidence($request, $engagement, $evidenceRequest, $request->validated());
        return response()->json(['success' => true, 'message' => 'Exact Evidence/Core Document Version linked to the request.', 'data' => ['evidence' => $link->toArray(), 'evidenceRequest' => $this->requests->requestData($evidenceRequest)]]);
    }

    public function assessEvidence(AemsEvidenceAssessmentRequest $request, AuditEngagement $engagement): JsonResponse
    {
        $assessment = $this->requests->assessEvidence($request, $engagement, $request->validated());
        return response()->json(['success' => true, 'message' => 'Evidence assessment recorded as an immutable version.', 'data' => ['assessment' => $this->requests->assessmentData($assessment)]], 201);
    }

    public function approveException(Request $request, AuditEngagement $engagement, AemsEvidenceAssessment $assessment): JsonResponse
    {
        $validated = $request->validate(['lockVersion' => ['required', 'integer', 'min:1'], 'comment' => ['required', 'string', 'min:5', 'max:4000']]);
        $updated = $this->requests->approveException($request, $engagement, $assessment, (int) $validated['lockVersion'], $validated['comment']);
        return response()->json(['success' => true, 'message' => 'Evidence exception approved.', 'data' => ['assessment' => $this->requests->assessmentData($updated)]]);
    }
}
