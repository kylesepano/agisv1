<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditReport;
use App\Models\AemsEvidenceRequest;
use App\Models\EntryConference;
use App\Models\ExitConference;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Centralizes AEMS role, assignment, office, report-recipient, and separation-of-duty access.
 */
class AemsAccessService
{
    /** @var list<string> */
    private const INDEPENDENT_ACTIONS = [
        'aems.aeo.review',
        'aems.aeo.approve',
        'aems.aeo.issue',
        'aems.aeo.sign',
        'aems.engagement.authorize',
        'aems.aep.review',
        'aems.aep.approve',
        'aems.program.review',
        'aems.program.approve',
        'aems.planning-package.review',
        'aems.planning-package.approve',
        'aems.working-paper.review',
        'aems.working-paper.approve',
        'aems.fieldwork.review',
        'aems.fieldwork.finalize',
        'aems.evidence-request.assess',
        'aems.evidence-request.extension_approve',
        'aems.management-response.approve_extension',
        'aems.management-response.reject_extension',
        'aems.evidence.exception_approve',
        'aems.evidence.outcome',
        'aems.issue.validate',
        'aems.issue.dismiss',
        'aems.issue.convert',
        'aems.issue.merge',
        'aems.issue.resolve',
        'aems.issue.observe',
        'aems.issue.refer',
        'aems.issue.close_without_finding',
        'aems.issue.withdraw',
        'aems.afr.transmit',
        'aems.afr.delivery',
        'aems.afr.acknowledge',
        'aems.finding.review',
        'aems.finding.validate',
        'aems.finding.revise',
        'aems.finding.communicate',
        'aems.finding.finalize',
        'aems.report.review',
        'aems.report.approve',
        'aems.report.issue',
        'aems.report.amend',
        'aems.report.supersede',
        'aems.report.authority',
        'aems.report.signatory',
        'aems.report.transmit',
        'aems.report.export',
        'aems.engagement.close',
        'aems.completion-assessment.review',
        'aems.completion-assessment.approve',
        'aems.completion-transfer.approve',
        'aems.closure.review',
        'aems.closure.approve',
        'aems.closure.close',
        'aems.retention.approve',
        'aems.retention.legal_hold_release',
        'aems.retention.destruction_review',
        'aems.retention.disposition_execute',
        'aems.engagement.reopen_approve',
    ];

