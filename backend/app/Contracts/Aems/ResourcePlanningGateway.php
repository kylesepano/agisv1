<?php

namespace App\Contracts\Aems;

use App\Models\AuditEngagement;
use App\Models\EngagementTeam;
use Carbon\CarbonInterface;

/**
 * Replaceable capacity/availability/competency boundary. The current provider
 * reads interim IAP records; ARMIS can replace the container binding later.
 */
interface ResourcePlanningGateway
{
    public function capacityFor(int $year, int $userId): float;

    public function engagementActualPersonDays(AuditEngagement $engagement): float;

    public function assignmentActualPersonDays(EngagementTeam $assignment): float;

    /**
     * @param  list<int>  $userIds
     * @param  list<int>  $specializationIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function skills(array $userIds, array $specializationIds = []): array;

    /** @return list<array<string, mixed>> */
    public function requirements(AuditEngagement $engagement): array;

    /** @return list<array<string, mixed>> */
    public function unavailability(
        int $userId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): array;

    /** @return array<string, mixed> */
    public function status(): array;
}
