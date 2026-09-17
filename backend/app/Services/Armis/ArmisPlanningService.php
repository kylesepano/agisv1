<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ArmisAvailabilityPeriod;
use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisResourceProfile;
use App\Models\ArmisResourceRequirement;
use App\Models\ArmisWorkloadAllocation;
use App\Models\ArmisWorkflowEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Governs ARMIS-3A availability, capacity, workload, and utilization data. */
class ArmisPlanningService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** @return Builder<ArmisAvailabilityPeriod> */
    public function availabilityQuery(User $actor): Builder
    {
        return $this->scope(ArmisAvailabilityPeriod::query(), $actor);
    }

    /** @return Builder<ArmisCapacitySubmission> */
    public function capacityQuery(User $actor): Builder
    {
        return $this->scope(ArmisCapacitySubmission::query(), $actor);
    }

    /** @return Builder<ArmisWorkloadAllocation> */
    public function workloadQuery(User $actor): Builder
    {
        return $this->scope(ArmisWorkloadAllocation::query(), $actor);
    }

    public function resolveAvailability(User $actor, int $id, bool $withHistory = true): ArmisAvailabilityPeriod
    {
        $query = $this->availabilityQuery($actor)->with($this->relations('availability'));
        if (! $withHistory) $query->where('is_current_revision', true);
        $record = $query->find($id);
        abort_unless($record, 404, 'The ARMIS availability period was not found in your scope.');

        return $record;
    }

    public function resolveCapacity(User $actor, int $id, bool $withHistory = true): ArmisCapacitySubmission
    {
        $query = $this->capacityQuery($actor)->with($this->relations('capacity'));
        if (! $withHistory) $query->where('is_current_revision', true);
        $record = $query->find($id);
        abort_unless($record, 404, 'The ARMIS capacity submission was not found in your scope.');

        return $record;
    }

    public function resolveWorkload(User $actor, int $id, bool $withHistory = true): ArmisWorkloadAllocation
    {
        $query = $this->workloadQuery($actor)->with($this->relations('workload'));
        if (! $withHistory) $query->where('is_current_revision', true);
        $record = $query->find($id);
        abort_unless($record, 404, 'The ARMIS workload allocation was not found in your scope.');

        return $record;
    }

    /** @param array<string, mixed> $attributes */
    public function createAvailability(Request $request, array $attributes): ArmisAvailabilityPeriod
    {
        $actor = $this->actor($request);
        $profile = $this->profile($actor, (int) $attributes['resource_profile_id']);
        $this->assertAvailabilityDates($profile->id, $attributes['start_date'], $attributes['end_date']);

        return DB::transaction(function () use ($request, $actor, $profile, $attributes): ArmisAvailabilityPeriod {
            $record = ArmisAvailabilityPeriod::query()->create([
                'availability_family_uuid' => (string) Str::uuid(),
                'resource_profile_id' => $profile->id,
                'version_number' => 1,
                'is_current_revision' => true,
                'availability_type' => $attributes['availability_type'],
                'start_date' => $attributes['start_date'],
                'end_date' => $attributes['end_date'],
                'person_days' => $attributes['person_days'] ?? null,
                'status' => 'DRAFT',
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->event($record, 'AVAILABILITY_CREATED', null, 'DRAFT', $actor, null, ['resourceProfileId' => $profile->id]);
            $this->record($request, 'armis.availability.created', 'ARMIS availability period created.', $record, $profile->id, null, $record->toArray());

            return $record->fresh($this->relations('availability'));
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function updateAvailability(Request $request, ArmisAvailabilityPeriod $period, array $attributes): ArmisAvailabilityPeriod
    {
        $actor = $this->actor($request);
        $period->loadMissing('resourceProfile');
        $this->assertOffice($actor, $period->resourceProfile?->office_id);

        return DB::transaction(function () use ($request, $actor, $period, $attributes): ArmisAvailabilityPeriod {
            $locked = ArmisAvailabilityPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $this->assertLock($locked, (int) $attributes['lock_version']);
            abort_unless($locked->is_current_revision && in_array($locked->status, ['DRAFT', 'RETURNED'], true), 409, 'Only a current Draft or Returned availability period can be edited.');
            $start = $attributes['start_date'] ?? $locked->start_date?->toDateString();
            $end = $attributes['end_date'] ?? $locked->end_date?->toDateString();
            $this->assertAvailabilityDates($locked->resource_profile_id, $start, $end, $locked->id);
            $before = $locked->toArray();
            $locked->update([
                'availability_type' => $attributes['availability_type'] ?? $locked->availability_type,
                'start_date' => $start,
                'end_date' => $end,
                'person_days' => array_key_exists('person_days', $attributes) ? $attributes['person_days'] : $locked->person_days,
                'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $locked->notes,
                'updated_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($locked, 'AVAILABILITY_UPDATED', $before['status'] ?? null, $locked->status, $actor, null, ['lockVersion' => $locked->lock_version]);
            $this->record($request, 'armis.availability.updated', 'ARMIS availability period updated.', $locked, $locked->resource_profile_id, $before, $locked->fresh()->toArray());

            return $locked->fresh($this->relations('availability'));
        }, 3);
    }

    /** Create a new Draft correction after an approved or locked period. */
    public function reviseAvailability(Request $request, ArmisAvailabilityPeriod $period, array $attributes): ArmisAvailabilityPeriod
    {
        $actor = $this->actor($request);
        $period->loadMissing('resourceProfile');
        $this->assertOffice($actor, $period->resourceProfile?->office_id);

        return DB::transaction(function () use ($request, $actor, $period, $attributes): ArmisAvailabilityPeriod {
            $current = ArmisAvailabilityPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $this->assertLock($current, (int) $attributes['lock_version']);
            abort_unless($current->is_current_revision && in_array($current->status, ['APPROVED', 'LOCKED'], true), 409, 'Only an approved or locked availability period can be revised.');
            $start = $attributes['start_date'] ?? $current->start_date?->toDateString();
            $end = $attributes['end_date'] ?? $current->end_date?->toDateString();
            $this->assertAvailabilityDates($current->resource_profile_id, $start, $end, $current->id);
            $current->update(['is_current_revision' => false, 'updated_by' => $actor->id]);
            $revision = ArmisAvailabilityPeriod::query()->create([
                'availability_family_uuid' => $current->availability_family_uuid,
                'resource_profile_id' => $current->resource_profile_id,
                'version_number' => (int) $current->version_number + 1,
                'supersedes_id' => $current->id,
                'is_current_revision' => true,
                'availability_type' => $attributes['availability_type'] ?? $current->availability_type,
                'start_date' => $start,
                'end_date' => $end,
                'person_days' => array_key_exists('person_days', $attributes) ? $attributes['person_days'] : $current->person_days,
                'status' => 'DRAFT',
                'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $current->notes,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->event($revision, 'AVAILABILITY_REVISED', $current->status, 'DRAFT', $actor, null, ['supersedesId' => $current->id, 'versionNumber' => $revision->version_number]);
            $this->record($request, 'armis.availability.revised', 'ARMIS availability correction created as a new revision.', $revision, $revision->resource_profile_id, ['supersedesId' => $current->id, 'status' => $current->status], $revision->toArray());

            return $revision->fresh($this->relations('availability'));
        }, 3);
    }

    public function submitAvailability(Request $request, ArmisAvailabilityPeriod $period, int $lockVersion): ArmisAvailabilityPeriod
    {
        $actor = $this->actor($request);
        $period->loadMissing('resourceProfile');
        $this->assertOffice($actor, $period->resourceProfile?->office_id);

        return DB::transaction(function () use ($request, $actor, $period, $lockVersion): ArmisAvailabilityPeriod {
            $locked = ArmisAvailabilityPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $this->assertLock($locked, $lockVersion);
            abort_unless($locked->is_current_revision && in_array($locked->status, ['DRAFT', 'RETURNED'], true), 409, 'Only a current Draft or Returned availability period can be submitted.');
            $profile = $locked->resourceProfile()->firstOrFail();
            abort_if($profile->status !== 'ACTIVE', 409, 'The linked ARMIS resource must be active before availability is submitted.');
            $locked->update([
                'status' => 'SUBMITTED', 'submitted_by' => $actor->id, 'submitted_at' => now(),
                'reviewed_by' => null, 'reviewed_at' => null, 'approved_by' => null, 'approved_at' => null,
                'updated_by' => $actor->id, 'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($locked, 'AVAILABILITY_SUBMITTED', 'DRAFT', 'SUBMITTED', $actor);
            $this->record($request, 'armis.availability.submitted', 'ARMIS availability submitted for review.', $locked, $locked->resource_profile_id, ['status' => 'DRAFT'], ['status' => 'SUBMITTED']);
            $this->notifyReviewers($locked, $profile, $actor, 'availability', 'submitted');

            return $locked->fresh($this->relations('availability'));
        }, 3);
    }

    public function reviewAvailability(Request $request, ArmisAvailabilityPeriod $period, string $decision, int $lockVersion, ?string $notes = null): ArmisAvailabilityPeriod
    {
        $actor = $this->actor($request);
        $period->loadMissing('resourceProfile');
        $this->assertOffice($actor, $period->resourceProfile?->office_id);

        return DB::transaction(function () use ($request, $actor, $period, $decision, $lockVersion, $notes): ArmisAvailabilityPeriod {
            $locked = ArmisAvailabilityPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $this->assertLock($locked, $lockVersion);
            $profile = $locked->resourceProfile()->firstOrFail();
            $this->assertIndependent($actor, $locked->submitted_by, $profile->user_id);
            abort_unless($locked->status === 'SUBMITTED', 409, 'Only submitted availability can be reviewed.');
            if ($decision === 'RETURN') {
                abort_if(blank($notes), 422, 'A return explanation is required.');
                $to = 'RETURNED';
            } elseif ($decision === 'APPROVE') {
                $to = 'APPROVED';
            } else {
                throw ValidationException::withMessages(['decision' => ['Availability review must be APPROVE or RETURN.']]);
            }
            $from = $locked->status;
            $locked->update([
                'status' => $to, 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
                'approved_by' => $decision === 'APPROVE' ? $actor->id : null,
                'approved_at' => $decision === 'APPROVE' ? now() : null,
                'notes' => $notes ?: $locked->notes,
                'updated_by' => $actor->id, 'lock_version' => $locked->lock_version + 1,
            ]);
            $event = $decision === 'APPROVE' ? 'AVAILABILITY_APPROVED' : 'AVAILABILITY_RETURNED';
            $this->event($locked, $event, $from, $to, $actor, $notes);
            $this->record($request, 'armis.availability.'.strtolower($decision), "ARMIS availability {$to}.", $locked, $locked->resource_profile_id, ['status' => $from], ['status' => $to, 'notes' => $notes]);
            $this->notifyOwner($locked, $profile, $actor, 'availability', strtolower($decision));

            return $locked->fresh($this->relations('availability'));
        }, 3);
    }

    public function lockAvailability(Request $request, ArmisAvailabilityPeriod $period, int $lockVersion): ArmisAvailabilityPeriod
    {
        $actor = $this->actor($request);
        $period->loadMissing('resourceProfile');
        $this->assertOffice($actor, $period->resourceProfile?->office_id);

        return DB::transaction(function () use ($request, $actor, $period, $lockVersion): ArmisAvailabilityPeriod {
            $locked = ArmisAvailabilityPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $this->assertLock($locked, $lockVersion);
            $profile = $locked->resourceProfile()->firstOrFail();
            abort_unless($locked->is_current_revision && $locked->status === 'APPROVED', 409, 'Only the current approved availability can be locked.');
            $locked->update(['status' => 'LOCKED', 'updated_by' => $actor->id, 'lock_version' => $locked->lock_version + 1]);
            $this->event($locked, 'AVAILABILITY_LOCKED', 'APPROVED', 'LOCKED', $actor);
            $this->record($request, 'armis.availability.locked', 'ARMIS availability period locked.', $locked, $profile->id, ['status' => 'APPROVED'], ['status' => 'LOCKED']);

            return $locked->fresh($this->relations('availability'));
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function createCapacity(Request $request, array $attributes): ArmisCapacitySubmission
    {
        $actor = $this->actor($request);
        $profile = $this->profile($actor, (int) $attributes['resource_profile_id']);

        return DB::transaction(function () use ($request, $actor, $profile, $attributes): ArmisCapacitySubmission {
            $current = ArmisCapacitySubmission::query()->where('resource_profile_id', $profile->id)->where('fiscal_year', $attributes['fiscal_year'])->where('is_current_revision', true)->lockForUpdate()->latest('version_number')->first();
            if ($current && in_array($current->status, ['DRAFT', 'SUBMITTED', 'RETURNED'], true)) {
                throw ValidationException::withMessages(['fiscalYear' => ['Complete or revise the current capacity submission before creating another version.']]);
            }
            if ($current) {
                $current->update(['is_current_revision' => false]);
            }
            $version = (int) ArmisCapacitySubmission::query()->where('resource_profile_id', $profile->id)->where('fiscal_year', $attributes['fiscal_year'])->max('version_number') + 1;
            $record = ArmisCapacitySubmission::query()->create([
                'resource_profile_id' => $profile->id, 'fiscal_year' => $attributes['fiscal_year'],
                'version_number' => $version, 'is_current_revision' => true,
                'available_person_days' => $attributes['available_person_days'], 'status' => 'DRAFT',
                'notes' => $attributes['notes'] ?? null, 'supersedes_id' => $current?->id,
                'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            $this->event($record, 'CAPACITY_CREATED', null, 'DRAFT', $actor, null, ['versionNumber' => $version, 'fiscalYear' => $record->fiscal_year]);
            $this->record($request, 'armis.capacity.created', 'ARMIS capacity submission created.', $record, $profile->id, null, $record->toArray());

            return $record->fresh($this->relations('capacity'));
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function updateCapacity(Request $request, ArmisCapacitySubmission $capacity, array $attributes): ArmisCapacitySubmission
    {
        $actor = $this->actor($request);
        $capacity->loadMissing('resourceProfile');
        $this->assertOffice($actor, $capacity->resourceProfile?->office_id);

        return DB::transaction(function () use ($request, $actor, $capacity, $attributes): ArmisCapacitySubmission {
            $locked = ArmisCapacitySubmission::query()->lockForUpdate()->findOrFail($capacity->id);
            $this->assertLock($locked, (int) $attributes['lock_version']);
            abort_unless($locked->is_current_revision && in_array($locked->status, ['DRAFT', 'RETURNED'], true), 409, 'Only a current Draft or Returned capacity submission can be edited.');
            $before = $locked->toArray();
            $locked->update(['available_person_days' => $attributes['available_person_days'] ?? $locked->available_person_days, 'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $locked->notes, 'updated_by' => $actor->id, 'lock_version' => $locked->lock_version + 1]);
            $this->event($locked, 'CAPACITY_UPDATED', $before['status'] ?? null, $locked->status, $actor);
            $this->record($request, 'armis.capacity.updated', 'ARMIS capacity submission updated.', $locked, $locked->resource_profile_id, $before, $locked->fresh()->toArray());

            return $locked->fresh($this->relations('capacity'));
        }, 3);
    }

    public function submitCapacity(Request $request, ArmisCapacitySubmission $capacity, int $lockVersion): ArmisCapacitySubmission
    {
        return $this->submitRecord($request, $capacity, $lockVersion, 'capacity');
    }

    public function reviewCapacity(Request $request, ArmisCapacitySubmission $capacity, string $decision, int $lockVersion, ?string $notes = null): ArmisCapacitySubmission
    {
        return $this->reviewRecord($request, $capacity, $decision, $lockVersion, $notes, 'capacity');
    }

    public function lockCapacity(Request $request, ArmisCapacitySubmission $capacity, int $lockVersion): ArmisCapacitySubmission
    {
        return $this->lockRecord($request, $capacity, $lockVersion, 'capacity');
    }

    /** @param array<string, mixed> $attributes */
    public function createWorkload(Request $request, array $attributes): ArmisWorkloadAllocation
    {
        $actor = $this->actor($request);
        $profile = $this->profile($actor, (int) $attributes['resource_profile_id']);
        $requirement = $this->requirement($actor, $attributes['requirement_id'] ?? null);

        return DB::transaction(function () use ($request, $actor, $profile, $requirement, $attributes): ArmisWorkloadAllocation {
            $current = $this->currentWorkload($profile->id, $attributes);
            if ($current && in_array($current->status, ['DRAFT', 'SUBMITTED', 'RETURNED'], true)) {
                throw ValidationException::withMessages(['workload' => ['Complete or revise the current workload allocation before creating another version.']]);
            }
            if ($current) {
                $current->update(['is_current_revision' => false]);
            }
            $version = (int) ArmisWorkloadAllocation::query()->where('resource_profile_id', $profile->id)->when($attributes['fiscal_year'] ?? null, fn ($q, $year) => $q->where('fiscal_year', $year))->max('version_number') + 1;
            $record = ArmisWorkloadAllocation::query()->create([
                'workload_family_uuid' => $current?->workload_family_uuid ?? (string) Str::uuid(),
                'resource_profile_id' => $profile->id, 'version_number' => $version,
                'supersedes_id' => $current?->id, 'is_current_revision' => true,
                'requirement_id' => $requirement?->id, 'source_module' => $attributes['source_module'] ?? 'ARMIS',
                'source_type' => $attributes['source_type'], 'source_id' => $attributes['source_id'] ?? null,
                'fiscal_year' => $attributes['fiscal_year'] ?? null, 'planned_person_days' => $attributes['planned_person_days'],
                'status' => 'DRAFT', 'notes' => $attributes['notes'] ?? null,
                'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            $this->event($record, 'WORKLOAD_CREATED', null, 'DRAFT', $actor, null, ['versionNumber' => $version]);
            $this->record($request, 'armis.workload.created', 'ARMIS workload allocation created.', $record, $profile->id, null, $record->toArray());

            return $record->fresh($this->relations('workload'));
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function updateWorkload(Request $request, ArmisWorkloadAllocation $workload, array $attributes): ArmisWorkloadAllocation
    {
        $actor = $this->actor($request);
        $workload->loadMissing('resourceProfile');
        $this->assertOffice($actor, $workload->resourceProfile?->office_id);

        return DB::transaction(function () use ($request, $actor, $workload, $attributes): ArmisWorkloadAllocation {
            $locked = ArmisWorkloadAllocation::query()->lockForUpdate()->findOrFail($workload->id);
            $this->assertLock($locked, (int) $attributes['lock_version']);
            abort_unless($locked->is_current_revision && in_array($locked->status, ['DRAFT', 'RETURNED'], true), 409, 'Only a current Draft or Returned workload allocation can be edited.');
            $before = $locked->toArray();
            $locked->update(['planned_person_days' => $attributes['planned_person_days'] ?? $locked->planned_person_days, 'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $locked->notes, 'updated_by' => $actor->id, 'lock_version' => $locked->lock_version + 1]);
            $this->event($locked, 'WORKLOAD_UPDATED', $before['status'] ?? null, $locked->status, $actor);
            $this->record($request, 'armis.workload.updated', 'ARMIS workload allocation updated.', $locked, $locked->resource_profile_id, $before, $locked->fresh()->toArray());

            return $locked->fresh($this->relations('workload'));
        }, 3);
    }

    public function submitWorkload(Request $request, ArmisWorkloadAllocation $workload, int $lockVersion): ArmisWorkloadAllocation
    {
        return $this->submitRecord($request, $workload, $lockVersion, 'workload');
    }

    public function reviewWorkload(Request $request, ArmisWorkloadAllocation $workload, string $decision, int $lockVersion, ?string $notes = null): ArmisWorkloadAllocation
    {
        if ($decision === 'APPROVE') {
            $workload->loadMissing('resourceProfile');
            $capacity = ArmisCapacitySubmission::query()->where('resource_profile_id', $workload->resource_profile_id)->where('fiscal_year', $workload->fiscal_year)->whereIn('status', ['APPROVED', 'LOCKED'])->where('is_current_revision', true)->first();
            if (! $capacity) {
                throw ValidationException::withMessages(['decision' => ['An approved ARMIS capacity submission is required before workload can be approved.']]);
            }
            $allocated = (float) ArmisWorkloadAllocation::query()->where('resource_profile_id', $workload->resource_profile_id)->where('fiscal_year', $workload->fiscal_year)->whereIn('status', ['APPROVED', 'LOCKED'])->where('is_current_revision', true)->where('id', '<>', $workload->id)->sum('planned_person_days');
            if (round($allocated + (float) $workload->planned_person_days, 2) > (float) $capacity->available_person_days) {
                throw ValidationException::withMessages(['plannedPersonDays' => ['Approved workload would exceed the current approved ARMIS capacity.']]);
            }
        }

        return $this->reviewRecord($request, $workload, $decision, $lockVersion, $notes, 'workload');
    }

    public function lockWorkload(Request $request, ArmisWorkloadAllocation $workload, int $lockVersion): ArmisWorkloadAllocation
    {
        return $this->lockRecord($request, $workload, $lockVersion, 'workload');
    }

    /** @return array{rows: list<array<string, mixed>>, summary: array<string, mixed>} */
    public function utilization(User $actor, int $fiscalYear, ?int $profileId = null): array
    {
        $profiles = ArmisResourceProfile::query()->whereIn('status', ['ACTIVE', 'SUSPENDED', 'INACTIVE'])->orderBy('resource_code');
        if ($profileId) {
            $profiles->whereKey($profileId);
        }
        if (! $actor->hasGlobalOfficeAccess()) {
            $profiles->where('office_id', $actor->office_id);
        }
        $profiles = $profiles->with(['user:id,employee_id,name,initials', 'office:id,code,name'])->get();
        $rows = $profiles->map(function (ArmisResourceProfile $profile) use ($fiscalYear): array {
            $capacity = ArmisCapacitySubmission::query()->where('resource_profile_id', $profile->id)->where('fiscal_year', $fiscalYear)->where('is_current_revision', true)->whereIn('status', ['APPROVED', 'LOCKED'])->latest('version_number')->first();
            $planned = (float) ArmisWorkloadAllocation::query()->where('resource_profile_id', $profile->id)->where('fiscal_year', $fiscalYear)->where('is_current_revision', true)->whereIn('status', ['APPROVED', 'LOCKED'])->sum('planned_person_days');
            $available = (float) ArmisAvailabilityPeriod::query()->where('resource_profile_id', $profile->id)->where('is_current_revision', true)->whereIn('status', ['APPROVED', 'LOCKED'])->where('availability_type', 'AVAILABLE')->whereYear('start_date', $fiscalYear)->sum('person_days');
            $unavailable = (float) ArmisAvailabilityPeriod::query()->where('resource_profile_id', $profile->id)->where('is_current_revision', true)->whereIn('status', ['APPROVED', 'LOCKED'])->whereIn('availability_type', ['UNAVAILABLE', 'LEAVE', 'TRAINING', 'OTHER'])->whereYear('start_date', $fiscalYear)->sum('person_days');
            $capacityDays = $capacity ? (float) $capacity->available_person_days : null;
            $remaining = $capacityDays === null ? null : round($capacityDays - $planned, 2);

            return [
                'resourceProfileId' => $profile->id,
                'resourceCode' => $profile->resource_code,
                'resourceUser' => $profile->user ? ['id' => $profile->user->id, 'employeeId' => $profile->user->employee_id, 'name' => $profile->user->name, 'initials' => $profile->user->initials] : null,
                'office' => $profile->office ? ['id' => $profile->office->id, 'code' => $profile->office->code, 'name' => $profile->office->name] : null,
                'fiscalYear' => $fiscalYear,
                'capacityPersonDays' => $capacityDays,
                'plannedPersonDays' => round($planned, 2),
                'remainingPersonDays' => $remaining,
                'availablePeriodDays' => round($available, 2),
                'unavailablePeriodDays' => round($unavailable, 2),
                'utilizationPercent' => $capacityDays && $capacityDays > 0 ? round(($planned / $capacityDays) * 100, 2) : null,
                'overCapacity' => $remaining !== null && $remaining < 0,
            ];
        })->values()->all();

        $capacityTotal = round((float) collect($rows)->sum(fn (array $row) => $row['capacityPersonDays'] ?? 0), 2);
        $plannedTotal = round((float) collect($rows)->sum('plannedPersonDays'), 2);

        return [
            'rows' => $rows,
            'summary' => [
                'fiscalYear' => $fiscalYear,
                'resourceCount' => count($rows),
                'capacityPersonDays' => $capacityTotal,
                'plannedPersonDays' => $plannedTotal,
                'remainingPersonDays' => round($capacityTotal - $plannedTotal, 2),
                'utilizationPercent' => $capacityTotal > 0 ? round(($plannedTotal / $capacityTotal) * 100, 2) : null,
                'overCapacityCount' => collect($rows)->where('overCapacity', true)->count(),
            ],
        ];
    }

    private function submitRecord(Request $request, Model $record, int $lockVersion, string $kind): Model
    {
        $actor = $this->actor($request);
        $record->loadMissing('resourceProfile');
        $this->assertOffice($actor, $record->resourceProfile?->office_id);

        return DB::transaction(function () use ($request, $actor, $record, $lockVersion, $kind): Model {
            $model = $record::query()->lockForUpdate()->findOrFail($record->id);
            $this->assertLock($model, $lockVersion);
            abort_unless($model->is_current_revision && in_array($model->status, ['DRAFT', 'RETURNED'], true), 409, "Only a current Draft or Returned {$kind} record can be submitted.");
            $profile = $model->resourceProfile()->firstOrFail();
            abort_if($profile->status !== 'ACTIVE', 409, 'The linked ARMIS resource must be active before planning data is submitted.');
            $model->update(['status' => 'SUBMITTED', 'submitted_by' => $actor->id, 'submitted_at' => now(), 'reviewed_by' => null, 'reviewed_at' => null, 'approved_by' => null, 'approved_at' => null, 'updated_by' => $actor->id, 'lock_version' => $model->lock_version + 1]);
            $this->event($model, strtoupper($kind).'_SUBMITTED', 'DRAFT', 'SUBMITTED', $actor);
            $this->record($request, 'armis.'.$kind.'.submitted', "ARMIS {$kind} submitted for review.", $model, $model->resource_profile_id, ['status' => 'DRAFT'], ['status' => 'SUBMITTED']);
            $this->notifyReviewers($model, $profile, $actor, $kind, 'submitted');

            return $model->fresh($this->relations($kind));
        }, 3);
    }

    private function reviewRecord(Request $request, Model $record, string $decision, int $lockVersion, ?string $notes, string $kind): Model
    {
        $actor = $this->actor($request);
        $record->loadMissing('resourceProfile');
        $this->assertOffice($actor, $record->resourceProfile?->office_id);

        return DB::transaction(function () use ($request, $actor, $record, $decision, $lockVersion, $notes, $kind): Model {
            $model = $record::query()->lockForUpdate()->findOrFail($record->id);
            $this->assertLock($model, $lockVersion);
            $profile = $model->resourceProfile()->firstOrFail();
            $this->assertIndependent($actor, $model->submitted_by, $profile->user_id);
            abort_unless($model->status === 'SUBMITTED', 409, "Only submitted {$kind} can be reviewed.");
            if ($decision === 'RETURN') {
                abort_if(blank($notes), 422, 'A return explanation is required.');
                $to = 'RETURNED';
            } elseif ($decision === 'APPROVE') {
                $to = 'APPROVED';
            } else {
                throw ValidationException::withMessages(['decision' => ['The review decision must be APPROVE or RETURN.']]);
            }
            $from = $model->status;
            $model->update(['status' => $to, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'approved_by' => $decision === 'APPROVE' ? $actor->id : null, 'approved_at' => $decision === 'APPROVE' ? now() : null, 'notes' => $notes ?: $model->notes, 'updated_by' => $actor->id, 'lock_version' => $model->lock_version + 1]);
            $this->event($model, strtoupper($kind).'_'.($decision === 'APPROVE' ? 'APPROVED' : 'RETURNED'), $from, $to, $actor, $notes);
            $this->record($request, 'armis.'.$kind.'.'.strtolower($decision), "ARMIS {$kind} {$to}.", $model, $model->resource_profile_id, ['status' => $from], ['status' => $to, 'notes' => $notes]);
            $this->notifyOwner($model, $profile, $actor, $kind, strtolower($decision));

            return $model->fresh($this->relations($kind));
        }, 3);
    }

    private function lockRecord(Request $request, Model $record, int $lockVersion, string $kind): Model
    {
        $actor = $this->actor($request);
        $record->loadMissing('resourceProfile');
        $this->assertOffice($actor, $record->resourceProfile?->office_id);

        return DB::transaction(function () use ($request, $actor, $record, $lockVersion, $kind): Model {
            $model = $record::query()->lockForUpdate()->findOrFail($record->id);
            $this->assertLock($model, $lockVersion);
            abort_unless($model->is_current_revision && $model->status === 'APPROVED', 409, "Only the current approved {$kind} can be locked.");
            $model->update(['status' => 'LOCKED', 'updated_by' => $actor->id, 'lock_version' => $model->lock_version + 1]);
            $this->event($model, strtoupper($kind).'_LOCKED', 'APPROVED', 'LOCKED', $actor);
            $this->record($request, 'armis.'.$kind.'.locked', "ARMIS {$kind} locked.", $model, $model->resource_profile_id, ['status' => 'APPROVED'], ['status' => 'LOCKED']);

            return $model->fresh($this->relations($kind));
        }, 3);
    }

    private function currentWorkload(int $profileId, array $attributes): ?ArmisWorkloadAllocation
    {
        return ArmisWorkloadAllocation::query()->where('resource_profile_id', $profileId)->where('is_current_revision', true)->where('source_module', $attributes['source_module'] ?? 'ARMIS')->where('source_type', $attributes['source_type'])->when(array_key_exists('source_id', $attributes) && $attributes['source_id'] !== null, fn ($q) => $q->where('source_id', $attributes['source_id']), fn ($q) => $q->whereNull('source_id'))->when(array_key_exists('requirement_id', $attributes) && $attributes['requirement_id'] !== null, fn ($q) => $q->where('requirement_id', $attributes['requirement_id']), fn ($q) => $q->whereNull('requirement_id'))->when($attributes['fiscal_year'] ?? null, fn ($q, $year) => $q->where('fiscal_year', $year))->latest('version_number')->lockForUpdate()->first();
    }

    private function profile(User $actor, int $id): ArmisResourceProfile
    {
        $profile = ArmisResourceProfile::query()->find($id);
        abort_unless($profile, 404, 'The ARMIS resource profile was not found.');
        $this->assertOffice($actor, $profile->office_id);
        abort_if($profile->status === 'ARCHIVED' || $profile->trashed(), 409, 'Archived ARMIS resource profiles cannot receive planning data.');

        return $profile;
    }

    private function requirement(User $actor, mixed $id): ?ArmisResourceRequirement
    {
        if ($id === null || $id === '') return null;
        $requirement = ArmisResourceRequirement::query()->find((int) $id);
        abort_unless($requirement, 422, 'The ARMIS resource requirement was not found.');
        $this->assertOffice($actor, $requirement->office_id);

        return $requirement;
    }

    private function assertAvailabilityDates(int $profileId, string $start, string $end, ?int $ignoreId = null): void
    {
        if ($start > $end) {
            throw ValidationException::withMessages(['endDate' => ['The availability end date must be on or after the start date.']]);
        }
        $overlap = ArmisAvailabilityPeriod::query()->where('resource_profile_id', $profileId)->where('is_current_revision', true)->whereIn('status', ['DRAFT', 'SUBMITTED', 'APPROVED', 'LOCKED'])->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId))->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->exists();
        abort_if($overlap, 422, 'The availability period overlaps another current ARMIS period for this resource.');
    }

    private function scope(Builder $query, User $actor): Builder
    {
        return $query->whereHas('resourceProfile', function (Builder $profile) use ($actor): void {
            if (! $actor->hasGlobalOfficeAccess()) $profile->where('office_id', $actor->office_id);
        });
    }

    private function assertOffice(User $actor, ?int $officeId): void
    {
        abort_unless($officeId !== null && ($actor->hasGlobalOfficeAccess() || (int) $actor->office_id === $officeId), 403, 'This ARMIS planning record is outside your office scope.');
    }

    private function assertIndependent(User $actor, ?int $submittedBy, ?int $resourceOwner): void
    {
        if ((int) $actor->id === (int) $submittedBy || (int) $actor->id === (int) $resourceOwner) {
            throw ValidationException::withMessages(['review' => ['The submitter and resource owner cannot independently approve planning data.']]);
        }
    }

    private function assertLock(Model $record, int $expected): void
    {
        if ((int) $record->lock_version !== $expected) {
            throw ValidationException::withMessages(['lockVersion' => ['This ARMIS planning record changed in another session. Refresh before continuing.']]);
        }
    }

    /** @return list<string> */
    private function relations(string $kind): array
    {
        return match ($kind) {
            'availability' => ['resourceProfile.user:id,employee_id,name,initials', 'resourceProfile.office:id,code,name', 'submitter:id,employee_id,name,initials', 'reviewer:id,employee_id,name,initials', 'approver:id,employee_id,name,initials', 'supersedes:id,version_number,status'],
            'capacity' => ['resourceProfile.user:id,employee_id,name,initials', 'resourceProfile.office:id,code,name', 'submitter:id,employee_id,name,initials', 'reviewer:id,employee_id,name,initials', 'approver:id,employee_id,name,initials', 'supersedes:id,version_number,status'],
            default => ['resourceProfile.user:id,employee_id,name,initials', 'resourceProfile.office:id,code,name', 'requirement:id,title,status', 'submitter:id,employee_id,name,initials', 'reviewer:id,employee_id,name,initials', 'approver:id,employee_id,name,initials', 'supersedes:id,version_number,status'],
        };
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function notifyReviewers(Model $record, ArmisResourceProfile $profile, User $actor, string $kind, string $action): void
    {
        $permission = "armis.{$kind}.approve";
        $ids = User::query()->where('is_active', true)->where(function (Builder $query) use ($permission): void {
            $query->whereHas('roles.permissions', fn (Builder $permissionQuery) => $permissionQuery->where('code', $permission))->orWhereHas('role.permissions', fn (Builder $permissionQuery) => $permissionQuery->where('code', $permission));
        })->pluck('id')->reject(fn (int $id): bool => $id === (int) $actor->id)->values();
        DB::afterCommit(fn () => $this->notifications->send($ids, [
            'actorId' => $actor->id, 'type' => 'ARMIS_PLANNING', 'category' => 'SYSTEM', 'priority' => 'HIGH', 'moduleCode' => 'ARMIS',
            'title' => "ARMIS {$kind} awaiting review", 'message' => "{$profile->resource_code} has ARMIS {$kind} data awaiting independent review.",
            'actionUrl' => "/audit-resource-management/resources/{$profile->id}", 'actionLabel' => 'Review ARMIS planning', 'subjectType' => $record::class, 'subjectId' => $record->id,
            'subjectCode' => $profile->resource_code, 'dedupeKey' => "armis-{$kind}:{$record->id}:{$record->lock_version}:{$action}",
        ]));
    }

    private function notifyOwner(Model $record, ArmisResourceProfile $profile, User $actor, string $kind, string $action): void
    {
        if (! $profile->user_id || (int) $profile->user_id === (int) $actor->id) return;
        DB::afterCommit(fn () => $this->notifications->send([$profile->user_id], [
            'actorId' => $actor->id, 'type' => 'ARMIS_PLANNING', 'category' => 'SYSTEM', 'priority' => 'NORMAL', 'moduleCode' => 'ARMIS',
            'title' => "ARMIS {$kind} review updated", 'message' => "Your ARMIS {$kind} record was {$action} by an independent reviewer.",
            'actionUrl' => "/audit-resource-management/resources/{$profile->id}", 'actionLabel' => 'View ARMIS planning', 'subjectType' => $record::class, 'subjectId' => $record->id,
            'subjectCode' => $profile->resource_code, 'dedupeKey' => "armis-{$kind}:{$record->id}:{$record->lock_version}:owner:{$action}",
        ]));
    }

    /** @param array<string, mixed>|null $oldValues @param array<string, mixed>|null $newValues */
    private function record(Request $request, string $action, string $description, Model $record, int $profileId, ?array $oldValues, ?array $newValues): void
    {
        $actor = $request->user();
        $metadata = ['module' => 'ARMIS', 'resourceProfileId' => $profileId, 'recordType' => $record::class, 'recordId' => $record->id];
        ActivityLog::query()->create(['user_id' => $actor?->id, 'action' => $action, 'description' => $description, 'old_values' => $oldValues, 'new_values' => $newValues, 'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000), 'metadata' => $metadata]);
        AuditLog::query()->create(['user_id' => $actor?->id, 'action' => $action, 'auditable_type' => $record::class, 'auditable_id' => $record->id, 'old_values' => $oldValues, 'new_values' => $newValues, 'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000), 'metadata' => $metadata]);
    }

    /** @param array<string, mixed>|null $metadata */
    private function event(Model $record, string $code, ?string $from, ?string $to, User $actor, ?string $reason = null, ?array $metadata = null): void
    {
        ArmisWorkflowEvent::query()->create(['subject_type' => $record::class, 'subject_id' => $record->id, 'event_code' => $code, 'from_status' => $from, 'to_status' => $to, 'actor_id' => $actor->id, 'reason' => $reason, 'metadata' => $metadata]);
    }
}
