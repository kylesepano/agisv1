<?php

namespace App\Services;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Integrations\Aems\ArmisResourcePlanningGateway;
use App\Models\ActivityLog;
use App\Models\ArmisProviderMonitoringCheck;
use App\Models\AuditLog;
use App\Models\ArmisWorkflowEvent;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Performs read-only ARMIS provider health and post-authority cutover checks.
 *
 * Monitoring never changes IAP, ARMIS, provider mode, or professional
 * decisions. Each requested check is an immutable, scope-pinned snapshot.
 */
class ArmisProviderMonitoringService
{
    private const SOURCE_QUERY_VERSION = 'ARMIS-6D-v1';

    private const MODE_AUTHORITATIVE = 'ARMIS_AUTHORITATIVE';

    public function __construct(
        private readonly ResourcePlanningGateway $provider,
        private readonly RuntimeConfiguration $runtime,
        private readonly NotificationService $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function status(User $user): array
    {
        $this->authorize($user, 'armis.provider.view');
        $latest = $this->checks($user)->first();
        $diagnostics = $this->diagnostics($user);

        return [
            ...$diagnostics,
            'latestCheck' => $latest ? $this->summary($latest) : null,
            'monitoringControls' => [
                'checkIsReadOnly' => true,
                'authoritySwitchIsSeparate' => true,
                'globalScopeRequiredToRun' => true,
            ],
        ];
    }

    /** @return \Illuminate\Support\Collection<int, ArmisProviderMonitoringCheck> */
    public function checks(User $user)
    {
        $this->authorize($user, 'armis.provider.view');
        $officeIds = $this->scopeOfficeIds($user);

        return ArmisProviderMonitoringCheck::query()
            ->with('performer')
            ->latest('performed_at')
            ->limit(50)
            ->get()
            ->filter(fn (ArmisProviderMonitoringCheck $check): bool => $this->checkVisible($check, $officeIds, $user))
            ->values();
    }

    public function show(User $user, int $checkId): ArmisProviderMonitoringCheck
    {
        $this->authorize($user, 'armis.provider.view');
        $check = ArmisProviderMonitoringCheck::query()->with('performer')->findOrFail($checkId);
        abort_unless($this->checkVisible($check, $this->scopeOfficeIds($user), $user), 404, 'The ARMIS monitoring check is not in your scope.');

        return $check;
    }

    public function run(Request $request): ArmisProviderMonitoringCheck
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $this->authorize($actor, 'armis.provider.monitor');
        $this->requireGlobalScope($actor);

        $diagnostics = $this->diagnostics($actor);
        $scope = [
            'officeIds' => $this->scopeOfficeIds($actor),
            'globalOfficeScope' => true,
        ];
        $providerSnapshot = $diagnostics['providerSnapshot'];
        $checksum = hash('sha256', json_encode([
            'sourceQueryVersion' => self::SOURCE_QUERY_VERSION,
            'scope' => $scope,
            'providerMode' => $diagnostics['providerMode'],
            'configuredMode' => $diagnostics['configuredMode'],
            'overallStatus' => $diagnostics['overallStatus'],
            'checks' => $diagnostics['checks'],
            'providerSnapshot' => $providerSnapshot,
        ], JSON_THROW_ON_ERROR));

        $check = DB::transaction(function () use ($request, $actor, $diagnostics, $scope, $providerSnapshot, $checksum): ArmisProviderMonitoringCheck {
            $check = ArmisProviderMonitoringCheck::query()->create([
                'check_uuid' => (string) Str::uuid(),
                'source_query_version' => self::SOURCE_QUERY_VERSION,
                'provider_mode' => $diagnostics['providerMode'],
                'configured_mode' => $diagnostics['configuredMode'],
                'overall_status' => $diagnostics['overallStatus'],
                'scope_snapshot' => $scope,
                'checks' => $diagnostics['checks'],
                'provider_snapshot' => $providerSnapshot,
                'result_checksum_sha256' => $checksum,
                'performed_by' => $actor->id,
                'performed_at' => now(),
            ]);
            $this->event($check, 'ARMIS_PROVIDER_MONITORING_CHECKED', null, $check->overall_status, $actor, null, [
                'checksumSha256' => $checksum,
                'failedChecks' => collect($diagnostics['checks'])->where('status', 'FAIL')->pluck('code')->values()->all(),
            ]);
            $this->record($request, 'armis.provider.monitoring.checked', 'Recorded an immutable ARMIS provider monitoring check.', $check, null, [
                'overallStatus' => $check->overall_status,
                'checksumSha256' => $checksum,
            ]);

            return $check;
        });

        if ($check->overall_status !== 'HEALTHY') {
            DB::afterCommit(fn () => $this->notifyMonitoringIssue($check, $actor));
        }

        return $check->load('performer');
    }

