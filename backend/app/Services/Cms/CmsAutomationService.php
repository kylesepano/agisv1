<?php

namespace App\Services\Cms;

use App\Models\CmsAutomationAction;
use App\Models\CmsAutomationRule;
use App\Models\CmsAutomationRuleVersion;
use App\Models\CmsAutomationRun;
use App\Models\CmsClosureCandidate;
use App\Models\CmsEscalationCandidate;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\CmsValidationVersion;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CMS-11A automation boundary. It detects, reminds, and prepares reviewable
 * candidates; it never makes a final professional decision or issues a notice.
 */
class CmsAutomationService
{
    public function __construct(
        private readonly CmsRecommendationScopeService $scope,
        private readonly NotificationService $notifications,
    ) {}

    /** @return list<CmsAutomationRule> */
    public function rules(User $actor): array
    {
        $this->authorize($actor, 'cms.automation.view');

        return CmsAutomationRule::query()
            ->with(['currentVersion', 'creator', 'updater'])
            ->orderBy('rule_code')
            ->get()
            ->all();
    }

    public function saveRule(User $actor, array $data, ?int $id = null): CmsAutomationRule
    {
        $this->authorize($actor, 'cms.automation.manage');
        $type = strtoupper((string) ($data['ruleType'] ?? $data['rule_type'] ?? ''));
        throw_unless(
            in_array($type, [
                CmsAutomationRule::TYPE_REMINDER,
                CmsAutomationRule::TYPE_CLOSURE_READINESS,
                CmsAutomationRule::TYPE_ESCALATION_CANDIDATE,
            ], true),
            ValidationException::withMessages(['ruleType' => ['The automation rule type is not supported.']]),
        );
        $ruleCode = strtoupper(trim((string) ($data['ruleCode'] ?? $data['rule_code'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        if ($id) {
            $existing = CmsAutomationRule::query()->findOrFail($id);
            $ruleCode = $ruleCode !== '' ? $ruleCode : $existing->rule_code;
            $name = $name !== '' ? $name : $existing->name;
        }
        throw_if($ruleCode === '', ValidationException::withMessages(['ruleCode' => ['A rule code is required.']]));
        throw_if($name === '', ValidationException::withMessages(['name' => ['A rule name is required.']]));
        throw_if(
            CmsAutomationRule::query()->where('rule_code', $ruleCode)->when($id, fn (Builder $query) => $query->where('id', '<>', $id))->exists(),
            ValidationException::withMessages(['ruleCode' => ['The rule code is already in use.']]),
        );

        return DB::transaction(function () use ($actor, $data, $id, $type): CmsAutomationRule {
            $rule = $id
                ? CmsAutomationRule::query()->lockForUpdate()->findOrFail($id)
                : new CmsAutomationRule(['created_by' => $actor->id, 'lock_version' => 1]);
            $configuration = $data['configuration'] ?? [];
            if (is_string($configuration)) {
                $configuration = json_decode($configuration, true) ?: [];
            }
            $attributes = [
                'rule_code' => strtoupper((string) ($data['ruleCode'] ?? $data['rule_code'] ?? $rule->rule_code)),
                'name' => trim((string) ($data['name'] ?? $rule->name)),
                'description' => $data['description'] ?? $rule->description,
                'rule_type' => $type,
                'status_code' => strtoupper((string) ($data['statusCode'] ?? $data['status_code'] ?? $rule->status_code ?? CmsAutomationRule::ACTIVE)),
                'schedule_code' => strtoupper((string) ($data['scheduleCode'] ?? $data['schedule_code'] ?? $rule->schedule_code ?? 'DAILY')),
                'configuration' => $configuration,
                'updated_by' => $actor->id,
            ];
            throw_if($attributes['status_code'] === 'ACTIVE' && ! in_array($attributes['schedule_code'], ['DAILY', 'HOURLY', 'MANUAL'], true), ValidationException::withMessages(['scheduleCode' => ['The schedule code is not supported.']]));
            $rule->fill($attributes);
            $rule->save();

            $versionNumber = ((int) $rule->versions()->lockForUpdate()->max('version_number')) + 1;
            $version = $rule->versions()->create([
                'version_number' => $versionNumber,
                'status_code' => $rule->status_code,
                'configuration' => $configuration,
                'created_by' => $actor->id,
                'effective_from' => now(),
            ]);
            $rule->forceFill([
                'current_version_id' => $version->id,
                'lock_version' => ((int) $rule->lock_version) + 1,
            ])->save();
            $this->recordAudit($actor->id, 'cms.automation.rule_saved', 'CMS automation rule configuration changed.', null, ['ruleId' => $rule->id, 'ruleCode' => $rule->rule_code, 'versionId' => $version->id]);

            return $rule->fresh(['currentVersion', 'creator', 'updater']);
        });
    }

    public function run(?User $actor = null, ?string $ruleCode = null): int
    {
        if ($actor) {
            $this->authorize($actor, 'cms.automation.run');
        }
        $query = CmsAutomationRule::query()
            ->where('status_code', CmsAutomationRule::ACTIVE)
            ->with('currentVersion');
        if ($ruleCode) {
            $query->where('rule_code', strtoupper($ruleCode));
        }
        $processed = 0;
        foreach ($query->get() as $rule) {
            $processed += $this->runRule($rule, $actor);
        }

        return $processed;
    }

    public function runRule(CmsAutomationRule $rule, ?User $actor = null): int
    {
        $version = $rule->currentVersion ?: $rule->versions()->first();
        if (! $version) {
            return 0;
        }
        $runKey = sprintf('cms-automation:%s:%s', $rule->rule_code, now()->toDateString());
        $run = CmsAutomationRun::query()->firstOrCreate(
            ['run_key' => $runKey],
            [
                'cms_automation_rule_id' => $rule->id,
                'cms_automation_rule_version_id' => $version->id,
                'status_code' => 'RUNNING',
                'started_at' => now(),
            ],
        );
        if ($run->status_code === 'COMPLETED') {
            return (int) $run->created_count;
        }

        $created = 0;
        $scanned = 0;
        try {
            $created = match ($rule->rule_type) {
                CmsAutomationRule::TYPE_REMINDER => $this->processReminders($rule, $version, $run, $scanned),
                CmsAutomationRule::TYPE_CLOSURE_READINESS => $this->processClosureCandidates($rule, $version, $run, $scanned),
                CmsAutomationRule::TYPE_ESCALATION_CANDIDATE => $this->processEscalationCandidates($rule, $version, $run, $scanned),
                default => 0,
            };
            $run->forceFill([
                'status_code' => 'COMPLETED', 'finished_at' => now(),
                'scanned_count' => $scanned, 'created_count' => $created,
            ])->save();
            $this->recordAudit($actor?->id, 'cms.automation.run_completed', 'CMS automation rule run completed.', null, ['ruleId' => $rule->id, 'runId' => $run->id, 'createdCount' => $created, 'scannedCount' => $scanned]);
        } catch (\Throwable $exception) {
            $run->forceFill(['status_code' => 'FAILED', 'finished_at' => now(), 'scanned_count' => $scanned, 'error_count' => 1, 'error_summary' => mb_substr($exception->getMessage(), 0, 2000)])->save();
            throw $exception;
        }

        return $created;
    }

    /** @return array<string, mixed> */
    public function readiness(User $actor, int $caseId): array
    {
        $this->authorize($actor, 'cms.automation.view');
        $case = $this->scope->resolveVisibleCase($actor, $caseId, 'cms.automation.view');

        return $this->readinessSnapshot($case->load($this->readinessRelations()));
    }

    /** @return array<string, mixed> */
    public function dashboard(User $actor): array
    {
        $this->authorize($actor, 'cms.automation.view');
        $ids = $this->visibleCaseIds($actor);

        return [
            'openClosureCandidates' => CmsClosureCandidate::query()->whereIn('cms_recommendation_case_id', $ids)->whereIn('status_code', [CmsClosureCandidate::OPEN, CmsClosureCandidate::ACKNOWLEDGED])->count(),
            'openEscalationCandidates' => CmsEscalationCandidate::query()->whereIn('cms_recommendation_case_id', $ids)->whereIn('status_code', [CmsEscalationCandidate::OPEN, CmsEscalationCandidate::ACKNOWLEDGED])->count(),
            'recentReminderCount' => CmsAutomationAction::query()->whereIn('cms_recommendation_case_id', $ids)->where('action_type', 'REMINDER')->where('created_at', '>=', now()->subDays(7))->count(),
            'activeRules' => CmsAutomationRule::query()->where('status_code', CmsAutomationRule::ACTIVE)->count(),
            'lastRunAt' => CmsAutomationRun::query()->latest('finished_at')->value('finished_at'),
        ];
    }

    public function runs(User $actor)
    {
        $this->authorize($actor, 'cms.automation.view');
        $query = CmsAutomationRun::query()->with('rule')->latest('started_at');
        if (! $actor->hasGlobalEngagementAccess()) {
            $ids = $this->visibleCaseIds($actor);
            $query->whereHas('actions', fn (Builder $actions) => $actions->whereIn('cms_recommendation_case_id', $ids));
        }

        return $query->paginate(25);
    }

    /** @return array<string, mixed> */
    public function candidates(User $actor): array
    {
        $this->authorize($actor, 'cms.automation.view');
        $ids = $this->visibleCaseIds($actor);

        return [
            'closureCandidates' => CmsClosureCandidate::query()->whereIn('cms_recommendation_case_id', $ids)->with($this->candidateRelations())->latest('detected_at')->paginate(25),
            'escalationCandidates' => CmsEscalationCandidate::query()->whereIn('cms_recommendation_case_id', $ids)->with($this->candidateRelations())->latest('detected_at')->paginate(25),
        ];
    }

    public function reviewClosureCandidate(Request $request, int $id, string $action): CmsClosureCandidate
    {
        $actor = $request->user();
        $this->authorize($actor, 'cms.automation.review');
        if (strtoupper($action) === 'DISMISS') {
            $this->authorize($actor, 'cms.automation.dismiss');
        }
        $candidate = CmsClosureCandidate::query()->with('case')->findOrFail($id);
        $this->scope->resolveVisibleCase($actor, $candidate->cms_recommendation_case_id, 'cms.automation.view');
        $status = $this->candidateStatus($action, CmsClosureCandidate::class);
        $this->assertCandidateOpen($candidate->status_code, CmsClosureCandidate::class);
        $candidate->forceFill(['status_code' => $status, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $request->input('reviewNote')])->save();
        $this->recordAudit($actor->id, 'cms.automation.closure_candidate_reviewed', 'CMS closure-readiness candidate reviewed.', $candidate->cms_recommendation_case_id, ['candidateId' => $candidate->id, 'status' => $status]);

        return $candidate->fresh($this->candidateRelations());
    }

    public function reviewEscalationCandidate(Request $request, int $id, string $action): CmsEscalationCandidate
    {
        $actor = $request->user();
        $this->authorize($actor, 'cms.automation.review');
        if (strtoupper($action) === 'DISMISS') {
            $this->authorize($actor, 'cms.automation.dismiss');
        }
        $candidate = CmsEscalationCandidate::query()->with('case')->findOrFail($id);
        $this->scope->resolveVisibleCase($actor, $candidate->cms_recommendation_case_id, 'cms.automation.view');
        $status = $this->candidateStatus($action, CmsEscalationCandidate::class);
        $this->assertCandidateOpen($candidate->status_code, CmsEscalationCandidate::class);
        $candidate->forceFill(['status_code' => $status, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $request->input('reviewNote')])->save();
        $this->recordAudit($actor->id, 'cms.automation.escalation_candidate_reviewed', 'CMS escalation candidate reviewed; no notice was issued automatically.', $candidate->cms_recommendation_case_id, ['candidateId' => $candidate->id, 'status' => $status]);

        return $candidate->fresh($this->candidateRelations());
    }

    private function processReminders(CmsAutomationRule $rule, CmsAutomationRuleVersion $version, CmsAutomationRun $run, int &$scanned): int
    {
        $daysAhead = max(0, (int) data_get($version->configuration, 'daysAhead', 7));
        $cases = $this->openCases()->whereNotNull('effective_target_implementation_date')->whereDate('effective_target_implementation_date', '<=', today()->addDays($daysAhead))->get();
        $created = 0;
        foreach ($cases as $case) {
            $scanned++;
            $overdue = $case->effective_target_implementation_date?->isPast() ?? false;
            $dedupe = "cms:automation:{$rule->rule_code}:{$case->id}:{$case->effective_target_implementation_date?->toDateString()}:".($overdue ? 'overdue' : 'upcoming');
            $payload = ['type' => $overdue ? 'CMS_TARGET_DATE_OVERDUE' : 'CMS_TARGET_DATE_REMINDER', 'category' => $overdue ? 'OVERDUE' : 'DUE_DATE', 'priority' => $overdue ? 'URGENT' : 'HIGH', 'moduleCode' => 'CMS', 'title' => ($overdue ? 'Overdue CMS target date: ' : 'CMS target date approaching: ').$this->caseCode($case), 'message' => 'The effective target implementation date is '.$case->effective_target_implementation_date->format('M j, Y').'.', 'actionUrl' => "/compliance-management/recommendations/{$case->id}", 'actionLabel' => 'Open recommendation', 'subjectType' => CmsRecommendationCase::class, 'subjectId' => $case->id, 'subjectCode' => $this->caseCode($case), 'dedupeKey' => $dedupe, 'metadata' => ['caseId' => $case->id, 'targetDate' => $case->effective_target_implementation_date->toDateString()]];
            foreach ($this->caseRecipients($case) as $recipientId) {
                $notifications = $this->notifications->send([$recipientId], $payload);
                $notificationId = $notifications->first()?->id;
                $action = CmsAutomationAction::query()->firstOrCreate(['dedupe_key' => $dedupe.':'.$recipientId], ['cms_automation_run_id' => $run->id, 'cms_automation_rule_id' => $rule->id, 'cms_recommendation_case_id' => $case->id, 'action_type' => 'REMINDER', 'status_code' => $notificationId ? 'CREATED' : 'SKIPPED', 'target_user_id' => $recipientId, 'notification_id' => $notificationId, 'payload' => $payload]);
                $created += $action->wasRecentlyCreated ? 1 : 0;
            }
        }

        return $created;
    }

    private function processClosureCandidates(CmsAutomationRule $rule, CmsAutomationRuleVersion $version, CmsAutomationRun $run, int &$scanned): int
    {
        $created = 0;
        foreach ($this->openCases()->where('status_code', CmsRecommendationCase::STATUS_IMPLEMENTED)->get() as $case) {
            $scanned++;
            $snapshot = $this->readinessSnapshot($case->load($this->readinessRelations()));
            if (! $snapshot['eligible']) {
                continue;
            }
            $key = "cms:closure-candidate:{$case->id}:{$case->active_cycle_number}";
            $candidate = CmsClosureCandidate::query()->firstOrCreate(['detection_key' => $key], ['cms_recommendation_case_id' => $case->id, 'cms_automation_run_id' => $run->id, 'status_code' => CmsClosureCandidate::OPEN, 'detected_at' => now(), 'readiness_snapshot' => $snapshot]);
            if (! $candidate->wasRecentlyCreated) {
                continue;
            }
            $created++;
            $this->action($run, $rule, $case, 'CLOSURE_CANDIDATE', $key, $candidate->id, $snapshot);
            $this->notifyCase($case, 'CMS_CLOSURE_CANDIDATE', 'Closure candidate ready', 'This recommendation satisfies the formal closure-readiness checklist and is awaiting professional review.', "{$key}:notice");
            $this->event($case, CmsRecommendationEvent::EVENT_AUTOMATION_CLOSURE_CANDIDATE, ['candidateId' => $candidate->id]);
        }

        return $created;
    }

    private function processEscalationCandidates(CmsAutomationRule $rule, CmsAutomationRuleVersion $version, CmsAutomationRun $run, int &$scanned): int
    {
        $overdueDays = max(1, (int) data_get($version->configuration, 'overdueDays', 30));
        $severity = strtoupper((string) data_get($version->configuration, 'severityCode', 'HIGH'));
        $created = 0;
        foreach ($this->openCases()->whereIn('status_code', [CmsRecommendationCase::STATUS_MONITORING, CmsRecommendationCase::STATUS_PARTIALLY_IMPLEMENTED])->whereNotNull('effective_target_implementation_date')->get() as $case) {
            $scanned++;
            $overdue = $case->effective_target_implementation_date?->diffInDays(today(), false) ?? 0;
            if ($overdue < $overdueDays || $case->activeEscalation) {
                continue;
            }
            $key = "cms:escalation-candidate:{$case->id}:{$case->effective_target_implementation_date?->toDateString()}";
            $candidate = CmsEscalationCandidate::query()->firstOrCreate(['detection_key' => $key], ['cms_recommendation_case_id' => $case->id, 'cms_automation_run_id' => $run->id, 'status_code' => CmsEscalationCandidate::OPEN, 'trigger_code' => 'OVERDUE_TARGET_DATE', 'severity_code' => $severity, 'reason' => "The effective target date is {$overdue} days overdue.", 'detected_at' => now(), 'trigger_snapshot' => ['caseId' => $case->id, 'caseStatus' => $case->status_code, 'targetDate' => $case->effective_target_implementation_date?->toDateString(), 'overdueDays' => $overdue, 'automationOnly' => true]]);
            if (! $candidate->wasRecentlyCreated) {
                continue;
            }
            $created++;
            $this->action($run, $rule, $case, 'ESCALATION_CANDIDATE', $key, $candidate->id, ['triggerCode' => 'OVERDUE_TARGET_DATE', 'severityCode' => $severity, 'overdueDays' => $overdue]);
            $this->notifyCase($case, 'CMS_ESCALATION_CANDIDATE', 'Escalation candidate requires review', 'Automation identified a materially overdue recommendation. No escalation notice has been issued.', "{$key}:notice");
            $this->event($case, CmsRecommendationEvent::EVENT_AUTOMATION_ESCALATION_CANDIDATE, ['candidateId' => $candidate->id, 'overdueDays' => $overdue]);
        }

        return $created;
    }

    private function openCases(): Builder
    {
        return CmsRecommendationCase::query()
            ->with(['recommendation', 'leadResponsibleOffice', 'currentAssignment.user', 'activeEscalation'])
            ->whereNotIn('status_code', [CmsRecommendationCase::STATUS_CLOSED, CmsRecommendationCase::STATUS_ACCEPTED_RISK, CmsRecommendationCase::STATUS_NO_LONGER_APPLICABLE]);
    }

    /** @return array<string, mixed> */
    private function readinessSnapshot(CmsRecommendationCase $case): array
    {
        $final = CmsValidationVersion::query()->whereHas('review', fn ($q) => $q->where('cms_recommendation_case_id', $case->id))->where('status_code', CmsValidationVersion::STATUS_FINALIZED)->where('final_conclusion_code', 'IMPLEMENTED')->latest('finalized_at')->first();
        $checks = [
            ['code' => 'case_status', 'label' => 'Recommendation is IMPLEMENTED', 'passed' => $case->status_code === CmsRecommendationCase::STATUS_IMPLEMENTED, 'blocking' => true],
            ['code' => 'validation', 'label' => 'Finalized validation concludes IMPLEMENTED', 'passed' => (bool) $final, 'blocking' => true],
            ['code' => 'active_validation', 'label' => 'No active validation review', 'passed' => ! $case->activeValidationReview, 'blocking' => true],
            ['code' => 'extension', 'label' => 'No unresolved target-date extension', 'passed' => ! $case->unresolvedTargetDateExtensionRequest, 'blocking' => true],
            ['code' => 'escalation', 'label' => 'No unresolved escalation', 'passed' => ! $case->activeEscalation, 'blocking' => true],
            ['code' => 'closure', 'label' => 'No unresolved closure request', 'passed' => ! $case->unresolvedClosureRequest, 'blocking' => true],
            ['code' => 'disposition', 'label' => 'No unresolved disposition request', 'passed' => ! $case->unresolvedDispositionRequest, 'blocking' => true],
            ['code' => 'reopening', 'label' => 'No unresolved reopening request', 'passed' => ! $case->unresolvedReopeningRequest, 'blocking' => true],
        ];
        return ['eligible' => ! collect($checks)->contains(fn (array $check): bool => $check['blocking'] && ! $check['passed']), 'evaluatedAt' => now()->toISOString(), 'checklist' => $checks, 'finalizedValidation' => $final?->only(['id', 'final_conclusion_code', 'finalized_at']), 'activeCycleNumber' => $case->active_cycle_number];
    }

    private function action(CmsAutomationRun $run, CmsAutomationRule $rule, CmsRecommendationCase $case, string $type, string $key, int $candidateId, array $payload): void
    {
        CmsAutomationAction::query()->firstOrCreate(['dedupe_key' => $key], ['cms_automation_run_id' => $run->id, 'cms_automation_rule_id' => $rule->id, 'cms_recommendation_case_id' => $case->id, 'action_type' => $type, 'status_code' => 'CREATED', 'candidate_type' => $type === 'CLOSURE_CANDIDATE' ? CmsClosureCandidate::class : CmsEscalationCandidate::class, 'candidate_id' => $candidateId, 'payload' => $payload]);
    }

    private function notifyCase(CmsRecommendationCase $case, string $type, string $title, string $message, string $dedupe): void
    {
        $this->notifications->send($this->caseRecipients($case)->merge(User::query()->where('is_active', true)->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'cms.automation.manage'))->pluck('id'))->unique(), ['type' => $type, 'category' => 'CMS_AUTOMATION', 'priority' => 'HIGH', 'moduleCode' => 'CMS', 'title' => $title, 'message' => $message, 'actionUrl' => "/compliance-management/recommendations/{$case->id}", 'actionLabel' => 'Review CMS candidate', 'subjectType' => CmsRecommendationCase::class, 'subjectId' => $case->id, 'subjectCode' => $this->caseCode($case), 'dedupeKey' => $dedupe]);
    }

    private function caseRecipients(CmsRecommendationCase $case): \Illuminate\Support\Collection
    {
        return collect([$case->currentAssignment?->user_id])->filter()->merge(User::query()->where('office_id', $case->lead_responsible_office_id)->where('is_active', true)->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'access.auditee_scope'))->pluck('id'))->unique();
    }

