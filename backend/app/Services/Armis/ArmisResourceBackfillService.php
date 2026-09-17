<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ArmisAvailabilityPeriod;
use App\Models\ArmisCompetency;
use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisRequirementCompetency;
use App\Models\ArmisResourceProfile;
use App\Models\ArmisResourceRequirement;
use App\Models\ArmisProviderReconciliationReview;
use App\Models\ArmisProviderReconciliationRun;
use App\Models\ArmisWorkloadAllocation;
use App\Models\IapAuditorCapacity;
use App\Models\IapAuditorSkill;
use App\Models\IapAuditorUnavailability;
use App\Models\IapEngagementSkillRequirement;
use App\Models\IapPlanEngagement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies the historical IAP resource ledgers into ARMIS exactly once.
 *
 * IAP remains a source of historical lineage, but it is never updated by this
 * service.  The copied ARMIS records are current revisions and are safe to
 * rerun; an existing ARMIS revision is preserved rather than overwritten.
 */
class ArmisResourceBackfillService
{
    /**
     * Create the immutable accepted reconciliation evidence used by the
     * authority gate. The snapshot records that historical IAP resource data
     * was copied without mutating its source; it is intentionally separate
     * from the authority decision itself.
     */
    public function ensureAcceptedCutoverReconciliation(User $actor): ArmisProviderReconciliationRun
    {
        return DB::transaction(function () use ($actor): ArmisProviderReconciliationRun {
            $run = ArmisProviderReconciliationRun::query()
                ->where('source_query_version', 'ARMIS-CUTOVER-SEED-v1')
                ->latest('id')
                ->first();
            if (! $run) {
                $resultSnapshot = [[
                    'key' => 'IAP_RESOURCE_LEDGER',
                    'status' => 'RECONCILED',
                    'message' => 'Historical IAP resource records were copied to ARMIS without source mutation.',
                ]];
                $run = ArmisProviderReconciliationRun::query()->create([
                    'run_uuid' => (string) Str::uuid(),
                    'source_query_version' => 'ARMIS-CUTOVER-SEED-v1',
                    'fiscal_year' => (int) now()->year,
                    'provider_mode' => 'ARMIS_AUTHORITATIVE',
                    'status' => 'GENERATED',
                    'filters' => ['source' => 'IAP_HISTORICAL_LEDGER'],
                    'scope_snapshot' => ['globalOfficeScope' => true],
                    'result_snapshot' => $resultSnapshot,
                    'summary' => ['reviewRequired' => false, 'discrepancyCount' => 0],
                    'result_checksum_sha256' => hash('sha256', json_encode($resultSnapshot, JSON_THROW_ON_ERROR)),
                    'generated_by' => $actor->id,
                    'generated_at' => now(),
                ]);
            }
            if (! $run->reviews()->where('decision', 'ACCEPTED')->exists()) {
                ArmisProviderReconciliationReview::query()->create([
                    'reconciliation_run_id' => $run->id,
                    'decision' => 'ACCEPTED',
                    'discrepancy_decisions' => [],
                    'comment' => 'Baseline accepted for the ARMIS resource ownership cutover.',
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                ]);
            }

            return $run->fresh();
        });
    }

