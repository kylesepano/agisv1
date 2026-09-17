<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\AuditEvidence;
use App\Models\AuditReportVersion;
use App\Models\AemsPlanningObjective;
use App\Models\AemsRiskMatrixItem;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\MasterList;
use App\Models\MasterListItem;
use App\Models\WorkingPaper;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Governs private, checksum-verified and immutable AEMS evidence versions.
 */
class AemsEvidenceService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly DocumentAccessService $documentAccess,
        private readonly RuntimeConfiguration $runtime,
        private readonly AemsNotificationService $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(Request $request, AuditEngagement $engagement): array
    {
        $planningReferences = $this->planningReferences($engagement);
        $records = AuditEvidence::query()
            ->visibleTo($request->user())
            ->where('audit_engagement_id', $engagement->id)
            ->with([
                'category',
                'sourceType',
                'confidentialityLevel',
                'documentVersion.document',
                'workingPapers:id,working_paper_code,title,status',
                'workingPaperVersions.workingPaper:id,working_paper_code,title,status',
                'findings:id,finding_code,title,status',
                'planningObjective:id,objective_code,objective_statement',
                'riskMatrixItem:id,risk_code,risk_statement',
                'reportVersions:id,audit_report_id,version_number,report_stage',
                'supersedes:id,evidence_code,version_number',
            ])
            ->orderBy('evidence_code')
            ->orderByDesc('version_number')
            ->get();

        return [
            'evidence' => $records->map(fn (AuditEvidence $evidence): array => $this->data($evidence))
                ->values(),
            'evidenceCategories' => $this->masterItems('AEMS_EVIDENCE_CATEGORY'),
            'evidenceSourceTypes' => $this->masterItems('AEMS_EVIDENCE_SOURCE_TYPE'),
            'confidentialityLevels' => $this->masterItems('DOCUMENT_CONFIDENTIALITY'),
            'planningObjectives' => $planningReferences['objectives'],
            'riskMatrixItems' => $planningReferences['riskMatrixItems'],
        ];
    }

    /** @return array{objectives: Collection<int, array<string, mixed>>, riskMatrixItems: Collection<int, array<string, mixed>>} */
    private function planningReferences(AuditEngagement $engagement): array
    {
        $package = $engagement->planningPackage;
        if (! $package) {
            return ['objectives' => collect(), 'riskMatrixItems' => collect()];
        }

        $relations = [
            'objectives',
            'riskMatrices.items.auditArea',
            'riskMatrices.items.auditFocus',
        ];
        $version = $package->latestVersion()->with($relations)->first();
        if ($package->approved_version_number) {
            $version = $package->versions()
                ->where('version_number', $package->approved_version_number)
                ->with($relations)
                ->first() ?? $version;
        }

        if (! $version) {
            return ['objectives' => collect(), 'riskMatrixItems' => collect()];
        }

        $objectives = $version->objectives->map(fn (AemsPlanningObjective $objective): array => [
            'id' => $objective->id,
            'code' => $objective->objective_code,
            'statement' => $objective->objective_statement,
            'sourceReference' => $objective->source_reference,
        ])->values();
        $riskMatrixItems = $version->riskMatrices->flatMap(
            fn ($matrix) => $matrix->items->map(fn (AemsRiskMatrixItem $item): array => [
                'id' => $item->id,
                'riskCode' => $item->risk_code,
                'riskStatement' => $item->risk_statement,
                'riskCategory' => $item->risk_category,
                'matrixCode' => $matrix->matrix_code,
                'matrixTitle' => $matrix->title,
                'auditArea' => $item->auditArea?->only(['id', 'code', 'name']),
                'auditFocus' => $item->auditFocus?->only(['id', 'code', 'name']),
            ]),
        )->values();

        return compact('objectives', 'riskMatrixItems');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
        UploadedFile $file,
    ): AuditEvidence {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.evidence.upload',
        );
        $classification = MasterListItem::query()
            ->findOrFail((int) $attributes['confidentialityLevelId']);
        $this->documentAccess->authorizeClassification($request->user(), $classification);
        $workingPapers = $this->workingPapers(
            $request,
            $engagement,
            $attributes['workingPaperIds'] ?? [],
        );
        $this->assertTraceability($engagement, $attributes);
        $stored = $this->storeFile($file, $engagement);

        try {
            $evidence = DB::transaction(function () use (
                $request,
                $engagement,
                $attributes,
                $classification,
                $workingPapers,
                $stored,
            ): AuditEvidence {
                $lockedEngagement = AuditEngagement::query()
                    ->lockForUpdate()
                    ->findOrFail($engagement->id);
                $this->ensureFieldworkAvailable($lockedEngagement);

                $documentType = MasterList::query()
                    ->where('code', 'DOCUMENT_TYPE')
                    ->firstOrFail()
                    ->items()
                    ->where('code', 'OTHER')
                    ->firstOrFail();
                $document = Document::query()->create([
                    'document_type_id' => $documentType->id,
                    'confidentiality_level_id' => $classification->id,
                    'title' => $attributes['title'],
                    'description' => $attributes['sourceDescription'],
                    'owner_module' => 'AEMS',
                    'library_visible' => false,
                    ...$stored,
                    'uploaded_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                    'is_active' => true,
                ]);
                $document->forceFill([
                    'document_code' => $this->runtime->formatNumber(
                        'document_number_format',
                        $document->id,
                    ),
                ])->save();
                $documentVersion = $document->versions()->create([
                    'version_number' => 1,
                    'version_label' => 'Evidence version 1',
                    'change_summary' => 'Initial AEMS evidence upload.',
                    ...$stored,
                    'uploaded_by' => $request->user()->id,
                ]);
                $document->forceFill([
                    'current_version_id' => $documentVersion->id,
                    'version' => $documentVersion->version_label,
                ])->save();
                $document->links()->create([
                    'module_code' => 'AEMS',
                    'record_type' => 'AUDIT_ENGAGEMENT',
                    'record_id' => $lockedEngagement->id,
                    'record_code' => $lockedEngagement->engagement_code,
                    'record_label' => "{$lockedEngagement->engagement_code} — {$lockedEngagement->title}",
                    'linked_by' => $request->user()->id,
                ]);

                $record = AuditEvidence::query()->create([
                    'evidence_family_uuid' => (string) Str::uuid(),
                    'version_number' => 1,
                    'is_current_revision' => true,
                    'audit_engagement_id' => $lockedEngagement->id,
                    'evidence_code' => $this->nextCode($lockedEngagement),
                    ...$this->attributes($attributes),
                    'document_version_id' => $documentVersion->id,
                    'checksum_sha256' => $stored['checksum_sha256'],
                    'status' => 'DRAFT',
                    'outcome' => 'REGISTERED',
                    'assessment_required' => true,
                    'uploaded_by' => $request->user()->id,
                    'lock_version' => 1,
                ]);
                if ($workingPapers->isNotEmpty()) {
                    $record->workingPapers()->syncWithoutDetaching($workingPapers->modelKeys());
                }

                $this->support->event(
                    $request,
                    $lockedEngagement,
                    'EVIDENCE_UPLOADED',
                    null,
                    'DRAFT',
                    null,
                    $this->auditValues($record),
                    null,
                    'AUDIT_EVIDENCE',
                    $record->id,
                    $record->version_number,
                    $record->evidence_code,
                    $record->evidence_family_uuid,
                    [$documentVersion->id],
                );
                $this->support->audit(
                    $request,
                    'aems.evidence.uploaded',
                    $lockedEngagement,
                    null,
                    $this->auditValues($record),
                    ['evidenceId' => $record->id, 'documentVersionId' => $documentVersion->id],
                );

                return $record;
            }, 3);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($stored['storage_path']);
            throw $error;
        }

        return $this->load($evidence);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function replace(
        Request $request,
        AuditEngagement $engagement,
        AuditEvidence $evidence,
        array $attributes,
        UploadedFile $file,
    ): AuditEvidence {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.evidence.upload',
        );
        $classification = MasterListItem::query()
            ->findOrFail((int) $attributes['confidentialityLevelId']);
        $this->documentAccess->authorizeClassification($request->user(), $classification);
        $workingPapers = $this->workingPapers(
            $request,
            $engagement,
            $attributes['workingPaperIds'] ?? [],
        );
        $this->assertTraceability($engagement, $attributes);
        $stored = $this->storeFile($file, $engagement);

        try {
            $revision = DB::transaction(function () use (
                $request,
                $engagement,
                $evidence,
                $attributes,
                $classification,
                $workingPapers,
                $stored,
            ): AuditEvidence {
                $locked = $this->lockEvidence(
                    $engagement,
                    $evidence,
                    (int) $attributes['lockVersion'],
                );
                if (! $locked->is_current_revision || $locked->status === 'VOIDED') {
                    throw ValidationException::withMessages([
                        'evidence' => ['Only the current non-voided evidence version can be replaced.'],
                    ]);
                }
                if (AuditEvidence::query()
                    ->where('evidence_family_uuid', $locked->evidence_family_uuid)
                    ->where('checksum_sha256', $stored['checksum_sha256'])
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'file' => ['This exact file already exists in the evidence version history.'],
                    ]);
                }

                $locked->loadMissing('documentVersion.document');
                $document = Document::query()
                    ->lockForUpdate()
                    ->findOrFail($locked->documentVersion->document_id);
                $documentNumber = ((int) $document->versions()->max('version_number')) + 1;
                $nextEvidenceVersion = $locked->version_number + 1;
                $documentVersion = $document->versions()->create([
                    'version_number' => $documentNumber,
                    'version_label' => "Evidence version {$nextEvidenceVersion}",
                    'change_summary' => $attributes['changeReason'],
                    ...$stored,
                    'uploaded_by' => $request->user()->id,
                ]);
                $document->forceFill([
                    'current_version_id' => $documentVersion->id,
                    'version' => $documentVersion->version_label,
                    'confidentiality_level_id' => $this->moreRestrictiveClassification(
                        $document->confidentiality_level_id,
                        $classification,
                    ),
                    'updated_by' => $request->user()->id,
                ])->save();

                // Use a literal boolean in the version switch so PostgreSQL and
                // SQLite both remove the superseded row from the partial unique
                // index before the replacement is inserted.
                DB::statement(
                    'UPDATE audit_evidence
                     SET is_current_revision = FALSE,
                         outcome = CASE WHEN outcome IN (\'VOIDED\', \'DUPLICATE\') THEN outcome ELSE \'SUPERSEDED\' END,
                         lock_version = lock_version + 1
                     WHERE id = ?',
                    [$locked->id],
                );
                $locked->refresh();
                if ($locked->is_current_revision) {
                    throw new \LogicException('The superseded evidence version could not be retired.');
                }
                $record = AuditEvidence::query()->create([
                    'evidence_family_uuid' => $locked->evidence_family_uuid,
                    'version_number' => $nextEvidenceVersion,
                    'supersedes_evidence_id' => $locked->id,
                    // Promote only after the row exists. This keeps the
                    // current-version switch atomic while avoiding partial
                    // unique-index ambiguity across supported databases.
                    'is_current_revision' => false,
                    'audit_engagement_id' => $locked->audit_engagement_id,
                    'evidence_code' => $locked->evidence_code,
                    ...$this->attributes($attributes),
                    'document_version_id' => $documentVersion->id,
                    'checksum_sha256' => $stored['checksum_sha256'],
                    'status' => 'DRAFT',
                    'outcome' => 'REGISTERED',
                    'assessment_required' => true,
                    'uploaded_by' => $request->user()->id,
                    'lock_version' => 1,
                ]);
                DB::statement(
                    'UPDATE audit_evidence SET is_current_revision = TRUE WHERE id = ?',
                    [$record->id],
                );
                $record->refresh();
                if (! $record->is_current_revision) {
                    throw new \LogicException('The replacement evidence version could not be promoted.');
                }
                $linkedPaperIds = $workingPapers->isNotEmpty()
                    ? $workingPapers->modelKeys()
                    : $locked->workingPapers()->pluck('working_papers.id')->all();
                if ($linkedPaperIds !== []) {
                    $record->workingPapers()->syncWithoutDetaching($linkedPaperIds);
                }

                $this->support->event(
                    $request,
                    $engagement,
                    'EVIDENCE_REPLACED',
                    $locked->status,
                    'DRAFT',
                    $this->auditValues($locked),
                    $this->auditValues($record),
                    $attributes['changeReason'],
                    'AUDIT_EVIDENCE',
                    $record->id,
                    $record->version_number,
                    $record->evidence_code,
                    $record->evidence_family_uuid,
                    [$documentVersion->id],
                );
                $this->support->audit(
                    $request,
                    'aems.evidence.replaced',
                    $engagement,
                    $this->auditValues($locked),
                    $this->auditValues($record),
                    [
                        'evidenceId' => $record->id,
                        'supersedesEvidenceId' => $locked->id,
                        'documentVersionId' => $documentVersion->id,
                    ],
                );

                return $record;
            }, 3);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($stored['storage_path']);
            throw $error;
        }

        return $this->load($revision);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AuditEvidence $evidence,
        string $action,
        int $lockVersion,
        ?string $reason,
    ): AuditEvidence {
        $permission = match ($action) {
            'VERIFY' => 'aems.evidence.verify',
            'ACCEPT', 'LIMIT', 'ADDITIONAL_REQUIRED', 'REJECT', 'DUPLICATE' => 'aems.evidence.outcome',
            'VOID' => 'aems.evidence.void',
            default => throw ValidationException::withMessages([
                'action' => ['Unsupported evidence action.'],
            ]),
        };
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            $permission,
        );

        $record = DB::transaction(function () use (
            $request,
            $engagement,
            $evidence,
            $action,
            $lockVersion,
            $reason,
        ): AuditEvidence {
            $locked = $this->lockEvidence($engagement, $evidence, $lockVersion);
            if (! $locked->is_current_revision) {
                throw ValidationException::withMessages([
                    'evidence' => ['Only the current evidence version can transition.'],
                ]);
            }
            $from = $locked->status;
            if ($action === 'VERIFY') {
                if ($from !== 'DRAFT') {
                    throw ValidationException::withMessages([
                        'action' => ['Only draft evidence can be verified.'],
                    ]);
                }
                $locked->loadMissing('documentVersion');
                if ($locked->checksum_sha256 !== $locked->documentVersion->checksum_sha256) {
                    throw ValidationException::withMessages([
                        'file' => ['The stored file checksum does not match the evidence record.'],
                    ]);
                }
                $locked->update([
                    'status' => 'VERIFIED',
                    'outcome' => 'FOR_ASSESSMENT',
                    'verified_by' => $request->user()->id,
                    'verified_at' => now(),
                    'lock_version' => $locked->lock_version + 1,
                ]);
                $to = 'VERIFIED';
            } elseif ($action !== 'VOID') {
                if (! in_array($from, ['VERIFIED', 'LOCKED'], true)) {
                    throw ValidationException::withMessages(['action' => ['Only verified or locked evidence can receive a professional outcome.']]);
                }
                if ($action === 'ACCEPT') {
                    $this->assertAcceptableOutcome($locked);
                }
                if ($action !== 'ACCEPT' && mb_strlen(trim((string) $reason)) < 5) {
                    throw ValidationException::withMessages(['reason' => ['A reason is required for a non-accepted evidence outcome.']]);
                }
                $outcome = match ($action) {
                    'ACCEPT' => 'ACCEPTED',
                    'LIMIT' => 'LIMITED',
                    'ADDITIONAL_REQUIRED' => 'ADDITIONAL_REQUIRED',
                    'REJECT' => 'REJECTED',
                    'DUPLICATE' => 'DUPLICATE',
                };
                $locked->update(['outcome' => $outcome, 'lock_version' => $locked->lock_version + 1]);
                $to = $from;
            } else {
                if (! in_array($from, ['DRAFT', 'VERIFIED'], true)) {
                    throw ValidationException::withMessages([
                        'action' => ['Locked or already voided evidence cannot be voided.'],
                    ]);
                }
                if (mb_strlen(trim((string) $reason)) < 5) {
                    throw ValidationException::withMessages([
                        'reason' => ['A clear evidence void reason is required.'],
                    ]);
                }
                $locked->update([
                    'status' => 'VOIDED',
                    'outcome' => 'VOIDED',
                    'voided_by' => $request->user()->id,
                    'voided_at' => now(),
                    'void_reason' => $reason,
                    'lock_version' => $locked->lock_version + 1,
                ]);
                $to = 'VOIDED';
            }

            $this->support->event(
                $request,
                $engagement,
                "EVIDENCE_{$action}",
                $from,
                $to,
                ['status' => $from],
                ['status' => $to],
                $reason,
                'AUDIT_EVIDENCE',
                $locked->id,
                $locked->version_number,
                $locked->evidence_code,
                $locked->evidence_family_uuid,
                [$locked->document_version_id],
            );
            $this->support->audit(
                $request,
                'aems.evidence.'.str($action)->lower(),
                $engagement,
                ['status' => $from],
                ['status' => $to],
                ['evidenceId' => $locked->id, 'reason' => $reason],
            );
            if ($action !== 'VERIFY') {
                $this->notifications->evidenceOutcomeRecorded($request, $engagement, $locked, $locked->outcome);
            }

            return $locked;
        });

        return $this->load($record);
    }

    public function downloadVersion(
        Request $request,
        AuditEngagement $engagement,
        AuditEvidence $evidence,
    ): DocumentVersion {
        $this->ensureEvidence($engagement, $evidence);
        $version = $evidence->documentVersion()->firstOrFail();
        abort_unless(
            Storage::disk('local')->exists($version->storage_path),
            404,
            'Stored evidence file not found.',
        );
        $this->support->audit(
            $request,
            'aems.evidence.downloaded',
            $engagement,
            null,
            ['evidenceId' => $evidence->id, 'documentVersionId' => $version->id],
            ['evidenceCode' => $evidence->evidence_code],
        );

        return $version;
    }

    public function linkReport(Request $request, AuditEngagement $engagement, AuditEvidence $evidence, int $reportVersionId, ?string $reason, int $sequence = 0): AuditEvidence
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.evidence.link_report', $evidence->uploaded_by);
        $record = DB::transaction(function () use ($request, $engagement, $evidence, $reportVersionId, $reason, $sequence): AuditEvidence {
            $locked = $this->lockEvidence($engagement, $evidence, (int) $evidence->lock_version);
            if (! $locked->is_current_revision || ! in_array($locked->outcome, ['ACCEPTED', 'LIMITED'], true)) {
                throw ValidationException::withMessages(['evidence' => ['Only current evidence with an explicit accepted or authorized limited outcome can be linked to a report.']]);
            }
            $report = AuditReportVersion::query()->whereKey($reportVersionId)->whereHas('report', fn ($query) => $query->where('audit_engagement_id', $engagement->id))->first();
            if (! $report) throw ValidationException::withMessages(['reportVersionId' => ['The report version does not belong to this engagement.']]);
            if ($report->is_locked) throw ValidationException::withMessages(['reportVersionId' => ['Issued or locked report versions cannot have links changed.']]);
            $locked->reportVersions()->syncWithoutDetaching([$report->id => ['sequence_number' => $sequence, 'link_reason' => $reason, 'linked_by' => $request->user()->id]]);
            $this->support->audit($request, 'aems.evidence.report_linked', $engagement, null, ['evidenceId' => $locked->id, 'reportVersionId' => $report->id], ['reason' => $reason]);
            return $locked;
        });
        return $this->load($record);
    }

    /** @return array<string, mixed> */
    public function data(AuditEvidence $evidence): array
    {
        $evidence = $this->load($evidence);
        $file = $evidence->documentVersion;

        return [
            'id' => $evidence->id,
            'familyUuid' => $evidence->evidence_family_uuid,
            'versionNumber' => $evidence->version_number,
            'supersedesEvidenceId' => $evidence->supersedes_evidence_id,
            'isCurrentRevision' => $evidence->is_current_revision,
            'evidenceCode' => $evidence->evidence_code,
            'title' => $evidence->title,
            'status' => $evidence->status,
            'outcome' => $evidence->outcome,
            'evidenceCategoryId' => $evidence->evidence_category_id,
            'evidenceCategory' => $evidence->category?->only(['id', 'code', 'label']),
            'evidenceSourceTypeId' => $evidence->evidence_source_type_id,
            'evidenceSourceType' => $evidence->sourceType?->only(['id', 'code', 'label']),
            'sourceDescription' => $evidence->source_description,
            'dateObtained' => $evidence->date_obtained?->toDateString(),
            'custodianName' => $evidence->custodian_name,
            'custodianOfficeId' => $evidence->custodian_office_id,
            'acquisitionMethod' => $evidence->acquisition_method,
            'acquisitionForm' => $evidence->acquisition_form,
            'planningObjectiveId' => $evidence->planning_objective_id,
            'planningObjective' => $evidence->planningObjective?->only(['id', 'objective_code', 'objective_statement']),
            'riskMatrixItemId' => $evidence->risk_matrix_item_id,
            'riskMatrixItem' => $evidence->riskMatrixItem?->only(['id', 'risk_code', 'risk_statement']),
            'controlReference' => $evidence->control_reference,
            'confidentialityLevelId' => $evidence->confidentiality_level_id,
            'confidentialityLevel' => $evidence->confidentialityLevel?->only(['id', 'code', 'label']),
            'documentVersionId' => $evidence->document_version_id,
            'checksumSha256' => $evidence->checksum_sha256,
            'fileName' => $file?->original_file_name,
            'fileSize' => $file?->file_size,
            'mimeType' => $file?->mime_type,
            'uploadedBy' => $evidence->uploader?->only(['id', 'employee_id', 'name', 'initials']),
            'verifiedBy' => $evidence->verifier?->only(['id', 'employee_id', 'name', 'initials']),
            'verifiedAt' => $evidence->verified_at?->toIso8601String(),
            'lockedAt' => $evidence->locked_at?->toIso8601String(),
            'voidedAt' => $evidence->voided_at?->toIso8601String(),
            'voidReason' => $evidence->void_reason,
            'lockVersion' => $evidence->lock_version,
            'workingPapers' => $evidence->workingPapers->map(fn (WorkingPaper $paper): array => [
                'id' => $paper->id,
                'workingPaperCode' => $paper->working_paper_code,
                'title' => $paper->title,
                'status' => $paper->status,
            ])->values(),
            'findings' => $evidence->findings->map(fn ($finding): array => [
                'id' => $finding->id,
                'findingCode' => $finding->finding_code,
                'title' => $finding->title,
                'status' => $finding->status,
            ])->values(),
            'reportVersions' => $evidence->reportVersions->map(fn ($report): array => ['id' => $report->id, 'reportId' => $report->audit_report_id, 'versionNumber' => $report->version_number, 'stage' => $report->report_stage])->values(),
            'createdAt' => $evidence->created_at?->toIso8601String(),
        ];
    }

    private function load(AuditEvidence $evidence): AuditEvidence
    {
        return $evidence->fresh([
            'category',
            'sourceType',
            'confidentialityLevel',
            'documentVersion.document',
            'uploader:id,employee_id,name,initials',
            'verifier:id,employee_id,name,initials',
            'workingPapers:id,working_paper_code,title,status',
            'findings:id,finding_code,title,status',
            'planningObjective:id,objective_code,objective_statement',
            'riskMatrixItem:id,risk_code,risk_statement',
            'reportVersions:id,audit_report_id,version_number,report_stage',
            'supersedes:id,evidence_code,version_number',
        ]);
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function attributes(array $attributes): array
    {
        return [
            'title' => $attributes['title'],
            'evidence_category_id' => $attributes['evidenceCategoryId'],
            'evidence_source_type_id' => $attributes['evidenceSourceTypeId'],
            'source_description' => $attributes['sourceDescription'],
            'date_obtained' => $attributes['dateObtained'],
            'custodian_name' => $attributes['custodianName'] ?? null,
            'custodian_office_id' => $attributes['custodianOfficeId'] ?? null,
            'confidentiality_level_id' => $attributes['confidentialityLevelId'],
            'acquisition_method' => $attributes['acquisitionMethod'] ?? null,
            'acquisition_form' => $attributes['acquisitionForm'] ?? null,
            'planning_objective_id' => $attributes['planningObjectiveId'] ?? null,
            'risk_matrix_item_id' => $attributes['riskMatrixItemId'] ?? null,
            'control_reference' => $attributes['controlReference'] ?? null,
        ];
    }

    /** @param list<int|string> $ids
     * @return Collection<int, WorkingPaper>
     */
    private function workingPapers(
        Request $request,
        AuditEngagement $engagement,
        array $ids,
    ): Collection {
        $ids = collect($ids)->map(fn ($id): int => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }
        $papers = WorkingPaper::query()
            ->visibleTo($request->user())
            ->where('audit_engagement_id', $engagement->id)
            ->whereIn('id', $ids)
            ->get();
        if ($papers->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'workingPaperIds' => ['Every linked working paper must belong to this engagement and be visible to you.'],
            ]);
        }

        return $papers;
    }

    private function assertTraceability(AuditEngagement $engagement, array $attributes): void
    {
        if (! empty($attributes['planningObjectiveId']) && ! AemsPlanningObjective::query()
            ->whereKey((int) $attributes['planningObjectiveId'])
            ->whereHas('version.package', fn ($package) => $package->where('audit_engagement_id', $engagement->id))
            ->exists()) {
            throw ValidationException::withMessages(['planningObjectiveId' => ['The planning objective must belong to this engagement planning package.']]);
        }
        if (! empty($attributes['riskMatrixItemId']) && ! AemsRiskMatrixItem::query()
            ->whereKey((int) $attributes['riskMatrixItemId'])
            ->whereHas('matrix.version.package', fn ($package) => $package->where('audit_engagement_id', $engagement->id))
            ->exists()) {
            throw ValidationException::withMessages(['riskMatrixItemId' => ['The risk-matrix item must belong to this engagement planning package.']]);
        }
    }

    private function ensureFieldworkAvailable(AuditEngagement $engagement): void
    {
        $available = $engagement->programs()
            ->where('is_current_revision', true)
            ->where('status', 'ACTIVE')
            ->exists();
        if (! $available) {
            throw ValidationException::withMessages([
                'engagement' => ['Evidence can be uploaded only after the approved Audit Program is active.'],
            ]);
        }
    }

    private function assertAcceptableOutcome(AuditEvidence $evidence): void
    {
        $assessment = $evidence->currentAssessment()->first();
        if (! $assessment || $assessment->status !== 'ASSESSED') {
            throw ValidationException::withMessages(['outcome' => ['Evidence must have a current professional assessment before it can be accepted.']]);
        }
        foreach (['sufficiency', 'appropriateness', 'relevance', 'reliability', 'competence', 'accuracy', 'completeness', 'corroboration', 'authenticity', 'integrity'] as $dimension) {
            if (! in_array(strtoupper((string) $assessment->{$dimension}), ['YES', 'HIGH', 'ADEQUATE'], true)) {
                throw ValidationException::withMessages(['outcome' => ['Evidence with negative or incomplete assessment dimensions cannot be accepted.']]);
            }
        }
        if (! in_array(strtoupper((string) $assessment->contradiction), ['NO', 'ADEQUATE'], true)
            || blank($assessment->confidentiality)
            || filled($assessment->evidence_gaps)
            || filled($assessment->limitations)
            || $assessment->is_restricted
            || filled($assessment->access_restrictions)
            || $assessment->exception_required) {
            throw ValidationException::withMessages(['outcome' => ['Evidence gaps, restrictions, limitations, or exceptions must be resolved before acceptance.']]);
        }
    }

    private function lockEvidence(
        AuditEngagement $engagement,
        AuditEvidence $evidence,
        int $lockVersion,
    ): AuditEvidence {
        $locked = AuditEvidence::query()->lockForUpdate()->findOrFail($evidence->id);
        $this->ensureEvidence($engagement, $locked);
        if ($locked->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['This evidence changed in another session. Refresh before continuing.'],
            ]);
        }

        return $locked;
    }

    private function ensureEvidence(
        AuditEngagement $engagement,
        AuditEvidence $evidence,
    ): void {
        if ((int) $evidence->audit_engagement_id !== (int) $engagement->id
            || $evidence->trashed()) {
            throw ValidationException::withMessages([
                'evidence' => ['The evidence record does not belong to this engagement.'],
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function storeFile(UploadedFile $file, AuditEngagement $engagement): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid().($extension ? ".{$extension}" : '');
        $path = Storage::disk('local')->putFileAs(
            "aems/engagements/{$engagement->id}/evidence",
            $file,
            $storedName,
        );
        if (! $path) {
            throw ValidationException::withMessages([
                'file' => ['The evidence file could not be stored. Please try again.'],
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

    private function nextCode(AuditEngagement $engagement): string
    {
        $sequence = AuditEvidence::query()
            ->withTrashed()
            ->where('audit_engagement_id', $engagement->id)
            ->distinct('evidence_family_uuid')
            ->count('evidence_family_uuid') + 1;
        do {
            $code = sprintf('EVD-%s-%03d', $engagement->engagement_code, $sequence++);
        } while (AuditEvidence::query()
            ->withTrashed()
            ->where('audit_engagement_id', $engagement->id)
            ->where('evidence_code', $code)
            ->exists());

        return $code;
    }

    private function moreRestrictiveClassification(
        ?int $currentId,
        MasterListItem $requested,
    ): int {
        if (! $currentId) {
            return $requested->id;
        }
        $current = MasterListItem::query()->find($currentId);
        $ranks = ['PUBLIC' => 1, 'INTERNAL' => 2, 'CONFIDENTIAL' => 3, 'RESTRICTED' => 4];

        return ($ranks[$current?->code] ?? 2) >= ($ranks[$requested->code] ?? 2)
            ? $currentId
            : $requested->id;
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
    private function auditValues(AuditEvidence $evidence): array
    {
        return [
            'id' => $evidence->id,
            'evidenceCode' => $evidence->evidence_code,
            'familyUuid' => $evidence->evidence_family_uuid,
            'versionNumber' => $evidence->version_number,
            'status' => $evidence->status,
            'checksumSha256' => $evidence->checksum_sha256,
            'documentVersionId' => $evidence->document_version_id,
        ];
    }
}