    private function visibleCaseIds(User $actor): \Illuminate\Support\Collection
    {
        return $this->scope->visibleCases(CmsRecommendationCase::query(), $actor, 'cms.automation.view')->pluck('cms_recommendation_cases.id');
    }

    private function candidateRelations(): array
    {
        return ['case.recommendation', 'case.leadResponsibleOffice', 'case.currentAssignment.user', 'reviewer'];
    }

    private function readinessRelations(): array
    {
        return ['actionPlan.acceptedVersion', 'activeValidationReview', 'unresolvedTargetDateExtensionRequest', 'activeEscalation', 'unresolvedClosureRequest', 'unresolvedDispositionRequest', 'unresolvedReopeningRequest'];
    }

    private function caseCode(CmsRecommendationCase $case): string
    {
        return sprintf('CMS-REC-%06d', $case->id);
    }

    private function authorize(User $actor, string $permission): void
    {
        throw_unless($this->scope->isUsableAccount($actor) && $actor->hasPermission($permission), new HttpException(403, 'You are not authorised for CMS automation.'));
    }

    private function candidateStatus(string $action, string $class): string
    {
        $action = strtoupper($action);
        return match ($action) {
            'ACKNOWLEDGE' => $class === CmsClosureCandidate::class ? CmsClosureCandidate::ACKNOWLEDGED : CmsEscalationCandidate::ACKNOWLEDGED,
            'DISMISS' => $class === CmsClosureCandidate::class ? CmsClosureCandidate::DISMISSED : CmsEscalationCandidate::DISMISSED,
            default => throw ValidationException::withMessages(['action' => ['Only acknowledge or dismiss is supported by automation review.']]),
        };
    }

