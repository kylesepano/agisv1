<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Models\AemsTeamSafeguardDeclaration;
use App\Models\AuditEngagement;
use App\Models\EngagementTeam;
use App\Services\AemsAccessService;
use App\Services\AemsTeamSafeguardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Exposes the provider, declaration, and independence safeguards for a team. */
class AemsTeamSafeguardController extends Controller
{
    public function __construct(
        private readonly AemsTeamSafeguardService $safeguards,
        private readonly AemsAccessService $access,
    ) {}

    public function show(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.team.safeguard_view');

        return response()->json(['success' => true, 'data' => $this->safeguards->overview($engagement)]);
    }

    public function storeDeclaration(
        Request $request,
        AuditEngagement $engagement,
        EngagementTeam $teamMember,
    ): JsonResponse {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.team.safeguard_declare');
        $declaration = $this->safeguards->submitDeclaration(
            $request,
            $engagement,
            $teamMember,
            $this->declaration($request),
        );

        return response()->json([
            'success' => true,
            'message' => 'Safeguard declaration submitted for authorized review.',
            'data' => ['declaration' => $declaration],
        ], 201);
    }

    public function reviewDeclaration(
        Request $request,
        AuditEngagement $engagement,
        EngagementTeam $teamMember,
        AemsTeamSafeguardDeclaration $declaration,
    ): JsonResponse {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.team.safeguard_review',
            // A reviewer with the explicit self-review permission may review
            // the same submission; all other reviewers remain independent.
            $request->user()->hasPermission('aems.review.own_submission')
                ? null : $declaration->submitted_by,
        );
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['ACCEPT', 'RETURN'])],
            'reviewNotes' => ['nullable', 'string', 'max:4000'],
        ]);
        $updated = $this->safeguards->reviewDeclaration(
            $request,
            $engagement,
            $teamMember,
            $declaration,
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => $validated['decision'] === 'ACCEPT'
                ? 'Safeguard declaration accepted.'
                : 'Safeguard declaration returned for revision.',
            'data' => ['declaration' => $updated],
        ]);
    }

    public function assess(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.team.safeguard_review');
        $assessment = $this->safeguards->assess($request, $engagement);

        return response()->json([
            'success' => true,
            'message' => 'Team safeguard assessment recorded for independent decision.',
            'data' => ['assessment' => $assessment],
        ], 201);
    }

    public function approve(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.team.safeguard_approve');
        $validated = $request->validate(['comment' => ['nullable', 'string', 'max:4000']]);
        $assessment = $this->safeguards->approve($request, $engagement, $validated['comment'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Team safeguards approved. The assessment is immutable.',
            'data' => ['assessment' => $assessment],
        ]);
    }

    /** @return array<string, mixed> */
    private function declaration(Request $request): array
    {
        return $request->validate([
            'declarationType' => ['required', Rule::in(AemsTeamSafeguardDeclaration::TYPES)],
            'outcome' => ['required', Rule::in(AemsTeamSafeguardDeclaration::OUTCOMES)],
            'statement' => ['required', 'string', 'min:10', 'max:10000'],
            'mitigationPlan' => ['nullable', 'string', 'max:10000'],
            'evidenceDocumentVersionId' => ['nullable', 'integer', 'exists:document_versions,id'],
            'revisionReason' => ['nullable', 'string', 'min:5', 'max:4000'],
        ]);
    }
}
