<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\CompletionAssessment;
use App\Models\CompletionAssessmentItem;
use App\Models\CompletionAssessmentVersion;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\MasterList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AemsCompletionAssessmentService
{
    /** @var array<string, string> */
    private const CRITERIA = [
        'OBJECTIVES_ACHIEVED' => 'Engagement objectives achieved',
        'SCOPE_COMPLETED' => 'Approved scope completed',
        'PROGRAM_COMPLETED' => 'Approved Audit Program completed',
        'PROCEDURES_TERMINAL' => 'Required procedures completed or validly waived',
        'WORKING_PAPERS_REVIEWED' => 'Working Papers properly reviewed',
        'EVIDENCE_SUFFICIENT' => 'Evidence is sufficient, reliable, relevant and useful',
        'FINDINGS_FINALIZED' => 'Findings are supported and finalized',
        'MANAGEMENT_DIALOGUE_COMPLETED' => 'Management-response dialogue completed',
        'ENTRY_CONFERENCE_TERMINAL' => 'Entry Conference completed or validly waived',
        'EXIT_CONFERENCE_TERMINAL' => 'Exit Conference completed or validly waived',
        'FINAL_REPORT_ISSUED' => 'Final report properly reviewed and issued',
        'RECIPIENTS_COMPLETE' => 'Report recipients and issuance records complete',
        'CMS_TRANSFER_COMPLETE' => 'Recommendations transferred or formally excluded',
        'START_DATE_VARIANCE' => 'Planned versus actual start date',
        'COMPLETION_DATE_VARIANCE' => 'Planned versus actual completion date',
        'REPORT_DATE_VARIANCE' => 'Planned versus actual report date',
        'PERSON_DAYS_VARIANCE' => 'Planned versus actual person-days',
        'COST_VARIANCE' => 'Planned versus actual cost, when available',
        'KPI_RESULTS' => 'Engagement KPI targets versus results, when KPIs exist',
        'MILESTONES' => 'Milestone accomplishment',
        'LIMITATIONS' => 'Scope limitations and nonconformance disclosures',
        'DELAYS' => 'Significant delays and causes',
        'LESSONS' => 'Lessons learned',
        'IMPROVEMENT_ACTIONS' => 'Improvement actions for future engagements',
        'CLOSURE_READINESS' => 'Overall readiness for closure',
    ];

    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly AemsNotificationService $notifications,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(Request $request, AuditEngagement $engagement): array
    {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.completion-assessment.view',
        );

        $assessments = $engagement->completionAssessments()
            ->with([
                'items.responsibleUser:id,name',
                'versions.documentVersion',
                'preparer:id,name',
                'reviewer:id,name',
                'approver:id,name',
            ])
            ->orderByDesc('revision_number')
            ->get();

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
                'lockVersion' => $engagement->lock_version,
            ],
            'criteria' => collect(self::CRITERIA)
                ->map(fn (string $label, string $code): array => compact('code', 'label'))
                ->values()
                ->all(),
            'assessments' => $assessments
                ->map(fn (CompletionAssessment $assessment): array => $this->assessmentData($assessment))
                ->values()
                ->all(),
            'currentAssessment' => ($current = $assessments->firstWhere('is_current_revision', true))
                ? $this->assessmentData($current)
                : null,
        ];
    }

    public function create(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): CompletionAssessment {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.completion-assessment.create',
        );
        if (! in_array($engagement->status, ['ISSUED', 'CLOSURE_REVIEW', 'COMPLETED'], true)) {
            throw ValidationException::withMessages([
                'engagement' => ['Completion Assessment begins only after report issuance.'],
            ]);
        }
        if ($engagement->currentCompletionAssessment()->exists()) {
            throw ValidationException::withMessages([
                'assessment' => ['A current Completion Assessment already exists.'],
            ]);
        }

        return DB::transaction(function () use ($request, $engagement, $attributes): CompletionAssessment {
            $sequence = $engagement->completionAssessments()->count() + 1;
            $assessment = $engagement->completionAssessments()->create([
                ...$this->content($attributes),
                'assessment_code' => sprintf('%s-CA-%02d', $engagement->engagement_code, $sequence),
                'revision_number' => 1,
                'is_current_revision' => true,
                'version_no' => 0,
                'status_code' => 'DRAFT',
                'prepared_by' => $request->user()->id,
                'lock_version' => 1,
            ]);
            $this->syncItems($assessment, $attributes['items'] ?? $this->defaultItems($engagement));
            $this->snapshot($request, $assessment, 'Initial draft');
            $this->record($request, $engagement, $assessment, 'aems.completion_assessment.created', null, 'DRAFT');

            return $this->load($assessment);
        });
    }

    public function update(
        Request $request,
        AuditEngagement $engagement,
        CompletionAssessment $assessment,
        array $attributes,
    ): CompletionAssessment {
        $this->ensureAssessment($engagement, $assessment);
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.completion-assessment.update',
        );
        if (! in_array($assessment->status_code, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
            throw ValidationException::withMessages([
                'assessment' => ['Only draft or returned assessments can be edited.'],
            ]);
        }
        $this->assertLock($assessment, (int) $attributes['lockVersion']);

        return DB::transaction(function () use ($request, $engagement, $assessment, $attributes): CompletionAssessment {
            $old = $this->snapshotData($assessment);
            $assessment->fill([
                ...$this->content($attributes),
                'lock_version' => $assessment->lock_version + 1,
            ])->save();
            if (array_key_exists('items', $attributes)) {
                $this->syncItems($assessment, $attributes['items']);
            }
            $this->record(
                $request,
                $engagement,
                $assessment,
                'aems.completion_assessment.updated',
                $assessment->status_code,
                $assessment->status_code,
                $old,
            );

            return $this->load($assessment);
        });
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        CompletionAssessment $assessment,
        string $action,
        int $lockVersion,
        ?string $comment = null,
    ): CompletionAssessment {
        $this->ensureAssessment($engagement, $assessment);
        $action = strtoupper($action);
        $permission = match ($action) {
            'SUBMIT', 'RESUBMIT' => 'aems.completion-assessment.submit',
            'RETURN' => 'aems.completion-assessment.review',
            'APPROVE', 'ACCEPT_BLOCKER' => 'aems.completion-assessment.approve',
            default => throw ValidationException::withMessages(['action' => ['Unsupported assessment action.']]),
        };
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            $permission,
            in_array($action, ['RETURN', 'APPROVE', 'ACCEPT_BLOCKER'], true)
                ? $assessment->prepared_by
                : null,
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $assessment,
            $action,
            $lockVersion,
            $comment,
        ): CompletionAssessment {
            $locked = CompletionAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
            $this->assertLock($locked, $lockVersion);
            $from = $locked->status_code;
            $to = match ($action) {
                'SUBMIT' => $from === 'DRAFT' ? 'PENDING_REVIEW' : null,
                'RETURN' => in_array($from, ['PENDING_REVIEW', 'RESUBMITTED'], true)
                    ? 'RETURNED_FOR_REVISION' : null,
                'RESUBMIT' => $from === 'RETURNED_FOR_REVISION' ? 'RESUBMITTED' : null,
                'APPROVE' => in_array($from, ['PENDING_REVIEW', 'RESUBMITTED'], true)
                    ? 'APPROVED' : null,
                default => null,
            };
            if (! $to) {
                throw ValidationException::withMessages([
                    'action' => ["{$action} is not allowed while the assessment is {$from}."],
                ]);
            }
            if ($action === 'RETURN' && blank($comment)) {
                throw ValidationException::withMessages(['comment' => ['A return comment is required.']]);
            }
            if (in_array($action, ['SUBMIT', 'RESUBMIT', 'APPROVE'], true)) {
                $this->validateComplete($locked);
            }
            if ($action === 'APPROVE') {
                $blockers = $locked->items()
                    ->where('blocking_flag', true)
                    ->whereNotIn('result_code', ['PASS', 'NOT_APPLICABLE'])
                    ->where('blocker_accepted', false)
                    ->pluck('criterion_code');
                if ($blockers->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => $blockers->map(fn ($code) => "Resolve or formally accept {$code}.")->all(),
                    ]);
                }
            }
            $old = $this->snapshotData($locked);
            $changes = [
                'status_code' => $to,
                'lock_version' => $locked->lock_version + 1,
            ];
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) {
                $changes['submitted_by'] = $request->user()->id;
                $changes['submitted_at'] = now();
            }
            if ($action === 'RETURN') {
                $changes['reviewed_by'] = $request->user()->id;
                $changes['reviewed_at'] = now();
                $changes['return_comment'] = $comment;
            }
            if ($action === 'APPROVE') {
                $changes['reviewed_by'] = $request->user()->id;
                $changes['reviewed_at'] = now();
                $changes['approved_by'] = $request->user()->id;
                $changes['approved_at'] = now();
            }
            $locked->fill($changes)->save();
            if (in_array($action, ['SUBMIT', 'RESUBMIT', 'APPROVE'], true)) {
                $this->snapshot($request, $locked, Str::headline(strtolower($action)));
            }
            $this->record(
                $request,
                $engagement,
                $locked,
                'aems.completion_assessment.'.strtolower($action),
                $from,
                $to,
                $old,
                $comment,
            );
            $this->notifications->completionAssessment(
                $request,
                $engagement,
                $locked,
                $action,
            );

            return $this->load($locked);
        });
    }

    public function acceptBlocker(
        Request $request,
        AuditEngagement $engagement,
        CompletionAssessment $assessment,
        CompletionAssessmentItem $item,
        int $lockVersion,
        string $reason,
    ): CompletionAssessment {
        $this->ensureAssessment($engagement, $assessment);
        if ((int) $item->completion_assessment_id !== (int) $assessment->id) {
            abort(404);
        }
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.completion-assessment.approve',
            $assessment->prepared_by,
        );
        $this->assertLock($assessment, $lockVersion);
        if ($assessment->status_code === 'APPROVED') {
            throw ValidationException::withMessages(['assessment' => ['Approved assessments are immutable.']]);
        }
        $item->fill([
            'blocker_accepted' => true,
            'blocker_accepted_by' => $request->user()->id,
            'blocker_accepted_at' => now(),
            'blocker_acceptance_reason' => $reason,
        ])->save();
        $assessment->increment('lock_version');
        $this->record(
            $request,
            $engagement,
            $assessment,
            'aems.completion_assessment.blocker_accepted',
            $assessment->status_code,
            $assessment->status_code,
            null,
            $reason,
        );

        return $this->load($assessment);
    }

    public function revise(
        Request $request,
        AuditEngagement $engagement,
        CompletionAssessment $assessment,
        string $reason,
    ): CompletionAssessment {
        $this->ensureAssessment($engagement, $assessment);
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.completion-assessment.create',
        );
        if ($assessment->status_code !== 'APPROVED' || ! $assessment->is_current_revision) {
            throw ValidationException::withMessages([
                'assessment' => ['Only the current approved assessment can begin a correction revision.'],
            ]);
        }

        return DB::transaction(function () use ($request, $engagement, $assessment, $reason): CompletionAssessment {
            DB::table('completion_assessments')
                ->where('id', $assessment->id)
                ->update(['is_current_revision' => false, 'updated_at' => now()]);
            $revision = $engagement->completionAssessments()->create([
                ...$assessment->only([
                    'assessment_code',
                    'period_from',
                    'period_to',
                    'overall_result_code',
                    'objectives_achievement_summary',
                    'scope_completion_summary',
                    'methodology_assessment',
                    'standards_compliance_assessment',
                    'evidence_sufficiency_assessment',
                    'supervision_assessment',
                    'report_timeliness_assessment',
                    'management_response_assessment',
                    'recommendation_transfer_assessment',
                    'resource_utilization_assessment',
                    'limitations_summary',
                    'lessons_summary',
                    'recommendation_for_closure',
                ]),
                'revision_number' => $assessment->revision_number + 1,
                'supersedes_assessment_id' => $assessment->id,
                'is_current_revision' => true,
                'version_no' => 0,
                'status_code' => 'DRAFT',
                'prepared_by' => $request->user()->id,
                'lock_version' => 1,
            ]);
            foreach ($assessment->items as $item) {
                $revision->items()->create([
                    ...$item->only([
                        'criterion_code',
                        'planned_value',
                        'actual_value',
                        'result_code',
                        'variance_value',
                        'explanation',
                        'related_record_type',
                        'related_record_id',
                        'blocking_flag',
                        'responsible_user_id',
                    ]),
                    'blocker_accepted' => false,
                ]);
            }
            $this->snapshot($request, $revision, $reason);
            $this->record(
                $request,
                $engagement,
                $revision,
                'aems.completion_assessment.revision_created',
                null,
                'DRAFT',
                null,
                $reason,
            );

            return $this->load($revision);
        });
    }

    /** @param list<array<string, mixed>> $items */
    private function syncItems(CompletionAssessment $assessment, array $items): void
    {
        foreach ($items as $item) {
            $code = strtoupper((string) ($item['criterionCode'] ?? ''));
            if (! isset(self::CRITERIA[$code])) {
                throw ValidationException::withMessages(['items' => ["Unknown criterion {$code}."]]);
            }
            $assessment->items()->updateOrCreate(
                ['criterion_code' => $code],
                [
                    'planned_value' => $item['plannedValue'] ?? null,
                    'actual_value' => $item['actualValue'] ?? null,
                    'result_code' => strtoupper($item['resultCode'] ?? 'PENDING'),
                    'variance_value' => $item['varianceValue'] ?? null,
                    'explanation' => trim((string) ($item['explanation'] ?? self::CRITERIA[$code])),
                    'related_record_type' => $item['relatedRecordType'] ?? null,
                    'related_record_id' => $item['relatedRecordId'] ?? null,
                    'blocking_flag' => (bool) ($item['blockingFlag'] ?? false),
                    'responsible_user_id' => $item['responsibleUserId'] ?? null,
                ],
            );
        }
    }

    /** @return list<array<string, mixed>> */
    private function defaultItems(AuditEngagement $engagement): array
    {
        $engagement->loadMissing([
            'programs.procedures',
            'workingPapers',
            'findings.recommendations',
            'entryConference',
            'exitConferences',
            'reports.currentVersion.recipients',
        ]);
        $program = $engagement->programs->firstWhere('is_current_revision', true);
        $issued = $engagement->reports->first(
            fn ($report) => $report->report_stage === 'FINAL_REPORT' && $report->status === 'ISSUED',
        );
        $recommendations = $engagement->findings->flatMap->recommendations;
        $facts = [
            'PROGRAM_COMPLETED' => in_array($program?->status, ['APPROVED', 'ACTIVE', 'COMPLETED'], true),
            'PROCEDURES_TERMINAL' => $program && $program->procedures->isNotEmpty()
                && $program->procedures->every(fn ($p) => in_array($p->status, ['COMPLETED', 'WAIVED'], true)),
            'WORKING_PAPERS_REVIEWED' => $engagement->workingPapers->isNotEmpty()
                && $engagement->workingPapers->every(fn ($p) => in_array($p->status, ['APPROVED', 'SUPERSEDED', 'VOIDED'], true)),
            'FINDINGS_FINALIZED' => $engagement->findings->every('status', 'FINALIZED'),
            'ENTRY_CONFERENCE_TERMINAL' => in_array($engagement->entryConference?->status, ['COMPLETED', 'WAIVED'], true),
            'EXIT_CONFERENCE_TERMINAL' => $engagement->exitConferences
                ->contains(fn ($c) => in_array($c->status, ['COMPLETED', 'WAIVED'], true)),
            'FINAL_REPORT_ISSUED' => $issued !== null,
            'RECIPIENTS_COMPLETE' => $issued?->currentVersion?->recipients->isNotEmpty() === true
                && $issued->currentVersion->recipients->every(fn ($r) => $r->sent_at !== null),
            'CMS_TRANSFER_COMPLETE' => $recommendations->every(
                fn ($r) => in_array($r->status, ['TRANSFERRED', 'EXCLUDED'], true),
            ),
            'PERSON_DAYS_VARIANCE' => (float) $engagement->actual_person_days > 0,
        ];

        return collect(self::CRITERIA)->map(
            fn (string $label, string $code): array => [
                'criterionCode' => $code,
                'plannedValue' => $this->plannedValue($engagement, $code),
                'actualValue' => $this->actualValue($engagement, $issued, $code),
                'resultCode' => array_key_exists($code, $facts)
                    ? ($facts[$code] ? 'PASS' : 'FAIL')
                    : (in_array($code, ['COST_VARIANCE', 'KPI_RESULTS'], true) ? 'NOT_APPLICABLE' : 'PENDING'),
                'explanation' => $label,
                'blockingFlag' => array_key_exists($code, $facts) && ! $facts[$code],
            ],
        )->values()->all();
    }

    private function plannedValue(AuditEngagement $engagement, string $code): ?string
    {
        return match ($code) {
            'START_DATE_VARIANCE' => $engagement->planned_start_date?->toDateString(),
            'COMPLETION_DATE_VARIANCE' => $engagement->planned_end_date?->toDateString(),
            'REPORT_DATE_VARIANCE' => $engagement->expected_report_date?->toDateString(),
            'PERSON_DAYS_VARIANCE' => (string) $engagement->planned_person_days,
            default => null,
        };
    }

    private function actualValue(AuditEngagement $engagement, mixed $issued, string $code): ?string
    {
        return match ($code) {
            'START_DATE_VARIANCE' => $engagement->actual_start_date?->toDateString(),
            'COMPLETION_DATE_VARIANCE' => $engagement->actual_end_date?->toDateString(),
            'REPORT_DATE_VARIANCE' => $issued?->issued_at?->toDateString(),
            'PERSON_DAYS_VARIANCE' => (string) $engagement->actual_person_days,
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function content(array $attributes): array
    {
        return [
            'period_from' => $attributes['periodFrom'] ?? null,
            'period_to' => $attributes['periodTo'] ?? null,
            'overall_result_code' => strtoupper($attributes['overallResultCode']),
            'objectives_achievement_summary' => trim($attributes['objectivesAchievementSummary']),
            'scope_completion_summary' => trim($attributes['scopeCompletionSummary']),
            'methodology_assessment' => trim($attributes['methodologyAssessment']),
            'standards_compliance_assessment' => trim($attributes['standardsComplianceAssessment']),
            'evidence_sufficiency_assessment' => trim($attributes['evidenceSufficiencyAssessment']),
            'supervision_assessment' => trim($attributes['supervisionAssessment']),
            'report_timeliness_assessment' => trim($attributes['reportTimelinessAssessment']),
            'management_response_assessment' => trim($attributes['managementResponseAssessment']),
            'recommendation_transfer_assessment' => trim($attributes['recommendationTransferAssessment']),
            'resource_utilization_assessment' => trim($attributes['resourceUtilizationAssessment']),
            'limitations_summary' => $attributes['limitationsSummary'] ?? null,
            'lessons_summary' => $attributes['lessonsSummary'] ?? null,
            'recommendation_for_closure' => trim($attributes['recommendationForClosure']),
        ];
    }

    private function validateComplete(CompletionAssessment $assessment): void
    {
        $missing = collect(array_keys(self::CRITERIA))->diff($assessment->items()->pluck('criterion_code'));
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => $missing->map(fn ($code) => "Assessment criterion {$code} is required.")->all(),
            ]);
        }
        if ($assessment->items()->whereIn('result_code', ['PENDING', 'NOT_ASSESSED'])->exists()) {
            throw ValidationException::withMessages([
                'items' => ['Every Completion Assessment criterion requires a result.'],
            ]);
        }
    }

    private function snapshot(
        Request $request,
        CompletionAssessment $assessment,
        string $reason,
    ): CompletionAssessmentVersion {
        $assessment->load('items');
        $versionNo = $assessment->version_no + 1;
        $snapshot = $this->snapshotData($assessment);
        $documentVersion = $this->snapshotDocument(
            $request,
            $assessment,
            $versionNo,
            $snapshot,
            $reason,
        );
        $version = $assessment->versions()->create([
            'version_no' => $versionNo,
            'snapshot_json' => $snapshot,
            'document_version_id' => $documentVersion->id,
            'prepared_by' => $request->user()->id,
        ]);
        DB::table('completion_assessments')
            ->where('id', $assessment->id)
            ->update(['version_no' => $versionNo, 'updated_at' => now()]);
        $assessment->forceFill(['version_no' => $versionNo]);

        return $version;
    }

    /** @param array<string, mixed> $snapshot */
    private function snapshotDocument(
        Request $request,
        CompletionAssessment $assessment,
        int $versionNo,
        array $snapshot,
        string $reason,
    ): DocumentVersion {
        $document = $assessment->versions()
            ->with('documentVersion.document')
            ->latest('version_no')
            ->first()?->documentVersion?->document;
        $payload = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $path = "aems/engagements/{$assessment->audit_engagement_id}/completion-assessments/"
            .Str::uuid().'.json';
        Storage::disk('local')->put($path, $payload);
        if (! $document) {
            $type = MasterList::query()->where('code', 'DOCUMENT_TYPE')
                ->firstOrFail()->items()->where('code', 'OTHER')->firstOrFail();
            $confidentiality = MasterList::query()->where('code', 'DOCUMENT_CONFIDENTIALITY')
                ->firstOrFail()->items()->where('code', 'INTERNAL')->firstOrFail();
            $document = Document::query()->create([
                'document_type_id' => $type->id,
                'confidentiality_level_id' => $confidentiality->id,
                'title' => "{$assessment->assessment_code} Completion Assessment",
                'description' => 'Private immutable AEMS Completion Assessment snapshot.',
                'owner_module' => 'AEMS',
                'library_visible' => false,
                'original_file_name' => "{$assessment->assessment_code}-v{$versionNo}.json",
                'storage_path' => $path,
                'mime_type' => 'application/json',
                'file_extension' => 'json',
                'file_size' => strlen($payload),
                'checksum_sha256' => hash('sha256', $payload),
                'uploaded_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'is_active' => true,
            ]);
            $document->forceFill([
                'document_code' => $this->runtime->formatNumber('document_number_format', $document->id),
            ])->save();
            $document->links()->create([
                'module_code' => 'AEMS',
                'record_type' => 'COMPLETION_ASSESSMENT',
                'record_id' => $assessment->id,
                'record_code' => $assessment->assessment_code,
                'record_label' => "{$assessment->assessment_code} - Completion Assessment",
                'linked_by' => $request->user()->id,
            ]);
        }
        $version = $document->versions()->create([
            'version_number' => $versionNo,
            'version_label' => "Completion Assessment version {$versionNo}",
            'change_summary' => $reason,
            'original_file_name' => "{$assessment->assessment_code}-v{$versionNo}.json",
            'storage_path' => $path,
            'mime_type' => 'application/json',
            'file_extension' => 'json',
            'file_size' => strlen($payload),
            'checksum_sha256' => hash('sha256', $payload),
            'uploaded_by' => $request->user()->id,
        ]);
        $document->forceFill([
            'current_version_id' => $version->id,
            'version' => $version->version_label,
            'original_file_name' => $version->original_file_name,
            'storage_path' => $path,
            'file_size' => strlen($payload),
            'checksum_sha256' => $version->checksum_sha256,
            'updated_by' => $request->user()->id,
        ])->save();

        return $version;
    }

    private function assertLock(CompletionAssessment $assessment, int $lockVersion): void
    {
        if ($assessment->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['The Completion Assessment changed in another session. Refresh first.'],
            ]);
        }
    }

    private function ensureAssessment(
        AuditEngagement $engagement,
        CompletionAssessment $assessment,
    ): void {
        if ((int) $assessment->audit_engagement_id !== (int) $engagement->id) {
            abort(404);
        }
    }

    private function load(CompletionAssessment $assessment): CompletionAssessment
    {
        return $assessment->fresh([
            'items.responsibleUser:id,name',
            'versions.documentVersion',
            'preparer:id,name',
            'reviewer:id,name',
            'approver:id,name',
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshotData(CompletionAssessment $assessment): array
    {
        $assessment->loadMissing('items');

        return [
            'assessmentCode' => $assessment->assessment_code,
            'revisionNumber' => $assessment->revision_number,
            'versionNo' => $assessment->version_no,
            'statusCode' => $assessment->status_code,
            ...collect($assessment->only([
                'overall_result_code',
                'objectives_achievement_summary',
                'scope_completion_summary',
                'methodology_assessment',
                'standards_compliance_assessment',
                'evidence_sufficiency_assessment',
                'supervision_assessment',
                'report_timeliness_assessment',
                'management_response_assessment',
                'recommendation_transfer_assessment',
                'resource_utilization_assessment',
                'limitations_summary',
                'lessons_summary',
                'recommendation_for_closure',
            ]))->mapWithKeys(fn ($value, $key) => [Str::camel($key) => $value])->all(),
            'items' => $assessment->items->map(fn (CompletionAssessmentItem $item): array => [
                'id' => $item->id,
                'criterionCode' => $item->criterion_code,
                'plannedValue' => $item->planned_value,
                'actualValue' => $item->actual_value,
                'resultCode' => $item->result_code,
                'varianceValue' => $item->variance_value,
                'explanation' => $item->explanation,
                'blockingFlag' => $item->blocking_flag,
                'blockerAccepted' => $item->blocker_accepted,
                'blockerAcceptanceReason' => $item->blocker_acceptance_reason,
                'relatedRecordType' => $item->related_record_type,
                'relatedRecordId' => $item->related_record_id,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function assessmentData(CompletionAssessment $assessment): array
    {
        $assessment->loadMissing([
            'items.responsibleUser:id,name',
            'versions.documentVersion',
            'preparer:id,name',
            'reviewer:id,name',
            'approver:id,name',
        ]);

        return [
            'id' => $assessment->id,
            ...$this->snapshotData($assessment),
            'isCurrentRevision' => $assessment->is_current_revision,
            'periodFrom' => $assessment->period_from?->toDateString(),
            'periodTo' => $assessment->period_to?->toDateString(),
            'preparedBy' => $assessment->preparer?->only(['id', 'name']),
            'reviewedBy' => $assessment->reviewer?->only(['id', 'name']),
            'approvedBy' => $assessment->approver?->only(['id', 'name']),
            'submittedAt' => $assessment->submitted_at?->toISOString(),
            'reviewedAt' => $assessment->reviewed_at?->toISOString(),
            'approvedAt' => $assessment->approved_at?->toISOString(),
            'returnComment' => $assessment->return_comment,
            'lockVersion' => $assessment->lock_version,
            'versions' => $assessment->versions->map(fn (CompletionAssessmentVersion $version): array => [
                'id' => $version->id,
                'versionNo' => $version->version_no,
                'documentVersionId' => $version->document_version_id,
                'checksumSha256' => $version->documentVersion?->checksum_sha256,
                'createdAt' => $version->created_at?->toISOString(),
            ])->values()->all(),
        ];
    }

    private function record(
        Request $request,
        AuditEngagement $engagement,
        CompletionAssessment $assessment,
        string $action,
        ?string $from,
        ?string $to,
        ?array $old = null,
        ?string $comment = null,
    ): void {
        $new = $this->snapshotData($assessment);
        $documentVersionIds = $assessment->versions()
            ->whereNotNull('document_version_id')
            ->pluck('document_version_id')
            ->all();
        $this->support->event(
            $request,
            $engagement,
            $action,
            $from,
            $to,
            $old,
            $new,
            $comment,
            'COMPLETION_ASSESSMENT',
            $assessment->id,
            $assessment->version_no,
            $assessment->assessment_code,
            null,
            $documentVersionIds,
        );
        $this->support->audit($request, $action, $engagement, $old, $new, [
            'subjectType' => 'COMPLETION_ASSESSMENT',
            'subjectId' => $assessment->id,
            'documentVersionIds' => $documentVersionIds,
        ]);
    }
}
