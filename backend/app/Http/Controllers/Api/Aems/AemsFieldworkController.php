<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Http\Requests\Aems\AemsFieldworkRecordRequest;
use App\Models\AemsFieldworkRecord;
use App\Models\AuditEngagement;
use App\Services\AemsAccessService;
use App\Services\AemsFieldworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Exposes scoped, versioned Fieldwork Record execution and finalization. */
class AemsFieldworkController extends Controller
{
    public function __construct(
        private readonly AemsFieldworkService $fieldwork,
        private readonly AemsAccessService $access,
    ) {}

    public function index(Request $request, AuditEngagement $engagement): JsonResponse
    {
        Gate::authorize('view', $engagement);
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.fieldwork.view');

        return response()->json(['success' => true, 'data' => $this->fieldwork->workspace($request, $engagement)]);
    }

    public function store(AemsFieldworkRecordRequest $request, AuditEngagement $engagement): JsonResponse
    {
        $record = $this->fieldwork->create($request, $engagement, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Draft Fieldwork Record created.',
            'data' => ['fieldworkRecord' => $this->fieldwork->data($record)],
        ], 201);
    }

    public function update(
        AemsFieldworkRecordRequest $request,
        AuditEngagement $engagement,
        AemsFieldworkRecord $record,
    ): JsonResponse {
        $record = $this->fieldwork->update($request, $engagement, $record, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'A new immutable Fieldwork Record version was saved.',
            'data' => ['fieldworkRecord' => $this->fieldwork->data($record)],
        ]);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AemsFieldworkRecord $record,
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['SUBMIT', 'REVIEW', 'RETURN', 'RESUBMIT', 'FINALIZE', 'REVISE'])],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:4000'],
        ]);
        $record = $this->fieldwork->transition(
            $request,
            $engagement,
            $record,
            $validated['action'],
            (int) $validated['lockVersion'],
            $validated['comment'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Fieldwork Record workflow action completed.',
            'data' => ['fieldworkRecord' => $this->fieldwork->data($record)],
        ]);
    }
}
