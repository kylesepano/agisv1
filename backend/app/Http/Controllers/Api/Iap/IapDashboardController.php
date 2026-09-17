<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Models\IapAuditUniverseItem;
use App\Models\IapPlanEngagement;
use App\Models\IapPrioritizationRun;
use App\Models\IapRiskPeriod;
use App\Models\InternalAuditPlan;
use App\Models\User;
use App\Services\IapPlanGuard;
use App\Services\IapScheduleConflictService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates live planning, risk, scheduling, and capacity dashboard metrics.
 */
class IapDashboardController extends Controller
{
    public function __construct(
        private readonly IapPlanGuard $guard,
        private readonly IapScheduleConflictService $conflicts,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $planQuery = InternalAuditPlan::query()
            ->where('is_current_revision', true)
            ->where('is_active', true);
        $this->guard->scopeVisible($planQuery, $request->user());
        $plan = $planQuery
            ->with([
                'preparer:id,employee_id,name,initials',
                'submitter:id,employee_id,name,initials',
                'approver:id,employee_id,name,initials',
                'prioritizationRun.riskPeriod',
                'prioritizationRun.items',
                'engagements' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with([
                        'offices:id,code,name',
                        'riskLevel',
                        'teamMembers.user:id,employee_id,name,initials',
                        'skillRequirements.specialization',
                        'aemEngagement:id,iap_plan_engagement_id,status,deleted_at',
                    ]),
            ])
            ->orderByDesc('fiscal_year')
            ->orderByDesc('revision_number')
            ->first();

        $visiblePlanIds = InternalAuditPlan::query();
        $this->guard->scopeVisible($visiblePlanIds, $request->user());
        $visiblePlanIds = $visiblePlanIds->pluck('id');
        $planCount = InternalAuditPlan::query()
            ->whereIn('id', $visiblePlanIds)
            ->count();

        $prioritization = $plan?->prioritizationRun
            ?? IapPrioritizationRun::query()
                ->where('status', 'FINALIZED')
                ->where('is_active', true)
                ->with(['riskPeriod', 'items'])
                ->latest('finalized_at')
                ->latest('id')
                ->first();
        $riskPeriod = $prioritization?->riskPeriod
            ?? IapRiskPeriod::query()
                ->whereIn('status', ['VALIDATED', 'LOCKED'])
                ->where('is_active', true)
                ->latest('assessment_year')
                ->latest('id')
                ->first();
        $riskAssessments = $riskPeriod
            ? $riskPeriod->assessments()
                ->whereIn('status', ['VALIDATED', 'LOCKED'])
                ->with([
                    'residualRiskLevel',
                    'auditUniverseItem:id,subject_code,name,responsible_office_id,primary_audit_area_id',
                    'auditUniverseItem.responsibleOffice:id,code,name',
                ])
                ->get()
            : collect();

        $engagements = $plan?->engagements ?? collect();
        $activeEngagements = $engagements
            ->where('is_active', true)
            ->where('schedule_status', '!=', 'CANCELLED')
            ->values();
        $prioritizationItems = $prioritization?->items ?? collect();
        $selected = $prioritizationItems->where('decision', 'SELECTED');
        $deferred = $prioritizationItems->where('decision', 'DEFERRED');
        $plannedSourceIds = $activeEngagements
            ->pluck('prioritization_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();
        $unplanned = $selected
            ->reject(fn ($item) => $plannedSourceIds->contains((int) $item->id))
            ->values();

        $fiscalYear = (int) ($plan?->fiscal_year
            ?? $prioritization?->riskPeriod?->assessment_year
            ?? app(\App\Services\RuntimeConfiguration::class)->currentFiscalYear());
        $capacity = $this->capacity($fiscalYear, $plan);
        $warnings = $this->scheduleWarnings($activeEngagements);
        $upcoming = $activeEngagements
            ->where('schedule_status', 'SCHEDULED')
            ->filter(fn ($engagement) => $engagement->planned_start_date?->gte(today()))
            ->sortBy('planned_start_date')
            ->take(6)
            ->map(fn ($engagement) => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'plannedStartDate' => $engagement->planned_start_date?->toDateString(),
                'plannedEndDate' => $engagement->planned_end_date?->toDateString(),
                'expectedReportDate' => $engagement->expected_report_date?->toDateString(),
                'riskLevel' => $engagement->riskLevel?->only(['id', 'code', 'label']),
                'offices' => $engagement->offices
                    ->map(fn ($office) => $office->only(['id', 'code', 'name']))
                    ->values(),
                'team' => $engagement->teamMembers
                    ->map(fn ($member) => [
                        'userId' => $member->user_id,
                        'name' => $member->user?->name,
                        'initials' => $member->user?->initials,
                    ])
                    ->values(),
            ])
            ->values();

        // AEMS owns the import relationship.  Do not use the legacy IAP
        // compatibility column as mutable source state.
        $implemented = $activeEngagements
            ->filter(fn ($engagement): bool => $engagement->aemEngagement !== null)
            ->count();
        $plannedCount = $activeEngagements->count();
        $accomplishment = $plan?->status === 'COMPLETED'
            ? 100.0
            : ($plannedCount > 0 ? round(($implemented / $plannedCount) * 100, 1) : 0.0);
        $approvalProgress = [
            'DRAFT' => 10,
            'RETURNED_FOR_REVISION' => 25,
            'PENDING_REVIEW' => 45,
            'RESUBMITTED' => 55,
            'REJECTED' => 60,
            'APPROVED' => 75,
            'ACTIVE' => 90,
            'COMPLETED' => 100,
        ][$plan?->status] ?? 0;

        $riskDistribution = collect(['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'])
            ->map(fn (string $code) => [
                'code' => $code,
                'label' => ucfirst(strtolower($code)),
                'value' => $riskAssessments
                    ->filter(fn ($assessment) => $assessment->residualRiskLevel?->code === $code)
                    ->count(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'context' => [
                    'fiscalYear' => $fiscalYear,
                    'planCount' => $planCount,
                    'generatedAt' => now()->toISOString(),
                    'riskPeriod' => $riskPeriod ? [
                        'id' => $riskPeriod->id,
                        'periodCode' => $riskPeriod->period_code,
                        'name' => $riskPeriod->name,
                        'assessmentYear' => $riskPeriod->assessment_year,
                        'status' => $riskPeriod->status,
                    ] : null,
                    'prioritization' => $prioritization ? [
                        'id' => $prioritization->id,
                        'runCode' => $prioritization->run_code,
                        'name' => $prioritization->name,
                        'status' => $prioritization->status,
                    ] : null,
                ],
                'plan' => $this->planData($plan, $approvalProgress),
                'metrics' => [
                    'totalAuditUniverse' => IapAuditUniverseItem::query()
                        ->where('is_active', true)
                        ->count(),
                    'criticalRiskSubjects' => $riskDistribution
                        ->firstWhere('code', 'CRITICAL')['value'],
                    'highRiskSubjects' => $riskDistribution
                        ->firstWhere('code', 'HIGH')['value'],
                    'selectedSubjects' => $selected->count(),
                    'deferredSubjects' => $deferred->count(),
                    'plannedAudits' => $plannedCount,
                    'unplannedAudits' => $unplanned->count(),
                    'availablePersonDays' => $capacity['availablePersonDays'],
                    'allocatedPersonDays' => $capacity['allocatedPersonDays'],
                    'remainingPersonDays' => $capacity['remainingPersonDays'],
                    'capacityUtilization' => $capacity['capacityUtilization'],
                    'availableAuditors' => $capacity['availableAuditors'],
                    'overallocatedAuditors' => $capacity['overallocatedAuditors'],
                    'planAccomplishment' => $accomplishment,
                    'implementedEngagements' => $implemented,
                    'scheduleConflictWarnings' => count($warnings),
                    'upcomingAudits' => $upcoming->count(),
                ],
                'riskDistribution' => $riskDistribution,
                'decisionDistribution' => [
                    ['code' => 'PLANNED', 'label' => 'Planned', 'value' => $plannedSourceIds->count()],
                    ['code' => 'UNPLANNED', 'label' => 'Unplanned', 'value' => $unplanned->count()],
                    ['code' => 'DEFERRED', 'label' => 'Deferred', 'value' => $deferred->count()],
                    [
                        'code' => 'NOT_SELECTED',
                        'label' => 'Not selected',
                        'value' => $prioritizationItems->where('decision', 'NOT_SELECTED')->count(),
                    ],
                ],
                'upcomingAudits' => $upcoming,
                'conflictWarnings' => $warnings,
                'unplannedSubjects' => $unplanned
                    ->take(6)
                    ->map(fn ($item) => [
                        'id' => $item->id,
                        'subjectCode' => $item->subject_code,
                        'subjectName' => $item->subject_name,
                        'officeCode' => $item->office_code,
                        'riskLevelCode' => $item->risk_level_code,
                        'priorityScore' => (float) $item->priority_score,
                        'finalRank' => $item->final_rank,
                    ])
                    ->values(),
            ],
        ]);
    }

