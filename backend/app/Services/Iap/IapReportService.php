<?php

namespace App\Services;

use App\Models\IapAuditUniverseItem;
use App\Models\IapPrioritizationRun;
use App\Models\IapRiskPeriod;
use App\Models\InternalAuditPlan;
use App\Models\StrategicInternalAuditPlan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Builds normalized datasets and export output for every supported IAP report.
 */
class IapReportService
{
    public const REPORTS = [
        'approved-siap' => [
            'title' => 'Approved Strategic Internal Audit Plan',
            'description' => 'Approved multi-year objectives, priorities, themes, expected outcomes, and linked audit areas.',
            'filter' => 'strategicPlanId',
        ],
        'audit-universe' => [
            'title' => 'Audit Universe Report',
            'description' => 'Complete inventory of active auditable subjects, responsible offices, audit areas, materiality, and audit history.',
            'filter' => null,
        ],
        'risk-assessment-matrix' => [
            'title' => 'Risk-assessment Matrix',
            'description' => 'Validated inherent risk, control effectiveness, residual risk, and supporting justification by auditable subject.',
            'filter' => 'riskPeriodId',
        ],
        'risk-heat-map' => [
            'title' => 'Risk Heat Map',
            'description' => 'Distribution of validated subjects across inherent and residual risk levels.',
            'filter' => 'riskPeriodId',
        ],
        'prioritization-ranking' => [
            'title' => 'Prioritization Ranking',
            'description' => 'Final ranking, risk scores, selection decisions, overrides, and decision reasons.',
            'filter' => 'prioritizationId',
        ],
        'approved-annual-plan' => [
            'title' => 'Approved Annual Internal Audit Plan',
            'description' => 'Approved engagements, objectives, scope, target dates, offices, risks, teams, and estimated resources.',
            'filter' => 'planId',
        ],
        'annual-audit-schedule' => [
            'title' => 'Annual Audit Schedule',
            'description' => 'Planned engagement dates, expected reports, responsible offices, proposed teams, and person-day allocations.',
            'filter' => 'planId',
        ],
        'auditor-allocation' => [
            'title' => 'Auditor Allocation Report',
            'description' => 'Annual capacity, assigned person-days, remaining capacity, utilization, and scheduled engagement workload.',
            'filter' => 'fiscalYear',
        ],
        'plan-revision-history' => [
            'title' => 'Plan Revision History',
            'description' => 'Annual-plan revisions and permanent workflow history with actors, comments, and status changes.',
            'filter' => 'fiscalYear',
        ],
    ];

    public function __construct(
        private readonly IapPlanGuard $planGuard,
        private readonly SiapPlanGuard $siapGuard,
        private readonly IapScheduleConflictService $capacity,
    ) {}

