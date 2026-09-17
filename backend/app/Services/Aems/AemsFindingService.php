<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\AuditEvidence;
use App\Models\AuditFinding;
use App\Models\AuditIssue;
use App\Models\AuditProgramProcedure;
use App\Models\AuditRecommendation;
use App\Models\AemsDialogueAttachment;
use App\Models\AemsFieldworkRecord;
use App\Models\AemsFieldworkRecordVersion;
use App\Models\AemsFindingTransmittal;
use App\Models\AemsFindingTransmittalEvent;
use App\Models\AemsFindingTransmittalRecipient;
use App\Models\AuditorRejoinder;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\EngagementEvent;
use App\Models\ManagementResponse;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\User;
use App\Models\WorkingPaperVersion;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Implements the supported issue-to-finding lifecycle, formal communication,
 * management dialogue, and immutable final recommendation contract.
 */
class AemsFindingService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsEvidenceRequestService $evidenceRequests,
        private readonly AemsSupport $support,
        private readonly RuntimeConfiguration $runtime,
        private readonly AemsNotificationService $notifications,
        private readonly AemsWorkQueueService $workQueue,
    ) {}

    /** @return list<array<string, mixed>> */
    public function engagements(Request $request): array
    {
        $query = $request->user()->hasPermission('access.auditee_scope')
            ? AuditEngagement::query()->whereHas(
                'findings',
                fn ($findings) => $findings
                    ->visibleTo($request->user())
                    ->where('is_current_revision', true),
            )
            : $this->access->visibleEngagements(AuditEngagement::query(), $request->user());

        return $query
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
        $engagement->loadMissing('offices:id,code,name');
        $canViewIssues = $request->user()->hasPermission('aems.issue.view')
            && ! $request->user()->hasPermission('access.auditee_scope');
        $issues = $canViewIssues
            ? AuditIssue::query()
                ->visibleTo($request->user())
                ->where('audit_engagement_id', $engagement->id)
                ->with($this->issueRelations())
                ->orderBy('issue_code')
                ->get()
            : collect();
        $findings = AuditFinding::query()
            ->visibleTo($request->user())
            ->where('audit_engagement_id', $engagement->id)
            ->where('is_current_revision', true)
            ->with($this->findingRelations())
            ->orderBy('finding_code')
            ->get();
        $workingPaperVersions = WorkingPaperVersion::query()
            ->whereHas('workingPaper', fn ($paper) => $paper
                ->where('audit_engagement_id', $engagement->id)
                ->where('status', 'APPROVED'))
            ->with('workingPaper:id,working_paper_code,title,status')
            ->orderBy('working_paper_id')
            ->orderByDesc('version_number')
            ->get();
        $procedures = AuditProgramProcedure::query()
            ->whereHas('program', fn ($program) => $program
                ->where('audit_engagement_id', $engagement->id)
                ->where('is_current_revision', true)
                ->whereNull('deleted_at'))
            ->orderBy('audit_program_id')
            ->orderBy('sequence_number')
            ->get(['id', 'audit_program_id', 'procedure_code', 'objective', 'audit_criteria', 'status']);
        $evidence = AuditEvidence::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('is_current_revision', true)
            ->whereIn('status', ['VERIFIED', 'LOCKED'])
            ->with(['currentAssessment.evidence', 'currentAssessment.documentVersion'])
            ->orderBy('evidence_code')
            ->get(['id', 'evidence_code', 'title', 'status', 'version_number']);

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
            ],
            'issues' => $issues->map(fn (AuditIssue $issue): array => $this->issueData($issue))->values(),
            'findings' => $findings->map(fn (AuditFinding $finding): array => $this->findingData($finding))->values(),
            'riskRatings' => $this->masterItems('RISK_LEVEL'),
            'offices' => $engagement->offices
                ->map(fn ($office): array => $office->only(['id', 'code', 'name']))
                ->values(),
            'workingPaperVersions' => $workingPaperVersions->map(
                fn (WorkingPaperVersion $version): array => [
                    'id' => $version->id,
                    'versionNumber' => $version->version_number,
                    'workingPaper' => [
                        'id' => $version->workingPaper->id,
                        'workingPaperCode' => $version->workingPaper->working_paper_code,
                        'title' => $version->workingPaper->title,
                        'status' => $version->workingPaper->status,
                    ],
                ],
            )->values(),
            'procedures' => $procedures->map(fn (AuditProgramProcedure $procedure): array => [
                'id' => $procedure->id,
                'procedureCode' => $procedure->procedure_code,
                'objective' => $procedure->objective,
                'auditCriteria' => $procedure->audit_criteria,
                'status' => $procedure->status,
            ])->values(),
            'evidence' => $evidence->map(fn (AuditEvidence $item): array => [
                'id' => $item->id,
                'evidenceCode' => $item->evidence_code,
                'title' => $item->title,
                'status' => $item->status,
                'versionNumber' => $item->version_number,
                'assessment' => $item->currentAssessment
                    ? $this->evidenceRequests->assessmentData($item->currentAssessment)
                    : null,
            ])->values(),
            'agreementPositions' => ManagementResponse::AGREEMENT_POSITIONS,
            'rejoinderDispositions' => AuditorRejoinder::DISPOSITIONS,
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function createIssue(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): AuditIssue {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.issue.create',
        );

        $issue = DB::transaction(function () use ($request, $engagement, $attributes): AuditIssue {
            $this->ensureOffice($engagement, (int) $attributes['responsibleOfficeId']);
            $this->ensureRiskRating((int) $attributes['riskRatingId']);
            $workingPapers = $this->workingPaperVersions(
                $engagement,
                $attributes['workingPaperVersionIds'] ?? [],
            );
            $evidence = $this->evidence($engagement, $attributes['evidenceIds'] ?? []);
            $issue = AuditIssue::query()->create([
                'audit_engagement_id' => $engagement->id,
                'issue_code' => $this->nextIssueCode($engagement),
                ...$this->issueAttributes($attributes),
                'status' => 'DRAFT',
                'raised_by' => $request->user()->id,
                'lock_version' => 1,
            ]);
            $issue->workingPaperVersions()->sync($workingPapers->modelKeys());
            $issue->evidence()->sync($evidence->modelKeys());
            $this->record(
                $request,
                $engagement,
                'AUDIT_ISSUE',
                $issue->id,
                $issue->issue_code,
                'ISSUE_CREATED',
                null,
                'DRAFT',
                null,
                $this->issueAudit($issue),
            );

            return $issue;
        }, 3);

        return $this->loadIssue($issue);
    }

    /** @param array<string, mixed> $attributes */
    public function updateIssue(
        Request $request,
        AuditEngagement $engagement,
        AuditIssue $issue,
        array $attributes,
    ): AuditIssue {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.issue.create',
        );

        $issue = DB::transaction(function () use ($request, $engagement, $issue, $attributes): AuditIssue {
            $locked = $this->lockIssue($engagement, $issue, (int) $attributes['lockVersion']);
            if ($locked->status !== 'DRAFT') {
                throw ValidationException::withMessages([
                    'status' => ['Only a draft issue can be edited.'],
                ]);
            }
            $this->ensureOffice($engagement, (int) $attributes['responsibleOfficeId']);
            $this->ensureRiskRating((int) $attributes['riskRatingId']);
            $workingPapers = $this->workingPaperVersions(
                $engagement,
                $attributes['workingPaperVersionIds'] ?? [],
            );
            $evidence = $this->evidence($engagement, $attributes['evidenceIds'] ?? []);
            $before = $this->issueAudit($locked);
            $locked->update([
                ...$this->issueAttributes($attributes),
                'lock_version' => $locked->lock_version + 1,
            ]);
            $locked->workingPaperVersions()->sync($workingPapers->modelKeys());
            $locked->evidence()->sync($evidence->modelKeys());
            $this->record(
                $request,
                $engagement,
                'AUDIT_ISSUE',
                $locked->id,
                $locked->issue_code,
                'ISSUE_UPDATED',
                'DRAFT',
                'DRAFT',
                $before,
                $this->issueAudit($locked),
            );

            return $locked;
        }, 3);

        return $this->loadIssue($issue);
    }

    /**
     * @return AuditIssue|AuditFinding
     */
    public function transitionIssue(
        Request $request,
        AuditEngagement $engagement,
        AuditIssue $issue,
        string $action,
        int $lockVersion,
        ?string $comment,
        array $details = [],
    ): AuditIssue|AuditFinding {
        $permission = match ($action) {
            'SUBMIT' => 'aems.issue.create',
            'VALIDATE' => 'aems.issue.validate',
            'DISMISS' => 'aems.issue.dismiss',
            'CONVERT' => 'aems.issue.convert',
            'MERGE' => 'aems.issue.merge',
            'RESOLVE' => 'aems.issue.resolve',
            'OBSERVE' => 'aems.issue.observe',
            'REFER' => 'aems.issue.refer',
            'CLOSE_WITHOUT_FINDING' => 'aems.issue.close_without_finding',
            'WITHDRAW' => 'aems.issue.withdraw',
            default => throw ValidationException::withMessages(['action' => ['Unsupported issue action.']]),
        };
        $originator = $action === 'SUBMIT' ? null : $issue->raised_by;
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            $permission,
            $originator,
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $issue,
            $action,
            $lockVersion,
            $comment,
            $details,
        ): AuditIssue|AuditFinding {
            $locked = $this->lockIssue($engagement, $issue, $lockVersion);
            if ($action === 'CONVERT' && $locked->status === 'CONVERTED_TO_FINDING') {
                return $this->loadFinding($locked->finding()->firstOrFail());
            }
            $from = $locked->status;
            $to = match ($action) {
                'SUBMIT' => $from === 'DRAFT' ? 'SUBMITTED' : null,
                'VALIDATE' => $from === 'SUBMITTED' ? 'VALIDATED' : null,
                'DISMISS' => $from === 'VALIDATED' ? 'DISMISSED' : null,
                'CONVERT' => $from === 'VALIDATED' ? 'CONVERTED_TO_FINDING' : null,
                'MERGE', 'RESOLVE', 'OBSERVE', 'REFER', 'CLOSE_WITHOUT_FINDING'
                    => $from === 'VALIDATED' ? 'DISMISSED' : null,
                'WITHDRAW' => in_array($from, ['DRAFT', 'SUBMITTED', 'VALIDATED'], true) ? 'WITHDRAWN' : null,
            };
            if (! $to) {
                throw ValidationException::withMessages([
                    'action' => ["{$action} is not available while the issue is {$from}."],
                ]);
            }
            $locked->loadMissing(['workingPaperVersions.workingPaper', 'evidence']);
            if ($action === 'SUBMIT') {
                $this->ensureIssueSupport($locked, false);
            }
            if (in_array($action, ['VALIDATE', 'CONVERT'], true)) {
                $this->ensureIssueSupport($locked, true);
            }
            if (in_array($action, ['DISMISS', 'MERGE', 'RESOLVE', 'OBSERVE', 'REFER', 'CLOSE_WITHOUT_FINDING', 'WITHDRAW'], true)
                && ! $comment) {
                throw ValidationException::withMessages([
                    'comment' => ['A disposition reason is required.'],
                ]);
            }
            if ($action === 'MERGE') {
                $targetId = (int) ($details['mergedIntoIssueId'] ?? 0);
                if (! $targetId || $targetId === (int) $locked->id) {
                    throw ValidationException::withMessages(['mergedIntoIssueId' => ['Select a different issue in this engagement.']]);
                }
                $target = AuditIssue::query()->where('audit_engagement_id', $engagement->id)->find($targetId);
                if (! $target
                    || (AuditIssue::STATUS_COMPATIBILITY[$target->status]['terminal'] ?? false)) {
                    throw ValidationException::withMessages(['mergedIntoIssueId' => ['The merge target must be an active issue in this engagement.']]);
                }
            }
            if ($action === 'REFER' && blank($details['referredTo'] ?? null)) {
                throw ValidationException::withMessages(['referredTo' => ['A referral destination is required.']]);
            }
            $before = $this->issueAudit($locked);
            $changes = [
                'status' => $to,
                'lock_version' => $locked->lock_version + 1,
            ];
            if ($action === 'SUBMIT') {
                $changes['submitted_at'] = now();
            } elseif ($action === 'VALIDATE') {
                $changes['reviewer_id'] = $request->user()->id;
                $changes['validated_by'] = $request->user()->id;
                $changes['validated_at'] = now();
            } elseif ($action === 'DISMISS') {
                $changes['dismissed_by'] = $request->user()->id;
                $changes['dismissed_at'] = now();
                $changes['dismissal_reason'] = $comment;
                $changes['disposition'] = 'DISMISSED';
                $changes['disposition_reason'] = $comment;
                $changes['disposition_recorded_by'] = $request->user()->id;
                $changes['disposition_recorded_at'] = now();
            } elseif ($action === 'CONVERT') {
                $changes['converted_by'] = $request->user()->id;
                $changes['converted_at'] = now();
                $changes['disposition'] = 'CONVERTED_TO_FINDING';
            } elseif ($action === 'WITHDRAW') {
                $changes['withdrawn_by'] = $request->user()->id;
                $changes['withdrawn_at'] = now();
                $changes['withdrawal_reason'] = $comment;
                $changes['disposition'] = 'WITHDRAWN';
                $changes['disposition_reason'] = $comment;
                $changes['disposition_recorded_by'] = $request->user()->id;
                $changes['disposition_recorded_at'] = now();
            } else {
                $changes['disposition'] = match ($action) {
                    'MERGE' => 'MERGED',
                    'RESOLVE' => 'RESOLVED_DURING_AUDIT',
                    'OBSERVE' => 'OBSERVATION',
                    'REFER' => 'REFERRED',
                    'CLOSE_WITHOUT_FINDING' => 'CLOSED_WITHOUT_FINDING',
                };
                $changes['disposition_reason'] = $comment;
                $changes['disposition_recorded_by'] = $request->user()->id;
                $changes['disposition_recorded_at'] = now();
                $changes['resolution_details'] = $details['resolutionDetails'] ?? null;
                $changes['referred_to'] = $details['referredTo'] ?? null;
                $changes['merged_into_issue_id'] = $details['mergedIntoIssueId'] ?? null;
                $changes['dismissed_by'] = $request->user()->id;
                $changes['dismissed_at'] = now();
                $changes['dismissal_reason'] = $comment;
            }
            $locked->update($changes);

            $finding = null;
            if ($action === 'CONVERT') {
                $finding = $this->findingFromIssue($request, $engagement, $locked);
            }
            $this->record(
                $request,
                $engagement,
                'AUDIT_ISSUE',
                $locked->id,
                $locked->issue_code,
                "ISSUE_{$action}",
                $from,
                $to,
                $before,
                $this->issueAudit($locked),
                $comment,
            );
            if (in_array($action, ['DISMISS', 'CONVERT', 'MERGE', 'RESOLVE', 'OBSERVE', 'REFER', 'CLOSE_WITHOUT_FINDING', 'WITHDRAW'], true)) {
                $this->notifications->issueDisposition($request, $engagement, $locked, $action);
            }

            return $finding ? $this->loadFinding($finding) : $this->loadIssue($locked);
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function createFinding(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): AuditFinding {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.finding.create',
        );

        $finding = DB::transaction(function () use ($request, $engagement, $attributes): AuditFinding {
            $this->ensureOffice($engagement, (int) $attributes['responsibleOfficeId']);
            $this->ensureRiskRating((int) $attributes['riskRatingId']);
            $workingPapers = $this->workingPaperVersions(
                $engagement,
                $attributes['workingPaperVersionIds'] ?? [],
            );
            $evidence = $this->evidence($engagement, $attributes['evidenceIds'] ?? []);
            $fieldworkLinks = $this->fieldworkLinks($engagement, $attributes);
            $procedureLinks = $this->procedureLinks(
                $engagement,
                $attributes,
                $workingPapers,
                $fieldworkLinks,
                (int) $request->user()->id,
            );
            $sourceIssue = null;
            if (filled($attributes['sourceIssueId'] ?? null)) {
                $sourceIssue = AuditIssue::query()
                    ->where('audit_engagement_id', $engagement->id)
                    ->whereKey((int) $attributes['sourceIssueId'])
                    ->first();
                if (! $sourceIssue || $sourceIssue->status !== 'VALIDATED') {
                    throw ValidationException::withMessages([
                        'sourceIssueId' => ['A Finding may reference only a validated Issue from this engagement.'],
                    ]);
                }
            } elseif (blank($attributes['directAuthorityReason'] ?? null)
                || blank($attributes['directAuthorityReference'] ?? null)) {
                throw ValidationException::withMessages([
                    'directAuthorityReference' => ['Direct Findings require an authorized reason and authority reference.'],
                ]);
            }
            $finding = AuditFinding::query()->create([
                'finding_family_uuid' => (string) Str::uuid(),
                'revision_number' => 0,
                'revision_type' => 'ORIGINAL',
                'is_current_revision' => true,
                'audit_engagement_id' => $engagement->id,
                'source_issue_id' => $sourceIssue?->id,
                'direct_creation_reason' => $sourceIssue ? null : $attributes['directAuthorityReason'],
                'direct_creation_authority' => $sourceIssue ? null : trim((string) $attributes['directAuthorityReference']),
                'direct_creation_by' => $sourceIssue ? null : $request->user()->id,
                'direct_creation_at' => $sourceIssue ? null : now(),
                'finding_code' => $this->nextFindingCode($engagement),
                ...$this->findingAttributes($attributes),
                'status' => 'DRAFT',
                'authored_by' => $request->user()->id,
                'lock_version' => 1,
            ]);
            $finding->workingPaperVersions()->sync($workingPapers->modelKeys());
            $finding->evidence()->sync($evidence->modelKeys());
            $finding->fieldworkRecordVersions()->sync($fieldworkLinks);
            $finding->procedures()->sync($procedureLinks);
            $this->recordFinding($request, $engagement, $finding, 'CREATED', null, 'DRAFT');

            return $finding;
        }, 3);

        return $this->loadFinding($finding);
    }

    /** @param array<string, mixed> $attributes */
    public function updateFinding(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        array $attributes,
    ): AuditFinding {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.finding.create',
        );

        $finding = DB::transaction(function () use ($request, $engagement, $finding, $attributes): AuditFinding {
            $locked = $this->lockFinding($engagement, $finding, (int) $attributes['lockVersion']);
            if ($locked->status !== 'DRAFT') {
                throw ValidationException::withMessages([
                    'status' => ['Finding content can be edited only while it is a draft.'],
                ]);
            }
            $this->ensureOffice($engagement, (int) $attributes['responsibleOfficeId']);
            $this->ensureRiskRating((int) $attributes['riskRatingId']);
            $workingPapers = $this->workingPaperVersions(
                $engagement,
                $attributes['workingPaperVersionIds'] ?? [],
            );
            $evidence = $this->evidence($engagement, $attributes['evidenceIds'] ?? []);
            $fieldworkLinks = array_key_exists('fieldworkRecordVersionIds', $attributes)
                || array_key_exists('fieldworkRecordIds', $attributes)
                ? $this->fieldworkLinks($engagement, $attributes)
                : null;
            $procedureLinks = array_key_exists('procedureIds', $attributes)
                || array_key_exists('workingPaperVersionIds', $attributes)
                || $fieldworkLinks !== null
                ? $this->procedureLinks(
                    $engagement,
                    $attributes,
                    $workingPapers,
                    $fieldworkLinks ?? $locked->fieldworkRecordVersions->mapWithKeys(
                        fn (AemsFieldworkRecordVersion $version): array => [
                            $version->id => ['fieldwork_record_id' => $version->fieldwork_record_id],
                        ],
                    )->all(),
                    (int) $request->user()->id,
                )
                : null;
            $before = $this->findingAudit($locked);
            $locked->update([
                ...$this->findingAttributes($attributes),
                'lock_version' => $locked->lock_version + 1,
            ]);
            $locked->workingPaperVersions()->sync($workingPapers->modelKeys());
            $locked->evidence()->sync($evidence->modelKeys());
            if ($fieldworkLinks !== null) {
                $locked->fieldworkRecordVersions()->sync($fieldworkLinks);
            }
            if ($procedureLinks !== null) {
                $locked->procedures()->sync($procedureLinks);
            }
            $this->recordFinding(
                $request,
                $engagement,
                $locked,
                'UPDATED',
                'DRAFT',
                'DRAFT',
                $before,
            );

            return $locked;
        }, 3);

        return $this->loadFinding($finding);
    }

    /**
     * Creates a new immutable finding revision. The prior revision is never
     * overwritten; finalized content and recommendation snapshots remain on
     * the original row.
     *
     * @return AuditFinding the new current revision
     */
    public function reviseFinding(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        string $action,
        int $lockVersion,
        string $reason,
    ): AuditFinding {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.finding.revise',
        );
        if (! in_array($action, ['CORRECT', 'AMEND', 'SUPERSEDE', 'WITHDRAW'], true)) {
            throw ValidationException::withMessages(['action' => ['Unsupported finding revision action.']]);
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => ['A revision reason is required.']]);
        }

        $revision = DB::transaction(function () use ($request, $engagement, $finding, $action, $lockVersion, $reason): AuditFinding {
            $source = $this->lockFinding($engagement, $finding, $lockVersion);
            if (! $source->is_current_revision || in_array($source->status, ['WITHDRAWN', 'SUPERSEDED'], true)) {
                throw ValidationException::withMessages(['finding' => ['Only the current finding revision may be revised.']]);
            }
            $source->loadMissing($this->findingRelations());
            $snapshot = [
                'finding' => $this->findingContent($source),
                'workingPaperVersionIds' => $source->workingPaperVersions->modelKeys(),
                'evidenceIds' => $source->evidence->modelKeys(),
                'fieldworkRecordVersionIds' => $source->fieldworkRecordVersions->modelKeys(),
                'procedureIds' => $source->procedures->modelKeys(),
                'recommendations' => $source->recommendations->map(
                    fn (AuditRecommendation $recommendation): array => $this->recommendationData($recommendation),
                )->values()->all(),
                'capturedAt' => now()->toIso8601String(),
            ];
            // Release the engagement/code current-revision index before the
            // replacement row is inserted. The transaction keeps this atomic.
            // This is the one controlled internal transition allowed for a
            // revision. It bypasses the public immutable model guard so the
            // partial current-revision index is released before insertion.
            DB::table('audit_findings')->where('id', $source->id)->update([
                'is_current_revision' => false,
                'lock_version' => $source->lock_version + 1,
                'updated_at' => now(),
            ]);
            if (DB::table('audit_findings')->where('id', $source->id)->where('is_current_revision', true)->exists()) {
                throw new \LogicException('The source finding revision could not be released before creating its replacement.');
            }
            $newStatus = $action === 'WITHDRAW' ? 'WITHDRAWN' : 'DRAFT';
            $new = AuditFinding::query()->create([
                'finding_family_uuid' => $source->finding_family_uuid,
                'revision_number' => $source->revision_number + 1,
                'revision_type' => match ($action) {
                    'CORRECT' => 'CORRECTION',
                    'AMEND' => 'AMENDMENT',
                    'SUPERSEDE' => 'SUPERSESSION',
                    'WITHDRAW' => 'WITHDRAWAL',
                },
                'revision_reason' => trim($reason),
                'revision_snapshot' => $snapshot,
                'supersedes_finding_id' => $source->id,
                'is_current_revision' => true,
                'audit_engagement_id' => $engagement->id,
                'source_issue_id' => null,
                'finding_code' => $source->finding_code,
                'title' => $source->title,
                'criteria' => $source->criteria,
                'condition' => $source->condition,
                'cause' => $source->cause,
                'effect' => $source->effect,
                'conclusion' => $source->conclusion,
                'significance_classification' => $source->significance_classification,
                'effect_classification' => $source->effect_classification,
                'no_recommendation_reason' => $source->no_recommendation_reason,
                'risk_rating_id' => $source->risk_rating_id,
                'responsible_office_id' => $source->responsible_office_id,
                'status' => $newStatus,
                'authored_by' => $request->user()->id,
                'lock_version' => 1,
                'withdrawn_at' => $action === 'WITHDRAW' ? now() : null,
                'withdrawn_by' => $action === 'WITHDRAW' ? $request->user()->id : null,
            ]);
            $new->workingPaperVersions()->sync($source->workingPaperVersions->modelKeys());
            $new->evidence()->sync($source->evidence->modelKeys());
            $new->fieldworkRecordVersions()->sync(
                $source->fieldworkRecordVersions->mapWithKeys(
                    fn (AemsFieldworkRecordVersion $version): array => [
                        $version->id => ['fieldwork_record_id' => $version->fieldwork_record_id],
                    ],
                )->all(),
            );
            $new->procedures()->sync(
                $source->procedures->mapWithKeys(
                    fn (AuditProgramProcedure $procedure): array => [
                        $procedure->id => [
                            'criteria_reference' => $procedure->pivot->criteria_reference,
                            'traceability_note' => $procedure->pivot->traceability_note,
                            'linked_by' => $procedure->pivot->linked_by,
                        ],
                    ],
                )->all(),
            );

            $this->recordFinding($request, $engagement, $new, 'REVISION_CREATED', null, $newStatus, null, $reason);
            $this->recordFinding($request, $engagement, $source, 'REVISION_SUPERSEDED', $source->status, $source->status, null, $reason);
            $this->notifications->findingRevision($request, $engagement, $new);

            return $new;
        }, 3);

        return $this->loadFinding($revision);
    }

    /**
     * @param array<string, mixed> $details
     */
    public function transitionFinding(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        string $action,
        int $lockVersion,
        array $details,
    ): AuditFinding {
        $permission = match ($action) {
            'SUBMIT' => 'aems.finding.create',
            'VALIDATE' => 'aems.finding.validate',
            'COMMUNICATE', 'REQUEST_RESPONSE' => 'aems.finding.communicate',
            'RECORD_NON_RESPONSE' => 'aems.management-response.request_clarification',
            'FINALIZE' => 'aems.finding.finalize',
            default => throw ValidationException::withMessages(['action' => ['Unsupported finding action.']]),
        };
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            $permission,
            $action === 'SUBMIT' ? null : $finding->authored_by,
        );

        $finding = DB::transaction(function () use (
            $request,
            $engagement,
            $finding,
            $action,
            $lockVersion,
            $details,
        ): AuditFinding {
            $locked = $this->lockFinding($engagement, $finding, $lockVersion);
            $from = $locked->status;
            $to = match ($action) {
                'SUBMIT' => $from === 'DRAFT' ? 'PENDING_REVIEW' : null,
                'VALIDATE' => $from === 'PENDING_REVIEW' ? 'VALIDATED' : null,
                'COMMUNICATE' => $from === 'VALIDATED' ? 'COMMUNICATED' : null,
                'REQUEST_RESPONSE' => $from === 'COMMUNICATED' ? 'AWAITING_MANAGEMENT_RESPONSE' : null,
                'RECORD_NON_RESPONSE' => $from === 'AWAITING_MANAGEMENT_RESPONSE' ? 'UNDER_DIALOGUE' : null,
                'FINALIZE' => $from === 'UNDER_DIALOGUE' ? 'FINALIZED' : null,
            };
            if (! $to) {
                throw ValidationException::withMessages([
                    'action' => ["{$action} is not available while the finding is {$from}."],
                ]);
            }
            $locked->loadMissing($this->findingRelations());
            if (in_array($action, ['SUBMIT', 'VALIDATE', 'FINALIZE'], true)) {
                $this->ensureFindingComplete(
                    $locked,
                    in_array($action, ['VALIDATE', 'FINALIZE'], true),
                );
            }
            $before = $this->findingAudit($locked);
            $changes = ['status' => $to, 'lock_version' => $locked->lock_version + 1];
            if ($action === 'SUBMIT') {
                $changes['submitted_at'] = now();
            } elseif ($action === 'VALIDATE') {
                $changes['reviewer_id'] = $request->user()->id;
                $changes['validated_by'] = $request->user()->id;
                $changes['validated_at'] = now();
                foreach ($locked->evidence as $evidence) {
                    if ($evidence->status === 'VERIFIED') {
                        $evidence->update([
                            'status' => 'LOCKED',
                            'locked_at' => now(),
                            'lock_version' => $evidence->lock_version + 1,
                        ]);
                    }
                }
            } elseif ($action === 'COMMUNICATE') {
                $recipients = collect($details['recipients'] ?? [])
                    ->map(fn ($recipient): string => trim((string) $recipient))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                if ($recipients === []) {
                    throw ValidationException::withMessages([
                        'recipients' => ['At least one communication recipient is required.'],
                    ]);
                }
                if (empty($details['dueDate'])) {
                    throw ValidationException::withMessages([
                        'dueDate' => ['A management-response due date is required.'],
                    ]);
                }
                $changes['communicated_at'] = now();
                $changes['communicated_by'] = $request->user()->id;
                $changes['management_response_due_date'] = $details['dueDate'];
                $changes['communicated_snapshot'] = [
                    'finding' => $this->findingContent($locked),
                    'recommendations' => $locked->recommendations->map(
                        fn (AuditRecommendation $recommendation): array => $this->recommendationData($recommendation),
                    )->values()->all(),
                    'workingPaperVersionIds' => $locked->workingPaperVersions->modelKeys(),
                    'evidenceIds' => $locked->evidence->modelKeys(),
                    'fieldworkRecordVersionIds' => $locked->fieldworkRecordVersions->modelKeys(),
                    'procedureIds' => $locked->procedures->modelKeys(),
                    'recipients' => $recipients,
                    'confidentiality' => $details['confidentiality'] ?? 'INTERNAL',
                    'communicatedAt' => now()->toIso8601String(),
                ];
            } elseif ($action === 'RECORD_NON_RESPONSE') {
                if (empty($details['comment'])) {
                    throw ValidationException::withMessages([
                        'comment' => ['A non-response explanation is required.'],
                    ]);
                }
                $changes['non_response_reason'] = $details['comment'];
                $changes['non_response_recorded_at'] = now();
                $changes['non_response_recorded_by'] = $request->user()->id;
            } elseif ($action === 'FINALIZE') {
                $hasDialogue = $locked->managementResponses
                    ->flatMap->rejoinders
                    ->contains('status', 'DIALOGUE_FINALIZED');
                if (! $hasDialogue && ! $locked->non_response_reason) {
                    throw ValidationException::withMessages([
                        'dialogue' => ['Finalize a rejoinder or document management non-response first.'],
                    ]);
                }
                $this->finalizeRecommendations($request, $locked);
                $locked->load('recommendations.responsibleOffice');
                $changes['finalized_at'] = now();
                $changes['finalized_by'] = $request->user()->id;
                $changes['finalized_snapshot'] = [
                    'finding' => $this->findingContent($locked),
                    'recommendations' => $locked->recommendations->map(
                        fn (AuditRecommendation $recommendation): array => $this->recommendationData($recommendation),
                    )->values()->all(),
                    'responseIds' => $locked->managementResponses->modelKeys(),
                    'workingPaperVersionIds' => $locked->workingPaperVersions->modelKeys(),
                    'evidenceIds' => $locked->evidence->modelKeys(),
                    'fieldworkRecordVersionIds' => $locked->fieldworkRecordVersions->modelKeys(),
                    'procedureIds' => $locked->procedures->modelKeys(),
                    'finalizedAt' => now()->toIso8601String(),
                ];
            }
            $locked->update($changes);
            if ($action === 'COMMUNICATE') {
                $this->createTransmittalRecord($request, $engagement, $locked, [
                    ...$details,
                    'recipients' => $recipients,
                ]);
            }
            if ($action === 'RECORD_NON_RESPONSE') {
                $this->workQueue->recordDueProcess(
                    $request,
                    $engagement,
                    [
                        'findingId' => $locked->id,
                        'eventType' => 'FINAL_NON_RESPONSE',
                        'content' => $details['comment'],
                        'dueDate' => $locked->management_response_due_date?->toDateString(),
                        'metadata' => ['source' => 'finding.transition', 'findingStatus' => $to],
                    ],
                    true,
                );
            }
            $this->recordFinding(
                $request,
                $engagement,
                $locked,
                $action,
                $from,
                $to,
                $before,
                $details['comment'] ?? null,
            );
            if ($action === 'COMMUNICATE') {
                $this->notifications->findingCommunicated(
                    $request,
                    $engagement,
                    $locked,
                );
            }

            return $locked;
        }, 3);

        return $this->loadFinding($finding);
    }

    /** @param array<string, mixed> $attributes */
    public function saveRecommendation(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ?AuditRecommendation $recommendation,
        array $attributes,
    ): AuditRecommendation {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.finding.create',
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $finding,
            $recommendation,
            $attributes,
        ): AuditRecommendation {
            $lockedFinding = $this->lockFinding(
                $engagement,
                $finding,
                (int) $attributes['findingLockVersion'],
            );
            if ($lockedFinding->status === 'FINALIZED') {
                throw ValidationException::withMessages([
                    'finding' => ['Finalized finding recommendations are immutable.'],
                ]);
            }
            $this->ensureOffice($engagement, (int) $attributes['responsibleOfficeId']);
            if ($recommendation) {
                if ((int) $recommendation->audit_finding_id !== (int) $lockedFinding->id) {
                    throw ValidationException::withMessages([
                        'recommendation' => ['The recommendation does not belong to this finding.'],
                    ]);
                }
                if ($recommendation->lock_version !== (int) $attributes['lockVersion']) {
                    throw ValidationException::withMessages([
                        'lockVersion' => ['This recommendation changed in another session.'],
                    ]);
                }
                if ($recommendation->status !== 'DRAFT') {
                    throw ValidationException::withMessages([
                        'recommendation' => ['Finalized recommendations are immutable.'],
                    ]);
                }
                $before = $this->recommendationData($recommendation);
                $recommendation->update([
                    ...$this->recommendationAttributes($attributes),
                    'updated_by' => $request->user()->id,
                    'lock_version' => $recommendation->lock_version + 1,
                ]);
                $action = 'RECOMMENDATION_UPDATED';
            } else {
                $recommendation = AuditRecommendation::query()->create([
                    'audit_finding_id' => $lockedFinding->id,
                    'recommendation_code' => $this->nextRecommendationCode($lockedFinding),
                    ...$this->recommendationAttributes($attributes),
                    'status' => 'DRAFT',
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                    'lock_version' => 1,
                ]);
                $before = null;
                $action = 'RECOMMENDATION_CREATED';
            }
            $lockedFinding->update([
                'lock_version' => $lockedFinding->lock_version + 1,
            ]);
            $this->record(
                $request,
                $engagement,
                'AUDIT_RECOMMENDATION',
                $recommendation->id,
                $recommendation->recommendation_code,
                $action,
                null,
                'DRAFT',
                $before,
                $this->recommendationData($recommendation),
            );

            return $recommendation->fresh(['responsibleOffice', 'creator', 'updater']);
        }, 3);
    }

    public function deleteRecommendation(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        AuditRecommendation $recommendation,
        int $findingLockVersion,
        int $lockVersion,
    ): void {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.finding.create',
        );
        DB::transaction(function () use (
            $request,
            $engagement,
            $finding,
            $recommendation,
            $findingLockVersion,
            $lockVersion,
        ): void {
            $lockedFinding = $this->lockFinding($engagement, $finding, $findingLockVersion);
            if ((int) $recommendation->audit_finding_id !== (int) $lockedFinding->id
                || $recommendation->lock_version !== $lockVersion
                || $recommendation->status !== 'DRAFT') {
                throw ValidationException::withMessages([
                    'recommendation' => ['Only the current draft recommendation can be removed.'],
                ]);
            }
            $before = $this->recommendationData($recommendation);
            $recommendation->delete();
            $lockedFinding->update(['lock_version' => $lockedFinding->lock_version + 1]);
            $this->record(
                $request,
                $engagement,
                'AUDIT_RECOMMENDATION',
                $recommendation->id,
                $recommendation->recommendation_code,
                'RECOMMENDATION_REMOVED',
                'DRAFT',
                null,
                $before,
                null,
            );
        }, 3);
    }

    /** Create an immutable formal AFR transmittal and recipient register. */
    public function createTransmittal(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        array $details,
    ): AemsFindingTransmittal {
        $this->ensureFinding($engagement, $finding);
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.afr.transmit',
            $finding->authored_by,
        );
        if (! in_array($finding->status, ['COMMUNICATED', 'AWAITING_MANAGEMENT_RESPONSE', 'UNDER_DIALOGUE'], true)) {
            throw ValidationException::withMessages(['finding' => ['Only a formally communicated Finding may be retransmitted.']]);
        }

        return DB::transaction(fn (): AemsFindingTransmittal => $this->createTransmittalRecord(
            $request,
            $engagement,
            $finding,
            $details,
        ), 3);
    }

    public function transitionTransmittalRecipient(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        AemsFindingTransmittal $transmittal,
        AemsFindingTransmittalRecipient $recipient,
        string $action,
        int $lockVersion,
        ?string $comment,
    ): AemsFindingTransmittalRecipient {
        $this->ensureFinding($engagement, $finding);
        if ((int) $transmittal->audit_finding_id !== (int) $finding->id
            || (int) $transmittal->audit_engagement_id !== (int) $engagement->id
            || (int) $recipient->transmittal_id !== (int) $transmittal->id) {
            throw ValidationException::withMessages(['recipient' => ['This recipient is outside the engagement Finding.']]);
        }
        if ($action === 'ACKNOWLEDGE' && $request->user()->hasPermission('access.auditee_scope')) {
            $isResponsibleOffice = (int) $request->user()->office_id === (int) $finding->responsible_office_id;
            $isNamedRecipient = $recipient->recipient_user_id !== null
                && (int) $recipient->recipient_user_id === (int) $request->user()->id;
            $isRecipientOffice = $recipient->recipient_office_id !== null
                && (int) $recipient->recipient_office_id === (int) $request->user()->office_id;
            if (! $isResponsibleOffice || (! $isNamedRecipient && ! $isRecipientOffice)) {
                abort(403, 'You may acknowledge only an AFR formally transmitted to your office or account.');
            }
            if (! $request->user()->hasPermission('aems.afr.acknowledge')) {
                abort(403, 'You do not have acknowledgement permission.');
            }
        } else {
            $this->access->authorizeEngagementAction(
                $request->user(),
                $engagement,
                $action === 'DELIVER' ? 'aems.afr.delivery' : 'aems.afr.acknowledge',
                $transmittal->sent_by,
            );
        }

        return DB::transaction(function () use (
            $request, $engagement, $transmittal, $recipient, $action, $lockVersion, $comment,
        ): AemsFindingTransmittalRecipient {
            $locked = AemsFindingTransmittalRecipient::query()->lockForUpdate()->findOrFail($recipient->id);
            if ($locked->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['lockVersion' => ['This recipient delivery state changed. Refresh before continuing.']]);
            }
            $from = $locked->delivery_status;
            $to = match ($action) {
                'DELIVER' => in_array($from, ['PENDING', 'SENT', 'FAILED'], true) ? 'DELIVERED' : null,
                'ACKNOWLEDGE' => in_array($from, ['SENT', 'DELIVERED'], true) ? 'ACKNOWLEDGED' : null,
                default => null,
            };
            if (! $to) {
                throw ValidationException::withMessages(['action' => ["{$action} is not available while the recipient is {$from}."]]);
            }
            if ($action === 'ACKNOWLEDGE' && blank($comment)) {
                throw ValidationException::withMessages(['comment' => ['An acknowledgement comment is required.']]);
            }
            $changes = ['delivery_status' => $to, 'lock_version' => $locked->lock_version + 1];
            if ($action === 'DELIVER') {
                $changes['delivered_at'] = now();
            } else {
                $changes['acknowledged_at'] = now();
                $changes['acknowledged_by'] = $request->user()->id;
                $changes['acknowledgement_comment'] = $comment;
            }
            $locked->update($changes);
            AemsFindingTransmittalEvent::query()->create([
                'transmittal_id' => $transmittal->id,
                'recipient_id' => $locked->id,
                'event_type' => $action === 'DELIVER' ? 'DELIVERED' : 'ACKNOWLEDGED',
                'content' => $comment,
                'actor_id' => $request->user()->id,
                'metadata' => ['fromStatus' => $from, 'toStatus' => $to],
                'recorded_at' => now(),
            ]);
            $this->support->audit(
                $request,
                'aems.afr.'.strtolower($action),
                $engagement,
                ['recipientId' => $locked->id, 'status' => $from],
                ['recipientId' => $locked->id, 'status' => $to, 'transmittalId' => $transmittal->id],
                ['findingId' => $transmittal->audit_finding_id],
            );

            return $locked->fresh(['user', 'office', 'acknowledger', 'events.actor']);
        }, 3);
    }

    /** @return AemsFindingTransmittal */
    private function createTransmittalRecord(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        array $details,
    ): AemsFindingTransmittal {
        $finding->loadMissing(['recommendations', 'workingPaperVersions', 'evidence']);
        $rawRecipients = collect($details['recipients'] ?? [])->values();
        if ($rawRecipients->isEmpty()) {
            throw ValidationException::withMessages(['recipients' => ['At least one AFR recipient is required.']]);
        }
        $sequence = AemsFindingTransmittal::query()->where('audit_finding_id', $finding->id)->count() + 1;
        $transmittal = AemsFindingTransmittal::query()->create([
            'audit_engagement_id' => $engagement->id,
            'audit_finding_id' => $finding->id,
            'transmittal_code' => "AFR-{$finding->finding_code}-".str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            'finding_revision_number' => $finding->revision_number,
            'transmittal_method' => strtoupper((string) ($details['transmittalMethod'] ?? 'OFFICIAL_LETTER')),
            'transmittal_reference' => $details['transmittalReference'] ?? null,
            'confidentiality' => $details['confidentiality'] ?? 'INTERNAL',
            'sent_by' => $request->user()->id,
            'sent_at' => now(),
            'response_due_date' => $details['dueDate'] ?? $finding->management_response_due_date,
            'content_snapshot' => [
                'finding' => $this->findingContent($finding),
                'recommendations' => $finding->recommendations->map(fn (AuditRecommendation $item): array => $this->recommendationData($item))->values()->all(),
                'workingPaperVersionIds' => $finding->workingPaperVersions->modelKeys(),
                'evidenceIds' => $finding->evidence->modelKeys(),
                'capturedAt' => now()->toIso8601String(),
            ],
            'lock_version' => 1,
        ]);
        foreach ($rawRecipients as $raw) {
            $name = is_array($raw) ? trim((string) ($raw['name'] ?? '')) : trim((string) $raw);
            if ($name === '') {
                throw ValidationException::withMessages(['recipients' => ['Recipient names cannot be blank.']]);
            }
            $office = $engagement->offices()->where(function ($query) use ($name): void {
                $query->where('code', $name)->orWhere('name', $name);
            })->first();
            $recipientOfficeId = is_array($raw) ? ($raw['officeId'] ?? null) : null;
            if ($recipientOfficeId !== null) {
                $this->ensureOffice($engagement, (int) $recipientOfficeId);
            }
            $recipientUserId = is_array($raw) ? ($raw['userId'] ?? null) : null;
            if ($recipientUserId !== null) {
                $recipientUser = User::query()->find((int) $recipientUserId);
                if (! $recipientUser || ! $recipientUser->is_active) {
                    throw ValidationException::withMessages(['recipients' => ['Recipient user must be an active user.']]);
                }
                if ($recipientOfficeId !== null && (int) $recipientUser->office_id !== (int) $recipientOfficeId) {
                    throw ValidationException::withMessages(['recipients' => ['Recipient user and office must match.']]);
                }
                if ($recipientOfficeId === null && $recipientUser->office_id !== null) {
                    $this->ensureOffice($engagement, (int) $recipientUser->office_id);
                    $recipientOfficeId = (int) $recipientUser->office_id;
                }
            }
            $recipient = $transmittal->recipients()->create([
                'recipient_type' => is_array($raw) ? strtoupper((string) ($raw['type'] ?? 'OFFICE')) : 'OFFICE',
                'recipient_user_id' => $recipientUserId,
                'recipient_office_id' => $office?->id ?? $recipientOfficeId,
                'recipient_name' => $name,
                'delivery_status' => 'SENT',
                'delivered_at' => now(),
                'lock_version' => 1,
            ]);
            $transmittal->events()->create([
                'recipient_id' => $recipient->id,
                'event_type' => 'SENT',
                'content' => 'AFR transmittal sent to recipient.',
                'actor_id' => $request->user()->id,
                'metadata' => ['recipientName' => $name],
                'recorded_at' => now(),
            ]);
        }
        $transmittal->events()->create([
            'event_type' => 'TRANSMITTED',
            'content' => 'AFR transmittal created.',
            'actor_id' => $request->user()->id,
            'metadata' => ['findingId' => $finding->id],
            'recorded_at' => now(),
        ]);
        $this->support->audit(
            $request,
            'aems.afr.transmitted',
            $engagement,
            null,
            ['transmittalId' => $transmittal->id, 'findingId' => $finding->id],
        );

        return $transmittal->fresh(['sender', 'recipients.user', 'recipients.office', 'events.actor']);
    }

    /** @param array<string, mixed> $attributes */
    public function createResponse(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        array $attributes,
    ): ManagementResponse {
        $this->access->authorizeManagementResponseSubmit($request->user(), $finding);

        $response = DB::transaction(function () use (
            $request,
            $engagement,
            $finding,
            $attributes,
        ): ManagementResponse {
            $lockedFinding = $this->lockFinding(
                $engagement,
                $finding,
                (int) $attributes['findingLockVersion'],
            );
            if (! in_array($lockedFinding->status, ['AWAITING_MANAGEMENT_RESPONSE', 'UNDER_DIALOGUE'], true)) {
                throw ValidationException::withMessages([
                    'finding' => ['This finding is not accepting a management response.'],
                ]);
            }
            $responseKind = strtoupper((string) ($attributes['responseKind'] ?? 'ORIGINAL'));
            if (! in_array($responseKind, ManagementResponse::RESPONSE_KINDS, true)) {
                throw ValidationException::withMessages(['responseKind' => ['Unsupported management response kind.']]);
            }
            if ($responseKind !== 'SUPPLEMENTAL'
                && $lockedFinding->managementResponses()->where('is_current_revision', true)->exists()) {
                throw ValidationException::withMessages([
                    'response' => ['A current management response already exists.'],
                ]);
            }
            if ($responseKind === 'SUPPLEMENTAL' && blank($attributes['supplementalReason'] ?? null)) {
                throw ValidationException::withMessages(['supplementalReason' => ['A supplemental-response reason is required.']]);
            }
            $responseNumber = $lockedFinding->managementResponses()->withTrashed()->count() + 1;
            $response = ManagementResponse::query()->create([
                'response_family_uuid' => (string) Str::uuid(),
                'version_number' => 1,
                'is_current_revision' => true,
                'audit_finding_id' => $lockedFinding->id,
                'response_code' => $responseKind === 'SUPPLEMENTAL'
                    ? "MR-{$lockedFinding->finding_code}-SUP-".str_pad((string) $responseNumber, 3, '0', STR_PAD_LEFT)
                    : "MR-{$lockedFinding->finding_code}-001",
                ...$this->responseAttributes($attributes, $lockedFinding),
                'status' => 'DRAFT',
                'authored_by' => $request->user()->id,
                'lock_version' => 1,
            ]);
            $lockedFinding->update(['lock_version' => $lockedFinding->lock_version + 1]);
            $this->record(
                $request,
                $engagement,
                'MANAGEMENT_RESPONSE',
                $response->id,
                $response->response_code,
                'MANAGEMENT_RESPONSE_CREATED',
                null,
                'DRAFT',
                null,
                $this->responseAudit($response),
            );

            return $response;
        }, 3);

        return $this->loadResponse($response);
    }

    /** @param array<string, mixed> $attributes */
    public function updateResponse(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ManagementResponse $response,
        array $attributes,
    ): ManagementResponse {
        $this->ensureFinding($engagement, $finding);
        $this->access->authorizeManagementResponseSubmit($request->user(), $finding);
        $response = DB::transaction(function () use (
            $request,
            $engagement,
            $finding,
            $response,
            $attributes,
        ): ManagementResponse {
            $locked = $this->lockResponse($finding, $response, (int) $attributes['lockVersion']);
            if ($locked->status !== 'DRAFT' || (int) $locked->authored_by !== (int) $request->user()->id) {
                throw ValidationException::withMessages([
                    'response' => ['Only your current draft response can be edited.'],
                ]);
            }
            $requestedKind = strtoupper((string) ($attributes['responseKind'] ?? $locked->response_kind));
            if (! in_array($requestedKind, ManagementResponse::RESPONSE_KINDS, true)) {
                throw ValidationException::withMessages(['responseKind' => ['Unsupported management response kind.']]);
            }
            if ($requestedKind !== $locked->response_kind) {
                throw ValidationException::withMessages([
                    'responseKind' => ['Response kind is immutable for a draft. Create a separate supplemental response instead.'],
                ]);
            }
            if ($locked->response_kind === 'SUPPLEMENTAL' && blank($attributes['supplementalReason'] ?? $locked->supplemental_reason)) {
                throw ValidationException::withMessages(['supplementalReason' => ['A supplemental-response reason is required.']]);
            }
            $before = $this->responseAudit($locked);
            $locked->update([
                ...$this->responseAttributes($attributes, $finding),
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->record(
                $request,
                $engagement,
                'MANAGEMENT_RESPONSE',
                $locked->id,
                $locked->response_code,
                'MANAGEMENT_RESPONSE_UPDATED',
                'DRAFT',
                'DRAFT',
                $before,
                $this->responseAudit($locked),
            );

            return $locked;
        }, 3);

        return $this->loadResponse($response);
    }

    public function transitionResponse(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ManagementResponse $response,
        string $action,
        int $lockVersion,
        ?string $comment,
        array $details = [],
    ): ManagementResponse {
        $this->ensureFinding($engagement, $finding);
        if (in_array($action, ['SUBMIT', 'REQUEST_EXTENSION'], true)) {
            $this->access->authorizeManagementResponseSubmit($request->user(), $finding);
        } elseif (in_array($action, ['APPROVE_EXTENSION', 'REJECT_EXTENSION'], true)) {
            $this->access->authorizeEngagementAction(
                $request->user(),
                $engagement,
                $action === 'APPROVE_EXTENSION'
                    ? 'aems.management-response.approve_extension'
                    : 'aems.management-response.reject_extension',
                $response->authored_by,
            );
        } else {
            $this->access->authorizeEngagementAction(
                $request->user(),
                $engagement,
                'aems.management-response.request_clarification',
            );
        }

        $response = DB::transaction(function () use (
            $request,
            $engagement,
            $finding,
            $response,
            $action,
            $lockVersion,
            $comment,
            $details,
        ): ManagementResponse {
            $locked = $this->lockResponse($finding, $response, $lockVersion);
            $from = $locked->status;
            $to = match ($action) {
                'SUBMIT' => in_array($from, ['DRAFT', 'EXTENSION_APPROVED'], true)
                    ? ($locked->version_number > 1 ? 'RESUBMITTED' : 'SUBMITTED')
                    : null,
                'START_REVIEW' => in_array($from, ['SUBMITTED', 'RESUBMITTED'], true)
                    ? 'UNDER_AUDITOR_REVIEW'
                    : null,
                'REQUEST_CLARIFICATION' => $from === 'UNDER_AUDITOR_REVIEW'
                    ? 'CLARIFICATION_REQUESTED'
                    : null,
                'REQUEST_EXTENSION' => in_array($from, ['DRAFT', 'SUBMITTED', 'CLARIFICATION_REQUESTED'], true)
                    ? 'EXTENSION_REQUESTED'
                    : null,
                'APPROVE_EXTENSION' => $from === 'EXTENSION_REQUESTED' ? 'EXTENSION_APPROVED' : null,
                'REJECT_EXTENSION' => $from === 'EXTENSION_REQUESTED' ? 'DRAFT' : null,
                default => null,
            };
            if (! $to) {
                throw ValidationException::withMessages([
                    'action' => ["{$action} is not available while the response is {$from}."],
                ]);
            }
            if ($action === 'REQUEST_CLARIFICATION' && ! $comment) {
                throw ValidationException::withMessages([
                    'comment' => ['A clarification request is required.'],
                ]);
            }
            $changes = [];
            if ($action === 'REQUEST_EXTENSION') {
                $requestedDueDate = $this->validExtensionDate($details['extensionDueDate'] ?? null, $finding);
                if (! $comment) {
                    throw ValidationException::withMessages(['comment' => ['An extension reason is required.']]);
                }
                $changes['extension_requested_at'] = now();
                $changes['extension_requested_by'] = $request->user()->id;
                $changes['extension_requested_due_date'] = $requestedDueDate;
                $changes['extension_reason'] = $comment;
            } elseif ($action === 'APPROVE_EXTENSION') {
                if (! $comment) {
                    throw ValidationException::withMessages(['comment' => ['An extension decision note is required.']]);
                }
                $changes['extension_approved_at'] = now();
                $changes['extension_approved_by'] = $request->user()->id;
                $changes['extension_approved_due_date'] = $locked->extension_requested_due_date;
                $changes['extension_reason'] = trim($locked->extension_reason.' '.$comment);
            } elseif ($action === 'REJECT_EXTENSION') {
                if (! $comment) {
                    throw ValidationException::withMessages(['comment' => ['A rejection reason is required.']]);
                }
                $changes['extension_reason'] = trim($locked->extension_reason.' Rejected: '.$comment);
            }
            if ($action === 'SUBMIT') {
                $effectiveDueDate = $locked->extension_approved_due_date ?? $finding->management_response_due_date;
                if ($effectiveDueDate && now()->startOfDay()->gt($effectiveDueDate->copy()->startOfDay())) {
                    if (blank($details['lateReason'] ?? null)) {
                        throw ValidationException::withMessages(['lateReason' => ['A reason is required for a late response.']]);
                    }
                    $changes['submitted_late'] = true;
                    $changes['late_reason'] = trim((string) $details['lateReason']);
                    if ($locked->response_kind === 'ORIGINAL') {
                        $changes['response_kind'] = 'LATE';
                    }
                }
            }
            $before = $this->responseAudit($locked);
            $changes = ['status' => $to, 'lock_version' => $locked->lock_version + 1, ...$changes];
            if ($action === 'SUBMIT') {
                $changes['submitted_at'] = now();
            } elseif ($action === 'REQUEST_CLARIFICATION') {
                $changes['clarification_requested_at'] = now();
                $changes['clarification_requested_by'] = $request->user()->id;
                $changes['clarification_request'] = $comment;
            }
            $locked->update($changes);
            if ($action === 'SUBMIT' && $finding->status === 'AWAITING_MANAGEMENT_RESPONSE') {
                $finding->update([
                    'status' => 'UNDER_DIALOGUE',
                    'lock_version' => $finding->lock_version + 1,
                ]);
            }
            $this->record(
                $request,
                $engagement,
                'MANAGEMENT_RESPONSE',
                $locked->id,
                $locked->response_code,
                "MANAGEMENT_RESPONSE_{$action}",
                $from,
                $to,
                $before,
                $this->responseAudit($locked),
                $comment,
            );
            if (in_array($action, ['REQUEST_EXTENSION', 'APPROVE_EXTENSION', 'REJECT_EXTENSION'], true)) {
                $this->workQueue->recordDueProcess(
                    $request,
                    $engagement,
                    [
                        'findingId' => $finding->id,
                        'responseId' => $locked->id,
                        'eventType' => match ($action) {
                            'REQUEST_EXTENSION' => 'EXTENSION_REQUESTED',
                            'APPROVE_EXTENSION' => 'EXTENSION_APPROVED',
                            default => 'EXTENSION_REJECTED',
                        },
                        'content' => $comment,
                        'dueDate' => $locked->extension_requested_due_date?->toDateString(),
                        'metadata' => ['responseCode' => $locked->response_code],
                    ],
                    true,
                );
            } elseif ($action === 'SUBMIT' && ($locked->submitted_late || $locked->response_kind === 'SUPPLEMENTAL')) {
                $this->workQueue->recordDueProcess(
                    $request,
                    $engagement,
                    [
                        'findingId' => $finding->id,
                        'responseId' => $locked->id,
                        'eventType' => $locked->response_kind === 'SUPPLEMENTAL' ? 'SUPPLEMENTAL_RESPONSE' : 'LATE_RESPONSE',
                        'content' => $locked->late_reason ?? $locked->supplemental_reason,
                        'metadata' => ['responseCode' => $locked->response_code],
                    ],
                    true,
                );
            }

            return $locked;
        }, 3);

        return $this->loadResponse($response);
    }

    public function reviseResponse(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ManagementResponse $response,
        int $lockVersion,
    ): ManagementResponse {
        $this->ensureFinding($engagement, $finding);
        $this->access->authorizeManagementResponseSubmit($request->user(), $finding);
        $revision = DB::transaction(function () use (
            $request,
            $engagement,
            $finding,
            $response,
            $lockVersion,
        ): ManagementResponse {
            $locked = $this->lockResponse($finding, $response, $lockVersion);
            if ($locked->status !== 'CLARIFICATION_REQUESTED') {
                throw ValidationException::withMessages([
                    'response' => ['Only a clarification-requested response can be revised.'],
                ]);
            }
            DB::statement(
                'UPDATE management_responses SET is_current_revision = FALSE WHERE id = ?',
                [$locked->id],
            );
            $revision = ManagementResponse::query()->create([
                'response_family_uuid' => $locked->response_family_uuid,
                'version_number' => $locked->version_number + 1,
                'supersedes_response_id' => $locked->id,
                'is_current_revision' => false,
                'audit_finding_id' => $locked->audit_finding_id,
                // Keep each immutable revision addressable even on legacy
                // SQLite deployments that cannot enforce the partial current
                // revision index consistently.
                'response_code' => $locked->response_code.'-V'.($locked->version_number + 1),
                'agreement_position' => $locked->agreement_position,
                'management_comment' => $locked->management_comment,
                'proposed_action' => $locked->proposed_action,
                'responsible_office_id' => $locked->responsible_office_id,
                'responsible_user_id' => $locked->responsible_user_id,
                'proposed_target_date' => $locked->proposed_target_date,
                'status' => 'DRAFT',
                'authored_by' => $request->user()->id,
                'lock_version' => 1,
            ]);
            DB::statement(
                'UPDATE management_responses SET is_current_revision = TRUE WHERE id = ?',
                [$revision->id],
            );
            $this->record(
                $request,
                $engagement,
                'MANAGEMENT_RESPONSE',
                $revision->id,
                $revision->response_code,
                'MANAGEMENT_RESPONSE_REVISED',
                'CLARIFICATION_REQUESTED',
                'DRAFT',
                $this->responseAudit($locked),
                $this->responseAudit($revision),
                $locked->clarification_request,
            );

            return $revision;
        }, 3);

        return $this->loadResponse($revision);
    }

    /** @param array<string, mixed> $attributes */
    public function saveRejoinder(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ManagementResponse $response,
        ?AuditorRejoinder $rejoinder,
        array $attributes,
    ): AuditorRejoinder {
        $this->ensureFinding($engagement, $finding);
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.rejoinder.create',
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $finding,
            $response,
            $rejoinder,
            $attributes,
        ): AuditorRejoinder {
            $lockedResponse = $this->lockResponse(
                $finding,
                $response,
                (int) $attributes['responseLockVersion'],
            );
            if ($lockedResponse->status !== 'UNDER_AUDITOR_REVIEW') {
                throw ValidationException::withMessages([
                    'response' => ['A rejoinder requires a response under auditor review.'],
                ]);
            }
            if ($rejoinder) {
                if ((int) $rejoinder->management_response_id !== (int) $lockedResponse->id
                    || $rejoinder->lock_version !== (int) $attributes['lockVersion']
                    || $rejoinder->status !== 'DRAFT') {
                    throw ValidationException::withMessages([
                        'rejoinder' => ['Only the current draft rejoinder can be edited.'],
                    ]);
                }
                $rejoinder->update([
                    'disposition' => $attributes['disposition'],
                    'rejoinder' => $attributes['rejoinder'],
                    'lock_version' => $rejoinder->lock_version + 1,
                ]);
                $action = 'REJOINDER_UPDATED';
            } else {
                $rejoinder = AuditorRejoinder::query()->create([
                    'management_response_id' => $lockedResponse->id,
                    'version_number' => $lockedResponse->rejoinders()->count() + 1,
                    'disposition' => $attributes['disposition'],
                    'rejoinder' => $attributes['rejoinder'],
                    'status' => 'DRAFT',
                    'authored_by' => $request->user()->id,
                    'lock_version' => 1,
                ]);
                $action = 'REJOINDER_CREATED';
            }
            $this->record(
                $request,
                $engagement,
                'AUDITOR_REJOINDER',
                $rejoinder->id,
                $lockedResponse->response_code,
                $action,
                null,
                'DRAFT',
                null,
                ['disposition' => $rejoinder->disposition],
            );

            return $rejoinder->fresh(['author', 'finalizer']);
        }, 3);
    }

    public function finalizeRejoinder(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ManagementResponse $response,
        AuditorRejoinder $rejoinder,
        int $responseLockVersion,
        int $lockVersion,
    ): AuditorRejoinder {
        $this->ensureFinding($engagement, $finding);
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.rejoinder.finalize',
            $rejoinder->authored_by,
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $finding,
            $response,
            $rejoinder,
            $responseLockVersion,
            $lockVersion,
        ): AuditorRejoinder {
            $lockedResponse = $this->lockResponse($finding, $response, $responseLockVersion);
            $locked = AuditorRejoinder::query()->lockForUpdate()->findOrFail($rejoinder->id);
            if ((int) $locked->management_response_id !== (int) $lockedResponse->id
                || $locked->lock_version !== $lockVersion
                || $locked->status !== 'DRAFT') {
                throw ValidationException::withMessages([
                    'rejoinder' => ['The draft rejoinder changed or is no longer finalizable.'],
                ]);
            }
            $locked->update([
                'status' => 'DIALOGUE_FINALIZED',
                'finalized_at' => now(),
                'finalized_by' => $request->user()->id,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $lockedResponse->update([
                'status' => 'DIALOGUE_FINALIZED',
                'finalized_at' => now(),
                'finalized_by' => $request->user()->id,
                'lock_version' => $lockedResponse->lock_version + 1,
            ]);
            $this->record(
                $request,
                $engagement,
                'AUDITOR_REJOINDER',
                $locked->id,
                $lockedResponse->response_code,
                'REJOINDER_FINALIZED',
                'DRAFT',
                'DIALOGUE_FINALIZED',
                null,
                ['disposition' => $locked->disposition],
            );

            return $locked->fresh(['author', 'finalizer']);
        }, 3);
    }

    public function uploadResponseAttachment(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ManagementResponse $response,
        UploadedFile $file,
        ?string $caption,
        int $lockVersion,
    ): AemsDialogueAttachment {
        $this->ensureFinding($engagement, $finding);
        $this->access->authorizeManagementResponseSubmit($request->user(), $finding);
        $stored = $this->storeDialogueFile($file, $engagement);

        try {
            return DB::transaction(function () use (
                $request,
                $engagement,
                $finding,
                $response,
                $caption,
                $lockVersion,
                $stored,
            ): AemsDialogueAttachment {
                $locked = $this->lockResponse($finding, $response, $lockVersion);
                if ($locked->status !== 'DRAFT'
                    || (int) $locked->authored_by !== (int) $request->user()->id) {
                    throw ValidationException::withMessages([
                        'attachment' => ['Attachments can be added only to your current draft response.'],
                    ]);
                }
                $version = $this->createDialogueDocument(
                    $request,
                    $engagement,
                    $finding,
                    $locked->response_code,
                    $stored,
                );
                $attachment = AemsDialogueAttachment::query()->create([
                    'audit_engagement_id' => $engagement->id,
                    'audit_finding_id' => $finding->id,
                    'management_response_id' => $locked->id,
                    'attachment_code' => $this->nextAttachmentCode(
                        $locked->response_code,
                        $locked->attachments()->count(),
                    ),
                    'caption' => $caption,
                    'document_version_id' => $version->id,
                    'uploaded_by' => $request->user()->id,
                ]);
                $locked->update(['lock_version' => $locked->lock_version + 1]);
                $this->record(
                    $request,
                    $engagement,
                    'MANAGEMENT_RESPONSE',
                    $locked->id,
                    $locked->response_code,
                    'MANAGEMENT_RESPONSE_ATTACHMENT_UPLOADED',
                    'DRAFT',
                    'DRAFT',
                    null,
                    $this->attachmentData($attachment),
                    version: $locked->version_number,
                    documentVersionIds: [$version->id],
                );

                return $attachment->fresh(['documentVersion', 'uploader']);
            }, 3);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($stored['storage_path']);
            throw $error;
        }
    }

    public function uploadRejoinderAttachment(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        ManagementResponse $response,
        AuditorRejoinder $rejoinder,
        UploadedFile $file,
        ?string $caption,
        int $lockVersion,
    ): AemsDialogueAttachment {
        $this->ensureFinding($engagement, $finding);
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.rejoinder.create',
        );
        $stored = $this->storeDialogueFile($file, $engagement);

        try {
            return DB::transaction(function () use (
                $request,
                $engagement,
                $finding,
                $response,
                $rejoinder,
                $caption,
                $lockVersion,
                $stored,
            ): AemsDialogueAttachment {
                $this->lockResponse($finding, $response, $response->lock_version);
                $locked = AuditorRejoinder::query()->lockForUpdate()->findOrFail($rejoinder->id);
                if ((int) $locked->management_response_id !== (int) $response->id
                    || $locked->lock_version !== $lockVersion
                    || $locked->status !== 'DRAFT') {
                    throw ValidationException::withMessages([
                        'attachment' => ['Attachments can be added only to the current draft rejoinder.'],
                    ]);
                }
                $version = $this->createDialogueDocument(
                    $request,
                    $engagement,
                    $finding,
                    "{$response->response_code}-RJ{$locked->version_number}",
                    $stored,
                );
                $attachment = AemsDialogueAttachment::query()->create([
                    'audit_engagement_id' => $engagement->id,
                    'audit_finding_id' => $finding->id,
                    'auditor_rejoinder_id' => $locked->id,
                    'attachment_code' => $this->nextAttachmentCode(
                        "{$response->response_code}-RJ{$locked->version_number}",
                        $locked->attachments()->count(),
                    ),
                    'caption' => $caption,
                    'document_version_id' => $version->id,
                    'uploaded_by' => $request->user()->id,
                ]);
                $locked->update(['lock_version' => $locked->lock_version + 1]);
                $this->record(
                    $request,
                    $engagement,
                    'AUDITOR_REJOINDER',
                    $locked->id,
                    $response->response_code,
                    'REJOINDER_ATTACHMENT_UPLOADED',
                    'DRAFT',
                    'DRAFT',
                    null,
                    $this->attachmentData($attachment),
                    version: $locked->version_number,
                    documentVersionIds: [$version->id],
                );

                return $attachment->fresh(['documentVersion', 'uploader']);
            }, 3);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($stored['storage_path']);
            throw $error;
        }
    }

    public function downloadAttachment(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        AemsDialogueAttachment $attachment,
    ): DocumentVersion {
        $this->ensureFinding($engagement, $finding);
        if ((int) $attachment->audit_engagement_id !== (int) $engagement->id
            || (int) $attachment->audit_finding_id !== (int) $finding->id) {
            throw ValidationException::withMessages([
                'attachment' => ['The attachment does not belong to this dialogue.'],
            ]);
        }
        $version = $attachment->documentVersion()->firstOrFail();
        abort_unless(Storage::disk('local')->exists($version->storage_path), 404);
        $this->support->audit(
            $request,
            'aems.dialogue.attachment.downloaded',
            $engagement,
            null,
            ['attachmentId' => $attachment->id, 'documentVersionId' => $version->id],
            ['findingId' => $finding->id],
        );

        return $version;
    }

    /** @return array<string, mixed> */
    public function issueData(AuditIssue $issue): array
    {
        $issue = $this->loadIssue($issue);

        return [
            'id' => $issue->id,
            'issueCode' => $issue->issue_code,
            'title' => $issue->title,
            'exceptionDescription' => $issue->exception_description,
            'responsibleOfficeId' => $issue->responsible_office_id,
            'responsibleOffice' => $this->office($issue->responsibleOffice),
            'riskRatingId' => $issue->risk_rating_id,
            'riskRating' => $issue->riskRating?->only(['id', 'code', 'label']),
            'status' => $issue->status,
            'disposition' => $issue->disposition,
            'statusCompatibility' => AuditIssue::STATUS_COMPATIBILITY[$issue->status] ?? null,
            'terminalDisposition' => $issue->disposition,
            'dispositionReason' => $issue->disposition_reason,
            'dispositionRecordedBy' => $this->user($issue->dispositionRecorder),
            'dispositionRecordedAt' => $issue->disposition_recorded_at?->toIso8601String(),
            'mergedIntoIssueId' => $issue->merged_into_issue_id,
            'referredTo' => $issue->referred_to,
            'resolutionDetails' => $issue->resolution_details,
            'raisedBy' => $this->user($issue->raiser),
            'reviewer' => $this->user($issue->reviewer),
            'submittedAt' => $issue->submitted_at?->toIso8601String(),
            'validatedAt' => $issue->validated_at?->toIso8601String(),
            'dismissedAt' => $issue->dismissed_at?->toIso8601String(),
            'dismissalReason' => $issue->dismissal_reason,
            'convertedAt' => $issue->converted_at?->toIso8601String(),
            'withdrawnAt' => $issue->withdrawn_at?->toIso8601String(),
            'withdrawnBy' => $this->user($issue->withdrawnBy),
            'withdrawalReason' => $issue->withdrawal_reason,
            'lockVersion' => $issue->lock_version,
            'workingPaperVersions' => $issue->workingPaperVersions->map(
                fn (WorkingPaperVersion $version): array => $this->workingPaperVersionData($version),
            )->values(),
            'evidence' => $issue->evidence->map(fn (AuditEvidence $item): array => $this->evidenceData($item))->values(),
            'findingId' => $issue->finding?->id,
            'history' => $this->history('AUDIT_ISSUE', $issue->id),
        ];
    }

    /** @return array<string, mixed> */
    public function findingData(AuditFinding $finding): array
    {
        $finding = $this->loadFinding($finding);

        return [
            'id' => $finding->id,
            'familyUuid' => $finding->finding_family_uuid,
            'revisionNumber' => $finding->revision_number,
            'isCurrentRevision' => $finding->is_current_revision,
            'sourceIssueId' => $finding->source_issue_id,
            'directCreationReason' => $finding->direct_creation_reason,
            'directCreationAuthority' => $finding->direct_creation_authority,
            'directCreatedBy' => $this->user($finding->directCreator),
            'directCreatedAt' => $finding->direct_creation_at?->toIso8601String(),
            'findingCode' => $finding->finding_code,
            'revisionType' => $finding->revision_type,
            'revisionReason' => $finding->revision_reason,
            'revisionSnapshot' => $finding->revision_snapshot,
            ...$this->findingContent($finding),
            'status' => $finding->status,
            'conclusion' => $finding->conclusion,
            'significanceClassification' => $finding->significance_classification,
            'effectClassification' => $finding->effect_classification,
            'authoredBy' => $this->user($finding->author),
            'validatedBy' => $this->user($finding->validator),
            'communicatedBy' => $this->user($finding->communicator),
            'finalizedBy' => $this->user($finding->finalizer),
            'submittedAt' => $finding->submitted_at?->toIso8601String(),
            'validatedAt' => $finding->validated_at?->toIso8601String(),
            'communicatedAt' => $finding->communicated_at?->toIso8601String(),
            'managementResponseDueDate' => $finding->management_response_due_date?->toDateString(),
            'communicatedSnapshot' => $finding->communicated_snapshot,
            'nonResponseReason' => $finding->non_response_reason,
            'nonResponseRecordedAt' => $finding->non_response_recorded_at?->toIso8601String(),
            'finalizedAt' => $finding->finalized_at?->toIso8601String(),
            'finalizedSnapshot' => $finding->finalized_snapshot,
            'withdrawnAt' => $finding->withdrawn_at?->toIso8601String(),
            'withdrawnBy' => $this->user($finding->withdrawnBy),
            'lockVersion' => $finding->lock_version,
            'workingPaperVersions' => $finding->workingPaperVersions->map(
                fn (WorkingPaperVersion $version): array => $this->workingPaperVersionData($version),
            )->values(),
            'evidence' => $finding->evidence->map(fn (AuditEvidence $item): array => $this->evidenceData($item))->values(),
            'fieldworkRecords' => $finding->fieldworkRecordVersions->map(
                fn (AemsFieldworkRecordVersion $version): array => [
                    'id' => $version->record?->id,
                    'recordCode' => $version->record?->record_code,
                    'versionId' => $version->id,
                    'versionNumber' => $version->version_number,
                    'status' => $version->record?->status,
                    'executionStatus' => $version->execution_status,
                ],
            )->values(),
            'procedures' => $finding->procedures->map(fn (AuditProgramProcedure $procedure): array => [
                'id' => $procedure->id,
                'procedureCode' => $procedure->procedure_code,
                'objective' => $procedure->objective,
                'auditCriteria' => $procedure->audit_criteria,
                'criteriaReference' => $procedure->pivot->criteria_reference,
                'traceabilityNote' => $procedure->pivot->traceability_note,
                'linkedBy' => $procedure->pivot->linked_by,
            ])->values(),
            'revisions' => $finding->revisions->map(
                fn (AuditFinding $revision): array => [
                    'id' => $revision->id,
                    'revisionNumber' => $revision->revision_number,
                    'revisionType' => $revision->revision_type,
                    'revisionReason' => $revision->revision_reason,
                    'status' => $revision->status,
                    'isCurrentRevision' => $revision->is_current_revision,
                    'createdAt' => $revision->created_at?->toIso8601String(),
                ],
            )->values(),
            'recommendations' => $finding->recommendations->map(
                fn (AuditRecommendation $recommendation): array => $this->recommendationData($recommendation),
            )->values(),
            'managementResponses' => $finding->managementResponses->map(
                fn (ManagementResponse $response): array => $this->responseData($response),
            )->values(),
            'dueProcess' => $finding->dueProcess->map(fn ($item): array => [
                'id' => $item->id,
                'eventCode' => $item->event_code,
                'versionNumber' => $item->version_number,
                'eventType' => $item->event_type,
                'content' => $item->content,
                'dueDate' => $item->due_date?->toDateString(),
                'recordedAt' => $item->recorded_at?->toIso8601String(),
                'actor' => $this->user($item->actor),
                'attachments' => $item->attachments->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'documentVersionId' => $attachment->document_version_id,
                    'attachmentCode' => $attachment->attachment_code,
                    'caption' => $attachment->caption,
                ])->values()->all(),
            ])->values(),
            'transmittals' => $finding->transmittals->map(
                fn (AemsFindingTransmittal $transmittal): array => $this->transmittalData($transmittal),
            )->values(),
            'history' => $this->history('AUDIT_FINDING', $finding->id),
        ];
    }

    /** @return array<string, mixed> */
    public function recommendationData(AuditRecommendation $recommendation): array
    {
        $recommendation->loadMissing(['responsibleOffice', 'creator', 'updater']);

        return [
            'id' => $recommendation->id,
            'recommendationCode' => $recommendation->recommendation_code,
            'recommendation' => $recommendation->recommendation,
            'responsibleOfficeId' => $recommendation->responsible_office_id,
            'responsibleOffice' => $this->office($recommendation->responsibleOffice),
            'targetImplementationDate' => $recommendation->target_implementation_date?->toDateString(),
            'status' => $recommendation->status,
            'createdBy' => $this->user($recommendation->creator),
            'updatedBy' => $this->user($recommendation->updater),
            'finalizedAt' => $recommendation->finalized_at?->toIso8601String(),
            'cmsTransferKey' => $recommendation->cms_transfer_key,
            'cmsRecommendationId' => $recommendation->cms_recommendation_id,
            'transferredToCmsAt' => $recommendation->transferred_to_cms_at?->toIso8601String(),
            'lockVersion' => $recommendation->lock_version,
        ];
    }

    /** @return array<string, mixed> */
    public function responseData(ManagementResponse $response): array
    {
        $response = $this->loadResponse($response);

        return [
            'id' => $response->id,
            'familyUuid' => $response->response_family_uuid,
            'versionNumber' => $response->version_number,
            'supersedesResponseId' => $response->supersedes_response_id,
            'isCurrentRevision' => $response->is_current_revision,
            'responseCode' => $response->response_code,
            'responseKind' => $response->response_kind,
            'agreementPosition' => $response->agreement_position,
            'managementComment' => $response->management_comment,
            'proposedAction' => $response->proposed_action,
            'responsibleOfficeId' => $response->responsible_office_id,
            'responsibleOffice' => $this->office($response->responsibleOffice),
            'responsibleUserId' => $response->responsible_user_id,
            'proposedTargetDate' => $response->proposed_target_date?->toDateString(),
            'status' => $response->status,
            'authoredBy' => $this->user($response->author),
            'submittedAt' => $response->submitted_at?->toIso8601String(),
            'clarificationRequest' => $response->clarification_request,
            'clarificationRequestedAt' => $response->clarification_requested_at?->toIso8601String(),
            'extensionRequestedAt' => $response->extension_requested_at?->toIso8601String(),
            'extensionRequestedDueDate' => $response->extension_requested_due_date?->toDateString(),
            'extensionRequestedBy' => $this->user($response->extensionRequester),
            'extensionApprovedAt' => $response->extension_approved_at?->toIso8601String(),
            'extensionApprovedDueDate' => $response->extension_approved_due_date?->toDateString(),
            'extensionApprovedBy' => $this->user($response->extensionApprover),
            'extensionReason' => $response->extension_reason,
            'submittedLate' => (bool) $response->submitted_late,
            'lateReason' => $response->late_reason,
            'supplementalReason' => $response->supplemental_reason,
            'finalizedAt' => $response->finalized_at?->toIso8601String(),
            'finalizedBy' => $this->user($response->finalizer),
            'lockVersion' => $response->lock_version,
            'createdAt' => $response->created_at?->toIso8601String(),
            'updatedAt' => $response->updated_at?->toIso8601String(),
            'attachments' => $response->attachments
                ->map(fn (AemsDialogueAttachment $attachment): array => $this->attachmentData($attachment))
                ->values(),
            'rejoinders' => $response->rejoinders
                ->map(fn (AuditorRejoinder $rejoinder): array => $this->rejoinderData($rejoinder))
                ->values(),
        ];
    }

    /** @return array<string, mixed> */
    public function transmittalData(AemsFindingTransmittal $transmittal): array
    {
        $transmittal->loadMissing([
            'sender', 'recipients.user', 'recipients.office', 'recipients.acknowledger',
            'recipients.events.actor', 'events.actor',
        ]);

        return [
            'id' => $transmittal->id,
            'transmittalCode' => $transmittal->transmittal_code,
            'findingRevisionNumber' => $transmittal->finding_revision_number,
            'transmittalMethod' => $transmittal->transmittal_method,
            'transmittalReference' => $transmittal->transmittal_reference,
            'confidentiality' => $transmittal->confidentiality,
            'sentAt' => $transmittal->sent_at?->toIso8601String(),
            'sentBy' => $this->user($transmittal->sender),
            'responseDueDate' => $transmittal->response_due_date?->toDateString(),
            'contentSnapshot' => $transmittal->content_snapshot,
            'lockVersion' => $transmittal->lock_version,
            'recipients' => $transmittal->recipients->map(fn (AemsFindingTransmittalRecipient $recipient): array => [
                'id' => $recipient->id,
                'recipientType' => $recipient->recipient_type,
                'recipientName' => $recipient->recipient_name,
                'recipientUserId' => $recipient->recipient_user_id,
                'recipientOfficeId' => $recipient->recipient_office_id,
                'deliveryStatus' => $recipient->delivery_status,
                'deliveredAt' => $recipient->delivered_at?->toIso8601String(),
                'acknowledgedAt' => $recipient->acknowledged_at?->toIso8601String(),
                'acknowledgedBy' => $this->user($recipient->acknowledger),
                'acknowledgementComment' => $recipient->acknowledgement_comment,
                'deliveryReference' => $recipient->delivery_reference,
                'lockVersion' => $recipient->lock_version,
                'events' => $recipient->events->map(fn (AemsFindingTransmittalEvent $event): array => [
                    'id' => $event->id,
                    'eventType' => $event->event_type,
                    'content' => $event->content,
                    'recordedAt' => $event->recorded_at?->toIso8601String(),
                    'actor' => $this->user($event->actor),
                ])->values()->all(),
            ])->values()->all(),
            'events' => $transmittal->events->map(fn (AemsFindingTransmittalEvent $event): array => [
                'id' => $event->id,
                'eventType' => $event->event_type,
                'content' => $event->content,
                'recordedAt' => $event->recorded_at?->toIso8601String(),
                'actor' => $this->user($event->actor),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function rejoinderData(AuditorRejoinder $rejoinder): array
    {
        $rejoinder->loadMissing(['author', 'finalizer']);

        return [
            'id' => $rejoinder->id,
            'versionNumber' => $rejoinder->version_number,
            'disposition' => $rejoinder->disposition,
            'rejoinder' => $rejoinder->rejoinder,
            'status' => $rejoinder->status,
            'authoredBy' => $this->user($rejoinder->author),
            'finalizedAt' => $rejoinder->finalized_at?->toIso8601String(),
            'finalizedBy' => $this->user($rejoinder->finalizer),
            'lockVersion' => $rejoinder->lock_version,
            'createdAt' => $rejoinder->created_at?->toIso8601String(),
            'updatedAt' => $rejoinder->updated_at?->toIso8601String(),
            'attachments' => $rejoinder->attachments
                ->map(fn (AemsDialogueAttachment $attachment): array => $this->attachmentData($attachment))
                ->values(),
        ];
    }

    /** @return array<string, mixed> */
    public function attachmentData(AemsDialogueAttachment $attachment): array
    {
        $attachment->loadMissing(['documentVersion', 'uploader']);
        $version = $attachment->documentVersion;

        return [
            'id' => $attachment->id,
            'attachmentCode' => $attachment->attachment_code,
            'caption' => $attachment->caption,
            'documentVersionId' => $attachment->document_version_id,
            'fileName' => $version?->original_file_name,
            'fileSize' => $version?->file_size,
            'mimeType' => $version?->mime_type,
            'checksumSha256' => $version?->checksum_sha256,
            'fileVersionNumber' => $version?->version_number,
            'uploadedBy' => $this->user($attachment->uploader),
            'uploadedAt' => $attachment->created_at?->toIso8601String(),
        ];
    }

    private function findingFromIssue(
        Request $request,
        AuditEngagement $engagement,
        AuditIssue $issue,
    ): AuditFinding {
        $existing = AuditFinding::query()->where('source_issue_id', $issue->id)->first();
        if ($existing) {
            return $existing;
        }
        $finding = AuditFinding::query()->create([
            'finding_family_uuid' => (string) Str::uuid(),
            'revision_number' => 0,
            'is_current_revision' => true,
            'audit_engagement_id' => $engagement->id,
            'source_issue_id' => $issue->id,
            'finding_code' => $this->nextFindingCode($engagement),
            'title' => $issue->title,
            'criteria' => '',
            'condition' => $issue->exception_description,
            'cause' => '',
            'effect' => '',
            'conclusion' => null,
            'significance_classification' => null,
            'effect_classification' => null,
            'revision_type' => 'ORIGINAL',
            'risk_rating_id' => $issue->risk_rating_id,
            'responsible_office_id' => $issue->responsible_office_id,
            'status' => 'DRAFT',
            'authored_by' => $request->user()->id,
            'lock_version' => 1,
        ]);
        $finding->workingPaperVersions()->sync($issue->workingPaperVersions->modelKeys());
        $finding->evidence()->sync($issue->evidence->modelKeys());
        $this->recordFinding($request, $engagement, $finding, 'CREATED_FROM_ISSUE', null, 'DRAFT');

        return $finding;
    }

    private function ensureIssueSupport(AuditIssue $issue, bool $approved): void
    {
        if ($issue->workingPaperVersions->isEmpty() && $issue->evidence->isEmpty()) {
            throw ValidationException::withMessages([
                'support' => ['Link at least one working-paper version or evidence record.'],
            ]);
        }
        if (! $approved) {
            return;
        }
        if ($issue->workingPaperVersions->contains(
            fn (WorkingPaperVersion $version): bool => $version->workingPaper?->status !== 'APPROVED',
        )) {
            throw ValidationException::withMessages([
                'workingPaperVersionIds' => ['Issue validation requires approved working-paper support.'],
            ]);
        }
        if ($issue->evidence->contains(
            fn (AuditEvidence $evidence): bool => ! in_array($evidence->status, ['VERIFIED', 'LOCKED'], true),
        )) {
            throw ValidationException::withMessages([
                'evidenceIds' => ['Issue validation requires verified or locked evidence.'],
            ]);
        }
    }

    private function ensureFindingComplete(AuditFinding $finding, bool $requireAssessedEvidence = false): void
    {
        foreach (['criteria', 'condition', 'cause', 'effect', 'conclusion'] as $field) {
            if (! trim((string) $finding->{$field})) {
                throw ValidationException::withMessages([
                    $field => [Str::headline($field).' is required before submission.'],
                ]);
            }
        }
        if ($finding->workingPaperVersions->isEmpty()) {
            throw ValidationException::withMessages([
                'workingPaperVersionIds' => ['At least one approved working-paper version is required.'],
            ]);
        }
        if ($finding->workingPaperVersions->contains(
            fn (WorkingPaperVersion $version): bool => $version->workingPaper?->status !== 'APPROVED',
        )) {
            throw ValidationException::withMessages([
                'workingPaperVersionIds' => ['All cited working papers must be approved.'],
            ]);
        }
        if ($finding->evidence->isEmpty()) {
            throw ValidationException::withMessages([
                'evidenceIds' => ['At least one verified evidence version is required.'],
            ]);
        }
        if ($finding->evidence->contains(
            fn (AuditEvidence $evidence): bool => ! in_array($evidence->status, ['VERIFIED', 'LOCKED'], true),
        )) {
            throw ValidationException::withMessages([
                'evidenceIds' => ['All cited evidence must be verified or locked.'],
            ]);
        }
        if ($finding->fieldworkRecordVersions->contains(
            fn (AemsFieldworkRecordVersion $version): bool => $version->record?->status !== 'FINALIZED',
        )) {
            throw ValidationException::withMessages([
                'fieldworkRecordVersionIds' => ['All directly linked fieldwork records must be finalized.'],
            ]);
        }
        if ($finding->procedures->isEmpty()) {
            throw ValidationException::withMessages([
                'procedureIds' => ['At least one approved-program procedure is required to establish criteria traceability.'],
            ]);
        }
        if ($requireAssessedEvidence) {
            foreach ($finding->evidence as $evidence) {
                $assessment = $evidence->currentAssessment;
                if (! $this->evidenceRequests->eligibleForFinalizedFinding($assessment)) {
                    $reasons = $this->evidenceRequests->evidenceEligibility($assessment)['reasons'];
                    throw ValidationException::withMessages([
                        'evidenceIds' => $reasons ?: ['Every cited evidence version must be professionally eligible before finding validation.'],
                    ]);
                }
            }
        }
        if ($finding->recommendations->where('status', 'DRAFT')->isEmpty()
            && ! trim((string) $finding->no_recommendation_reason)) {
            throw ValidationException::withMessages([
                'recommendations' => ['Add a draft recommendation or document why none is required.'],
            ]);
        }
    }

    private function finalizeRecommendations(Request $request, AuditFinding $finding): void
    {
        $drafts = $finding->recommendations->where('status', 'DRAFT');
        if ($drafts->isEmpty() && ! trim((string) $finding->no_recommendation_reason)) {
            throw ValidationException::withMessages([
                'recommendations' => ['A recommendation or documented reason is required before finalization.'],
            ]);
        }
        foreach ($drafts as $recommendation) {
            $snapshot = $this->recommendationData($recommendation);
            $recommendation->update([
                'status' => 'FINALIZED',
                'finalized_at' => now(),
                'finalized_by' => $request->user()->id,
                'finalized_snapshot' => $snapshot,
                'lock_version' => $recommendation->lock_version + 1,
            ]);
        }
    }

    /** @return Collection<int, WorkingPaperVersion> */
    private function workingPaperVersions(AuditEngagement $engagement, array $ids): Collection
    {
        $ids = collect($ids)->map(fn ($id): int => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }
        $records = WorkingPaperVersion::query()
            ->whereHas('workingPaper', fn ($paper) => $paper
                ->where('audit_engagement_id', $engagement->id))
            ->whereIn('id', $ids)
            ->get();
        if ($records->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'workingPaperVersionIds' => ['Every working-paper version must belong to this engagement.'],
            ]);
        }

        return $records;
    }

    /** @return Collection<int, AuditEvidence> */
    private function evidence(AuditEngagement $engagement, array $ids): Collection
    {
        $ids = collect($ids)->map(fn ($id): int => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }
        $records = AuditEvidence::query()
            ->where('audit_engagement_id', $engagement->id)
            ->whereIn('id', $ids)
            ->get();
        if ($records->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'evidenceIds' => ['Every evidence record must belong to this engagement.'],
            ]);
        }

        return $records;
    }

    private function ensureOffice(AuditEngagement $engagement, int $officeId): void
    {
        if (! $engagement->offices()->whereKey($officeId)->exists()) {
            throw ValidationException::withMessages([
                'responsibleOfficeId' => ['The responsible office must be in the engagement scope.'],
            ]);
        }
    }

    private function ensureRiskRating(int $riskRatingId): void
    {
        $valid = MasterList::query()
            ->where('code', 'RISK_LEVEL')
            ->whereHas('items', fn ($items) => $items
                ->whereKey($riskRatingId)
                ->where('is_active', true))
            ->exists();
        if (! $valid) {
            throw ValidationException::withMessages([
                'riskRatingId' => ['Select an active risk rating.'],
            ]);
        }
    }

    private function lockIssue(
        AuditEngagement $engagement,
        AuditIssue $issue,
        int $lockVersion,
    ): AuditIssue {
        $locked = AuditIssue::query()->lockForUpdate()->findOrFail($issue->id);
        if ((int) $locked->audit_engagement_id !== (int) $engagement->id) {
            throw ValidationException::withMessages(['issue' => ['The issue does not belong to this engagement.']]);
        }
        if ($locked->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['This issue changed in another session. Refresh before continuing.'],
            ]);
        }

        return $locked;
    }

    private function lockFinding(
        AuditEngagement $engagement,
        AuditFinding $finding,
        int $lockVersion,
    ): AuditFinding {
        $locked = AuditFinding::query()->lockForUpdate()->findOrFail($finding->id);
        if ((int) $locked->audit_engagement_id !== (int) $engagement->id) {
            throw ValidationException::withMessages(['finding' => ['The finding does not belong to this engagement.']]);
        }
        if (! $locked->is_current_revision || $locked->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['This finding changed in another session. Refresh before continuing.'],
            ]);
        }

        return $locked;
    }

    private function ensureFinding(
        AuditEngagement $engagement,
        AuditFinding $finding,
    ): void {
        if ((int) $finding->audit_engagement_id !== (int) $engagement->id) {
            throw ValidationException::withMessages([
                'finding' => ['The finding does not belong to this engagement.'],
            ]);
        }
    }

    private function lockResponse(
        AuditFinding $finding,
        ManagementResponse $response,
        int $lockVersion,
    ): ManagementResponse {
        $locked = ManagementResponse::query()->lockForUpdate()->findOrFail($response->id);
        if ((int) $locked->audit_finding_id !== (int) $finding->id
            || ! $locked->is_current_revision) {
            throw ValidationException::withMessages([
                'response' => ['The response is not the current version for this finding.'],
            ]);
        }
        if ($locked->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['This response changed in another session. Refresh before continuing.'],
            ]);
        }

        return $locked;
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function issueAttributes(array $attributes): array
    {
        return [
            'title' => $attributes['title'],
            'exception_description' => $attributes['exceptionDescription'],
            'responsible_office_id' => $attributes['responsibleOfficeId'],
            'risk_rating_id' => $attributes['riskRatingId'],
        ];
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function findingAttributes(array $attributes): array
    {
        return [
            'title' => $attributes['title'],
            'criteria' => $attributes['criteria'],
            'condition' => $attributes['condition'],
            'cause' => $attributes['cause'],
            'effect' => $attributes['effect'],
            'conclusion' => $attributes['conclusion'] ?? null,
            'significance_classification' => $attributes['significanceClassification'] ?? null,
            'effect_classification' => $attributes['effectClassification'] ?? null,
            'no_recommendation_reason' => $attributes['noRecommendationReason'] ?? null,
            'risk_rating_id' => $attributes['riskRatingId'],
            'responsible_office_id' => $attributes['responsibleOfficeId'],
        ];
    }

    /** @param array<string, mixed> $attributes
     * @return array<int, array{fieldwork_record_id:int}>
     */
    private function fieldworkLinks(AuditEngagement $engagement, array $attributes): array
    {
        $versionIds = collect($attributes['fieldworkRecordVersionIds'] ?? [])
            ->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        $recordIds = collect($attributes['fieldworkRecordIds'] ?? [])
            ->map(fn ($id): int => (int) $id)->filter()->unique()->values();

        $versions = AemsFieldworkRecordVersion::query()
            ->whereIn('id', $versionIds)
            ->with('record:id,audit_engagement_id')
            ->get();
        if ($versions->count() !== $versionIds->count()
            || $versions->contains(fn (AemsFieldworkRecordVersion $version): bool => (int) $version->record?->audit_engagement_id !== (int) $engagement->id)) {
            throw ValidationException::withMessages(['fieldworkRecordVersionIds' => ['Every fieldwork version must belong to this engagement.']]);
        }

        if ($recordIds->isNotEmpty()) {
            $records = AemsFieldworkRecord::query()
                ->where('audit_engagement_id', $engagement->id)
                ->whereIn('id', $recordIds)
                ->with('latestVersion')
                ->get();
            if ($records->count() !== $recordIds->count()) {
                throw ValidationException::withMessages(['fieldworkRecordIds' => ['Every fieldwork record must belong to this engagement.']]);
            }
            foreach ($records as $record) {
                if ($record->latestVersion) {
                    $versions->push($record->latestVersion);
                }
            }
        }

        return $versions->unique('id')->mapWithKeys(
            fn (AemsFieldworkRecordVersion $version): array => [
                $version->id => ['fieldwork_record_id' => $version->fieldwork_record_id],
            ],
        )->all();
    }

    /**
     * Build the explicit finding-to-procedure criteria chain. Procedure IDs
     * may be supplied directly, or are inferred from cited working-paper and
     * fieldwork versions so existing clients retain their traceability.
     *
     * @param array<int, array{fieldwork_record_id:int}> $fieldworkLinks
     * @return array<int, array{criteria_reference:?string,traceability_note:?string,linked_by:int}>
     */
    private function procedureLinks(
        AuditEngagement $engagement,
        array $attributes,
        Collection $workingPapers,
        array $fieldworkLinks,
        int $linkedBy,
    ): array {
        $ids = collect($attributes['procedureIds'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique();

        $workingPapers->loadMissing('workingPaper:id,audit_program_procedure_id');
        $ids = $ids->merge(
            $workingPapers->pluck('workingPaper.audit_program_procedure_id')->filter(),
        );

        $versionIds = collect(array_keys($fieldworkLinks))
            ->map(fn ($id): int => (int) $id)
            ->filter();
        if ($versionIds->isNotEmpty()) {
            $ids = $ids->merge(
                AemsFieldworkRecordVersion::query()
                    ->whereIn('id', $versionIds)
                    ->pluck('audit_program_procedure_id'),
            );
        }
        $ids = $ids->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $procedures = AuditProgramProcedure::query()
            ->whereIn('id', $ids)
            ->whereHas('program', fn ($program) => $program
                ->where('audit_engagement_id', $engagement->id)
                ->whereNull('deleted_at'))
            ->get();
        if ($procedures->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'procedureIds' => ['Every criteria-traceability procedure must belong to this engagement.'],
            ]);
        }

        return $procedures->mapWithKeys(
            fn (AuditProgramProcedure $procedure): array => [
                $procedure->id => [
                    'criteria_reference' => $procedure->audit_criteria,
                    'traceability_note' => 'Criteria linked from approved audit procedure '.$procedure->procedure_code.'.',
                    'linked_by' => $linkedBy,
                ],
            ],
        )->all();
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function recommendationAttributes(array $attributes): array
    {
        return [
            'recommendation' => trim((string) $attributes['recommendation']),
            'responsible_office_id' => $attributes['responsibleOfficeId'],
            'target_implementation_date' => $attributes['targetImplementationDate'] ?? null,
        ];
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function responseAttributes(array $attributes, AuditFinding $finding): array
    {
        if (! empty($attributes['responsibleUserId'])) {
            $responsible = User::query()->find((int) $attributes['responsibleUserId']);
            if (! $responsible
                || (int) $responsible->office_id !== (int) $finding->responsible_office_id) {
                throw ValidationException::withMessages([
                    'responsibleUserId' => ['The responsible person must belong to the Finding office.'],
                ]);
            }
        }

        return [
            'response_kind' => strtoupper((string) ($attributes['responseKind'] ?? 'ORIGINAL')),
            'agreement_position' => $attributes['agreementPosition'],
            'management_comment' => trim((string) $attributes['managementComment']),
            'proposed_action' => isset($attributes['proposedAction'])
                ? trim((string) $attributes['proposedAction'])
                : null,
            'responsible_office_id' => $finding->responsible_office_id,
            'responsible_user_id' => $attributes['responsibleUserId'] ?? null,
            'proposed_target_date' => $attributes['proposedTargetDate'] ?? null,
            'supplemental_reason' => $attributes['supplementalReason'] ?? null,
        ];
    }

    private function validExtensionDate(mixed $date, AuditFinding $finding): string
    {
        if (! is_string($date) || blank($date)) {
            throw ValidationException::withMessages(['extensionDueDate' => ['A proposed extension date is required.']]);
        }
        try {
            $parsed = \Illuminate\Support\Carbon::parse($date)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['extensionDueDate' => ['The extension date is invalid.']]);
        }
        if ($parsed->lte(now()->startOfDay())
            || ($finding->management_response_due_date && $parsed->lte($finding->management_response_due_date->copy()->startOfDay()))) {
            throw ValidationException::withMessages(['extensionDueDate' => ['The extension date must be after the current due date and today.']]);
        }

        return $parsed->toDateString();
    }

    private function nextIssueCode(AuditEngagement $engagement): string
    {
        return $this->nextCode(
            'ISS',
            $engagement,
            AuditIssue::query()->withTrashed(),
            'issue_code',
        );
    }

    private function nextFindingCode(AuditEngagement $engagement): string
    {
        return $this->nextCode(
            'FND',
            $engagement,
            AuditFinding::query()->withTrashed(),
            'finding_code',
        );
    }

    private function nextCode(
        string $prefix,
        AuditEngagement $engagement,
        mixed $query,
        string $column,
    ): string {
        $sequence = (clone $query)->where('audit_engagement_id', $engagement->id)->count() + 1;
        do {
            $code = sprintf('%s-%s-%03d', $prefix, $engagement->engagement_code, $sequence++);
        } while ((clone $query)
            ->where('audit_engagement_id', $engagement->id)
            ->where($column, $code)
            ->exists());

        return $code;
    }

    private function nextRecommendationCode(AuditFinding $finding): string
    {
        $sequence = AuditRecommendation::query()
            ->withTrashed()
            ->where('audit_finding_id', $finding->id)
            ->count() + 1;
        do {
            $code = sprintf('REC-%s-%02d', $finding->finding_code, $sequence++);
        } while (AuditRecommendation::query()
            ->withTrashed()
            ->where('recommendation_code', $code)
            ->exists());

        return $code;
    }

    /** @return list<string> */
    private function issueRelations(): array
    {
        return [
            'responsibleOffice',
            'riskRating',
            'raiser',
            'reviewer',
            'dispositionRecorder',
            'mergedInto:id,issue_code,title,status',
            'workingPaperVersions.workingPaper',
            'evidence.currentAssessment',
            'finding:id,source_issue_id',
            'withdrawnBy',
        ];
    }

    /** @return list<string> */
    private function findingRelations(): array
    {
        return [
            'responsibleOffice',
            'riskRating',
            'author',
            'validator',
            'communicator',
            'finalizer',
            'withdrawnBy',
            'directCreator',
            'fieldworkRecordVersions.record',
            'procedures',
            'revisions',
            'workingPaperVersions.workingPaper',
            'evidence.currentAssessment',
            'evidence.currentAssessment.documentVersion',
            'recommendations.responsibleOffice',
            'recommendations.creator',
            'recommendations.updater',
            'managementResponses.responsibleOffice',
            'managementResponses.author',
            'managementResponses.finalizer',
            'managementResponses.extensionRequester',
            'managementResponses.extensionApprover',
            'managementResponses.attachments.documentVersion',
            'managementResponses.attachments.uploader',
            'managementResponses.rejoinders.author',
            'managementResponses.rejoinders.finalizer',
            'managementResponses.rejoinders.attachments.documentVersion',
            'managementResponses.rejoinders.attachments.uploader',
            'dueProcess.actor',
            'dueProcess.response',
            'dueProcess.attachments.documentVersion',
            'dueProcess.attachments.uploader',
            'transmittals.sender',
            'transmittals.recipients.user',
            'transmittals.recipients.office',
            'transmittals.recipients.acknowledger',
            'transmittals.recipients.events.actor',
            'transmittals.events.actor',
        ];
    }

    private function loadIssue(AuditIssue $issue): AuditIssue
    {
        return $issue->fresh($this->issueRelations());
    }

    private function loadFinding(AuditFinding $finding): AuditFinding
    {
        return $finding->fresh($this->findingRelations());
    }

    private function loadResponse(ManagementResponse $response): ManagementResponse
    {
        return $response->fresh([
            'responsibleOffice',
            'author',
            'finalizer',
            'extensionRequester',
            'extensionApprover',
            'attachments.documentVersion',
            'attachments.uploader',
            'rejoinders.author',
            'rejoinders.finalizer',
            'rejoinders.attachments.documentVersion',
            'rejoinders.attachments.uploader',
        ]);
    }

    /** @return array<string, mixed> */
    private function findingContent(AuditFinding $finding): array
    {
        return [
            'title' => $finding->title,
            'criteria' => $finding->criteria,
            'condition' => $finding->condition,
            'cause' => $finding->cause,
            'effect' => $finding->effect,
            'conclusion' => $finding->conclusion,
            'significanceClassification' => $finding->significance_classification,
            'effectClassification' => $finding->effect_classification,
            'noRecommendationReason' => $finding->no_recommendation_reason,
            'riskRatingId' => $finding->risk_rating_id,
            'riskRating' => $finding->riskRating?->only(['id', 'code', 'label']),
            'responsibleOfficeId' => $finding->responsible_office_id,
            'responsibleOffice' => $this->office($finding->responsibleOffice),
        ];
    }

    /** @return array<string, mixed> */
    private function issueAudit(AuditIssue $issue): array
    {
        return [
            'id' => $issue->id,
            'issueCode' => $issue->issue_code,
            'status' => $issue->status,
            'riskRatingId' => $issue->risk_rating_id,
            'responsibleOfficeId' => $issue->responsible_office_id,
        ];
    }

    /** @return array<string, mixed> */
    private function findingAudit(AuditFinding $finding): array
    {
        return [
            'id' => $finding->id,
            'findingCode' => $finding->finding_code,
            'revisionNumber' => $finding->revision_number,
            'status' => $finding->status,
            'riskRatingId' => $finding->risk_rating_id,
            'responsibleOfficeId' => $finding->responsible_office_id,
        ];
    }

    /** @return array<string, mixed> */
    private function responseAudit(ManagementResponse $response): array
    {
        return [
            'id' => $response->id,
            'responseCode' => $response->response_code,
            'versionNumber' => $response->version_number,
            'status' => $response->status,
            'agreementPosition' => $response->agreement_position,
        ];
    }

    /** @return array<string, mixed> */
    private function workingPaperVersionData(WorkingPaperVersion $version): array
    {
        return [
            'id' => $version->id,
            'versionNumber' => $version->version_number,
            'workingPaperCode' => $version->workingPaper?->working_paper_code,
            'title' => $version->workingPaper?->title,
            'status' => $version->workingPaper?->status,
            'procedureId' => $version->workingPaper?->audit_program_procedure_id,
        ];
    }

    /** @return array<string, mixed> */
    private function evidenceData(AuditEvidence $evidence): array
    {
        $assessment = $evidence->currentAssessment;
        $eligibility = $this->evidenceRequests->evidenceEligibility($assessment);
        return [
            'id' => $evidence->id,
            'evidenceCode' => $evidence->evidence_code,
            'title' => $evidence->title,
            'status' => $evidence->status,
            'versionNumber' => $evidence->version_number,
            'checksumSha256' => $evidence->checksum_sha256,
            'assessmentRequired' => (bool) $evidence->assessment_required,
            'assessment' => $assessment ? [
                'id' => $assessment->id,
                'versionNumber' => $assessment->version_number,
                'status' => $assessment->status,
                'documentVersionId' => $assessment->document_version_id,
                'isRestricted' => $assessment->is_restricted,
                'accessRestrictions' => $assessment->access_restrictions,
                'exceptionApprovedAt' => $assessment->exception_approved_at?->toIso8601String(),
                'eligibleForFinalizedFinding' => $eligibility['eligible'],
                'eligibilityReasons' => $eligibility['reasons'],
            ] : null,
            'eligibleForFinalizedFinding' => $eligibility['eligible'],
            'eligibilityReasons' => $eligibility['reasons'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function history(string $subjectType, int $subjectId): array
    {
        return EngagementEvent::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->with('actor:id,employee_id,name,initials')
            ->oldest('id')
            ->get()
            ->map(fn (EngagementEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action,
                'fromStatus' => $event->from_status,
                'toStatus' => $event->to_status,
                'comment' => $event->comment,
                'actor' => $this->user($event->actor),
                'createdAt' => $event->created_at?->toIso8601String(),
            ])->all();
    }

    private function recordFinding(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        string $action,
        ?string $from,
        ?string $to,
        ?array $before = null,
        ?string $comment = null,
    ): void {
        $this->record(
            $request,
            $engagement,
            'AUDIT_FINDING',
            $finding->id,
            $finding->finding_code,
            "FINDING_{$action}",
            $from,
            $to,
            $before,
            $this->findingAudit($finding),
            $comment,
            $finding->finding_family_uuid,
            $finding->revision_number,
        );
    }

    private function record(
        Request $request,
        AuditEngagement $engagement,
        string $subjectType,
        int $subjectId,
        string $subjectCode,
        string $action,
        ?string $from,
        ?string $to,
        ?array $before,
        ?array $after,
        ?string $comment = null,
        ?string $familyUuid = null,
        ?int $version = null,
        ?array $documentVersionIds = null,
    ): void {
        $this->support->event(
            $request,
            $engagement,
            $action,
            $from,
            $to,
            $before,
            $after,
            $comment,
            $subjectType,
            $subjectId,
            $version,
            $subjectCode,
            $familyUuid,
            $documentVersionIds,
        );
        $this->support->audit(
            $request,
            Str::lower(str_replace('_', '.', $action)),
            $engagement,
            $before,
            $after,
            ['subjectType' => $subjectType, 'subjectId' => $subjectId],
        );
    }

    /** @return list<array<string, mixed>> */
    private function masterItems(string $code): array
    {
        return MasterList::query()
            ->where('code', $code)
            ->firstOrFail()
            ->items()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get(['id', 'code', 'label', 'description'])
            ->map->toArray()
            ->all();
    }

    /** @return array<string, mixed> */
    private function storeDialogueFile(
        UploadedFile $file,
        AuditEngagement $engagement,
    ): array {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid().($extension ? ".{$extension}" : '');
        $path = Storage::disk('local')->putFileAs(
            "aems/engagements/{$engagement->id}/dialogue",
            $file,
            $storedName,
        );
        if (! $path) {
            throw ValidationException::withMessages([
                'file' => ['The supporting document could not be stored.'],
            ]);
        }

        return [
            'original_file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_extension' => $extension ?: null,
            'file_size' => $file->getSize(),
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    /** @param array<string, mixed> $stored */
    private function createDialogueDocument(
        Request $request,
        AuditEngagement $engagement,
        AuditFinding $finding,
        string $subjectCode,
        array $stored,
    ): DocumentVersion {
        $documentType = MasterList::query()
            ->where('code', 'DOCUMENT_TYPE')
            ->firstOrFail()
            ->items()
            ->where('code', 'OTHER')
            ->firstOrFail();
        $confidentiality = MasterList::query()
            ->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->firstOrFail()
            ->items()
            ->where('code', 'INTERNAL')
            ->firstOrFail();
        $document = Document::query()->create([
            'document_type_id' => $documentType->id,
            'confidentiality_level_id' => $confidentiality->id,
            'title' => "Dialogue support — {$subjectCode}",
            'description' => "Private support exchanged for {$finding->finding_code}.",
            'owner_module' => 'AEMS',
            'library_visible' => false,
            ...$stored,
            'uploaded_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'is_active' => true,
        ]);
        $document->forceFill([
            'document_code' => $this->runtime->formatNumber('document_number_format', $document->id),
        ])->save();
        $version = $document->versions()->create([
            'version_number' => 1,
            'version_label' => 'Dialogue attachment version 1',
            'change_summary' => 'Initial immutable dialogue supporting document.',
            ...$stored,
            'uploaded_by' => $request->user()->id,
        ]);
        $document->forceFill([
            'current_version_id' => $version->id,
            'version' => $version->version_label,
        ])->save();
        $document->links()->create([
            'module_code' => 'AEMS',
            'record_type' => 'AUDIT_FINDING',
            'record_id' => $finding->id,
            'record_code' => $finding->finding_code,
            'record_label' => "{$finding->finding_code} — {$finding->title}",
            'linked_by' => $request->user()->id,
        ]);

        return $version;
    }

    private function nextAttachmentCode(string $subjectCode, int $currentCount): string
    {
        $sequence = $currentCount + 1;
        do {
            $code = sprintf('ATT-%s-%02d', $subjectCode, $sequence++);
        } while (AemsDialogueAttachment::query()
            ->where('attachment_code', $code)
            ->exists());

        return $code;
    }

    /** @return array<string, mixed>|null */
    private function user(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'name' => $user->name,
            'initials' => $user->initials,
        ] : null;
    }

    /** @return array<string, mixed>|null */
    private function office(?Office $office): ?array
    {
        return $office ? $office->only(['id', 'code', 'name']) : null;
    }
}
