<?php

namespace App\Integrations\Aems;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Models\AuditEngagement;
use App\Models\EngagementTeam;
use App\Models\IapAuditorSkill;
use App\Models\IapAuditorUnavailability;
use App\Services\IapScheduleConflictService;
use Carbon\CarbonInterface;

/** Historical IAP lineage adapter used only for ARMIS reconciliation snapshots. */
class InterimIapResourcePlanningGateway implements ResourcePlanningGateway
{
    public function __construct(private readonly IapScheduleConflictService $capacity) {}

    public function capacityFor(int $year, int $userId): float
    {
        return $this->capacity->capacityFor($year, $userId);
    }

    public function engagementActualPersonDays(AuditEngagement $engagement): float
    {
        return (float) $engagement->actual_person_days;
    }

    public function assignmentActualPersonDays(EngagementTeam $assignment): float
    {
        return (float) $assignment->actual_person_days;
    }

    public function skills(array $userIds, array $specializationIds = []): array
    {
        if ($userIds === []) {
            return [];
        }

        return IapAuditorSkill::query()
            ->whereIn('user_id', $userIds)
            ->when(
                $specializationIds !== [],
                fn ($query) => $query->whereIn('specialization_id', $specializationIds),
            )
            ->with('specialization:id,code,label')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($skills) => $skills->map(fn ($skill): array => [
                'id' => $skill->specialization_id,
                'code' => $skill->specialization?->code,
                'label' => $skill->specialization?->label,
                'proficiencyLevel' => $skill->proficiency_level,
            ])->values()->all())
            ->all();
    }

    public function requirements(AuditEngagement $engagement): array
    {
        $source = $engagement->sourcePlanEngagement()
            ->with('skillRequirements.specialization:id,code,label')
            ->first();

        return $source?->skillRequirements->map(fn ($requirement): array => [
            'specializationId' => $requirement->specialization_id,
            'code' => $requirement->specialization?->code,
            'label' => $requirement->specialization?->label,
            'minimumProficiency' => $requirement->minimum_proficiency,
            'minimumAuditors' => (int) $requirement->minimum_auditors,
        ])->values()->all() ?? [];
    }

    public function unavailability(
        int $userId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): array {
        return IapAuditorUnavailability::query()
            ->where('user_id', $userId)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->with('type:id,code,label')
            ->get()
            ->map(fn ($period): array => [
                'title' => $period->title,
                'typeLabel' => $period->type?->label,
                'startDate' => $period->start_date->toDateString(),
                'endDate' => $period->end_date->toDateString(),
            ])->values()->all();
    }

    public function status(): array
    {
        return [
            'module' => 'ARMIS',
            'provider' => self::class,
            'mode' => 'IAP_HISTORICAL_LINEAGE',
            'available' => true,
            'authoritative' => false,
            'operational' => false,
            'capabilities' => [
                'availability',
                'workload',
                'competencies',
                'planned_person_days',
                'actual_person_days',
            ],
            'actualPersonDaysOwner' => 'HISTORICAL_ONLY',
            'futureAuthoritativeProvider' => null,
        ];
    }
}
