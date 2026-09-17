<?php

namespace App\Services\Cms;

use App\Models\CmsAutomationAction;
use App\Models\CmsAutomationRule;
use App\Models\CmsAutomationRun;
use App\Models\CmsClosureCandidate;
use App\Models\CmsClosureRequestVersion;
use App\Models\CmsDispositionRequestVersion;
use App\Models\CmsEscalation;
use App\Models\CmsEscalationCandidate;
use App\Models\CmsEscalationNoticeVersion;
use App\Models\CmsEscalationResponseVersion;
use App\Models\CmsProgressUpdate;
use App\Models\CmsProgressUpdateVersion;
use App\Models\CmsRecommendationCase;
use App\Models\CmsReopeningRequestVersion;
use App\Models\CmsTargetDateExtensionVersion;
use App\Models\CmsValidationReview;
use App\Models\CmsValidationVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Produces live aggregates from the actor's database-scoped CMS population. */
class CmsDashboardService
{
    public function __construct(
        private readonly CmsRecommendationRegistryService $registry,
        private readonly CmsRecommendationScopeService $scope,
    ) {}

    /** @return array<string, mixed> */
    public function dashboard(User $user): array
    {
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();
        $portfolio = $this->registry->baseQuery($user, 'cms.dashboard.view');
        $base = clone $portfolio;
        $base->whereNotIn('cms_recommendation_cases.status_code', [
            CmsRecommendationCase::STATUS_CLOSED,
            CmsRecommendationCase::STATUS_ACCEPTED_RISK,
            CmsRecommendationCase::STATUS_NO_LONGER_APPLICABLE,
        ]);
        $overdue = $this->registry->applyOverdue(clone $base, $today);
        $visibleCaseIds = (clone $portfolio)->reorder()->pluck('cms_recommendation_cases.id');

        $cards = [
            'totalVisibleCases' => (clone $base)->count(),
            'transferredOpenCases' => (clone $base)
                ->where('cms_recommendation_cases.status_code', 'TRANSFERRED')
                ->count(),
            'assignedCases' => (clone $base)
                ->whereNotNull('cms_current_assignments.id')
                ->count(),
            'unassignedCases' => (clone $base)
                ->whereNull('cms_current_assignments.id')
                ->count(),
            'overdueCases' => (clone $overdue)->count(),
            'withoutTargetDate' => (clone $base)
                ->whereNull('cms_recommendation_cases.effective_target_implementation_date')
                ->count(),
            'transferredThisMonth' => (clone $base)
                ->whereBetween('cms_intakes.transferred_at', [
                    $now->startOfMonth(),
                    $now->endOfMonth(),
                ])->count(),
            'highRiskCases' => (clone $base)
                ->whereIn('cms_intakes.risk_code_snapshot', $this->highRiskCodes())
                ->count(),
            'highRiskOverdueCases' => (clone $overdue)
                ->whereIn('cms_intakes.risk_code_snapshot', $this->highRiskCodes())
                ->count(),
            'monitoringCasesWithoutRecordedProgress' => (clone $base)
                ->where('cms_recommendation_cases.status_code', 'MONITORING')
                ->whereDoesntHave(
                    'progressUpdates',
                    fn (Builder $updates) => $updates->whereNotNull('recorded_version_id'),
                )
                ->count(),
            'progressUpdatesAwaitingReview' => CmsProgressUpdateVersion::query()
                ->whereIn('status_code', ['SUBMITTED', 'UNDER_REVIEW'])
                ->whereHas(
                    'progressUpdate',
                    fn (Builder $updates) => $updates->whereIn(
                        'cms_recommendation_case_id',
                        $visibleCaseIds,
                    ),
                )
                ->count(),
            'recordedProgressUpdates' => CmsProgressUpdate::query()
                ->whereIn('cms_recommendation_case_id', $visibleCaseIds)
                ->whereNotNull('recorded_version_id')
                ->count(),
            'managementReportedCompleteAwaitingValidation' => CmsProgressUpdate::query()
                ->whereIn('cms_recommendation_case_id', $visibleCaseIds)
                ->whereHas(
                    'recordedVersion',
                    fn (Builder $version) => $version
                        ->where('management_reported_overall_percentage', '>=', 100),
                )
                ->count(),
            'casesAwaitingValidationAssignment' => CmsValidationReview::query()
                ->whereIn('cms_recommendation_case_id', $visibleCaseIds)
                ->where('active_slot', 'ACTIVE')
                ->whereDoesntHave('currentAssignment')
                ->count(),
            'activeValidations' => CmsValidationReview::query()
                ->whereIn('cms_recommendation_case_id', $visibleCaseIds)
                ->where('active_slot', 'ACTIVE')
                ->count(),
            'validationsAwaitingSupervisoryReview' => CmsValidationVersion::query()
                ->where('status_code', CmsValidationVersion::STATUS_SUBMITTED)
                ->whereHas(
                    'review',
                    fn (Builder $review) => $review->whereIn(
                        'cms_recommendation_case_id',
                        $visibleCaseIds,
                    ),
                )
                ->count(),
            'returnedValidations' => CmsValidationVersion::query()
                ->where('status_code', CmsValidationVersion::STATUS_RETURNED)
                ->whereHas(
                    'review',
                    fn (Builder $review) => $review->whereIn(
                        'cms_recommendation_case_id',
                        $visibleCaseIds,
                    ),
                )
                ->count(),
            'extensionRequestsInDraft' => CmsTargetDateExtensionVersion::query()
                ->where('status_code', CmsTargetDateExtensionVersion::STATUS_DRAFT)
                ->whereHas('request', fn (Builder $request) => $request->whereIn('cms_recommendation_case_id', $visibleCaseIds))
                ->count(),
            'extensionRequestsAwaitingReview' => CmsTargetDateExtensionVersion::query()
                ->whereIn('status_code', [
                    CmsTargetDateExtensionVersion::STATUS_SUBMITTED,
                    CmsTargetDateExtensionVersion::STATUS_UNDER_REVIEW,
                ])
                ->whereHas('request', fn (Builder $request) => $request->whereIn('cms_recommendation_case_id', $visibleCaseIds))
                ->count(),
            'extensionRequestsAwaitingApproval' => CmsTargetDateExtensionVersion::query()
                ->where('status_code', CmsTargetDateExtensionVersion::STATUS_FOR_APPROVAL)
                ->whereHas('request', fn (Builder $request) => $request->whereIn('cms_recommendation_case_id', $visibleCaseIds))
                ->count(),
            'returnedExtensionRequests' => CmsTargetDateExtensionVersion::query()
                ->where('status_code', CmsTargetDateExtensionVersion::STATUS_RETURNED)
                ->whereHas('request', fn (Builder $request) => $request->whereIn('cms_recommendation_case_id', $visibleCaseIds))
                ->count(),
            'approvedExtensions' => CmsTargetDateExtensionVersion::query()
                ->where('status_code', CmsTargetDateExtensionVersion::STATUS_APPROVED)
                ->whereHas('request', fn (Builder $request) => $request->whereIn('cms_recommendation_case_id', $visibleCaseIds))
                ->count(),
            'rejectedExtensionRequests' => CmsTargetDateExtensionVersion::query()
                ->where('status_code', CmsTargetDateExtensionVersion::STATUS_REJECTED)
                ->whereHas('request', fn (Builder $request) => $request->whereIn('cms_recommendation_case_id', $visibleCaseIds))
                ->count(),
            'recommendationsEligibleForEscalation' => (clone $base)->whereIn('cms_recommendation_cases.status_code', [CmsRecommendationCase::STATUS_MONITORING, CmsRecommendationCase::STATUS_PARTIALLY_IMPLEMENTED])->whereDoesntHave('activeEscalation')->count(),
            'activeEscalations' => CmsEscalation::query()->whereIn('cms_recommendation_case_id', $visibleCaseIds)->whereNull('resolved_at')->count(),
            'noticesAwaitingReview' => CmsEscalationNoticeVersion::query()->where('status_code', CmsEscalationNoticeVersion::STATUS_SUBMITTED)->whereHas('escalation', fn (Builder $query) => $query->whereIn('cms_recommendation_case_id', $visibleCaseIds))->count(),
            'issuedNoticesAwaitingAcknowledgement' => CmsEscalation::query()->whereIn('cms_recommendation_case_id', $visibleCaseIds)->where('operational_status_code', CmsEscalation::STATUS_ISSUED)->whereNotNull('issued_notice_version_id')->whereDoesntHave('acknowledgements')->count(),
            'responsesOverdue' => CmsEscalationResponseVersion::query()->whereIn('status_code', [CmsEscalationResponseVersion::STATUS_SUBMITTED, CmsEscalationResponseVersion::STATUS_UNDER_REVIEW])->whereHas('response', fn (Builder $query) => $query->whereHas('escalation', fn (Builder $escalation) => $escalation->whereIn('cms_recommendation_case_id', $visibleCaseIds)))->whereHas('response.issuedNoticeVersion', fn (Builder $query) => $query->whereDate('response_due_date', '<', $today->toDateString()))->count(),
            'responsesAwaitingReview' => CmsEscalationResponseVersion::query()->where('status_code', CmsEscalationResponseVersion::STATUS_SUBMITTED)->whereHas('response', fn (Builder $query) => $query->whereHas('escalation', fn (Builder $escalation) => $escalation->whereIn('cms_recommendation_case_id', $visibleCaseIds)))->count(),
            'escalationsInFollowUp' => CmsEscalation::query()->whereIn('cms_recommendation_case_id', $visibleCaseIds)->where('operational_status_code', CmsEscalation::STATUS_FOLLOW_UP)->count(),
            'resolvedEscalations' => CmsEscalation::query()->whereIn('cms_recommendation_case_id', $visibleCaseIds)->where('operational_status_code', CmsEscalation::STATUS_RESOLVED)->count(),
            'implementedRecommendationsEligibleForClosure' => (clone $base)->where('cms_recommendation_cases.status_code', CmsRecommendationCase::STATUS_IMPLEMENTED)->whereDoesntHave('unresolvedClosureRequest')->count(),
            'closureRequestsInDraft' => CmsClosureRequestVersion::query()->where('status_code', CmsClosureRequestVersion::DRAFT)->whereHas('request', fn (Builder $query) => $query->whereIn('cms_recommendation_case_id', $visibleCaseIds))->count(),
            'closureRequestsAwaitingReview' => CmsClosureRequestVersion::query()->whereIn('status_code', [CmsClosureRequestVersion::SUBMITTED, CmsClosureRequestVersion::UNDER_REVIEW])->whereHas('request', fn (Builder $query) => $query->whereIn('cms_recommendation_case_id', $visibleCaseIds))->count(),
            'closureRequestsAwaitingDecision' => CmsClosureRequestVersion::query()->where('status_code', CmsClosureRequestVersion::FOR_DECISION)->whereHas('request', fn (Builder $query) => $query->whereIn('cms_recommendation_case_id', $visibleCaseIds))->count(),
            'returnedClosureRequests' => CmsClosureRequestVersion::query()->where('status_code', CmsClosureRequestVersion::RETURNED)->whereHas('request', fn (Builder $query) => $query->whereIn('cms_recommendation_case_id', $visibleCaseIds))->count(),
            'recommendationsEligibleForDisposition' => (clone $base)->whereIn('cms_recommendation_cases.status_code', [CmsRecommendationCase::STATUS_MONITORING, CmsRecommendationCase::STATUS_PARTIALLY_IMPLEMENTED])->whereDoesntHave('unresolvedDispositionRequest')->count(),
            'dispositionRequestsInDraft' => CmsDispositionRequestVersion::query()->where('status_code', CmsDispositionRequestVersion::DRAFT)->whereHas('request', fn (Builder $query) => $query->whereIn('cms_recommendation_case_id', $visibleCaseIds))->count(),
            'dispositionRequestsAwaitingReview' => CmsDispositionRequestVersion::query()->whereIn('status_code', [CmsDispositionRequestVersion::SUBMITTED, CmsDispositionRequestVersion::UNDER_REVIEW])->whereHas('request', fn (Builder $query) => $query->whereIn('cms_recommendation_case_id', $visibleCaseIds))->count(),
            'dispositionRequestsAwaitingDecision' => CmsDispositionRequestVersion::query()->where('status_code', CmsDispositionRequestVersion::FOR_DECISION)->whereHas('request', fn (Builder $query) => $query->whereIn('cms_recommendation_case_id', $visibleCaseIds))->count(),
            'returnedDispositionRequests' => CmsDispositionRequestVersion::query()->where('status_code', CmsDispositionRequestVersion::RETURNED)->whereHas('request', fn (Builder $query) => $query->whereIn('cms_recommendation_case_id', $visibleCaseIds))->count(),
            'acceptedRiskRecommendations' => (clone $portfolio)->where('cms_recommendation_cases.status_code', CmsRecommendationCase::STATUS_ACCEPTED_RISK)->count(),
            'noLongerApplicableRecommendations' => (clone $portfolio)->where('cms_recommendation_cases.status_code', CmsRecommendationCase::STATUS_NO_LONGER_APPLICABLE)->count(),
            'terminalRecommendationsEligibleForReopening' => (clone $portfolio)
                ->whereIn('cms_recommendation_cases.status_code', [CmsRecommendationCase::STATUS_CLOSED, CmsRecommendationCase::STATUS_ACCEPTED_RISK, CmsRecommendationCase::STATUS_NO_LONGER_APPLICABLE])
                ->whereDoesntHave('unresolvedReopeningRequest')
                ->count(),
            'reopeningDrafts' => CmsReopeningRequestVersion::query()->where('status_code', CmsReopeningRequestVersion::DRAFT)->whereHas('request', fn (Builder $query) => $query->whereIn('cms_recommendation_case_id', $visibleCaseIds))->count(),
            'reopeningRequestsAwaitingReview' => CmsReopeningRequestVersion::query()->whereIn('status_code', [CmsReopeningRequestVersion::SUBMITTED, CmsReopeningRequestVersion::UNDER_REVIEW])->whereHas('request', fn (Builder $query) => $query->whereIn('cms_recommendation_case_id', $visibleCaseIds))->count(),
            'reopeningRequestsAwaitingDecision' => CmsReopeningRequestVersion::query()->where('status_code', CmsReopeningRequestVersion::FOR_DECISION)->whereHas('request', fn (Builder $query) => $query->whereIn('cms_recommendation_case_id', $visibleCaseIds))->count(),
            'returnedReopeningRequests' => CmsReopeningRequestVersion::query()->where('status_code', CmsReopeningRequestVersion::RETURNED)->whereHas('request', fn (Builder $query) => $query->whereIn('cms_recommendation_case_id', $visibleCaseIds))->count(),
            'rejectedReopeningRequests' => CmsReopeningRequestVersion::query()->where('status_code', CmsReopeningRequestVersion::REJECTED)->whereHas('request', fn (Builder $query) => $query->whereIn('cms_recommendation_case_id', $visibleCaseIds))->count(),
            'recentlyReopenedRecommendations' => (clone $portfolio)->whereNotNull('cms_recommendation_cases.last_reopened_at')->where('cms_recommendation_cases.last_reopened_at', '>=', $now->copy()->subDays(30))->count(),
            'recentlyClosedRecommendations' => CmsRecommendationCase::query()->whereIn('id', $visibleCaseIds)->where('status_code', CmsRecommendationCase::STATUS_CLOSED)->where('closed_at', '>=', $now->copy()->subDays(30))->count(),
            'openClosureCandidates' => CmsClosureCandidate::query()->whereIn('cms_recommendation_case_id', $visibleCaseIds)->whereIn('status_code', [CmsClosureCandidate::OPEN, CmsClosureCandidate::ACKNOWLEDGED])->count(),
            'openEscalationCandidates' => CmsEscalationCandidate::query()->whereIn('cms_recommendation_case_id', $visibleCaseIds)->whereIn('status_code', [CmsEscalationCandidate::OPEN, CmsEscalationCandidate::ACKNOWLEDGED])->count(),
            'recentAutomationReminders' => CmsAutomationAction::query()->whereIn('cms_recommendation_case_id', $visibleCaseIds)->where('action_type', 'REMINDER')->where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'activeAutomationRules' => CmsAutomationRule::query()->where('status_code', CmsAutomationRule::ACTIVE)->count(),
            'lastAutomationRunAt' => CmsAutomationRun::query()->latest('finished_at')->value('finished_at'),
            'totalClosedRecommendations' => CmsRecommendationCase::query()->whereIn('id', $visibleCaseIds)->where('status_code', CmsRecommendationCase::STATUS_CLOSED)->count(),
            'finalizedValidationConclusions' => collect([
                'NOT_IMPLEMENTED',
                'PARTIALLY_IMPLEMENTED',
                'IMPLEMENTED',
                'INADEQUATE_BASIS',
            ])->mapWithKeys(
                fn (string $conclusion): array => [
                    $conclusion => CmsValidationVersion::query()
                        ->where('status_code', CmsValidationVersion::STATUS_FINALIZED)
                        ->where('final_conclusion_code', $conclusion)
                        ->whereHas(
                            'review',
                            fn (Builder $review) => $review->whereIn(
                                'cms_recommendation_case_id',
                                $visibleCaseIds,
                            ),
                        )
                        ->count(),
                ],
            )->all(),
        ];

        return [
            'evaluationDateTime' => $now->toISOString(),
            'evaluationDate' => $today->toDateString(),
            'scope' => $this->scope->summary($user),
            'cards' => $cards,
            'groups' => [
                'byResponsibleOffice' => $this->group(
                    clone $base,
                    'cms_recommendation_cases.lead_responsible_office_id',
                    "COALESCE(cms_offices.code, 'UNASSIGNED')",
                    "COALESCE(cms_offices.name, 'No responsible office')",
                ),
                'byRiskLevel' => $this->group(
                    clone $base,
                    'cms_intakes.risk_rating_id',
                    "COALESCE(cms_intakes.risk_code_snapshot, 'UNRATED')",
                    "COALESCE(cms_intakes.risk_label_snapshot, 'Unrated')",
                ),
                'byConfidentialityLevel' => $this->group(
                    clone $base,
                    'cms_intakes.confidentiality_level_id',
                    "COALESCE(cms_intakes.confidentiality_code_snapshot, 'INTERNAL')",
                    "COALESCE(cms_intakes.confidentiality_label_snapshot, 'Internal')",
                ),
                'byAssignedMonitor' => $this->group(
                    clone $base,
                    'cms_current_assignments.user_id',
                    "COALESCE(cms_monitor_users.employee_id, 'UNASSIGNED')",
                    "COALESCE(cms_monitor_users.name, 'Unassigned')",
                ),
            ],
            'recentlyTransferred' => $this->records(
                (clone $base)
                    ->with($this->summaryRelations())
                    ->orderByDesc('cms_intakes.transferred_at')
                    ->orderByDesc('cms_recommendation_cases.id')
                    ->limit(10)
                    ->get(),
                $today,
            ),
            'oldestUnresolvedTargetDates' => $this->records(
                (clone $base)
                    ->with($this->summaryRelations())
                    ->whereNotNull(
                        'cms_recommendation_cases.effective_target_implementation_date',
                    )
                    ->whereNotIn('cms_recommendation_cases.status_code', [
                        'CLOSED', 'ACCEPTED_RISK', 'NO_LONGER_APPLICABLE', 'CANCELLED',
                    ])
                    ->orderBy(
                        'cms_recommendation_cases.effective_target_implementation_date',
                    )
                    ->orderBy('cms_recommendation_cases.id')
                    ->limit(10)
                    ->get(),
                $today,
            ),
            'dueSoon' => [
                'available' => false,
                'reason' => 'No approved CMS due-soon runtime threshold is configured.',
            ],
            'dataLimitations' => [
                'Due-soon metrics require an approved runtime threshold.',
                'Progress metrics remain management-reported until a separate Validation Review is finalized.',
                'Automation identifies readiness, sends reminders, and prepares candidates; professional decisions and formal notices remain manual.',
            ],
        ];
    }

