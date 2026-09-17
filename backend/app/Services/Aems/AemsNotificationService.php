<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditIssue;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Models\CompletionAssessment;
use App\Models\EngagementClosure;
use App\Models\EngagementReopenRequest;
use App\Models\EngagementTeam;
use App\Models\EntryConference;
use App\Models\ExitConference;
use App\Models\User;
use App\Models\WorkingPaper;
use App\Models\AemsTeamSafeguardAssessment;
use App\Models\AemsTeamSafeguardDeclaration;
use App\Models\AemsAeoDistribution;
use App\Models\AuditEngagementOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Routes AEMS workflow events through Core notifications. */
class AemsNotificationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function teamAssignment(
        Request $request,
        AuditEngagement $engagement,
        EngagementTeam $member,
        string $action,
    ): void {
        $actorId = $request->user()->id;
        $recipientId = $member->user_id;
        $assignmentRole = str($member->assignment_role_code)->replace('_', ' ')->title();
        $lockVersion = $member->updated_at?->timestamp ?? $member->id;

        DB::afterCommit(fn () => $this->notifications->send([$recipientId], [
            'actorId' => $actorId,
            'type' => 'AEMS_TEAM_'.strtoupper($action),
            'category' => 'ASSIGNMENT',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$engagement->engagement_code}: {$assignmentRole} assignment",
            'message' => "You were {$action} as {$assignmentRole} for {$engagement->title}.",
            'actionUrl' => "/audit-engagement-management/team?engagementId={$engagement->id}",
            'actionLabel' => 'Open Audit Team',
            'subjectType' => AuditEngagement::class,
            'subjectId' => $engagement->id,
            'subjectCode' => $engagement->engagement_code,
            'dedupeKey' => "aems:team:{$member->id}:{$action}:{$lockVersion}",
            'metadata' => [
                'engagementId' => $engagement->id,
                'teamMemberId' => $member->id,
                'assignmentRoleCode' => $member->assignment_role_code,
            ],
        ]));
    }

    public function teamSafeguard(
        Request $request,
        AuditEngagement $engagement,
        EngagementTeam $member,
        string $action,
        AemsTeamSafeguardDeclaration $declaration,
    ): void {
        $recipientIds = $action === 'SUBMITTED'
            ? $this->reviewers($engagement, 'aems.team.safeguard_review')
            : collect([$declaration->user_id]);
        $recipientIds = $recipientIds
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->unique()->values();
        $actorId = $request->user()->id;
        $label = str($declaration->declaration_type)->replace('_', ' ')->title();
        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_TEAM_SAFEGUARD_'.$action,
            'category' => 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$engagement->engagement_code}: {$label} declaration {$action}",
            'message' => "The {$label} declaration for {$engagement->title} was {$action}.",
            'actionUrl' => "/audit-engagement-management/team?engagementId={$engagement->id}&tab=safeguards",
            'actionLabel' => 'Open Team Safeguards',
            'subjectType' => AemsTeamSafeguardDeclaration::class,
            'subjectId' => $declaration->id,
            'subjectCode' => $declaration->declaration_family_uuid,
            'dedupeKey' => "aems:team-safeguard:declaration:{$declaration->id}:{$action}:{$declaration->lock_version}",
            'metadata' => [
                'engagementId' => $engagement->id,
                'teamMemberId' => $member->id,
                'declarationType' => $declaration->declaration_type,
                'versionNumber' => $declaration->version_number,
            ],
        ]));
    }

    public function teamSafeguardDecision(
        Request $request,
        AuditEngagement $engagement,
        AemsTeamSafeguardAssessment $assessment,
    ): void {
        $recipientIds = $engagement->teamMembers()
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->pluck('user_id')
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->unique()->values();
        $actorId = $request->user()->id;
        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_TEAM_SAFEGUARD_APPROVED',
            'category' => 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$engagement->engagement_code}: team safeguards approved",
            'message' => "The provider and independence safeguards for {$engagement->title} were approved.",
            'actionUrl' => "/audit-engagement-management/team?engagementId={$engagement->id}&tab=safeguards",
            'actionLabel' => 'Open Team Safeguards',
            'subjectType' => AemsTeamSafeguardAssessment::class,
            'subjectId' => $assessment->id,
            'subjectCode' => $assessment->assessment_uuid,
            'dedupeKey' => "aems:team-safeguard:assessment:{$assessment->id}:approved",
            'metadata' => [
                'engagementId' => $engagement->id,
                'assessmentVersion' => $assessment->version_number,
                'providerMode' => $assessment->provider_mode,
            ],
        ]));
    }

    public function fieldworkTransition(
        Request $request,
        AuditEngagement $engagement,
        \App\Models\AemsFieldworkRecord $record,
        string $action,
        int $versionNumber,
        ?string $comment,
    ): void {
        if (! in_array($action, ['SUBMIT', 'RESUBMIT', 'REVIEW', 'RETURN', 'FINALIZE'], true)) {
            return;
        }
        $recipientIds = in_array($action, ['SUBMIT', 'RESUBMIT'], true)
            ? $this->reviewers($engagement, 'aems.fieldwork.review')
            : collect([$record->prepared_by, $record->submitted_by, $record->reviewer_id])->filter();
        $recipientIds = $recipientIds->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)->unique()->values();
        $verb = match ($action) {
            'SUBMIT' => 'submitted for review',
            'RESUBMIT' => 'resubmitted for review',
            'REVIEW' => 'independently reviewed',
            'RETURN' => 'returned for revision',
            'FINALIZE' => 'finalized',
        };
        $actorId = $request->user()->id;
        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_FIELDWORK_'.$action,
            'category' => 'WORKFLOW',
            'priority' => $action === 'FINALIZE' ? 'NORMAL' : 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$record->record_code}: Fieldwork Record {$verb}",
            'message' => "Fieldwork Record version {$versionNumber} for {$engagement->title} was {$verb}.".($comment ? " {$comment}" : ''),
            'actionUrl' => "/audit-engagement-management/audit-program?engagementId={$engagement->id}",
            'actionLabel' => 'Open Fieldwork Records',
            'subjectType' => \App\Models\AemsFieldworkRecord::class,
            'subjectId' => $record->id,
            'subjectCode' => $record->record_code,
            'dedupeKey' => "aems:fieldwork:{$record->id}:v{$versionNumber}:".strtolower($action),
            'metadata' => ['engagementId' => $engagement->id, 'versionNumber' => $versionNumber, 'workflowAction' => $action],
        ]));
    }

    public function evidenceRequestTransition(
        Request $request,
        AuditEngagement $engagement,
        \App\Models\AemsEvidenceRequest $record,
        string $action,
    ): void {
        if (! in_array($action, ['SUBMIT', 'SEND', 'ACKNOWLEDGE', 'RESPONSE_SUBMITTED', 'MARK_OVERDUE', 'REQUEST_EXTENSION', 'APPROVE_EXTENSION', 'REJECT_EXTENSION', 'ESCALATE', 'MARK_PARTIALLY_RECEIVED', 'MARK_RECEIVED', 'FOR_REVIEW', 'ASSESS', 'CLOSE_WITHOUT_SUBMISSION', 'CANCEL', 'CLOSE'], true)) {
            return;
        }
        $recipientIds = $record->requested_from_user_id
            ? collect([$record->requested_from_user_id])
            : ($record->requested_from_office_id
                ? User::query()->where('office_id', $record->requested_from_office_id)->pluck('id')
                : $this->reviewers($engagement, 'aems.evidence-request.view'));
        $recipientIds = $recipientIds->merge([$record->prepared_by, $record->submitted_by])
            ->filter()->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)->unique()->values();
        $verb = match ($action) {
            'SUBMIT' => 'submitted', 'SEND' => 'sent', 'MARK_PARTIALLY_RECEIVED' => 'partially received',
            'MARK_RECEIVED' => 'received', 'ACKNOWLEDGE' => 'acknowledged', 'RESPONSE_SUBMITTED' => 'received a response', 'MARK_OVERDUE' => 'marked overdue',
            'REQUEST_EXTENSION' => 'requested an extension', 'APPROVE_EXTENSION' => 'received an approved extension',
            'REJECT_EXTENSION' => 'had its extension declined', 'ESCALATE' => 'escalated',
            'FOR_REVIEW' => 'queued for assessment', 'ASSESS' => 'assessed', 'CLOSE_WITHOUT_SUBMISSION' => 'closed without submission',
            'CANCEL' => 'cancelled', 'CLOSE' => 'closed',
        };
        $actorId = $request->user()->id;
        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_EVIDENCE_REQUEST_'.strtoupper($action),
            'category' => 'WORKFLOW',
            'priority' => in_array($action, ['SUBMIT', 'SEND', 'MARK_RECEIVED'], true) ? 'HIGH' : 'NORMAL',
            'moduleCode' => 'AEMS',
            'title' => "{$record->request_code}: Evidence Request {$verb}",
            'message' => "Evidence Request {$record->request_code} for {$engagement->title} was {$verb}.",
            'actionUrl' => "/audit-engagement-management/working-papers?engagementId={$engagement->id}",
            'actionLabel' => 'Open Evidence Workspace',
            'subjectType' => \App\Models\AemsEvidenceRequest::class,
            'subjectId' => $record->id,
            'subjectCode' => $record->request_code,
            'dedupeKey' => "aems:evidence-request:{$record->id}:{$record->lock_version}:".strtolower($action),
            'metadata' => ['engagementId' => $engagement->id, 'workflowAction' => $action],
        ]));
    }

    public function evidenceAssessmentRecorded(
        Request $request,
        AuditEngagement $engagement,
        \App\Models\AemsEvidenceAssessment $assessment,
        string $action = 'ASSESSED',
    ): void {
        $recipientIds = $this->reviewers($engagement, 'aems.evidence-request.view')
            ->merge([$assessment->evidence?->uploaded_by, $assessment->evidence?->verified_by])
            ->filter()->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)->unique()->values();
        $verb = $action === 'EXCEPTION_APPROVED' ? 'received an approved exception' : 'was assessed';
        $actorId = $request->user()->id;
        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_EVIDENCE_'.strtoupper($action),
            'category' => 'WORKFLOW',
            'priority' => $action === 'EXCEPTION_APPROVED' ? 'HIGH' : 'NORMAL',
            'moduleCode' => 'AEMS',
            'title' => "Evidence {$verb}",
            'message' => "Evidence {$assessment->evidence?->evidence_code} for {$engagement->title} {$verb}.",
            'actionUrl' => "/audit-engagement-management/working-papers?engagementId={$engagement->id}",
            'actionLabel' => 'Open Evidence Workspace',
            'subjectType' => \App\Models\AemsEvidenceAssessment::class,
            'subjectId' => $assessment->id,
            'subjectCode' => $assessment->evidence?->evidence_code,
            'dedupeKey' => "aems:evidence-assessment:{$assessment->id}:{$assessment->lock_version}:".strtolower($action),
            'metadata' => ['engagementId' => $engagement->id, 'assessmentId' => $assessment->id, 'documentVersionId' => $assessment->document_version_id],
        ]));
    }

    public function evidenceOutcomeRecorded(
        Request $request,
        AuditEngagement $engagement,
        \App\Models\AuditEvidence $evidence,
        string $outcome,
    ): void {
        $recipientIds = $this->reviewers($engagement, 'aems.evidence.view')
            ->merge([$evidence->uploaded_by, $evidence->verified_by])
            ->filter()->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)->unique()->values();
        $actorId = $request->user()->id;
        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_EVIDENCE_OUTCOME_'.strtoupper($outcome),
            'category' => 'WORKFLOW',
            'priority' => in_array($outcome, ['REJECTED', 'ADDITIONAL_REQUIRED', 'DUPLICATE'], true) ? 'HIGH' : 'NORMAL',
            'moduleCode' => 'AEMS',
            'title' => "Evidence {$evidence->evidence_code}: {$outcome}",
            'message' => "Evidence {$evidence->evidence_code} for {$engagement->title} has outcome {$outcome}.",
            'actionUrl' => "/audit-engagement-management/working-papers?engagementId={$engagement->id}",
            'actionLabel' => 'Open Evidence Workspace',
            'subjectType' => \App\Models\AuditEvidence::class,
            'subjectId' => $evidence->id,
            'subjectCode' => $evidence->evidence_code,
            'dedupeKey' => "aems:evidence:{$evidence->id}:outcome:{$evidence->lock_version}",
            'metadata' => ['engagementId' => $engagement->id, 'outcome' => $outcome],
        ]));
    }

    public function controlledDocumentTransition(
        Request $request,
        AuditEngagement $engagement,
        string $documentType,
        int $documentId,
        string $documentCode,
        string $documentLabel,
        string $action,
        int $versionNumber,
        int $preparedBy,
        ?int $submittedBy,
        string $reviewPermission,
        string $actionUrl,
    ): void {
        if (! in_array($action, ['SUBMIT', 'RESUBMIT', 'RETURN', 'APPROVE'], true)) {
            return;
        }

        $recipientIds = in_array($action, ['SUBMIT', 'RESUBMIT'], true)
            ? $this->reviewers($engagement, $reviewPermission)
            : collect([$preparedBy, $submittedBy])->filter();
        $recipientIds = $recipientIds
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $verb = match ($action) {
            'SUBMIT' => 'submitted for review',
            'RESUBMIT' => 'resubmitted for review',
            'RETURN' => 'returned for revision',
            'APPROVE' => 'approved',
        };
        $priority = in_array($action, ['SUBMIT', 'RESUBMIT', 'RETURN'], true)
            ? 'HIGH'
            : 'NORMAL';
        $actorId = $request->user()->id;

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => "AEMS_{$documentType}_{$action}",
            'category' => 'WORKFLOW',
            'priority' => $priority,
            'moduleCode' => 'AEMS',
            'title' => "{$documentCode}: {$documentLabel} {$verb}",
            'message' => "{$documentLabel} version {$versionNumber} for {$engagement->title} was {$verb}.",
            'actionUrl' => $actionUrl,
            'actionLabel' => "Open {$documentLabel}",
            'subjectType' => $documentType,
            'subjectId' => $documentId,
            'subjectCode' => $documentCode,
            'dedupeKey' => "aems:{$documentType}:{$documentId}:v{$versionNumber}:".strtolower($action),
            'metadata' => [
                'engagementId' => $engagement->id,
                'versionNumber' => $versionNumber,
                'workflowAction' => $action,
            ],
        ]));
    }

    public function workingPaperReturned(
        Request $request,
        AuditEngagement $engagement,
        WorkingPaper $paper,
        int $versionNumber,
        ?string $comment,
    ): void {
        $actorId = $request->user()->id;
        $recipientIds = collect([$paper->prepared_by, $paper->submitted_by])
            ->filter()
            ->reject(fn ($id): bool => (int) $id === (int) $actorId)
            ->unique()
            ->values();

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_WORKING_PAPER_RETURNED',
            'category' => 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$paper->working_paper_code}: Working Paper returned",
            'message' => trim("Working Paper version {$versionNumber} was returned for revision. {$comment}"),
            'actionUrl' => "/audit-engagement-management/working-papers?engagementId={$engagement->id}",
            'actionLabel' => 'Open Working Paper',
            'subjectType' => WorkingPaper::class,
            'subjectId' => $paper->id,
            'subjectCode' => $paper->working_paper_code,
            'dedupeKey' => "aems:working-paper:{$paper->id}:v{$versionNumber}:returned",
            'metadata' => [
                'engagementId' => $engagement->id,
                'versionNumber' => $versionNumber,
            ],
        ]));
    }

    public function findingCommunicated(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
    ): void {
        $recipientIds = $this->auditeeRepresentatives(
            collect([$finding->responsible_office_id])->filter(),
        );
        $actorId = $request->user()->id;
        $dueDate = $finding->management_response_due_date?->format('M j, Y');

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_FINDING_COMMUNICATED',
            'category' => 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$finding->finding_code}: Finding communicated",
            'message' => "{$finding->title} was formally communicated"
                .($dueDate ? "; management's response is due {$dueDate}." : '.'),
            'actionUrl' => "/audit-engagement-management/auditee-responses?engagementId={$engagement->id}&findingId={$finding->id}",
            'actionLabel' => 'Open Auditee Response',
            'subjectType' => AuditFinding::class,
            'subjectId' => $finding->id,
            'subjectCode' => $finding->finding_code,
            'dedupeKey' => "aems:finding:{$finding->id}:communicated:{$finding->lock_version}",
            'metadata' => [
                'engagementId' => $engagement->id,
                'responsibleOfficeId' => $finding->responsible_office_id,
                'responseDueDate' => $finding->management_response_due_date?->toDateString(),
            ],
        ]));
    }

    public function issueDisposition(
        Request $request,
        AuditEngagement $engagement,
        AuditIssue $issue,
        string $action,
    ): void {
        $recipientIds = $this->reviewers($engagement, 'aems.issue.view')
            ->merge([$issue->raised_by])
            ->filter()
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->unique()->values();
        $actorId = $request->user()->id;
        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_ISSUE_DISPOSITION_'.strtoupper($action),
            'category' => 'WORKFLOW',
            'priority' => 'NORMAL',
            'moduleCode' => 'AEMS',
            'title' => "{$issue->issue_code}: issue {$action}",
            'message' => "Issue {$issue->issue_code} for {$engagement->title} received the {$issue->disposition} disposition.",
            'actionUrl' => "/audit-engagement-management/issues?engagementId={$engagement->id}",
            'actionLabel' => 'Open Audit Issues',
            'subjectType' => AuditIssue::class,
            'subjectId' => $issue->id,
            'subjectCode' => $issue->issue_code,
            'dedupeKey' => "aems:issue:{$issue->id}:disposition:{$issue->lock_version}",
            'metadata' => ['engagementId' => $engagement->id, 'disposition' => $issue->disposition],
        ]));
    }

    public function findingRevision(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
    ): void {
        $recipientIds = $this->reviewers($engagement, 'aems.finding.view')
            ->merge([$finding->authored_by])
            ->filter()
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->unique()->values();
        $actorId = $request->user()->id;
        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_FINDING_REVISION_CREATED',
            'category' => 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$finding->finding_code}: finding revision created",
            'message' => "A {$finding->revision_type} revision was created for {$engagement->title} and requires controlled review.",
            'actionUrl' => "/audit-engagement-management/findings?engagementId={$engagement->id}&findingId={$finding->id}",
            'actionLabel' => 'Open Findings',
            'subjectType' => AuditFinding::class,
            'subjectId' => $finding->id,
            'subjectCode' => $finding->finding_code,
            'dedupeKey' => "aems:finding:{$finding->id}:revision:{$finding->revision_number}",
            'metadata' => ['engagementId' => $engagement->id, 'revisionNumber' => $finding->revision_number, 'revisionType' => $finding->revision_type],
        ]));
    }

    public function exitConferenceScheduled(
        Request $request,
        AuditEngagement $engagement,
        ExitConference $conference,
        string $action,
    ): void {
        $conference->loadMissing('participants');
        $recipientIds = $conference->participants->pluck('user_id')->filter()
            ->merge($engagement->teamMembers()
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->pluck('user_id'))
            ->merge($this->auditeeRepresentatives(
                $conference->participants->pluck('office_id')->filter()
                    ->merge($engagement->offices()->pluck('offices.id')),
            ))
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $actorId = $request->user()->id;
        $rescheduled = $action === 'RESCHEDULED';

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => $rescheduled
                ? 'AEMS_EXIT_CONFERENCE_RESCHEDULED'
                : 'AEMS_EXIT_CONFERENCE_SCHEDULED',
            'category' => 'DUE_DATE',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$conference->conference_code}: Exit Conference "
                .($rescheduled ? 'rescheduled' : 'scheduled'),
            'message' => "The Exit Conference for {$engagement->title} is scheduled for "
                .$conference->scheduled_start_at?->format('M j, Y g:i A').'.',
            'actionUrl' => "/compliance-management/entry-conference-acknowledgements?engagementId={$engagement->id}",
            'actionLabel' => 'Review Entry Conference notes',
            'subjectType' => ExitConference::class,
            'subjectId' => $conference->id,
            'subjectCode' => $conference->conference_code,
            'dedupeKey' => "aems:conference:{$conference->id}:"
                .$conference->scheduled_start_at?->format('YmdHi'),
            'metadata' => [
                'engagementId' => $engagement->id,
                'scheduledStartAt' => $conference->scheduled_start_at?->toISOString(),
            ],
        ]));
    }

    public function reportIssued(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
    ): void {
        $version->loadMissing('recipients');
        $userIds = $version->recipients->pluck('user_id')->filter();
        $officeIds = $version->recipients->pluck('office_id')->filter();
        if ($officeIds->isNotEmpty()) {
            $officeRecipients = User::query()
                ->whereIn('office_id', $officeIds)
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query
                        ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'access.auditee_scope'))
                        ->orWhereHas('role.permissions', fn ($permission) => $permission->where('code', 'access.auditee_scope'));
                })
                ->pluck('id');
            $userIds = $userIds->merge($officeRecipients);
        }
        $recipientIds = $userIds->map(fn ($id): int => (int) $id)->unique()->values();
        $actorId = $request->user()->id;

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_REPORT_ISSUED',
            'category' => 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$report->report_code}: Final Report issued",
            'message' => "The Final Audit Report for {$engagement->title} has been issued.",
            'actionUrl' => "/audit-engagement-management/reports?engagementId={$engagement->id}",
            'actionLabel' => 'Open Audit Report',
            'subjectType' => AuditReport::class,
            'subjectId' => $report->id,
            'subjectCode' => $report->report_code,
            'dedupeKey' => "aems:report:{$report->id}:issued:v{$version->version_number}",
            'metadata' => [
                'engagementId' => $engagement->id,
                'reportVersionId' => $version->id,
                'reportVersionNumber' => $version->version_number,
            ],
        ]));
    }

    public function engagementTransition(
        Request $request,
        AuditEngagement $engagement,
        string $action,
        string $fromStatus,
        string $toStatus,
    ): void {
        $recipientIds = $engagement->teamMembers()
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->pluck('user_id')
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $actorId = $request->user()->id;

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_ENGAGEMENT_'.strtoupper($action),
            'category' => 'WORKFLOW',
            'priority' => in_array($action, ['SUSPEND', 'CANCEL'], true) ? 'URGENT' : 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$engagement->engagement_code}: "
                .str($toStatus)->replace('_', ' ')->title(),
            'message' => "{$engagement->title} moved from "
                .str($fromStatus)->replace('_', ' ')->title().' to '
                .str($toStatus)->replace('_', ' ')->title().'.',
            'actionUrl' => "/audit-engagement-management/{$engagement->id}?tab=lifecycle",
            'actionLabel' => 'Open Engagement Lifecycle',
            'subjectType' => AuditEngagement::class,
            'subjectId' => $engagement->id,
            'subjectCode' => $engagement->engagement_code,
            'dedupeKey' => "aems:engagement:{$engagement->id}:{$action}:{$engagement->lock_version}",
            'metadata' => [
                'engagementId' => $engagement->id,
                'fromStatus' => $fromStatus,
                'toStatus' => $toStatus,
            ],
        ]));
    }

    public function aeoDistributed(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementOrder $order,
        AemsAeoDistribution $distribution,
    ): void {
        $recipientIds = $distribution->recipient_user_id
            ? collect([$distribution->recipient_user_id])
            : $this->auditeeRepresentatives(collect([$distribution->recipient_office_id])->filter());
        $recipientIds = $recipientIds
            ->filter()
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $actorId = $request->user()->id;

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_AEO_DISTRIBUTED',
            'category' => 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$order->order_code}: issued AEO available for acknowledgement",
            'message' => "The issued Audit Engagement Order for {$engagement->title} was transmitted to your account or office.",
            'actionUrl' => "/compliance-management/aeo-acknowledgements?distributionId={$distribution->id}",
            'actionLabel' => 'Acknowledge issued AEO',
            'subjectType' => AemsAeoDistribution::class,
            'subjectId' => $distribution->id,
            'subjectCode' => $order->order_code,
            'dedupeKey' => "aems:aeo:distribution:{$distribution->id}:sent",
            'metadata' => [
                'engagementId' => $engagement->id,
                'orderId' => $order->id,
                'distributionId' => $distribution->id,
                'versionNumber' => $distribution->version_number,
            ],
        ]));
    }

    public function entryConference(
        Request $request,
        AuditEngagement $engagement,
        EntryConference $conference,
        string $action,
    ): void {
        $conference->loadMissing('participants');
        $recipientIds = $conference->participants->pluck('user_id')->filter()
            ->merge($this->auditeeRepresentatives(
                $conference->participants->pluck('office_id')->filter(),
            ))
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $actorId = $request->user()->id;

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_ENTRY_CONFERENCE_'.strtoupper($action),
            'category' => $action === 'SCHEDULE' || $action === 'RESCHEDULE'
                ? 'DUE_DATE'
                : 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$conference->conference_code}: Entry Conference "
                .str($action)->replace('_', ' ')->lower(),
            'message' => "The Entry Conference for {$engagement->title} was "
                .str($action)->replace('_', ' ')->lower().'.',
            'actionUrl' => "/audit-engagement-management/conferences?engagementId={$engagement->id}",
            'actionLabel' => 'Open Conference Management',
            'subjectType' => EntryConference::class,
            'subjectId' => $conference->id,
            'subjectCode' => $conference->conference_code,
            'dedupeKey' => "aems:entry-conference:{$conference->id}:{$action}:{$conference->lock_version}",
            'metadata' => [
                'engagementId' => $engagement->id,
                'conferenceStatus' => $conference->status,
            ],
        ]));
    }

    public function completionAssessment(
        Request $request,
        AuditEngagement $engagement,
        CompletionAssessment $assessment,
        string $action,
    ): void {
        if (! in_array($action, ['SUBMIT', 'RESUBMIT', 'RETURN', 'APPROVE'], true)) {
            return;
        }
        $recipientIds = in_array($action, ['SUBMIT', 'RESUBMIT'], true)
            ? $this->reviewers($engagement, 'aems.completion-assessment.review')
            : collect([$assessment->prepared_by, $assessment->submitted_by])->filter();
        $recipientIds = $recipientIds
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->unique()->values();
        $actorId = $request->user()->id;
        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_COMPLETION_ASSESSMENT_'.$action,
            'category' => 'WORKFLOW',
            'priority' => in_array($action, ['SUBMIT', 'RESUBMIT', 'RETURN'], true) ? 'HIGH' : 'NORMAL',
            'moduleCode' => 'AEMS',
            'title' => "{$assessment->assessment_code}: Completion Assessment "
                .str($action)->replace('_', ' ')->lower(),
            'message' => "The Completion Assessment for {$engagement->title} was "
                .str($action)->replace('_', ' ')->lower().'.',
            'actionUrl' => "/audit-engagement-management/{$engagement->id}?tab=completion-assessment",
            'actionLabel' => 'Open Completion Assessment',
            'subjectType' => CompletionAssessment::class,
            'subjectId' => $assessment->id,
            'subjectCode' => $assessment->assessment_code,
            'dedupeKey' => "aems:completion-assessment:{$assessment->id}:{$action}:{$assessment->lock_version}",
            'metadata' => ['engagementId' => $engagement->id, 'workflowAction' => $action],
        ]));
    }

    public function closure(
        Request $request,
        AuditEngagement $engagement,
        EngagementClosure $closure,
        string $action,
    ): void {
        if (! in_array($action, [
            'SUBMIT_CLOSURE',
            'RESUBMIT_CLOSURE',
            'RETURN_CLOSURE',
            'APPROVE_CLOSURE',
            'CLOSE_ENGAGEMENT',
        ], true)) {
            return;
        }
        $recipientIds = in_array($action, ['SUBMIT_CLOSURE', 'RESUBMIT_CLOSURE'], true)
            ? $this->reviewers($engagement, 'aems.closure.review')
            : $engagement->teamMembers()
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->pluck('user_id');
        $recipientIds = $recipientIds
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->unique()->values();
        $actorId = $request->user()->id;
        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_'.$action,
            'category' => 'WORKFLOW',
            'priority' => $action === 'CLOSE_ENGAGEMENT' ? 'HIGH' : 'NORMAL',
            'moduleCode' => 'AEMS',
            'title' => "{$closure->closure_code}: "
                .str($action)->replace('_', ' ')->title(),
            'message' => "The formal Closure for {$engagement->title} was "
                .str($action)->replace('_', ' ')->lower().'.',
            'actionUrl' => "/audit-engagement-management/{$engagement->id}?tab=closure",
            'actionLabel' => 'Open Engagement Closure',
            'subjectType' => EngagementClosure::class,
            'subjectId' => $closure->id,
            'subjectCode' => $closure->closure_code,
            'dedupeKey' => "aems:closure:{$closure->id}:{$action}:{$closure->lock_version}",
            'metadata' => ['engagementId' => $engagement->id, 'workflowAction' => $action],
        ]));
    }

    /** @param  array<int, array<string, mixed>>  $checklist */
    public function closureRecordsBlockers(
        Request $request,
        AuditEngagement $engagement,
        EngagementClosure $closure,
        array $checklist,
    ): void {
        $blockers = collect($checklist)
            ->filter(fn (array $item): bool => in_array(
                $item['checklistCode'] ?? null,
                ['DOCUMENT_INDEX', 'RETENTION_METADATA'],
                true,
            ) && ($item['resultCode'] ?? null) !== 'PASS');
        if ($blockers->isEmpty()) {
            return;
        }
        $recipientIds = $this->reviewers($engagement, 'aems.closure.update')
            ->merge($engagement->teamMembers()
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->pluck('user_id'))
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $codes = $blockers->pluck('checklistCode')->sort()->implode(':');
        $labels = $blockers->pluck('description')->implode('; ');
        $actorId = $request->user()->id;

        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_CLOSURE_RECORDS_BLOCKED',
            'category' => 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$closure->closure_code}: records action required",
            'message' => "Closure records require action: {$labels}",
            'actionUrl' => "/audit-engagement-management/{$engagement->id}?tab=closure",
            'actionLabel' => 'Resolve Closure Blockers',
            'subjectType' => EngagementClosure::class,
            'subjectId' => $closure->id,
            'subjectCode' => $closure->closure_code,
            'dedupeKey' => "aems:closure:{$closure->id}:records-blocked:{$codes}:{$closure->lock_version}",
            'metadata' => [
                'engagementId' => $engagement->id,
                'blockerCodes' => $blockers->pluck('checklistCode')->values()->all(),
            ],
        ]));
    }

    /** @param array<string, mixed> $summary */
    public function completionTransfer(
        Request $request,
        AuditEngagement $engagement,
        string $action,
        array $summary,
    ): void {
        $recipientIds = $this->reviewers($engagement, 'aems.completion-transfer.view')
            ->merge($engagement->teamMembers()->where('is_active', true)->whereNull('ended_at')->pluck('user_id'))
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->unique()->values();
        $actorId = $request->user()->id;
        $status = $summary['manifest']['status'] ?? $summary['effortReconciliation']['status'] ?? 'UPDATED';
        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_COMPLETION_TRANSFER_'.strtoupper($action),
            'category' => 'WORKFLOW',
            'priority' => $status === 'EXCEPTION' ? 'HIGH' : 'NORMAL',
            'moduleCode' => 'AEMS',
            'title' => "{$engagement->engagement_code}: completion transfer {$action}",
            'message' => "Completion transfer reconciliation for {$engagement->title} is {$status}.",
            'actionUrl' => "/audit-engagement-management/{$engagement->id}?tab=completion-transfer",
            'actionLabel' => 'Open Completion & Transfer',
            'subjectType' => AuditEngagement::class,
            'subjectId' => $engagement->id,
            'subjectCode' => $engagement->engagement_code,
            'dedupeKey' => "aems:completion-transfer:{$engagement->id}:{$action}:".($summary['manifest']['id'] ?? $summary['effortReconciliation']['id'] ?? 'none'),
            'metadata' => ['engagementId' => $engagement->id, 'status' => $status],
        ]));
    }

    public function reopen(
        Request $request,
        AuditEngagement $engagement,
        EngagementReopenRequest $reopen,
        string $action,
    ): void {
        $recipientIds = $action === 'SUBMIT_REOPEN_REQUEST'
            ? $this->reviewers($engagement, 'aems.engagement.reopen_approve')
            : $engagement->teamMembers()
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->pluck('user_id')
                ->push($reopen->requested_by);
        $recipientIds = $recipientIds
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->unique()->values();
        $actorId = $request->user()->id;
        DB::afterCommit(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $actorId,
            'type' => 'AEMS_'.$action,
            'category' => 'WORKFLOW',
            'priority' => 'HIGH',
            'moduleCode' => 'AEMS',
            'title' => "{$reopen->request_code}: "
                .str($action)->replace('_', ' ')->title(),
            'message' => "The exceptional reopening request for {$engagement->title} was "
                .str($action)->replace('_', ' ')->lower().'.',
            'actionUrl' => "/audit-engagement-management/{$engagement->id}?tab=closure",
            'actionLabel' => 'Open Reopening Request',
            'subjectType' => EngagementReopenRequest::class,
            'subjectId' => $reopen->id,
            'subjectCode' => $reopen->request_code,
            'dedupeKey' => "aems:reopen:{$reopen->id}:{$action}:{$reopen->lock_version}",
            'metadata' => ['engagementId' => $engagement->id, 'workflowAction' => $action],
        ]));
    }

    /** @return Collection<int, int> */
    private function reviewers(
        AuditEngagement $engagement,
        string $permission,
    ): Collection {
        $assignedIds = $engagement->teamMembers()
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->pluck('user_id');

        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($assignedIds): void {
                $query
                    ->whereIn('id', $assignedIds)
                    ->orWhereHas('roles.permissions', fn ($permission) => $permission->where('code', 'aems.team.safeguard_review'));
            })
            ->with(['role.permissions', 'roles.permissions'])
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission($permission))
            ->pluck('id');
    }

    /** @return Collection<int, int> */
    private function auditeeRepresentatives(Collection $officeIds): Collection
    {
        if ($officeIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('office_id', $officeIds)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query
                    ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'access.auditee_scope'))
                    ->orWhereHas('role.permissions', fn ($permission) => $permission->where('code', 'access.auditee_scope'));
            })
            ->pluck('id');
    }
}
