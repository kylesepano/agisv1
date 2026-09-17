<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ArmisActualPersonDay;
use App\Models\ArmisAssignmentCompetency;
use App\Models\ArmisAvailabilityPeriod;
use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisCompetency;
use App\Models\ArmisEngagementAssignment;
use App\Models\ArmisResourceProfile;
use App\Models\ArmisResourceRequirement;
use App\Models\ArmisWorkflowEvent;
use App\Models\AuditEngagement;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Governs ARMIS-4A assignments, actuals, conflicts, and capacity checks. */
class ArmisAssignmentService
{
    /** @var list<string> */
    private const PROFICIENCY_LEVELS = ['BASIC', 'INTERMEDIATE', 'ADVANCED', 'EXPERT'];

    public function __construct(private readonly NotificationService $notifications) {}

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return [
            'assignmentStatuses' => collect(ArmisEngagementAssignment::STATUSES)->map(fn (string $code): array => [
                'code' => $code,
                'label' => str($code)->replace('_', ' ')->lower()->headline()->toString(),
            ])->values(),
            'actualStatuses' => collect(ArmisActualPersonDay::STATUSES)->map(fn (string $code): array => [
                'code' => $code,
                'label' => str($code)->replace('_', ' ')->lower()->headline()->toString(),
            ])->values(),
            'assignmentRoles' => collect(ArmisEngagementAssignment::ROLES)->map(fn (string $code): array => [
                'code' => $code,
                'label' => str($code)->replace('_', ' ')->lower()->headline()->toString(),
            ])->values(),
            'proficiencyLevels' => collect(self::PROFICIENCY_LEVELS)->map(fn (string $code): array => [
                'code' => $code,
                'label' => str($code)->replace('_', ' ')->lower()->headline()->toString(),
            ])->values(),
            'workflow' => [
                'editableStatuses' => ['DRAFT', 'RETURNED'],
                'reviewStatus' => 'SUBMITTED',
                'approvedStatus' => 'APPROVED',
                'lockedStatus' => 'LOCKED',
            ],
            'rules' => [
                'approvedCapacityRequired' => true,
                'verifiedCompetencyRequired' => true,
                'actualVarianceReasonRequired' => true,
                'aemsProviderAuthority' => 'UNCHANGED',
            ],
        ];
    }

    /** @return Builder<ArmisEngagementAssignment> */
    public function assignmentQuery(User $actor): Builder
    {
        return $this->scopeAssignments(ArmisEngagementAssignment::query(), $actor)
            ->with($this->assignmentRelations());
    }

    /** @return Builder<ArmisActualPersonDay> */
    public function actualQuery(User $actor): Builder
    {
        return ArmisActualPersonDay::query()
            ->whereHas('assignment', fn (Builder $query) => $this->scopeAssignments($query, $actor));
    }

    public function resolveAssignment(User $actor, int $id, bool $withHistory = true): ArmisEngagementAssignment
    {
        $query = $this->assignmentQuery($actor);
        if (! $withHistory) $query->where('is_current_revision', true);
        $record = $query->find($id);
        abort_unless($record, 404, 'The ARMIS assignment was not found in your scope.');

        return $record;
    }

    public function resolveActual(User $actor, int $id, bool $withHistory = true): ArmisActualPersonDay
    {
        $query = $this->actualQuery($actor)->with($this->actualRelations());
        if (! $withHistory) $query->where('is_current_revision', true);
        $record = $query->find($id);
        abort_unless($record, 404, 'The ARMIS actual person-day record was not found in your scope.');

        return $record;
    }

    /** @param array<string, mixed> $attributes */
    public function createAssignment(Request $request, array $attributes): ArmisEngagementAssignment
    {
        $actor = $this->actor($request);
        $engagement = $this->engagement($actor, (int) $attributes['audit_engagement_id']);
        $profile = $this->profile($actor, (int) $attributes['resource_profile_id']);
        $this->assertEngagementProfile($engagement, $profile);
        $requirement = $this->requirement($actor, $attributes['requirement_id'] ?? null, $engagement);
        $competencies = $this->competencyPayload($attributes['required_competencies'] ?? null, $requirement);

        $this->assertAssignmentDates($engagement, $attributes['assigned_from'] ?? null, $attributes['assigned_until'] ?? null);
        $this->assertNoDuplicateOrOverlap($engagement, $profile, $attributes['assigned_from'] ?? null, $attributes['assigned_until'] ?? null);

        return DB::transaction(function () use ($request, $actor, $engagement, $profile, $requirement, $attributes, $competencies): ArmisEngagementAssignment {
            $record = ArmisEngagementAssignment::query()->create([
                'assignment_family_uuid' => (string) Str::uuid(),
                'audit_engagement_id' => $engagement->id,
                'resource_profile_id' => $profile->id,
                'requirement_id' => $requirement?->id,
                'version_number' => 1,
                'is_current_revision' => true,
                'assignment_role_code' => $attributes['assignment_role_code'],
                'assigned_from' => $attributes['assigned_from'] ?? $engagement->planned_start_date?->toDateString(),
                'assigned_until' => $attributes['assigned_until'] ?? $engagement->planned_end_date?->toDateString(),
                'planned_person_days' => $attributes['planned_person_days'],
                'status' => 'DRAFT',
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->syncCompetencies($record, $competencies);
            $this->event($record, 'ASSIGNMENT_CREATED', null, 'DRAFT', $actor, null, ['resourceProfileId' => $profile->id]);
            $this->record($request, 'armis.assignment.created', 'ARMIS engagement assignment created.', $record, null, $record->toArray());

            return $record->fresh($this->assignmentRelations());
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function updateAssignment(Request $request, ArmisEngagementAssignment $assignment, array $attributes): ArmisEngagementAssignment
    {
        $actor = $this->actor($request);
        $assignment->loadMissing(['engagement', 'resourceProfile']);
        $this->assertAssignmentScope($actor, $assignment);

        return DB::transaction(function () use ($request, $actor, $assignment, $attributes): ArmisEngagementAssignment {
            $locked = ArmisEngagementAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $this->assertLock($locked, (int) $attributes['lock_version']);
            abort_unless($locked->is_current_revision && in_array($locked->status, ['DRAFT', 'RETURNED'], true), 409, 'Only a current Draft or Returned assignment can be edited.');
            $engagement = $this->engagement($actor, $locked->audit_engagement_id);
            $profile = $this->profile($actor, $locked->resource_profile_id);
            $requirement = $this->requirement($actor, ($attributes['requirement_id'] ?? null) ?: $locked->requirement_id, $engagement);
            $start = $attributes['assigned_from'] ?? $locked->assigned_from?->toDateString();
            $end = $attributes['assigned_until'] ?? $locked->assigned_until?->toDateString();
            $this->assertEngagementProfile($engagement, $profile);
            $this->assertAssignmentDates($engagement, $start, $end);
            $this->assertNoDuplicateOrOverlap($engagement, $profile, $start, $end, $locked->id);
            $before = $locked->toArray();
            $locked->update([
                'requirement_id' => $requirement?->id,
                'assignment_role_code' => $attributes['assignment_role_code'] ?? $locked->assignment_role_code,
                'assigned_from' => $start,
                'assigned_until' => $end,
                'planned_person_days' => $attributes['planned_person_days'] ?? $locked->planned_person_days,
                'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $locked->notes,
                'updated_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ]);
            if (array_key_exists('required_competencies', $attributes)) {
                $locked->competencies()->delete();
                $this->syncCompetencies($locked, $this->competencyPayload($attributes['required_competencies'], $requirement));
            }
            $this->event($locked, 'ASSIGNMENT_UPDATED', $before['status'] ?? null, $locked->status, $actor, null, ['lockVersion' => $locked->lock_version]);
            $this->record($request, 'armis.assignment.updated', 'ARMIS engagement assignment updated.', $locked, $before, $locked->fresh()->toArray());

            return $locked->fresh($this->assignmentRelations());
        }, 3);
    }

    public function submitAssignment(Request $request, ArmisEngagementAssignment $assignment, int $lockVersion): ArmisEngagementAssignment
    {
        $actor = $this->actor($request);
        $assignment->loadMissing(['engagement', 'resourceProfile', 'competencies']);
        $this->assertAssignmentScope($actor, $assignment);

        return DB::transaction(function () use ($request, $actor, $assignment, $lockVersion): ArmisEngagementAssignment {
            $locked = ArmisEngagementAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $this->assertLock($locked, $lockVersion);
            abort_unless($locked->is_current_revision && in_array($locked->status, ['DRAFT', 'RETURNED'], true), 409, 'Only a current Draft or Returned assignment can be submitted.');
            $engagement = $this->engagement($actor, $locked->audit_engagement_id);
            $profile = $this->profile($actor, $locked->resource_profile_id);
            abort_if(in_array($engagement->status, ['CANCELLED', 'CLOSED'], true), 409, 'Assignments cannot be submitted for a cancelled or closed engagement.');
            $this->assertAssignmentReady($locked, $engagement, $profile, true);
            $locked->update([
                'status' => 'SUBMITTED', 'submitted_by' => $actor->id, 'submitted_at' => now(),
                'reviewed_by' => null, 'reviewed_at' => null, 'approved_by' => null, 'approved_at' => null,
                'updated_by' => $actor->id, 'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($locked, 'ASSIGNMENT_SUBMITTED', 'DRAFT', 'SUBMITTED', $actor);
            $this->record($request, 'armis.assignment.submitted', 'ARMIS engagement assignment submitted for review.', $locked, ['status' => 'DRAFT'], ['status' => 'SUBMITTED']);
            $this->notifyReviewers($locked, $actor, 'assignment', 'submitted');

            return $locked->fresh($this->assignmentRelations());
        }, 3);
    }

    public function reviewAssignment(Request $request, ArmisEngagementAssignment $assignment, string $decision, int $lockVersion, ?string $notes = null): ArmisEngagementAssignment
    {
        $actor = $this->actor($request);
        $assignment->loadMissing(['engagement', 'resourceProfile']);
        $this->assertAssignmentScope($actor, $assignment);

        return DB::transaction(function () use ($request, $actor, $assignment, $decision, $lockVersion, $notes): ArmisEngagementAssignment {
            $locked = ArmisEngagementAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $this->assertLock($locked, $lockVersion);
            abort_unless($locked->status === 'SUBMITTED' && $locked->is_current_revision, 409, 'Only a submitted current assignment can be reviewed.');
            $engagement = $this->engagement($actor, $locked->audit_engagement_id);
            $profile = $this->profile($actor, $locked->resource_profile_id);
            $this->assertIndependent($actor, $locked->submitted_by, $profile->user_id);
            if ($decision === 'APPROVE') {
                $this->assertAssignmentReady($locked, $engagement, $profile, true);
                $to = 'APPROVED';
            } elseif ($decision === 'RETURN') {
                abort_if(blank($notes), 422, 'A return explanation is required.');
                $to = 'RETURNED';
            } else {
                throw ValidationException::withMessages(['decision' => ['Assignment review must be APPROVE or RETURN.']]);
            }
            $from = $locked->status;
            $locked->update([
                'status' => $to, 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
                'approved_by' => $decision === 'APPROVE' ? $actor->id : null,
                'approved_at' => $decision === 'APPROVE' ? now() : null,
                'notes' => $notes ?: $locked->notes, 'updated_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($locked, 'ASSIGNMENT_'.($decision === 'APPROVE' ? 'APPROVED' : 'RETURNED'), $from, $to, $actor, $notes);
            $this->record($request, 'armis.assignment.'.strtolower($decision), "ARMIS assignment {$to}.", $locked, ['status' => $from], ['status' => $to, 'notes' => $notes]);
            $this->notifyOwner($locked, $actor, strtolower($decision));

            return $locked->fresh($this->assignmentRelations());
        }, 3);
    }

    public function lockAssignment(Request $request, ArmisEngagementAssignment $assignment, int $lockVersion): ArmisEngagementAssignment
    {
        $actor = $this->actor($request);
        $assignment->loadMissing(['resourceProfile']);
        $this->assertAssignmentScope($actor, $assignment);

        return DB::transaction(function () use ($request, $actor, $assignment, $lockVersion): ArmisEngagementAssignment {
            $locked = ArmisEngagementAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $this->assertLock($locked, $lockVersion);
            abort_unless($locked->status === 'APPROVED' && $locked->is_current_revision, 409, 'Only an approved current assignment can be locked.');
            $locked->update(['status' => 'LOCKED', 'updated_by' => $actor->id, 'lock_version' => $locked->lock_version + 1]);
            $this->event($locked, 'ASSIGNMENT_LOCKED', 'APPROVED', 'LOCKED', $actor);
            $this->record($request, 'armis.assignment.locked', 'ARMIS engagement assignment locked.', $locked, ['status' => 'APPROVED'], ['status' => 'LOCKED']);

            return $locked->fresh($this->assignmentRelations());
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function reviseAssignment(Request $request, ArmisEngagementAssignment $assignment, array $attributes): ArmisEngagementAssignment
    {
        $actor = $this->actor($request);
        $assignment->loadMissing(['engagement', 'resourceProfile', 'requirement', 'competencies']);
        $this->assertAssignmentScope($actor, $assignment);

        return DB::transaction(function () use ($request, $actor, $assignment, $attributes): ArmisEngagementAssignment {
            $current = ArmisEngagementAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $current->load('competencies');
            $this->assertLock($current, (int) $attributes['lock_version']);
            abort_unless($current->is_current_revision && in_array($current->status, ['APPROVED', 'LOCKED'], true), 409, 'Only an approved or locked current assignment can be revised.');
            $engagement = $this->engagement($actor, $current->audit_engagement_id);
            $profile = $this->profile($actor, $current->resource_profile_id);
            $requirement = $this->requirement($actor, ($attributes['requirement_id'] ?? null) ?: $current->requirement_id, $engagement);
            $start = $attributes['assigned_from'] ?? $current->assigned_from?->toDateString();
            $end = $attributes['assigned_until'] ?? $current->assigned_until?->toDateString();
            $this->assertAssignmentDates($engagement, $start, $end);
            $this->assertNoDuplicateOrOverlap($engagement, $profile, $start, $end, $current->id);
            $competencies = array_key_exists('required_competencies', $attributes)
                ? $this->competencyPayload($attributes['required_competencies'], $requirement)
                : $current->competencies->map(fn (ArmisAssignmentCompetency $item): array => [
                    'competency_id' => $item->competency_id,
                    'minimum_proficiency' => $item->minimum_proficiency,
                    'notes' => $item->notes,
                ])->all();
            $current->update(['is_current_revision' => false, 'updated_by' => $actor->id]);
            $revision = ArmisEngagementAssignment::query()->create([
                'assignment_family_uuid' => $current->assignment_family_uuid,
                'audit_engagement_id' => $current->audit_engagement_id,
                'resource_profile_id' => $current->resource_profile_id,
                'requirement_id' => $requirement?->id,
                'version_number' => (int) $current->version_number + 1,
                'supersedes_id' => $current->id,
                'is_current_revision' => true,
                'assignment_role_code' => $attributes['assignment_role_code'] ?? $current->assignment_role_code,
                'assigned_from' => $start,
                'assigned_until' => $end,
                'planned_person_days' => $attributes['planned_person_days'] ?? $current->planned_person_days,
                'status' => 'DRAFT',
                'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $current->notes,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->syncCompetencies($revision, $competencies);
            $this->event($revision, 'ASSIGNMENT_REVISED', $current->status, 'DRAFT', $actor, null, ['supersedesId' => $current->id, 'versionNumber' => $revision->version_number]);
            $this->record($request, 'armis.assignment.revised', 'ARMIS assignment correction created as a new revision.', $revision, ['supersedesId' => $current->id, 'status' => $current->status], $revision->toArray());

            return $revision->fresh($this->assignmentRelations());
        }, 3);
    }

    /** @return list<array<string, mixed>> */
    public function conflicts(User $actor, ArmisEngagementAssignment $assignment): array
    {
        $this->assertAssignmentScope($actor, $assignment);
        $assignment->loadMissing(['engagement', 'resourceProfile', 'competencies']);
        return $this->conflictList($assignment, true, true);
    }

    /** @param array<string, mixed> $attributes */
    public function createActual(Request $request, array $attributes): ArmisActualPersonDay
    {
        $actor = $this->actor($request);
        $assignment = $this->resolveAssignment($actor, (int) $attributes['assignment_id']);
        $this->assertAssignmentUsable($assignment);
        $this->assertActualDates($assignment, $attributes['period_start'], $attributes['period_end']);
        $this->assertActualVariance($assignment, (float) $attributes['actual_person_days'], null, $attributes['variance_reason'] ?? null);

        return DB::transaction(function () use ($request, $actor, $assignment, $attributes): ArmisActualPersonDay {
            $this->assertNoActualDuplicate($assignment, $attributes['period_start'], $attributes['period_end']);
            $record = ArmisActualPersonDay::query()->create([
                'actual_family_uuid' => (string) Str::uuid(),
                'resource_profile_id' => $assignment->resource_profile_id,
                'assignment_id' => $assignment->id,
                'source_module' => 'ARMIS',
                'source_type' => 'AEMS_ASSIGNMENT',
                'source_id' => $assignment->id,
                'period_start' => $attributes['period_start'],
                'period_end' => $attributes['period_end'],
                'version_number' => 1,
                'is_current_revision' => true,
                'actual_person_days' => $attributes['actual_person_days'],
                'status' => 'DRAFT',
                'notes' => $attributes['notes'] ?? null,
                'variance_reason' => $attributes['variance_reason'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->event($record, 'ACTUAL_CREATED', null, 'DRAFT', $actor, null, ['assignmentId' => $assignment->id]);
            $this->record($request, 'armis.actuals.created', 'ARMIS actual person-days created.', $record, null, $record->toArray());

            return $record->fresh($this->actualRelations());
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function updateActual(Request $request, ArmisActualPersonDay $actual, array $attributes): ArmisActualPersonDay
    {
        $actor = $this->actor($request);
        $actual->loadMissing(['assignment', 'resourceProfile']);
        $this->assertActualScope($actor, $actual);

        return DB::transaction(function () use ($request, $actor, $actual, $attributes): ArmisActualPersonDay {
            $locked = ArmisActualPersonDay::query()->lockForUpdate()->findOrFail($actual->id);
            $this->assertLock($locked, (int) $attributes['lock_version']);
            abort_unless($locked->is_current_revision && in_array($locked->status, ['DRAFT', 'RETURNED'], true), 409, 'Only a current Draft or Returned actual record can be edited.');
            $assignment = $this->resolveAssignment($actor, (int) $locked->assignment_id);
            $start = $attributes['period_start'] ?? $locked->period_start?->toDateString();
            $end = $attributes['period_end'] ?? $locked->period_end?->toDateString();
            $days = array_key_exists('actual_person_days', $attributes) ? (float) $attributes['actual_person_days'] : (float) $locked->actual_person_days;
            $variance = array_key_exists('variance_reason', $attributes) ? $attributes['variance_reason'] : $locked->variance_reason;
            $this->assertAssignmentUsable($assignment);
            $this->assertActualDates($assignment, $start, $end);
            $this->assertActualVariance($assignment, $days, $locked->id, $variance);
            $before = $locked->toArray();
            $locked->update([
                'period_start' => $start, 'period_end' => $end, 'actual_person_days' => $days,
                'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $locked->notes,
                'variance_reason' => $variance, 'updated_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($locked, 'ACTUAL_UPDATED', $before['status'] ?? null, $locked->status, $actor, null, ['lockVersion' => $locked->lock_version]);
            $this->record($request, 'armis.actuals.updated', 'ARMIS actual person-days updated.', $locked, $before, $locked->fresh()->toArray());

            return $locked->fresh($this->actualRelations());
        }, 3);
    }

    public function submitActual(Request $request, ArmisActualPersonDay $actual, int $lockVersion): ArmisActualPersonDay
    {
        $actor = $this->actor($request);
        $actual->loadMissing(['assignment']);
        $this->assertActualScope($actor, $actual);

        return DB::transaction(function () use ($request, $actor, $actual, $lockVersion): ArmisActualPersonDay {
            $locked = ArmisActualPersonDay::query()->lockForUpdate()->findOrFail($actual->id);
            $this->assertLock($locked, $lockVersion);
            abort_unless($locked->is_current_revision && in_array($locked->status, ['DRAFT', 'RETURNED'], true), 409, 'Only a current Draft or Returned actual record can be submitted.');
            $assignment = $this->resolveAssignment($actor, (int) $locked->assignment_id);
            $this->assertAssignmentUsable($assignment);
            $this->assertActualDates($assignment, $locked->period_start?->toDateString(), $locked->period_end?->toDateString());
            $this->assertActualVariance($assignment, (float) $locked->actual_person_days, $locked->id, $locked->variance_reason);
            $locked->update([
                'status' => 'SUBMITTED', 'submitted_by' => $actor->id, 'submitted_at' => now(),
                'reviewed_by' => null, 'reviewed_at' => null, 'approved_by' => null, 'approved_at' => null,
                'updated_by' => $actor->id, 'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($locked, 'ACTUAL_SUBMITTED', 'DRAFT', 'SUBMITTED', $actor);
            $this->record($request, 'armis.actuals.submitted', 'ARMIS actual person-days submitted for review.', $locked, ['status' => 'DRAFT'], ['status' => 'SUBMITTED']);
            $this->notifyReviewers($locked, $actor, 'actuals', 'submitted');

            return $locked->fresh($this->actualRelations());
        }, 3);
    }

    public function reviewActual(Request $request, ArmisActualPersonDay $actual, string $decision, int $lockVersion, ?string $notes = null): ArmisActualPersonDay
    {
        $actor = $this->actor($request);
        $actual->loadMissing(['assignment', 'resourceProfile']);
        $this->assertActualScope($actor, $actual);

        return DB::transaction(function () use ($request, $actor, $actual, $decision, $lockVersion, $notes): ArmisActualPersonDay {
            $locked = ArmisActualPersonDay::query()->lockForUpdate()->findOrFail($actual->id);
            $this->assertLock($locked, $lockVersion);
            abort_unless($locked->status === 'SUBMITTED' && $locked->is_current_revision, 409, 'Only a submitted current actual record can be reviewed.');
            $assignment = $this->resolveAssignment($actor, (int) $locked->assignment_id);
            $profile = $assignment->resourceProfile()->firstOrFail();
            $this->assertIndependent($actor, $locked->submitted_by, $profile->user_id);
            $this->assertActualVariance($assignment, (float) $locked->actual_person_days, $locked->id, $locked->variance_reason);
            if ($decision === 'APPROVE') {
                $to = 'APPROVED';
            } elseif ($decision === 'RETURN') {
                abort_if(blank($notes), 422, 'A return explanation is required.');
                $to = 'RETURNED';
            } else {
                throw ValidationException::withMessages(['decision' => ['Actual review must be APPROVE or RETURN.']]);
            }
            $from = $locked->status;
            $locked->update([
                'status' => $to, 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
                'approved_by' => $decision === 'APPROVE' ? $actor->id : null,
                'approved_at' => $decision === 'APPROVE' ? now() : null,
                'notes' => $notes ?: $locked->notes, 'updated_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($locked, 'ACTUAL_'.($decision === 'APPROVE' ? 'APPROVED' : 'RETURNED'), $from, $to, $actor, $notes);
            $this->record($request, 'armis.actuals.'.strtolower($decision), "ARMIS actual person-days {$to}.", $locked, ['status' => $from], ['status' => $to, 'notes' => $notes]);
            $this->notifyOwner($locked, $actor, strtolower($decision));

            return $locked->fresh($this->actualRelations());
        }, 3);
    }

    public function lockActual(Request $request, ArmisActualPersonDay $actual, int $lockVersion): ArmisActualPersonDay
    {
        $actor = $this->actor($request);
        $actual->loadMissing(['assignment']);
        $this->assertActualScope($actor, $actual);

        return DB::transaction(function () use ($request, $actor, $actual, $lockVersion): ArmisActualPersonDay {
            $locked = ArmisActualPersonDay::query()->lockForUpdate()->findOrFail($actual->id);
            $this->assertLock($locked, $lockVersion);
            abort_unless($locked->status === 'APPROVED' && $locked->is_current_revision, 409, 'Only an approved current actual record can be locked.');
            $locked->update(['status' => 'LOCKED', 'updated_by' => $actor->id, 'lock_version' => $locked->lock_version + 1]);
            $this->event($locked, 'ACTUAL_LOCKED', 'APPROVED', 'LOCKED', $actor);
            $this->record($request, 'armis.actuals.locked', 'ARMIS actual person-days locked.', $locked, ['status' => 'APPROVED'], ['status' => 'LOCKED']);

            return $locked->fresh($this->actualRelations());
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function reviseActual(Request $request, ArmisActualPersonDay $actual, array $attributes): ArmisActualPersonDay
    {
        $actor = $this->actor($request);
        $actual->loadMissing(['assignment']);
        $this->assertActualScope($actor, $actual);

        return DB::transaction(function () use ($request, $actor, $actual, $attributes): ArmisActualPersonDay {
            $current = ArmisActualPersonDay::query()->lockForUpdate()->findOrFail($actual->id);
            $this->assertLock($current, (int) $attributes['lock_version']);
            abort_unless($current->is_current_revision && in_array($current->status, ['APPROVED', 'LOCKED'], true), 409, 'Only an approved or locked current actual record can be revised.');
            $assignment = $this->resolveAssignment($actor, (int) $current->assignment_id);
            $start = $attributes['period_start'] ?? $current->period_start?->toDateString();
            $end = $attributes['period_end'] ?? $current->period_end?->toDateString();
            $days = array_key_exists('actual_person_days', $attributes) ? (float) $attributes['actual_person_days'] : (float) $current->actual_person_days;
            $variance = array_key_exists('variance_reason', $attributes) ? $attributes['variance_reason'] : $current->variance_reason;
            $this->assertActualDates($assignment, $start, $end);
            $this->assertActualVariance($assignment, $days, $current->id, $variance);
            $current->update(['is_current_revision' => false, 'updated_by' => $actor->id]);
            $revision = ArmisActualPersonDay::query()->create([
                'actual_family_uuid' => $current->actual_family_uuid,
                'resource_profile_id' => $current->resource_profile_id,
                'assignment_id' => $current->assignment_id,
                'source_module' => $current->source_module,
                'source_type' => $current->source_type,
                'source_id' => $current->source_id,
                'period_start' => $start,
                'period_end' => $end,
                'version_number' => (int) $current->version_number + 1,
                'supersedes_id' => $current->id,
                'is_current_revision' => true,
                'actual_person_days' => $days,
                'status' => 'DRAFT',
                'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $current->notes,
                'variance_reason' => $variance,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->event($revision, 'ACTUAL_REVISED', $current->status, 'DRAFT', $actor, null, ['supersedesId' => $current->id, 'versionNumber' => $revision->version_number]);
            $this->record($request, 'armis.actuals.revised', 'ARMIS actual person-day correction created as a new revision.', $revision, ['supersedesId' => $current->id, 'status' => $current->status], $revision->toArray());

            return $revision->fresh($this->actualRelations());
        }, 3);
    }

    private function assertAssignmentReady(ArmisEngagementAssignment $assignment, AuditEngagement $engagement, ArmisResourceProfile $profile, bool $requireCapacity): void
    {
        abort_if($profile->status !== 'ACTIVE' || ! $profile->user?->is_active, 409, 'The linked ARMIS resource must be active.');
        $this->assertAssignmentDates($engagement, $assignment->assigned_from?->toDateString(), $assignment->assigned_until?->toDateString());
        $this->assertNoDuplicateOrOverlap($engagement, $profile, $assignment->assigned_from?->toDateString(), $assignment->assigned_until?->toDateString(), $assignment->id);
        $conflicts = $this->conflictList($assignment, $requireCapacity, true);
        $errors = collect($conflicts)->where('severity', 'error');
        if ($errors->isNotEmpty()) {
            throw ValidationException::withMessages(['conflicts' => $errors->pluck('message')->values()->all()]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function conflictList(ArmisEngagementAssignment $assignment, bool $requireCapacity, bool $requireCompetencies): array
    {
        $assignment->loadMissing(['engagement', 'resourceProfile', 'competencies']);
        $engagement = $assignment->engagement;
        $profile = $assignment->resourceProfile;
        $start = $assignment->assigned_from ?? $engagement?->planned_start_date;
        $end = $assignment->assigned_until ?? $engagement?->planned_end_date;
        $conflicts = [];

        if (! $profile || $profile->status !== 'ACTIVE' || ! $profile->user?->is_active) {
            $conflicts[] = ['type' => 'RESOURCE_INACTIVE', 'severity' => 'error', 'message' => 'The ARMIS resource profile and Core user must both be active.'];
        }
        if ($engagement && $profile && ! $engagement->offices()->whereKey($profile->office_id)->exists()) {
            $conflicts[] = ['type' => 'OFFICE_MISMATCH', 'severity' => 'error', 'message' => 'The resource office is not covered by the engagement offices.'];
        }
        if ($start && $end) {
            $unavailable = ArmisAvailabilityPeriod::query()
                ->where('resource_profile_id', $profile?->id)
                ->where('is_current_revision', true)
                ->whereIn('status', ['APPROVED', 'LOCKED'])
                ->whereIn('availability_type', ['UNAVAILABLE', 'LEAVE', 'TRAINING', 'OTHER'])
                ->whereDate('start_date', '<=', $end->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString())
                ->get();
            foreach ($unavailable as $period) {
                $conflicts[] = [
                    'type' => 'AVAILABILITY_CONFLICT', 'severity' => 'error',
                    'message' => sprintf('The resource has %s availability from %s to %s.', $period->availability_type, $period->start_date->toDateString(), $period->end_date->toDateString()),
                ];
            }

            $overlaps = ArmisEngagementAssignment::query()
                ->where('resource_profile_id', $profile?->id)
                ->where('is_current_revision', true)
                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'LOCKED'])
                ->whereKeyNot($assignment->id)
                ->whereNull('deleted_at')
                ->whereHas('engagement', fn ($query) => $query->activeForResourceConflicts())
                ->with('engagement:id,engagement_code,title,status')
                ->get()
                ->filter(function (ArmisEngagementAssignment $other) use ($start, $end): bool {
                    if (in_array($other->engagement?->status, ['CANCELLED', 'CLOSED'], true)) return false;
                    $otherStart = $other->assigned_from ?? $other->engagement?->planned_start_date;
                    $otherEnd = $other->assigned_until ?? $other->engagement?->planned_end_date;
                    return $otherStart && $otherEnd && $otherStart->lte($end) && $otherEnd->gte($start);
                });
            foreach ($overlaps as $other) {
                $conflicts[] = [
                    'type' => 'ENGAGEMENT_OVERLAP', 'severity' => 'error',
                    'message' => sprintf('The resource overlaps %s (%s).', $other->engagement?->engagement_code ?? 'another engagement', $other->engagement?->title ?? 'existing assignment'),
                ];
            }
        }

        if ($requireCapacity && $profile && $start) {
            $year = $start->year;
            $capacity = ArmisCapacitySubmission::query()
                ->where('resource_profile_id', $profile->id)
                ->where('fiscal_year', $year)
                ->where('is_current_revision', true)
                ->whereIn('status', ['APPROVED', 'LOCKED'])
                ->latest('version_number')
                ->first();
            if (! $capacity) {
                $conflicts[] = ['type' => 'CAPACITY_MISSING', 'severity' => 'error', 'message' => "No approved ARMIS capacity exists for fiscal year {$year}."];
            } else {
                $allocated = (float) ArmisEngagementAssignment::query()
                    ->where('resource_profile_id', $profile->id)
                    ->where('is_current_revision', true)
                    ->whereIn('status', ['APPROVED', 'LOCKED'])
                    ->whereYear('assigned_from', $year)
                    ->whereKeyNot($assignment->id)
                    ->whereHas('engagement', fn ($query) => $query->activeForResourceConflicts())
                    ->sum('planned_person_days');
                $total = round($allocated + (float) $assignment->planned_person_days, 2);
                if ($total > (float) $capacity->available_person_days) {
                    $conflicts[] = ['type' => 'CAPACITY_EXCEEDED', 'severity' => 'error', 'message' => sprintf('Planned assignment would use %.2f person-days against %.2f approved capacity.', $total, (float) $capacity->available_person_days)];
                }
            }
        }

        if ($requireCapacity && $engagement && (float) $engagement->planned_person_days > 0) {
            $engagementAssigned = (float) ArmisEngagementAssignment::query()
                ->where('audit_engagement_id', $engagement->id)
                ->where('is_current_revision', true)
                ->whereIn('status', ['APPROVED', 'LOCKED'])
                ->whereKeyNot($assignment->id)
                ->sum('planned_person_days');
            if (round($engagementAssigned + (float) $assignment->planned_person_days, 2) > (float) $engagement->planned_person_days) {
                $conflicts[] = ['type' => 'ENGAGEMENT_DAYS_EXCEEDED', 'severity' => 'error', 'message' => 'Planned assignment exceeds the engagement planned person-days.'];
            }
        }

        if ($requireCompetencies && $assignment->competencies->isNotEmpty()) {
            foreach ($assignment->competencies as $required) {
                $claim = ArmisCompetency::query()
                    ->where('resource_profile_id', $profile?->id)
                    ->where('competency_id', $required->competency_id)
                    ->where('is_current_revision', true)
                    ->where('status', 'VERIFIED')
                    ->where(function (Builder $query): void {
                        $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()->toDateString());
                    })
                    ->first();
                if (! $claim || $this->proficiencyRank($claim->proficiency_level) < $this->proficiencyRank($required->minimum_proficiency)) {
                    $label = $required->competency?->label ?? $required->competency?->code ?? "competency #{$required->competency_id}";
                    $conflicts[] = ['type' => 'COMPETENCY_GAP', 'severity' => 'error', 'message' => "The resource does not have a current verified {$required->minimum_proficiency} {$label} competency."];
                }
            }
        }

        return $conflicts;
    }

    private function assertNoDuplicateOrOverlap(AuditEngagement $engagement, ArmisResourceProfile $profile, ?string $start, ?string $end, ?int $ignoreId = null): void
    {
        $existing = ArmisEngagementAssignment::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('resource_profile_id', $profile->id)
            ->where('is_current_revision', true)
            ->whereNull('deleted_at')
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
        abort_if($existing, 422, 'This ARMIS resource already has a current assignment for the engagement.');
        if (! $start || ! $end) return;
        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);
        $overlap = ArmisEngagementAssignment::query()
            ->where('resource_profile_id', $profile->id)
            ->where('is_current_revision', true)
            ->whereIn('status', ['SUBMITTED', 'APPROVED', 'LOCKED'])
            ->whereNull('deleted_at')
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->whereHas('engagement', fn ($query) => $query->activeForResourceConflicts())
            ->with('engagement:id,engagement_code,title,status,planned_start_date,planned_end_date')
            ->get()
            ->contains(function (ArmisEngagementAssignment $other) use ($startDate, $endDate): bool {
                $otherStart = $other->assigned_from ?? $other->engagement?->planned_start_date;
                $otherEnd = $other->assigned_until ?? $other->engagement?->planned_end_date;
                return $otherStart && $otherEnd && $otherStart->lte($endDate) && $otherEnd->gte($startDate);
            });
        abort_if($overlap, 422, 'The resource overlaps another current ARMIS engagement assignment.');
    }

    private function assertAssignmentDates(AuditEngagement $engagement, ?string $start, ?string $end): void
    {
        if (($start && ! $end) || (! $start && $end)) {
            throw ValidationException::withMessages(['assignedUntil' => ['Assignment start and end dates must be supplied together.']]);
        }
        if (! $start || ! $end) return;
        $from = Carbon::parse($start);
        $to = Carbon::parse($end);
        abort_if($from->gt($to), 422, 'The assignment end date must be on or after the start date.');
        if ($engagement->planned_start_date && $from->lt($engagement->planned_start_date)) {
            throw ValidationException::withMessages(['assignedFrom' => ['The assignment cannot start before the engagement.']]);
        }
        if ($engagement->planned_end_date && $to->gt($engagement->planned_end_date)) {
            throw ValidationException::withMessages(['assignedUntil' => ['The assignment cannot end after the engagement.']]);
        }
    }

    private function assertActualDates(ArmisEngagementAssignment $assignment, string $start, string $end): void
    {
        $from = Carbon::parse($start);
        $to = Carbon::parse($end);
        abort_if($from->gt($to), 422, 'The actual period end date must be on or after the start date.');
        $assignmentStart = $assignment->assigned_from ?? $assignment->engagement?->planned_start_date;
        $assignmentEnd = $assignment->assigned_until ?? $assignment->engagement?->planned_end_date;
        if ($assignmentStart && $from->lt($assignmentStart)) {
            throw ValidationException::withMessages(['periodStart' => ['Actual person-days cannot precede the assignment.']]);
        }
        if ($assignmentEnd && $to->gt($assignmentEnd)) {
            throw ValidationException::withMessages(['periodEnd' => ['Actual person-days cannot extend beyond the assignment.']]);
        }
    }

    private function assertActualVariance(ArmisEngagementAssignment $assignment, float $days, ?int $ignoreId, ?string $varianceReason): void
    {
        $approved = (float) ArmisActualPersonDay::query()
            ->where('assignment_id', $assignment->id)
            ->where('is_current_revision', true)
            ->whereIn('status', ['DRAFT', 'SUBMITTED', 'RETURNED', 'APPROVED', 'LOCKED'])
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->sum('actual_person_days');
        if (round($approved + $days, 2) > (float) $assignment->planned_person_days && blank($varianceReason)) {
            throw ValidationException::withMessages(['varianceReason' => ['A variance reason is required when actual person-days exceed the approved assignment plan.']]);
        }
    }

    private function assertNoActualDuplicate(ArmisEngagementAssignment $assignment, string $start, string $end, ?int $ignoreId = null): void
    {
        $exists = ArmisActualPersonDay::query()
            ->where('assignment_id', $assignment->id)
            ->where('period_start', $start)
            ->where('period_end', $end)
            ->where('is_current_revision', true)
            ->whereNull('deleted_at')
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
        abort_if($exists, 422, 'A current actual person-day record already exists for this assignment period.');
    }

    private function assertAssignmentUsable(ArmisEngagementAssignment $assignment): void
    {
        abort_unless($assignment->is_current_revision && in_array($assignment->status, ['APPROVED', 'LOCKED'], true), 409, 'Actual person-days require an approved or locked current assignment.');
        abort_if(in_array($assignment->engagement?->status, ['CANCELLED', 'CLOSED'], true), 409, 'Actual person-days cannot be recorded for a cancelled or closed engagement.');
        abort_if($assignment->resourceProfile?->status !== 'ACTIVE', 409, 'Actual person-days require an active ARMIS resource profile.');
    }

    private function syncCompetencies(ArmisEngagementAssignment $assignment, array $competencies): void
    {
        foreach ($competencies as $competency) {
            ArmisAssignmentCompetency::query()->create([
                'assignment_id' => $assignment->id,
                'competency_id' => $competency['competency_id'],
                'minimum_proficiency' => $competency['minimum_proficiency'],
                'notes' => $competency['notes'] ?? null,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function competencyPayload(?array $items, ?ArmisResourceRequirement $requirement): array
    {
        if ($items === null && $requirement) {
            return $requirement->competencies()->get()->map(fn ($item): array => [
                'competency_id' => $item->competency_id,
                'minimum_proficiency' => $item->minimum_proficiency,
                'notes' => $item->notes,
            ])->all();
        }
        if ($items === null) return [];
        $seen = [];
        return collect($items)->map(function (array $item) use (&$seen): array {
            $id = (int) ($item['competency_id'] ?? 0);
            abort_if($id <= 0 || in_array($id, $seen, true), 422, 'Each required competency must be unique and valid.');
            $seen[] = $id;
            abort_unless(\App\Models\MasterListItem::query()->whereKey($id)->whereHas('masterList', fn (Builder $query): Builder => $query->where('code', 'IAP_AUDITOR_SPECIALIZATION'))->exists(), 422, 'The selected required competency was not found in the Core competency catalogue.');
            $level = strtoupper((string) ($item['minimum_proficiency'] ?? 'INTERMEDIATE'));
            abort_unless(in_array($level, self::PROFICIENCY_LEVELS, true), 422, 'The required competency proficiency is invalid.');
            return ['competency_id' => $id, 'minimum_proficiency' => $level, 'notes' => $item['notes'] ?? null];
        })->values()->all();
    }

    private function requirement(User $actor, mixed $id, AuditEngagement $engagement): ?ArmisResourceRequirement
    {
        if ($id === null || $id === '') return null;
        $requirement = ArmisResourceRequirement::query()->with('competencies')->find((int) $id);
        abort_unless($requirement, 422, 'The ARMIS resource requirement was not found.');
        $this->assertOffice($actor, $requirement->office_id);
        abort_unless((string) $requirement->source_module === 'AEMS' && (int) $requirement->source_id === (int) $engagement->id, 422, 'The ARMIS requirement does not belong to this engagement.');

        return $requirement;
    }

    private function engagement(User $actor, int $id): AuditEngagement
    {
        $engagement = $this->visibleEngagements($actor)->find($id);
        abort_unless($engagement, 404, 'The AEMS engagement was not found in your ARMIS scope.');
        return $engagement;
    }

    private function profile(User $actor, int $id): ArmisResourceProfile
    {
        $profile = ArmisResourceProfile::query()->with(['user', 'office'])->find($id);
        abort_unless($profile, 404, 'The ARMIS resource profile was not found.');
        $this->assertOffice($actor, $profile->office_id);
        abort_if($profile->trashed() || $profile->status === 'ARCHIVED', 409, 'Archived ARMIS resource profiles cannot be assigned.');
        return $profile;
    }

    private function assertEngagementProfile(AuditEngagement $engagement, ArmisResourceProfile $profile): void
    {
        abort_unless($engagement->offices()->whereKey($profile->office_id)->exists(), 422, 'The resource office must be covered by the engagement.');
    }

    private function assertAssignmentScope(User $actor, ArmisEngagementAssignment $assignment): void
    {
        abort_unless($this->scopeAssignments(ArmisEngagementAssignment::query(), $actor)->whereKey($assignment->id)->exists(), 404, 'The ARMIS assignment was not found in your scope.');
    }

    private function assertActualScope(User $actor, ArmisActualPersonDay $actual): void
    {
        abort_unless($this->actualQuery($actor)->whereKey($actual->id)->exists(), 404, 'The ARMIS actual person-day record was not found in your scope.');
    }

    private function scopeAssignments(Builder $query, User $actor): Builder
    {
        return $query
            ->whereHas('resourceProfile', fn (Builder $profile): Builder => $profile->when(! $actor->hasGlobalOfficeAccess(), fn (Builder $scoped) => $scoped->where('office_id', $actor->office_id)))
            ->whereHas('engagement', fn (Builder $engagement): Builder => $this->scopeEngagements($engagement, $actor));
    }

    private function visibleEngagements(User $actor): Builder
    {
        return $this->scopeEngagements(AuditEngagement::query(), $actor);
    }

    private function scopeEngagements(Builder $query, User $actor): Builder
    {
        if ($actor->hasGlobalEngagementAccess()) return $query;
        if (! $actor->hasPermission('aems.engagement.view')) return $query->whereRaw('1 = 0');
        return $query->whereHas('teamMembers', fn (Builder $team): Builder => $team->where('user_id', $actor->id)->where('is_active', true)->whereNull('ended_at'));
    }

    private function assertOffice(User $actor, ?int $officeId): void
    {
        abort_unless($officeId !== null && ($actor->hasGlobalOfficeAccess() || (int) $actor->office_id === $officeId), 403, 'This ARMIS record is outside your office scope.');
    }

    private function assertIndependent(User $actor, ?int $submittedBy, ?int $resourceOwner): void
    {
        if ((int) $actor->id === (int) $submittedBy || (int) $actor->id === (int) $resourceOwner) {
            throw ValidationException::withMessages(['review' => ['The submitter and resource owner cannot independently approve ARMIS records.']]);
        }
    }

    private function assertLock(Model $record, int $expected): void
    {
        if ((int) $record->lock_version !== $expected) {
            throw ValidationException::withMessages(['lockVersion' => ['This ARMIS record changed in another session. Refresh before continuing.']]);
        }
    }

    private function proficiencyRank(?string $level): int
    {
        return array_search(strtoupper((string) $level), self::PROFICIENCY_LEVELS, true) + 1;
    }

    /** @return list<string> */
    private function assignmentRelations(): array
    {
        return ['engagement:id,engagement_code,title,status,planned_start_date,planned_end_date,planned_person_days', 'resourceProfile.user:id,employee_id,name,initials', 'resourceProfile.office:id,code,name', 'requirement:id,title,status', 'competencies.competency:id,code,label', 'submitter:id,employee_id,name,initials', 'reviewer:id,employee_id,name,initials', 'approver:id,employee_id,name,initials', 'supersedes:id,version_number,status'];
    }

    /** @return list<string> */
    private function actualRelations(): array
    {
        return ['assignment.engagement:id,engagement_code,title,status', 'assignment.resourceProfile.user:id,employee_id,name,initials', 'assignment.resourceProfile.office:id,code,name', 'submitter:id,employee_id,name,initials', 'reviewer:id,employee_id,name,initials', 'approver:id,employee_id,name,initials', 'supersedes:id,version_number,status'];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        return $actor;
    }

    private function notifyReviewers(Model $record, User $actor, string $kind, string $action): void
    {
        $permission = $kind === 'assignment' ? 'armis.assignment.approve' : 'armis.actuals.approve';
        $ids = User::query()->where('is_active', true)->where(function (Builder $query) use ($permission): void {
            $query->whereHas('roles.permissions', fn (Builder $permissions) => $permissions->where('code', $permission))
                ->orWhereHas('role.permissions', fn (Builder $permissions) => $permissions->where('code', $permission));
        })->pluck('id')->reject(fn (int $id): bool => $id === (int) $actor->id)->values();
        $profile = $record instanceof ArmisActualPersonDay ? $record->assignment?->resourceProfile : $record->resourceProfile;
        DB::afterCommit(fn () => $this->notifications->send($ids, [
            'actorId' => $actor->id, 'type' => 'ARMIS_ASSIGNMENT', 'category' => 'SYSTEM', 'priority' => 'HIGH', 'moduleCode' => 'ARMIS',
            'title' => "ARMIS {$kind} awaiting review", 'message' => "{$profile?->resource_code} has ARMIS {$kind} data awaiting independent review.",
            'actionUrl' => '/audit-resource-management/assignments', 'actionLabel' => 'Review ARMIS assignment', 'subjectType' => $record::class, 'subjectId' => $record->id,
            'subjectCode' => $profile?->resource_code, 'dedupeKey' => "armis-{$kind}:{$record->id}:{$record->lock_version}:{$action}",
        ]));
    }

    private function notifyOwner(Model $record, User $actor, string $action): void
    {
        $profile = $record instanceof ArmisActualPersonDay ? $record->assignment?->resourceProfile : $record->resourceProfile;
        if (! $profile?->user_id || (int) $profile->user_id === (int) $actor->id) return;
        DB::afterCommit(fn () => $this->notifications->send([$profile->user_id], [
            'actorId' => $actor->id, 'type' => 'ARMIS_ASSIGNMENT', 'category' => 'SYSTEM', 'priority' => 'NORMAL', 'moduleCode' => 'ARMIS',
            'title' => 'ARMIS review updated', 'message' => "Your ARMIS record was {$action} by an independent reviewer.",
            'actionUrl' => '/audit-resource-management/assignments', 'actionLabel' => 'View ARMIS assignment', 'subjectType' => $record::class, 'subjectId' => $record->id,
            'subjectCode' => $profile->resource_code, 'dedupeKey' => "armis-assignment:{$record->id}:{$record->lock_version}:owner:{$action}",
        ]));
    }

    /** @param array<string, mixed>|null $oldValues @param array<string, mixed>|null $newValues */
    private function record(Request $request, string $action, string $description, Model $record, ?array $oldValues, ?array $newValues): void
    {
        $actor = $request->user();
        $metadata = ['module' => 'ARMIS', 'recordType' => $record::class, 'recordId' => $record->id];
        ActivityLog::query()->create([
            'user_id' => $actor?->id, 'action' => $action, 'description' => $description,
            'old_values' => $oldValues, 'new_values' => $newValues, 'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000), 'metadata' => $metadata,
        ]);
        AuditLog::query()->create([
            'user_id' => $actor?->id, 'action' => $action, 'auditable_type' => $record::class,
            'auditable_id' => $record->id, 'old_values' => $oldValues, 'new_values' => $newValues,
            'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000), 'metadata' => $metadata,
        ]);
    }

    /** @param array<string, mixed>|null $metadata */
    private function event(Model $record, string $code, ?string $from, ?string $to, User $actor, ?string $reason = null, ?array $metadata = null): void
    {
        ArmisWorkflowEvent::query()->create([
            'subject_type' => $record::class, 'subject_id' => $record->id, 'event_code' => $code,
            'from_status' => $from, 'to_status' => $to, 'actor_id' => $actor->id, 'reason' => $reason, 'metadata' => $metadata,
        ]);
    }
}
