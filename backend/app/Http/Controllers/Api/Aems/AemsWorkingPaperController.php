<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Http\Requests\Aems\AemsWorkingPaperRequest;
use App\Models\AuditEngagement;
use App\Models\WorkingPaper;
use App\Services\AemsAccessService;
use App\Services\AemsWorkingPaperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Exposes the scoped, immutable Working Paper workspace and workflow. */
class AemsWorkingPaperController extends Controller
{
    public function __construct(
        private readonly AemsWorkingPaperService $papers,
        private readonly AemsAccessService $access,
    ) {}

    public function index(Request $request, AuditEngagement $engagement): JsonResponse
    {
        Gate::authorize('view', $engagement);
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.working-paper.view',
        );

        return response()->json([
            'success' => true,
            'data' => $this->papers->workspace($request, $engagement),
        ]);
    }

    public function store(
        AemsWorkingPaperRequest $request,
        AuditEngagement $engagement,
    ): JsonResponse {
        Gate::authorize('create', [WorkingPaper::class, $engagement]);
        $paper = $this->papers->create($request, $engagement, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Draft Working Paper created.',
            'data' => ['workingPaper' => $this->papers->data($paper)],
        ], 201);
    }

    public function update(
        AemsWorkingPaperRequest $request,
        AuditEngagement $engagement,
        WorkingPaper $paper,
    ): JsonResponse {
        Gate::authorize('prepare', $paper);
        $paper = $this->papers->update(
            $request,
            $engagement,
            $paper,
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'A new immutable Working Paper content version was saved.',
            'data' => ['workingPaper' => $this->papers->data($paper)],
        ]);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        WorkingPaper $paper,
    ): JsonResponse {
        $validated = $request->validate([
            'action' => [
                'required',
                Rule::in(['SUBMIT', 'RETURN', 'RESUBMIT', 'APPROVE', 'VOID']),
            ],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:4000'],
        ]);
        match ($validated['action']) {
            'SUBMIT', 'RESUBMIT' => Gate::authorize('prepare', $paper),
            'RETURN' => Gate::authorize('review', $paper),
            'APPROVE' => Gate::authorize('approve', $paper),
            'VOID' => Gate::authorize('void', $paper),
        };
        $paper = $this->papers->transition(
            $request,
            $engagement,
            $paper,
            $validated['action'],
            $validated['lockVersion'],
            $validated['comment'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Working Paper workflow action completed.',
            'data' => ['workingPaper' => $this->papers->data($paper)],
        ]);
    }

    public function revise(
        Request $request,
        AuditEngagement $engagement,
        WorkingPaper $paper,
    ): JsonResponse {
        Gate::authorize('prepare', $paper);
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:4000'],
        ]);
        $paper = $this->papers->revise(
            $request,
            $engagement,
            $paper,
            $validated['lockVersion'],
            $validated['reason'],
        );

        return response()->json([
            'success' => true,
            'message' => 'A formal Working Paper correction revision was started.',
            'data' => ['workingPaper' => $this->papers->data($paper)],
        ]);
    }
}
