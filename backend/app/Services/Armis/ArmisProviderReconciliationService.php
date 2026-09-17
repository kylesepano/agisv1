<?php

namespace App\Services;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Integrations\Aems\ArmisResourcePlanningGateway;
use App\Integrations\Aems\InterimIapResourcePlanningGateway;
use App\Models\ArmisProviderAuthorityDecision;
use App\Models\ArmisProviderReconciliationReview;
use App\Models\ArmisProviderReconciliationRun;
use App\Models\ArmisWorkflowEvent;
use App\Models\AuditEngagement;
use App\Models\EngagementTeam;
use App\Models\Office;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\ActivityLog;
use App\Models\AuditLog;

/**
 * Compares historical IAP and ARMIS read ledgers for quality evidence.
 *
 * Reconciliation snapshots and all review/authority decisions are append-only.
 * No comparison or decision writes either provider's operational records.
 */
class ArmisProviderReconciliationService
{
    private const SOURCE_QUERY_VERSION = 'ARMIS-6B-v1';

    private const MODE_AUTHORITATIVE = 'ARMIS_AUTHORITATIVE';

    public function __construct(
        private readonly InterimIapResourcePlanningGateway $interim,
        private readonly ArmisResourcePlanningGateway $armis,
        private readonly ResourcePlanningGateway $provider,
        private readonly RuntimeConfiguration $runtime,
        private readonly NotificationService $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function generate(Request $request, array $filters = []): ArmisProviderReconciliationRun
    {
        $actor = $this->actor($request);
        $this->authorize($actor, 'armis.provider.reconcile');
        $this->requireGlobalScope($actor);

        $fiscalYear = (int) ($filters['fiscalYear'] ?? $this->runtime->currentFiscalYear());
        abort_if($fiscalYear < 2000 || $fiscalYear > 2200, 422, 'The fiscal year is invalid.');
        // Reconciliation is now a historical/source-quality snapshot only.
        // ARMIS remains the sole active provider throughout the operation.
        $mode = self::MODE_AUTHORITATIVE;

        $officeIds = $this->scopeOfficeIds($actor);
        $profiles = \App\Models\ArmisResourceProfile::query()
            ->whereIn('office_id', $officeIds)
            ->where('status', 'ACTIVE')
            ->whereHas('user', fn (Builder $query): Builder => $query->where('is_active', true))
            ->orderBy('id')
            ->get();
        $engagements = AuditEngagement::query()
            ->whereNotIn('status', ['CANCELLED'])
            ->whereHas('offices', fn (Builder $query): Builder => $query->whereIn('offices.id', $officeIds))
            ->with(['teamMembers', 'offices:id'])
            ->orderBy('id')
            ->get();

        $periodStart = Carbon::create($fiscalYear, 1, 1)->startOfDay();
        $periodEnd = Carbon::create($fiscalYear, 12, 31)->endOfDay();
        $items = [];

        foreach ($profiles as $profile) {
            $userId = (int) $profile->user_id;
            $resourceCode = (string) $profile->resource_code;
            $this->appendComparison(
                $items,
                'CAPACITY',
                "user:{$userId}:year:{$fiscalYear}",
                ['userId' => $userId, 'resourceCode' => $resourceCode, 'fiscalYear' => $fiscalYear],
                $this->interim->capacityFor($fiscalYear, $userId),
                $this->armis->capacityFor($fiscalYear, $userId),
            );
            $this->appendComparison(
                $items,
                'SKILLS',
                "user:{$userId}",
                ['userId' => $userId, 'resourceCode' => $resourceCode],
                $this->interim->skills([$userId])[$userId] ?? [],
                $this->armis->skills([$userId])[$userId] ?? [],
            );
            $this->appendComparison(
                $items,
                'UNAVAILABILITY',
                "user:{$userId}:year:{$fiscalYear}",
                ['userId' => $userId, 'resourceCode' => $resourceCode, 'fiscalYear' => $fiscalYear],
                $this->interim->unavailability($userId, $periodStart, $periodEnd),
                $this->armis->unavailability($userId, $periodStart, $periodEnd),
            );
        }

        foreach ($engagements as $engagement) {
            $engagementKey = "engagement:{$engagement->id}";
            $this->appendComparison(
                $items,
                'REQUIREMENTS',
                $engagementKey,
                ['engagementId' => $engagement->id, 'engagementCode' => $engagement->engagement_code],
                $this->interim->requirements($engagement),
                $this->armis->requirements($engagement),
            );
            $this->appendComparison(
                $items,
                'ENGAGEMENT_ACTUALS',
                $engagementKey,
                ['engagementId' => $engagement->id, 'engagementCode' => $engagement->engagement_code],
                (float) $engagement->actual_person_days,
                $this->armis->engagementActualPersonDays($engagement),
            );

            foreach ($engagement->teamMembers as $teamMember) {
                $this->appendComparison(
                    $items,
                    'ASSIGNMENT_ACTUALS',
                    "engagement:{$engagement->id}:user:{$teamMember->user_id}",
                    [
                        'engagementId' => $engagement->id,
                        'engagementCode' => $engagement->engagement_code,
                        'userId' => $teamMember->user_id,
                    ],
                    (float) $teamMember->actual_person_days,
                    $this->armis->assignmentActualPersonDays($teamMember),
                );
            }
        }

        $summary = $this->summarize($items);
        $scope = [
            'officeIds' => $officeIds,
            'resourceProfileIds' => $profiles->modelKeys(),
            'engagementIds' => $engagements->modelKeys(),
            'globalOfficeScope' => $actor->hasGlobalOfficeAccess(),
        ];
        $filters = ['fiscalYear' => $fiscalYear];
        $checksum = hash('sha256', json_encode([
            'sourceQueryVersion' => self::SOURCE_QUERY_VERSION,
            'providerMode' => $mode,
            'filters' => $filters,
            'scope' => $scope,
            'items' => $items,
        ], JSON_THROW_ON_ERROR));

        $run = DB::transaction(function () use ($request, $actor, $mode, $fiscalYear, $filters, $scope, $items, $summary, $checksum): ArmisProviderReconciliationRun {
            $run = ArmisProviderReconciliationRun::query()->create([
                'run_uuid' => (string) Str::uuid(),
                'source_query_version' => self::SOURCE_QUERY_VERSION,
                'fiscal_year' => $fiscalYear,
                'provider_mode' => $mode,
                'status' => 'GENERATED',
                'filters' => $filters,
                'scope_snapshot' => $scope,
                'result_snapshot' => $items,
                'summary' => $summary,
                'result_checksum_sha256' => $checksum,
                'generated_by' => $actor->id,
                'generated_at' => now(),
            ]);
            $this->event($run, 'ARMIS_RECONCILIATION_GENERATED', null, 'GENERATED', $actor, null, $summary);
            $this->record($request, 'armis.provider.reconciliation.generated', 'Generated an immutable IAP versus ARMIS reconciliation snapshot.', $run, null, [
                'runId' => $run->id,
                'checksumSha256' => $checksum,
                'summary' => $summary,
            ]);

            return $run;
        });

        DB::afterCommit(fn () => $this->notifyReviewers($run, $actor));

        return $run->load(['generator', 'reviews', 'authorityDecisions']);
    }

    /** @return \Illuminate\Support\Collection<int, ArmisProviderReconciliationRun> */
    public function runs(User $user)
    {
        $this->authorize($user, 'armis.provider.view');
        $officeIds = $this->scopeOfficeIds($user);

        return ArmisProviderReconciliationRun::query()
            ->with(['generator', 'reviews.reviewer', 'authorityDecisions.decider'])
            ->latest('generated_at')
            ->get()
            ->filter(fn (ArmisProviderReconciliationRun $run): bool => $this->runVisible($run, $officeIds, $user))
            ->values();
    }

    public function show(User $user, int $runId): ArmisProviderReconciliationRun
    {
        $this->authorize($user, 'armis.provider.view');
        $run = ArmisProviderReconciliationRun::query()
            ->with(['generator', 'reviews.reviewer', 'authorityDecisions.decider'])
            ->findOrFail($runId);
        abort_unless($this->runVisible($run, $this->scopeOfficeIds($user), $user), 404, 'The ARMIS reconciliation run is not in your scope.');

        return $run;
    }

    /** @param array<string, mixed> $data */
    public function review(Request $request, int $runId, array $data): ArmisProviderReconciliationReview
    {
        $actor = $this->actor($request);
        $this->authorize($actor, 'armis.provider.review');
        $this->requireGlobalScope($actor);

        $review = DB::transaction(function () use ($request, $actor, $runId, $data): ArmisProviderReconciliationReview {
            $run = ArmisProviderReconciliationRun::query()->with('reviews')->lockForUpdate()->findOrFail($runId);
            abort_if($run->reviews->isNotEmpty(), 409, 'This reconciliation run already has an immutable review.');
            abort_if((int) $run->generated_by === (int) $actor->id, 403, 'The reconciliation generator cannot independently review the same run.');

            $decision = strtoupper((string) ($data['decision'] ?? ''));
            $comment = trim((string) ($data['comment'] ?? ''));
            $supplied = $data['discrepancyDecisions'] ?? [];
            $discrepancies = collect($run->result_snapshot ?? [])
                ->filter(fn (array $item): bool => ($item['status'] ?? null) === 'DISCREPANCY')
                ->mapWithKeys(fn (array $item): array => [(string) $item['key'] => true]);
            $decisions = collect($supplied)->mapWithKeys(
                fn ($value, $key): array => [(string) $key => strtoupper((string) $value)],
            );

            abort_unless(in_array($decision, ['ACCEPTED', 'REJECTED'], true), 422, 'The reconciliation review decision is invalid.');
            abort_if(mb_strlen($comment) < 10, 422, 'A reconciliation review comment of at least 10 characters is required.');
            abort_if($discrepancies->keys()->diff($decisions->keys())->isNotEmpty(), 422, 'Every discrepancy must have an explicit review decision.');
            abort_if($decisions->keys()->diff($discrepancies->keys())->isNotEmpty(), 422, 'The review contains an unknown discrepancy key.');
            abort_if($decisions->contains(fn (string $value): bool => ! in_array($value, ['ACCEPT', 'REJECT'], true)), 422, 'Discrepancy decisions must be ACCEPT or REJECT.');

            $allAccepted = $decisions->every(fn (string $value): bool => $value === 'ACCEPT');
            abort_if($decision === 'ACCEPTED' && ! $allAccepted, 422, 'An accepted reconciliation requires every discrepancy to be explicitly accepted.');
            abort_if($decision === 'REJECTED' && $discrepancies->isNotEmpty() && $allAccepted, 422, 'A rejected reconciliation requires at least one rejected discrepancy.');

            $review = ArmisProviderReconciliationReview::query()->create([
                'reconciliation_run_id' => $run->id,
                'decision' => $decision,
                'discrepancy_decisions' => $decisions->all(),
                'comment' => $comment,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);
            $this->event($run, "ARMIS_RECONCILIATION_REVIEW_{$decision}", 'GENERATED', $decision, $actor, $comment, [
                'reviewId' => $review->id,
            ]);
            $this->record($request, 'armis.provider.reconciliation.reviewed', "Marked ARMIS reconciliation {$decision}.", $review, null, [
                'runId' => $run->id,
                'decision' => $decision,
                'discrepancyDecisions' => $decisions->all(),
            ]);

            return $review;
        });

        if ($review->decision === 'ACCEPTED') {
            DB::afterCommit(fn () => $this->notifyAuthority($review->run()->firstOrFail(), $actor));
        }

        return $review->load(['run', 'reviewer']);
    }

    public function activate(Request $request, int $runId, string $reason): ArmisProviderAuthorityDecision
    {
        abort(409, 'ARMIS is already the sole operational resource provider; provider activation is not available.');
    }

    public function rollback(Request $request, string $reason): ArmisProviderAuthorityDecision
    {
        abort(409, 'ARMIS is the sole operational resource provider; rollback to another provider is not available.');
    }

    /** @return array<string, mixed> */
    public function status(User $user): array
    {
        $this->authorize($user, 'armis.provider.view');
        $run = $this->runs($user)->first();
        $review = $run?->reviews?->first();
        $authority = ArmisProviderAuthorityDecision::query()->with('decider')->latest('decided_at')->first();

        return [
            'provider' => $this->provider->status(),
            'latestReconciliation' => $run ? $this->runSummary($run) : null,
            'latestReview' => $review ? [
                'id' => $review->id,
                'decision' => $review->decision,
                'comment' => $review->comment,
                'reviewedAt' => $review->reviewed_at?->toISOString(),
                'reviewedBy' => $review->reviewer?->only(['id', 'name']),
            ] : null,
            'latestAuthorityDecision' => $authority ? [
                'id' => $authority->id,
                'decisionCode' => $authority->decision_code,
                'fromMode' => $authority->from_mode,
                'toMode' => $authority->to_mode,
                'reason' => $authority->reason,
                'decidedAt' => $authority->decided_at?->toISOString(),
                'decidedBy' => $authority->decider?->only(['id', 'name']),
            ] : null,
            'authorityEligible' => false,
            'authorityControls' => [
                'providerSwitchingEnabled' => false,
                'armisSoleOperationalProvider' => true,
                'historicalReconciliationReviewRequired' => true,
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function summarize(array $items): array
    {
        $matches = collect($items)->where('status', 'MATCH')->count();
        $discrepancies = collect($items)->where('status', 'DISCREPANCY')->pluck('key')->values()->all();

        return [
            'total' => count($items),
            'matches' => $matches,
            'discrepancies' => count($discrepancies),
            'discrepancyKeys' => $discrepancies,
            'reviewRequired' => count($discrepancies) > 0,
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function appendComparison(array &$items, string $category, string $key, array $subject, mixed $iap, mixed $armis): void
    {
        $iap = $this->normalize($iap);
        $armis = $this->normalize($armis);
        $items[] = [
            'category' => $category,
            'key' => "{$category}:{$key}",
            'subject' => $subject,
            'iap' => $iap,
            'armis' => $armis,
            'status' => $this->same($iap, $armis) ? 'MATCH' : 'DISCREPANCY',
        ];
    }

    private function same(mixed $left, mixed $right): bool
    {
        return json_encode($left, JSON_THROW_ON_ERROR) === json_encode($right, JSON_THROW_ON_ERROR);
    }

    private function normalize(mixed $value): mixed
    {
        if (is_float($value) || is_int($value)) {
            return round((float) $value, 2);
        }
        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalize($item);
        }
        if (array_is_list($normalized)) {
            usort($normalized, fn (mixed $left, mixed $right): int => strcmp(
                json_encode($left, JSON_THROW_ON_ERROR),
                json_encode($right, JSON_THROW_ON_ERROR),
            ));
        } else {
            ksort($normalized);
        }

        return $normalized;
    }

    /** @return list<int> */
    private function scopeOfficeIds(User $user): array
    {
        return $user->hasGlobalOfficeAccess()
            ? Office::query()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : [(int) $user->office_id];
    }

    private function runVisible(ArmisProviderReconciliationRun $run, array $officeIds, User $user): bool
    {
        if ($user->hasGlobalOfficeAccess()) {
            return true;
        }

        return collect($run->scope_snapshot['officeIds'] ?? [])->map(fn ($id): int => (int) $id)
            ->intersect($officeIds)->isNotEmpty();
    }

    /** @return array<string, mixed> */
    private function runSummary(ArmisProviderReconciliationRun $run): array
    {
        return [
            'id' => $run->id,
            'displayCode' => $run->display_code,
            'uuid' => $run->run_uuid,
            'fiscalYear' => $run->fiscal_year,
            'providerMode' => $run->provider_mode,
            'status' => $run->status,
            'summary' => $run->summary,
            'checksumSha256' => $run->result_checksum_sha256,
            'generatedAt' => $run->generated_at?->toISOString(),
            'generatedBy' => $run->generator?->only(['id', 'name']),
            'review' => $run->reviews->first()?->only(['id', 'decision', 'comment', 'reviewed_by', 'reviewed_at']),
            'authorityDecision' => $run->authorityDecisions->first()?->only(['id', 'decision_code', 'from_mode', 'to_mode', 'reason', 'decided_by', 'decided_at']),
        ];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function authorize(User $user, string $permission): void
    {
        abort_unless($user->hasPermission($permission), 403, 'You are not authorized for this ARMIS provider action.');
    }

    private function requireGlobalScope(User $user): void
    {
        abort_unless($user->hasGlobalOfficeAccess(), 403, 'Provider reconciliation and authority decisions require global office scope.');
    }

    private function notifyReviewers(ArmisProviderReconciliationRun $run, User $actor): void
    {
        $recipients = User::query()->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereHas('roles.permissions', fn (Builder $permissions) => $permissions->where('code', 'armis.provider.review'))
                    ->orWhereHas('role.permissions', fn (Builder $permissions) => $permissions->where('code', 'armis.provider.review'));
            })->where('users.id', '<>', $actor->id)->pluck('id');
        $this->notifications->send($recipients, [
            'actorId' => $actor->id,
            'type' => 'ARMIS_RECONCILIATION',
            'category' => 'SYSTEM',
            'priority' => 'HIGH',
            'moduleCode' => 'ARMIS',
            'title' => 'ARMIS reconciliation awaiting review',
            'message' => "{$run->display_code} contains {$run->summary['discrepancies']} provider discrepancies requiring independent review.",
            'actionUrl' => '/audit-resource-management/reports',
            'actionLabel' => 'Review ARMIS reconciliation',
            'subjectType' => $run::class,
            'subjectId' => $run->id,
            'subjectCode' => $run->display_code,
            'dedupeKey' => "armis-reconciliation:{$run->id}:review",
        ]);
    }

    private function notifyAuthority(ArmisProviderReconciliationRun $run, User $actor): void
    {
        $recipients = User::query()->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereHas('roles.permissions', fn (Builder $permissions) => $permissions->where('code', 'armis.provider.switch'))
                    ->orWhereHas('role.permissions', fn (Builder $permissions) => $permissions->where('code', 'armis.provider.switch'));
            })->where('users.id', '<>', $actor->id)->pluck('id');
        $this->notifications->send($recipients, [
            'actorId' => $actor->id,
            'type' => 'ARMIS_AUTHORITY',
            'category' => 'SYSTEM',
            'priority' => 'HIGH',
            'moduleCode' => 'ARMIS',
            'title' => 'ARMIS authority decision ready',
            'message' => "{$run->display_code} was independently accepted and is ready for authority approval.",
            'actionUrl' => '/audit-resource-management/reports',
            'actionLabel' => 'Review ARMIS authority gate',
            'subjectType' => $run::class,
            'subjectId' => $run->id,
            'subjectCode' => $run->display_code,
            'dedupeKey' => "armis-reconciliation:{$run->id}:authority",
        ]);
    }

    /** @param array<string, mixed>|null $oldValues @param array<string, mixed>|null $newValues */
    private function record(Request $request, string $action, string $description, Model $subject, ?array $oldValues, ?array $newValues): void
    {
        $actor = $request->user();
        $metadata = ['module' => 'ARMIS', 'recordType' => $subject::class, 'recordId' => $subject->id];
        ActivityLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ]);
        AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ]);
    }

    /** @param array<string, mixed>|null $metadata */
    private function event(Model $subject, string $code, ?string $from, ?string $to, User $actor, ?string $reason = null, ?array $metadata = null): void
    {
        ArmisWorkflowEvent::query()->create([
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'event_code' => $code,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor->id,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
