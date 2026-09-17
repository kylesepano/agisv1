<?php

namespace App\Services;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditProgramProcedure;
use App\Models\AuditReport;
use App\Models\AemsCompletionTransferException;
use App\Models\AemsEngagementTask;
use App\Models\AemsEscalationCandidate;
use App\Models\AemsEvidenceAssessment;
use App\Models\AemsEvidenceRequest;
use App\Models\AemsReviewNote;
use App\Models\EntryConference;
use App\Models\ExitConference;
use App\Models\Office;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\WorkingPaper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Derives portfolio metrics and engagement progress from the source workflow
 * records. No dashboard-owned state is persisted.
 */
class AemsDashboardService
{
    public function __construct(
        private readonly AemsIntegrationStatusService $integrations,
        private readonly ResourcePlanningGateway $resources,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    private const ACTIVE_STATUSES = [
        'DRAFT',
        'AUTHORIZATION_PREPARATION',
        'RETURNED_FOR_REVISION',
        'AUTHORIZED',
        'ENGAGEMENT_PLANNING',
        'ENTRY_CONFERENCE',
        'FIELDWORK',
        'FINDINGS_COMMUNICATION',
        'EXIT_CONFERENCE',
        'REPORTING',
        'ISSUED',
        'CLOSURE_REVIEW',
        'SUSPENDED',
    ];

    private const PLANNING_STATUSES = [
        'DRAFT',
        'AUTHORIZATION_PREPARATION',
        'RETURNED_FOR_REVISION',
        'AUTHORIZED',
        'ENGAGEMENT_PLANNING',
    ];

    private const REVIEW_STATUSES = ['PENDING_REVIEW', 'RESUBMITTED'];

    /** @param array<string, mixed> $filters */
    public function dashboard(User $user, array $filters): array
    {
        $today = CarbonImmutable::today();
        $query = $this->filteredEngagements($user, $filters)
            ->with($this->trackerRelations());

        $engagements = $query
            ->orderBy(
                $filters['sortBy'] ?? 'updated_at',
                $filters['sortDirection'] ?? 'desc',
            )
            ->paginate((int) ($filters['perPage'] ?? 10))
            ->withQueryString();

        // Work queues already calculate the access-scoped counts used by most
        // dashboard cards. Build them once and reuse those counts instead of
        // issuing a second count query for every queue (the previous ordering
        // made one dashboard request execute the same filters twice).
        $workQueues = $this->workQueues($user, $today);

        return [
            'asOf' => now()->toIso8601String(),
            'cards' => $this->cards($user, $today, $workQueues),
            'phaseCounts' => $this->phaseCounts($user),
            'workQueues' => $workQueues,
            'notifications' => $this->notificationSummary($user),
            'reminderRules' => $this->reminderRules(),
            'engagements' => $engagements->getCollection()
                ->map(fn (AuditEngagement $engagement) => $this->engagementData(
                    $engagement,
                    $today,
                ))
                ->values(),
            'pagination' => [
                'currentPage' => $engagements->currentPage(),
                'lastPage' => $engagements->lastPage(),
                'perPage' => $engagements->perPage(),
                'total' => $engagements->total(),
                'from' => $engagements->firstItem(),
                'to' => $engagements->lastItem(),
            ],
            'filters' => [
                'statuses' => AuditEngagement::STATUSES,
                'phases' => array_keys(self::PHASE_STATUSES),
                'offices' => $this->visibleOffices($user),
            ],
            'integrations' => $this->integrations->status($user),
            'capabilities' => [
                'canExport' => $user->hasPermission('aems.engagement.export'),
            ],
        ];
    }

    /**
     * Builds the access-scoped operational Engagement Progress Report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function portfolioReport(User $user, array $filters): array
    {
        $today = CarbonImmutable::today();
        $engagements = $this->filteredEngagements($user, $filters)
            ->with($this->trackerRelations())
            ->orderBy(
                $filters['sortBy'] ?? 'updated_at',
                $filters['sortDirection'] ?? 'desc',
            )
            ->get()
            ->map(fn (AuditEngagement $engagement): array => $this->engagementData(
                $engagement,
                $today,
            ));

        return [
            'title' => 'AEMS Engagement Progress Report',
            'description' => 'Access-scoped portfolio progress, workflow health, alerts, and closure readiness.',
            'generatedAt' => now()->toIso8601String(),
            'fileName' => 'aems-engagement-progress-'.now()->format('Ymd-His'),
            'columns' => [
                ['key' => 'engagementCode', 'label' => 'Engagement Code'],
                ['key' => 'title', 'label' => 'Title'],
                ['key' => 'offices', 'label' => 'Auditee Offices'],
                ['key' => 'status', 'label' => 'Engagement Status'],
                ['key' => 'health', 'label' => 'Health'],
                ['key' => 'overallProgress', 'label' => 'Overall Progress'],
                ['key' => 'currentStage', 'label' => 'Current Stage'],
                ['key' => 'plannedEndDate', 'label' => 'Planned End'],
                ['key' => 'expectedReportDate', 'label' => 'Expected Report'],
                ['key' => 'alerts', 'label' => 'Alerts'],
                ['key' => 'closureReady', 'label' => 'Ready for Closure'],
                ['key' => 'closureBlockers', 'label' => 'Closure Blockers'],
            ],
            'rows' => $engagements->map(function (array $engagement): array {
                $currentStage = collect($engagement['stages'])
                    ->first(fn (array $stage): bool => $stage['percent'] < 100);

                return [
                    'engagementCode' => $engagement['engagementCode'],
                    'title' => $engagement['title'],
                    'offices' => collect($engagement['offices'])->pluck('name')->implode('; '),
                    'status' => $this->statusLabel($engagement['status']),
                    'health' => $this->statusLabel($engagement['health']),
                    'overallProgress' => "{$engagement['overallProgress']}%",
                    'currentStage' => $currentStage['label'] ?? 'Complete',
                    'plannedEndDate' => $engagement['plannedEndDate'],
                    'expectedReportDate' => $engagement['expectedReportDate'],
                    'alerts' => collect($engagement['alerts'])->implode('; '),
                    'closureReady' => $engagement['closure']['isReady'] ? 'Yes' : 'No',
                    'closureBlockers' => collect($engagement['closure']['blockers'])->implode('; '),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Builds a protected, access-scoped snapshot of the current operational
     * queues. Queue rows are intentionally generated on the backend so an
     * export cannot expose records hidden from the dashboard actor.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function workQueueReport(User $user, array $filters = []): array
    {
        $dashboard = $this->dashboard($user, [
            ...$filters,
            'page' => 1,
            'perPage' => 1,
        ]);
        $rows = collect($dashboard['workQueues'])->flatMap(
            fn (array $queue): Collection => collect($queue['items'] ?? [])->map(fn (array $item): array => [
                'queue' => $queue['label'],
                'code' => $item['code'] ?? '',
                'title' => $item['title'] ?? '',
                'status' => $item['status'] ?? '',
                'dueAt' => $item['dueAt'] ?? '',
                'engagement' => $item['engagement']['code'] ?? '',
            ]),
        )->values();

        return [
            'title' => 'AEMS Operational Work Queues',
            'description' => 'Access-scoped queue items currently requiring review or action.',
            'generatedAt' => now()->toIso8601String(),
            'fileName' => 'aems-work-queues-'.now()->format('Ymd-His'),
            'columns' => [
                ['key' => 'queue', 'label' => 'Queue'],
                ['key' => 'code', 'label' => 'Record Code'],
                ['key' => 'title', 'label' => 'Title or Reason'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'dueAt', 'label' => 'Due'],
                ['key' => 'engagement', 'label' => 'Engagement'],
            ],
            'rows' => $rows->all(),
        ];
    }

    /** @param array<string, array<string, mixed>> $workQueues */
    private function cards(User $user, CarbonImmutable $today, array $workQueues = []): array
    {
        $visible = fn (): Builder => $this->visibleEngagements($user);
        $relatedToVisible = fn (Builder $engagement): Builder => $engagement
            ->visibleTo($user)
            ->where('is_active', true);
        $upcomingEnd = $today->addDays(30)->endOfDay();
        $queueCount = static fn (string $key, callable $fallback): int => isset($workQueues[$key]['count'])
            ? (int) $workQueues[$key]['count']
            : (int) $fallback();

        return [
            'activeEngagements' => $visible()
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->count(),
            'engagementsInPlanning' => $visible()
                ->whereIn('status', self::PLANNING_STATUSES)
                ->count(),
            'engagementsInFieldwork' => $visible()
                ->where('status', 'FIELDWORK')
                ->count(),
            'overdueProcedures' => $queueCount('overdueProcedures', fn (): int => AuditProgramProcedure::query()
                ->whereDate('target_date', '<', $today->toDateString())
                ->whereNotIn('status', ['COMPLETED', 'WAIVED'])
                ->whereHas('program.engagement', $relatedToVisible)
                ->count()),
            'workingPapersAwaitingReview' => $queueCount('workingPapersAwaitingReview', fn (): int => WorkingPaper::query()
                ->whereIn('status', ['SUBMITTED', 'RESUBMITTED'])
                ->whereHas('engagement', $relatedToVisible)
                ->count()),
            'findingsAwaitingResponse' => $queueCount('findingsAwaitingManagementResponse', fn (): int => AuditFinding::query()
                ->where('is_current_revision', true)
                ->whereIn('status', ['COMMUNICATED', 'AWAITING_MANAGEMENT_RESPONSE'])
                ->whereHas('engagement', $relatedToVisible)
                ->count()),
            'upcomingExitConferences' => isset($workQueues['upcomingConferences']['exitCount'])
                ? (int) $workQueues['upcomingConferences']['exitCount']
                : ExitConference::query()
                    ->whereIn('status', ['SCHEDULED', 'RESCHEDULED'])
                    ->whereBetween('scheduled_start_at', [$today->startOfDay(), $upcomingEnd])
                    ->whereHas('engagement', $relatedToVisible)
                    ->count(),
            'reportsPendingApproval' => $queueCount('reportsPendingApproval', fn (): int => AuditReport::query()
                ->whereIn('status', self::REVIEW_STATUSES)
                ->whereHas('engagement', $relatedToVisible)
                ->count()),
            'engagementsReadyForClosure' => $this->readyForClosureQuery($user)->count(),
            'evidenceRequestsAwaitingResponse' => $queueCount('evidenceRequestsAwaitingResponse', fn (): int => AemsEvidenceRequest::query()
                ->whereIn('status', ['SENT', 'PARTIALLY_RECEIVED'])
                ->whereHas('engagement', $relatedToVisible)
                ->count()),
            'evidenceGaps' => $queueCount('evidenceGaps', fn (): int => $this->evidenceGapQuery($user, $relatedToVisible)->count()),
            'findingsAwaitingReview' => $queueCount('findingsAwaitingReview', fn (): int => AuditFinding::query()
                ->where('is_current_revision', true)
                ->whereIn('status', self::REVIEW_STATUSES)
                ->whereHas('engagement', $relatedToVisible)
                ->count()),
            'findingsAwaitingManagementResponse' => $queueCount('findingsAwaitingManagementResponse', fn (): int => AuditFinding::query()
                ->where('is_current_revision', true)
                ->whereIn('status', ['COMMUNICATED', 'AWAITING_MANAGEMENT_RESPONSE'])
                ->whereHas('engagement', $relatedToVisible)
                ->count()),
            'upcomingConferences' => $this->upcomingConferenceQuery($user, $today)->count()
                + EntryConference::query()
                    ->whereIn('status', ['SCHEDULED', 'RESCHEDULED'])
                    ->whereBetween('scheduled_start_at', [$today->startOfDay(), $upcomingEnd])
                    ->whereHas('engagement', $relatedToVisible)
                    ->count(),
            'cmsTransferExceptions' => AemsCompletionTransferException::query()
                ->where('status', 'OPEN')
                ->whereHas('manifest.engagement', $relatedToVisible)
                ->count(),
            'reviewNotesAwaitingReview' => AemsReviewNote::query()
                ->where('is_current_revision', true)
                ->where('status', 'DRAFT')
                ->whereHas('engagement', $relatedToVisible)
                ->count(),
            'openTasks' => AemsEngagementTask::query()
                ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
                ->whereHas('engagement', $relatedToVisible)
                ->count(),
            'escalationCandidates' => AemsEscalationCandidate::query()
                ->whereIn('status', ['OPEN', 'ACKNOWLEDGED'])
                ->whereHas('engagement', $relatedToVisible)
                ->count(),
        ];
    }

    private const PHASE_STATUSES = [
        'planning' => ['DRAFT', 'AUTHORIZATION_PREPARATION', 'RETURNED_FOR_REVISION', 'AUTHORIZED', 'ENGAGEMENT_PLANNING', 'ENTRY_CONFERENCE'],
        'fieldwork' => ['FIELDWORK', 'FINDINGS_COMMUNICATION'],
        'reporting' => ['REPORTING', 'ISSUED'],
        'closure' => ['CLOSURE_REVIEW', 'CLOSED'],
    ];

    /** @return list<array<string, mixed>> */
    private function phaseCounts(User $user): array
    {
        $visible = $this->visibleEngagements($user);
        $counts = collect(self::PHASE_STATUSES)->mapWithKeys(
            fn (array $statuses, string $phase): array => [$phase => (clone $visible)->whereIn('status', $statuses)->count()],
        );
        $known = collect(self::PHASE_STATUSES)->flatten()->unique();

        return collect([
            ['key' => 'planning', 'label' => 'Planning'],
            ['key' => 'fieldwork', 'label' => 'Fieldwork'],
            ['key' => 'reporting', 'label' => 'Reporting'],
            ['key' => 'closure', 'label' => 'Closure'],
            ['key' => 'other', 'label' => 'Other'],
        ])->map(function (array $phase) use ($counts, $known, $visible): array {
            $count = $phase['key'] === 'other'
                ? (clone $visible)->whereNotIn('status', $known->all())->count()
                : (int) ($counts[$phase['key']] ?? 0);

            return [...$phase, 'count' => $count];
        })->all();
    }

    /** @return array<string, array<string, mixed>> */
    private function workQueues(User $user, CarbonImmutable $today): array
    {
        $visible = fn (Builder $engagement): Builder => $engagement
            ->visibleTo($user)
            ->where('is_active', true);
        $queue = function (Builder $query, string $key, string $label, string $route, callable $map): array {
            $count = (clone $query)->count();
            $items = (clone $query)->limit(6)->get()->map($map)->values()->all();

            return compact('key', 'label', 'route', 'count', 'items');
        };

        $procedureQueue = AuditProgramProcedure::query()
            ->whereDate('target_date', '<', $today->toDateString())
            ->whereNotIn('status', ['COMPLETED', 'WAIVED'])
            ->whereHas('program.engagement', $visible)
            ->with('program.engagement:id,engagement_code,title')
            ->orderBy('target_date');
        $paperQueue = WorkingPaper::query()
            ->whereIn('status', ['SUBMITTED', 'RESUBMITTED'])
            ->whereHas('engagement', $visible)
            ->with('engagement:id,engagement_code,title')
            ->latest('updated_at');
        $evidenceQueue = AemsEvidenceRequest::query()
            ->whereIn('status', ['SENT', 'PARTIALLY_RECEIVED'])
            ->whereHas('engagement', $visible)
            ->with('engagement:id,engagement_code,title')
            ->orderBy('due_date');
        $gapQueue = $this->evidenceGapQuery($user, $visible)
            ->with(['engagement:id,engagement_code,title', 'evidence:id,evidence_code,title'])
            ->latest('assessed_at');
        $reviewFindingQueue = AuditFinding::query()
            ->where('is_current_revision', true)
            ->whereIn('status', self::REVIEW_STATUSES)
            ->whereHas('engagement', $visible)
            ->with('engagement:id,engagement_code,title')
            ->latest('updated_at');
        $responseQueue = AuditFinding::query()
            ->where('is_current_revision', true)
            ->whereIn('status', ['COMMUNICATED', 'AWAITING_MANAGEMENT_RESPONSE'])
            ->whereHas('engagement', $visible)
            ->with('engagement:id,engagement_code,title')
            ->orderBy('management_response_due_date');
        $reportQueue = AuditReport::query()
            ->whereIn('status', self::REVIEW_STATUSES)
            ->whereHas('engagement', $visible)
            ->with('engagement:id,engagement_code,title')
            ->latest('updated_at');
        $exceptionQueue = AemsCompletionTransferException::query()
            ->where('status', 'OPEN')
            ->whereHas('manifest.engagement', $visible)
            ->with('manifest.engagement:id,engagement_code,title')
            ->latest('created_at');
        $noteQueue = AemsReviewNote::query()
            ->where('is_current_revision', true)
            ->where('status', 'DRAFT')
            ->whereHas('engagement', $visible)
            ->with('engagement:id,engagement_code,title')
            ->latest('updated_at');
        $taskQueue = AemsEngagementTask::query()
            ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
            ->whereHas('engagement', $visible)
            ->with('engagement:id,engagement_code,title')
            ->orderByRaw('due_at IS NULL, due_at');
        $candidateQueue = AemsEscalationCandidate::query()
            ->whereIn('status', ['OPEN', 'ACKNOWLEDGED'])
            ->whereHas('engagement', $visible)
            ->with('engagement:id,engagement_code,title')
            ->latest('detected_at');

        $upcoming = $this->upcomingConferenceQuery($user, $today);
        $entryUpcoming = EntryConference::query()
            ->whereIn('status', ['SCHEDULED', 'RESCHEDULED'])
            ->whereBetween('scheduled_start_at', [$today->startOfDay(), $today->addDays(30)->endOfDay()])
            ->whereHas('engagement', $visible)
            ->with('engagement:id,engagement_code,title')
            ->orderBy('scheduled_start_at');
        $conferenceItems = (clone $upcoming)->with('engagement:id,engagement_code,title')->limit(6)->get()
            ->merge((clone $entryUpcoming)->limit(6)->get())
            ->sortBy('scheduled_start_at')->take(6)->map(fn ($conference): array => [
            'id' => $conference->id,
            'code' => $conference->conference_code,
            'type' => $conference instanceof EntryConference ? 'ENTRY' : 'EXIT',
            'status' => $conference->status,
            'scheduledAt' => $conference->scheduled_start_at?->toISOString(),
            'engagement' => $this->engagementRef($conference->engagement),
            'route' => '/audit-engagement-management/conferences?engagementId='.$conference->audit_engagement_id,
        ])->values()->all();

        $exitCount = (clone $upcoming)->count();
        $entryCount = (clone $entryUpcoming)->count();

        return [
            'overdueProcedures' => $queue($procedureQueue, 'overdueProcedures', 'Overdue procedures', '/audit-engagement-management/audit-program', fn ($item): array => ['id' => $item->id, 'code' => $item->procedure_code, 'title' => $item->procedure_description, 'dueAt' => $item->target_date?->toDateString(), 'engagement' => $this->engagementRef($item->program?->engagement)]),
            'workingPapersAwaitingReview' => $queue($paperQueue, 'workingPapersAwaitingReview', 'Working Papers awaiting review', '/audit-engagement-management/working-papers', fn ($item): array => ['id' => $item->id, 'code' => $item->working_paper_code, 'title' => $item->title, 'status' => $item->status, 'engagement' => $this->engagementRef($item->engagement)]),
            'evidenceRequestsAwaitingResponse' => $queue($evidenceQueue, 'evidenceRequestsAwaitingResponse', 'Evidence Requests awaiting response', '/audit-engagement-management/evidence', fn ($item): array => ['id' => $item->id, 'code' => $item->request_code, 'title' => $item->title, 'status' => $item->status, 'dueAt' => $item->due_date?->toDateString(), 'engagement' => $this->engagementRef($item->engagement)]),
            'evidenceGaps' => $queue($gapQueue, 'evidenceGaps', 'Evidence gaps', '/audit-engagement-management/evidence', fn ($item): array => ['id' => $item->id, 'code' => $item->evidence?->evidence_code, 'title' => $item->evidence?->title, 'gaps' => $item->evidence_gaps, 'restricted' => (bool) $item->is_restricted, 'engagement' => $this->engagementRef($item->engagement)]),
            'findingsAwaitingReview' => $queue($reviewFindingQueue, 'findingsAwaitingReview', 'Findings awaiting review', '/audit-engagement-management/findings', fn ($item): array => ['id' => $item->id, 'code' => $item->finding_code, 'title' => $item->title, 'status' => $item->status, 'engagement' => $this->engagementRef($item->engagement)]),
            'findingsAwaitingManagementResponse' => $queue($responseQueue, 'findingsAwaitingManagementResponse', 'Findings awaiting management response', '/audit-engagement-management/auditee-responses', fn ($item): array => ['id' => $item->id, 'code' => $item->finding_code, 'title' => $item->title, 'status' => $item->status, 'dueAt' => $item->management_response_due_date?->toDateString(), 'engagement' => $this->engagementRef($item->engagement)]),
            'upcomingConferences' => ['key' => 'upcomingConferences', 'label' => 'Upcoming conferences', 'route' => '/audit-engagement-management/conferences', 'count' => $exitCount + $entryCount, 'exitCount' => $exitCount, 'items' => $conferenceItems],
            'reportsPendingApproval' => $queue($reportQueue, 'reportsPendingApproval', 'Reports pending approval', '/audit-engagement-management/reports', fn ($item): array => ['id' => $item->id, 'code' => $item->report_code, 'title' => $item->title, 'status' => $item->status, 'engagement' => $this->engagementRef($item->engagement)]),
            'cmsTransferExceptions' => $queue($exceptionQueue, 'cmsTransferExceptions', 'CMS transfer exceptions', '/audit-engagement-management/completion', fn ($item): array => ['id' => $item->id, 'code' => $item->exception_code, 'title' => $item->message, 'status' => $item->status, 'engagement' => $this->engagementRef($item->manifest?->engagement)]),
            'reviewNotesAwaitingReview' => $queue($noteQueue, 'reviewNotesAwaitingReview', 'Review Notes', '/audit-engagement-management/work-queues', fn ($item): array => ['id' => $item->id, 'code' => $item->note_code, 'title' => $item->content, 'status' => $item->status, 'engagement' => $this->engagementRef($item->engagement)]),
            'tasks' => $queue($taskQueue, 'tasks', 'Tasks', '/audit-engagement-management/work-queues', fn ($item): array => ['id' => $item->id, 'code' => $item->task_code, 'title' => $item->title, 'status' => $item->status, 'dueState' => $item->due_state, 'dueAt' => $item->due_at?->toISOString(), 'engagement' => $this->engagementRef($item->engagement)]),
            'escalationCandidates' => $queue($candidateQueue, 'escalationCandidates', 'Escalation candidates', '/audit-engagement-management/work-queues', fn ($item): array => ['id' => $item->id, 'code' => $item->candidate_code, 'title' => $item->reason, 'status' => $item->status, 'dueAt' => $item->due_at?->toISOString(), 'engagement' => $this->engagementRef($item->engagement)]),
        ];
    }

    private function evidenceGapQuery(User $user, callable $visible): Builder
    {
        return AemsEvidenceAssessment::query()
            ->where('is_current_revision', true)
            ->where(function (Builder $query): void {
                $query->where('is_restricted', true)
                    ->orWhereNotNull('evidence_gaps')->where('evidence_gaps', '<>', '')
                    ->orWhereNotNull('limitations')->where('limitations', '<>', '')
                    ->orWhere('exception_required', true);
            })
            ->whereHas('engagement', $visible);
    }

    private function upcomingConferenceQuery(User $user, CarbonImmutable $today): Builder
    {
        // Keep the two conference sources separate at the query boundary; the
        // shared dashboard queue is assembled from both records below.
        return ExitConference::query()
            ->whereIn('status', ['SCHEDULED', 'RESCHEDULED'])
            ->whereBetween('scheduled_start_at', [$today->startOfDay(), $today->addDays(30)->endOfDay()])
            ->whereHas('engagement', fn (Builder $engagement): Builder => $engagement->visibleTo($user)->where('is_active', true));
    }

    /** @return array<string, mixed> */
    private function notificationSummary(User $user): array
    {
        $base = SystemNotification::query()->where('recipient_id', $user->id)->whereNull('archived_at');
        $aems = fn (Builder $query): Builder => $query->whereIn('module_code', ['AEMS', 'AEM']);

        return [
            'unread' => (clone $base)->whereNull('read_at')->count(),
            'aemsUnread' => $aems((clone $base))->whereNull('read_at')->count(),
            'overdue' => (clone $base)->where('category', 'OVERDUE')->whereNull('read_at')->count(),
            'recent' => (clone $base)->with('actor:id,name,employee_id')->latest()->limit(6)->get()->map(fn (SystemNotification $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'message' => $item->message,
                'category' => $item->category,
                'priority' => $item->priority,
                'moduleCode' => $item->module_code,
                'readAt' => $item->read_at?->toISOString(),
                'actionUrl' => $item->action_url,
                'createdAt' => $item->created_at?->toISOString(),
            ])->values()->all(),
        ];
    }

    private function reminderRules(): array
    {
        return [
            'enabled' => $this->runtime->boolean('aems_reminders_enabled'),
            'dueHours' => $this->runtime->integer('aems_reminder_due_hours'),
            'responseDueDays' => $this->runtime->integer('aems_response_reminder_days'),
            'conferenceDueDays' => $this->runtime->integer('aems_conference_reminder_days'),
        ];
    }

    private function engagementRef(?AuditEngagement $engagement): ?array
    {
        return $engagement ? ['id' => $engagement->id, 'code' => $engagement->engagement_code, 'title' => $engagement->title] : null;
    }

    private function readyForClosureQuery(User $user): Builder
    {
        return $this->visibleEngagements($user)
            ->whereNotIn('status', ['CLOSED', 'CANCELLED', 'SUSPENDED'])
            ->where('actual_person_days', '>', 0)
            ->whereHas('reports', fn (Builder $report) => $report
                ->where('report_stage', 'FINAL_REPORT')
                ->where('status', 'ISSUED')
                ->whereHas('currentVersion.recipients')
                ->whereDoesntHave('currentVersion.recipients', fn (Builder $recipient) => $recipient
                    ->whereNull('sent_at')
                    ->whereNotIn('delivery_status', ['SENT', 'DELIVERED', 'ACKNOWLEDGED'])))
            ->whereDoesntHave('findings', fn (Builder $finding) => $finding
                ->where('is_current_revision', true)
                ->where('status', '<>', 'FINALIZED'))
            ->whereDoesntHave('findings', fn (Builder $finding) => $finding
                ->where('is_current_revision', true)
                ->whereHas('recommendations', fn (Builder $recommendation) => $recommendation
                    ->whereNotIn('status', ['TRANSFERRED', 'EXCLUDED'])))
            ->whereDoesntHave('workingPapers', fn (Builder $paper) => $paper
                ->whereNotIn('status', ['APPROVED', 'SUPERSEDED', 'VOIDED']))
            ->whereDoesntHave('programs', fn (Builder $program) => $program
                ->where('is_current_revision', true)
                ->where('is_active', true)
                ->whereHas('procedures', fn (Builder $procedure) => $procedure
                    ->whereNotIn('status', ['COMPLETED', 'WAIVED'])))
            ->whereHas('exitConferences', fn (Builder $conference) => $conference
                ->whereIn('status', ['COMPLETED', 'WAIVED']))
            ->whereDoesntHave('engagementOrder', fn (Builder $order) => $order
                ->whereIn('status', self::REVIEW_STATUSES))
            ->whereDoesntHave('engagementPlan', fn (Builder $plan) => $plan
                ->whereIn('status', self::REVIEW_STATUSES))
            ->whereDoesntHave('programs', fn (Builder $program) => $program
                ->where('is_current_revision', true)
                ->whereIn('status', self::REVIEW_STATUSES))
            ->whereDoesntHave('workingPapers', fn (Builder $paper) => $paper
                ->whereIn('status', self::REVIEW_STATUSES))
            ->whereDoesntHave('reports', fn (Builder $report) => $report
                ->whereIn('status', self::REVIEW_STATUSES))
            ->whereDoesntHave('reports.currentVersion.reviewComments', fn (Builder $comment) => $comment
                ->where('review_action', 'RETURNED'));
    }

    private function visibleEngagements(User $user): Builder
    {
        return AuditEngagement::query()
            ->visibleTo($user)
            ->where('is_active', true);
    }

    /** @param array<string, mixed> $filters */
    private function filteredEngagements(User $user, array $filters): Builder
    {
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));

