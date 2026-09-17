<?php

namespace App\Services\Cms;

use App\Models\CmsRecommendationAssignment;
use App\Models\CmsRecommendationCase;
use App\Models\User;
use App\Services\RuntimeConfiguration;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** Builds the scoped, searchable, filterable CMS recommendation registry. */
class CmsRecommendationRegistryService
{
    /** @var array<string, string> */
    private const SORTS = [
        'recommendationCode' => 'cms_intakes.recommendation_code',
        'transferredAt' => 'cms_intakes.transferred_at',
        'targetDate' => 'cms_recommendation_cases.effective_target_implementation_date',
        'responsibleOffice' => 'cms_offices.name',
        'risk' => 'cms_intakes.risk_code_snapshot',
        'status' => 'cms_recommendation_cases.status_code',
        'assignedMonitor' => 'cms_monitor_users.name',
    ];

    /** @var list<string> */
    private const TERMINAL_STATUSES = ['CLOSED', 'ACCEPTED_RISK', 'NO_LONGER_APPLICABLE', 'CANCELLED'];

    public function __construct(
        private readonly CmsRecommendationScopeService $scope,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    /** @param array<string, mixed> $filters */
    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $today = CarbonImmutable::today();
        $query = $this->baseQuery($user)
            ->with([
                'recommendation.confidentialityLevel',
                'recommendation.riskRating',
                'leadResponsibleOffice',
                'currentAssignment.user',
            ]);

        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $query->when($search !== '', function (Builder $query) use ($search): void {
            $pattern = "%{$search}%";
            $cmsId = preg_match('/^cms-rec-0*(\d+)$/i', $search, $match)
                ? (int) $match[1]
                : null;
            $query->where(function (Builder $searchQuery) use ($pattern, $cmsId): void {
                if ($cmsId) {
                    $searchQuery->whereKey($cmsId);
                }
                $searchQuery
                    ->orWhereRaw('LOWER(cms_intakes.recommendation_code) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(cms_intakes.report_code_snapshot) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(cms_offices.code, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(cms_offices.name, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(cms_monitor_users.name, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(cms_monitor_users.employee_id, \'\')) LIKE ?', [$pattern])
                    ->orWhereHas('recommendation.sourceRecommendation', fn (Builder $source) => $source
                        ->whereRaw('LOWER(recommendation) LIKE ?', [$pattern]))
                    ->orWhereHas('recommendation.finding', fn (Builder $finding) => $finding
                        ->whereRaw('LOWER(finding_code) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(title) LIKE ?', [$pattern]))
                    ->orWhereHas('recommendation.engagement', fn (Builder $engagement) => $engagement
                        ->whereRaw('LOWER(engagement_code) LIKE ?', [$pattern]));
            });
        });

        $query
            ->when(isset($filters['status']), fn (Builder $query) => $query
                ->where('cms_recommendation_cases.status_code', $filters['status']))
            ->when(isset($filters['officeId']), fn (Builder $query) => $query
                ->where('cms_recommendation_cases.lead_responsible_office_id', $filters['officeId']))
            ->when(isset($filters['risk']), fn (Builder $query) => $query
                ->where('cms_intakes.risk_code_snapshot', strtoupper($filters['risk'])))
            ->when(isset($filters['confidentiality']), fn (Builder $query) => $query
                ->where(
                    'cms_intakes.confidentiality_code_snapshot',
                    strtoupper($filters['confidentiality']),
                ))
            ->when(isset($filters['monitorId']), fn (Builder $query) => $query
                ->where('cms_current_assignments.user_id', $filters['monitorId']))
            ->when(array_key_exists('assigned', $filters), fn (Builder $query) => $filters['assigned']
                ? $query->whereNotNull('cms_current_assignments.id')
                : $query->whereNull('cms_current_assignments.id'))
            ->when(array_key_exists('hasTargetDate', $filters), fn (Builder $query) => $filters['hasTargetDate']
                ? $query->whereNotNull('cms_recommendation_cases.effective_target_implementation_date')
                : $query->whereNull('cms_recommendation_cases.effective_target_implementation_date'))
            ->when(array_key_exists('overdue', $filters), function (Builder $query) use ($filters, $today): void {
                if ($filters['overdue']) {
                    $this->applyOverdue($query, $today);
                } else {
                    $query->where(function (Builder $notOverdue) use ($today): void {
                        $notOverdue
                            ->whereNull('cms_recommendation_cases.effective_target_implementation_date')
                            ->orWhere(
                                'cms_recommendation_cases.effective_target_implementation_date',
                                '>=',
                                $today->toDateString(),
                            )
                            ->orWhereIn(
                                'cms_recommendation_cases.status_code',
                                self::TERMINAL_STATUSES,
                            );
                    });
                }
            })
            ->when(isset($filters['transferredFrom']), fn (Builder $query) => $query
                ->whereDate('cms_intakes.transferred_at', '>=', $filters['transferredFrom']))
            ->when(isset($filters['transferredTo']), fn (Builder $query) => $query
                ->whereDate('cms_intakes.transferred_at', '<=', $filters['transferredTo']))
            ->when(isset($filters['targetFrom']), fn (Builder $query) => $query
                ->whereDate(
                    'cms_recommendation_cases.effective_target_implementation_date',
                    '>=',
                    $filters['targetFrom'],
                ))
            ->when(isset($filters['targetTo']), fn (Builder $query) => $query
                ->whereDate(
                    'cms_recommendation_cases.effective_target_implementation_date',
                    '<=',
                    $filters['targetTo'],
                ));

        $sort = self::SORTS[$filters['sortBy'] ?? 'transferredAt'];

        return $query
            ->orderBy($sort, $filters['sortDirection'] ?? 'desc')
            ->orderByDesc('cms_recommendation_cases.id')
            ->paginate((int) ($filters['perPage'] ?? $this->runtime->paginationSize()))
            ->withQueryString();
    }