    /**
     * @return list<array{id: int|null, code: string, label: string, count: int}>
     */
    private function group(
        Builder $query,
        string $idColumn,
        string $codeExpression,
        string $labelExpression,
    ): array {
        return $query
            ->reorder()
            ->select([
                DB::raw("{$idColumn} as group_id"),
                DB::raw("{$codeExpression} as group_code"),
                DB::raw("{$labelExpression} as group_label"),
                DB::raw('COUNT(*) as aggregate_count'),
            ])
            ->groupByRaw("{$idColumn}, {$codeExpression}, {$labelExpression}")
            ->orderByDesc('aggregate_count')
            ->orderBy('group_label')
            ->get()
            ->map(fn (object $row): array => [
                'id' => $row->group_id === null ? null : (int) $row->group_id,
                'code' => (string) $row->group_code,
                'label' => (string) $row->group_label,
                'count' => (int) $row->aggregate_count,
            ])->all();
    }

    /**
     * @param  iterable<int, CmsRecommendationCase>  $cases
     * @return list<array<string, mixed>>
     */
    private function records(iterable $cases, CarbonImmutable $today): array
    {
        return collect($cases)->map(function (CmsRecommendationCase $case) use ($today): array {
            $target = $case->effective_target_implementation_date;

            return [
                'id' => $case->id,
                'cmsRecommendationCode' => sprintf('CMS-REC-%06d', $case->id),
                'recommendationCode' => $case->recommendation->recommendation_code,
                'status' => $case->status_code,
                'transferredAt' => $case->recommendation->transferred_at?->toISOString(),
                'effectiveTargetDate' => $target?->toDateString(),
                'isOverdue' => $target !== null
                    && $target->lt($today)
                    && ! in_array($case->status_code, [
                        'CLOSED', 'ACCEPTED_RISK', 'CANCELLED',
                    ], true),
                'responsibleOffice' => $case->leadResponsibleOffice?->only([
                    'id', 'code', 'name',
                ]),
                'risk' => [
                    'code' => $case->recommendation->risk_code_snapshot,
                    'label' => $case->recommendation->risk_label_snapshot,
                ],
                'assignedMonitor' => $case->currentAssignment?->user ? [
                    'id' => $case->currentAssignment->user->id,
                    'employeeId' => $case->currentAssignment->user->employee_id,
                    'name' => $case->currentAssignment->user->name,
                ] : null,
            ];
        })->values()->all();
    }

    /** @return list<string> */
    private function highRiskCodes(): array
    {
        return ['HIGH', 'VERY_HIGH', 'CRITICAL'];
    }

    /** @return list<string> */
    private function summaryRelations(): array
    {
        return [
            'recommendation',
            'leadResponsibleOffice',
            'currentAssignment.user',
        ];
    }
}
