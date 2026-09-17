<?php

namespace App\Services;

use App\Contracts\Aems\EngagementRetentionProvider;
use App\Models\AuditEngagement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AemsClosureChecklistService
{
    public function __construct(
        private readonly AemsDocumentIndexService $documentIndex,
        private readonly EngagementRetentionProvider $retention,
        private readonly AemsCompletionTransferService $completionTransfer,
    ) {}

    /** @return list<array<string, mixed>> */
    public function evaluate(AuditEngagement $engagement): array
    {
        $engagement = $engagement->fresh($this->relations());
        $program = $engagement->programs
            ->where('is_current_revision', true)
            ->where('is_active', true)
            ->sortByDesc('revision_number')
            ->first();
        $procedures = $program?->procedures ?? collect();
        $issuedReport = $engagement->reports->first(
            fn ($report) => $report->report_stage === 'FINAL_REPORT'
                && $report->status === 'ISSUED',
        );
        $issuedVersion = $issuedReport?->currentVersion;
        $recommendations = $engagement->findings
            ->where('is_current_revision', true)
            ->flatMap->recommendations;
        $reliedEvidence = $engagement->evidence
            ->filter(fn ($evidence) => $evidence->workingPaperVersions->isNotEmpty()
                || $evidence->findings->isNotEmpty());
        $evidenceReady = $reliedEvidence->every(
            fn ($evidence) => $evidence->status === 'LOCKED'
                && $evidence->locked_at !== null
                && $evidence->documentVersion
                && hash_equals(
                    strtolower($evidence->checksum_sha256),
                    strtolower($evidence->documentVersion->checksum_sha256),
                )
                && Storage::disk('local')->exists($evidence->documentVersion->storage_path),
        );
        $responsesReady = $engagement->findings
            ->where('is_current_revision', true)
            ->every(function ($finding): bool {
                $current = $finding->managementResponses->firstWhere('is_current_revision', true);

                return $finding->status === 'FINALIZED'
                    && (! $current || $current->status === 'DIALOGUE_FINALIZED');
            });
        $recipientReady = $issuedVersion?->recipients->isNotEmpty() === true
            && $issuedVersion->recipients->every(
                fn ($recipient) => $recipient->sent_at !== null
                    && in_array($recipient->delivery_status, ['SENT', 'DELIVERED', 'ACKNOWLEDGED'], true),
            );
        $cmsReady = $recommendations->every(
            fn ($recommendation) => $recommendation->status === 'TRANSFERRED'
                ? $recommendation->cms_recommendation_id !== null
                    && $recommendation->cms_transfer_key !== null
                    && $recommendation->transferred_to_cms_at !== null
                : $recommendation->status === 'EXCLUDED'
                    && filled($recommendation->cms_exclusion_reason)
                    && filled($recommendation->cms_exclusion_authority)
                    && $recommendation->cms_excluded_by !== null
                    && $recommendation->cms_excluded_at !== null,
        );
        $documentReadiness = $this->documentIndex->readiness($engagement);
        $retentionReadiness = $this->retention->readiness($engagement->retentionRecord);
        $transferGate = $this->completionTransfer->closureGate($engagement);
        $assessment = $engagement->currentCompletionAssessment;
        $activeChild = $this->activeChildWorkflow($engagement, $program);
        $activeCoreWorkflow = DB::table('workflow_instances')
            ->where('module_code', 'AEMS')
            ->where('status', 'ACTIVE')
            ->where(function ($query) use ($engagement): void {
                $query->where(function ($engagementSubject) use ($engagement): void {
                    $engagementSubject
                        ->where('subject_type', 'AUDIT_ENGAGEMENT')
                        ->where('subject_id', $engagement->id);
                })->orWhere('subject_code', 'like', $engagement->engagement_code.'%');
            })
            ->exists();
        $reportCommentsReady = ! $engagement->reports->contains(function ($report): bool {
            $latest = $report->reviewComments->sortByDesc('reviewed_at')->first();

            return $latest?->review_action === 'RETURNED';
        });

        $items = collect();
        $add = function (
            string $code,
            string $category,
            string $description,
            bool $pass,
            string $explanation,
            ?string $recordType = null,
            ?int $recordId = null,
            ?string $sourcePath = null,
            bool $required = true,
            bool $blocking = true,
            ?string $result = null,
            mixed $snapshot = null,
        ) use ($items): void {
            $items->push([
                'checklistCode' => $code,
                'checklistCategoryCode' => $category,
                'description' => $description,
                'requiredFlag' => $required,
                'resultCode' => $result ?? ($pass ? 'PASS' : 'FAIL'),
                'explanation' => $explanation,
                'relatedRecordType' => $recordType,
                'relatedRecordId' => $recordId,
                'sourcePath' => $sourcePath,
                'blockingFlag' => $blocking,
                'sourceSnapshot' => $snapshot,
            ]);
        };

        $sourceValid = $engagement->source_type === 'PLANNED'
            ? $engagement->iap_plan_engagement_id !== null && $engagement->source_snapshot !== null
            : filled($engagement->special_authority_reference)
                && $engagement->special_authority_document_version_id !== null;
        $add('SOURCE_VALID', 'AUTHORIZATION', 'Engagement source remains valid', $sourceValid,
            $sourceValid ? 'IAP or special-authority lineage is preserved.' : 'The engagement source lineage is incomplete.',
            'AUDIT_ENGAGEMENT', $engagement->id, $this->link($engagement, 'overview'));
        $add('AEO_ISSUED', 'AUTHORIZATION', 'Current AEO is issued',
            $engagement->engagementOrder?->status === 'ISSUED',
            'Authoritative AEO status: '.($engagement->engagementOrder?->status ?? 'MISSING'),
            'AUDIT_ENGAGEMENT_ORDER', $engagement->engagementOrder?->id,
            '/audit-engagement-management/aeo?engagementId='.$engagement->id);
        $add('AEP_APPROVED', 'AUTHORIZATION', 'Current AEP is approved',
            $engagement->engagementPlan?->status === 'APPROVED',
            'Authoritative AEP status: '.($engagement->engagementPlan?->status ?? 'MISSING'),
            'AUDIT_ENGAGEMENT_PLAN', $engagement->engagementPlan?->id,
            '/audit-engagement-management/aep?engagementId='.$engagement->id);
        $add('PROGRAM_CURRENT', 'AUTHORIZATION', 'Current Audit Program is approved and current',
            $program && in_array($program->status, ['APPROVED', 'ACTIVE', 'COMPLETED'], true),
            'Current Audit Program status: '.($program?->status ?? 'MISSING'),
            'AUDIT_PROGRAM', $program?->id,
            '/audit-engagement-management/audit-program?engagementId='.$engagement->id);
        $add('ENTRY_CONFERENCE', 'AUTHORIZATION', 'Entry Conference is completed or validly waived',
            in_array($engagement->entryConference?->status, ['COMPLETED', 'WAIVED'], true),
            'Entry Conference status: '.($engagement->entryConference?->status ?? 'MISSING'),
            'ENTRY_CONFERENCE', $engagement->entryConference?->id,
            "/audit-engagement-management/conferences?engagementId={$engagement->id}");

        $add('PROGRAM_COMPLETED', 'FIELDWORK', 'Audit Program is completed',
            $program?->status === 'COMPLETED',
            'Current Audit Program status: '.($program?->status ?? 'MISSING'),
            'AUDIT_PROGRAM', $program?->id,
            '/audit-engagement-management/audit-program?engagementId='.$engagement->id);
        $add('PROCEDURES_TERMINAL', 'FIELDWORK', 'Required procedures are completed or waived',
            $procedures->isNotEmpty()
                && $procedures->every(fn ($procedure) => in_array($procedure->status, ['COMPLETED', 'WAIVED'], true)),
            $procedures->whereNotIn('status', ['COMPLETED', 'WAIVED'])->count().' nonterminal procedure(s).',
            'AUDIT_PROGRAM', $program?->id,
            '/audit-engagement-management/audit-program?engagementId='.$engagement->id);
        $add('WORKING_PAPERS_TERMINAL', 'FIELDWORK', 'Working Papers are approved, superseded, or voided',
            $engagement->workingPapers->isNotEmpty()
                && $engagement->workingPapers->every(
                    fn ($paper) => in_array($paper->status, ['APPROVED', 'SUPERSEDED', 'VOIDED'], true),
                ),
            $engagement->workingPapers->whereNotIn('status', ['APPROVED', 'SUPERSEDED', 'VOIDED'])->count()
                .' nonterminal Working Paper(s).',
            'WORKING_PAPER', null,
            '/audit-engagement-management/working-papers?engagementId='.$engagement->id);
        $add('EVIDENCE_LOCKED', 'FIELDWORK', 'Relied-upon evidence is available, checksum-valid, and locked',
            $evidenceReady,
            $reliedEvidence->count().' relied-upon evidence record(s) evaluated.',
            'AUDIT_EVIDENCE', null,
            '/audit-engagement-management/working-papers?engagementId='.$engagement->id,
            true, true, null, ['reliedEvidenceIds' => $reliedEvidence->pluck('id')->all()]);
        $add('REVIEW_COMMENTS_RESOLVED', 'FIELDWORK', 'No unresolved required reviewer comments remain',
            $reportCommentsReady,
            $reportCommentsReady ? 'Latest report review actions are not returned.' : 'A current report has a returned latest review action.',
            'AUDIT_REPORT', $issuedReport?->id,
            '/audit-engagement-management/reports?engagementId='.$engagement->id);

        $add('ISSUES_TERMINAL', 'FINDINGS', 'Every issue is dismissed or converted to a Finding',
            $engagement->issues->every(fn ($issue) => in_array($issue->status, ['DISMISSED', 'CONVERTED_TO_FINDING'], true)),
            $engagement->issues->whereNotIn('status', ['DISMISSED', 'CONVERTED_TO_FINDING'])->count().' unresolved issue(s).',
            'AUDIT_ISSUE', null,
            '/audit-engagement-management/issues?engagementId='.$engagement->id);
        $add('FINDINGS_FINALIZED', 'FINDINGS', 'Every reportable Finding is finalized',
            $engagement->findings->where('is_current_revision', true)->every('status', 'FINALIZED'),
            $engagement->findings->where('is_current_revision', true)->where('status', '!=', 'FINALIZED')->count()
                .' nonfinal Finding(s).',
            'AUDIT_FINDING', null,
            '/audit-engagement-management/findings?engagementId='.$engagement->id);
        $add('DIALOGUE_COMPLETE', 'FINDINGS', 'Management-response dialogue is complete or non-response is finalized',
            $responsesReady,
            $responsesReady ? 'Current dialogue records are terminal.' : 'A current management response remains active.',
            'MANAGEMENT_RESPONSE', null,
            '/audit-engagement-management/auditee-responses?engagementId='.$engagement->id);
        $add('EXIT_CONFERENCE', 'FINDINGS', 'Exit Conference is completed or validly waived',
            $engagement->exitConferences->contains(
                fn ($conference) => in_array($conference->status, ['COMPLETED', 'WAIVED'], true),
            ),
            'Terminal Exit Conference status is required.',
            'EXIT_CONFERENCE', $engagement->exitConferences->first()?->id,
            '/audit-engagement-management/conferences?engagementId='.$engagement->id);
        $limitations = filled($assessment?->limitations_summary);
        $add('LIMITATIONS_DISCLOSED', 'FINDINGS', 'Material scope limitations have approved report disclosure',
            ! $limitations,
            $limitations
                ? 'Completion Assessment identifies limitations; confirm disclosure in the issued report.'
                : 'No material scope limitation is recorded.',
            'COMPLETION_ASSESSMENT', $assessment?->id,
            $this->link($engagement, 'completion-assessment'),
            false, false, $limitations ? 'WARNING' : 'PASS');

        $add('FINAL_REPORT_ISSUED', 'REPORTING', 'Final Report is issued',
            $issuedReport !== null,
            'Final Report status: '.($issuedReport?->status ?? 'MISSING'),
            'AUDIT_REPORT', $issuedReport?->id,
            '/audit-engagement-management/reports?engagementId='.$engagement->id);
        $exactIssued = $issuedVersion
            && $issuedVersion->is_locked
            && $issuedVersion->documentVersion
            && filled($issuedVersion->checksum_sha256)
            && hash_equals($issuedVersion->checksum_sha256, $issuedVersion->documentVersion->checksum_sha256)
            && Storage::disk('local')->exists($issuedVersion->documentVersion->storage_path);
        $add('ISSUED_VERSION_EXACT', 'REPORTING', 'Exact issued DocumentVersion and checksum are preserved',
            (bool) $exactIssued,
            $exactIssued ? "DocumentVersion {$issuedVersion->document_version_id} is locked and available." : 'Issued version metadata or file is incomplete.',
            'AUDIT_REPORT_VERSION', $issuedVersion?->id,
            '/audit-engagement-management/reports?engagementId='.$engagement->id,
            true, true, null, [
                'documentVersionId' => $issuedVersion?->document_version_id,
                'checksumSha256' => $issuedVersion?->checksum_sha256,
            ]);
        $add('RECIPIENTS_COMPLETE', 'REPORTING', 'Required recipient issuance records are complete',
            (bool) $recipientReady,
            $issuedVersion?->recipients->count().' recipient record(s) evaluated.',
            'AUDIT_REPORT_VERSION', $issuedVersion?->id,
            '/audit-engagement-management/reports?engagementId='.$engagement->id);
        $newerUnissued = $issuedReport?->versions
            ->where('version_number', '>', $issuedVersion?->version_number ?? 0)
            ->isNotEmpty() ?? false;
        $add('NO_NEWER_UNISSUED', 'REPORTING', 'No newer approved but unissued Final Report version exists',
            ! $newerUnissued,
            $newerUnissued ? 'A newer unissued version exists.' : 'The issued version is current.',
            'AUDIT_REPORT', $issuedReport?->id,
            '/audit-engagement-management/reports?engagementId='.$engagement->id);

        $add('CMS_COMPLETE', 'CMS', 'Recommendations have one successful CMS transfer or authorized exclusion',
            $cmsReady,
            $recommendations->whereIn('status', ['TRANSFERRED', 'EXCLUDED'])->count()
                ." of {$recommendations->count()} recommendation(s) have terminal CMS disposition.",
            'AUDIT_RECOMMENDATION', null,
            '/audit-engagement-management/reports?engagementId='.$engagement->id,
            true, true, null, ['recommendationIds' => $recommendations->pluck('id')->all()]);
        $uniqueCms = $recommendations->where('status', 'TRANSFERRED')->pluck('cms_recommendation_id')->filter();
        $add('CMS_IDEMPOTENT', 'CMS', 'CMS transfer IDs and keys remain unique',
            $uniqueCms->count() === $uniqueCms->unique()->count()
                && $recommendations->pluck('cms_transfer_key')->filter()->count()
                    === $recommendations->pluck('cms_transfer_key')->filter()->unique()->count(),
            'Database transfer IDs and idempotency keys were evaluated.',
            'AUDIT_RECOMMENDATION', null,
            '/audit-engagement-management/reports?engagementId='.$engagement->id);
        $add('CMS_TRANSFER_MANIFEST', 'CMS', 'CMS transfer manifest is reconciled',
            $transferGate['manifestReady'],
            $transferGate['manifestReady']
                ? 'The current CMS transfer manifest is reconciled.'
                : 'Generate and reconcile the current CMS transfer manifest before closure.',
            'AEMS_TRANSFER_MANIFEST', null,
            $this->link($engagement, 'completion-transfer'));
        $add('CMS_TRANSFER_EXCEPTIONS', 'CMS', 'CMS transfer exceptions are resolved',
            (int) $transferGate['openExceptions'] === 0,
            (int) $transferGate['openExceptions'] === 0
                ? 'No open transfer exception remains.'
                : "{$transferGate['openExceptions']} transfer exception(s) remain open.",
            'AEMS_TRANSFER_MANIFEST', null,
            $this->link($engagement, 'completion-transfer'));

        $add('ACTUAL_PERSON_DAYS', 'RESOURCES', 'Actual person-days are recorded',
            (float) $engagement->actual_person_days > 0,
            "Actual person-days: {$engagement->actual_person_days}.",
            'AUDIT_ENGAGEMENT', $engagement->id,
            $this->link($engagement, 'overview'));
        $add('EFFORT_RECONCILIATION', 'RESOURCES', 'Actual person-days are reconciled with the active provider',
            $transferGate['effortReady'],
            $transferGate['effortReady']
                ? 'The active resource provider effort snapshot is available.'
                : 'Generate and resolve the ARMIS effort reconciliation before closure.',
            'AEMS_EFFORT_RECONCILIATION', null,
            $this->link($engagement, 'completion-transfer'));
        $add('ACTUAL_COST', 'RESOURCES', 'Actual cost is recorded when required',
            true,
            'Cost reporting is not configured for this engagement.',
            null, null, null, false, false, 'NOT_APPLICABLE');
        $add('KPI_RESULTS', 'RESOURCES', 'KPI results are recorded when KPIs exist',
            true,
            'No engagement KPI infrastructure is configured.',
            null, null, null, false, false, 'NOT_APPLICABLE');
        $add('MILESTONES', 'RESOURCES', 'Milestone results are complete',
            $procedures->isNotEmpty()
                && $procedures->every(fn ($procedure) => in_array($procedure->status, ['COMPLETED', 'WAIVED'], true)),
            'Audit Program procedure milestones are the current authoritative milestone source.',
            'AUDIT_PROGRAM', $program?->id,
            '/audit-engagement-management/audit-program?engagementId='.$engagement->id);
        $add('ASSESSMENT_APPROVED', 'RESOURCES', 'Current Completion Assessment is approved',
            $assessment?->status_code === 'APPROVED' && $assessment->is_current_revision,
            'Current Completion Assessment status: '.($assessment?->status_code ?? 'MISSING'),
            'COMPLETION_ASSESSMENT', $assessment?->id,
            $this->link($engagement, 'completion-assessment'));

        $add('DOCUMENT_INDEX', 'RECORDS', 'Final document index is complete',
            $documentReadiness['ready'],
            $documentReadiness['ready']
                ? "{$documentReadiness['included']} immutable record(s) included."
                : implode(' ', $documentReadiness['blockers']),
            'FINAL_DOCUMENT_INDEX', $engagement->id,
            $this->link($engagement, 'document-index'),
            true, true, null, $documentReadiness);
        $add('RETENTION_METADATA', 'RECORDS', 'Retention classification, rule, and custodian are approved',
            $retentionReadiness['ready'],
            $retentionReadiness['ready'] ? 'Approved retention metadata is complete.' : implode(' ', $retentionReadiness['blockers']),
            'ENGAGEMENT_RETENTION', $engagement->retentionRecord?->id,
            $this->link($engagement, 'retention'));
        $legalHold = $engagement->retentionRecord?->legal_hold_flag ?? false;
        $add('LEGAL_HOLD', 'RECORDS', 'Legal hold is preserved without permitting disposition',
            ! $legalHold,
            $legalHold
                ? 'Legal hold is active; closure cannot bypass the unresolved records control.'
                : 'No legal hold is recorded.',
            'ENGAGEMENT_RETENTION', $engagement->retentionRecord?->id,
            $this->link($engagement, 'retention'),
            true, true, null);

        $overdueMilestones = $engagement->milestones
            ->filter(fn ($milestone): bool => $milestone->due_date
                && $milestone->due_date->lt(today())
                && ! in_array($milestone->status_code, ['COMPLETED', 'WAIVED', 'CANCELLED'], true));
        $add('AUDIT_CALENDAR', 'RECORDS', 'Required Audit Calendar milestones are complete or not overdue',
            $overdueMilestones->isEmpty(),
            $overdueMilestones->isEmpty()
                ? 'No required overdue Audit Calendar milestone remains.'
                : $overdueMilestones->count().' required milestone(s) are overdue.',
            'AEMS_MILESTONE', $overdueMilestones->first()?->id,
            $this->link($engagement, 'calendar'));

        $add('NO_ACTIVE_CHILD_WORKFLOW', 'WORKFLOW', 'No active child approval workflow remains',
            ! $activeChild,
            $activeChild ? 'At least one AEMS child record is in an active workflow status.' : 'All tracked child records are terminal.',
            'AUDIT_ENGAGEMENT', $engagement->id,
            $this->link($engagement, 'lifecycle'));
        $add('NO_ACTIVE_CORE_TASK', 'WORKFLOW', 'No unresolved required Core workflow task remains',
            ! $activeCoreWorkflow,
            $activeCoreWorkflow ? 'An active Core workflow instance remains.' : 'No active Core AEMS workflow instance was found.',
            'AUDIT_ENGAGEMENT', $engagement->id,
            $this->link($engagement, 'lifecycle'));
        $add('ENGAGEMENT_CLOSURE_REVIEW', 'WORKFLOW', 'Engagement is in CLOSURE_REVIEW',
            $engagement->status === 'CLOSURE_REVIEW',
            "Current engagement status: {$engagement->status}.",
            'AUDIT_ENGAGEMENT', $engagement->id,
            $this->link($engagement, 'lifecycle'));
        $add('ENGAGEMENT_AVAILABLE', 'WORKFLOW', 'Engagement is active, not archived, suspended, or cancelled',
            $engagement->is_active && ! $engagement->trashed()
                && ! in_array($engagement->status, ['SUSPENDED', 'CANCELLED'], true),
            'Current engagement availability and soft-deletion state evaluated.',
            'AUDIT_ENGAGEMENT', $engagement->id,
            $this->link($engagement, 'overview'));

        return $items->values()->all();
    }

    /** @param list<array<string, mixed>> $items
     * @return array{ready: bool, percentage: int, blockers: list<array<string, mixed>>, warnings: list<array<string, mixed>>}
     */
    public function summary(array $items): array
    {
        $collection = collect($items);
        $required = $collection->where('requiredFlag', true);
        $passing = $required->whereIn('resultCode', ['PASS', 'NOT_APPLICABLE'])->count();
        $blockers = $collection->filter(
            fn (array $item): bool => $item['blockingFlag']
                && ! in_array($item['resultCode'], ['PASS', 'NOT_APPLICABLE'], true),
        )->values();
        $warnings = $collection->whereIn('resultCode', ['WARNING'])->values();

        return [
            'ready' => $blockers->isEmpty(),
            'percentage' => (int) round(($passing / max($required->count(), 1)) * 100),
            'blockers' => $blockers->all(),
            'warnings' => $warnings->all(),
        ];
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'engagementOrder',
            'engagementPlan',
            'programs.procedures',
            'workingPapers',
            'evidence.documentVersion',
            'evidence.workingPaperVersions',
            'evidence.findings',
            'issues',
            'findings.recommendations',
            'findings.managementResponses',
            'entryConference',
            'exitConferences',
            'reports.versions',
            'reports.currentVersion.documentVersion',
            'reports.currentVersion.recipients',
            'reports.reviewComments',
            'currentCompletionAssessment',
            'retentionRecord',
            'milestones',
            'closure',
        ];
    }

    private function activeChildWorkflow(
        AuditEngagement $engagement,
        mixed $program,
    ): bool {
        if ($engagement->engagementOrder?->status !== 'ISSUED'
            || $engagement->engagementPlan?->status !== 'APPROVED'
            || $program?->status !== 'COMPLETED') {
            return true;
        }
        if ($engagement->workingPapers->contains(
            fn ($paper) => ! in_array($paper->status, ['APPROVED', 'SUPERSEDED', 'VOIDED'], true),
        )) {
            return true;
        }
        if ($engagement->issues->contains(
            fn ($issue) => ! in_array($issue->status, ['DISMISSED', 'CONVERTED_TO_FINDING'], true),
        )) {
            return true;
        }
        if ($engagement->findings->where('is_current_revision', true)->contains('status', '!=', 'FINALIZED')) {
            return true;
        }
        if ($engagement->reports->contains(
            fn ($report) => $report->report_stage === 'FINAL_REPORT' && $report->status !== 'ISSUED',
        )) {
            return true;
        }

        return false;
    }

    private function link(AuditEngagement $engagement, string $tab): string
    {
        return "/audit-engagement-management/{$engagement->id}?tab={$tab}";
    }
}
