<?php

namespace App\Services\Ais;

use App\Models\AisIntegrationSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * AIS-5B integration health and immutable diagnostic snapshots.
 *
 * This service owns only health observations. Source modules remain the
 * authority for their records and are never written by this service.
 */
class AisIntegrationHealthService
{
    public const VERSION = 'AIS-5B.0';

    private const REQUIRED_SOURCES = ['CORE', 'IAP', 'AEMS', 'CMS', 'ARMIS'];

    public function __construct(private readonly AisIntegrationContractService $contract) {}

    /** @return array<string, mixed> */
    public function health(User $user): array
    {
        $contract = $this->contract->contract($user);
        $validation = $this->validateContract($contract);

        return [
            'healthContractVersion' => self::VERSION,
            'integrationContractVersion' => $contract['integrationContractVersion'],
            'integrationStatus' => $contract['status'],
            'status' => $validation['eligible'] ? 'HEALTHY' : 'BLOCKED',
            'mode' => 'READ_ONLY',
            'checkedAt' => now()->toIso8601String(),
            'scope' => $contract['scope'],
            'sourceModules' => $contract['sourceModules'],
            'reconciliation' => $contract['reconciliation'],
            'validation' => $validation,
            'diagnostics' => $this->diagnostics($contract),
            'controls' => [
                'immutableSnapshots' => true,
                'sourceWrites' => false,
                'professionalDecisions' => false,
                'duplicateOwnershipTables' => false,
                'sourceContractRevalidated' => true,
                'failureMode' => 'FAIL_CLOSED',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function assertReady(User $user): array
    {
        $health = $this->health($user);
        abort_unless(
            (bool) data_get($health, 'validation.eligible'),
            503,
            'AIS integration is temporarily unavailable because an authoritative source requires reconciliation.',
        );

        return $health;
    }

    public function capture(User $user): AisIntegrationSnapshot
    {
        $health = $this->health($user);
        $encoded = json_encode([
            'integrationContractVersion' => $health['integrationContractVersion'],
            'scope' => $health['scope'],
            'sourceModules' => $health['sourceModules'],
            'reconciliation' => $health['reconciliation'],
            'validation' => $health['validation'],
            'diagnostics' => $health['diagnostics'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return AisIntegrationSnapshot::query()->create([
            'snapshot_code' => 'AIS-INT-'.strtoupper(Str::ulid()),
            'contract_version' => self::VERSION,
            'integration_contract_version' => $health['integrationContractVersion'],
            'status' => $health['status'],
            'scope_snapshot' => $health['scope'],
            'source_statuses' => $health['sourceModules'],
            'reconciliation' => $health['reconciliation'],
            'diagnostics' => $health['diagnostics'],
            'source_contract_hash_sha256' => hash('sha256', $encoded),
            'generated_by' => $user->id,
            'generated_at' => now(),
        ]);
    }

    /** @return Collection<int, AisIntegrationSnapshot> */
    public function snapshots(User $user, int $limit = 50): Collection
    {
        $this->authorize($user);

        return AisIntegrationSnapshot::query()
            ->where('generated_by', $user->id)
            ->latest('generated_at')
            ->limit(max(1, min($limit, 100)))
            ->get();
    }

    /** @return array<string, mixed> */
    public function snapshotData(AisIntegrationSnapshot $snapshot): array
    {
        return [
            'id' => $snapshot->id,
            'snapshotCode' => $snapshot->snapshot_code,
            'contractVersion' => $snapshot->contract_version,
            'integrationContractVersion' => $snapshot->integration_contract_version,
            'status' => $snapshot->status,
            'scope' => $snapshot->scope_snapshot,
            'sourceModules' => $snapshot->source_statuses,
            'reconciliation' => $snapshot->reconciliation,
            'diagnostics' => $snapshot->diagnostics,
            'sourceContractHashSha256' => $snapshot->source_contract_hash_sha256,
            'generatedBy' => $snapshot->generated_by,
            'generatedAt' => $snapshot->generated_at?->toIso8601String(),
            'immutable' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function validateContract(array $contract): array
    {
        $sources = collect($contract['sourceModules'] ?? []);
        $modules = $sources->pluck('module')->values()->all();
        $checks = [
            'requiredSourcesPresent' => count(array_intersect(self::REQUIRED_SOURCES, $modules)) === count(self::REQUIRED_SOURCES),
            'sourceAuthoritiesUnique' => count($modules) === count(array_unique($modules)),
            'readOnlyAdapters' => $sources->every(fn (array $source): bool => ($source['mode'] ?? null) === 'READ_ONLY'),
            'sourceScopeRevalidated' => $sources->every(fn (array $source): bool => (bool) ($source['scopeRevalidated'] ?? false)),
            'confidentialityRevalidated' => $sources->every(fn (array $source): bool => (bool) ($source['confidentialityRevalidated'] ?? false)),
            'sourceReconciliationEligible' => (bool) data_get($contract, 'reconciliation.eligible'),
            'sourceWritesDisabled' => data_get($contract, 'controls.sourceWrites') === false,
            'professionalDecisionsDisabled' => data_get($contract, 'controls.professionalDecisions') === false,
            'duplicateOwnershipTablesDisabled' => data_get($contract, 'controls.duplicateOwnershipTables') === false,
            'failClosed' => data_get($contract, 'controls.failureMode') === 'FAIL_CLOSED',
        ];
        $failed = collect($checks)->filter(fn (bool $passed): bool => ! $passed)->keys()->values()->all();

        return [
            'status' => $failed === [] ? 'PASS' : 'BLOCKED',
            'eligible' => $failed === [],
            'checks' => $checks,
            'failedChecks' => $failed,
            'requiredSources' => self::REQUIRED_SOURCES,
            'checkedAt' => now()->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function diagnostics(array $contract): array
    {
        return collect($contract['sourceModules'] ?? [])
            ->map(function (array $source): array {
                $eligible = (bool) data_get($source, 'reconciliation.eligible');
                $reasons = [];
                if (! (bool) ($source['available'] ?? false)) {
                    $reasons[] = 'SOURCE_UNAVAILABLE';
                }
                if (data_get($source, 'freshness.status') === 'STALE') {
                    $reasons[] = 'SOURCE_STALE';
                }
                if (! $eligible) {
                    $reasons[] = 'RECONCILIATION_REQUIRED';
                }

                return [
                    'module' => $source['module'] ?? 'UNKNOWN',
                    'adapter' => $source['adapter'] ?? null,
                    'status' => $eligible ? 'PASS' : 'BLOCKED',
                    'freshness' => $source['freshness'] ?? null,
                    'reconciliation' => $source['reconciliation'] ?? null,
                    'issues' => array_values(array_unique($reasons)),
                    'scopeRevalidated' => (bool) ($source['scopeRevalidated'] ?? false),
                    'confidentialityRevalidated' => (bool) ($source['confidentialityRevalidated'] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    private function authorize(?User $user): void
    {
        abort_unless($user?->is_active && ! $user->trashed() && $user->hasPermission('ais.view'), 403, 'You do not have permission to access AIS integration health.');
    }
}
