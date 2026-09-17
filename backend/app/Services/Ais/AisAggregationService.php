<?php

namespace App\Services\Ais;

use App\Models\AisAggregationSnapshot;
use App\Models\ArmisEngagementAssignment;
use App\Models\ArmisResourceProfile;
use App\Models\AuditEngagement;
use App\Models\AuditEvidence;
use App\Models\AuditFinding;
use App\Models\CmsRecommendationCase;
use App\Models\InternalAuditPlan;
use App\Models\Office;
use App\Models\User;
use App\Services\AemsAccessService;
use App\Services\ArmisResourceService;
use App\Services\Cms\CmsRecommendationScopeService;
use App\Services\IapPlanGuard;
use App\Support\ActivityRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use App\Models\AuditLog;

/**
 * Reads source modules through their existing scope services and stores only
 * immutable AIS-owned aggregation snapshots.
 */
class AisAggregationService
{
    public const CONTRACT_VERSION = 'AIS-1.0';
    public const SOURCE_QUERY_VERSION = 'AIS-1-v1';
    public const DASHBOARD_VERSION = 'AIS-2.0';

    public function __construct(
        private readonly IapPlanGuard $iap,
        private readonly AemsAccessService $aems,
        private readonly CmsRecommendationScopeService $cms,
        private readonly ArmisResourceService $armis,
        private readonly AisIntegrationHealthService $integration,
    ) {}

    /** @return array<string, mixed> */
    public function overview(User $user): array
    {
        $this->authorize($user);
        $integration = $this->integration->assertReady($user);
        $metrics = $this->aggregate($user);
        $latest = $this->snapshots($user, 1)->first();

        return [
            'contractVersion' => self::CONTRACT_VERSION,
            'sourceQueryVersion' => self::SOURCE_QUERY_VERSION,
            'generatedAt' => now()->toIso8601String(),
            'scope' => $this->scopeSnapshot($user),
            'integration' => [
                'contractVersion' => $integration['integrationContractVersion'],
                'status' => $integration['integrationStatus'],
                'reconciliation' => $integration['reconciliation'],
            ],
            'metrics' => $metrics,
            'latestSnapshot' => $latest ? $this->snapshotData($latest) : null,
            'sourceModes' => collect(['CORE', 'IAP', 'AEMS', 'CMS', 'ARMIS'])
                ->map(fn (string $module): array => ['module' => $module, 'mode' => 'READ_ONLY', 'status' => 'AVAILABLE'])
                ->values(),
        ];
    }

    /**
     * Returns the AIS-2 read-only analytical view. The view is derived from
     * the already scoped AIS-1 metrics and actor-owned immutable snapshots;
     * it never becomes an authority for a source-module decision.
     *
     * @return array<string, mixed>
     */
    public function dashboard(User $user): array
    {
        $overview = $this->overview($user);
        $metrics = $overview['metrics'];
        $snapshots = $this->snapshots($user, 12)
            ->sortBy('generated_at')
            ->values();

        return [
            'dashboardVersion' => self::DASHBOARD_VERSION,
            'contractVersion' => self::CONTRACT_VERSION,
            'generatedAt' => now()->toIso8601String(),
            'scope' => $overview['scope'],
            'sourceModes' => $overview['sourceModes'],
            'metrics' => $metrics,
            'headline' => [
                'approvedIapPlans' => $metrics['iap']['approvedPlans'],
                'activeEngagements' => $metrics['aems']['activeEngagements'],
                'findingsAwaitingReview' => $this->statusValue($metrics['aems']['findingsByStatus'], 'PENDING_REVIEW'),
                'findingsAwaitingResponse' => $this->statusValue($metrics['aems']['findingsByStatus'], 'AWAITING_MANAGEMENT_RESPONSE'),
                'evidenceAwaitingAssessment' => $this->statusValue($metrics['aems']['evidenceByOutcome'], 'FOR_ASSESSMENT')
                    + $this->statusValue($metrics['aems']['evidenceByOutcome'], 'ADDITIONAL_REQUIRED'),
                'openCmsCases' => $metrics['cms']['openCases'],
                'overdueCmsCases' => $metrics['cms']['overdueCases'],
                'approvedArmisAssignments' => $metrics['armis']['approvedAssignments'],
                'plannedPersonDays' => $metrics['armis']['plannedPersonDays'],
            ],
            'distributions' => [
                'engagementStatuses' => $this->distribution($metrics['aems']['engagementsByStatus']),
                'findingStatuses' => $this->distribution($metrics['aems']['findingsByStatus']),
                'evidenceOutcomes' => $this->distribution($metrics['aems']['evidenceByOutcome']),
                'cmsStatuses' => $this->distribution($metrics['cms']['casesByStatus']),
                'armisResourceStatuses' => $this->distribution($metrics['armis']['resourcesByStatus']),
            ],
            'snapshotTrend' => $snapshots->map(fn (AisAggregationSnapshot $snapshot): array => [
                'period' => $snapshot->generated_at?->format('Y-m-d H:i'),
                'snapshotCode' => $snapshot->display_code,
                'activeEngagements' => (int) data_get($snapshot->metrics, 'aems.activeEngagements', 0),
                'openCmsCases' => (int) data_get($snapshot->metrics, 'cms.openCases', 0),
                'overdueCmsCases' => (int) data_get($snapshot->metrics, 'cms.overdueCases', 0),
                'plannedPersonDays' => (float) data_get($snapshot->metrics, 'armis.plannedPersonDays', 0),
            ])->all(),
            'attention' => [
                ['code' => 'FINDINGS_REVIEW', 'label' => 'Findings awaiting review', 'value' => $this->statusValue($metrics['aems']['findingsByStatus'], 'PENDING_REVIEW'), 'tone' => 'warning'],
                ['code' => 'EVIDENCE_ASSESSMENT', 'label' => 'Evidence requiring assessment', 'value' => $this->statusValue($metrics['aems']['evidenceByOutcome'], 'FOR_ASSESSMENT') + $this->statusValue($metrics['aems']['evidenceByOutcome'], 'ADDITIONAL_REQUIRED'), 'tone' => 'warning'],
                ['code' => 'CMS_OVERDUE', 'label' => 'Overdue CMS cases', 'value' => $metrics['cms']['overdueCases'], 'tone' => 'danger'],
                ['code' => 'RESOURCE_ASSIGNMENTS', 'label' => 'Approved ARMIS assignments', 'value' => $metrics['armis']['approvedAssignments'], 'tone' => 'info'],
            ],
            'latestSnapshot' => $overview['latestSnapshot'],
            'limitations' => [
                'snapshotTrend' => $snapshots->isNotEmpty() ? 'Actor-owned immutable snapshots only.' : 'Generate an AIS-1 snapshot to establish a trend baseline.',
                'decisionAuthority' => 'Source modules retain all professional decisions and workflow authority.',
                'exports' => 'Protected reports and exports require the AIS-3 ais.export permission.',
            ],
        ];
    }

