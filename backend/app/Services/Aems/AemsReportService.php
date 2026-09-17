<?php

namespace App\Services;

use App\Contracts\Aems\CmsRecommendationGateway;
use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditRecommendation;
use App\Models\AuditReport;
use App\Models\AuditReportReviewComment;
use App\Models\AuditReportVersion;
use App\Models\AuditReportDistributionDecision;
use App\Models\AuditIssue;
use App\Models\AuditEvidence;
use App\Models\WorkingPaperVersion;
use App\Models\AemsReportAuthorityDecision;
use App\Models\AemsReportSignatory;
use App\Models\AemsReportTransmittal;
use App\Models\AemsReportExport;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\ExitConference;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\ReportRecipient;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Implements immutable draft/final audit report generation and issuance. */
class AemsReportService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly RuntimeConfiguration $runtime,
        private readonly CmsRecommendationGateway $cms,
        private readonly AemsNotificationService $notifications,
        private readonly AemsEngagementTransitionService $engagementTransitions,
    ) {}

    /** @return list<array<string, mixed>> */
    public function engagements(Request $request): array
    {
        $user = $request->user();
        $query = $user->hasPermission('aems.report.view')
            ? AuditEngagement::query()->visibleTo($user)
            : AuditEngagement::query()->whereHas(
                'reports',
                fn ($reports) => $reports->visibleTo($user),
            );

        return $query->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->get(['id', 'engagement_code', 'title', 'status'])
            ->map(fn (AuditEngagement $engagement): array => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
            ])->all();
    }

    /** @return array<string, mixed> */
    public function workspace(Request $request, AuditEngagement $engagement): array
    {
        $user = $request->user();
        $canViewInternal = $user->hasPermission('aems.report.view');
        if ($canViewInternal) {
            $this->access->authorizeEngagementAction(
                $user,
                $engagement,
                'aems.report.view',
            );
            $report = AuditReport::query()
                ->where('audit_engagement_id', $engagement->id)
                ->where('is_active', true)
                ->first();
        } else {
            $report = AuditReport::query()
                ->visibleTo($user)
                ->where('audit_engagement_id', $engagement->id)
                ->where('is_active', true)
                ->first();
            abort_unless($report, 403, 'No issued report from this engagement is visible to you.');
        }

        $findings = $canViewInternal
            ? AuditFinding::query()
                ->where('audit_engagement_id', $engagement->id)
                ->where('is_current_revision', true)
                ->whereIn('status', [
                    'VALIDATED',
                    'COMMUNICATED',
                    'AWAITING_MANAGEMENT_RESPONSE',
                    'UNDER_DIALOGUE',
                    'FINALIZED',
                ])
                ->with(['responsibleOffice', 'riskRating', 'recommendations.responsibleOffice'])
                ->orderBy('finding_code')
                ->get()
                ->map(fn (AuditFinding $finding): array => $this->findingReference($finding))
                ->all()
            : [];

        $officeIds = $engagement->offices()->pluck('offices.id');
        $teamUserIds = $engagement->teamMembers()
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->pluck('user_id');

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
            ],
            'report' => $report
                ? $this->reportData($this->load($report), $user)
                : null,
            'references' => [
                'findings' => $findings,
                'issues' => $canViewInternal
                    ? AuditIssue::query()->where('audit_engagement_id', $engagement->id)
                        ->whereNull('deleted_at')->whereIn('status', ['DRAFT', 'SUBMITTED', 'VALIDATED', 'DISMISSED', 'CONVERTED_TO_FINDING', 'WITHDRAWN'])
                        ->orderBy('issue_code')->get(['id', 'issue_code', 'title', 'status', 'disposition'])
                        ->map(fn (AuditIssue $issue): array => ['id' => $issue->id, 'issueCode' => $issue->issue_code, 'title' => $issue->title, 'status' => $issue->status, 'disposition' => $issue->disposition])->all()
                    : [],
                'workingPaperVersions' => $canViewInternal
                    ? WorkingPaperVersion::query()->whereHas('workingPaper', fn ($query) => $query->where('audit_engagement_id', $engagement->id)->where('status', 'APPROVED'))
                        ->with('workingPaper')->orderBy('working_paper_id')->orderBy('version_number')->get()
                        ->map(fn (WorkingPaperVersion $version): array => ['id' => $version->id, 'workingPaperId' => $version->working_paper_id, 'code' => $version->workingPaper?->working_paper_code, 'versionNumber' => $version->version_number, 'checksum' => $version->checksum_sha256])->all()
                    : [],
                'evidence' => $canViewInternal
                    ? AuditEvidence::query()->where('audit_engagement_id', $engagement->id)->where('is_current_revision', true)->whereNull('deleted_at')
                        ->orderBy('evidence_code')->get(['id', 'evidence_code', 'title', 'outcome', 'document_version_id', 'checksum_sha256'])
                        ->map(fn (AuditEvidence $evidence): array => ['id' => $evidence->id, 'evidenceCode' => $evidence->evidence_code, 'title' => $evidence->title, 'outcome' => $evidence->outcome, 'documentVersionId' => $evidence->document_version_id, 'checksum' => $evidence->checksum_sha256])->all()
                    : [],
                'confidentialityLevels' => $canViewInternal
                    ? $this->masterItems('DOCUMENT_CONFIDENTIALITY')
                    : [],
                'users' => $canViewInternal
                    ? User::query()
                        ->where('is_active', true)
                        ->where(function ($query) use ($officeIds, $teamUserIds, $user): void {
                            $query->whereIn('office_id', $officeIds)
                                ->orWhereIn('id', $teamUserIds)
                                ->orWhere('id', $user->id);
                        })
                        ->orderBy('name')
                        ->get()
                        ->map(fn (User $member): array => $this->userData($member))
                        ->all()
                    : [],
                'offices' => $canViewInternal
                    ? Office::query()
                        ->whereIn('id', $officeIds)
                        ->orderBy('name')
                        ->get()
                        ->map(fn (Office $office): array => $this->officeData($office))
                        ->all()
                    : [],
            ],
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function createDraft(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
        string $stage = 'DRAFT_REPORT',
    ): AuditReport {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.report.create',
        );
        if (! in_array($engagement->status, ['FINDINGS_COMMUNICATION', 'REPORTING'], true)) {
            throw ValidationException::withMessages([
                'engagement' => ['Reports can be prepared during findings communication or reporting.'],
            ]);
        }
        if (AuditReport::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'report' => ['This engagement already has an active report family.'],
            ]);
        }
        $findings = $this->validatedFindings(
            $engagement,
            $attributes['findingIds'],
            false,
        );
        $this->ensureConfidentiality($attributes['confidentialityLevelId']);

        return DB::transaction(function () use (
            $request,
            $engagement,
            $attributes,
            $findings,
            $stage,
        ): AuditReport {
            $report = AuditReport::query()->create([
                'audit_engagement_id' => $engagement->id,
                'report_code' => $this->nextCode($engagement),
                'title' => trim($attributes['title']),
                'report_stage' => $stage,
                'status' => 'DRAFT',
                'current_version_number' => 0,
                'confidentiality_level_id' => $attributes['confidentialityLevelId'],
                'prepared_by' => $request->user()->id,
                'lock_version' => 1,
                'is_active' => true,
            ]);
            $document = $this->createDocument($request, $report);
            $report->forceFill(['document_id' => $document->id])->save();
            $version = $this->createVersion(
                $request,
                $engagement,
                $report,
                $stage,
                $attributes,
                $findings,
                [],
            );
            $report->forceFill([
                'current_version_number' => $version->version_number,
                'current_version_id' => $version->id,
                'lock_version' => $report->lock_version + 1,
            ])->save();
            $report = $this->load($report);
            $this->record($request, $engagement, $report, 'aems.report.created', null, 'DRAFT');

            return $report;
        });
    }

    /** Create an Interim Report using the same immutable assembly contract. */
    public function createInterim(Request $request, AuditEngagement $engagement, array $attributes): AuditReport
    {
        return $this->createDraft($request, $engagement, $attributes, 'INTERIM_REPORT');
    }

    /** @param array<string, mixed> $attributes */
    public function revise(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        array $attributes,
    ): AuditReport {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.report.create',
        );
        $findings = $this->validatedFindings(
            $engagement,
            $attributes['findingIds'],
            $report->report_stage === 'FINAL_REPORT',
        );
        $this->ensureConfidentiality($attributes['confidentialityLevelId']);

        return DB::transaction(function () use (
            $request,
            $engagement,
            $report,
            $attributes,
            $findings,
        ): AuditReport {
            $report = $this->lock($engagement, $report, $attributes['lockVersion']);
            if (! in_array($report->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
                throw ValidationException::withMessages([
                    'report' => ['A new version can only be generated from Draft or Returned status.'],
                ]);
            }
            if ((int) $report->prepared_by !== (int) $request->user()->id
                && ! $request->user()->hasPermission('aems.report.manage')) {
                throw ValidationException::withMessages([
                    'report' => ['Only the report preparer can generate its revision.'],
                ]);
            }
            $old = $this->reportAudit($report);
            $recipients = $report->report_stage === 'FINAL_REPORT'
                ? $this->validateRecipients($engagement, $attributes['recipients'])
                : [];
            $report->fill([
                'title' => trim($attributes['title']),
                'confidentiality_level_id' => $attributes['confidentialityLevelId'],
                'approving_authority' => $report->report_stage === 'FINAL_REPORT'
                    ? trim($attributes['approvingAuthority'])
                    : null,
                'status' => $report->status === 'RETURNED_FOR_REVISION'
                    ? 'RESUBMITTED'
                    : 'DRAFT',
                'lock_version' => $report->lock_version + 1,
            ])->save();
            $version = $this->createVersion(
                $request,
                $engagement,
                $report,
                $report->report_stage,
                $attributes,
                $findings,
                $recipients,
            );
            $report->forceFill([
                'current_version_number' => $version->version_number,
                'current_version_id' => $version->id,
            ])->save();
            $report = $this->load($report);
            $this->record(
                $request,
                $engagement,
                $report,
                'aems.report.version_generated',
                $old['status'],
                $report->status,
                $old,
                $attributes['changeReason'],
                [$version->document_version_id],
            );

            return $report;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function createFinal(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        array $attributes,
    ): AuditReport {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.report.create',
        );
        $findings = $this->validatedFindings($engagement, $attributes['findingIds'], true);
        $this->ensureConfidentiality($attributes['confidentialityLevelId']);
        $recipients = $this->validateRecipients($engagement, $attributes['recipients']);
        $attributes['sourceInterimReportVersionId'] ??= $report->current_version_id;
        $attributes['interimTreatment'] ??= 'RETAINED_WITH_REVIEW';

        return DB::transaction(function () use (
            $request,
            $engagement,
            $report,
            $attributes,
            $findings,
            $recipients,
        ): AuditReport {
            $report = $this->lock($engagement, $report, $attributes['lockVersion']);
            if (! in_array($report->report_stage, ['INTERIM_REPORT', 'DRAFT_REPORT'], true)
                || $report->status !== 'APPROVED') {
                throw ValidationException::withMessages([
                    'report' => ['The approved Draft Report is required before generating the Final Report.'],
                ]);
            }
            $old = $this->reportAudit($report);
            $report->fill([
                'title' => trim($attributes['title']),
                'report_stage' => 'FINAL_REPORT',
                'status' => 'DRAFT',
                'confidentiality_level_id' => $attributes['confidentialityLevelId'],
                'approving_authority' => trim($attributes['approvingAuthority']),
                'submitted_at' => null,
                'submitted_by' => null,
                'approved_at' => null,
                'approved_by' => null,
                'lock_version' => $report->lock_version + 1,
            ])->save();
            $version = $this->createVersion(
                $request,
                $engagement,
                $report,
                'FINAL_REPORT',
                $attributes,
                $findings,
                $recipients,
            );
            $report->forceFill([
                'current_version_number' => $version->version_number,
                'current_version_id' => $version->id,
            ])->save();
            $report = $this->load($report);
            $this->record(
                $request,
                $engagement,
                $report,
                'aems.report.final_generated',
                $old['status'],
                'DRAFT',
                $old,
                $attributes['changeReason'],
                [$version->document_version_id],
            );

            return $report;
        });
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        string $action,
        int $lockVersion,
        ?string $comment,
        ?string $issuanceDate,
    ): AuditReport {
        return DB::transaction(function () use (
            $request,
            $engagement,
            $report,
            $action,
            $lockVersion,
            $comment,
            $issuanceDate,
        ): AuditReport {
            $report = $this->lock($engagement, $report, $lockVersion);
            $old = $this->reportAudit($report);
            $version = $report->currentVersion()->with([
                'findings.recommendations',
                'recipients',
            ])->firstOrFail();

            if ($action === 'SUBMIT') {
                $this->access->authorizeEngagementAction(
                    $request->user(),
                    $engagement,
                    'aems.report.create',
                );
                if (! in_array($report->status, ['DRAFT', 'RESUBMITTED'], true)) {
                    $this->invalidTransition($action, $report);
                }
                $report->fill([
                    'status' => 'PENDING_REVIEW',
                    'submitted_at' => now(),
                    'submitted_by' => $request->user()->id,
                    'lock_version' => $report->lock_version + 1,
                ])->save();
            } elseif ($action === 'RETURN') {
                $this->access->authorizeEngagementAction(
                    $request->user(),
                    $engagement,
                    'aems.report.review',
                    $report->prepared_by,
                );
                if ($report->status !== 'PENDING_REVIEW' || ! $comment) {
                    $this->invalidTransition($action, $report);
                }
                $this->reviewComment($request, $report, $version, 'RETURNED', $comment);
                $report->fill([
                    'status' => 'RETURNED_FOR_REVISION',
                    'lock_version' => $report->lock_version + 1,
                ])->save();
            } elseif ($action === 'APPROVE') {
                $this->access->authorizeEngagementAction(
                    $request->user(),
                    $engagement,
                    'aems.report.approve',
                    $report->prepared_by,
                );
                if (! in_array($report->status, ['PENDING_REVIEW', 'RESUBMITTED'], true)) {
                    $this->invalidTransition($action, $report);
                }
                if ($report->report_stage === 'FINAL_REPORT') {
                    $this->validateFinalApproval($engagement, $report, $version);
                }
                $this->reviewComment(
                    $request,
                    $report,
                    $version,
                    'APPROVED',
                    $comment ?: 'Approved for the next controlled stage.',
                );
                $report->fill([
                    'status' => 'APPROVED',
                    'approved_at' => now(),
                    'approved_by' => $request->user()->id,
                    'lock_version' => $report->lock_version + 1,
                ])->save();
                if ($report->report_stage === 'FINAL_REPORT') {
                    $this->ensureDefaultAuthorityDecisions($report, $version, $request->user()->id);
                }
            } elseif ($action === 'ISSUE') {
                $this->access->authorizeEngagementAction(
                    $request->user(),
                    $engagement,
                    'aems.report.issue',
                    $report->prepared_by,
                );
                if ($report->report_stage !== 'FINAL_REPORT' || $report->status !== 'APPROVED') {
                    $this->invalidTransition($action, $report);
                }
                $this->validateFinalApproval($engagement, $report, $version);
                $this->ensureAuthorityMatrix($report, $version);
                $this->ensureSignatoryMatrix($report, $version, $request->user()->id);
                $issuedAt = $issuanceDate ? Carbon::parse($issuanceDate) : now();
                $version->recipients()->update([
                    'delivery_status' => 'SENT',
                    'sent_at' => $issuedAt,
                    'delivered_at' => $issuedAt,
                    'updated_at' => now(),
                ]);
                $version->forceFill([
                    'is_locked' => true,
                    'locked_at' => now(),
                    'locked_by' => $request->user()->id,
                ])->save();
                $report->fill([
                    'status' => 'ISSUED',
                    'issued_at' => $issuedAt,
                    'issued_by' => $request->user()->id,
                    'lock_version' => $report->lock_version + 1,
                ])->save();
                $this->ensureTransmittal($report, $version, $request->user()->id, $issuedAt);
                $this->transferRecommendations(
                    $request,
                    $engagement,
                    $report,
                    $version,
                    false,
                );
                $this->engagementTransitions->synchronizeIssuedReport($request, $engagement);
                $this->notifications->reportIssued(
                    $request,
                    $engagement,
                    $report,
                    $version,
                );
            } else {
                $this->invalidTransition($action, $report);
            }

            $report = $this->load($report);
            $this->record(
                $request,
                $engagement,
                $report,
                'aems.report.'.strtolower($action),
                $old['status'],
                $report->status,
                $old,
                $comment,
                [$version->document_version_id],
            );
            if (in_array($action, ['SUBMIT', 'RETURN', 'APPROVE'], true)) {
                $this->notifications->controlledDocumentTransition(
                    $request,
                    $engagement,
                    'REPORT',
                    $report->id,
                    $report->report_code,
                    $report->report_stage === 'FINAL_REPORT'
                        ? 'Final Audit Report'
                        : 'Draft Audit Report',
                    $action,
                    $version->version_number,
                    $report->prepared_by,
                    $report->submitted_by,
                    'aems.report.review',
                    "/audit-engagement-management/reports?engagementId={$engagement->id}",
                );
            }

            return $report;
        });
    }

    /** @return list<array<string, mixed>> */
    public function retryCmsTransfer(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        int $lockVersion,
    ): array {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.report.issue',
            $report->prepared_by,
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $report,
            $lockVersion,
        ): array {
            $report = $this->lock($engagement, $report, $lockVersion);
            if ($report->report_stage !== 'FINAL_REPORT' || $report->status !== 'ISSUED') {
                throw ValidationException::withMessages([
                    'report' => ['Recommendations transfer only from an issued Final Report.'],
                ]);
            }
            $version = AuditReportVersion::query()
                ->lockForUpdate()
                ->with('findings.recommendations')
                ->findOrFail($report->current_version_id);

            return $this->transferRecommendations(
                $request,
                $engagement,
                $report,
                $version,
                true,
            );
        });
    }

    public function download(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
    ): DocumentVersion {
        $this->access->authorizeReportView($request->user(), $report);
        $this->ensureReport($engagement, $report);
        if ((int) $version->audit_report_id !== (int) $report->id) {
            throw ValidationException::withMessages([
                'version' => ['The report version does not belong to this report.'],
            ]);
        }
        $isInternal = $request->user()->hasPermission('aems.report.view');
        if (! $isInternal && (int) $version->id !== (int) $report->current_version_id) {
            abort(403, 'Recipients can download only the issued report version.');
        }
        $this->authorizeConfidentiality($request->user(), $report, $version);
        $documentVersion = $version->documentVersion;
        if (! $documentVersion || ! Storage::disk('local')->exists($documentVersion->storage_path)) {
            abort(404, 'The generated report file is unavailable.');
        }
        $this->record(
            $request,
            $engagement,
            $report,
            'aems.report.downloaded',
            $report->status,
            $report->status,
            null,
            "Version {$version->version_number}",
            [$documentVersion->id],
        );

        return $documentVersion;
    }

    /** Record an append-only delivery/acknowledgement decision for a recipient. */
    public function distributionDecision(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
        ReportRecipient $recipient,
        string $decision,
        ?string $comment,
    ): AuditReportDistributionDecision {
        $this->ensureReport($engagement, $report);
        abort_unless((int) $version->audit_report_id === (int) $report->id, 422, 'The report version does not belong to this report.');
        abort_unless((int) $recipient->audit_report_version_id === (int) $version->id, 422, 'The recipient does not belong to this report version.');
        abort_unless($report->status === 'ISSUED' && $version->is_locked, 422, 'Distribution decisions require an issued report version.');

        $user = $request->user();
        $isRecipient = ((int) $recipient->user_id === (int) $user->id)
            || ($recipient->office_id && (int) $recipient->office_id === (int) $user->office_id);
        if ($decision === 'ACKNOWLEDGED') {
            abort_unless($isRecipient && $user->hasPermission('aems.report.acknowledge'), 403, 'Only an authorized recipient may acknowledge this report.');
        } else {
            $this->access->authorizeEngagementAction($user, $engagement, 'aems.report.distribute', $report->prepared_by);
        }

        return DB::transaction(function () use ($request, $engagement, $report, $version, $recipient, $decision, $comment): AuditReportDistributionDecision {
            $decisionRecord = AuditReportDistributionDecision::query()->create([
                'audit_report_id' => $report->id,
                'audit_report_version_id' => $version->id,
                'report_recipient_id' => $recipient->id,
                'decision_code' => $decision,
                'comment' => $comment,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);
            $this->support->event($request, $engagement, 'aems.report.distribution_'.$decision, 'ISSUED', 'ISSUED', null, [
                'reportId' => $report->id, 'versionId' => $version->id, 'recipientId' => $recipient->id,
                'decision' => $decision,
            ], $comment, 'AUDIT_REPORT', $report->id, $version->version_number, $report->report_code, null, [$version->document_version_id]);
            $this->support->audit($request, 'aems.report.distribution_'.$decision, $engagement, null, [
                'reportId' => $report->id, 'versionId' => $version->id, 'recipientId' => $recipient->id,
                'decision' => $decision,
            ], ['subjectType' => 'AUDIT_REPORT', 'subjectId' => $report->id, 'subjectCode' => $report->report_code]);

            return $decisionRecord->load(['recipient', 'decider']);
        });
    }

    /** Withdraw an issued report without mutating its immutable issued version. */
    public function withdraw(Request $request, AuditEngagement $engagement, AuditReport $report, int $lockVersion, string $reason): AuditReport
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.report.withdraw', $report->prepared_by);
        return DB::transaction(function () use ($request, $engagement, $report, $lockVersion, $reason): AuditReport {
            $locked = $this->lock($engagement, $report, $lockVersion);
            if ($locked->status !== 'ISSUED') $this->invalidTransition('WITHDRAW', $locked);
            $old = $this->reportAudit($locked);
            AuditReport::query()->whereKey($locked->id)->update([
                'status' => 'WITHDRAWN', 'is_active' => false,
                'withdrawn_at' => now(), 'withdrawn_by' => $request->user()->id,
                'withdrawal_reason' => trim($reason), 'lock_version' => $locked->lock_version + 1,
            ]);
            $fresh = $this->load($locked->fresh());
            $this->record($request, $engagement, $fresh, 'aems.report.withdrawn', $old['status'], 'WITHDRAWN', $old, $reason, [$fresh->currentVersion?->document_version_id]);
            return $fresh;
        });
    }

    /**
     * Create a controlled successor version for an issued report.
     *
     * The report family remains the single active family for the engagement;
     * the issued version is never edited and the correction is represented by
     * a new draft version linked to its predecessor in the immutable snapshot.
     */
    public function createSuccessor(Request $request, AuditEngagement $engagement, AuditReport $report, int $lockVersion, string $action, string $reason): AuditReport
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, $action === 'AMEND' ? 'aems.report.amend' : 'aems.report.supersede', $report->prepared_by);
        return DB::transaction(function () use ($request, $engagement, $report, $lockVersion, $action, $reason): AuditReport {
            $source = $this->lock($engagement, $report, $lockVersion);
            if ($source->status !== 'ISSUED') $this->invalidTransition($action, $source);
            $sourceVersion = $source->currentVersion()->with(['findings', 'recipients'])->firstOrFail();
            $snapshot = $sourceVersion->content_snapshot;
            $attributes = [
                'title' => $snapshot['title'].' — '.($action === 'AMEND' ? 'Amended' : 'Superseding'),
                'executiveSummary' => $snapshot['executiveSummary'],
                'sections' => $snapshot['sections'],
                'findingIds' => $sourceVersion->findings->pluck('id')->all(),
                'confidentialityLevelId' => $source->confidentiality_level_id,
                'approvingAuthority' => $source->approving_authority,
                'recipients' => $sourceVersion->recipients->map(fn (ReportRecipient $recipient): array => [
                    'recipientType' => $recipient->recipient_type, 'userId' => $recipient->user_id,
                    'officeId' => $recipient->office_id, 'externalName' => $recipient->external_name,
                    'externalEmail' => $recipient->external_email, 'deliveryMethod' => $recipient->delivery_method,
                ])->all(),
                'changeReason' => trim($reason),
                'qualityChecklist' => $snapshot['qualityChecklist'] ?? [],
                'supersedesVersionId' => $sourceVersion->id,
            ];
            $findings = $this->validatedFindings(
                $engagement,
                $attributes['findingIds'],
                $source->report_stage === 'FINAL_REPORT',
            );
            $recipients = $source->report_stage === 'FINAL_REPORT'
                ? $this->validateRecipients($engagement, $attributes['recipients'])
                : [];

            // Query-builder updates intentionally bypass the issued-row guard:
            // they reset only the family pointer to a draft state. Existing
            // issued versions and their document versions remain untouched.
            AuditReport::query()->whereKey($source->id)->update([
                'title' => $attributes['title'],
                'status' => 'DRAFT',
                'submitted_at' => null,
                'submitted_by' => null,
                'approved_at' => null,
                'approved_by' => null,
                'issued_at' => null,
                'issued_by' => null,
                'withdrawn_at' => null,
                'withdrawn_by' => null,
                'withdrawal_reason' => null,
                'lock_version' => $source->lock_version + 1,
            ]);
            $draft = $source->fresh();
            $version = $this->createVersion(
                $request,
                $engagement,
                $draft,
                $draft->report_stage,
                $attributes,
                $findings,
                $recipients,
            );
            AuditReport::query()->whereKey($draft->id)->update([
                'current_version_number' => $version->version_number,
                'current_version_id' => $version->id,
                'lock_version' => $draft->lock_version + 1,
            ]);
            $new = $this->load($draft->fresh());
            $this->record(
                $request,
                $engagement,
                $new,
                'aems.report.'.strtolower($action),
                'ISSUED',
                'DRAFT',
                ['supersedesVersionId' => $sourceVersion->id, 'action' => $action],
                $reason,
                [$version->document_version_id],
            );
            return $new;
        });
    }

    /** @return array<string, mixed> */
    public function reportData(AuditReport $report, ?User $viewer = null): array
    {
        $report->loadMissing($this->relations());

        return [
            'id' => $report->id,
            'reportCode' => $report->report_code,
            'title' => $report->title,
            'reportStage' => $report->report_stage,
            'status' => $report->status,
            'currentVersionNumber' => $report->current_version_number,
            'currentVersionId' => $report->current_version_id,
            'confidentialityLevel' => $report->confidentialityLevel?->only([
                'id',
                'code',
                'label',
            ]),
            'preparedBy' => $this->userData($report->preparer),
            'submittedBy' => $this->userData($report->submitter),
            'submittedAt' => $report->submitted_at?->toISOString(),
            'approvedBy' => $this->userData($report->approver),
            'approvedAt' => $report->approved_at?->toISOString(),
            'approvingAuthority' => $report->approving_authority,
            'issuedBy' => $this->userData($report->issuer),
            'issuedAt' => $report->issued_at?->toISOString(),
            'withdrawnAt' => $report->withdrawn_at?->toISOString(),
            'withdrawnBy' => $this->userData($report->withdrawnBy),
            'withdrawalReason' => $report->withdrawal_reason,
            'administrativelyClosedAt' => $report->administratively_closed_at?->toISOString(),
            'administrativelyClosedBy' => $this->userData($report->administrativelyClosedBy),
            'administrativeClosureReason' => $report->administrative_closure_reason,
            'administrativeClosureReference' => $report->administrative_closure_reference,
            'supersedesReportId' => $report->supersedes_report_id,
            'lockVersion' => $report->lock_version,
            'versions' => $report->versions
                ->map(fn (AuditReportVersion $version): array => $this->versionData($version, $viewer))
                ->values()
                ->all(),
            'cmsTransfers' => $report->currentVersion?->findings
                ?->flatMap(fn (AuditFinding $finding) => $finding->recommendations)
                ->filter(fn (AuditRecommendation $recommendation) => $recommendation->cmsRecommendation)
                ->map(fn (AuditRecommendation $recommendation): array => [
                    'recommendationId' => $recommendation->id,
                    'recommendationCode' => $recommendation->recommendation_code,
                    'cmsRecommendationId' => $recommendation->cms_recommendation_id,
                    'transferKey' => $recommendation->cms_transfer_key,
                    'transferredAt' => $recommendation->transferred_to_cms_at?->toISOString(),
                ])->values()->all() ?? [],
            'canDownloadCurrent' => $viewer
                ? $this->canDownload($viewer, $report)
                : true,
            'distributionDecisions' => $report->distributionDecisions
                ->map(fn (AuditReportDistributionDecision $decision): array => [
                    'id' => $decision->id,
                    'versionId' => $decision->audit_report_version_id,
                    'recipientId' => $decision->report_recipient_id,
                    'decision' => $decision->decision_code,
                    'comment' => $decision->comment,
                    'decidedBy' => $this->userData($decision->decider),
                    'decidedAt' => $decision->decided_at?->toISOString(),
                ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function versionData(AuditReportVersion $version, ?User $viewer = null): array
    {
        $internal = $viewer === null || $viewer->hasPermission('aems.report.view');
        return [
            'id' => $version->id,
            'versionNumber' => $version->version_number,
            'reportStage' => $version->report_stage,
            'contentSnapshot' => $version->content_snapshot,
            'documentVersionId' => $version->document_version_id,
            'checksumSha256' => $version->checksum_sha256,
            'pdfFileName' => $version->pdf_file_name,
            'fileSize' => $version->file_size,
            'isLocked' => $version->is_locked,
            'lockedAt' => $version->locked_at?->toISOString(),
            'changeReason' => $version->change_reason,
            'sourceInterimReportVersionId' => $internal ? $version->source_interim_report_version_id : null,
            'interimTreatment' => $internal ? $version->interim_treatment : null,
            'sourceManifest' => $internal ? $version->source_manifest : null,
            'sourceManifestSha256' => $internal ? $version->source_manifest_sha256 : null,
            'reproducibilityKey' => $internal ? $version->reproducibility_key : null,
            'createdBy' => $this->userData($version->creator),
            'createdAt' => $version->created_at?->toISOString(),
            'findings' => $version->findings
                ->map(fn (AuditFinding $finding): array => [
                    ...$this->findingReference($finding),
                    'sequenceNumber' => (int) $finding->pivot->sequence_number,
                ])->values()->all(),
            'recipients' => $version->recipients
                ->map(fn (ReportRecipient $recipient): array => $this->recipientData($recipient))
                ->values()->all(),
            'reviewComments' => $version->reviewComments
                ->map(fn (AuditReportReviewComment $review): array => [
                    'id' => $review->id,
                    'action' => $review->review_action,
                    'comment' => $review->comment,
                    'reviewedBy' => $this->userData($review->reviewer),
                    'reviewedAt' => $review->reviewed_at?->toISOString(),
                ])->values()->all(),
            'issues' => $internal ? $version->issues->map(fn (AuditIssue $issue): array => ['id' => $issue->id, 'issueCode' => $issue->issue_code, 'status' => $issue->status, 'treatment' => $issue->pivot->treatment])->values()->all() : [],
            'workingPaperVersions' => $internal ? $version->workingPaperVersions->map(fn (WorkingPaperVersion $item): array => ['id' => $item->id, 'workingPaperId' => $item->working_paper_id, 'versionNumber' => $item->version_number, 'checksum' => $item->checksum_sha256, 'treatment' => $item->pivot->treatment])->values()->all() : [],
            'evidence' => $internal ? $version->evidence->map(fn (AuditEvidence $item): array => ['id' => $item->id, 'evidenceCode' => $item->evidence_code, 'outcome' => $item->outcome, 'documentVersionId' => $item->document_version_id, 'checksum' => $item->checksum_sha256, 'treatment' => $item->pivot->treatment])->values()->all() : [],
            'authorityDecisions' => $internal ? $version->authorityDecisions->map(fn (AemsReportAuthorityDecision $item): array => ['id' => $item->id, 'authorityRole' => $item->authority_role, 'decisionCode' => $item->decision_code, 'comment' => $item->comment, 'decidedBy' => $this->userData($item->decider), 'decidedAt' => $item->decided_at?->toISOString()])->values()->all() : [],
            'signatories' => $internal ? $version->signatories->map(fn (AemsReportSignatory $item): array => ['id' => $item->id, 'signatoryRole' => $item->signatory_role, 'user' => $this->userData($item->user), 'signatoryName' => $item->signatory_name, 'signatureMethod' => $item->signature_method, 'signatureReference' => $item->signature_reference, 'signedAt' => $item->signed_at?->toISOString()])->values()->all() : [],
            'transmittals' => $internal ? $version->transmittals->map(fn (AemsReportTransmittal $item): array => ['id' => $item->id, 'reference' => $item->transmittal_reference, 'method' => $item->transmittal_method, 'status' => $item->delivery_status, 'sentAt' => $item->sent_at?->toISOString()])->values()->all() : [],
            'exports' => $internal ? $version->exports->map(fn (AemsReportExport $item): array => ['id' => $item->id, 'format' => $item->format, 'fileName' => $item->file_name, 'fileChecksumSha256' => $item->file_checksum_sha256, 'sourceManifestSha256' => $item->source_manifest_sha256, 'fileSize' => $item->file_size, 'generatedAt' => $item->generated_at?->toISOString(), 'generatedBy' => $this->userData($item->generator)])->values()->all() : [],
        ];
    }

    /** @param array<string, mixed> $attributes
     * @param  Collection<int, AuditFinding>  $findings
     * @param  list<array<string, mixed>>  $recipients
     */
    private function createVersion(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        string $stage,
        array $attributes,
        $findings,
        array $recipients,
    ): AuditReportVersion {
        $versionNumber = $report->versions()->max('version_number') + 1;
        $snapshot = [
            'title' => trim($attributes['title']),
            'reportStage' => $stage,
            'executiveSummary' => trim($attributes['executiveSummary']),
            'sections' => collect($attributes['sections'])->values()->map(
                fn (array $section, int $index): array => [
                    'sequenceNumber' => $index + 1,
                    'title' => trim($section['title']),
                    'content' => trim($section['content']),
                ],
            )->all(),
            'findings' => $findings->values()->map(
                fn (AuditFinding $finding, int $index): array => [
                    'sequenceNumber' => $index + 1,
                    'id' => $finding->id,
                    'findingCode' => $finding->finding_code,
                    'title' => $finding->title,
                    'status' => $finding->status,
                    'criteria' => $finding->criteria,
                    'condition' => $finding->condition,
                    'cause' => $finding->cause,
                    'effect' => $finding->effect,
                    'riskRating' => $finding->riskRating?->only(['id', 'code', 'label']),
                    'responsibleOffice' => $this->officeData($finding->responsibleOffice),
                    'recommendations' => $finding->recommendations->map(
                        fn (AuditRecommendation $recommendation): array => [
                            'id' => $recommendation->id,
                            'recommendationCode' => $recommendation->recommendation_code,
                            'recommendation' => $recommendation->recommendation,
                            'responsibleOffice' => $this->officeData($recommendation->responsibleOffice),
                            'targetImplementationDate' => $recommendation
                                ->target_implementation_date?->toDateString(),
                            'status' => $recommendation->status,
                        ],
                    )->values()->all(),
                ],
            )->all(),
            'approvingAuthority' => $stage === 'FINAL_REPORT'
                ? trim($attributes['approvingAuthority'])
                : null,
            'confidentialityLevelId' => $attributes['confidentialityLevelId'],
            'qualityChecklist' => collect($attributes['qualityChecklist'] ?? [])->values()->map(fn ($item): array => [
                'code' => (string) ($item['code'] ?? ''),
                'label' => (string) ($item['label'] ?? ''),
                'completed' => (bool) ($item['completed'] ?? false),
            ])->all(),
            'supersedesVersionId' => $attributes['supersedesVersionId'] ?? null,
            'recipients' => $recipients,
            'generatedAt' => now()->toISOString(),
            'generatedBy' => $this->userData($request->user()),
        ];
        $pdf = Pdf::loadView('reports.audit-report', [
            'engagement' => $engagement,
            'report' => $report,
            'versionNumber' => $versionNumber,
            'snapshot' => $snapshot,
            'configuration' => $this->runtime->publicValues(),
        ])->setPaper('a4')->output();
        $fileName = "{$report->report_code}-v{$versionNumber}.pdf";
        $path = "aems/engagements/{$engagement->id}/reports/".Str::uuid().'.pdf';
        Storage::disk('local')->put($path, $pdf);
        $checksum = hash('sha256', $pdf);
        $documentVersion = $report->document->versions()->create([
            'version_number' => $versionNumber,
            'version_label' => "{$stage} version {$versionNumber}",
            'change_summary' => $attributes['changeReason'] ?? 'Initial generated report version.',
            'original_file_name' => $fileName,
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => strlen($pdf),
            'checksum_sha256' => $checksum,
            'uploaded_by' => $request->user()->id,
        ]);
        $report->document->forceFill([
            'confidentiality_level_id' => $attributes['confidentialityLevelId'],
            'current_version_id' => $documentVersion->id,
            'version' => $documentVersion->version_label,
            'original_file_name' => $fileName,
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => strlen($pdf),
            'checksum_sha256' => $checksum,
            'updated_by' => $request->user()->id,
        ])->save();
        $version = $report->versions()->create([
            'version_number' => $versionNumber,
            'report_stage' => $stage,
            'content_snapshot' => $snapshot,
            'document_version_id' => $documentVersion->id,
            'checksum_sha256' => $checksum,
            'pdf_file_name' => $fileName,
            'file_size' => strlen($pdf),
            'is_locked' => false,
            'change_reason' => $attributes['changeReason'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        $version->findings()->attach($findings->values()->mapWithKeys(
            fn (AuditFinding $finding, int $index): array => [
                $finding->id => [
                    'sequence_number' => $index + 1,
                    'is_included' => true,
                ],
            ],
        )->all());
        $source = $this->validatedSources($engagement, $report, $attributes, $stage === 'FINAL_REPORT');
        if ($source['issues']->isNotEmpty()) {
            $version->issues()->attach($source['issues']->mapWithKeys(fn (AuditIssue $issue, int $index): array => [
                $issue->id => ['sequence_number' => $index + 1, 'treatment' => $source['issueTreatments'][$issue->id] ?? null, 'link_reason' => $source['linkReasons'][$issue->id] ?? null, 'linked_by' => $request->user()->id],
            ])->all());
        }
        if ($source['workingPapers']->isNotEmpty()) {
            $version->workingPaperVersions()->attach($source['workingPapers']->mapWithKeys(fn (WorkingPaperVersion $wp, int $index): array => [
                $wp->id => ['sequence_number' => $index + 1, 'treatment' => $source['wpTreatments'][$wp->id] ?? null, 'link_reason' => $source['linkReasons'][$wp->id] ?? null, 'linked_by' => $request->user()->id],
            ])->all());
        }
        if ($source['evidence']->isNotEmpty()) {
            $version->evidence()->attach($source['evidence']->mapWithKeys(fn (AuditEvidence $evidence, int $index): array => [
                $evidence->id => ['sequence_number' => $index + 1, 'treatment' => $source['evidenceTreatments'][$evidence->id] ?? null, 'link_reason' => $source['linkReasons'][$evidence->id] ?? null, 'linked_by' => $request->user()->id],
            ])->all());
        }
        $manifest = $this->sourceManifest($engagement, $report, $version, $findings, $source);
        DB::table('audit_report_versions')->where('id', $version->id)->update([
            'source_interim_report_version_id' => $attributes['sourceInterimReportVersionId'] ?? null,
            'interim_treatment' => $attributes['interimTreatment'] ?? null,
            'source_manifest' => $manifest,
            'source_manifest_sha256' => hash('sha256', json_encode($manifest, JSON_UNESCAPED_SLASHES)),
            'reproducibility_key' => Str::uuid()->toString(),
        ]);
        foreach ($recipients as $recipient) {
            $version->recipients()->create($recipient);
        }

        return $version;
    }

    private function createDocument(Request $request, AuditReport $report): Document
    {
        $type = MasterList::query()->where('code', 'DOCUMENT_TYPE')
            ->firstOrFail()->items()->where('code', 'OTHER')->firstOrFail();
        $placeholder = 'pending/'.Str::uuid().'.pdf';
        $document = Document::query()->create([
            'document_type_id' => $type->id,
            'confidentiality_level_id' => $report->confidentiality_level_id,
            'title' => $report->title,
            'description' => "Generated AEMS report {$report->report_code}.",
            'owner_module' => 'AEMS',
            'library_visible' => false,
            'original_file_name' => "{$report->report_code}-pending.pdf",
            'storage_path' => $placeholder,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 0,
            'checksum_sha256' => hash('sha256', ''),
            'uploaded_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'is_active' => true,
        ]);
        $document->forceFill([
            'document_code' => $this->runtime->formatNumber('document_number_format', $document->id),
        ])->save();
        $document->links()->create([
            'module_code' => 'AEMS',
            'record_type' => 'AUDIT_REPORT',
            'record_id' => $report->id,
            'record_code' => $report->report_code,
            'record_label' => "{$report->report_code} — {$report->title}",
            'linked_by' => $request->user()->id,
        ]);

        return $document;
    }

    /** @param list<int> $findingIds
     * @return Collection<int, AuditFinding>
     */
    private function validatedFindings(
        AuditEngagement $engagement,
        array $findingIds,
        bool $finalOnly,
    ) {
        $statuses = $finalOnly
            ? ['FINALIZED']
            : ['VALIDATED', 'COMMUNICATED', 'AWAITING_MANAGEMENT_RESPONSE', 'UNDER_DIALOGUE', 'FINALIZED'];
        $findings = AuditFinding::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('is_current_revision', true)
            ->whereIn('status', $statuses)
            ->whereIn('id', $findingIds)
            ->with(['riskRating', 'responsibleOffice', 'recommendations.responsibleOffice'])
            ->get()->keyBy('id');
        if ($findings->count() !== count(array_unique($findingIds))) {
            throw ValidationException::withMessages([
                'findingIds' => [$finalOnly
                    ? 'Only current finalized Findings can enter the Final Report.'
                    : 'Draft Reports require current validated or later Findings from this engagement.'],
            ]);
        }

        return collect($findingIds)->map(fn (int $id) => $findings->get($id));
    }

    /** @return array{issues:Collection,workingPapers:Collection,evidence:Collection,issueTreatments:array,wpTreatments:array,evidenceTreatments:array,linkReasons:array} */
    private function validatedSources(AuditEngagement $engagement, AuditReport $report, array $attributes, bool $finalOnly): array
    {
        $issueIds = array_values(array_unique(array_map('intval', $attributes['issueIds'] ?? [])));
        $wpIds = array_values(array_unique(array_map('intval', $attributes['workingPaperVersionIds'] ?? [])));
        $evidenceIds = array_values(array_unique(array_map('intval', $attributes['evidenceIds'] ?? [])));
        $issues = AuditIssue::query()->where('audit_engagement_id', $engagement->id)->whereIn('id', $issueIds)->get()->keyBy('id');
        $workingPapers = WorkingPaperVersion::query()->whereIn('id', $wpIds)->with('workingPaper')->get()
            ->filter(fn (WorkingPaperVersion $version): bool => (int) $version->workingPaper?->audit_engagement_id === (int) $engagement->id
                && $version->workingPaper?->status === 'APPROVED')->keyBy('id');
        $evidence = AuditEvidence::query()->where('audit_engagement_id', $engagement->id)
            ->where('is_current_revision', true)->whereIn('id', $evidenceIds)->with('documentVersion')->get()->keyBy('id');
        if ($issues->count() !== count($issueIds) || $workingPapers->count() !== count($wpIds) || $evidence->count() !== count($evidenceIds)) {
            throw ValidationException::withMessages(['sources' => ['Every report source must belong to the engagement and use an approved/current record.']]);
        }
        if ($evidence->contains(fn (AuditEvidence $item): bool => ! $item->document_version_id || ! $item->checksum_sha256 || ! $item->documentVersion)) {
            throw ValidationException::withMessages(['evidenceIds' => ['Every linked Evidence item must pin an available Core Document Version and checksum.']]);
        }
        if ($finalOnly && $evidence->contains(fn (AuditEvidence $item): bool => $item->outcome !== 'ACCEPTED')) {
            throw ValidationException::withMessages(['evidenceIds' => ['Only evidence with an Accepted outcome may be linked to a Final Report.']]);
        }
        if ($finalOnly && $issues->contains(fn (AuditIssue $item): bool => ! in_array($item->status, ['VALIDATED', 'DISMISSED', 'CONVERTED_TO_FINDING', 'WITHDRAWN'], true))) {
            throw ValidationException::withMessages(['issueIds' => ['Only evaluated or terminal Issues may be linked to a Final Report.']]);
        }
        $treatments = $attributes['sourceTreatments'] ?? [];
        if (! empty($attributes['sourceInterimReportVersionId'])) {
            $sourceVersion = AuditReportVersion::query()->with('report')->find($attributes['sourceInterimReportVersionId']);
            if (! $sourceVersion || (int) $sourceVersion->report?->audit_engagement_id !== (int) $engagement->id
                || ! in_array($sourceVersion->report_stage, ['INTERIM_REPORT', 'DRAFT_REPORT'], true)
                || ! in_array($sourceVersion->report?->status, ['DRAFT', 'APPROVED', 'ISSUED', 'ADMINISTRATIVELY_CLOSED'], true)
                || ((int) $sourceVersion->audit_report_id !== (int) $report->id && $sourceVersion->report?->status === 'DRAFT')) {
                throw ValidationException::withMessages(['sourceInterimReportVersionId' => ['Select an approved Interim or Draft Report version from this engagement.']]);
            }
        }
        return [
            'issues' => $issues->values(), 'workingPapers' => $workingPapers->values(), 'evidence' => $evidence->values(),
            'issueTreatments' => $treatments['issues'] ?? [], 'wpTreatments' => $treatments['workingPapers'] ?? [],
            'evidenceTreatments' => $treatments['evidence'] ?? [], 'linkReasons' => $attributes['linkReasons'] ?? [],
        ];
    }

    private function sourceManifest(AuditEngagement $engagement, AuditReport $report, AuditReportVersion $version, Collection $findings, array $source): array
    {
        return [
            'engagementId' => $engagement->id, 'reportId' => $report->id, 'reportCode' => $report->report_code,
            'reportVersionId' => $version->id, 'reportVersionNumber' => $version->version_number,
            'findings' => $findings->map(fn (AuditFinding $finding): array => ['id' => $finding->id, 'revision' => $finding->revision_number ?? null, 'status' => $finding->status, 'finalizedSnapshot' => $finding->finalized_snapshot, 'snapshotHash' => hash('sha256', json_encode($finding->only(['id','title','criteria','condition','cause','effect','conclusion','status','finalized_snapshot']), JSON_UNESCAPED_SLASHES))])->values()->all(),
            'issues' => $source['issues']->map(fn (AuditIssue $item): array => ['id' => $item->id, 'code' => $item->issue_code, 'status' => $item->status, 'disposition' => $item->disposition])->values()->all(),
            'workingPapers' => $source['workingPapers']->map(fn (WorkingPaperVersion $item): array => ['id' => $item->id, 'workingPaperId' => $item->working_paper_id, 'version' => $item->version_number, 'documentVersionId' => $item->document_version_id, 'checksum' => $item->checksum_sha256])->values()->all(),
            'evidence' => $source['evidence']->map(fn (AuditEvidence $item): array => ['id' => $item->id, 'code' => $item->evidence_code, 'outcome' => $item->outcome, 'documentVersionId' => $item->document_version_id, 'checksum' => $item->checksum_sha256])->values()->all(),
        ];
    }

    /** @param list<array<string, mixed>> $recipients
     * @return list<array<string, mixed>>
     */
    private function validateRecipients(AuditEngagement $engagement, array $recipients): array
    {
        $normalized = [];
        foreach ($recipients as $index => $recipient) {
            $type = $recipient['recipientType'];
            $userId = $recipient['userId'] ?? null;
            $officeId = $recipient['officeId'] ?? null;
            $externalName = $this->nullableTrim($recipient['externalName'] ?? null);
            if ($type === 'USER' && ! User::query()->where('is_active', true)->whereKey($userId)->exists()) {
                throw ValidationException::withMessages([
                    "recipients.{$index}.userId" => ['Select an active internal recipient.'],
                ]);
            }
            if ($type === 'OFFICE' && (! $officeId
                || ! $engagement->offices()->whereKey($officeId)->exists())) {
                throw ValidationException::withMessages([
                    "recipients.{$index}.officeId" => ['Select an office covered by the engagement.'],
                ]);
            }
            if ($type === 'EXTERNAL' && ! $externalName) {
                throw ValidationException::withMessages([
                    "recipients.{$index}.externalName" => ['Enter the external recipient name.'],
                ]);
            }
            $normalized[] = [
                'user_id' => $type === 'USER' ? $userId : null,
                'office_id' => $type === 'OFFICE' ? $officeId : null,
                'external_name' => $type === 'EXTERNAL' ? $externalName : null,
                'external_email' => $type === 'EXTERNAL'
                    ? $this->nullableTrim($recipient['externalEmail'] ?? null)
                    : null,
                'recipient_type' => $type,
                'delivery_method' => $recipient['deliveryMethod'] ?? 'SYSTEM',
                'delivery_status' => 'PENDING',
            ];
        }

        return $normalized;
    }

    private function validateFinalApproval(
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
    ): void {
        if (! $report->approving_authority || ! $report->confidentiality_level_id) {
            throw ValidationException::withMessages([
                'report' => ['Final approval requires an approving authority and confidentiality level.'],
            ]);
        }
        if ($version->recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'report' => ['Final approval requires at least one version-bound recipient.'],
            ]);
        }
        if ($version->findings->isEmpty()
            || $version->findings->contains(fn ($finding) => $finding->status !== 'FINALIZED')) {
            throw ValidationException::withMessages([
                'report' => ['Only finalized Findings can be approved in the Final Report.'],
            ]);
        }
        $checklist = collect($version->content_snapshot['qualityChecklist'] ?? []);
        if ($checklist->isNotEmpty() && $checklist->contains(fn ($item): bool => ! (bool) ($item['completed'] ?? false))) {
            throw ValidationException::withMessages([
                'report' => ['The quality checklist must be completed before Final Report approval.'],
            ]);
        }
        if (! ExitConference::query()
            ->where('audit_engagement_id', $engagement->id)
            ->whereIn('status', ['COMPLETED', 'WAIVED'])
            ->exists()) {
            throw ValidationException::withMessages([
                'report' => ['Final approval requires a completed or formally waived Exit Conference.'],
            ]);
        }
    }

    private function ensureDefaultAuthorityDecisions(AuditReport $report, AuditReportVersion $version, int $userId): void
    {
        foreach ([['IAU_HEAD_RECOMMENDATION', 'RECOMMEND'], ['LCE_APPROVAL', 'APPROVE']] as [$role, $decision]) {
            if (! $version->authorityDecisions()->where('authority_role', $role)->exists()) {
                AemsReportAuthorityDecision::query()->create([
                    'audit_report_id' => $report->id, 'audit_report_version_id' => $version->id,
                    'authority_role' => $role, 'decision_code' => $decision,
                    'comment' => 'Recorded from the controlled report approval action.', 'decided_by' => $userId, 'decided_at' => now(),
                ]);
            }
        }
    }

    private function ensureAuthorityMatrix(AuditReport $report, AuditReportVersion $version): void
    {
        $roles = $version->authorityDecisions()->whereIn('authority_role', ['IAU_HEAD_RECOMMENDATION', 'LCE_APPROVAL'])->get();
        foreach (['IAU_HEAD_RECOMMENDATION' => 'RECOMMEND', 'LCE_APPROVAL' => 'APPROVE'] as $role => $decision) {
            if (! $roles->contains(fn ($item): bool => $item->authority_role === $role && $item->decision_code === $decision)) {
                throw ValidationException::withMessages(['authority' => ["{$role} decision is required before issuance."]]);
            }
        }
    }

    private function ensureSignatoryMatrix(AuditReport $report, AuditReportVersion $version, int $issuerId): void
    {
        if (! $version->signatories()->where('signatory_role', 'LCE')->exists()) {
            AemsReportSignatory::query()->create([
                'audit_report_id' => $report->id, 'audit_report_version_id' => $version->id,
                'signatory_role' => 'LCE', 'user_id' => $report->approved_by ?: $issuerId,
                'signature_method' => 'CONTROLLED_WORKFLOW', 'signature_reference' => $report->report_code.'-APPROVAL', 'signed_at' => $report->approved_at ?: now(),
            ]);
        }
        if (! $version->signatories()->where('signatory_role', 'PRESIDING_OFFICER')->exists()) {
            AemsReportSignatory::query()->create([
                'audit_report_id' => $report->id, 'audit_report_version_id' => $version->id,
                'signatory_role' => 'PRESIDING_OFFICER', 'user_id' => $issuerId,
                'signature_method' => 'CONTROLLED_WORKFLOW', 'signature_reference' => $report->report_code.'-ISSUANCE', 'signed_at' => now(),
            ]);
        }
    }

    private function ensureTransmittal(AuditReport $report, AuditReportVersion $version, int $userId, Carbon $sentAt): void
    {
        if (! $version->transmittals()->exists()) {
            AemsReportTransmittal::query()->create([
                'audit_report_id' => $report->id, 'audit_report_version_id' => $version->id,
                'transmittal_reference' => $report->report_code.'-V'.$version->version_number.'-'.Str::upper(Str::random(8)),
                'transmittal_method' => 'CONTROLLED_SYSTEM', 'delivery_status' => 'SENT',
                'sent_by' => $userId, 'sent_at' => $sentAt,
            ]);
        }
    }

    public function recordAuthorityDecision(Request $request, AuditEngagement $engagement, AuditReport $report, AuditReportVersion $version, array $attributes): AemsReportAuthorityDecision
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.report.authority', $report->prepared_by);
        $this->ensureReport($engagement, $report); abort_unless((int) $version->audit_report_id === (int) $report->id, 422);
        abort_unless(! $version->is_locked, 422, 'Authority decisions cannot be added after issuance.');
        $decision = AemsReportAuthorityDecision::query()->create(['audit_report_id' => $report->id, 'audit_report_version_id' => $version->id, 'authority_role' => $attributes['authorityRole'], 'decision_code' => $attributes['decisionCode'], 'comment' => $attributes['comment'] ?? null, 'decision_reference' => $attributes['decisionReference'] ?? null, 'decided_by' => $request->user()->id, 'decided_at' => now()]);
        $this->record($request, $engagement, $report, 'aems.report.authority_decision', $report->status, $report->status, null, $decision->authority_role, [$version->document_version_id]);
        return $decision;
    }

    public function recordSignatory(Request $request, AuditEngagement $engagement, AuditReport $report, AuditReportVersion $version, array $attributes): AemsReportSignatory
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.report.signatory', $report->prepared_by);
        $this->ensureReport($engagement, $report); abort_unless((int) $version->audit_report_id === (int) $report->id, 422); abort_unless(! $version->is_locked, 422, 'Signatories cannot be changed after issuance.');
        $signatory = AemsReportSignatory::query()->create(['audit_report_id' => $report->id, 'audit_report_version_id' => $version->id, 'signatory_role' => $attributes['signatoryRole'], 'user_id' => $attributes['userId'] ?? null, 'signatory_name' => $attributes['signatoryName'] ?? null, 'signature_method' => $attributes['signatureMethod'], 'signature_reference' => $attributes['signatureReference'] ?? null, 'signed_at' => $attributes['signedAt'] ?? now()]);
        $this->record($request, $engagement, $report, 'aems.report.signatory_recorded', $report->status, $report->status, null, $signatory->signatory_role, [$version->document_version_id]);
        return $signatory;
    }

    public function createTransmittal(Request $request, AuditEngagement $engagement, AuditReport $report, AuditReportVersion $version, array $attributes): AemsReportTransmittal
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.report.transmit', $report->prepared_by); $this->ensureReport($engagement, $report); abort_unless((int) $version->audit_report_id === (int) $report->id && $version->is_locked, 422, 'Only an issued locked version may be transmitted.');
        $transmittal = AemsReportTransmittal::query()->create(['audit_report_id' => $report->id, 'audit_report_version_id' => $version->id, 'transmittal_reference' => $attributes['transmittalReference'], 'transmittal_method' => $attributes['transmittalMethod'], 'delivery_status' => $attributes['deliveryStatus'] ?? 'SENT', 'note' => $attributes['note'] ?? null, 'sent_by' => $request->user()->id, 'sent_at' => $attributes['sentAt'] ?? now()]);
        $this->record($request, $engagement, $report, 'aems.report.transmittal_recorded', $report->status, $report->status, null, $transmittal->transmittal_reference, [$version->document_version_id]);
        return $transmittal;
    }

    public function administrativeClose(Request $request, AuditEngagement $engagement, AuditReport $report, int $lockVersion, string $reason, ?string $reference): AuditReport
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.report.close_admin', $report->prepared_by);
        return DB::transaction(function () use ($request, $engagement, $report, $lockVersion, $reason, $reference): AuditReport {
            $locked = $this->lock($engagement, $report, $lockVersion); abort_unless($locked->status === 'ISSUED', 422, 'Only an issued report can be administratively closed.');
            $version = $locked->currentVersion()->firstOrFail(); abort_unless($version->is_locked && $version->source_manifest_sha256, 422, 'The issued version must have a source manifest.');
            abort_unless($version->transmittals()->exists(), 422, 'A report transmittal is required before administrative closure.');
            AuditReport::query()->whereKey($locked->id)->update(['status' => 'ADMINISTRATIVELY_CLOSED', 'administratively_closed_at' => now(), 'administratively_closed_by' => $request->user()->id, 'administrative_closure_reason' => trim($reason), 'administrative_closure_reference' => $reference ? trim($reference) : null, 'lock_version' => $locked->lock_version + 1]);
            $fresh = $this->load($locked->fresh()); $this->record($request, $engagement, $fresh, 'aems.report.administratively_closed', 'ISSUED', 'ADMINISTRATIVELY_CLOSED', null, $reason, [$version->document_version_id]); return $fresh;
        });
    }

    public function export(Request $request, AuditEngagement $engagement, AuditReport $report, AuditReportVersion $version, string $format): AemsReportExport
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.report.export', $report->prepared_by); $this->ensureReport($engagement, $report); abort_unless((int) $version->audit_report_id === (int) $report->id && $version->is_locked, 422, 'Only an issued locked version may be exported.');
        $this->authorizeConfidentiality($request->user(), $report, $version);
        $format = strtoupper($format); abort_unless(in_array($format, ['PDF', 'CSV'], true), 422, 'Only PDF and CSV exports are supported.');
        $manifest = $version->source_manifest ?? []; $bytes = $format === 'PDF' ? Storage::disk('local')->get($version->documentVersion->storage_path) : $this->csvManifest($manifest);
        $name = $report->report_code.'-v'.$version->version_number.'.'.strtolower($format); $path = 'aems/engagements/'.$engagement->id.'/exports/'.Str::uuid().'.'.strtolower($format); Storage::disk('local')->put($path, $bytes);
        $export = AemsReportExport::query()->create(['audit_report_id' => $report->id, 'audit_report_version_id' => $version->id, 'format' => $format, 'document_version_id' => $format === 'PDF' ? $version->document_version_id : null, 'source_manifest_sha256' => $version->source_manifest_sha256, 'file_checksum_sha256' => hash('sha256', $bytes), 'file_size' => strlen($bytes), 'file_name' => $name, 'scope_hash' => hash('sha256', $engagement->id.'|'.$request->user()->id), 'generated_by' => $request->user()->id, 'generated_at' => now(), 'storage_path' => $path]);
        $this->record($request, $engagement, $report, 'aems.report.exported', $report->status, $report->status, null, $format.' export', [$version->document_version_id]);
        return $export;
    }

    private function csvManifest(array $manifest): string
    {
        $stream = fopen('php://temp', 'r+'); fputcsv($stream, ['record_type', 'record_id', 'code', 'status', 'checksum']);
        foreach (['findings' => ['findingCode', 'status', null], 'issues' => ['code', 'status', null], 'workingPapers' => ['id', 'version', 'checksum'], 'evidence' => ['code', 'outcome', 'checksum']] as $type => [$code, $status, $checksum]) foreach ($manifest[$type] ?? [] as $row) fputcsv($stream, [$this->csvCell($type), $this->csvCell($row['id'] ?? ''), $this->csvCell($row[$code] ?? ''), $this->csvCell($row[$status] ?? ''), $this->csvCell($checksum ? ($row[$checksum] ?? '') : '')]);
        rewind($stream); return stream_get_contents($stream) ?: '';
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    /** @return list<array<string, mixed>> */
    private function transferRecommendations(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
        bool $recordEvent,
    ): array {
        $transfers = [];
        foreach ($version->findings as $finding) {
            foreach ($finding->recommendations as $recommendation) {
                if (! in_array($recommendation->status, ['FINALIZED', 'TRANSFERRED'], true)) {
                    continue;
                }
                $cms = $this->cms->transfer(
                    $recommendation,
                    $engagement,
                    $report,
                    $version,
                    $request,
                );
                $transfers[] = [
                    'recommendationId' => $recommendation->id,
                    'recommendationCode' => $recommendation->recommendation_code,
                    'cmsRecommendationId' => $cms->id,
                    'transferKey' => $cms->transfer_key,
                    'transferredAt' => $cms->transferred_at?->toISOString(),
                ];
            }
        }
        if ($recordEvent) {
            $this->record(
                $request,
                $engagement,
                $report,
                'aems.report.cms_transfer_retried',
                'ISSUED',
                'ISSUED',
                null,
                'Idempotent CMS recommendation transfer.',
                [$version->document_version_id],
            );
        }

        return $transfers;
    }

    private function reviewComment(
        Request $request,
        AuditReport $report,
        AuditReportVersion $version,
        string $action,
        string $comment,
    ): void {
        AuditReportReviewComment::query()->create([
            'audit_report_id' => $report->id,
            'audit_report_version_id' => $version->id,
            'review_action' => $action,
            'comment' => trim($comment),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
    }

    private function authorizeConfidentiality(
        User $user,
        AuditReport $report,
        AuditReportVersion $version,
    ): void {
        $directRecipient = $version->recipients()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id);
                if ($user->office_id) {
                    $query->orWhere('office_id', $user->office_id);
                }
            })->exists();
        if ($directRecipient) {
            return;
        }
        $code = $report->confidentialityLevel?->code ?? 'INTERNAL';
        $allowed = match ($code) {
            'RESTRICTED' => $user->hasPermission('documents.view_restricted'),
            'CONFIDENTIAL' => $user->hasPermission('documents.view_confidential'),
            default => true,
        };
        abort_unless($allowed, 403, 'The report confidentiality level does not allow this download.');
    }

    private function canDownload(User $user, AuditReport $report): bool
    {
        try {
            $this->access->authorizeReportView($user, $report);
            $version = $report->currentVersion;
            if (! $version) {
                return false;
            }
            $this->authorizeConfidentiality($user, $report, $version);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function ensureConfidentiality(int $id): void
    {
        $valid = MasterList::query()->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->whereHas('items', fn ($items) => $items->whereKey($id)->where('is_active', true))
            ->exists();
        if (! $valid) {
            throw ValidationException::withMessages([
                'confidentialityLevelId' => ['Select an active confidentiality level.'],
            ]);
        }
    }

    private function lock(
        AuditEngagement $engagement,
        AuditReport $report,
        int $lockVersion,
    ): AuditReport {
        $locked = AuditReport::query()->lockForUpdate()->findOrFail($report->id);
        $this->ensureReport($engagement, $locked);
        if ($locked->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['This report changed in another session. Refresh before continuing.'],
            ]);
        }

        return $locked;
    }

    private function ensureReport(AuditEngagement $engagement, AuditReport $report): void
    {
        if ((int) $report->audit_engagement_id !== (int) $engagement->id) {
            throw ValidationException::withMessages([
                'report' => ['The report does not belong to this engagement.'],
            ]);
        }
    }

    private function invalidTransition(string $action, AuditReport $report): never
    {
        throw ValidationException::withMessages([
            'action' => ["{$action} is not allowed while the report is {$report->status}."],
        ]);
    }

    private function nextCode(AuditEngagement $engagement): string
    {
        $prefix = sprintf('AR-%s-', $engagement->engagement_code);
        $number = AuditReport::withTrashed()->where('report_code', 'like', $prefix.'%')->count() + 1;
        do {
            $code = sprintf('%s%02d', $prefix, $number++);
        } while (AuditReport::withTrashed()->where('report_code', $code)->exists());

        return $code;
    }

    /** @return list<array<string, mixed>> */
    private function masterItems(string $code): array
    {
        return MasterList::query()->where('code', $code)->firstOrFail()
            ->items()->where('is_active', true)->orderBy('display_order')
            ->get(['id', 'code', 'label', 'description'])->map->toArray()->all();
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'confidentialityLevel',
            'preparer',
            'submitter',
            'approver',
            'issuer',
            'withdrawnBy',
            'supersededReport',
            'currentVersion.findings.recommendations.cmsRecommendation',
            'versions.creator',
            'versions.documentVersion',
            'versions.findings.responsibleOffice',
            'versions.findings.riskRating',
            'versions.findings.recommendations.responsibleOffice',
            'versions.recipients.user',
            'versions.recipients.office',
            'versions.reviewComments.reviewer',
            'distributionDecisions.decider',
            'administrativelyClosedBy',
            'versions.issues',
            'versions.workingPaperVersions',
            'versions.evidence',
            'versions.authorityDecisions.decider',
            'versions.signatories.user',
            'versions.transmittals.sender',
            'versions.exports.generator',
        ];
    }

    private function load(AuditReport $report): AuditReport
    {
        return $report->fresh($this->relations());
    }

    /** @return array<string, mixed> */
    private function findingReference(AuditFinding $finding): array
    {
        return [
            'id' => $finding->id,
            'findingCode' => $finding->finding_code,
            'title' => $finding->title,
            'status' => $finding->status,
            'riskRating' => $finding->riskRating?->only(['id', 'code', 'label']),
            'responsibleOffice' => $this->officeData($finding->responsibleOffice),
            'recommendationCount' => $finding->recommendations->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function recipientData(ReportRecipient $recipient): array
    {
        return [
            'id' => $recipient->id,
            'recipientType' => $recipient->recipient_type,
            'user' => $this->userData($recipient->user),
            'office' => $this->officeData($recipient->office),
            'externalName' => $recipient->external_name,
            'externalEmail' => $recipient->external_email,
            'deliveryMethod' => $recipient->delivery_method,
            'deliveryStatus' => $recipient->delivery_status,
            'sentAt' => $recipient->sent_at?->toISOString(),
            'acknowledgedAt' => $recipient->acknowledged_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function userData(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'officeId' => $user->office_id,
        ] : null;
    }

    /** @return array<string, mixed>|null */
    private function officeData(?Office $office): ?array
    {
        return $office ? [
            'id' => $office->id,
            'code' => $office->code,
            'name' => $office->name,
            'acronym' => $office->acronym,
        ] : null;
    }

    /** @return array<string, mixed> */
    private function reportAudit(AuditReport $report): array
    {
        return [
            'id' => $report->id,
            'reportCode' => $report->report_code,
            'reportStage' => $report->report_stage,
            'status' => $report->status,
            'currentVersionNumber' => $report->current_version_number,
            'lockVersion' => $report->lock_version,
        ];
    }

    private function record(
        Request $request,
        AuditEngagement $engagement,
        AuditReport $report,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?array $oldValues = null,
        ?string $comment = null,
        ?array $documentVersionIds = null,
    ): void {
        $newValues = $this->reportAudit($report);
        $this->support->event(
            $request,
            $engagement,
            $action,
            $fromStatus,
            $toStatus,
            $oldValues,
            $newValues,
            $comment,
            'AUDIT_REPORT',
            $report->id,
            $report->current_version_number,
            $report->report_code,
            null,
            $documentVersionIds,
        );
        $this->support->audit(
            $request,
            $action,
            $engagement,
            $oldValues,
            $newValues,
            [
                'subjectType' => 'AUDIT_REPORT',
                'subjectId' => $report->id,
                'subjectCode' => $report->report_code,
                'documentVersionIds' => $documentVersionIds,
            ],
        );
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