    /** @return array<string, float|int> */
    private function capacity(int $fiscalYear, ?InternalAuditPlan $plan): array
    {
        $auditors = User::query()
            ->where('is_active', true)
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'iap.assign_team'))
            ->get(['id']);
        $available = (float) $auditors
            ->sum(fn ($user) => $this->conflicts->capacityFor($fiscalYear, $user->id));
        $allocatedByUser = $plan
            ? DB::table('iap_engagement_team_members as team')
                ->join('iap_plan_engagements as engagement', 'engagement.id', '=', 'team.plan_engagement_id')
                ->where('engagement.plan_id', $plan->id)
                ->where('engagement.schedule_status', 'SCHEDULED')
                ->where('engagement.is_active', true)
                ->whereNull('engagement.deleted_at')
                ->groupBy('team.user_id')
                ->selectRaw('team.user_id, SUM(team.planned_person_days) as allocated')
                ->pluck('allocated', 'team.user_id')
            : collect();
        $allocated = (float) $allocatedByUser->sum();
        $overallocated = $auditors->filter(
            fn ($user) => (float) ($allocatedByUser[$user->id] ?? 0)
                > $this->conflicts->capacityFor($fiscalYear, $user->id),
        )->count();

        return [
            'availablePersonDays' => round($available, 2),
            'allocatedPersonDays' => round($allocated, 2),
            'remainingPersonDays' => round($available - $allocated, 2),
            'capacityUtilization' => $available > 0
                ? round(($allocated / $available) * 100, 1)
                : ($allocated > 0 ? 100.0 : 0.0),
            'availableAuditors' => $auditors->count(),
            'overallocatedAuditors' => $overallocated,
        ];
    }

    /**
     * @param  Collection<int, IapPlanEngagement>  $engagements
     * @return list<array<string, mixed>>
     */
    private function scheduleWarnings(Collection $engagements): array
    {
        $warnings = [];
        foreach ($engagements->where('schedule_status', 'SCHEDULED') as $engagement) {
            if (! $engagement->planned_start_date || ! $engagement->planned_end_date) {
                continue;
            }
            $detected = $this->conflicts->detect(
                $engagement,
                $engagement->planned_start_date,
                $engagement->planned_end_date,
                $engagement->teamMembers->map(fn ($member) => [
                    'userId' => $member->user_id,
                    'plannedPersonDays' => (float) $member->planned_person_days,
                ]),
            );
            foreach ($detected as $warning) {
                $warnings[] = [
                    ...$warning,
                    'sourceEngagementId' => $engagement->id,
                    'sourceEngagementCode' => $engagement->engagement_code,
                    'sourceEngagementTitle' => $engagement->title,
                ];
            }
        }

        return collect($warnings)
            ->unique(fn ($warning) => implode('|', [
                $warning['sourceEngagementId'],
                $warning['type'] ?? '',
                $warning['engagementId'] ?? '',
                $warning['userId'] ?? '',
                $warning['message'] ?? '',
            ]))
            ->take(12)
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function planData(?InternalAuditPlan $plan, int $approvalProgress): ?array
    {
        if (! $plan) {
            return null;
        }

        return [
            'id' => $plan->id,
            'planCode' => $plan->plan_code,
            'title' => $plan->title,
            'fiscalYear' => $plan->fiscal_year,
            'revisionNumber' => $plan->revision_number,
            'status' => $plan->status,
            'approvalProgress' => $approvalProgress,
            'preparedBy' => $plan->preparer?->only(['id', 'employee_id', 'name', 'initials']),
            'submittedAt' => $plan->submitted_at?->toISOString(),
            'submittedBy' => $plan->submitter?->only(['id', 'employee_id', 'name', 'initials']),
            'approvedAt' => $plan->approved_at?->toISOString(),
            'approvedBy' => $plan->approver?->only(['id', 'employee_id', 'name', 'initials']),
        ];
    }
}
