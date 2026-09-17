<?php

namespace App\Services\Ais;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Models\AuditEngagement;
use App\Models\CmsRecommendation;
use App\Models\CmsRecommendationCase;
use App\Models\InternalAuditPlan;
use App\Models\Office;
use App\Models\User;
use App\Services\AemsAccessService;
use App\Services\Cms\CmsRecommendationScopeService;
use App\Services\IapPlanGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Publishes the AIS-5A read-only integration contract. This service only
 * interrogates source ledgers through their existing scope/provider services;
 * it never creates, updates, or transfers an operational source record.
 */
class AisIntegrationContractService
{
    public const VERSION = 'AIS-5A.0';

    public function __construct(
        private readonly IapPlanGuard $iap,
        private readonly AemsAccessService $aems,
        private readonly CmsRecommendationScopeService $cms,
        private readonly ResourcePlanningGateway $resources,
    ) {}

    /** @return array<string, mixed> */
    public function contract(User $user): array
    {
        $this->authorize($user);
        $sources = [
            'CORE' => $this->core($user),
            'IAP' => $this->iap($user),
            'AEMS' => $this->aems($user),
            'CMS' => $this->cms($user),
            'ARMIS' => $this->armis(),
        ];
        $reconciliation = $this->reconciliation($sources);

        return [
            'integrationContractVersion' => self::VERSION,
            'status' => $reconciliation['eligible'] ? 'READ_ONLY_READY' : 'READ_ONLY_BLOCKED',
            'mode' => 'READ_ONLY',
            'generatedAt' => now()->toIso8601String(),
            'scope' => $this->scope($user),
            'sourceModules' => array_values($sources),
            'reconciliation' => $reconciliation,
            'ownershipBoundaries' => [
                'CORE' => 'Core owns users, offices, roles, permissions, scopes, documents, and shared infrastructure.',
                'IAP' => 'IAP owns approved plans, risk decisions, prioritization, and planning lineage.',
                'AEMS' => 'AEMS owns engagements, fieldwork, evidence, findings, reports, and closure records.',
                'CMS' => 'CMS owns finalized recommendation intake, monitoring, validation, disposition, and closure.',
                'ARMIS' => 'ARMIS owns the sole operational resource ledgers and historical provider reconciliation.',
            ],
            'failClosedRules' => [
                'missingSource' => 'A source marked unavailable blocks AIS aggregation and reporting for that scope.',
                'staleSource' => 'A source marked stale or requiring reconciliation blocks AIS aggregation and reporting.',
                'scopeMismatch' => 'A source scope or confidentiality failure blocks the affected AIS operation.',
                'cmsEligibility' => 'AIS consumes only CMS records backed by finalized or transferred recommendations.',
                'armisAuthority' => 'ARMIS authority is not inferred; an ineligible authoritative provider blocks the integration.',
            ],
            'controls' => [
                'sourceScopeRechecked' => true,
                'confidentialityRechecked' => true,
                'lineagePinned' => true,
                'sourceWrites' => false,
                'professionalDecisions' => false,
                'duplicateOwnershipTables' => false,
                'failureMode' => 'FAIL_CLOSED',
            ],
        ];
    }

    public function assertReady(User $user): void
    {
        $contract = $this->contract($user);
        abort_unless(
            (bool) data_get($contract, 'reconciliation.eligible'),
            503,
            'AIS integration is temporarily unavailable because an authoritative source requires reconciliation.',
        );
    }

    /** @return array<string, mixed> */
    private function core(User $user): array
    {
        try {
            DB::connection()->getPdo();
            $offices = Office::query()->whereNull('deleted_at');
            if (! $user->hasGlobalOfficeAccess()) {
                $offices->whereKey($user->office_id ?: 0);
            }
            $available = $offices->exists() || $user->hasGlobalOfficeAccess();
        } catch (Throwable) {
            $available = false;
        }

        return $this->source('CORE', 'CORE_READ_SCOPE', $available, 'LIVE_DATABASE_QUERY', $available ? 'PASS' : 'BLOCKED', [
            'scope' => 'Core office, role, permission, and confidentiality scope is rechecked per request.',
            'sharedServices' => ['users', 'offices', 'roles_permissions', 'document_versions', 'notifications', 'activity_logs', 'audit_logs'],
        ]);
    }

    /** @return array<string, mixed> */
    private function iap(User $user): array
    {
        try {
            $plans = InternalAuditPlan::query()
                ->where('is_active', true)
                ->where('is_current_revision', true);
            $this->iap->scopeVisible($plans, $user);
            $approvedPlans = (clone $plans)->whereIn('status', ['APPROVED', 'ACTIVE', 'COMPLETED'])->count();
            $available = true;
        } catch (Throwable) {
            $approvedPlans = null;
            $available = false;
        }

        return $this->source('IAP', 'IAP_APPROVED_PLAN_GATE', $available, 'LIVE_DATABASE_QUERY', $available ? 'PASS' : 'BLOCKED', [
            'approvedPlanCount' => $approvedPlans,
            'lineage' => 'AIS receives approved plan references only; IAP source rows remain immutable to AIS.',
            'duplicatePrevention' => 'AEMS source foreign key and active uniqueness controls are authoritative.',
        ]);
    }

