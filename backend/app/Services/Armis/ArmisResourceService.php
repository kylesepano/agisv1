<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ArmisResourceProfile;
use App\Models\ArmisWorkflowEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Owns ARMIS resource registry scope, lifecycle, optimistic locking, and audit records. */
class ArmisResourceService
{
    /** @return Builder<ArmisResourceProfile> */
    public function scopeVisible(Builder $query, User $actor): Builder
    {
        if ($actor->hasGlobalOfficeAccess()) {
            return $query;
        }

        return $query->where('office_id', $actor->office_id);
    }

    public function resolveVisible(User $actor, int $id, bool $withTrashed = false): ArmisResourceProfile
    {
        $query = $withTrashed
            ? ArmisResourceProfile::withTrashed()
            : ArmisResourceProfile::query();

        $profile = $this->scopeVisible($query, $actor)->find($id);
        abort_unless($profile, 404, 'The ARMIS resource profile was not found in your scope.');

        return $profile;
    }

    /** @param array<string, mixed> $attributes */
    public function create(Request $request, array $attributes): ArmisResourceProfile
    {
        $actor = $this->actor($request);
        $this->assertOfficeScope($actor, (int) $attributes['office_id']);
        $this->assertIdentityOffice((int) $attributes['user_id'], (int) $attributes['office_id']);

        return DB::transaction(function () use ($request, $actor, $attributes): ArmisResourceProfile {
            $profile = ArmisResourceProfile::query()->create([
                'resource_code' => $attributes['resource_code']
                    ?? 'ARMIS-RES-'.strtoupper(Str::random(10)),
                'user_id' => $attributes['user_id'],
                'office_id' => $attributes['office_id'],
                'category' => $attributes['category'] ?? 'AUDIT_RESOURCE',
                'status' => 'DRAFT',
                'effective_from' => $attributes['effective_from'] ?? null,
                'effective_to' => $attributes['effective_to'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->event($profile, 'RESOURCE_CREATED', null, 'DRAFT', $actor, null, [
                'resourceCode' => $profile->resource_code,
            ]);
            $this->record($request, 'armis.resource.created', 'ARMIS resource profile created.', $profile, null, $profile->toArray());

            return $profile->fresh(['user', 'office']);
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Request $request, ArmisResourceProfile $profile, array $attributes): ArmisResourceProfile
    {
        $actor = $this->actor($request);
        $this->assertOfficeScope($actor, $profile->office_id);

        return DB::transaction(function () use ($request, $actor, $profile, $attributes): ArmisResourceProfile {
            $locked = ArmisResourceProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $this->assertLock($locked, (int) $attributes['lock_version']);
            abort_if($locked->status === 'ARCHIVED' || $locked->trashed(), 409, 'Archived ARMIS resources cannot be edited.');

            $officeId = (int) ($attributes['office_id'] ?? $locked->office_id);
            $userId = (int) ($attributes['user_id'] ?? $locked->user_id);
            $this->assertOfficeScope($actor, $officeId);
            $this->assertIdentityOffice($userId, $officeId);
            $before = $locked->toArray();
            $locked->fill([
                'resource_code' => $attributes['resource_code'] ?? $locked->resource_code,
                'user_id' => $userId,
                'office_id' => $officeId,
                'category' => $attributes['category'] ?? $locked->category,
                'effective_from' => $attributes['effective_from'] ?? $locked->effective_from,
                'effective_to' => $attributes['effective_to'] ?? $locked->effective_to,
                'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $locked->notes,
                'updated_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $locked->save();
            $this->event($locked, 'RESOURCE_UPDATED', $before['status'] ?? null, $locked->status, $actor, null, [
                'lockVersion' => $locked->lock_version,
            ]);
            $this->record($request, 'armis.resource.updated', 'ARMIS resource profile updated.', $locked, $before, $locked->fresh()->toArray());

            return $locked->fresh(['user', 'office']);
        }, 3);
    }

    public function transition(Request $request, ArmisResourceProfile $profile, string $toStatus, int $lockVersion, ?string $reason = null): ArmisResourceProfile
    {
        $actor = $this->actor($request);
        $this->assertOfficeScope($actor, $profile->office_id);

        return DB::transaction(function () use ($request, $actor, $profile, $toStatus, $lockVersion, $reason): ArmisResourceProfile {
            $locked = ArmisResourceProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $this->assertLock($locked, $lockVersion);
            $fromStatus = $locked->status;
            $this->assertTransition($fromStatus, $toStatus);
            if ($toStatus === 'ACTIVE' && ! $locked->user?->is_active) {
                throw ValidationException::withMessages(['status' => ['Only an active Core user can have an active ARMIS profile.']]);
            }
            if ($toStatus === 'ARCHIVED' && blank($reason)) {
                throw ValidationException::withMessages(['reason' => ['A reason is required when archiving a resource profile.']]);
            }

            $locked->update([
                'status' => $toStatus,
                'updated_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ]);
            if ($toStatus === 'ARCHIVED') {
                $locked->delete();
            }
            $this->event($locked, 'RESOURCE_STATUS_CHANGED', $fromStatus, $toStatus, $actor, $reason);
            $this->record($request, 'armis.resource.status_changed', "ARMIS resource changed from {$fromStatus} to {$toStatus}.", $locked, ['status' => $fromStatus], ['status' => $toStatus, 'reason' => $reason]);

            return $locked->fresh(['user', 'office']);
        }, 3);
    }

    public function restore(Request $request, ArmisResourceProfile $profile, int $lockVersion): ArmisResourceProfile
    {
        $actor = $this->actor($request);
        $this->assertOfficeScope($actor, $profile->office_id);

        return DB::transaction(function () use ($request, $actor, $profile, $lockVersion): ArmisResourceProfile {
            $locked = ArmisResourceProfile::withTrashed()->lockForUpdate()->findOrFail($profile->id);
            $this->assertLock($locked, $lockVersion);
            abort_unless($locked->status === 'ARCHIVED' && $locked->trashed(), 409, 'Only archived ARMIS resources can be restored.');
            $locked->restore();
            $locked->update(['status' => 'INACTIVE', 'updated_by' => $actor->id, 'lock_version' => $locked->lock_version + 1]);
            $this->event($locked, 'RESOURCE_RESTORED', 'ARCHIVED', 'INACTIVE', $actor);
            $this->record($request, 'armis.resource.restored', 'ARMIS resource profile restored.', $locked, ['status' => 'ARCHIVED'], ['status' => 'INACTIVE']);

            return $locked->fresh(['user', 'office']);
        }, 3);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function assertOfficeScope(User $actor, int $officeId): void
    {
        abort_unless($actor->hasGlobalOfficeAccess() || (int) $actor->office_id === $officeId, 403, 'This ARMIS resource is outside your office scope.');
    }

    private function assertIdentityOffice(int $userId, int $officeId): void
    {
        $user = User::withTrashed()->find($userId);
        abort_unless($user && (int) $user->office_id === $officeId, 422, 'The resource identity must belong to the selected office.');
    }

    private function assertLock(ArmisResourceProfile $profile, int $expected): void
    {
        if ((int) $profile->lock_version !== $expected) {
            throw ValidationException::withMessages(['lockVersion' => ['The ARMIS resource was changed by another user. Refresh and try again.']]);
        }
    }

    private function assertTransition(string $from, string $to): void
    {
        $allowed = [
            'DRAFT' => ['ACTIVE', 'INACTIVE'],
            'ACTIVE' => ['SUSPENDED', 'INACTIVE'],
            'SUSPENDED' => ['ACTIVE', 'INACTIVE'],
            'INACTIVE' => ['ACTIVE', 'ARCHIVED'],
            'ARCHIVED' => [],
        ];
        abort_unless(in_array($to, $allowed[$from] ?? [], true), 409, "ARMIS resource cannot transition from {$from} to {$to}.");
    }

    /** @param array<string, mixed>|null $oldValues @param array<string, mixed>|null $newValues */
    private function record(Request $request, string $action, string $description, ArmisResourceProfile $profile, ?array $oldValues, ?array $newValues): void
    {
        $actor = $request->user();
        $metadata = ['module' => 'ARMIS', 'resourceProfileId' => $profile->id, 'resourceCode' => $profile->resource_code];
        $context = [
            'user_id' => $actor?->id,
            'action' => $action,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ];
        ActivityLog::query()->create($context);
        AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => ArmisResourceProfile::class,
            'auditable_id' => $profile->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ]);
    }

    /** @param array<string, mixed>|null $metadata */
    private function event(ArmisResourceProfile $profile, string $code, ?string $from, ?string $to, User $actor, ?string $reason = null, ?array $metadata = null): void
    {
        ArmisWorkflowEvent::query()->create([
            'subject_type' => ArmisResourceProfile::class,
            'subject_id' => $profile->id,
            'event_code' => $code,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor->id,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