    /** @return array<string, mixed> */
    private function diagnostics(User $user): array
    {
        $provider = $this->provider->status();
        $configuredMode = $this->runtime->armisProviderMode();
        $providerMode = self::MODE_AUTHORITATIVE;
        $checks = [];
        $checks[] = $this->diagnostic(
            'CONFIGURATION_CONSISTENCY',
            $configuredMode === $providerMode ? 'PASS' : 'FAIL',
            'Configured and effective provider modes agree.',
            ['configuredMode' => $configuredMode, 'effectiveMode' => $providerMode],
            ['configuredMode' => $configuredMode, 'effectiveMode' => $providerMode],
        );

        $expectedProvider = ArmisResourcePlanningGateway::class;
        $activeProvider = (string) ($provider['activeProvider'] ?? '');
        $checks[] = $this->diagnostic(
            'AEMS_READ_PATH',
            $activeProvider === $expectedProvider ? 'PASS' : 'FAIL',
            'AEMS active reads resolve to the provider selected by the effective mode.',
            ['activeProvider' => $activeProvider],
            ['activeProvider' => $expectedProvider],
        );

        $checks[] = $this->diagnostic(
            'AUTHORITY_DECISION_CONSISTENCY',
            'PASS',
            'ARMIS is the sole operational provider; no provider-switch decision is required.',
            ['providerMode' => self::MODE_AUTHORITATIVE],
            ['providerMode' => self::MODE_AUTHORITATIVE],
        );

        $adapterAvailable = (bool) ($provider['armisAdapter']['available'] ?? $provider['shadowAvailable'] ?? false);
        $checks[] = $this->diagnostic(
            'ARMIS_ADAPTER_AVAILABILITY',
            $adapterAvailable ? 'PASS' : 'FAIL',
            'The ARMIS adapter reports an available read ledger for comparison or authoritative use.',
            ['available' => $adapterAvailable, 'provider' => $provider['activeProvider'] ?? ArmisResourcePlanningGateway::class],
            ['available' => true],
        );

        $failed = collect($checks)->where('status', 'FAIL')->count();
        $warnings = collect($checks)->where('status', 'WARN')->count();

        return [
            'providerMode' => $providerMode,
            'configuredMode' => $configuredMode,
            'overallStatus' => $failed > 0 ? 'FAILED' : ($warnings > 0 ? 'DEGRADED' : 'HEALTHY'),
            'checks' => $checks,
            'providerSnapshot' => [
                'provider' => $provider,
                'latestAuthorityDecision' => null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function diagnostic(string $code, string $status, string $message, array $observed, array $expected): array
    {
        return compact('code', 'status', 'message', 'observed', 'expected');
    }

    /** @return array<string, mixed> */
    private function summary(ArmisProviderMonitoringCheck $check): array
    {
        return [
            'id' => $check->id,
            'displayCode' => $check->display_code,
            'providerMode' => $check->provider_mode,
            'configuredMode' => $check->configured_mode,
            'overallStatus' => $check->overall_status,
            'checksumSha256' => $check->result_checksum_sha256,
            'performedAt' => $check->performed_at?->toISOString(),
            'performedBy' => $check->performer?->only(['id', 'name']),
        ];
    }

    /** @return list<int> */
    private function scopeOfficeIds(User $user): array
    {
        return $user->hasGlobalOfficeAccess()
            ? Office::query()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : [(int) $user->office_id];
    }

    private function checkVisible(ArmisProviderMonitoringCheck $check, array $officeIds, User $user): bool
    {
        if ($user->hasGlobalOfficeAccess()) {
            return true;
        }

        return collect($check->scope_snapshot['officeIds'] ?? [])->map(fn ($id): int => (int) $id)
            ->intersect($officeIds)->isNotEmpty();
    }

    private function authorize(User $user, string $permission): void
    {
        abort_unless($user->hasPermission($permission), 403, 'You are not authorized for ARMIS provider monitoring.');
    }

    private function requireGlobalScope(User $user): void
    {
        abort_unless($user->hasGlobalOfficeAccess(), 403, 'Provider monitoring checks require global office scope.');
    }

    private function notifyMonitoringIssue(ArmisProviderMonitoringCheck $check, User $actor): void
    {
        $recipients = User::query()->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereHas('roles.permissions', fn (Builder $permissions) => $permissions->where('code', 'armis.provider.monitor'))
                    ->orWhereHas('role.permissions', fn (Builder $permissions) => $permissions->where('code', 'armis.provider.monitor'));
            })
            ->where('users.id', '<>', $actor->id)
            ->pluck('id');
        $this->notifications->send($recipients, [
            'actorId' => $actor->id,
            'type' => 'ARMIS_PROVIDER_MONITORING',
            'category' => 'SYSTEM',
            'priority' => $check->overall_status === 'FAILED' ? 'HIGH' : 'NORMAL',
            'moduleCode' => 'ARMIS',
            'title' => "ARMIS provider check {$check->overall_status}",
            'message' => "{$check->display_code} reported {$check->overall_status}. Review the provider monitoring workspace.",
            'actionUrl' => '/audit-resource-management/provider-monitoring',
            'actionLabel' => 'Review ARMIS provider health',
            'subjectType' => $check::class,
            'subjectId' => $check->id,
            'subjectCode' => $check->display_code,
            'dedupeKey' => "armis-provider-monitoring:{$check->id}",
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