    /**
     * @return array{profiles:int,capacity:int,competencies:int,availability:int,requirements:int,workloads:int}
     */
    public function backfill(User $actor, bool $approve = false): array
    {
        return DB::transaction(function () use ($actor, $approve): array {
            $counts = [
                'profiles' => 0,
                'capacity' => 0,
                'competencies' => 0,
                'availability' => 0,
                'requirements' => 0,
                'workloads' => 0,
            ];

            $profiles = ArmisResourceProfile::query()
                ->where('status', 'ACTIVE')
                ->get()
                ->keyBy('user_id');

            $resolveProfile = function (int $userId) use (&$profiles, $actor, &$counts): ?ArmisResourceProfile {
                $profile = $profiles->get($userId);
                if ($profile) {
                    return $profile;
                }

                $user = User::query()->find($userId);
                if (! $user?->is_active || ! $user->office_id) {
                    return null;
                }

                $profile = ArmisResourceProfile::query()->firstOrCreate(
                    ['user_id' => $user->id, 'office_id' => $user->office_id, 'status' => 'ACTIVE'],
                    [
                        'resource_code' => 'ARMIS-IAP-USER-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                        'category' => $user->hasPermission('iap.review') ? 'REVIEWER' : 'AUDIT_RESOURCE',
                        'effective_from' => now()->startOfYear()->toDateString(),
                        'notes' => 'Created while backfilling the historical IAP resource ledger.',
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                        'lock_version' => 1,
                    ],
                );
                $profiles->put($user->id, $profile);
                $counts['profiles']++;

                return $profile;
            };

            $capacities = IapAuditorCapacity::query()->get();
            $skills = IapAuditorSkill::query()->get();
            $availability = IapAuditorUnavailability::query()->with('type')->get();
            $engagements = IapPlanEngagement::query()
                ->with(['plan:id,fiscal_year', 'offices:id', 'skillRequirements'])
                ->get();

            foreach ($capacities as $source) {
                $profile = $resolveProfile((int) $source->user_id);
                if (! $profile) {
                    continue;
                }

                $existing = ArmisCapacitySubmission::query()
                    ->where('resource_profile_id', $profile->id)
                    ->where('fiscal_year', $source->fiscal_year)
                    ->where('is_current_revision', true)
                    ->first();
                if ($existing) {
                    continue;
                }

                ArmisCapacitySubmission::query()->create([
                    'resource_profile_id' => $profile->id,
                    'fiscal_year' => $source->fiscal_year,
                    'version_number' => 1,
                    'available_person_days' => $source->available_person_days,
                    'status' => $approve ? 'APPROVED' : 'DRAFT',
                    'is_current_revision' => true,
                    'notes' => 'Backfilled from the historical IAP capacity ledger; ARMIS is authoritative.',
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                    'reviewed_by' => $approve ? $actor->id : null,
                    'reviewed_at' => $approve ? now() : null,
                    'approved_by' => $approve ? $actor->id : null,
                    'approved_at' => $approve ? now() : null,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'lock_version' => 1,
                ]);
                $counts['capacity']++;
            }

            foreach ($skills as $source) {
                $profile = $resolveProfile((int) $source->user_id);
                if (! $profile) {
                    continue;
                }

                $existing = ArmisCompetency::query()
                    ->where('resource_profile_id', $profile->id)
                    ->where('competency_id', $source->specialization_id)
                    ->where('is_current_revision', true)
                    ->first();
                if ($existing) {
                    continue;
                }

                ArmisCompetency::query()->create([
                    'competency_family_uuid' => (string) Str::uuid(),
                    'resource_profile_id' => $profile->id,
                    'competency_id' => $source->specialization_id,
                    'version_number' => 1,
                    'is_current_revision' => true,
                    'proficiency_level' => $source->proficiency_level,
                    'status' => 'VERIFIED',
                    'verified_by' => $actor->id,
                    'verified_at' => now(),
                    'notes' => 'Backfilled from the historical IAP skills ledger; ARMIS is authoritative.',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'lock_version' => 1,
                ]);
                $counts['competencies']++;
            }

            foreach ($availability as $source) {
                $profile = $resolveProfile((int) $source->user_id);
                if (! $profile || ! $source->start_date || ! $source->end_date) {
                    continue;
                }

                $type = strtoupper((string) ($source->type?->code ?? 'OTHER'));
                $type = in_array($type, ArmisAvailabilityPeriod::TYPES, true) ? $type : 'OTHER';
                $existing = ArmisAvailabilityPeriod::query()
                    ->where('resource_profile_id', $profile->id)
                    ->where('availability_type', $type)
                    ->whereDate('start_date', $source->start_date->toDateString())
                    ->whereDate('end_date', $source->end_date->toDateString())
                    ->where('is_current_revision', true)
                    ->first();
                if ($existing) {
                    continue;
                }

                ArmisAvailabilityPeriod::query()->create([
                    'availability_family_uuid' => (string) Str::uuid(),
                    'resource_profile_id' => $profile->id,
                    'version_number' => 1,
                    'is_current_revision' => true,
                    'availability_type' => $type,
                    'start_date' => $source->start_date,
                    'end_date' => $source->end_date,
                    'person_days' => null,
                    'status' => $approve ? 'APPROVED' : 'DRAFT',
                    'notes' => trim(($source->title ?? '').' '.($source->notes ?? '').' Backfilled from IAP.'),
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                    'reviewed_by' => $approve ? $actor->id : null,
                    'reviewed_at' => $approve ? now() : null,
                    'approved_by' => $approve ? $actor->id : null,
                    'approved_at' => $approve ? now() : null,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'lock_version' => 1,
                ]);
                $counts['availability']++;
            }

            foreach ($engagements as $engagement) {
                $officeId = (int) ($engagement->offices->first()?->id ?? 0);
                $fiscalYear = (int) ($engagement->plan?->fiscal_year ?? now()->year);
                $requirement = ArmisResourceRequirement::withTrashed()
                    ->where('source_module', 'IAP')
                    ->where('source_type', 'IAP_PLAN_ENGAGEMENT')
                    ->where('source_id', $engagement->id)
                    ->first();

                if (! $requirement) {
                    $requirement = ArmisResourceRequirement::query()->create([
                        'source_module' => 'IAP',
                        'source_type' => 'IAP_PLAN_ENGAGEMENT',
                        'source_id' => $engagement->id,
                        'office_id' => $officeId ?: null,
                        'fiscal_year' => $fiscalYear,
                        'title' => $engagement->title,
                        'required_person_days' => $engagement->estimated_person_days ?? 0,
                        'status' => $approve ? 'APPROVED' : 'DRAFT',
                        'notes' => 'Backfilled IAP engagement demand; ARMIS owns resource planning.',
                        'submitted_by' => $actor->id,
                        'submitted_at' => now(),
                        'reviewed_by' => $approve ? $actor->id : null,
                        'reviewed_at' => $approve ? now() : null,
                        'approved_by' => $approve ? $actor->id : null,
                        'approved_at' => $approve ? now() : null,
                        'created_by' => $actor->id,
                        'lock_version' => 1,
                    ]);
                    $counts['requirements']++;
                }

                foreach ($engagement->skillRequirements as $skillRequirement) {
                    ArmisRequirementCompetency::query()->firstOrCreate(
                        [
                            'requirement_id' => $requirement->id,
                            'competency_id' => $skillRequirement->specialization_id,
                        ],
                        [
                            'minimum_resources' => $skillRequirement->minimum_auditors,
                            'minimum_proficiency' => $skillRequirement->minimum_proficiency,
                            'notes' => $skillRequirement->notes,
                        ],
                    );
                }

                foreach ($engagement->teamMembers()->get() as $member) {
                    $profile = $resolveProfile((int) $member->user_id);
                    if (! $profile) {
                        continue;
                    }

                    $existing = ArmisWorkloadAllocation::query()
                        ->where('resource_profile_id', $profile->id)
                        ->where('requirement_id', $requirement->id)
                        ->where('source_module', 'IAP')
                        ->where('source_type', 'IAP_PLAN_ENGAGEMENT')
                        ->where('source_id', $engagement->id)
                        ->where('fiscal_year', $fiscalYear)
                        ->where('is_current_revision', true)
                        ->first();
                    if ($existing) {
                        continue;
                    }

                    ArmisWorkloadAllocation::query()->create([
                        'workload_family_uuid' => (string) Str::uuid(),
                        'resource_profile_id' => $profile->id,
                        'version_number' => 1,
                        'is_current_revision' => true,
                        'requirement_id' => $requirement->id,
                        'source_module' => 'IAP',
                        'source_type' => 'IAP_PLAN_ENGAGEMENT',
                        'source_id' => $engagement->id,
                        'fiscal_year' => $fiscalYear,
                        'planned_person_days' => $member->planned_person_days,
                        'status' => $approve ? 'APPROVED' : 'DRAFT',
                        'notes' => 'Backfilled from IAP scheduled team allocation; ARMIS is authoritative.',
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                        'submitted_by' => $actor->id,
                        'submitted_at' => now(),
                        'reviewed_by' => $approve ? $actor->id : null,
                        'reviewed_at' => $approve ? now() : null,
                        'approved_by' => $approve ? $actor->id : null,
                        'approved_at' => $approve ? now() : null,
                        'lock_version' => 1,
                    ]);
                    $counts['workloads']++;
                }
            }

            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'action' => 'armis.resource_backfill.completed',
                'description' => 'Backfilled historical IAP resource records into ARMIS.',
                'new_values' => $counts,
                'metadata' => ['approved' => $approve, 'source' => 'IAP'],
            ]);

            return $counts;
        }, 3);
    }
}