    /** @return Collection<int, AisAggregationSnapshot> */
    public function snapshots(User $user, int $limit = 25): Collection
    {
        $this->authorize($user);

        return AisAggregationSnapshot::query()
            ->with('generator:id,name,employee_id')
            ->where('generated_by', $user->id)
            ->latest('generated_at')
            ->limit($limit)
            ->get();
    }

    public function generate(Request $request): AisAggregationSnapshot
    {
        $actor = $request->user();
        $this->authorize($actor);
        $this->integration->assertReady($actor);
        $metrics = $this->aggregate($actor);
        $encoded = json_encode($metrics, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $snapshot = DB::transaction(function () use ($actor, $metrics, $encoded): AisAggregationSnapshot {
            $snapshot = AisAggregationSnapshot::query()->create([
                'snapshot_code' => 'AIS-SNP-'.strtoupper(Str::random(12)),
                'contract_version' => self::CONTRACT_VERSION,
                'source_query_version' => self::SOURCE_QUERY_VERSION,
                'scope_snapshot' => $this->scopeSnapshot($actor),
                'source_versions' => $this->sourceVersions(),
                'metrics' => $metrics,
                'metrics_checksum_sha256' => hash('sha256', $encoded),
                'generated_by' => $actor->id,
                'generated_at' => now(),
            ]);

            return $snapshot;
        });

        $this->record($request, 'ais.aggregation.snapshot_generated', 'Generated an immutable AIS read-only aggregation snapshot.', $snapshot, [
            'snapshotCode' => $snapshot->snapshot_code,
            'checksumSha256' => $snapshot->metrics_checksum_sha256,
            'sourceQueryVersion' => self::SOURCE_QUERY_VERSION,
        ]);

        return $snapshot->load('generator:id,name,employee_id');
    }

    /** @return array<string, mixed> */
    private function aggregate(User $user): array
    {
        $offices = Office::query()->whereNull('deleted_at');
        if (! $user->hasGlobalOfficeAccess()) $offices->whereKey($user->office_id ?: 0);

        $users = User::query()->where('is_active', true);
        if (! $user->hasGlobalOfficeAccess()) $users->where('office_id', $user->office_id);

        $plans = InternalAuditPlan::query()->where('is_active', true)->where('is_current_revision', true);
        $this->iap->scopeVisible($plans, $user);

        $engagements = $this->aems->visibleEngagements(AuditEngagement::query()->whereNull('deleted_at'), $user);
        $cmsCases = $this->cms->visibleCases(CmsRecommendationCase::query(), $user, 'cms.recommendation.view');
        $resources = $this->armis->scopeVisible(ArmisResourceProfile::query(), $user);

        $aemsFindings = AuditFinding::query()->whereHas('engagement', fn (Builder $query): Builder => $this->aems->visibleEngagements($query, $user));
        $aemsEvidence = AuditEvidence::query()->whereHas('engagement', fn (Builder $query): Builder => $this->aems->visibleEngagements($query, $user));
        $assignments = ArmisEngagementAssignment::query()
            ->whereHas('resourceProfile', fn (Builder $query): Builder => $this->armis->scopeVisible($query, $user))
            ->whereHas('engagement', fn (Builder $query): Builder => $this->aems->visibleEngagements($query, $user));

        return [
            'core' => [
                'offices' => (int) $offices->count(),
                'activeUsers' => (int) $users->count(),
            ],
            'iap' => [
                'plansByStatus' => $this->groupCounts(clone $plans, 'status'),
                'approvedPlans' => (int) (clone $plans)->whereIn('status', ['APPROVED', 'ACTIVE', 'COMPLETED'])->count(),
            ],
            'aems' => [
                'engagementsByStatus' => $this->groupCounts(clone $engagements, 'status'),
                'activeEngagements' => (int) (clone $engagements)->whereNotIn('status', ['CLOSED', 'CANCELLED'])->count(),
                'findingsByStatus' => $this->groupCounts(clone $aemsFindings, 'status'),
                'evidenceByOutcome' => $this->groupCounts(clone $aemsEvidence, 'outcome'),
            ],
            'cms' => [
                'casesByStatus' => $this->groupCounts(clone $cmsCases, 'status_code'),
                'openCases' => (int) (clone $cmsCases)->whereNotIn('status_code', ['CLOSED', 'ACCEPTED_RISK', 'NO_LONGER_APPLICABLE'])->count(),
                'overdueCases' => (int) (clone $cmsCases)->whereNotNull('effective_target_implementation_date')->where('effective_target_implementation_date', '<', today())->whereNotIn('status_code', ['CLOSED', 'ACCEPTED_RISK', 'NO_LONGER_APPLICABLE'])->count(),
            ],
            'armis' => [
                'resourcesByStatus' => $this->groupCounts(clone $resources, 'status'),
                'approvedAssignments' => (int) (clone $assignments)->whereIn('status', ['APPROVED', 'LOCKED'])->where('is_current_revision', true)->count(),
                'plannedPersonDays' => (float) (clone $assignments)->whereIn('status', ['APPROVED', 'LOCKED'])->where('is_current_revision', true)->sum('planned_person_days'),
            ],
        ];
    }

    /** @return array<string, int> */
    private function groupCounts(Builder $query, string $column): array
    {
        return $query->select($column, DB::raw('count(*) as aggregate'))->groupBy($column)->get()->mapWithKeys(fn ($row): array => [(string) ($row->{$column} ?? 'UNSPECIFIED') => (int) $row->aggregate])->all();
    }

    /** @param array<string, int> $values */
    private function distribution(array $values): array
    {
        return collect($values)
            ->map(fn (int $value, string $code): array => ['code' => $code, 'label' => str($code)->replace('_', ' ')->title()->toString(), 'value' => $value])
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /** @param array<string, int> $values */
    private function statusValue(array $values, string $status): int
    {
        return (int) ($values[$status] ?? 0);
    }

    /** @return array<string, mixed> */
    private function scopeSnapshot(User $user): array
    {
        return [
            'userId' => $user->id,
            'officeId' => $user->hasGlobalOfficeAccess() ? null : $user->office_id,
            'officeScope' => $user->hasGlobalOfficeAccess() ? 'ALL' : 'OWN_OFFICE',
            'engagementScope' => $user->hasGlobalEngagementAccess() ? 'ALL' : 'ASSIGNED',
            'confidentiality' => [
                'confidential' => $user->hasPermission('documents.view_confidential') || $user->hasPermission('documents.view_restricted'),
                'restricted' => $user->hasPermission('documents.view_restricted'),
            ],
        ];
    }

    /** @return array<string, string> */
    private function sourceVersions(): array
    {
        return ['CORE' => 'CORE-1-v1', 'IAP' => 'IAP-live-v1', 'AEMS' => 'AEMS-G10E-v1', 'CMS' => 'CMS-12B-v1', 'ARMIS' => 'ARMIS-7C-v1'];
    }

    /** @return array<string, mixed> */
    public function snapshotData(AisAggregationSnapshot $snapshot): array
    {
        return [
            'id' => $snapshot->id,
            'displayCode' => $snapshot->display_code,
            'contractVersion' => $snapshot->contract_version,
            'sourceQueryVersion' => $snapshot->source_query_version,
            'generatedAt' => $snapshot->generated_at?->toIso8601String(),
            'scope' => $snapshot->scope_snapshot,
            'metrics' => $snapshot->metrics,
            'checksumSha256' => $snapshot->metrics_checksum_sha256,
            'sourceVersions' => $snapshot->source_versions,
        ];
    }

    private function authorize(?User $user): void
    {
        abort_unless($user?->is_active && ! $user->trashed() && $user->hasPermission('ais.view'), 403, 'You do not have permission to access AIS aggregations.');
    }

    /** @param array<string, mixed> $metadata */
    private function record(Request $request, string $action, string $description, object $subject, array $metadata): void
    {
        ActivityRecorder::record($request, $action, $description, metadata: ['module' => 'AIS', 'recordType' => $subject::class, 'recordId' => $subject->getKey(), ...$metadata]);
        AuditLog::query()->create([
            'user_id' => $request->user()?->id, 'action' => $action, 'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(), 'new_values' => $metadata, 'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000), 'metadata' => ['module' => 'AIS', ...$metadata],
        ]);
    }
}
