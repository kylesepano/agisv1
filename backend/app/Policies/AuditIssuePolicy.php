<?php

namespace App\Policies;

use App\Models\AuditEngagement;
use App\Models\AuditIssue;
use App\Models\User;
use App\Services\AemsAccessService;
use Throwable;

/** Enforces engagement assignment and author/reviewer separation for issues. */
class AuditIssuePolicy
{
    public function __construct(private readonly AemsAccessService $access) {}

    public function view(User $user, AuditIssue $issue): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $issue->engagement,
                'aems.issue.view',
            ),
        );
    }

    public function create(User $user, AuditEngagement $engagement): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $engagement,
                'aems.issue.create',
            ),
        );
    }

    public function prepare(User $user, AuditIssue $issue): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $issue->engagement,
                'aems.issue.create',
            ),
        );
    }

    public function validate(User $user, AuditIssue $issue): bool
    {
        return $this->review($user, $issue, 'aems.issue.validate');
    }

    public function dismiss(User $user, AuditIssue $issue): bool
    {
        return $this->review($user, $issue, 'aems.issue.dismiss');
    }

    public function convert(User $user, AuditIssue $issue): bool
    {
        return $this->review($user, $issue, 'aems.issue.convert');
    }

    public function merge(User $user, AuditIssue $issue): bool
    {
        return $this->review($user, $issue, 'aems.issue.merge');
    }

    public function resolve(User $user, AuditIssue $issue): bool
    {
        return $this->review($user, $issue, 'aems.issue.resolve');
    }

    public function observe(User $user, AuditIssue $issue): bool
    {
        return $this->review($user, $issue, 'aems.issue.observe');
    }

    public function refer(User $user, AuditIssue $issue): bool
    {
        return $this->review($user, $issue, 'aems.issue.refer');
    }

    public function closeWithoutFinding(User $user, AuditIssue $issue): bool
    {
        return $this->review($user, $issue, 'aems.issue.close_without_finding');
    }

    public function withdraw(User $user, AuditIssue $issue): bool
    {
        return $this->review($user, $issue, 'aems.issue.withdraw');
    }

    private function review(User $user, AuditIssue $issue, string $permission): bool
    {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $issue->engagement,
                $permission,
                $issue->raised_by,
            ),
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
