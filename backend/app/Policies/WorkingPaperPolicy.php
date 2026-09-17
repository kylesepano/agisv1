<?php

namespace App\Policies;

use App\Models\AuditEngagement;
use App\Models\User;
use App\Models\WorkingPaper;
use App\Services\AemsAccessService;
use Throwable;

/**
 * Applies engagement assignment roles and preparer/reviewer separation to working papers.
 */
class WorkingPaperPolicy
{
    public function __construct(private readonly AemsAccessService $access) {}

    public function view(User $user, WorkingPaper $paper): bool
    {
        return $this->allowsAction($user, $paper->engagement, 'aems.working-paper.view');
    }

    public function create(User $user, AuditEngagement $engagement): bool
    {
        return $this->allowsAction($user, $engagement, 'aems.working-paper.create');
    }

    public function prepare(User $user, WorkingPaper $paper): bool
    {
        return $this->allowsAction(
            $user,
            $paper->engagement,
            'aems.working-paper.create',
        );
    }

    public function void(User $user, WorkingPaper $paper): bool
    {
        return $this->allowsPaperAction($user, $paper, 'aems.working-paper.review');
    }

    public function review(User $user, WorkingPaper $paper): bool
    {
        return $this->allowsPaperAction($user, $paper, 'aems.working-paper.review');
    }

    public function approve(User $user, WorkingPaper $paper): bool
    {
        return $this->allowsPaperAction($user, $paper, 'aems.working-paper.approve');
    }

    private function allowsPaperAction(
        User $user,
        WorkingPaper $paper,
        string $permission,
    ): bool {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $paper->engagement,
                $permission,
                $paper->prepared_by,
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
