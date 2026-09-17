<?php

namespace App\Integrations\Aems;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Models\AuditEngagement;
use App\Models\EngagementTeam;
use App\Services\RuntimeConfiguration;
use Carbon\CarbonInterface;

/**
 * Keeps the AEMS provider boundary explicit after the ARMIS ownership cutover.
 *
 * ARMIS is the sole operational resource provider. Historical IAP resource
 * ledgers remain available for lineage and reconciliation records, but they
 * are never selected as an AEMS read provider.
 */
class ConfigurableResourcePlanningGateway implements ResourcePlanningGateway
{
    public const ARMIS_AUTHORITATIVE = 'ARMIS_AUTHORITATIVE';

    /** @var list<string> */
    public const SUPPORTED_MODES = [
        self::ARMIS_AUTHORITATIVE,
    ];

    public function __construct(
        private readonly RuntimeConfiguration $runtime,
        private readonly ArmisResourcePlanningGateway $armis,
    ) {}

    public function capacityFor(int $year, int $userId): float
    {
        return $this->active()->capacityFor($year, $userId);
    }

    public function engagementActualPersonDays(AuditEngagement $engagement): float
    {
        return $this->active()->engagementActualPersonDays($engagement);
    }

    public function assignmentActualPersonDays(EngagementTeam $assignment): float
    {
        return $this->active()->assignmentActualPersonDays($assignment);
    }

    public function skills(array $userIds, array $specializationIds = []): array
    {
        return $this->active()->skills($userIds, $specializationIds);
    }

    public function requirements(AuditEngagement $engagement): array
    {
        return $this->active()->requirements($engagement);
    }

    public function unavailability(
        int $userId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): array {
        return $this->active()->unavailability($userId, $start, $end);
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $active = $this->armis->status();
        $armis = $this->armis->status();

        return [
            ...$active,
            'provider' => $active['provider'],
            'mode' => self::ARMIS_AUTHORITATIVE,
            'configuredMode' => self::ARMIS_AUTHORITATIVE,
            'activeProvider' => $active['provider'],
            'fallbackProvider' => null,
            'shadowProvider' => null,
            'shadowAvailable' => false,
            'authoritative' => true,
            'authoritySwitchAllowed' => false,
            'authoritySwitchReason' => 'ARMIS is the sole operational resource provider.',
            'supportedModes' => self::SUPPORTED_MODES,
            'armisAdapter' => $armis,
            'authorityEligible' => true,
            'actualPersonDaysOwner' => 'ARMIS',
            'futureAuthoritativeProvider' => null,
            'fallback' => [
                'explicit' => false,
                'provider' => null,
                'active' => false,
                'reason' => null,
            ],
            'reconciliation' => [
                'requiredForAuthority' => false,
                'authorityEligible' => true,
            ],
        ];
    }

    public function mode(): string
    {
        return self::ARMIS_AUTHORITATIVE;
    }

    private function active(): ResourcePlanningGateway
    {
        return $this->armis;
    }
}
