<?php

namespace App\Policies;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\User;
use App\Services\AemsAccessService;
use Throwable;

/**
 * Protects findings until they are formally communicated to the responsible office.
 */
class AuditFindingPolicy
{
    public function __construct(private readonly AemsAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('aems.finding.view');
    }

    public function view(User $user, AuditFinding $finding): bool
    {
        return $this->allows(fn () => $this->access->authorizeFindingView($user, $finding));
    }

    public function create(User $user, AuditEngagement $engagement): bool
    {
        return $this->allowsAction($user, $engagement, 'aems.finding.create');
    }

    public function prepare(User $user, AuditFinding $finding): bool
    {
        return $this->allowsAction(
            $user,
            $finding->engagement,
            'aems.finding.create',
        );
    }

    public function review(User $user, AuditFinding $finding): bool
    {
        return $this->allowsFindingAction($user, $finding, 'aems.finding.review');
    }

    public function validate(User $user, AuditFinding $finding): bool
    {
        return $this->allowsFindingAction($user, $finding, 'aems.finding.validate');
    }

    public function communicate(User $user, AuditFinding $finding): bool
    {
        return $this->allowsFindingAction($user, $finding, 'aems.finding.communicate');
    }

    public function finalize(User $user, AuditFinding $finding): bool
    {
        return $this->allowsFindingAction($user, $finding, 'aems.finding.finalize');
    }

    public function revise(User $user, AuditFinding $finding): bool
    {
        return $this->allowsAction($user, $finding->engagement, 'aems.finding.revise');
    }

    public function submitManagementResponse(User $user, AuditFinding $finding): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeManagementResponseSubmit($user, $finding),
        );
    }

    private function allowsFindingAction(
        User $user,
        AuditFinding $finding,
        string $permission,
    ): bool {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $finding->engagement,
                $permission,
                $finding->authored_by,
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
