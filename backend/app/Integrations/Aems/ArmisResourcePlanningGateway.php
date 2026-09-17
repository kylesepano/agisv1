<?php

namespace App\Integrations\Aems;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Models\ArmisActualPersonDay;
use App\Models\ArmisAvailabilityPeriod;
use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisCompetency;
use App\Models\ArmisEngagementAssignment;
use App\Models\ArmisResourceProfile;
use App\Models\ArmisResourceRequirement;
use App\Models\AuditEngagement;
use App\Models\EngagementTeam;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reads the ARMIS planning and assignment ledgers through the AEMS gateway.
 *
 * ARMIS is the sole current resource authority. Historical IAP comparisons are
 * performed by a separate lineage adapter and never replace this read path.
 */
class ArmisResourcePlanningGateway implements ResourcePlanningGateway
{
    private const APPROVED_STATUSES = ['APPROVED', 'LOCKED'];

    public function capacityFor(int $year, int $userId): float
    {
        $profileId = ArmisResourceProfile::query()
            ->where('user_id', $userId)
            ->where('status', 'ACTIVE')
            ->latest('id')
            ->value('id');

        if (! $profileId) {
            return 0.0;
        }

        return round((float) ArmisCapacitySubmission::query()
            ->where('resource_profile_id', $profileId)
            ->where('fiscal_year', $year)
            ->where('is_current_revision', true)
            ->whereIn('status', self::APPROVED_STATUSES)
            ->sum('available_person_days'), 2);
    }

    public function engagementActualPersonDays(AuditEngagement $engagement): float
    {
        return round((float) ArmisActualPersonDay::query()
            ->where('is_current_revision', true)
            ->whereIn('status', self::APPROVED_STATUSES)
            ->whereHas('assignment', fn (Builder $query): Builder => $query
                ->where('audit_engagement_id', $engagement->id)
                ->where('is_current_revision', true)
                ->whereIn('status', self::APPROVED_STATUSES))
            ->sum('actual_person_days'), 2);
    }

    public function assignmentActualPersonDays(EngagementTeam $assignment): float
    {
        $profileIds = ArmisResourceProfile::query()
            ->where('user_id', $assignment->user_id)
            ->where('status', 'ACTIVE')
            ->pluck('id');

        if ($profileIds->isEmpty()) {
            return 0.0;
        }

        $armisAssignmentIds = ArmisEngagementAssignment::query()
            ->where('audit_engagement_id', $assignment->audit_engagement_id)
            ->whereIn('resource_profile_id', $profileIds)
            ->where('is_current_revision', true)
            ->whereIn('status', self::APPROVED_STATUSES)
            ->pluck('id');

        if ($armisAssignmentIds->isEmpty()) {
            return 0.0;
        }

        return round((float) ArmisActualPersonDay::query()
            ->whereIn('assignment_id', $armisAssignmentIds)
            ->where('is_current_revision', true)
            ->whereIn('status', self::APPROVED_STATUSES)
            ->sum('actual_person_days'), 2);
    }

    /**
     * @param  list<int>  $userIds
     * @param  list<int>  $specializationIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function skills(array $userIds, array $specializationIds = []): array
    {
        if ($userIds === []) {
            return [];
        }

        return ArmisCompetency::query()
            ->where('is_current_revision', true)
            ->where('status', 'VERIFIED')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', now()->toDateString());
            })
            ->whereHas('resourceProfile', fn (Builder $query): Builder => $query
                ->whereIn('user_id', $userIds)
                ->where('status', 'ACTIVE'))
            ->when(
                $specializationIds !== [],
                fn (Builder $query): Builder => $query->whereIn('competency_id', $specializationIds),
            )
            ->with([
                'resourceProfile:id,user_id',
                'competency:id,code,label',
            ])
            ->get()
            ->groupBy(fn (ArmisCompetency $claim): int => (int) $claim->resourceProfile?->user_id)
            ->map(fn ($claims): array => $claims->map(function (ArmisCompetency $claim): array {
                $base = [
                    'id' => $claim->competency_id,
                    'code' => $claim->competency?->code,
                    'label' => $claim->competency?->label,
                    'proficiencyLevel' => $claim->proficiency_level,
                ];
                $credentials = [
                    'credentialType' => $claim->credential_type,
                    'credentialReference' => $claim->credential_reference,
                    'issuer' => $claim->issuer,
                    'issuedAt' => $claim->issued_at?->toDateString(),
                    'expiresAt' => $claim->expires_at?->toDateString(),
                    'evidenceDocumentVersionId' => $claim->evidence_document_version_id,
                ];

                return collect($credentials)->filter(fn ($value): bool => $value !== null && $value !== '')
                    ->isEmpty() ? $base : [...$base, ...$credentials];
            })->values()->all())
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function requirements(AuditEngagement $engagement): array
    {
        return ArmisResourceRequirement::query()
            // ARMIS is the sole operational resource authority. Historical
            // IAP-sourced requirements remain queryable for migration lineage,
            // but they must never drive current AEMS readiness or assignments.
            ->where('source_module', 'AEMS')
            ->where('source_type', 'AEMS_ASSIGNMENT')
            ->whereIn('status', self::APPROVED_STATUSES)
            ->where('source_id', $engagement->id)
            ->with('competencies.competency:id,code,label')
            ->get()
            ->flatMap(fn (ArmisResourceRequirement $requirement) => $requirement->competencies->map(
                fn ($competency): array => [
                    'specializationId' => $competency->competency_id,
                    'code' => $competency->competency?->code,
                    'label' => $competency->competency?->label ?? $requirement->title,
                    'minimumProficiency' => $competency->minimum_proficiency,
                    'minimumAuditors' => (int) $competency->minimum_resources,
                ],
            ))
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function unavailability(
        int $userId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): array {
        return ArmisAvailabilityPeriod::query()
            ->where('availability_type', '<>', 'AVAILABLE')
            ->where('is_current_revision', true)
            ->whereIn('status', self::APPROVED_STATUSES)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->whereHas('resourceProfile', fn (Builder $query): Builder => $query
                ->where('user_id', $userId)
                ->where('status', 'ACTIVE'))
            ->with('resourceProfile:id,user_id')
            ->get()
            ->map(fn (ArmisAvailabilityPeriod $period): array => [
                'id' => $period->id,
                'userId' => (int) $period->resourceProfile?->user_id,
                'title' => $period->notes ?: str($period->availability_type)->replace('_', ' ')->title()->toString(),
                'typeLabel' => str($period->availability_type)->replace('_', ' ')->title()->toString(),
                'startDate' => $period->start_date->toDateString(),
                'endDate' => $period->end_date->toDateString(),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        return [
            'module' => 'ARMIS',
            'provider' => self::class,
            'mode' => 'ARMIS_ADAPTER',
            'available' => true,
            'authoritative' => true,
            'authorityEligible' => true,
            'providerStatus' => 'AVAILABLE_APPROVED_CURRENT_LEDGER',
            'dataFreshness' => 'CURRENT_REVISIONS_ONLY',
            'fallbackSupported' => false,
            'capabilities' => [
                'availability',
                'workload',
                'competencies',
                'planned_person_days',
                'actual_person_days',
            ],
            'actualPersonDaysOwner' => 'ARMIS',
            'futureAuthoritativeProvider' => null,
        ];
    }
}
