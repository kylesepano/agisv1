<?php

namespace App\Services;

use App\Models\StrategicInternalAuditPlan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Enforces SIAP completeness and approved-version immutability constraints.
 */
class SiapPlanGuard
{
    /** @param Builder<StrategicInternalAuditPlan> $query */
    public function scopeVisible(Builder $query, User $user): Builder
    {
        if ($user->hasGlobalEngagementAccess()) {
            if (! $user->isReadOnlyOnly()) {
                return $query;
            }

            return $query->whereIn('status', ['APPROVED', 'ACTIVE', 'COMPLETED']);
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query
                ->where('prepared_by', $user->id)
                ->orWhere('coordinator_id', $user->id);
        });
    }

    public function assertCanView(User $user, StrategicInternalAuditPlan $plan): void
    {
        if (! $this->scopeVisible(
            StrategicInternalAuditPlan::query()->whereKey($plan->getKey()),
            $user,
        )->exists()) {
            throw new AuthorizationException;
        }
    }

    public function assertEditable(User $user, StrategicInternalAuditPlan $plan): void
    {
        $this->assertCanView($user, $plan);
        if (! $user->hasPermission('iap.update')) {
            throw new AuthorizationException;
        }

        if (! in_array($plan->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft or returned strategic plans may be changed.'],
            ]);
        }
    }

    public function assertManagement(User $user): void
    {
        if (! $user->hasPermission('iap.manage_universe')) {
            throw new AuthorizationException;
        }
    }

    public function assertLockVersion(StrategicInternalAuditPlan $plan, int $lockVersion): void
    {
        if ($plan->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => [
                    'This strategic plan was changed by another user. Refresh it before continuing.',
                ],
            ]);
        }
    }
}
