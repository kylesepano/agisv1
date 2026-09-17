<?php

namespace App\Services;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Models\AuditEngagement;
use App\Models\AemsTeamAccessHistory;
use App\Models\AemsTeamAmendment;
use App\Models\EngagementTeam;
use App\Models\EngagementTeamHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Maintains the current engagement team while preserving append-only
 * assignment and reassignment history. Interim IAP capacity, skill, and
 * unavailability records provide warnings until ARMIS replaces them.
 */
class AemsTeamService
{
    public const ROLES = [
        'SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER',
        'SPECIALIST', 'AUTHORIZED_PARTICIPANT',
    ];

    public function __construct(
        private readonly AemsSupport $support,
        private readonly ResourcePlanningGateway $resources,
        private readonly AemsNotificationService $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function overview(AuditEngagement $engagement): array
    {
        $engagement->loadMissing([
            'teamMembers' => fn ($query) => $query
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->with(['user.roles', 'user.role']),
            'teamHistory.actor',
            'teamHistory.teamMember.user',
            'teamAmendments.actor',
            'teamAmendments.teamMember.user',
            'teamAccessHistory.actor',
            'teamAccessHistory.user',
            'offices:id,code,name',
            'auditAreas:id,code,name',
        ]);

        $team = $engagement->teamMembers->values();

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
                'plannedStartDate' => $engagement->planned_start_date?->toDateString(),
                'plannedEndDate' => $engagement->planned_end_date?->toDateString(),
                'plannedPersonDays' => (float) $engagement->planned_person_days,
                'scopeReady' => $engagement->offices->count() === 1 && $engagement->auditAreas->isNotEmpty(),
            ],
            'roles' => collect(self::ROLES)->map(fn (string $code): array => [
                'code' => $code,
                'label' => str($code)->replace('_', ' ')->title()->toString(),
            ])->values(),
            'teamMembers' => $team->map(fn (EngagementTeam $member): array => $this->member($member)),
            'history' => $engagement->teamHistory
                ->sortByDesc('created_at')
                ->map(fn (EngagementTeamHistory $history): array => [
                    'id' => $history->id,
                    'action' => $history->action,
                    'reason' => $history->reason,
                    'oldValues' => $history->old_values,
                    'newValues' => $history->new_values,
                    'createdAt' => $history->created_at?->toISOString(),
                    'actor' => $this->user($history->actor),
                ])->values(),
            'amendments' => $engagement->teamAmendments
                ->map(fn (AemsTeamAmendment $amendment): array => [
                    'id' => $amendment->id,
                    'action' => $amendment->action,
                    'authorityCode' => $amendment->authority_code,
                    'reason' => $amendment->reason,
                    'consequenceAssessment' => $amendment->consequence_assessment,
                    'oldValues' => $amendment->old_values,
                    'newValues' => $amendment->new_values,
                    'createdAt' => $amendment->created_at?->toISOString(),
                    'actor' => $this->user($amendment->actor),
                ])->values(),
            'accessHistory' => $engagement->teamAccessHistory
                ->map(fn (AemsTeamAccessHistory $entry): array => [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'assignmentRoleCode' => $entry->assignment_role_code,
                    'accessFrom' => $entry->access_from?->toDateString(),
                    'accessUntil' => $entry->access_until?->toDateString(),
                    'reason' => $entry->reason,
                    'snapshot' => $entry->snapshot,
                    'createdAt' => $entry->created_at?->toISOString(),
                    'user' => $this->user($entry->user),
                    'actor' => $this->user($entry->actor),
                ])->values(),
            'candidates' => $this->candidates($engagement, $team),
            'warnings' => $this->warnings($engagement, $team),
            'summary' => [
                'members' => $team->count(),
                'plannedPersonDays' => round((float) $team->sum('planned_person_days'), 2),
                'requiredPersonDays' => (float) $engagement->planned_person_days,
                'rolesFilled' => $team->pluck('assignment_role_code')->unique()->count(),
            ],
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function assign(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): EngagementTeam {
        return DB::transaction(function () use ($request, $engagement, $attributes): EngagementTeam {
            $locked = AuditEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            $this->ensureMutable($locked);
            $this->validateAssignment($locked, $attributes);

            $member = EngagementTeam::query()->create([
                ...$this->assignmentAttributes($locked, $attributes),
                'audit_engagement_id' => $locked->id,
                'assigned_by' => $request->user()->id,
                'is_active' => true,
            ]);
            $snapshot = $this->memberSnapshot($member->load('user'));
            $this->history($locked, $member, 'ASSIGNED', $request->user()->id, null, null, $snapshot);
            $this->recordAmendment($locked, $member, 'ASSIGNED', $request, null, $snapshot, $attributes);
            $this->recordAccess($locked, $member, 'GRANTED', $request->user()->id, null, $snapshot);
            $this->record($request, $locked, 'aems.team.assigned', null, $snapshot);
            $this->notifications->teamAssignment(
                $request,
                $locked,
                $member,
                'assigned',
            );

            return $member->load(['user.roles', 'user.role']);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(
        Request $request,
        AuditEngagement $engagement,
        EngagementTeam $member,
        array $attributes,
    ): EngagementTeam {
        return DB::transaction(function () use ($request, $engagement, $member, $attributes): EngagementTeam {
            $locked = AuditEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            $current = EngagementTeam::query()->lockForUpdate()->findOrFail($member->id);
            $this->ensureMember($locked, $current);
            $this->ensureMutable($locked);
            $payload = ['userId' => $current->user_id, ...$attributes];
            $this->validateAssignment($locked, $payload, $current->id);
            $before = $this->memberSnapshot($current->load('user'));

            $current->update($this->assignmentAttributes($locked, $payload));
            $after = $this->memberSnapshot($current->fresh('user'));
            $this->history($locked, $current, 'UPDATED', $request->user()->id, $attributes['reason'] ?? null, $before, $after);
            $this->recordAmendment($locked, $current, 'UPDATED', $request, $before, $after, $attributes);
            $this->recordAccess($locked, $current, 'UPDATED', $request->user()->id, $attributes['reason'] ?? null, $after);
            $this->record($request, $locked, 'aems.team.updated', $before, $after);

            return $current->fresh(['user.roles', 'user.role']);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function reassign(
        Request $request,
        AuditEngagement $engagement,
        EngagementTeam $member,
        array $attributes,
    ): EngagementTeam {
        return DB::transaction(function () use ($request, $engagement, $member, $attributes): EngagementTeam {
            $locked = AuditEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            $current = EngagementTeam::query()->lockForUpdate()->findOrFail($member->id);
            $this->ensureMember($locked, $current);
            $this->ensureMutable($locked);
            $replacement = [
                'userId' => $attributes['replacementUserId'],
                'assignmentRoleCode' => $attributes['assignmentRoleCode'] ?? $current->assignment_role_code,
                'plannedPersonDays' => $attributes['plannedPersonDays'] ?? $current->planned_person_days,
                'assignedFrom' => $attributes['assignedFrom'] ?? now()->toDateString(),
                'assignedUntil' => $attributes['assignedUntil'] ?? $current->assigned_until?->toDateString(),
                'assignmentNotes' => $attributes['assignmentNotes'] ?? $current->assignment_notes,
            ];
            $this->validateAssignment($locked, $replacement, $current->id);
            $before = $this->memberSnapshot($current->load('user'));

            $current->update([
                'ended_at' => now(),
                'ended_by' => $request->user()->id,
                'end_reason' => $attributes['reason'],
                'is_active' => false,
            ]);
            $current->delete();

            $newMember = EngagementTeam::query()->create([
                ...$this->assignmentAttributes($locked, $replacement),
                'audit_engagement_id' => $locked->id,
                'assigned_by' => $request->user()->id,
                'assignment_notes' => $replacement['assignmentNotes'],
                'is_active' => true,
            ]);
            $after = $this->memberSnapshot($newMember->load('user'));
            $this->history($locked, $current, 'REASSIGNED_FROM', $request->user()->id, $attributes['reason'], $before, $after);
            $this->history($locked, $newMember, 'REASSIGNED_TO', $request->user()->id, $attributes['reason'], $before, $after);
            $this->recordAmendment($locked, $newMember, 'REASSIGNED', $request, $before, $after, $attributes);
            $this->recordAccess($locked, $current, 'REVOKED', $request->user()->id, $attributes['reason'], $before);
            $this->recordAccess($locked, $newMember, 'GRANTED', $request->user()->id, $attributes['reason'], $after);
            $this->record($request, $locked, 'aems.team.reassigned', $before, $after);
            $this->notifications->teamAssignment(
                $request,
                $locked,
                $newMember,
                'reassigned',
            );

            return $newMember->load(['user.roles', 'user.role']);
        });
    }

    public function end(
        Request $request,
        AuditEngagement $engagement,
        EngagementTeam $member,
        string $reason,
    ): void {
        DB::transaction(function () use ($request, $engagement, $member, $reason): void {
            $locked = AuditEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            $current = EngagementTeam::query()->lockForUpdate()->findOrFail($member->id);
            $this->ensureMember($locked, $current);
            $this->ensureMutable($locked);
            $before = $this->memberSnapshot($current->load('user'));
            $current->update([
                'ended_at' => now(),
                'ended_by' => $request->user()->id,
                'end_reason' => $reason,
                'is_active' => false,
            ]);
            $this->history($locked, $current, 'ENDED', $request->user()->id, $reason, $before, null);
            $this->recordAmendment($locked, $current, 'ENDED', $request, $before, null, ['reason' => $reason]);
            $this->recordAccess($locked, $current, 'REVOKED', $request->user()->id, $reason, $before);
            $this->record($request, $locked, 'aems.team.assignment_ended', $before, null);
            $current->delete();
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function candidates(AuditEngagement $engagement, Collection $team): Collection
    {
        $assignedIds = $team->pluck('user_id');
        $year = $engagement->planned_start_date?->year ?? now()->year;

        $users = User::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'aems.team.view'));
            })
            ->with('office:id,code,name')
            ->orderBy('name')
            ->get();
        $skills = $this->resources->skills($users->pluck('id')->all());

        return $users
            ->map(function (User $user) use ($assignedIds, $year, $skills): array {
                $available = $this->resources->capacityFor($year, $user->id);
                $allocated = $this->allocatedPersonDays($year, $user->id);

                return [
                    ...$this->user($user),
                    'office' => $user->office?->only(['id', 'code', 'name']),
                    'alreadyAssigned' => $assignedIds->contains($user->id),
                    'availablePersonDays' => $available,
                    'allocatedPersonDays' => $allocated,
                    'remainingPersonDays' => round($available - $allocated, 2),
                    'skills' => $skills[$user->id] ?? [],
                ];
            })->values();
    }

    /** @return list<array<string, mixed>> */
    private function warnings(AuditEngagement $engagement, Collection $team): array
    {
        $warnings = [];
        foreach (['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'] as $requiredRole) {
            if (! $team->contains('assignment_role_code', $requiredRole)) {
                $warnings[] = [
                    'type' => 'MISSING_ROLE',
                    'severity' => 'warning',
                    'message' => str($requiredRole)->replace('_', ' ')->title().' has not been assigned.',
                ];
            }
        }

        $assignedDays = round((float) $team->sum('planned_person_days'), 2);
        $requiredDays = (float) $engagement->planned_person_days;
        if ($requiredDays > 0 && abs($assignedDays - $requiredDays) > 0.009) {
            $warnings[] = [
                'type' => 'PERSON_DAY_MISMATCH',
                'severity' => 'warning',
                'message' => sprintf(
                    'The team has %.2f assigned person-days against %.2f required.',
                    $assignedDays,
                    $requiredDays,
                ),
            ];
        }

        foreach ($team as $member) {
            array_push($warnings, ...$this->memberWarnings($engagement, $member));
        }
        array_push($warnings, ...$this->skillWarnings($engagement, $team));

        return $warnings;
    }

    /** @return list<array<string, mixed>> */
    private function memberWarnings(AuditEngagement $engagement, EngagementTeam $member): array
    {
        $warnings = [];
        $start = $member->assigned_from ?? $engagement->planned_start_date;
        $end = $member->assigned_until ?? $engagement->planned_end_date;
        if ($start && $end) {
            $unavailable = $this->resources->unavailability(
                $member->user_id,
                $start,
                $end,
            );
            foreach ($unavailable as $period) {
                $warnings[] = [
                    'type' => 'AUDITOR_UNAVAILABLE',
                    'severity' => 'danger',
                    'userId' => $member->user_id,
                    'message' => sprintf(
                        '%s is unavailable for %s from %s to %s.',
                        $member->user?->name ?? "User #{$member->user_id}",
                        $period['typeLabel'] ?? $period['title'],
                        date('M j, Y', strtotime($period['startDate'])),
                        date('M j, Y', strtotime($period['endDate'])),
                    ),
                ];
            }

            $overlap = EngagementTeam::query()
                ->where('user_id', $member->user_id)
                ->where('audit_engagement_id', '<>', $engagement->id)
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->whereDate('assigned_from', '<=', $end->toDateString())
                ->whereDate('assigned_until', '>=', $start->toDateString())
                ->whereHas('engagement', fn ($query) => $query->activeForResourceConflicts())
                ->with('engagement:id,engagement_code,title')
                ->first();
            if ($overlap) {
                $warnings[] = [
                    'type' => 'ENGAGEMENT_OVERLAP',
                    'severity' => 'danger',
                    'userId' => $member->user_id,
                    'message' => sprintf(
                        '%s is also assigned to %s during these dates.',
                        $member->user?->name ?? "User #{$member->user_id}",
                        $overlap->engagement?->engagement_code ?? 'another engagement',
                    ),
                ];
            }
        }

        $year = $start?->year ?? now()->year;
        $available = $this->resources->capacityFor($year, $member->user_id);
        $allocated = $this->allocatedPersonDays($year, $member->user_id);
        if ($allocated > $available) {
            $warnings[] = [
                'type' => 'CAPACITY_EXCEEDED',
                'severity' => 'warning',
                'userId' => $member->user_id,
                'message' => sprintf(
                    '%s has %.2f allocated person-days against %.2f available.',
                    $member->user?->name ?? "User #{$member->user_id}",
                    $allocated,
                    $available,
                ),
            ];
        }

        return $warnings;
    }

    /** @return list<array<string, mixed>> */
    private function skillWarnings(AuditEngagement $engagement, Collection $team): array
    {
        $requirements = collect($this->resources->requirements($engagement));
        if ($requirements->isEmpty()) {
            return [];
        }
        $ranks = ['BASIC' => 1, 'INTERMEDIATE' => 2, 'ADVANCED' => 3, 'EXPERT' => 4];
        $skills = collect($this->resources->skills(
            $team->pluck('user_id')->all(),
            $requirements->pluck('specializationId')->all(),
        ))->flatMap(fn (array $userSkills, int|string $userId) => collect($userSkills)
            ->map(fn (array $skill): array => [...$skill, 'userId' => (int) $userId]))
            ->groupBy('id');
        $warnings = [];
        foreach ($requirements as $requirement) {
            $qualified = $skills
                ->get($requirement['specializationId'], collect())
                ->filter(fn (array $skill): bool => ($ranks[$skill['proficiencyLevel']] ?? 0)
                    >= ($ranks[$requirement['minimumProficiency']] ?? 0))
                ->pluck('userId')->unique()->count();
            if ($qualified < $requirement['minimumAuditors']) {
                $warnings[] = [
                    'type' => 'SKILL_GAP',
                    'severity' => 'warning',
                    'message' => sprintf(
                        '%s requires %d auditor(s) at %s proficiency; %d currently qualify.',
                        $requirement['label'] ?? 'A required competency',
                        $requirement['minimumAuditors'],
                        str($requirement['minimumProficiency'])->lower()->title(),
                        $qualified,
                    ),
                ];
            }
        }

        return $warnings;
    }

    /** @param array<string, mixed> $attributes */
    private function validateAssignment(
        AuditEngagement $engagement,
        array $attributes,
        ?int $ignoreMemberId = null,
    ): void {
        $user = User::query()->find($attributes['userId']);
        if (! $user
            || ! $user->is_active
            || $user->trashed()
            || ! $user->hasPermission('aems.team.view')) {
            throw ValidationException::withMessages(['userId' => ['Select an active CIAS team member.']]);
        }
        $duplicate = EngagementTeam::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->when($ignoreMemberId, fn ($query) => $query->whereKeyNot($ignoreMemberId))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['userId' => ['This user is already assigned to the engagement.']]);
        }
        if (in_array($attributes['assignmentRoleCode'], ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'], true)) {
            $occupied = EngagementTeam::query()
                ->where('audit_engagement_id', $engagement->id)
                ->where('assignment_role_code', $attributes['assignmentRoleCode'])
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->when($ignoreMemberId, fn ($query) => $query->whereKeyNot($ignoreMemberId))
                ->exists();
            if ($occupied) {
                throw ValidationException::withMessages([
                    'assignmentRoleCode' => ['Only one active '.str($attributes['assignmentRoleCode'])->replace('_', ' ')->lower().' is allowed.'],
                ]);
            }
        }
    }

    private function ensureMutable(AuditEngagement $engagement): void
    {
        if ($engagement->trashed() || in_array($engagement->status, ['CLOSED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['engagement' => ['The team cannot be changed for this engagement.']]);
        }
        if ($engagement->status === 'DRAFT') {
            throw ValidationException::withMessages([
                'engagement' => [
                    'Move the engagement from Draft to Authorization Preparation before assigning or amending the Audit Team.',
                ],
            ]);
        }
        if ($engagement->offices()->count() !== 1 || ! $engagement->auditAreas()->exists()) {
            throw ValidationException::withMessages([
                'engagement' => ['Complete and save the Engagement Scope (one office and at least one audit area) before assigning the Audit Team.'],
            ]);
        }
    }

    private function ensureMember(AuditEngagement $engagement, EngagementTeam $member): void
    {
        if ((int) $member->audit_engagement_id !== (int) $engagement->id || ! $member->is_active || $member->ended_at) {
            throw ValidationException::withMessages(['teamMember' => ['The active team assignment was not found.']]);
        }
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function assignmentAttributes(AuditEngagement $engagement, array $attributes): array
    {
        return [
            'user_id' => $attributes['userId'],
            'assignment_role_code' => $attributes['assignmentRoleCode'],
            'planned_person_days' => $attributes['plannedPersonDays'],
            'assigned_from' => $attributes['assignedFrom'] ?? $engagement->planned_start_date?->toDateString(),
            'assigned_until' => $attributes['assignedUntil'] ?? $engagement->planned_end_date?->toDateString(),
            'assignment_notes' => $attributes['assignmentNotes'] ?? null,
        ];
    }

    private function allocatedPersonDays(int $year, int $userId): float
    {
        return round((float) EngagementTeam::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->whereHas('engagement', fn ($query) => $query
                ->activeForResourceConflicts()
                ->whereYear('planned_start_date', $year)
            )
            ->sum('planned_person_days'), 2);
    }

    /** @return array<string, mixed> */
    private function member(EngagementTeam $member): array
    {
        $year = $member->assigned_from?->year ?? $member->engagement?->planned_start_date?->year ?? now()->year;

        return [
            ...$this->memberSnapshot($member),
            'actualPersonDays' => $this->resources->assignmentActualPersonDays($member),
            'availablePersonDays' => $this->resources->capacityFor($year, $member->user_id),
            'allocatedPersonDays' => $this->allocatedPersonDays($year, $member->user_id),
            'skills' => $this->resources->skills([$member->user_id])[$member->user_id] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function memberSnapshot(EngagementTeam $member): array
    {
        return [
            'id' => $member->id,
            'userId' => $member->user_id,
            'user' => $this->user($member->user),
            'assignmentRoleCode' => $member->assignment_role_code,
            'plannedPersonDays' => (float) $member->planned_person_days,
            'assignedFrom' => $member->assigned_from?->toDateString(),
            'assignedUntil' => $member->assigned_until?->toDateString(),
            'assignmentNotes' => $member->assignment_notes,
            'isActive' => (bool) $member->is_active,
        ];
    }

    /** @return array<string, mixed>|null */
    private function user(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'name' => $user->name,
            'initials' => $user->initials,
        ] : null;
    }

    /** @param array<string, mixed>|null $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function history(
        AuditEngagement $engagement,
        EngagementTeam $member,
        string $action,
        int $actorId,
        ?string $reason,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        EngagementTeamHistory::query()->create([
            'audit_engagement_id' => $engagement->id,
            'engagement_team_id' => $member->id,
            'action' => $action,
            'actor_id' => $actorId,
            'reason' => $reason,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    /** @param array<string, mixed>|null $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function record(
        Request $request,
        AuditEngagement $engagement,
        string $action,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        $this->support->event(
            $request,
            $engagement,
            str($action)->afterLast('.')->upper()->toString(),
            $engagement->status,
            $engagement->status,
            $oldValues,
            $newValues,
            subjectType: 'TEAM',
        );
        $this->support->audit($request, $action, $engagement, $oldValues, $newValues);
    }

    /** @param array<string, mixed> $attributes */
    private function recordAmendment(
        AuditEngagement $engagement,
        EngagementTeam $member,
        string $action,
        Request $request,
        ?array $oldValues,
        ?array $newValues,
        array $attributes,
    ): void {
        AemsTeamAmendment::query()->create([
            'audit_engagement_id' => $engagement->id,
            'engagement_team_id' => $member->id,
            'action' => $action,
            'authority_code' => $attributes['amendmentAuthority'] ?? 'AEMS_TEAM_ASSIGNMENT_AUTHORITY',
            'reason' => $attributes['reason'] ?? 'Authorized engagement team amendment.',
            'consequence_assessment' => $attributes['consequenceAssessment']
                ?? 'Assignment impact assessed; no unresolved independence or capacity consequence identified.',
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'actor_id' => $request->user()->id,
        ]);
    }

    private function recordAccess(
        AuditEngagement $engagement,
        EngagementTeam $member,
        string $action,
        int $actorId,
        ?string $reason,
        ?array $snapshot,
    ): void {
        AemsTeamAccessHistory::query()->create([
            'audit_engagement_id' => $engagement->id,
            'engagement_team_id' => $member->id,
            'user_id' => $member->user_id,
            'action' => $action,
            'assignment_role_code' => $member->assignment_role_code,
            'access_from' => $member->assigned_from,
            'access_until' => $member->assigned_until,
            'actor_id' => $actorId,
            'reason' => $reason,
            'snapshot' => $snapshot,
        ]);
    }
}
