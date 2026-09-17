<?php

namespace App\Policies;

use App\Models\AuditEngagement;
use App\Models\User;
use App\Services\AemsAccessService;
use Throwable;

/**
 * Enforces both the permission code and the user's current engagement assignment.
 */
class AuditEngagementPolicy
{
    public function __construct(private readonly AemsAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('aems.engagement.view');
    }

    public function view(User $user, AuditEngagement $engagement): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementView($user, $engagement),
        );
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('aems.engagement.create');
    }

    public function update(User $user, AuditEngagement $engagement): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $engagement,
                'aems.engagement.update',
            ),
        );
    }

    public function transition(User $user, AuditEngagement $engagement): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $engagement,
                'aems.engagement.transition',
            ),
        );
    }

    public function authorize(User $user, AuditEngagement $engagement): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $engagement,
                'aems.engagement.authorize',
                $engagement->created_by,
            ),
        );
    }

    public function assignTeam(User $user, AuditEngagement $engagement): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $engagement,
                'aems.team.assign',
            ),
        );
    }

    public function suspend(User $user, AuditEngagement $engagement): bool
    {
        return $this->allowsAction($user, $engagement, 'aems.engagement.suspend');
    }

    public function cancel(User $user, AuditEngagement $engagement): bool
    {
        return $this->allowsAction($user, $engagement, 'aems.engagement.cancel');
    }

    public function archive(User $user, AuditEngagement $engagement): bool
    {
        return $this->allowsAction($user, $engagement, 'aems.engagement.archive');
    }

    public function restore(User $user, AuditEngagement $engagement): bool
    {
        return $this->allowsAction($user, $engagement, 'aems.engagement.restore');
    }

    public function close(User $user, AuditEngagement $engagement): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $engagement,
                'aems.engagement.close',
                $engagement->created_by,
            ),
        );
    }

    private function allowsAction(
        User $user,
        AuditEngagement $engagement,
        string $permission,
    ): bool {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction($user, $engagement, $permission),
        );
    }

    private function allows(callable $authorization): bool
    {
        try {
            $authorization();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