    /** @return array<string, mixed> */
    private function aems(User $user): array
    {
        try {
            $engagements = $this->aems->visibleEngagements(
                AuditEngagement::query()->whereNull('deleted_at'),
                $user,
            );
            $lineageGaps = (clone $engagements)
                ->where('source_type', 'PLANNED')
                ->where(function (Builder $query): void {
                    $query->whereNull('iap_plan_engagement_id')->orWhereNull('source_snapshot');
                })
                ->count();
            $available = $lineageGaps === 0;
            $engagementCount = (clone $engagements)->count();
        } catch (Throwable) {
            $lineageGaps = null;
            $engagementCount = null;
            $available = false;
        }

        return $this->source('AEMS', 'AEMS_SCOPED_ENGAGEMENT_GATE', $available, 'LIVE_DATABASE_QUERY', $available ? 'PASS' : 'BLOCKED', [
            'visibleEngagementCount' => $engagementCount,
            'lineageGaps' => $lineageGaps,
            'traceability' => 'Engagement, fieldwork, evidence, finding, report, and closure references remain AEMS-owned.',
        ]);
    }

    /** @return array<string, mixed> */
    private function cms(User $user): array
    {
        try {
            $cases = $this->cms->visibleCases(CmsRecommendationCase::query(), $user, 'cms.recommendation.view');
            $finalized = CmsRecommendation::query()
                ->whereIn('status', ['FINALIZED', CmsRecommendation::STATUS_TRANSFERRED])
                ->whereHas('case', fn (Builder $query): Builder => $this->cms->visibleCases($query, $user, 'cms.recommendation.view'));
            $sourceGaps = (clone $finalized)->whereNull('source_snapshot')->count();
            $available = $sourceGaps === 0;
            $caseCount = (clone $cases)->count();
            $finalizedCount = (clone $finalized)->count();
        } catch (Throwable) {
            $sourceGaps = null;
            $caseCount = null;
            $finalizedCount = null;
            $available = false;
        }

        return $this->source('CMS', 'CMS_FINALIZED_RECOMMENDATION_GATE', $available, 'LIVE_DATABASE_QUERY', $available ? 'PASS' : 'BLOCKED', [
            'visibleCaseCount' => $caseCount,
            'finalizedRecommendationCount' => $finalizedCount,
            'sourceGaps' => $sourceGaps,
            'eligibility' => 'Only finalized or transferred recommendation envelopes are eligible for AIS consumption.',
        ]);
    }

    /** @return array<string, mixed> */
    private function armis(): array
    {
        try {
            $provider = $this->resources->status();
            $available = (bool) ($provider['available'] ?? false);
            $mode = (string) ($provider['mode'] ?? 'UNKNOWN');
            $freshness = (string) ($provider['dataFreshness'] ?? $provider['armisAdapter']['dataFreshness'] ?? 'CURRENT_REVISIONS_ONLY');
            $stale = preg_match('/STALE|WARN|UNKNOWN/i', $freshness) === 1;
            $authorityEligible = (bool) ($provider['authorityEligible'] ?? false);
            $eligible = $available && ! $stale && $mode === 'ARMIS_AUTHORITATIVE' && $authorityEligible;
            $status = ! $available || $stale || ! $eligible ? 'BLOCKED' : 'PASS';
        } catch (Throwable) {
            $provider = [];
            $available = false;
            $mode = 'UNKNOWN';
            $freshness = 'UNKNOWN';
            $stale = true;
            $authorityEligible = false;
            $eligible = false;
            $status = 'BLOCKED';
        }

        return $this->source('ARMIS', 'ARMIS_PROVIDER_GATE', $eligible, $freshness, $status, [
            'providerMode' => $mode,
            'providerStatus' => $provider['providerStatus'] ?? null,
            'fallbackSupported' => false,
            'authorityEligible' => $authorityEligible,
            'reconciliation' => $provider['reconciliation'] ?? null,
            'capabilities' => $provider['capabilities'] ?? $provider['armisAdapter']['capabilities'] ?? [],
        ]);
    }

    /** @param array<string, mixed> $details @return array<string, mixed> */
    private function source(string $module, string $adapter, bool $available, string $freshness, string $reconciliation, array $details): array
    {
        return [
            'module' => $module,
            'authority' => $module,
            'adapter' => $adapter,
            'mode' => 'READ_ONLY',
            'available' => $available,
            'freshness' => [
                'mode' => str_contains($freshness, 'LIVE') ? $freshness : 'PROVIDER_REPORTED',
                'status' => preg_match('/STALE|WARN|UNKNOWN|BLOCKED/i', $freshness) === 1 ? 'STALE' : 'CURRENT',
                'observedAt' => now()->toIso8601String(),
            ],
            'reconciliation' => [
                'status' => $reconciliation,
                'eligible' => $available && $reconciliation !== 'BLOCKED',
            ],
            'scopeRevalidated' => true,
            'confidentialityRevalidated' => true,
            'details' => $details,
        ];
    }

    /** @param array<string, array<string, mixed>> $sources @return array<string, mixed> */
    private function reconciliation(array $sources): array
    {
        $blocked = collect($sources)
            ->filter(fn (array $source): bool => ! (bool) data_get($source, 'reconciliation.eligible'))
            ->keys()
            ->values()
            ->all();

        return [
            'eligible' => $blocked === [],
            'status' => $blocked === [] ? 'PASS' : 'BLOCKED',
            'blockedSources' => $blocked,
            'checkedAt' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function scope(User $user): array
    {
        return [
            'userId' => $user->id,
            'officeScope' => $user->hasGlobalOfficeAccess() ? 'ALL' : 'OWN_OFFICE',
            'officeId' => $user->hasGlobalOfficeAccess() ? null : $user->office_id,
            'engagementScope' => $user->hasGlobalEngagementAccess() ? 'ALL' : 'ASSIGNED',
            'confidentiality' => [
                'confidential' => $user->hasPermission('documents.view_confidential') || $user->hasPermission('documents.view_restricted'),
                'restricted' => $user->hasPermission('documents.view_restricted'),
            ],
        ];
    }

    private function authorize(?User $user): void
    {
        abort_unless($user?->is_active && ! $user->trashed() && $user->hasPermission('ais.view'), 403, 'You do not have permission to access AIS integration metadata.');
    }
}
