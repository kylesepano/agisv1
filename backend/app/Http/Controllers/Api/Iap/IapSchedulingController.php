<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iap\IapScheduleCancelRequest;
use App\Http\Requests\Iap\IapScheduleRequest;
use App\Models\IapEngagementTeamMember;
use App\Models\IapPlanEngagement;
use App\Models\IapScheduleEvent;
use App\Models\InternalAuditPlan;
use App\Models\MasterListItem;
use App\Models\User;
use App\Services\IapPlanGuard;
use App\Services\IapScheduleConflictService;
use App\Services\IapSupport;
use App\Services\ArmisResourceBackfillService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Schedules plan engagements and preserves conflict, cancellation, and revision history.
 */
class IapSchedulingController extends Controller
{
    public function __construct(
        private readonly IapPlanGuard $guard,
        private readonly IapScheduleConflictService $conflicts,
        private readonly IapSupport $support,
        private readonly NotificationService $notifications,
        private readonly ArmisResourceBackfillService $resourceBackfill,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fiscalYear' => ['nullable', 'integer', 'min:2000', 'max:2200'],
            'planId' => ['nullable', 'integer', 'exists:internal_audit_plans,id'],
            'status' => ['nullable', 'in:UNSCHEDULED,SCHEDULED,CANCELLED'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $visiblePlans = InternalAuditPlan::query();
        $this->guard->scopeVisible($visiblePlans, $request->user());
        $planIds = $visiblePlans->pluck('id');
        $search = trim((string) ($validated['search'] ?? ''));

        $engagements = IapPlanEngagement::query()
            ->whereIn('plan_id', $planIds)
            ->when(isset($validated['planId']), fn ($query) => $query
                ->where('plan_id', $validated['planId']))
            ->when(isset($validated['fiscalYear']), fn ($query) => $query
                ->whereHas('plan', fn ($plan) => $plan
                    ->where('fiscal_year', $validated['fiscalYear'])))
            ->when(isset($validated['status']), fn ($query) => $query
                ->where('schedule_status', $validated['status']))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('engagement_code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('offices', fn ($office) => $office
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('teamMembers.user', fn ($user) => $user
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%"));
            }))
            ->with($this->relations())
            ->orderBy('planned_start_date')
            ->orderBy('engagement_code')
            ->get();

        $auditors = $this->auditors();
        $years = InternalAuditPlan::query()
            ->whereIn('id', $planIds)
            ->distinct()
            ->orderByDesc('fiscal_year')
            ->pluck('fiscal_year');
        $capacityRows = collect();
        foreach ($years as $year) {
            $capacityRows = $capacityRows->merge(
                $this->capacityRows((int) $year, $auditors),
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'schedules' => $engagements->map(fn ($engagement) => $this->payload($engagement)),
                'plans' => InternalAuditPlan::query()
                    ->whereIn('id', $planIds)
                    ->orderByDesc('fiscal_year')
                    ->get(['id', 'plan_code', 'title', 'fiscal_year', 'status'])
                    ->map(fn ($plan) => [
                        'id' => $plan->id,
                        'planCode' => $plan->plan_code,
                        'title' => $plan->title,
                        'fiscalYear' => $plan->fiscal_year,
                        'status' => $plan->status,
                    ]),
                'auditors' => $auditors->map(fn ($user) => [
                    'id' => $user->id,
                    'employeeId' => $user->employee_id,
                    'name' => $user->name,
                    'initials' => $user->initials,
                    'roleCode' => $user->role?->code,
                ]),
                'teamRoles' => $this->teamRoles()->map(fn ($item) => [
                    'id' => $item->id,
                    'code' => $item->code,
                    'label' => $item->label,
                ]),
                'capacities' => $capacityRows->values(),
            ],
        ]);
    }

    public function conflicts(
        IapScheduleRequest $request,
        IapPlanEngagement $engagement,
    ): JsonResponse {
        $plan = $engagement->plan;
        $this->assertSchedulable($request, $plan);
        $members = collect($request->validated('members'));
        $this->validateDomain($request, $engagement, $members, false);

        return response()->json([
            'success' => true,
            'data' => [
                'conflicts' => $this->conflicts->detect(
                    $engagement,
                    $request->date('plannedStartDate'),
                    $request->date('plannedEndDate'),
                    $members,
                ),
            ],
        ]);
    }

    public function update(
        IapScheduleRequest $request,
        IapPlanEngagement $engagement,
    ): JsonResponse {
        $plan = $engagement->plan;
        $this->assertSchedulable($request, $plan);
        $members = collect($request->validated('members'));
        $this->validateDomain($request, $engagement, $members, true);
        $detected = $this->conflicts->detect(
            $engagement,
            $request->date('plannedStartDate'),
            $request->date('plannedEndDate'),
            $members,
        );
        if ($detected !== [] && ! $request->boolean('acknowledgeConflicts')) {
            throw ValidationException::withMessages([
                'conflicts' => array_column($detected, 'message'),
            ]);
        }

        DB::transaction(function () use ($request, $engagement, $plan, $members, $detected): void {
            $lockedPlan = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $locked = IapPlanEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            $this->guard->assertLockVersion($lockedPlan, (int) $request->validated('lockVersion'));
            $oldTeam = $this->teamSnapshot($locked);
            $from = $locked->schedule_status;
            $reschedule = in_array($from, ['SCHEDULED', 'CANCELLED'], true);
            $reason = $request->validated('reason');

            $locked->forceFill([
                'planned_start_date' => $request->validated('plannedStartDate'),
                'planned_end_date' => $request->validated('plannedEndDate'),
                'expected_report_date' => $request->validated('expectedReportDate'),
                'schedule_status' => 'SCHEDULED',
                'scheduled_at' => $locked->scheduled_at ?? now(),
                'scheduled_by' => $locked->scheduled_by ?? $request->user()->id,
                'last_rescheduled_at' => $reschedule ? now() : $locked->last_rescheduled_at,
                'last_rescheduled_by' => $reschedule
                    ? $request->user()->id
                    : $locked->last_rescheduled_by,
                'last_reschedule_reason' => $reschedule
                    ? $reason
                    : $locked->last_reschedule_reason,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancellation_reason' => null,
            ])->save();
            $locked->teamMembers()->delete();
            foreach ($members as $member) {
                IapEngagementTeamMember::query()->create([
                    'plan_engagement_id' => $locked->id,
                    'user_id' => $member['userId'],
                    'team_role_id' => $member['teamRoleId'],
                    'planned_person_days' => $member['plannedPersonDays'],
                    'assignment_notes' => $member['notes'] ?? null,
                ]);
            }
            $lockedPlan->forceFill(['lock_version' => $lockedPlan->lock_version + 1])->save();
            $newTeam = $this->teamSnapshot($locked->fresh());
            IapScheduleEvent::query()->create([
                'plan_engagement_id' => $locked->id,
                'action' => $reschedule ? 'RESCHEDULE' : 'SCHEDULE',
                'from_status' => $from,
                'to_status' => 'SCHEDULED',
                'old_start_date' => $engagement->planned_start_date,
                'old_end_date' => $engagement->planned_end_date,
                'old_expected_report_date' => $engagement->expected_report_date,
                'new_start_date' => $request->validated('plannedStartDate'),
                'new_end_date' => $request->validated('plannedEndDate'),
                'new_expected_report_date' => $request->validated('expectedReportDate'),
                'old_team' => $oldTeam,
                'new_team' => $newTeam,
                'conflicts' => $detected,
                'reason' => $reason,
                'actor_id' => $request->user()->id,
            ]);
            $this->support->audit(
                $request,
                $reschedule ? 'iap.schedule.rescheduled' : 'iap.schedule.created',
                $locked,
                ['status' => $from, 'team' => $oldTeam],
                ['status' => 'SCHEDULED', 'team' => $newTeam],
                ['conflicts' => $detected, 'reason' => $reason],
            );
        }, 3);

        // Keep ARMIS's workload ledger current while preserving the IAP
        // schedule as immutable planning lineage.
        $this->resourceBackfill->backfill($request->user(), true);

        $this->notifications->send($members->pluck('userId'), [
            'actorId' => $request->user()->id,
            'type' => 'IAP_SCHEDULE_ASSIGNED',
            'category' => 'ASSIGNMENT',
            'priority' => 'HIGH',
            'moduleCode' => 'IAP',
            'title' => "Audit scheduled: {$engagement->engagement_code}",
            'message' => "{$engagement->title} is scheduled from {$request->validated('plannedStartDate')} to {$request->validated('plannedEndDate')}.",
            'actionUrl' => '/internal-audit-planning/scheduling',
            'actionLabel' => 'Open schedule',
            'subjectType' => 'IAP_PLAN_ENGAGEMENT',
            'subjectId' => $engagement->id,
            'subjectCode' => $engagement->engagement_code,
            'dedupeKey' => "iap-schedule:{$engagement->id}",
            'renotify' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => $engagement->schedule_status === 'UNSCHEDULED'
                ? 'Audit schedule created successfully.'
                : 'Audit schedule updated successfully.',
            'data' => [
                'schedule' => $this->payload(
                    $engagement->fresh()->load($this->relations()),
                ),
                'conflicts' => $detected,
            ],
        ]);
    }

    public function cancel(
        IapScheduleCancelRequest $request,
        IapPlanEngagement $engagement,
    ): JsonResponse {
        $plan = $engagement->plan;
        $this->assertSchedulable($request, $plan);
        if ($engagement->schedule_status !== 'SCHEDULED') {
            throw ValidationException::withMessages([
                'status' => ['Only a scheduled audit can be cancelled.'],
            ]);
        }

        DB::transaction(function () use ($request, $engagement, $plan): void {
            $lockedPlan = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $locked = IapPlanEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            $this->guard->assertLockVersion($lockedPlan, (int) $request->validated('lockVersion'));
            $team = $this->teamSnapshot($locked);
            $locked->forceFill([
                'schedule_status' => 'CANCELLED',
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()->id,
                'cancellation_reason' => $request->validated('reason'),
            ])->save();
            $lockedPlan->forceFill(['lock_version' => $lockedPlan->lock_version + 1])->save();
            IapScheduleEvent::query()->create([
                'plan_engagement_id' => $locked->id,
                'action' => 'CANCEL',
                'from_status' => 'SCHEDULED',
                'to_status' => 'CANCELLED',
                'old_start_date' => $locked->planned_start_date,
                'old_end_date' => $locked->planned_end_date,
                'old_expected_report_date' => $locked->expected_report_date,
                'old_team' => $team,
                'new_team' => $team,
                'reason' => $request->validated('reason'),
                'actor_id' => $request->user()->id,
            ]);
            $this->support->audit(
                $request,
                'iap.schedule.cancelled',
                $locked,
                ['schedule_status' => 'SCHEDULED'],
                ['schedule_status' => 'CANCELLED'],
                ['reason' => $request->validated('reason')],
            );
        }, 3);

        $this->notifications->send(
            $engagement->teamMembers()->pluck('user_id'),
            [
                'actorId' => $request->user()->id,
                'type' => 'IAP_SCHEDULE_CANCELLED',
                'category' => 'ASSIGNMENT',
                'priority' => 'URGENT',
                'moduleCode' => 'IAP',
                'title' => "Audit schedule cancelled: {$engagement->engagement_code}",
                'message' => $request->validated('reason'),
                'actionUrl' => '/internal-audit-planning/scheduling',
                'actionLabel' => 'Open schedule',
                'subjectType' => 'IAP_PLAN_ENGAGEMENT',
                'subjectId' => $engagement->id,
                'subjectCode' => $engagement->engagement_code,
                'dedupeKey' => "iap-schedule-cancelled:{$engagement->id}",
                'renotify' => true,
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Audit schedule cancelled without deleting its history.',
        ]);
    }

    public function updateCapacity(Request $request, User $user): JsonResponse
    {
        $this->guard->assertManagement($request->user());
        return response()->json([
            'success' => false,
            'message' => 'ARMIS is the authoritative resource workspace. Use /audit-resource-management/planning for capacity changes.',
            'replacementPath' => '/audit-resource-management/planning',
            'sourceOfTruth' => 'ARMIS',
        ], 409);
    }

    private function assertSchedulable(Request $request, InternalAuditPlan $plan): void
    {
        $this->guard->assertEditable($request->user(), $plan);
    }

    private function validateDomain(
        IapScheduleRequest $request,
        IapPlanEngagement $engagement,
        Collection $members,
        bool $requireReason,
    ): void {
        $plan = $engagement->plan;
        if ($request->date('plannedStartDate')->lt($plan->planning_period_start)
            || $request->date('plannedEndDate')->gt($plan->planning_period_end)) {
            throw ValidationException::withMessages([
                'plannedStartDate' => ['Schedule dates must fall within the annual plan period.'],
            ]);
        }
        if ($requireReason
            && in_array($engagement->schedule_status, ['SCHEDULED', 'CANCELLED'], true)
            && blank($request->validated('reason'))) {
            throw ValidationException::withMessages([
                'reason' => ['A reason is required when rescheduling or reinstating a cancelled audit.'],
            ]);
        }

        $roles = $this->teamRoles()->keyBy('id');
        $roleCodes = $members->map(function ($member) use ($roles): ?string {
            return $roles->get((int) $member['teamRoleId'])?->code;
        });
        if ($roleCodes->filter()->count() !== $members->count()) {
            throw ValidationException::withMessages([
                'members' => ['Every team role must be an active IAP team role.'],
            ]);
        }
        if ($roleCodes->filter(fn ($code) => $code === 'LEAD_AUDITOR')->count() !== 1) {
            throw ValidationException::withMessages([
                'members' => ['Assign exactly one proposed Team Leader.'],
            ]);
        }
        if (! $roleCodes->contains('REVIEWER')) {
            throw ValidationException::withMessages([
                'members' => ['Assign at least one Reviewer.'],
            ]);
        }

        $eligible = User::query()
            ->whereIn('id', $members->pluck('userId'))
            ->where('is_active', true)
            ->whereHas('roles.permissions', fn ($permission) => $permission
                ->where('code', 'iap.assign_team'))
            ->count();
        if ($eligible !== $members->count()) {
            throw ValidationException::withMessages([
                'members' => ['Every team member must be an active CIAS auditor.'],
            ]);
        }

        if (abs(
            (float) $members->sum('plannedPersonDays')
            - (float) $engagement->estimated_person_days
        ) > 0.01) {
            throw ValidationException::withMessages([
                'members' => [
                    'Assigned team person-days must equal the engagement estimate of '.
                    number_format((float) $engagement->estimated_person_days, 2).'.',
                ],
            ]);
        }
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'plan:id,plan_code,title,fiscal_year,status,lock_version,prepared_by',
            'offices:id,code,name',
            'auditAreas:id,code,name',
            'riskLevel',
            'priority',
            'teamMembers.user:id,employee_id,name,initials',
            'teamMembers.teamRole',
            'scheduleEvents.actor:id,employee_id,name,initials',
        ];
    }

    private function payload(IapPlanEngagement $engagement): array
    {
        $currentConflicts = $engagement->schedule_status === 'SCHEDULED'
            ? $this->conflicts->detect(
                $engagement,
                $engagement->planned_start_date,
                $engagement->planned_end_date,
                $engagement->teamMembers->map(fn ($member) => [
                    'userId' => $member->user_id,
                    'plannedPersonDays' => (float) $member->planned_person_days,
                ]),
            )
            : [];

        return [
            'id' => $engagement->id,
            'engagementCode' => $engagement->engagement_code,
            'title' => $engagement->title,
            'plan' => [
                'id' => $engagement->plan->id,
                'planCode' => $engagement->plan->plan_code,
                'title' => $engagement->plan->title,
                'fiscalYear' => $engagement->plan->fiscal_year,
                'status' => $engagement->plan->status,
                'lockVersion' => $engagement->plan->lock_version,
                'preparedBy' => $engagement->plan->prepared_by,
            ],
            'plannedStartDate' => $engagement->planned_start_date?->toDateString(),
            'plannedEndDate' => $engagement->planned_end_date?->toDateString(),
            'expectedReportDate' => $engagement->expected_report_date?->toDateString(),
            'scheduleStatus' => $engagement->schedule_status,
            'estimatedPersonDays' => (float) $engagement->estimated_person_days,
            'targetQuarter' => $engagement->target_quarter,
            'priority' => $engagement->priority ? [
                'id' => $engagement->priority->id,
                'code' => $engagement->priority->code,
                'label' => $engagement->priority->label,
            ] : null,
            'riskLevel' => $engagement->riskLevel ? [
                'id' => $engagement->riskLevel->id,
                'code' => $engagement->riskLevel->code,
                'label' => $engagement->riskLevel->label,
            ] : null,
            'offices' => $engagement->offices->map->only(['id', 'code', 'name'])->values(),
            'auditAreas' => $engagement->auditAreas->map->only(['id', 'code', 'name'])->values(),
            'teamMembers' => $engagement->teamMembers->map(fn ($member) => [
                'id' => $member->id,
                'userId' => $member->user_id,
                'user' => $member->user ? [
                    'id' => $member->user->id,
                    'employeeId' => $member->user->employee_id,
                    'name' => $member->user->name,
                    'initials' => $member->user->initials,
                ] : null,
                'teamRoleId' => $member->team_role_id,
                'teamRoleCode' => $member->teamRole?->code,
                'teamRoleLabel' => $member->teamRole?->label,
                'plannedPersonDays' => (float) $member->planned_person_days,
                'notes' => $member->assignment_notes,
            ])->values(),
            'conflicts' => $currentConflicts,
            'cancellationReason' => $engagement->cancellation_reason,
            'history' => $engagement->scheduleEvents->map(fn ($event) => [
                'id' => $event->id,
                'action' => $event->action,
                'fromStatus' => $event->from_status,
                'toStatus' => $event->to_status,
                'oldStartDate' => $event->old_start_date?->toDateString(),
                'oldEndDate' => $event->old_end_date?->toDateString(),
                'oldExpectedReportDate' => $event->old_expected_report_date?->toDateString(),
                'newStartDate' => $event->new_start_date?->toDateString(),
                'newEndDate' => $event->new_end_date?->toDateString(),
                'newExpectedReportDate' => $event->new_expected_report_date?->toDateString(),
                'reason' => $event->reason,
                'conflicts' => $event->conflicts ?? [],
                'actor' => $event->actor ? [
                    'id' => $event->actor->id,
                    'name' => $event->actor->name,
                ] : null,
                'createdAt' => $event->created_at?->toISOString(),
            ])->values(),
        ];
    }

    private function auditors(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles.permissions', fn ($permission) => $permission
                ->where('code', 'iap.assign_team'))
            ->with('role:id,code,name')
            ->orderBy('name')
            ->get(['id', 'employee_id', 'name', 'initials', 'role_id']);
    }

    private function teamRoles(): Collection
    {
        return MasterListItem::query()
            ->where('is_active', true)
            ->whereHas('masterList', fn ($list) => $list
                ->where('code', 'IAP_TEAM_ROLE')
                ->where('is_active', true))
            ->orderBy('display_order')
            ->get();
    }

    private function teamSnapshot(IapPlanEngagement $engagement): array
    {
        return $engagement->teamMembers()
            ->with(['user:id,employee_id,name', 'teamRole:id,code,label'])
            ->get()
            ->map(fn ($member) => [
                'userId' => $member->user_id,
                'employeeId' => $member->user?->employee_id,
                'name' => $member->user?->name,
                'teamRoleId' => $member->team_role_id,
                'teamRoleCode' => $member->teamRole?->code,
                'plannedPersonDays' => (float) $member->planned_person_days,
            ])->all();
    }

    private function capacityRows(int $year, Collection $auditors): Collection
    {
        $allocated = DB::table('iap_engagement_team_members as team')
            ->join('iap_plan_engagements as engagement', 'engagement.id', '=', 'team.plan_engagement_id')
            ->join('internal_audit_plans as plan', 'plan.id', '=', 'engagement.plan_id')
            ->where('plan.fiscal_year', $year)
            ->where('plan.is_current_revision', true)
            ->where('engagement.schedule_status', 'SCHEDULED')
            ->whereNull('engagement.deleted_at')
            ->whereNull('plan.deleted_at')
            ->groupBy('team.user_id')
            ->selectRaw('team.user_id, SUM(team.planned_person_days) as allocated')
            ->pluck('allocated', 'user_id');

        return $auditors->map(fn ($user) => [
            'fiscalYear' => $year,
            'userId' => $user->id,
            'availablePersonDays' => $this->conflicts->capacityFor($year, $user->id),
            'allocatedPersonDays' => (float) ($allocated[$user->id] ?? 0),
            'remainingPersonDays' => round(
                $this->conflicts->capacityFor($year, $user->id)
                - (float) ($allocated[$user->id] ?? 0),
                2,
            ),
        ]);
    }
}
