<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Models\AuditEngagement;
use App\Models\EngagementReopenRequest;
use App\Services\AemsReopenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AemsReopenController extends Controller
{
    public function __construct(private readonly AemsReopenService $reopening) {}

    public function index(Request $request, AuditEngagement $engagement): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['requests' => $this->reopening->index($request, $engagement)],
        ]);
    }

    public function store(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $validated = $request->validate([
            'reasonCode' => ['required', Rule::in([
                'AUTHORIZED_CORRECTION',
                'SIGNIFICANT_ERROR',
                'COURT_DIRECTION',
                'OVERSIGHT_DIRECTION',
                'OTHER_APPROVED_AUTHORITY',
            ])],
            'reasonText' => ['required', 'string', 'max:20000'],
            'authorityDocumentVersionId' => ['required', 'exists:document_versions,id'],
        ]);
        $reopen = $this->reopening->create($request, $engagement, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Exceptional reopening request created.',
            'data' => ['request' => $reopen],
        ], 201);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        EngagementReopenRequest $reopen,
        string $action,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:10000'],
        ]);
        $reopen = $this->reopening->transition(
            $request,
            $engagement,
            $reopen,
            $action,
            $validated['lockVersion'],
            $validated['comment'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Exceptional reopening workflow action completed.',
            'data' => ['request' => $reopen],
        ]);
    }
}