        return $this->visibleEngagements($user)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $pattern = "%{$search}%";
                $query->where(function (Builder $searchQuery) use ($pattern): void {
                    $searchQuery
                        ->whereRaw('LOWER(engagement_code) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(title) LIKE ?', [$pattern])
                        ->orWhereHas('offices', fn (Builder $office) => $office
                            ->whereRaw('LOWER(name) LIKE ?', [$pattern])
                            ->orWhereRaw('LOWER(code) LIKE ?', [$pattern]));
                });
            })
            ->when(
                isset($filters['status']),
                fn (Builder $query) => $query->where('status', $filters['status']),
            )
            ->when(
                isset($filters['officeId']),
                fn (Builder $query) => $query->whereHas(
                    'offices',
                    fn (Builder $office) => $office->whereKey($filters['officeId']),
                ),
            )
            ->when(
                filled($filters['phase'] ?? null),
                function (Builder $query) use ($filters): void {
                    $phase = $filters['phase'];
                    if ($phase === 'other') {
                        $query->whereNotIn('status', collect(self::PHASE_STATUSES)->flatten()->unique()->all());
                    } else {
                        $query->whereIn('status', self::PHASE_STATUSES[$phase] ?? []);
                    }
                },
            );
    }

    /** @return list<string> */
    private function trackerRelations(): array
    {
        return [
            'offices:id,code,name',
            'engagementOrder:id,audit_engagement_id,status,current_version_number',
            'engagementPlan:id,audit_engagement_id,status,current_version_number',
            'programs' => fn ($query) => $query
                ->where('is_current_revision', true)
                ->where('is_active', true)
                ->with('procedures:id,audit_program_id,status,target_date'),
            'workingPapers:id,audit_engagement_id,status,current_version_number',
            'entryConference:id,audit_engagement_id,status,scheduled_start_at,held_at',
            'evidence' => fn ($query) => $query
                ->where('is_current_revision', true)
                ->select([
                    'id',
                    'audit_engagement_id',
                    'status',
                    'version_number',
                    'is_current_revision',
                ]),
            'findings' => fn ($query) => $query
                ->where('is_current_revision', true)
                ->with([
                    'recommendations:id,audit_finding_id,status',
                    'managementResponses' => fn ($response) => $response
                        ->where('is_current_revision', true)
                        ->select([
                            'id',
                            'audit_finding_id',
                            'status',
                            'is_current_revision',
                        ]),
                ]),
            'exitConferences:id,audit_engagement_id,status,scheduled_start_at,acknowledged_at',
            'reports' => fn ($query) => $query
                ->where('is_active', true)
                ->with([
                    'currentVersion:id,audit_report_id,report_stage,is_locked',
                    'currentVersion.recipients:id,audit_report_version_id,delivery_status,sent_at',
                    'currentVersion.reviewComments:id,audit_report_id,audit_report_version_id,review_action',
                ]),
            'currentCompletionAssessment:id,audit_engagement_id,status_code,is_current_revision,approved_at',
            'closure:id,audit_engagement_id,completion_assessment_id,status_code,is_current_revision,approved_at,closed_at,document_index_locked_at',
        ];
    }

    /** @return Collection<int, array{id:int, code:string, name:string}> */
    private function visibleOffices(User $user): Collection
    {
        $visibleEngagementIds = $this->visibleEngagements($user)
            ->select('audit_engagements.id');

        return Office::query()
            ->select(['id', 'code', 'name'])
            ->whereIn('id', fn ($query) => $query
                ->select('office_id')
                ->from('audit_engagement_offices')
                ->whereIn('audit_engagement_id', $visibleEngagementIds))
            ->orderBy('name')
            ->get()
            ->map(fn (Office $office) => [
                'id' => $office->id,
                'code' => $office->code,
                'name' => $office->name,
            ]);
    }

    private function engagementData(
        AuditEngagement $engagement,
        CarbonImmutable $today,
    ): array {
        $programs = $engagement->programs;
        $procedures = $programs->flatMap->procedures;
        $papers = $engagement->workingPapers;
        $evidence = $engagement->evidence;
        $findings = $engagement->findings;
        $responses = $findings->flatMap->managementResponses;
        $reports = $engagement->reports;
        $closure = $this->closureData($engagement);
        $actualPersonDays = $this->resources->engagementActualPersonDays($engagement);

        $overdueProcedures = $procedures->filter(
            fn ($procedure) => $procedure->target_date?->isBefore($today)
                && ! in_array($procedure->status, ['COMPLETED', 'WAIVED'], true),
        )->count();
        $overdueResponses = $findings->filter(
            fn ($finding) => $finding->management_response_due_date?->isBefore($today)
                && in_array(
                    $finding->status,
                    ['COMMUNICATED', 'AWAITING_MANAGEMENT_RESPONSE'],
                    true,
                ),
        )->count();
        $overdueConference = $engagement->exitConferences->contains(
            fn ($conference) => $conference->scheduled_start_at?->isBefore($today->startOfDay())
                && in_array($conference->status, ['SCHEDULED', 'RESCHEDULED'], true),
        );
        $scheduleOverdue = $engagement->planned_end_date?->isBefore($today)
            && ! in_array($engagement->status, ['ISSUED', 'CLOSED', 'CANCELLED'], true);

        $alerts = collect([
            $overdueProcedures > 0
                ? "{$overdueProcedures} overdue fieldwork ".($overdueProcedures === 1 ? 'procedure' : 'procedures')
                : null,
            $overdueResponses > 0
                ? "{$overdueResponses} overdue management ".($overdueResponses === 1 ? 'response' : 'responses')
                : null,
            $overdueConference ? 'Exit conference is overdue' : null,
            $scheduleOverdue ? 'Planned engagement end date has passed' : null,
            $engagement->status === 'SUSPENDED' ? 'Engagement is suspended' : null,
        ])->filter()->values();

        $stages = [
            $this->singleWorkflowStage(
                'aeo',
                'AEO',
                $engagement->engagementOrder?->status,
                ['ISSUED'],
                '/audit-engagement-management/aeo',
            ),
            $this->singleWorkflowStage(
                'aep',
                'AEP',
                $engagement->engagementPlan?->status,
                ['APPROVED', 'SUPERSEDED'],
                '/audit-engagement-management/aep',
            ),
            $this->aggregateWorkflowStage(
                'auditProgram',
                'Audit Program',
                $programs,
                ['COMPLETED'],
                '/audit-engagement-management/audit-program',
            ),
            $this->entryConferenceStage($engagement),
            $this->countStage(
                'fieldworkProcedures',
                'Fieldwork Procedures',
                $procedures,
                ['COMPLETED', 'WAIVED'],
                '/audit-engagement-management/audit-program',
                $overdueProcedures,
            ),
            $this->countStage(
                'workingPapers',
                'Working Papers',
                $papers,
                ['APPROVED', 'SUPERSEDED', 'VOIDED'],
                '/audit-engagement-management/working-papers',
                0,
                $papers->whereIn('status', ['SUBMITTED', 'RESUBMITTED'])->count(),
            ),
            $this->countStage(
                'evidence',
                'Evidence',
                $evidence,
                ['VERIFIED', 'LOCKED', 'VOIDED'],
                '/audit-engagement-management/working-papers',
            ),
            $this->findingsStage($findings, $procedures),
            $this->responsesStage($findings, $responses, $procedures, $overdueResponses),
            $this->exitConferenceStage($engagement->exitConferences, $today),
            $this->reportStage($reports, 'DRAFT_REPORT', 'Draft Report'),
            $this->reportStage($reports, 'FINAL_REPORT', 'Final Report'),
            $this->cmsStage($findings, $reports),
            $this->closureStage($engagement, $closure),
        ];

        $overallProgress = (int) round(collect($stages)->avg('percent'));
        $health = $engagement->status === 'SUSPENDED'
            ? 'BLOCKED'
            : ($alerts->isNotEmpty() ? 'OVERDUE' : ($closure['isReady'] ? 'READY_FOR_CLOSURE' : 'ON_TRACK'));

        return [
            'id' => $engagement->id,
            'engagementCode' => $engagement->engagement_code,
            'title' => $engagement->title,
            'sourceType' => $engagement->source_type,
            'status' => $engagement->status,
            'phase' => $engagement->phase,
            'administrativeStatus' => $engagement->administrative_status,
            'engagementOfficeId' => $engagement->engagement_office_id,
            'health' => $health,
            'overallProgress' => $overallProgress,
            'plannedStartDate' => $engagement->planned_start_date?->toDateString(),
            'plannedEndDate' => $engagement->planned_end_date?->toDateString(),
            'expectedReportDate' => $engagement->expected_report_date?->toDateString(),
            'actualPersonDays' => $actualPersonDays,
            'offices' => $engagement->offices
                ->map(fn (Office $office) => [
                    'id' => $office->id,
                    'code' => $office->code,
                    'name' => $office->name,
                    'isPrimary' => (bool) $office->pivot?->is_primary,
                ])->values(),
            'alerts' => $alerts,
            'stages' => $stages,
            'closure' => $closure,
            'updatedAt' => $engagement->updated_at?->toIso8601String(),
        ];
    }

    private function singleWorkflowStage(
        string $key,
        string $label,
        ?string $workflowStatus,
        array $terminalStatuses,
        string $route,
    ): array {
        if ($workflowStatus === null) {
            return $this->stage($key, $label, 'NOT_STARTED', 0, 'Not started', $route);
        }

        $percent = in_array($workflowStatus, $terminalStatuses, true)
            ? 100
            : $this->workflowPercent($workflowStatus);

        return $this->stage(
            $key,
            $label,
            $this->displayStatus($workflowStatus, $percent),
            $percent,
            $this->statusLabel($workflowStatus),
            $route,
        );
    }

    private function aggregateWorkflowStage(
        string $key,
        string $label,
        Collection $records,
        array $terminalStatuses,
        string $route,
    ): array {
        if ($records->isEmpty()) {
            return $this->stage($key, $label, 'NOT_STARTED', 0, 'No current program', $route);
        }

        $percent = (int) round($records->avg(
            fn ($record) => in_array($record->status, $terminalStatuses, true)
                ? 100
                : $this->workflowPercent($record->status),
        ));
        $complete = $records->whereIn('status', $terminalStatuses)->count();

        return $this->stage(
            $key,
            $label,
            $percent === 100 ? 'COMPLETE' : 'IN_PROGRESS',
            $percent,
            "{$complete} of {$records->count()} completed",
            $route,
            $records->count(),
            $complete,
        );
    }

    private function countStage(
        string $key,
        string $label,
        Collection $records,
        array $terminalStatuses,
        string $route,
        int $overdue = 0,
        int $awaitingReview = 0,
    ): array {
        $total = $records->count();
        if ($total === 0) {
            return $this->stage($key, $label, 'NOT_STARTED', 0, 'No records', $route);
        }

        $complete = $records->whereIn('status', $terminalStatuses)->count();
        $percent = (int) round(($complete / $total) * 100);
        $status = $overdue > 0
            ? 'OVERDUE'
            : ($percent === 100 ? 'COMPLETE' : ($awaitingReview > 0 ? 'AWAITING_REVIEW' : 'IN_PROGRESS'));
        $detail = "{$complete} of {$total} complete";
        if ($overdue > 0) {
            $detail .= " · {$overdue} overdue";
        } elseif ($awaitingReview > 0) {
            $detail .= " · {$awaitingReview} awaiting review";
        }

        return $this->stage(
            $key,
            $label,
            $status,
            $percent,
            $detail,
            $route,
            $total,
            $complete,
            $overdue,
            $awaitingReview,
        );
    }

    private function findingsStage(Collection $findings, Collection $procedures): array
    {
        if ($findings->isEmpty() && $this->allTerminal($procedures, ['COMPLETED', 'WAIVED'])) {
            return $this->stage(
                'findings',
                'Findings',
                'NOT_APPLICABLE',
                100,
                'Fieldwork completed with no findings',
                '/audit-engagement-management/findings',
            );
        }

        return $this->countStage(
            'findings',
            'Findings',
            $findings,
            ['FINALIZED'],
            '/audit-engagement-management/findings',
        );
    }

    private function responsesStage(
        Collection $findings,
        Collection $responses,
        Collection $procedures,
        int $overdue,
    ): array {
        $responseRequired = $findings->filter(fn ($finding) => in_array(
            $finding->status,
            ['COMMUNICATED', 'AWAITING_MANAGEMENT_RESPONSE', 'UNDER_DIALOGUE', 'FINALIZED'],
            true,
        ));
        if ($responseRequired->isEmpty()
            && ($findings->isNotEmpty() || $this->allTerminal($procedures, ['COMPLETED', 'WAIVED']))) {
            return $this->stage(
                'managementResponses',
                'Management Responses',
                'NOT_APPLICABLE',
                100,
                'No communicated findings require a response',
                '/audit-engagement-management/auditee-responses',
            );
        }

        $total = $responseRequired->count();
        if ($total === 0) {
            return $this->stage(
                'managementResponses',
                'Management Responses',
                'NOT_STARTED',
                0,
                'No response requested',
                '/audit-engagement-management/auditee-responses',
            );
        }
        $complete = $responseRequired->filter(function ($finding) use ($responses): bool {
            return $finding->non_response_recorded_at !== null
                || $responses->where('audit_finding_id', $finding->id)
                    ->contains('status', 'DIALOGUE_FINALIZED');
        })->count();
        $percent = (int) round(($complete / $total) * 100);

        return $this->stage(
            'managementResponses',
            'Management Responses',
            $overdue > 0 ? 'OVERDUE' : ($percent === 100 ? 'COMPLETE' : 'IN_PROGRESS'),
            $percent,
            "{$complete} of {$total} dialogues finalized".($overdue > 0 ? " · {$overdue} overdue" : ''),
            '/audit-engagement-management/auditee-responses',
            $total,
            $complete,
            $overdue,
        );
    }

    private function exitConferenceStage(Collection $conferences, CarbonImmutable $today): array
    {
        $conference = $conferences->sortByDesc('scheduled_start_at')->first();
        if ($conference === null) {
            return $this->stage(
                'exitConference',
                'Exit Conference',
                'NOT_STARTED',
                0,
                'Not scheduled',
                '/audit-engagement-management/conferences',
            );
        }
        if (in_array($conference->status, ['COMPLETED', 'WAIVED'], true)) {
            return $this->stage(
                'exitConference',
                'Exit Conference',
                'COMPLETE',
                100,
                $this->statusLabel($conference->status),
                '/audit-engagement-management/conferences',
            );
        }
        $overdue = $conference->scheduled_start_at?->isBefore($today->startOfDay())
            && in_array($conference->status, ['SCHEDULED', 'RESCHEDULED'], true);

        return $this->stage(
            'exitConference',
            'Exit Conference',
            $overdue ? 'OVERDUE' : 'SCHEDULED',
            50,
            ($overdue ? 'Overdue · ' : '').$conference->scheduled_start_at?->format('M j, Y g:i A'),
            '/audit-engagement-management/conferences',
            1,
            0,
            $overdue ? 1 : 0,
        );
    }

    private function entryConferenceStage(AuditEngagement $engagement): array
    {
        $conference = $engagement->entryConference;
        $route = "/audit-engagement-management/conferences?engagementId={$engagement->id}";
        if ($conference === null) {
            return $this->stage(
                'entryConference',
                'Entry Conference',
                'NOT_STARTED',
                0,
                'Not prepared',
                $route,
            );
        }
        if (in_array($conference->status, ['COMPLETED', 'WAIVED'], true)) {
            return $this->stage(
                'entryConference',
                'Entry Conference',
                'COMPLETE',
                100,
                $this->statusLabel($conference->status),
                $route,
            );
        }

        return $this->singleWorkflowStage(
            'entryConference',
            'Entry Conference',
            $conference->status,
            ['COMPLETED', 'WAIVED'],
            $route,
        );
    }

    private function reportStage(Collection $reports, string $reportStage, string $label): array
    {
        $report = $reports->sortByDesc('updated_at')->first();
        $route = '/audit-engagement-management/reports';
        if ($reportStage === 'DRAFT_REPORT' && $report?->report_stage === 'FINAL_REPORT') {
            return $this->stage('draftReport', $label, 'COMPLETE', 100, 'Draft approved', $route);
        }
        if ($report === null || $report->report_stage !== $reportStage) {
            return $this->stage(
                $reportStage === 'DRAFT_REPORT' ? 'draftReport' : 'finalReport',
                $label,
                'NOT_STARTED',
                0,
                'Not generated',
                $route,
            );
        }

        $terminal = $reportStage === 'DRAFT_REPORT' ? ['APPROVED'] : ['ISSUED'];

        return $this->singleWorkflowStage(
            $reportStage === 'DRAFT_REPORT' ? 'draftReport' : 'finalReport',
            $label,
            $report->status,
            $terminal,
            $route,
        );
    }

    private function cmsStage(Collection $findings, Collection $reports): array
    {
        $recommendations = $findings->flatMap->recommendations;
        $issued = $reports->contains('status', 'ISSUED');
        if ($recommendations->isEmpty()) {
            return $this->stage(
                'cmsTransfer',
                'CMS Transfer',
                $issued ? 'NOT_APPLICABLE' : 'NOT_STARTED',
                $issued ? 100 : 0,
                $issued ? 'No recommendations to transfer' : 'Awaiting final report',
                '/audit-engagement-management/reports',
            );
        }
        $complete = $recommendations->whereIn('status', ['TRANSFERRED', 'EXCLUDED'])->count();
        $total = $recommendations->count();
        $percent = (int) round(($complete / $total) * 100);

        return $this->stage(
            'cmsTransfer',
            'CMS Transfer',
            $percent === 100 ? 'COMPLETE' : ($issued ? 'IN_PROGRESS' : 'BLOCKED'),
            $percent,
            "{$complete} of {$total} transferred or excluded",
            '/audit-engagement-management/reports',
            $total,
            $complete,
        );
    }

    private function closureStage(AuditEngagement $engagement, array $closure): array
    {
        if ($engagement->status === 'CLOSED') {
            return $this->stage(
                'engagementClosure',
                'Engagement Closure',
                'COMPLETE',
                100,
                'Engagement closed',
                "/audit-engagement-management/{$engagement->id}?tab=closure",
            );
        }
        $complete = collect($closure['gates'])->where('met', true)->count();
        $total = count($closure['gates']);
        $percent = $closure['isReady']
            ? 90
            : (int) round(($complete / max($total, 1)) * 85);

        return $this->stage(
            'engagementClosure',
            'Engagement Closure',
            $closure['isReady'] ? 'READY' : 'BLOCKED',
            $percent,
            $closure['isReady']
                ? ($closure['formalClosureStatus']
                    ? 'Pre-closure gates satisfied; formal Closure is '
                        .str($closure['formalClosureStatus'])->replace('_', ' ')->lower()
                    : 'Pre-closure gates satisfied; formal Completion Assessment and Closure remain')
                : count($closure['blockers']).' closure '.(count($closure['blockers']) === 1 ? 'gate' : 'gates').' outstanding',
            "/audit-engagement-management/{$engagement->id}?tab=closure",
            $total,
            $complete,
        );
    }

    private function closureData(AuditEngagement $engagement): array
    {
        $findings = $engagement->findings;
        $recommendations = $findings->flatMap->recommendations;
        $procedures = $engagement->programs->flatMap->procedures;
        $reports = $engagement->reports;
        $issuedReport = $reports->first(
            fn ($report) => $report->report_stage === 'FINAL_REPORT'
                && $report->status === 'ISSUED',
        );
        $recipientsComplete = $issuedReport?->currentVersion?->recipients->isNotEmpty()
            && $issuedReport->currentVersion->recipients->every(
                fn ($recipient) => $recipient->sent_at !== null
                    || in_array($recipient->delivery_status, ['SENT', 'DELIVERED', 'ACKNOWLEDGED'], true),
            );
        $activeReview = collect([
            $engagement->engagementOrder?->status,
            $engagement->engagementPlan?->status,
            ...$engagement->programs->pluck('status'),
            ...$engagement->workingPapers->pluck('status'),
            ...$reports->pluck('status'),
        ])->filter()->contains(fn ($status) => in_array($status, self::REVIEW_STATUSES, true));
        $unresolvedReportComment = $reports->contains(
            fn ($report) => $report->currentVersion?->reviewComments
                ?->contains('review_action', 'RETURNED') ?? false,
        );

        $gates = [
            ['key' => 'issuedReport', 'label' => 'Final report issued', 'met' => $issuedReport !== null],
            ['key' => 'recipients', 'label' => 'Recipients and issuance recorded', 'met' => (bool) $recipientsComplete],
            [
                'key' => 'findings',
                'label' => 'All findings finalized',
                'met' => $findings->every('status', 'FINALIZED'),
            ],
            [
                'key' => 'recommendations',
                'label' => 'Recommendations transferred or excluded',
                'met' => $recommendations->every(
                    fn ($recommendation) => in_array($recommendation->status, ['TRANSFERRED', 'EXCLUDED'], true),
                ),
            ],
            [
                'key' => 'workingPapers',
                'label' => 'Working papers terminal',
                'met' => $engagement->workingPapers->every(
                    fn ($paper) => in_array($paper->status, ['APPROVED', 'SUPERSEDED', 'VOIDED'], true),
                ),
            ],
            [
                'key' => 'procedures',
                'label' => 'Procedures completed or waived',
                'met' => $procedures->every(
                    fn ($procedure) => in_array($procedure->status, ['COMPLETED', 'WAIVED'], true),
                ),
            ],
            [
                'key' => 'entryConference',
                'label' => 'Entry conference completed or waived',
                'met' => in_array(
                    $engagement->entryConference?->status,
                    ['COMPLETED', 'WAIVED'],
                    true,
                ),
            ],
            [
                'key' => 'exitConference',
                'label' => 'Exit conference completed or waived',
                'met' => $engagement->exitConferences->contains(
                    fn ($conference) => in_array($conference->status, ['COMPLETED', 'WAIVED'], true),
                ),
            ],
            [
                'key' => 'actualPersonDays',
                'label' => 'Actual person-days recorded',
                'met' => $this->resources->engagementActualPersonDays($engagement) > 0,
            ],
            [
                'key' => 'activeReviews',
                'label' => 'No active child review',
                'met' => ! $activeReview,
            ],
            [
                'key' => 'reviewComments',
                'label' => 'No unresolved current report return',
                'met' => ! $unresolvedReportComment,
            ],
        ];
        $blockers = collect($gates)
            ->where('met', false)
            ->pluck('label')
            ->values()
            ->all();

        return [
            'isReady' => $engagement->status !== 'CLOSED' && $blockers === [],
            'preClosureReady' => $engagement->status !== 'CLOSED' && $blockers === [],
            'completionAssessmentStatus' => $engagement->currentCompletionAssessment?->status_code,
            'formalClosureStatus' => $engagement->closure?->status_code,
            'isFormallyClosed' => $engagement->status === 'CLOSED'
                && $engagement->closure?->status_code === 'CLOSED',
            'gates' => $gates,
            'blockers' => $blockers,
        ];
    }

    private function allTerminal(Collection $records, array $terminalStatuses): bool
    {
        return $records->isNotEmpty()
            && $records->every(fn ($record) => in_array($record->status, $terminalStatuses, true));
    }

    private function workflowPercent(string $status): int
    {
        return match ($status) {
            'DRAFT' => 20,
            'RETURNED_FOR_REVISION' => 35,
            'SUBMITTED', 'PENDING_REVIEW' => 55,
            'RESUBMITTED' => 65,
            'VALIDATED', 'APPROVED' => 80,
            'AUTHORIZED', 'COMMUNICATED', 'ACTIVE' => 85,
            'AWAITING_MANAGEMENT_RESPONSE' => 60,
            'UNDER_DIALOGUE' => 75,
            'FINALIZED', 'COMPLETED', 'ISSUED', 'TRANSFERRED', 'SUPERSEDED' => 100,
            default => 30,
        };
    }

    private function displayStatus(string $workflowStatus, int $percent): string
    {
        if ($percent === 100) {
            return 'COMPLETE';
        }
        if (in_array($workflowStatus, self::REVIEW_STATUSES, true)) {
            return 'AWAITING_REVIEW';
        }
        if ($workflowStatus === 'RETURNED_FOR_REVISION') {
            return 'RETURNED';
        }

        return 'IN_PROGRESS';
    }

    private function statusLabel(string $status): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $status)));
    }

    private function stage(
        string $key,
        string $label,
        string $status,
        int $percent,
        string $detail,
        string $route,
        int $total = 0,
        int $complete = 0,
        int $overdue = 0,
        int $awaitingReview = 0,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'percent' => max(0, min(100, $percent)),
            'detail' => $detail,
            'route' => $route,
            'total' => $total,
            'complete' => $complete,
            'overdue' => $overdue,
            'awaitingReview' => $awaitingReview,
        ];
    }
}