    /** @return array<string, mixed> */
    public function catalog(User $user): array
    {
        $plans = InternalAuditPlan::query();
        $this->planGuard->scopeVisible($plans, $user);
        $plans = $plans
            ->orderByDesc('fiscal_year')
            ->orderByDesc('revision_number')
            ->get(['id', 'plan_code', 'title', 'fiscal_year', 'revision_number', 'status', 'is_current_revision']);

        $strategicPlans = StrategicInternalAuditPlan::query();
        $this->siapGuard->scopeVisible($strategicPlans, $user);
        $strategicPlans = $strategicPlans
            ->whereIn('status', ['APPROVED', 'ACTIVE', 'COMPLETED'])
            ->orderByDesc('start_year')
            ->orderByDesc('revision_number')
            ->get(['id', 'plan_code', 'title', 'start_year', 'end_year', 'revision_number', 'status']);

        return [
            'reports' => collect(self::REPORTS)->map(
                fn (array $definition, string $code) => [
                    'code' => $code,
                    ...$definition,
                ],
            )->values(),
            'strategicPlans' => $strategicPlans->map(fn ($plan) => [
                'id' => $plan->id,
                'label' => "{$plan->plan_code} - {$plan->title}",
                'status' => $plan->status,
            ])->values(),
            'plans' => $plans->map(fn ($plan) => [
                'id' => $plan->id,
                'label' => "{$plan->plan_code} - {$plan->title}",
                'fiscalYear' => $plan->fiscal_year,
                'revisionNumber' => $plan->revision_number,
                'status' => $plan->status,
                'isCurrentRevision' => $plan->is_current_revision,
            ])->values(),
            'approvedPlans' => $plans
                ->whereIn('status', ['APPROVED', 'ACTIVE', 'COMPLETED'])
                ->values()
                ->map(fn ($plan) => [
                    'id' => $plan->id,
                    'label' => "{$plan->plan_code} - {$plan->title}",
                    'fiscalYear' => $plan->fiscal_year,
                    'status' => $plan->status,
                ]),
            'riskPeriods' => IapRiskPeriod::query()
                ->whereIn('status', ['VALIDATED', 'LOCKED'])
                ->orderByDesc('assessment_year')
                ->get(['id', 'period_code', 'name', 'assessment_year', 'status'])
                ->map(fn ($period) => [
                    'id' => $period->id,
                    'label' => "{$period->period_code} - {$period->name}",
                    'assessmentYear' => $period->assessment_year,
                    'status' => $period->status,
                ]),
            'prioritizations' => IapPrioritizationRun::query()
                ->where('status', 'FINALIZED')
                ->orderByDesc('finalized_at')
                ->get(['id', 'run_code', 'name', 'risk_period_id', 'status'])
                ->map(fn ($run) => [
                    'id' => $run->id,
                    'label' => "{$run->run_code} - {$run->name}",
                    'status' => $run->status,
                ]),
            'fiscalYears' => $plans->pluck('fiscal_year')->unique()->sortDesc()->values(),
            'canExport' => $user->hasPermission('iap.export'),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function generate(string $code, array $filters, User $user): array
    {
        if (! isset(self::REPORTS[$code])) {
            abort(404);
        }

        $report = match ($code) {
            'approved-siap' => $this->approvedSiap($filters, $user),
            'audit-universe' => $this->auditUniverse(),
            'risk-assessment-matrix' => $this->riskAssessment($filters, false),
            'risk-heat-map' => $this->riskAssessment($filters, true),
            'prioritization-ranking' => $this->prioritization($filters),
            'approved-annual-plan' => $this->annualPlan($filters, $user, true),
            'annual-audit-schedule' => $this->annualSchedule($filters, $user),
            'auditor-allocation' => $this->auditorAllocation($filters, $user),
            'plan-revision-history' => $this->revisionHistory($filters, $user),
        };

        return [
            'code' => $code,
            'title' => self::REPORTS[$code]['title'],
            'description' => self::REPORTS[$code]['description'],
            'generatedAt' => now()->toISOString(),
            'fileName' => Str::slug(self::REPORTS[$code]['title']).'-'.now()->format('Ymd-His'),
            ...$report,
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function approvedSiap(array $filters, User $user): array
    {
        $query = StrategicInternalAuditPlan::query()
            ->whereIn('status', ['APPROVED', 'ACTIVE', 'COMPLETED']);
        $this->siapGuard->scopeVisible($query, $user);
        $plan = isset($filters['strategicPlanId'])
            ? $query->findOrFail((int) $filters['strategicPlanId'])
            : $query->latest('start_year')->latest('revision_number')->first();
        $this->requireRecord($plan, 'strategicPlanId', 'No approved strategic plan is available.');
        $plan->load([
            'objectives.auditAreas:id,code,name',
            'priorities',
            'preparer:id,name',
            'approver:id,name',
        ]);

        return $this->table(
            [
                'Plan' => $plan->plan_code,
                'Planning Period' => "{$plan->start_year}-{$plan->end_year}",
                'Status' => $plan->status,
                'Revision' => $plan->revision_number,
                'Prepared By' => $plan->preparer?->name,
                'Approved By' => $plan->approver?->name,
                'Approved On' => $plan->approved_at?->format('M j, Y'),
            ],
            [
                ['key' => 'objectiveCode', 'label' => 'Objective Code'],
                ['key' => 'objective', 'label' => 'Strategic Objective'],
                ['key' => 'auditAreas', 'label' => 'Linked Audit Areas'],
                ['key' => 'expectedOutcome', 'label' => 'Expected Outcome'],
            ],
            $plan->objectives->map(fn ($objective) => [
                'objectiveCode' => $objective->objective_code,
                'objective' => $objective->title,
                'auditAreas' => $objective->auditAreas
                    ->map(fn ($area) => "{$area->code} - {$area->name}")
                    ->join('; '),
                'expectedOutcome' => $objective->expected_outcome,
            ]),
            [
                [
                    'title' => 'Strategic Context',
                    'items' => [
                        ['heading' => 'Context', 'text' => $plan->strategic_context],
                        ['heading' => 'Vision', 'text' => $plan->vision],
                        ['heading' => 'Mission Alignment', 'text' => $plan->mission_alignment],
                        ['heading' => 'Planning Methodology', 'text' => $plan->planning_methodology],
                        ['heading' => 'Expected Outcomes', 'text' => $plan->expected_outcomes],
                    ],
                ],
                [
                    'title' => 'Audit Priorities and Themes',
                    'items' => $plan->priorities->map(fn ($priority) => [
                        'heading' => "{$priority->priority_code} - {$priority->title}",
                        'text' => collect([
                            $priority->theme ? "Theme: {$priority->theme}" : null,
                            $priority->description,
                            $priority->expected_outcome ? "Expected outcome: {$priority->expected_outcome}" : null,
                        ])->filter()->join(' | '),
                    ])->values()->all(),
                ],
            ],
        );
    }

    /** @return array<string, mixed> */
    private function auditUniverse(): array
    {
        $items = IapAuditUniverseItem::query()
            ->with([
                'subjectType', 'responsibleOffice:id,code,name',
                'primaryAuditArea:id,code,name', 'materialityLevel',
            ])
            ->orderBy('subject_code')
            ->get();

        return $this->table(
            [
                'Active Subjects' => $items->where('is_active', true)->count(),
                'Total Records' => $items->count(),
            ],
            [
                ['key' => 'subjectCode', 'label' => 'Subject Code'],
                ['key' => 'subject', 'label' => 'Auditable Subject'],
                ['key' => 'type', 'label' => 'Subject Type'],
                ['key' => 'office', 'label' => 'Responsible Office'],
                ['key' => 'auditArea', 'label' => 'Primary Audit Area'],
                ['key' => 'materiality', 'label' => 'Materiality'],
                ['key' => 'lastAudit', 'label' => 'Last Audit'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            $items->map(fn ($item) => [
                'subjectCode' => $item->subject_code,
                'subject' => $item->name,
                'type' => $item->subjectType?->label,
                'office' => $item->responsibleOffice
                    ? "{$item->responsibleOffice->code} - {$item->responsibleOffice->name}" : null,
                'auditArea' => $item->primaryAuditArea
                    ? "{$item->primaryAuditArea->code} - {$item->primaryAuditArea->name}" : null,
                'materiality' => $item->materialityLevel?->label,
                'lastAudit' => $item->last_audit_date?->format('M j, Y'),
                'status' => $item->is_active ? 'Active' : 'Inactive',
            ]),
        );
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function riskAssessment(array $filters, bool $heatMap): array
    {
        $period = $this->riskPeriod($filters);
        $period->load([
            'assessments' => fn ($query) => $query
                ->whereIn('status', ['VALIDATED', 'LOCKED'])
                ->with([
                    'auditUniverseItem.responsibleOffice:id,code,name',
                    'inherentRiskLevel', 'residualRiskLevel', 'assessor:id,name',
                ])
                ->orderByDesc('residual_risk_score'),
        ]);
        $assessments = $period->assessments;
        $rows = $assessments->map(fn ($assessment) => [
            'subjectCode' => $assessment->auditUniverseItem?->subject_code,
            'subject' => $assessment->auditUniverseItem?->name,
            'office' => $assessment->auditUniverseItem?->responsibleOffice?->code,
            'inherentScore' => number_format((float) $assessment->inherent_risk_score, 2),
            'inherentLevel' => $assessment->inherentRiskLevel?->label,
            'controlEffectiveness' => number_format((float) $assessment->control_effectiveness_percent, 1).'%',
            'residualScore' => number_format((float) $assessment->residual_risk_score, 2),
            'residualLevel' => $assessment->residualRiskLevel?->label,
            'justification' => $assessment->justification,
            '_inherentCode' => $assessment->inherentRiskLevel?->code,
            '_residualCode' => $assessment->residualRiskLevel?->code,
        ]);
        $levels = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];
        $matrix = collect($levels)->map(fn ($inherent) => [
            'inherent' => $inherent,
            'cells' => collect($levels)->map(fn ($residual) => [
                'residual' => $residual,
                'value' => $rows->filter(
                    fn ($row) => $row['_inherentCode'] === $inherent
                        && $row['_residualCode'] === $residual,
                )->count(),
            ])->values(),
        ])->values();

        return $this->table(
            [
                'Assessment Period' => $period->period_code,
                'Assessment Year' => $period->assessment_year,
                'Status' => $period->status,
                'Validated Subjects' => $assessments->count(),
            ],
            [
                ['key' => 'subjectCode', 'label' => 'Subject Code'],
                ['key' => 'subject', 'label' => 'Auditable Subject'],
                ['key' => 'office', 'label' => 'Office'],
                ['key' => 'inherentScore', 'label' => 'Inherent Score'],
                ['key' => 'inherentLevel', 'label' => 'Inherent Level'],
                ['key' => 'controlEffectiveness', 'label' => 'Control Effectiveness'],
                ['key' => 'residualScore', 'label' => 'Residual Score'],
                ['key' => 'residualLevel', 'label' => 'Residual Level'],
                ...($heatMap ? [] : [['key' => 'justification', 'label' => 'Justification']]),
            ],
            $rows->map(fn ($row) => collect($row)->except(['_inherentCode', '_residualCode'])->all()),
            [],
            $heatMap ? ['type' => 'riskHeatMap', 'levels' => $levels, 'matrix' => $matrix] : null,
        );
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function prioritization(array $filters): array
    {
        $query = IapPrioritizationRun::query()->where('status', 'FINALIZED');
        $run = isset($filters['prioritizationId'])
            ? $query->findOrFail((int) $filters['prioritizationId'])
            : $query->latest('finalized_at')->first();
        $this->requireRecord($run, 'prioritizationId', 'No finalized prioritization is available.');
        $run->load(['riskPeriod:id,period_code,name,assessment_year', 'items']);

        return $this->table(
            [
                'Prioritization' => $run->run_code,
                'Risk Period' => $run->riskPeriod?->period_code,
                'Finalized On' => $run->finalized_at?->format('M j, Y'),
                'Total Ranked Subjects' => $run->items->count(),
            ],
            [
                ['key' => 'rank', 'label' => 'Final Rank'],
                ['key' => 'subjectCode', 'label' => 'Subject Code'],
                ['key' => 'subject', 'label' => 'Auditable Subject'],
                ['key' => 'office', 'label' => 'Office'],
                ['key' => 'auditArea', 'label' => 'Audit Area'],
                ['key' => 'residualRisk', 'label' => 'Residual Risk'],
                ['key' => 'priorityScore', 'label' => 'Priority Score'],
                ['key' => 'decision', 'label' => 'Decision'],
                ['key' => 'reason', 'label' => 'Decision / Override Reason'],
            ],
            $run->items->map(fn ($item) => [
                'rank' => $item->final_rank,
                'subjectCode' => $item->subject_code,
                'subject' => $item->subject_name,
                'office' => $item->office_code,
                'auditArea' => $item->audit_area_name,
                'residualRisk' => number_format((float) $item->residual_risk_score, 2)
                    ." ({$item->risk_level_label})",
                'priorityScore' => number_format((float) $item->priority_score, 2),
                'decision' => str_replace('_', ' ', $item->decision),
                'reason' => $item->override_reason ?: $item->decision_reason,
            ]),
        );
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function annualPlan(array $filters, User $user, bool $approvedOnly): array
    {
        $query = InternalAuditPlan::query();
        $this->planGuard->scopeVisible($query, $user);
        if ($approvedOnly) {
            $query->whereIn('status', ['APPROVED', 'ACTIVE', 'COMPLETED']);
        }
        $plan = isset($filters['planId'])
            ? $query->findOrFail((int) $filters['planId'])
            : $query->where('is_current_revision', true)
                ->latest('fiscal_year')->latest('revision_number')->first();
        $this->requireRecord(
            $plan,
            'planId',
            $approvedOnly
                ? 'No approved annual plan is available.'
                : 'No annual plan is available.',
        );
        $plan->load([
            'preparer:id,name', 'approver:id,name',
            'engagements' => fn ($query) => $query
                ->where('is_active', true)
                ->with([
                    'engagementType', 'auditApproach', 'priority', 'riskLevel',
                    'offices:id,code,name', 'auditAreas:id,code,name',
                    'teamMembers.user:id,name', 'teamMembers.teamRole',
                ]),
        ]);

        return $this->table(
            [
                'Plan' => $plan->plan_code,
                'Fiscal Year' => $plan->fiscal_year,
                'Status' => $plan->status,
                'Revision' => $plan->revision_number,
                'Prepared By' => $plan->preparer?->name,
                'Approved By' => $plan->approver?->name,
                'Approved On' => $plan->approved_at?->format('M j, Y'),
                'Total Person-Days' => number_format(
                    (float) $plan->engagements->sum('estimated_person_days'),
                    2,
                ),
            ],
            [
                ['key' => 'code', 'label' => 'Engagement Code'],
                ['key' => 'engagement', 'label' => 'Proposed Engagement'],
                ['key' => 'office', 'label' => 'Office(s)'],
                ['key' => 'auditArea', 'label' => 'Audit Area(s)'],
                ['key' => 'risk', 'label' => 'Risk / Priority'],
                ['key' => 'objective', 'label' => 'Objective'],
                ['key' => 'scope', 'label' => 'Preliminary Scope'],
                ['key' => 'target', 'label' => 'Target / Dates'],
                ['key' => 'team', 'label' => 'Proposed Team'],
                ['key' => 'personDays', 'label' => 'Person-Days'],
            ],
            $plan->engagements->map(fn ($engagement) => [
                'code' => $engagement->engagement_code,
                'engagement' => $engagement->title,
                'office' => $engagement->offices->pluck('code')->join(', '),
                'auditArea' => $engagement->auditAreas->pluck('name')->join('; '),
                'risk' => collect([
                    $engagement->riskLevel?->label,
                    $engagement->priority?->label,
                ])->filter()->join(' / '),
                'objective' => $engagement->objectives,
                'scope' => $engagement->scope,
                'target' => collect([
                    $engagement->target_quarter ? "Q{$engagement->target_quarter}" : null,
                    $engagement->planned_start_date?->format('M j, Y'),
                    $engagement->planned_end_date?->format('M j, Y'),
                ])->filter()->join(' | '),
                'team' => $engagement->teamMembers->map(
                    fn ($member) => $member->user
                        ? "{$member->user->name} ({$member->teamRole?->label})" : null,
                )->filter()->join('; '),
                'personDays' => number_format((float) $engagement->estimated_person_days, 2),
            ]),
            [[
                'title' => 'Plan Overview',
                'items' => [
                    ['heading' => 'Executive Summary', 'text' => $plan->executive_summary],
                    ['heading' => 'Planning Methodology', 'text' => $plan->planning_methodology],
                    ['heading' => 'Overall Objective', 'text' => $plan->overall_objective],
                    ['heading' => 'Overall Scope', 'text' => $plan->overall_scope],
                    ['heading' => 'Limitations', 'text' => $plan->limitations],
                ],
            ]],
        );
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function annualSchedule(array $filters, User $user): array
    {
        $query = InternalAuditPlan::query();
        $this->planGuard->scopeVisible($query, $user);
        $plan = isset($filters['planId'])
            ? $query->findOrFail((int) $filters['planId'])
            : $query->where('is_current_revision', true)
                ->latest('fiscal_year')
                ->latest('revision_number')
                ->first();
        $this->requireRecord($plan, 'planId', 'No annual plan is available.');
        $engagements = $plan->engagements()
            ->where('is_active', true)
            ->with([
                'offices:id,code,name',
                'teamMembers.user:id,name',
                'teamMembers.teamRole',
            ])
            ->orderBy('planned_start_date')
            ->orderBy('engagement_code')
            ->get();

        return $this->table(
            [
                'Plan' => $plan->plan_code,
                'Fiscal Year' => $plan->fiscal_year,
                'Plan Status' => $plan->status,
                'Scheduled Audits' => $engagements->where('schedule_status', 'SCHEDULED')->count(),
                'Unscheduled Audits' => $engagements->where('schedule_status', 'UNSCHEDULED')->count(),
            ],
            [
                ['key' => 'code', 'label' => 'Engagement Code'],
                ['key' => 'engagement', 'label' => 'Audit Engagement'],
                ['key' => 'office', 'label' => 'Office(s)'],
                ['key' => 'start', 'label' => 'Planned Start'],
                ['key' => 'end', 'label' => 'Planned End'],
                ['key' => 'report', 'label' => 'Expected Report'],
                ['key' => 'teamLeader', 'label' => 'Proposed Team Leader'],
                ['key' => 'team', 'label' => 'Audit Team'],
                ['key' => 'personDays', 'label' => 'Allocated Person-Days'],
                ['key' => 'status', 'label' => 'Schedule Status'],
            ],
            $engagements->map(function ($engagement) {
                $leader = $engagement->teamMembers->first(
                    fn ($member) => $member->teamRole?->code === 'LEAD_AUDITOR',
                );

                return [
                    'code' => $engagement->engagement_code,
                    'engagement' => $engagement->title,
                    'office' => $engagement->offices->pluck('code')->join(', '),
                    'start' => $engagement->planned_start_date?->format('M j, Y'),
                    'end' => $engagement->planned_end_date?->format('M j, Y'),
                    'report' => $engagement->expected_report_date?->format('M j, Y'),
                    'teamLeader' => $leader?->user?->name,
                    'team' => $engagement->teamMembers->pluck('user.name')->filter()->join(', '),
                    'personDays' => number_format(
                        (float) $engagement->teamMembers->sum('planned_person_days'),
                        2,
                    ),
                    'status' => $engagement->schedule_status,
                ];
            }),
        );
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function auditorAllocation(array $filters, User $user): array
    {
        $planQuery = InternalAuditPlan::query();
        $this->planGuard->scopeVisible($planQuery, $user);
        $visiblePlanIds = $planQuery->pluck('id');
        $year = (int) ($filters['fiscalYear']
            ?? InternalAuditPlan::query()
                ->whereIn('id', $visiblePlanIds)
                ->max('fiscal_year')
            ?? app(RuntimeConfiguration::class)->currentFiscalYear());
        $auditors = User::query()
            ->where('is_active', true)
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'iap.assign_team'))
            ->with('role:id,code,name')
            ->orderBy('name')
            ->get();
        $allocations = DB::table('iap_engagement_team_members as team')
            ->join('iap_plan_engagements as engagement', 'engagement.id', '=', 'team.plan_engagement_id')
            ->join('internal_audit_plans as plan', 'plan.id', '=', 'engagement.plan_id')
            ->whereIn('plan.id', $visiblePlanIds)
            ->where('plan.fiscal_year', $year)
            ->where('plan.is_current_revision', true)
            ->where('engagement.schedule_status', 'SCHEDULED')
            ->whereNull('engagement.deleted_at')
            ->groupBy('team.user_id')
            ->selectRaw('team.user_id, SUM(team.planned_person_days) as allocated')
            ->pluck('allocated', 'team.user_id');
        $rows = $auditors->map(function ($auditor) use ($allocations, $year) {
            $available = $this->capacity->capacityFor($year, $auditor->id);
            $allocated = (float) ($allocations[$auditor->id] ?? 0);

            return [
                'employeeId' => $auditor->employee_id,
                'auditor' => $auditor->name,
                'role' => $auditor->role?->name,
                'available' => number_format($available, 2),
                'allocated' => number_format($allocated, 2),
                'remaining' => number_format($available - $allocated, 2),
                'utilization' => ($available > 0
                    ? number_format(($allocated / $available) * 100, 1)
                    : ($allocated > 0 ? '100.0' : '0.0')).'%',
                'status' => $allocated > $available ? 'Overallocated' : 'Within Capacity',
            ];
        });

        return $this->table(
            [
                'Fiscal Year' => $year,
                'Auditors' => $auditors->count(),
                'Available Person-Days' => number_format(
                    (float) $rows->sum(fn ($row) => (float) str_replace(',', '', $row['available'])),
                    2,
                ),
                'Allocated Person-Days' => number_format(
                    (float) $rows->sum(fn ($row) => (float) str_replace(',', '', $row['allocated'])),
                    2,
                ),
            ],
            [
                ['key' => 'employeeId', 'label' => 'Employee ID'],
                ['key' => 'auditor', 'label' => 'Auditor'],
                ['key' => 'role', 'label' => 'Role'],
                ['key' => 'available', 'label' => 'Available Person-Days'],
                ['key' => 'allocated', 'label' => 'Allocated Person-Days'],
                ['key' => 'remaining', 'label' => 'Remaining'],
                ['key' => 'utilization', 'label' => 'Utilization'],
                ['key' => 'status', 'label' => 'Capacity Status'],
            ],
            $rows,
        );
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function revisionHistory(array $filters, User $user): array
    {
        $query = InternalAuditPlan::query();
        $this->planGuard->scopeVisible($query, $user);
        $year = (int) ($filters['fiscalYear'] ?? (clone $query)->max('fiscal_year') ?? app(RuntimeConfiguration::class)->currentFiscalYear());
        $plans = $query
            ->where('fiscal_year', $year)
            ->with([
                'workflowEvents.actor:id,name',
                'preparer:id,name',
                'approver:id,name',
            ])
            ->orderBy('revision_number')
            ->get();
        $this->requireRecord(
            $plans->first(),
            'fiscalYear',
            'No visible annual-plan revisions are available for this fiscal year.',
        );
        $rows = $plans->flatMap(function ($plan) {
            if ($plan->workflowEvents->isEmpty()) {
                return [[
                    'revision' => "R{$plan->revision_number}",
                    'planCode' => $plan->plan_code,
                    'action' => 'CREATED',
                    'statusChange' => "Created as {$plan->status}",
                    'actor' => $plan->preparer?->name,
                    'date' => $plan->created_at?->format('M j, Y g:i A'),
                    'comment' => null,
                ]];
            }

            return $plan->workflowEvents->map(fn ($event) => [
                'revision' => "R{$plan->revision_number}",
                'planCode' => $plan->plan_code,
                'action' => $event->action,
                'statusChange' => collect([$event->from_status, $event->to_status])
                    ->filter()->join(' -> '),
                'actor' => $event->actor?->name,
                'date' => $event->created_at?->format('M j, Y g:i A'),
                'comment' => $event->comment,
            ]);
        })->values();

        return $this->table(
            [
                'Fiscal Year' => $year,
                'Revisions' => $plans->count(),
                'Current Revision' => $plans->firstWhere('is_current_revision', true)?->plan_code,
                'Current Status' => $plans->firstWhere('is_current_revision', true)?->status,
            ],
            [
                ['key' => 'revision', 'label' => 'Revision'],
                ['key' => 'planCode', 'label' => 'Plan Code'],
                ['key' => 'action', 'label' => 'Workflow Action'],
                ['key' => 'statusChange', 'label' => 'Status Change'],
                ['key' => 'actor', 'label' => 'Actor'],
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'comment', 'label' => 'Comment / Explanation'],
            ],
            $rows,
        );
    }

    /** @param array<string, mixed> $filters */
    private function riskPeriod(array $filters): IapRiskPeriod
    {
        $query = IapRiskPeriod::query()->whereIn('status', ['VALIDATED', 'LOCKED']);
        $period = isset($filters['riskPeriodId'])
            ? $query->findOrFail((int) $filters['riskPeriodId'])
            : $query->latest('assessment_year')->first();
        $this->requireRecord($period, 'riskPeriodId', 'No validated risk period is available.');

        return $period;
    }

    private function requireRecord(mixed $record, string $field, string $message): void
    {
        if (! $record) {
            throw ValidationException::withMessages([$field => [$message]]);
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<array{key: string, label: string}>  $columns
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $sections
     * @param  array<string, mixed>|null  $visualization
     * @return array<string, mixed>
     */
    private function table(
        array $meta,
        array $columns,
        Collection $rows,
        array $sections = [],
        ?array $visualization = null,
    ): array {
        return [
            'meta' => collect($meta)
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value, $label) => [
                    'label' => $label,
                    'value' => (string) $value,
                ])->values()->all(),
            'columns' => $columns,
            'rows' => $rows->values()->all(),
            'sections' => $sections,
            'visualization' => $visualization,
        ];
    }
}
