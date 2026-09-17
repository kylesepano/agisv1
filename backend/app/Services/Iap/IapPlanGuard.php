<?php

namespace App\Services;

use App\Models\InternalAuditPlan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Enforces Annual Plan completeness, immutability, and transition prerequisites.
 */
class IapPlanGuard
{
    /** @param Builder<InternalAuditPlan> $query */
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
                ->orWhere('coordinator_id', $user->id)
                ->orWhereHas(
                    'engagements.teamMembers',
                    fn (Builder $team) => $team->where('user_id', $user->id),
                );
        });
    }

    public function assertCanView(User $user, InternalAuditPlan $plan): void
    {
        $visible = $this->scopeVisible(
            InternalAuditPlan::query()->whereKey($plan->getKey()),
            $user,
        )->exists();

        if (! $visible) {
            throw new AuthorizationException;
        }
    }

    public function assertEditable(User $user, InternalAuditPlan $plan): void
    {
        $this->assertCanView($user, $plan);

        $mayEdit = $user->hasPermission('iap.update');

        if (! $mayEdit) {
            throw new AuthorizationException;
        }

        if (! in_array($plan->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft or returned plans may be changed.'],
            ]);
        }
    }

    public function assertManagement(User $user): void
    {
        if (! $user->hasPermission('iap.manage_universe')) {
            throw new AuthorizationException;
        }
    }

    public function assertLockVersion(InternalAuditPlan $plan, int $lockVersion): void
    {
        if ($plan->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['This plan was changed by another user. Refresh it before continuing.'],
            ]);
        }
    }
}
