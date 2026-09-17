<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Models\AuditEngagement;
use App\Models\EngagementTeam;
use App\Services\AemsAccessService;
use App\Services\AemsTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Exposes current Audit Team assignments, resource warnings, and immutable
 * reassignment history for one authorized engagement.
 */
class AemsTeamController extends Controller
{
    public function __construct(
        private readonly AemsTeamService $teams,
        private readonly AemsAccessService $access,
    ) {}

    public function show(Request $request, AuditEngagement $engagement): JsonResponse
    {
        Gate::authorize('view', $engagement);
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.team.view',
        );

        return response()->json([
            'success' => true,
            'data' => $this->teams->overview($engagement),
        ]);
    }

    public function store(Request $request, AuditEngagement $engagement): JsonResponse
    {
        Gate::authorize('assignTeam', $engagement);
        $member = $this->teams->assign(
            $request,
            $engagement,
            $this->assignment($request),
        );

        return response()->json([
            'success' => true,
            'message' => 'Audit team member assigned.',
            'data' => ['teamMember' => $member],
        ], 201);
    }

    public function update(
        Request $request,
        AuditEngagement $engagement,
        EngagementTeam $teamMember,
    ): JsonResponse {
        Gate::authorize('assignTeam', $engagement);
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.team.amend');
        $member = $this->teams->update(
            $request,
            $engagement,
            $teamMember,
            $this->assignment($request, false),
        );

        return response()->json([
            'success' => true,
            'message' => 'Audit team assignment updated.',
            'data' => ['teamMember' => $member],
        ]);
    }

    public function reassign(
        Request $request,
        AuditEngagement $engagement,
        EngagementTeam $teamMember,
    ): JsonResponse {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.team.amend');
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.team.reassign',
        );
        $validated = $request->validate([
            'replacementUserId' => ['required', 'integer', 'exists:users,id'],
            'assignmentRoleCode' => ['nullable', Rule::in(AemsTeamService::ROLES)],
            'plannedPersonDays' => ['nullable', 'numeric', 'min:0.25', 'max:9999'],
            'assignedFrom' => ['nullable', 'date'],
            'assignedUntil' => ['nullable', 'date', 'after_or_equal:assignedFrom'],
            'assignmentNotes' => ['nullable', 'string', 'max:4000'],
            'reason' => ['required', 'string', 'min:5', 'max:4000'],
            'amendmentAuthority' => ['nullable', 'string', 'max:80'],
            'consequenceAssessment' => ['nullable', 'string', 'max:4000'],
        ]);
        $member = $this->teams->reassign(
            $request,
            $engagement,
            $teamMember,
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Audit team member reassigned.',
            'data' => ['teamMember' => $member],
        ]);
    }

    public function destroy(
        Request $request,
        AuditEngagement $engagement,
        EngagementTeam $teamMember,
    ): JsonResponse {
        Gate::authorize('assignTeam', $engagement);
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.team.amend');
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:4000'],
        ]);
        $this->teams->end($request, $engagement, $teamMember, $validated['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Audit team assignment ended.',
        ]);
    }

    /** @return array<string, mixed> */
    private function assignment(Request $request, bool $requireUser = true): array
    {
        return $request->validate([
            'userId' => [$requireUser ? 'required' : 'sometimes', 'integer', 'exists:users,id'],
            'assignmentRoleCode' => ['required', Rule::in(AemsTeamService::ROLES)],
            'plannedPersonDays' => ['required', 'numeric', 'min:0.25', 'max:9999'],
            'assignedFrom' => ['nullable', 'date'],
            'assignedUntil' => ['nullable', 'date', 'after_or_equal:assignedFrom'],
            'assignmentNotes' => ['nullable', 'string', 'max:4000'],
            'reason' => ['nullable', 'string', 'max:4000'],
            'amendmentAuthority' => ['nullable', 'string', 'max:80'],
            'consequenceAssessment' => ['nullable', 'string', 'max:4000'],
        ]);
    }
}