    /** @return array<string, mixed> */
    public function filterOptions(User $user): array
    {
        $base = $this->baseQuery($user);

        return [
            'statuses' => (clone $base)
                ->reorder()
                ->distinct()
                ->orderBy('cms_recommendation_cases.status_code')
                ->pluck('cms_recommendation_cases.status_code')
                ->values(),
            'responsibleOffices' => (clone $base)
                ->reorder()
                ->whereNotNull('cms_offices.id')
                ->select([
                    'cms_offices.id',
                    'cms_offices.code',
                    'cms_offices.name',
                ])
                ->distinct()
                ->orderBy('cms_offices.name')
                ->get(),
            'riskLevels' => (clone $base)
                ->reorder()
                ->whereNotNull('cms_intakes.risk_code_snapshot')
                ->selectRaw('cms_intakes.risk_rating_id as id')
                ->selectRaw('cms_intakes.risk_code_snapshot as code')
                ->selectRaw('cms_intakes.risk_label_snapshot as label')
                ->distinct()
                ->orderBy('label')
                ->get(),
            'confidentialityLevels' => (clone $base)
                ->reorder()
                ->whereNotNull('cms_intakes.confidentiality_code_snapshot')
                ->selectRaw('cms_intakes.confidentiality_level_id as id')
                ->selectRaw('cms_intakes.confidentiality_code_snapshot as code')
                ->selectRaw('cms_intakes.confidentiality_label_snapshot as label')
                ->distinct()
                ->orderBy('label')
                ->get(),
            'assignedMonitors' => (clone $base)
                ->reorder()
                ->whereNotNull('cms_monitor_users.id')
                ->select([
                    'cms_monitor_users.id',
                    'cms_monitor_users.employee_id',
                    'cms_monitor_users.name',
                ])
                ->distinct()
                ->orderBy('cms_monitor_users.name')
                ->get()
                ->map(fn (object $user): array => [
                    'id' => (int) $user->id,
                    'employeeId' => $user->employee_id,
                    'name' => $user->name,
                ])->values(),
        ];
    }

    public function baseQuery(User $user, string $permission = 'cms.recommendation.view'): Builder
    {
        $query = CmsRecommendationCase::query()
            ->select('cms_recommendation_cases.*')
            ->join(
                'cms_recommendations as cms_intakes',
                'cms_intakes.id',
                '=',
                'cms_recommendation_cases.cms_recommendation_id',
            )
            ->leftJoin(
                'offices as cms_offices',
                'cms_offices.id',
                '=',
                'cms_recommendation_cases.lead_responsible_office_id',
            )
            ->leftJoin(
                'cms_recommendation_assignments as cms_current_assignments',
                function ($join): void {
                    $join
                        ->on(
                            'cms_current_assignments.cms_recommendation_case_id',
                            '=',
                            'cms_recommendation_cases.id',
                        )
                        ->where('cms_current_assignments.is_current', true)
                        ->where(
                            'cms_current_assignments.assignment_role_code',
                            CmsRecommendationAssignment::ROLE_COMPLIANCE_MONITOR,
                        )
                        ->where(function ($join): void {
                            $join
                                ->whereNull('cms_current_assignments.effective_from')
                                ->orWhere(
                                    'cms_current_assignments.effective_from',
                                    '<=',
                                    now(),
                                );
                        })
                        ->where(function ($join): void {
                            $join
                                ->whereNull('cms_current_assignments.effective_until')
                                ->orWhere(
                                    'cms_current_assignments.effective_until',
                                    '>',
                                    now(),
                                );
                        });
                },
            )
            ->leftJoin(
                'users as cms_monitor_users',
                'cms_monitor_users.id',
                '=',
                'cms_current_assignments.user_id',
            );

        return $this->scope->visibleCases($query, $user, $permission);
    }

    public function applyOverdue(Builder $query, CarbonImmutable $today): Builder
    {
        return $query
            ->whereNotNull('cms_recommendation_cases.effective_target_implementation_date')
            ->where(
                'cms_recommendation_cases.effective_target_implementation_date',
                '<',
                $today->toDateString(),
            )
            ->whereNotIn(
                'cms_recommendation_cases.status_code',
                self::TERMINAL_STATUSES,
            );
    }
}