    /** @var array<string, list<string>> */
    private const ASSIGNMENT_ROLES = [
        'aems.engagement.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.engagement.update' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.engagement.transition' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.foundation.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.foundation.manage_scope' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.team.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.team.safeguard_view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.team.safeguard_declare' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.team.history' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.team.amend' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.team.safeguard_review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.aeo.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.aeo.prepare' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.aeo.review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.aeo.sign' => ['SUPERVISOR', 'REVIEWER'],
        'aems.aeo.acknowledge' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.aeo.distribute' => ['SUPERVISOR'],
        'aems.aep.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.aep.create' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.aep.review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.program.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.program.manage' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.program.review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.fieldwork.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.fieldwork.create' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.fieldwork.review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.fieldwork.finalize' => ['SUPERVISOR', 'REVIEWER'],
        'aems.planning-package.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.planning-package.create' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.planning-package.update' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.planning-package.review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.working-paper.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.working-paper.create' => ['TEAM_LEADER', 'AUDITOR'],
        'aems.working-paper.review' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.working-paper.approve' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.evidence.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.evidence.upload' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.evidence.verify' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.evidence.assess' => ['SUPERVISOR', 'REVIEWER'],
        'aems.evidence.exception_approve' => ['SUPERVISOR'],
        'aems.evidence.outcome' => ['SUPERVISOR', 'REVIEWER'],
        'aems.evidence.link_report' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.evidence-request.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.evidence-request.create' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.evidence-request.update' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.evidence-request.submit' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.evidence-request.send' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.evidence-request.receive' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.evidence-request.assess' => ['SUPERVISOR', 'REVIEWER'],
        'aems.evidence-request.close' => ['SUPERVISOR'],
        'aems.evidence-request.acknowledge' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.evidence-request.extend' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.evidence-request.extension_approve' => ['SUPERVISOR', 'REVIEWER'],
        'aems.evidence-request.overdue' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.evidence-request.escalate' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.evidence-request.cancel' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.issue.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.issue.create' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.issue.validate' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.issue.dismiss' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.issue.convert' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.issue.merge' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.issue.resolve' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.issue.observe' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.issue.refer' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.issue.close_without_finding' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.issue.withdraw' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.afr.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.afr.transmit' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.afr.delivery' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.afr.acknowledge' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.finding.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.finding.create' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.finding.review' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.finding.validate' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.finding.revise' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.management-response.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.management-response.request_clarification' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.management-response.request_extension' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.management-response.approve_extension' => ['SUPERVISOR', 'REVIEWER'],
        'aems.management-response.reject_extension' => ['SUPERVISOR', 'REVIEWER'],
        'aems.management-response.supplement' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.rejoinder.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.rejoinder.create' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.rejoinder.finalize' => ['SUPERVISOR', 'TEAM_LEADER', 'REVIEWER'],
        'aems.task.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.task.create' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.task.update' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.task.assign' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.task.complete' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.task.cancel' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.task.reopen' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.task.escalate' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.review-note.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER', 'SPECIALIST', 'AUTHORIZED_PARTICIPANT'],
        'aems.review-note.create' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.review-note.update' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.review-note.finalize' => ['SUPERVISOR', 'REVIEWER'],
        'aems.review-note.revise' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.review-note.attach' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.due-process.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.due-process.create' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.due-process.remind' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.due-process.record_non_response' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.due-process.attach' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.escalation-candidate.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.escalation-candidate.create' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR'],
        'aems.escalation-candidate.review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.escalation-candidate.resolve' => ['SUPERVISOR', 'REVIEWER'],
        'aems.escalation-candidate.dismiss' => ['SUPERVISOR', 'REVIEWER'],
        'aems.conference.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.conference.manage' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.entry-conference.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.entry-conference.manage' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.entry-conference.acknowledge' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.report.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.report.create' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.report.review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.report.view_issued' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.report.distribute' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.report.amend' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.report.supersede' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.report.withdraw' => ['SUPERVISOR'],
        'aems.report.authority' => ['SUPERVISOR', 'REVIEWER'],
        'aems.report.signatory' => ['SUPERVISOR', 'REVIEWER'],
        'aems.report.transmit' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.report.export' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.report.close_admin' => ['SUPERVISOR'],
        'aems.report.acknowledge' => [],
        'aems.completion-assessment.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.completion-assessment.create' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.completion-assessment.update' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.completion-assessment.submit' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.completion-assessment.review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.completion-transfer.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.completion-transfer.reconcile' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.completion-transfer.approve' => ['SUPERVISOR', 'REVIEWER'],
        'aems.closure.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.closure.create' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.closure.update' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.closure.submit' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.closure.review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.document-index.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.document-index.manage' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.retention.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.retention.manage' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.retention.archive' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.retention.legal_hold_release' => ['SUPERVISOR', 'REVIEWER'],
        'aems.retention.destruction_review' => ['SUPERVISOR', 'REVIEWER'],
        'aems.retention.disposition_execute' => ['SUPERVISOR'],
        'aems.records.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.records.search' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.calendar.view' => ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'],
        'aems.calendar.manage' => ['SUPERVISOR', 'TEAM_LEADER'],
        'aems.engagement.reopen_request' => ['SUPERVISOR', 'TEAM_LEADER'],
    ];

    public function visibleEngagements(Builder $query, User $user): Builder
    {
        if (! $user->hasPermission('aems.engagement.view')) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->hasGlobalEngagementAccess()) {
            return $query;
        }

        return $query->whereHas(
            'teamMembers',
            fn (Builder $team): Builder => $team
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->whereNull('ended_at'),
        );
    }

    public function authorizeEngagementView(User $user, AuditEngagement $engagement): void
    {
        $allowed = $this->visibleEngagements(
            AuditEngagement::query()->withTrashed()->whereKey($engagement->id),
            $user,
        )->exists();

        throw_unless($allowed, new HttpException(403, 'This engagement is outside your AEMS access scope.'));
    }

    public function authorizeEngagementAction(
        User $user,
        AuditEngagement $engagement,
        string $permission,
        ?int $originatorId = null,
    ): void {
        throw_unless(
            $user->hasPermission($permission),
            new HttpException(403, 'You do not have the required AEMS permission.'),
        );

        if ($engagement->status === 'CLOSED'
            && ! str_ends_with($permission, '.view')
            && ! in_array($permission, [
                'aems.engagement.reopen_request',
                'aems.engagement.reopen_approve',
                'aems.retention.archive',
                'aems.retention.legal_hold_release',
                'aems.retention.destruction_review',
                'aems.retention.disposition_execute',
            ], true)) {
            throw ValidationException::withMessages([
                'engagement' => ['Closed engagement official records are immutable.'],
            ]);
        }

        if ($user->hasGlobalEngagementAccess()
            && str_ends_with($permission, '.view')) {
            // Global administrators may monitor AEMS records but still receive
            // no operational or approval authority from this read-only branch.
        } elseif (array_key_exists($permission, self::ASSIGNMENT_ROLES)
            && ! $user->hasGlobalEngagementAccess()) {
            $allowedRoles = self::ASSIGNMENT_ROLES[$permission];
            throw_unless(
                $this->hasAssignmentRole($user, $engagement, $allowedRoles),
                new HttpException(403, 'Your engagement assignment does not allow this action.'),
            );
        }

        $this->enforceSeparationOfDuties($user, $permission, $originatorId, $engagement);
    }

    /**
     * A user with the explicit self-review permission may complete the AEO
     * review/approval/issue sequence for a submission they prepared.
     */
    public function mayUseCiasHeadAeoReviewException(User $user, string $permission): bool
    {
        return $user->hasPermission('aems.review.own_submission')
            && in_array($permission, [
                'aems.aeo.review',
                'aems.aeo.approve',
                'aems.aeo.issue',
            ], true);
    }

    /**
     * The aggregate engagement authorization may be performed by the
     * originating user only when the explicit self-review permission is
     * granted and the AEO has already been approved. Issuance is a later gate.
     */
    public function mayUseSingleCiasEngagementAuthorization(
        User $user,
        string $permission,
        ?AuditEngagement $engagement = null,
    ): bool
    {
        if ($permission !== 'aems.engagement.authorize'
            || ! $user->hasPermission('aems.review.own_submission')
            || ! $engagement) {
            return false;
        }

        $order = $engagement->engagementOrder;
        if (! $order
            || ! $order->is_active
            || ! in_array($order->status, ['APPROVED', 'ISSUED'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Returns whether the user has been explicitly granted the controlled
     * self-review exception for an independent AEMS action.
     */
    public function mayUseSingleCiasHeadReviewException(User $user, string $permission): bool
    {
        return in_array($permission, [
                'aems.aeo.review',
                'aems.aeo.approve',
                'aems.aeo.issue',
                'aems.aep.review',
                'aems.aep.approve',
                'aems.planning-package.review',
                'aems.planning-package.approve',
            ], true)
            && $user->hasPermission('aems.review.own_submission');
    }

    public function authorizeEvidenceRequestAcknowledgement(User $user, AemsEvidenceRequest $record): void
    {
        throw_unless($user->hasPermission('aems.evidence-request.acknowledge'), new HttpException(403, 'You do not have acknowledgement permission.'));
        $allowed = $user->hasGlobalEngagementAccess()
            || ($user->hasPermission('access.auditee_scope')
                && (($record->requested_from_user_id && (int) $record->requested_from_user_id === (int) $user->id)
                    || ($record->requested_from_office_id && ! $record->requested_from_user_id && (int) $record->requested_from_office_id === (int) $user->office_id)
                    || (! $record->requested_from_user_id && ! $record->requested_from_office_id
                        && $record->engagement->offices()->whereKey($user->office_id)->exists())))
            || $this->isAssigned($user, $record->engagement);
        throw_unless($allowed, new HttpException(403, 'Only the requested custodian office or user may acknowledge this request.'));
    }

    public function authorizeEvidenceRequestResponse(User $user, AemsEvidenceRequest $record): void
    {
        throw_unless(
            $user->hasPermission('aems.evidence-request.respond'),
            new HttpException(403, 'You do not have permission to submit Evidence Request responses.'),
        );
        throw_unless(
            $user->hasPermission('access.auditee_scope') && $user->office_id,
            new HttpException(403, 'Only an auditee-scoped user may submit an Evidence Request response.'),
        );
        $requestedUser = $record->requested_from_user_id
            && (int) $record->requested_from_user_id === (int) $user->id;
        $requestedOffice = $record->requested_from_office_id
            && ! $record->requested_from_user_id
            && (int) $record->requested_from_office_id === (int) $user->office_id;
        $engagementOffice = ! $record->requested_from_user_id
            && ! $record->requested_from_office_id
            && $record->engagement
            && $record->engagement->offices()->whereKey($user->office_id)->exists();
        throw_unless(
            $requestedUser || $requestedOffice || $engagementOffice,
            new HttpException(403, 'Only the requested custodian office or user may submit evidence.'),
        );
    }

    public function visibleFindings(Builder $query, User $user): Builder
    {
        if (! $user->hasPermission('aems.finding.view')) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->hasGlobalEngagementAccess()) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user): void {
            $visible->whereHas(
                'engagement.teamMembers',
                fn (Builder $team): Builder => $team
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->whereNull('ended_at'),
            );
            if ($user->hasPermission('access.auditee_scope') && $user->office_id) {
                $visible->orWhere(function (Builder $auditee) use ($user): void {
                    $auditee
                        ->where('responsible_office_id', $user->office_id)
                        ->whereIn('status', [
                            'COMMUNICATED',
                            'AWAITING_MANAGEMENT_RESPONSE',
                            'UNDER_DIALOGUE',
                            'FINALIZED',
                        ]);
                });
            }
        });
    }

    public function authorizeFindingView(User $user, AuditFinding $finding): void
    {
        $allowed = $this->visibleFindings(
            AuditFinding::query()->withTrashed()->whereKey($finding->id),
            $user,
        )->exists();

        throw_unless($allowed, new HttpException(403, 'This finding is outside your AEMS access scope.'));
    }

    public function authorizeManagementResponseSubmit(User $user, AuditFinding $finding): void
    {
        throw_unless(
            $user->hasPermission('aems.management-response.submit')
                && $user->hasPermission('access.auditee_scope')
                && (int) $user->office_id === (int) $finding->responsible_office_id
                && in_array($finding->status, [
                    'COMMUNICATED',
                    'AWAITING_MANAGEMENT_RESPONSE',
                    'UNDER_DIALOGUE',
                ], true),
            new HttpException(403, 'You cannot respond to this finding.'),
        );
    }

    public function authorizeConferenceView(User $user, ExitConference $conference): void
    {
        throw_unless(
            $user->hasPermission('aems.conference.view'),
            new HttpException(403, 'You cannot view AEMS conferences.'),
        );
        if ($user->hasGlobalEngagementAccess()
            || $this->isAssigned($user, $conference->engagement)) {
            return;
        }

        $covered = $user->hasPermission('access.auditee_scope')
            && $user->office_id
            && $conference->engagement->offices()->whereKey($user->office_id)->exists();

        throw_unless($covered, new HttpException(403, 'This conference is outside your office scope.'));
    }

    public function authorizeEntryConferenceView(
        User $user,
        AuditEngagement|EntryConference $record,
    ): void {
        throw_unless(
            $user->hasPermission('aems.entry-conference.view'),
            new HttpException(403, 'You cannot view Entry Conferences.'),
        );
        $engagement = $record instanceof EntryConference ? $record->engagement : $record;
        if ($user->hasGlobalEngagementAccess() || $this->isAssigned($user, $engagement)) {
            return;
        }

        $covered = $user->hasPermission('access.auditee_scope')
            && $user->office_id
            && $engagement->offices()->whereKey($user->office_id)->exists();

        throw_unless($covered, new HttpException(403, 'This Entry Conference is outside your office scope.'));
    }

    public function visibleReports(Builder $query, User $user): Builder
    {
        $canViewInternal = $user->hasPermission('aems.report.view');
        $canViewIssued = $user->hasPermission('aems.report.view_issued');
        if (! $canViewInternal && ! $canViewIssued) {
            return $query->whereRaw('1 = 0');
        }
        if ($canViewInternal && $user->hasGlobalEngagementAccess()) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user, $canViewInternal, $canViewIssued): void {
            if ($canViewInternal && ! $user->hasGlobalEngagementAccess()) {
                $visible->whereHas(
                    'engagement.teamMembers',
                    fn (Builder $team): Builder => $team
                        ->where('user_id', $user->id)
                        ->where('is_active', true)
                        ->whereNull('ended_at'),
                );
            }

            if ($canViewIssued) {
                if ($user->hasGlobalEngagementAccess() && ! $user->isReadOnlyOnly()) {
                    // Platform administrators may monitor issued output, not working drafts.
                    $visible->orWhereIn('status', ['ISSUED', 'ADMINISTRATIVELY_CLOSED']);
                } else {
                    $visible->orWhere(function (Builder $issued) use ($user): void {
                        $issued->whereIn('status', ['ISSUED', 'ADMINISTRATIVELY_CLOSED'])
                            ->whereHas('versions', function (Builder $version) use ($user): void {
                                $version
                                    ->whereColumn(
                                        'audit_report_versions.version_number',
                                        'audit_reports.current_version_number',
                                    )
                                    ->whereHas('recipients', function (Builder $recipient) use ($user): void {
                                        $recipient->where('user_id', $user->id);
                                        if ($user->hasPermission('access.auditee_scope') && $user->office_id) {
                                            $recipient->orWhere('office_id', $user->office_id);
                                        }
                                    });
                            });
                    });
                }
            }
        });
    }

    public function authorizeReportView(User $user, AuditReport $report): void
    {
        $allowed = $this->visibleReports(
            AuditReport::query()->withTrashed()->whereKey($report->id),
            $user,
        )->exists();

        throw_unless($allowed, new HttpException(403, 'This report is outside your AEMS access scope.'));
    }

    /** @param list<string> $allowedRoles */
    private function hasAssignmentRole(
        User $user,
        AuditEngagement $engagement,
        array $allowedRoles,
    ): bool {
        if ($allowedRoles === []) {
            return false;
        }

        return $engagement->teamMembers()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->whereIn('assignment_role_code', $allowedRoles)
            ->exists();
    }

    private function isAssigned(User $user, AuditEngagement $engagement): bool
    {
        return $engagement->teamMembers()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->exists();
    }

    private function enforceSeparationOfDuties(
        User $user,
        string $permission,
        ?int $originatorId,
        AuditEngagement $engagement,
    ): void {
        if ($originatorId !== null
            && $originatorId === $user->id
            && in_array($permission, self::INDEPENDENT_ACTIONS, true)
            && ! $this->mayUseCiasHeadAeoReviewException($user, $permission)
            && ! $this->mayUseSingleCiasEngagementAuthorization($user, $permission, $engagement)
            && ! $this->mayUseSingleCiasHeadReviewException($user, $permission)) {
            throw ValidationException::withMessages([
                'action' => ['The preparer or originator cannot perform this independent AEMS action.'],
            ]);
        }
    }
}