    private function assertCandidateOpen(string $status, string $class): void
    {
        $open = $class === CmsClosureCandidate::class ? [CmsClosureCandidate::OPEN, CmsClosureCandidate::ACKNOWLEDGED] : [CmsEscalationCandidate::OPEN, CmsEscalationCandidate::ACKNOWLEDGED];
        throw_unless(in_array($status, $open, true), new HttpException(409, 'This automation candidate has already been resolved.'));
    }

    private function event(CmsRecommendationCase $case, string $event, array $metadata): void
    {
        CmsRecommendationEvent::query()->firstOrCreate(['idempotency_key' => "cms.automation:{$event}:{$case->id}:".sha1(json_encode($metadata))], ['cms_recommendation_case_id' => $case->id, 'cms_recommendation_id' => $case->cms_recommendation_id, 'event_code' => $event, 'source_module' => 'CMS', 'actor_id' => null, 'previous_status' => $case->status_code, 'new_status' => $case->status_code, 'event_metadata' => ['automation' => true, ...$metadata], 'created_at' => now()]);
    }

    private function recordAudit(?int $actorId, string $action, string $description, ?int $caseId, array $metadata): void
    {
        DB::table('activity_logs')->insert(['user_id' => $actorId, 'action' => $action, 'description' => $description, 'metadata' => json_encode(['module' => 'CMS', 'caseId' => $caseId, ...$metadata]), 'created_at' => now()]);
        DB::table('audit_logs')->insert(['user_id' => $actorId, 'action' => $action, 'auditable_type' => CmsAutomationRule::class, 'auditable_id' => $metadata['ruleId'] ?? ($metadata['candidateId'] ?? $caseId ?? 0), 'new_values' => json_encode($metadata), 'metadata' => json_encode(['module' => 'CMS', 'caseId' => $caseId, ...$metadata]), 'created_at' => now()]);
    }
}
