<?php

namespace App\Policies;

use App\Models\AuditEngagement;
use App\Models\AuditReport;
use App\Models\User;
use App\Services\AemsAccessService;
use Throwable;

/**
 * Separates internal report preparation from approval, issuance, and recipient access.
 */
class AuditReportPolicy
{
    public function __construct(private readonly AemsAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['aems.report.view', 'aems.report.view_issued']);
    }

    public function view(User $user, AuditReport $report): bool
    {
        return $this->allows(fn () => $this->access->authorizeReportView($user, $report));
    }

    public function create(User $user, AuditEngagement $engagement): bool
    {
        return $this->allowsAction($user, $engagement, 'aems.report.create');
    }

    public function review(User $user, AuditReport $report): bool
    {
        return $this->allowsReportAction($user, $report, 'aems.report.review');
    }

    public function approve(User $user, AuditReport $report): bool
    {
        return $this->allowsReportAction($user, $report, 'aems.report.approve');
    }

    public function issue(User $user, AuditReport $report): bool
    {
        return $this->allowsReportAction($user, $report, 'aems.report.issue');
    }

    private function allowsReportAction(
        User $user,
        AuditReport $report,
        string $permission,
    ): bool {
        return $this->allows(
            fn () => $this->access->authorizeEngagementAction(
                $user,
                $report->engagement,
                $permission,
                $report->prepared_by,
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
