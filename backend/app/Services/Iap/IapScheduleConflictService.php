<?php

namespace App\Services;

use App\Integrations\Aems\ArmisResourcePlanningGateway;
use App\Models\IapPlanEngagement;
use App\Models\User;
use App\Models\ArmisWorkloadAllocation;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Detects date, office, auditor, skill, availability, and capacity conflicts.
 */
class IapScheduleConflictService
{
    public function __construct(private readonly ArmisResourcePlanningGateway $resources) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $members
     * @return list<array<string, mixed>>
     */
    public function detect(
        IapPlanEngagement $engagement,
        CarbonInterface $start,
        CarbonInterface $end,
        Collection $members,
    ): array {
        $engagement->loadMissing([
            'plan',
            'offices:id,code,name',
            'skillRequirements.specialization',
        ]);
        $memberIds = $members->pluck('userId')->map(fn ($id) => (int) $id);
        $officeIds = $engagement->offices->pluck('id');
        $conflicts = [];

        $overlapping = IapPlanEngagement::query()
            ->where('id', '<>', $engagement->id)
            ->where('schedule_status', 'SCHEDULED')
            ->whereDate('planned_start_date', '<=', $end->toDateString())
            ->whereDate('planned_end_date', '>=', $start->toDateString())
            ->whereHas('plan', fn ($plan) => $plan
                ->whereNull('deleted_at')
                ->where('is_current_revision', true))
            ->with([
                'plan:id,plan_code,fiscal_year',
                'offices:id,code,name',
                'teamMembers.user:id,employee_id,name,initials',
            ])
            ->get();

        foreach ($overlapping as $other) {
            $sharedAuditors = $other->teamMembers
                ->whereIn('user_id', $memberIds)
                ->pluck('user.name')
                ->filter()
                ->values();
            if ($sharedAuditors->isNotEmpty()) {
                $conflicts[] = [
                    'type' => 'AUDITOR_OVERLAP',
                    'severity' => 'danger',
                    'engagementId' => $other->id,
                    'engagementCode' => $other->engagement_code,
                    'message' => sprintf(
                        '%s already has %s assigned during the proposed dates.',
                        $other->engagement_code,
                        $sharedAuditors->join(', '),
                    ),
                ];
            }

            $sharedOffices = $other->offices
                ->whereIn('id', $officeIds)
                ->pluck('code')
                ->filter()
                ->values();
            if ($sharedOffices->isNotEmpty()) {
                $conflicts[] = [
                    'type' => 'OFFICE_OVERLAP',
                    'severity' => 'danger',
                    'engagementId' => $other->id,
                    'engagementCode' => $other->engagement_code,
                    'message' => sprintf(
                        '%s overlaps an audit of office %s.',
                        $other->engagement_code,
                        $sharedOffices->join(', '),
                    ),
                ];
            }
        }

        foreach ($memberIds as $userId) {
            $unavailable = $this->resources->unavailability((int) $userId, $start, $end);
            foreach ($unavailable as $period) {
                $user = User::withTrashed()->find($userId);
                $conflicts[] = [
                    'type' => 'AUDITOR_UNAVAILABLE',
                    'severity' => 'danger',
                    'userId' => (int) $userId,
                    'unavailabilityId' => $period['id'] ?? null,
                    'message' => sprintf(
                        '%s is unavailable for %s from %s to %s.',
                        $user?->name ?? "User #{$userId}",
                        $period['typeLabel'] ?? $period['title'] ?? 'an unavailable period',
                        Carbon::parse($period['startDate'])->format('M j, Y'),
                        Carbon::parse($period['endDate'])->format('M j, Y'),
                    ),
                ];
            }
        }

        $proficiencyRank = [
            'BASIC' => 1,
            'INTERMEDIATE' => 2,
            'ADVANCED' => 3,
            'EXPERT' => 4,
        ];
        $skills = $this->resources->skills(
            $memberIds->map(fn ($id): int => (int) $id)->all(),
            $engagement->skillRequirements->pluck('specialization_id')->map(fn ($id): int => (int) $id)->all(),
        );
        foreach ($engagement->skillRequirements as $requirement) {
            $qualified = collect($skills)
                ->filter(fn (array $claims): bool => collect($claims)->contains(
                    fn (array $skill): bool => (int) ($skill['id'] ?? 0) === (int) $requirement->specialization_id
                        && ($proficiencyRank[$skill['proficiencyLevel'] ?? ''] ?? 0)
                            >= ($proficiencyRank[$requirement->minimum_proficiency] ?? 0),
                ))
                ->keys()
                ->count();
            if ($qualified < $requirement->minimum_auditors) {
                $conflicts[] = [
                    'type' => 'SKILL_GAP',
                    'severity' => 'warning',
                    'specializationId' => $requirement->specialization_id,
                    'message' => sprintf(
                        '%s requires %d auditor(s) at %s proficiency; the proposed team has %d.',
                        $requirement->specialization?->label ?? 'This specialization',
                        $requirement->minimum_auditors,
                        ucfirst(strtolower($requirement->minimum_proficiency)),
                        $qualified,
                    ),
                    'requiredAuditors' => $requirement->minimum_auditors,
                    'qualifiedAuditors' => $qualified,
                ];
            }
        }

        foreach ($members as $member) {
            $userId = (int) $member['userId'];
            $capacity = $this->resources->capacityFor($engagement->plan->fiscal_year, $userId);
            $allocatedElsewhere = (float) ArmisWorkloadAllocation::query()
                ->whereHas('resourceProfile', fn ($query) => $query->where('user_id', $userId)->where('status', 'ACTIVE'))
                ->whereHas('requirement', fn ($query) => $query->where('source_module', 'IAP'))
                ->where('fiscal_year', $engagement->plan->fiscal_year)
                ->where('source_module', 'IAP')
                ->where('source_type', 'IAP_PLAN_ENGAGEMENT')
                ->where('status', '!=', 'RETURNED')
                ->where('is_current_revision', true)
                ->where('source_id', '<>', $engagement->id)
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('iap_plan_engagements as source_engagement')
                        ->whereColumn('source_engagement.id', 'armis_workload_allocations.source_id')
                        ->where('source_engagement.schedule_status', 'SCHEDULED')
                        ->whereNull('source_engagement.deleted_at');
                })
                ->sum('planned_person_days');
            $proposedTotal = round(
                $allocatedElsewhere + (float) $member['plannedPersonDays'],
                2,
            );
            if ($proposedTotal > $capacity) {
                $user = User::withTrashed()->find($userId);
                $conflicts[] = [
                    'type' => 'CAPACITY_EXCEEDED',
                    'severity' => 'warning',
                    'userId' => $userId,
                    'message' => sprintf(
                        '%s would have %.2f assigned person-days against %.2f available.',
                        $user?->name ?? "User #{$userId}",
                        $proposedTotal,
                        $capacity,
                    ),
                    'allocatedPersonDays' => $proposedTotal,
                    'availablePersonDays' => $capacity,
                ];
            }
        }

        return $conflicts;
    }

    /** @return array<string, float> */
    public function capacitySnapshot(int $fiscalYear, Collection $users): array
    {
        return $users->mapWithKeys(fn ($user) => [
            (string) $user->id => $this->resources->capacityFor($fiscalYear, $user->id),
        ])->all();
    }

    public function capacityFor(int $fiscalYear, int $userId): float
    {
        return $this->resources->capacityFor($fiscalYear, $userId);
    }
}
