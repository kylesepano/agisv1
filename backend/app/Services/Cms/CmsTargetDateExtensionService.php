<?php

namespace App\Services\Cms;

use App\Models\CmsActionPlanVersion;
use App\Models\CmsProgressUpdate;
use App\Models\CmsProgressUpdateVersion;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\CmsRecommendationTargetDateHistory;
use App\Models\CmsTargetDateExtensionEvidenceLink;
use App\Models\CmsTargetDateExtensionRequest;
use App\Models\CmsTargetDateExtensionVersion;
use App\Models\Document;
use App\Models\MasterList;
use App\Models\MasterListItem;
use App\Models\User;
use App\Services\DocumentAccessService;
use App\Services\NotificationService;
use App\Services\RuntimeConfiguration;
use App\Support\ActivityRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Owns CMS-6A target-date extension workflow and immutable date history. */
class CmsTargetDateExtensionService
{
    private const CLASSIFICATION_RANK = [
        'PUBLIC' => 1,
        'INTERNAL' => 2,
        'CONFIDENTIAL' => 3,
        'RESTRICTED' => 4,
    ];

    public function __construct(
        private readonly CmsRecommendationScopeService $scope,
        private readonly NotificationService $notifications,
        private readonly DocumentAccessService $documentAccess,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    /** @return array{case: CmsRecommendationCase, extensions: mixed, permittedActions: list<string>} */
    public function forRecommendation(User $actor, int $caseId): array
    {
        $case = $this->visibleCase($actor, $caseId);
        $extensions = CmsTargetDateExtensionRequest::query()
            ->where('cms_recommendation_case_id', $case->id)
            ->with($this->requestRelations())
            ->orderByDesc('request_sequence')
            ->get();

        return [
            'case' => $case,
            'extensions' => $extensions,
            'permittedActions' => $this->permittedFamilyActions($actor, $case, $extensions->first()),
        ];
    }

    /** @return array{case: CmsRecommendationCase, extension: CmsTargetDateExtensionRequest} */
    public function show(User $actor, int $extensionId): array
    {
        $reference = CmsTargetDateExtensionRequest::query()->find($extensionId);
        throw_unless($reference, new HttpException(404, 'The target-date extension is unavailable.'));
        $case = $this->visibleCase($actor, $reference->cms_recommendation_case_id);
        $extension = CmsTargetDateExtensionRequest::query()
            ->whereKey($reference->id)
            ->where('cms_recommendation_case_id', $case->id)
            ->with($this->requestRelations())
            ->firstOrFail();

        return ['case' => $case, 'extension' => $extension];
    }

    /** @return array<string, mixed> */
    public function options(User $actor, int $caseId): array
    {
        $case = $this->visibleCase($actor, $caseId);
        $case->load([
            'recommendation',
            'leadResponsibleOffice',
            'currentAssignment.user',
            'actionPlan.acceptedVersion.milestones',
            'activeValidationReview',
        ]);
        $active = CmsTargetDateExtensionRequest::query()
            ->where('cms_recommendation_case_id', $case->id)
            ->whereNull('resolved_at')
            ->with('currentVersion')
            ->first();
        $latestProgress = $this->latestRecordedProgress($case);
        $accepted = $case->actionPlan?->acceptedVersion;
        $reasons = $this->eligibilityReasons($actor, $case, $active, $accepted);
        $canCreate = $reasons === [] && $actor->hasPermission('cms.extension.create');

        return [
            'case' => $case,
            'creationAllowed' => $canCreate,
            'originalTargetDate' => $case->recommendation?->original_target_implementation_date?->toDateString(),
            'effectiveTargetDate' => $case->effective_target_implementation_date?->toDateString(),
            'caseStatus' => $case->status_code,
            'caseLockVersion' => $case->lock_version,
            'acceptedActionPlanVersion' => $accepted ? [
                'id' => $accepted->id,
                'versionNumber' => $accepted->version_number,
                'status' => $accepted->status_code,
                'milestoneCount' => $accepted->milestones?->count() ?? 0,
            ] : null,
            'latestRecordedProgressUpdate' => $latestProgress ? [
                'id' => $latestProgress->id,
                'recordedVersionId' => $latestProgress->recorded_version_id,
                'versionNumber' => $latestProgress->recordedVersion?->version_number,
                'reportingPeriodEnd' => $latestProgress->reporting_period_end?->toDateString(),
                'managementReportedPercentage' => $latestProgress->recordedVersion?->management_reported_overall_percentage,
            ] : null,
            'currentComplianceMonitor' => $case->currentAssignment?->user
                ? $this->safeUser($case->currentAssignment->user)
                : null,
            'unresolvedRequest' => $active ? [
                'id' => $active->id,
                'displayCode' => $active->display_code,
                'versionId' => $active->current_version_id,
                'status' => $active->currentVersion?->status_code,
            ] : null,
            'earliestAllowedRequestedDate' => $case->effective_target_implementation_date
                ? $case->effective_target_implementation_date->addDay()->toDateString()
                : null,
            'maximumExtensionDate' => null,
            'maximumExtensionDays' => null,
            'retroactiveAllowed' => false,
            'permittedActions' => $canCreate ? ['create'] : [],
            'unavailableReasons' => $reasons,
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function create(Request $request, int $caseId, array $attributes): CmsTargetDateExtensionRequest
    {
        $actor = $request->user();
        $extension = DB::transaction(function () use ($request, $actor, $caseId, $attributes): CmsTargetDateExtensionRequest {
            $case = $this->visibleCase($actor, $caseId, true);
            $this->authorizeResponsible($actor, $case, 'cms.extension.create');
            $this->assertCaseEligible($case);
            $this->assertCaseLock($case, (int) $attributes['lockVersion']);
            $this->assertNoUnresolved($case);
            $accepted = $this->acceptedPlan($case);
            $progress = $this->latestRecordedProgress($case);
            $sequence = (int) CmsTargetDateExtensionRequest::query()
                ->where('cms_recommendation_case_id', $case->id)
                ->lockForUpdate()
                ->max('request_sequence') + 1;
            $extension = CmsTargetDateExtensionRequest::query()->create([
                'cms_recommendation_case_id' => $case->id,
                'request_sequence' => $sequence,
                'baseline_effective_target_date' => $case->effective_target_implementation_date,
                'created_by' => $actor->id,
                'lock_version' => 1,
            ]);
            $version = $extension->versions()->create([
                'version_number' => 1,
                'status_code' => CmsTargetDateExtensionVersion::STATUS_DRAFT,
                'active_slot' => 'ACTIVE',
                'accepted_action_plan_version_id' => $accepted->id,
                'recorded_progress_update_version_id' => $progress?->recorded_version_id,
                'case_lock_version' => $case->lock_version,
                'requested_target_date' => $attributes['requestedTargetDate'],
                ...$this->narrativeAttributes($attributes),
                'prepared_by' => $actor->id,
                'lock_version' => 1,
            ]);
            $extension->forceFill(['current_version_id' => $version->id])->save();
            $this->assertRequestedDate($extension, $version);
            $this->record($request, $case, $extension, $version, 'EXTENSION_REQUEST_CREATED', 'cms.extension.created', null, $version->status_code);

            return $extension;
        }, 3);

        return $extension->fresh($this->requestRelations());
    }

    /** @param array<string, mixed> $attributes */
    public function update(Request $request, int $extensionId, int $versionId, array $attributes): CmsTargetDateExtensionRequest
    {
        $actor = $request->user();
        $extension = DB::transaction(function () use ($request, $actor, $extensionId, $versionId, $attributes): CmsTargetDateExtensionRequest {
            [$case, $family, $version] = $this->resolveVersion($actor, $extensionId, $versionId, true);
            $this->authorizeResponsible($actor, $case, 'cms.extension.update');
            $this->assertVersionLock($version, (int) $attributes['lockVersion']);
            $this->assertRequestLock($family, (int) $attributes['lockVersion']);
            $this->assertDraft($family, $version);
            $version->fill([
                'requested_target_date' => $attributes['requestedTargetDate'],
                ...$this->narrativeAttributes($attributes),
            ]);
            $this->assertRequestedDate($family, $version);
            $version->forceFill(['lock_version' => $version->lock_version + 1])->save();
            $family->forceFill(['lock_version' => $family->lock_version + 1])->save();
            $this->record($request, $case, $family, $version, 'EXTENSION_REQUEST_UPDATED', 'cms.extension.updated', CmsTargetDateExtensionVersion::STATUS_DRAFT, CmsTargetDateExtensionVersion::STATUS_DRAFT);

            return $family;
        }, 3);

        return $extension->fresh($this->requestRelations());
    }

    public function submit(Request $request, int $extensionId, int $versionId, int $lockVersion): CmsTargetDateExtensionRequest
    {
        $actor = $request->user();
        $extension = DB::transaction(function () use ($request, $actor, $extensionId, $versionId, $lockVersion): CmsTargetDateExtensionRequest {
            [$case, $family, $version] = $this->resolveVersion($actor, $extensionId, $versionId, true);
            $this->authorizeResponsible($actor, $case, 'cms.extension.submit');
            $this->assertVersionLock($version, $lockVersion);
            $this->assertRequestLock($family, $lockVersion);
            $this->assertDraft($family, $version);
            $this->assertCaseEligible($case);
            $this->assertCaseSource($case, $version);
            $this->assertRequestedDate($family, $version, true);
            $this->assertComplete($version);
            $this->assertSubmittedOnTime($case);
            $version->load('activeEvidenceLinks.documentVersion');
            $version->forceFill([
                'status_code' => CmsTargetDateExtensionVersion::STATUS_SUBMITTED,
                'active_slot' => 'ACTIVE',
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'submission_snapshot' => $this->submissionSnapshot($case, $family, $version),
                'lock_version' => $version->lock_version + 1,
            ])->save();
            $family->forceFill([
                'current_version_id' => $version->id,
                'lock_version' => $family->lock_version + 1,
            ])->save();
            $this->record($request, $case, $family, $version, 'EXTENSION_REQUEST_SUBMITTED', 'cms.extension.submitted', CmsTargetDateExtensionVersion::STATUS_DRAFT, $version->status_code);
            $this->notifyAfterCommit('submitted', $request, $case, $family, $version);

            return $family;
        }, 3);

        return $extension->fresh($this->requestRelations());
    }

    public function startReview(Request $request, int $extensionId, int $versionId, int $lockVersion, ?string $comment = null): CmsTargetDateExtensionRequest
    {
        $actor = $request->user();
        $extension = DB::transaction(function () use ($request, $actor, $extensionId, $versionId, $lockVersion, $comment): CmsTargetDateExtensionRequest {
            [$case, $family, $version] = $this->resolveVersion($actor, $extensionId, $versionId, true);
            $this->assertVersionLock($version, $lockVersion);
            $this->assertStatus($version, CmsTargetDateExtensionVersion::STATUS_SUBMITTED);
            $this->authorizeReviewer($actor, $case, $version, 'cms.extension.review');
            $this->assertSnapshotIntegrity($version);
            $version->forceFill([
                'status_code' => CmsTargetDateExtensionVersion::STATUS_UNDER_REVIEW,
                'review_started_by' => $actor->id,
                'review_started_at' => now(),
                'lock_version' => $version->lock_version + 1,
            ])->save();
            $family->forceFill(['lock_version' => $family->lock_version + 1])->save();
            $this->record($request, $case, $family, $version, 'EXTENSION_REVIEW_STARTED', 'cms.extension.review_started', CmsTargetDateExtensionVersion::STATUS_SUBMITTED, $version->status_code, ['reviewComment' => $comment]);
            $this->notifyAfterCommit('review_started', $request, $case, $family, $version);

            return $family;
        }, 3);

        return $extension->fresh($this->requestRelations());
    }

    public function returnVersion(Request $request, int $extensionId, int $versionId, int $lockVersion, string $reason): CmsTargetDateExtensionRequest
    {
        $actor = $request->user();
        $extension = DB::transaction(function () use ($request, $actor, $extensionId, $versionId, $lockVersion, $reason): CmsTargetDateExtensionRequest {
            [$case, $family, $version] = $this->resolveVersion($actor, $extensionId, $versionId, true);
            $this->assertVersionLock($version, $lockVersion);
            $this->assertStatus($version, [CmsTargetDateExtensionVersion::STATUS_UNDER_REVIEW, CmsTargetDateExtensionVersion::STATUS_FOR_APPROVAL]);
            if ($version->status_code === CmsTargetDateExtensionVersion::STATUS_UNDER_REVIEW) {
                $this->authorizeReviewer($actor, $case, $version, 'cms.extension.return');
            } else {
                $this->authorizeDecision($actor, $case, $version, 'cms.extension.approve');
            }
            $from = $version->status_code;
            $version->forceFill([
                'status_code' => CmsTargetDateExtensionVersion::STATUS_RETURNED,
                'active_slot' => null,
                'returned_by' => $actor->id,
                'returned_at' => now(),
                'return_reason' => $reason,
                'lock_version' => $version->lock_version + 1,
            ])->save();
            $family->forceFill(['lock_version' => $family->lock_version + 1])->save();
            $this->record($request, $case, $family, $version, 'EXTENSION_REQUEST_RETURNED', 'cms.extension.returned', $from, $version->status_code, ['returnReason' => $reason]);
            $this->notifyAfterCommit('returned', $request, $case, $family, $version);

            return $family;
        }, 3);

        return $extension->fresh($this->requestRelations());
    }

    /** @param array<string, mixed> $attributes */
    public function recommend(Request $request, int $extensionId, int $versionId, array $attributes): CmsTargetDateExtensionRequest
    {
        $actor = $request->user();
        $extension = DB::transaction(function () use ($request, $actor, $extensionId, $versionId, $attributes): CmsTargetDateExtensionRequest {
            [$case, $family, $version] = $this->resolveVersion($actor, $extensionId, $versionId, true);
            $this->assertVersionLock($version, (int) $attributes['lockVersion']);
            $this->assertStatus($version, CmsTargetDateExtensionVersion::STATUS_UNDER_REVIEW);
            $this->authorizeReviewer($actor, $case, $version, 'cms.extension.recommend');
            $this->assertSnapshotIntegrity($version);
            if ($version->assessment()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['assessment' => ['This extension version already has an assessment.']]);
            }
            $version->load('activeEvidenceLinks');
            $version->assessment()->create([
                'assessor_user_id' => $actor->id,
                'recommendation_code' => $attributes['recommendationCode'],
                'assessment_summary' => $attributes['assessmentSummary'],
                'evidence_review_summary' => $attributes['evidenceReviewSummary'],
                'feasibility_assessment' => $attributes['feasibilityAssessment'],
                'risk_of_delay_summary' => $attributes['riskOfDelaySummary'],
                'conditions_or_observations' => $attributes['conditionsOrObservations'] ?? null,
                'assessed_at' => now(),
            ]);
            $version->forceFill([
                'status_code' => CmsTargetDateExtensionVersion::STATUS_FOR_APPROVAL,
                'active_slot' => 'ACTIVE',
                'lock_version' => $version->lock_version + 1,
            ])->save();
            $family->forceFill(['lock_version' => $family->lock_version + 1])->save();
            $this->record($request, $case, $family, $version, 'EXTENSION_ASSESSMENT_COMPLETED', 'cms.extension.assessed', CmsTargetDateExtensionVersion::STATUS_UNDER_REVIEW, $version->status_code, ['recommendationCode' => $attributes['recommendationCode'], 'assessorId' => $actor->id]);
            $this->notifyAfterCommit('assessed', $request, $case, $family, $version);

            return $family;
        }, 3);

        return $extension->fresh($this->requestRelations());
    }

    public function approve(Request $request, int $extensionId, int $versionId, int $lockVersion, string $comment, ?string $overrideReason = null): CmsTargetDateExtensionRequest
    {
        return $this->decide($request, $extensionId, $versionId, $lockVersion, 'APPROVED', $comment, $overrideReason);
    }

    public function reject(Request $request, int $extensionId, int $versionId, int $lockVersion, string $reason, ?string $overrideReason = null): CmsTargetDateExtensionRequest
    {
        return $this->decide($request, $extensionId, $versionId, $lockVersion, 'REJECTED', $reason, $overrideReason);
    }

    public function revise(Request $request, int $extensionId, int $versionId, int $lockVersion, string $reason): CmsTargetDateExtensionRequest
    {
        $actor = $request->user();
        $extension = DB::transaction(function () use ($request, $actor, $extensionId, $versionId, $lockVersion, $reason): CmsTargetDateExtensionRequest {
            [$case, $family, $source] = $this->resolveVersion($actor, $extensionId, $versionId, true);
            $this->authorizeResponsible($actor, $case, 'cms.extension.revise');
            $this->assertVersionLock($source, $lockVersion);
            $this->assertStatus($source, CmsTargetDateExtensionVersion::STATUS_RETURNED);
            $this->assertRequestLock($family, $lockVersion);
            throw_unless((int) $family->current_version_id === $source->id, ValidationException::withMessages(['version' => ['Only the current returned version may be revised.']]));
            $this->assertCaseEligible($case);
            $accepted = $this->acceptedPlan($case);
            $progress = $this->latestRecordedProgress($case);
            $number = (int) $family->versions()->lockForUpdate()->max('version_number') + 1;
            $source->load('activeEvidenceLinks');
            $revision = $family->versions()->create([
                'version_number' => $number,
                'previous_version_id' => $source->id,
                'status_code' => CmsTargetDateExtensionVersion::STATUS_DRAFT,
                'active_slot' => 'ACTIVE',
                'accepted_action_plan_version_id' => $accepted->id,
                'recorded_progress_update_version_id' => $progress?->recorded_version_id,
                'case_lock_version' => $case->lock_version,
                'requested_target_date' => $source->requested_target_date,
                ...$this->copyNarratives($source),
                'prepared_by' => $actor->id,
                'revision_reason' => $reason,
                'lock_version' => 1,
            ]);
            foreach ($source->activeEvidenceLinks as $evidence) {
                $revision->evidenceLinks()->create([
                    'document_id' => $evidence->document_id,
                    'document_version_id' => $evidence->document_version_id,
                    'evidence_category' => $evidence->evidence_category,
                    'title' => $evidence->title,
                    'description' => $evidence->description,
                    'source_or_custodian' => $evidence->source_or_custodian,
                    'linked_by' => $actor->id,
                    'linked_at' => now(),
                    'checksum_sha256' => $evidence->checksum_sha256,
                    'confidentiality_level_id' => $evidence->confidentiality_level_id,
                    'confidentiality_code_snapshot' => $evidence->confidentiality_code_snapshot,
                ]);
            }
            $family->forceFill([
                'current_version_id' => $revision->id,
                'lock_version' => $family->lock_version + 1,
            ])->save();
            $this->record($request, $case, $family, $revision, 'EXTENSION_REVISION_CREATED', 'cms.extension.revision_created', $source->status_code, $revision->status_code, ['sourceVersionId' => $source->id, 'revisionReason' => $reason]);

            return $family;
        }, 3);

        return $extension->fresh($this->requestRelations());
    }

    /** @param string $decisionCode APPROVED or REJECTED */
    private function decide(Request $request, int $extensionId, int $versionId, int $lockVersion, string $decisionCode, string $comment, ?string $overrideReason): CmsTargetDateExtensionRequest
    {
        $actor = $request->user();
        $extension = DB::transaction(function () use ($request, $actor, $extensionId, $versionId, $lockVersion, $decisionCode, $comment, $overrideReason): CmsTargetDateExtensionRequest {
            [$case, $family, $version] = $this->resolveVersion($actor, $extensionId, $versionId, true);
            $this->assertVersionLock($version, $lockVersion);
            $this->assertStatus($version, CmsTargetDateExtensionVersion::STATUS_FOR_APPROVAL);
            $decisionPermission = $decisionCode === 'APPROVED'
                ? 'cms.extension.approve'
                : 'cms.extension.reject';
            $this->authorizeDecision($actor, $case, $version, $decisionPermission);
            $this->assertSnapshotIntegrity($version);
            $this->assertCaseEligible($case);
            $this->assertCaseSource($case, $version);
            $assessment = $version->assessment()->lockForUpdate()->firstOrFail();
            if ($assessment->recommendation_code !== 'RECOMMEND_'.($decisionCode === 'APPROVED' ? 'APPROVAL' : 'REJECTION')
                && blank($overrideReason)) {
                throw ValidationException::withMessages([
                    'overrideReason' => ['An override reason is required when the final decision differs from the assessment recommendation.'],
                ]);
            }
            if ($version->decision()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['decision' => ['This extension version already has a final decision.']]);
            }
            $previous = $case->effective_target_implementation_date;
            $decision = $version->decision()->create([
                'decision_code' => $decisionCode,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_comment' => $comment,
                'override_reason' => $overrideReason,
                'previous_effective_target_date' => $previous,
                'approved_target_date' => $decisionCode === 'APPROVED' ? $version->requested_target_date : null,
                'new_effective_target_date' => $decisionCode === 'APPROVED' ? $version->requested_target_date : $previous,
            ]);
            $from = $version->status_code;
            if ($decisionCode === 'APPROVED') {
                $case->forceFill([
                    'effective_target_implementation_date' => $version->requested_target_date,
                    'lock_version' => $case->lock_version + 1,
                ])->save();
                $history = CmsRecommendationTargetDateHistory::query()->create([
                    'cms_recommendation_case_id' => $case->id,
                    'history_code' => 'EXTENSION_APPROVED',
                    'previous_target_date' => $previous,
                    'new_target_date' => $version->requested_target_date,
                    'cms_target_date_extension_decision_id' => $decision->id,
                    'actor_id' => $actor->id,
                    'occurred_at' => now(),
                    'metadata' => [
                        'extensionRequestId' => $family->id,
                        'extensionVersionId' => $version->id,
                        'requestedExtensionDays' => $this->extensionDays($family, $version),
                    ],
                    'created_at' => now(),
                ]);
                $this->record($request, $case, $family, $version, 'EFFECTIVE_TARGET_DATE_CHANGED', 'cms.recommendation.effective_target_changed', $from, CmsTargetDateExtensionVersion::STATUS_APPROVED, [
                    'targetDateHistoryId' => $history->id,
                    'previousEffectiveTargetDate' => $previous?->toDateString(),
                    'newEffectiveTargetDate' => $version->requested_target_date?->toDateString(),
                ]);
            }
            $version->forceFill([
                'status_code' => $decisionCode,
                'active_slot' => null,
                'lock_version' => $version->lock_version + 1,
            ])->save();
            $family->forceFill([
                'resolved_version_id' => $version->id,
                'resolved_at' => now(),
                'lock_version' => $family->lock_version + 1,
            ])->save();
            $this->record($request, $case, $family, $version, 'EXTENSION_'.($decisionCode === 'APPROVED' ? 'APPROVED' : 'REJECTED'), 'cms.extension.'.strtolower($decisionCode), $from, $version->status_code, [
                'decisionId' => $decision->id,
                'decisionCode' => $decisionCode,
                'overrideReason' => $overrideReason,
                'previousEffectiveTargetDate' => $previous?->toDateString(),
                'approvedTargetDate' => $decision->approved_target_date?->toDateString(),
            ]);
            $this->notifyAfterCommit(strtolower($decisionCode), $request, $case, $family, $version);

            return $family;
        }, 3);

        return $extension->fresh($this->requestRelations());
    }

    /** @param array<string, mixed> $attributes */
    public function uploadEvidence(Request $request, int $extensionId, int $versionId, array $attributes, UploadedFile $file): CmsTargetDateExtensionRequest
    {
        $actor = $request->user();
        [$case, $family, $version] = $this->resolveVersion($actor, $extensionId, $versionId);
        $this->authorizeResponsible($actor, $case, 'cms.extension-evidence.upload');
        $requested = MasterListItem::query()->findOrFail((int) $attributes['confidentialityLevelId']);
        $effective = $this->effectiveClassification($case, $requested);
        $this->documentAccess->authorizeClassification($actor, $effective);
        $stored = $this->storeFile($file, $case);

        try {
            DB::transaction(function () use ($request, $actor, $family, $version, $attributes, $effective, $stored): void {
                [$lockedCase, $lockedFamily, $lockedVersion] = $this->resolveVersion($actor, $family->id, $version->id, true);
                $this->assertVersionLock($lockedVersion, (int) $attributes['lockVersion']);
                $this->assertRequestLock($lockedFamily, (int) $attributes['lockVersion']);
                $this->assertDraft($lockedFamily, $lockedVersion);
                if ($lockedVersion->activeEvidenceLinks()->where('checksum_sha256', $stored['checksum_sha256'])->exists()) {
                    throw ValidationException::withMessages(['file' => ['This exact file is already linked to the extension draft.']]);
                }
                $documentType = MasterList::query()->where('code', 'DOCUMENT_TYPE')->firstOrFail()->items()->where('code', 'OTHER')->firstOrFail();
                $document = Document::query()->create([
                    'document_type_id' => $documentType->id,
                    'confidentiality_level_id' => $effective->id,
                    'title' => $attributes['title'],
                    'description' => $attributes['description'] ?? null,
                    'owner_module' => 'CMS',
                    'library_visible' => false,
                    ...$stored,
                    'uploaded_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'is_active' => true,
                ]);
                $document->forceFill([
                    'document_code' => $this->runtime->formatNumber('document_number_format', $document->id),
                ])->save();
                $documentVersion = $document->versions()->create([
                    'version_number' => 1,
                    'version_label' => 'CMS extension evidence version 1',
                    'change_summary' => 'Initial CMS target-date extension evidence upload.',
                    ...$stored,
                    'uploaded_by' => $actor->id,
                ]);
                $document->forceFill([
                    'current_version_id' => $documentVersion->id,
                    'version' => $documentVersion->version_label,
                ])->save();
                $evidence = $lockedVersion->evidenceLinks()->create([
                    'document_id' => $document->id,
                    'document_version_id' => $documentVersion->id,
                    'evidence_category' => strtoupper($attributes['evidenceCategory']),
                    'title' => $attributes['title'],
                    'description' => $attributes['description'] ?? null,
                    'source_or_custodian' => $attributes['sourceOrCustodian'] ?? null,
                    'linked_by' => $actor->id,
                    'linked_at' => now(),
                    'checksum_sha256' => $documentVersion->checksum_sha256,
                    'confidentiality_level_id' => $effective->id,
                    'confidentiality_code_snapshot' => $effective->code,
                ]);
                $document->links()->create([
                    'module_code' => 'CMS',
                    'record_type' => 'EXTENSION_REQUEST_VERSION',
                    'record_id' => $lockedVersion->id,
                    'record_code' => $lockedVersion->display_code,
                    'record_label' => $attributes['title'],
                    'linked_by' => $actor->id,
                ]);
                $lockedVersion->forceFill(['lock_version' => $lockedVersion->lock_version + 1])->save();
                $lockedFamily->forceFill(['lock_version' => $lockedFamily->lock_version + 1])->save();
                $this->record($request, $lockedCase, $lockedFamily, $lockedVersion, 'EXTENSION_EVIDENCE_LINKED', 'cms.extension.evidence_linked', $lockedVersion->status_code, $lockedVersion->status_code, [
                    'evidenceLinkId' => $evidence->id,
                    'documentId' => $document->id,
                    'documentVersionId' => $documentVersion->id,
                    'checksumSha256' => $evidence->checksum_sha256,
                ]);
            }, 3);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($stored['storage_path']);
            throw $error;
        }

        return $family->fresh($this->requestRelations());
    }

    public function removeEvidence(Request $request, int $evidenceId, int $lockVersion, string $reason): CmsTargetDateExtensionRequest
    {
        $actor = $request->user();
        $reference = CmsTargetDateExtensionEvidenceLink::query()->find($evidenceId);
        throw_unless($reference, new HttpException(404, 'The extension evidence is unavailable.'));

        return DB::transaction(function () use ($request, $actor, $reference, $lockVersion, $reason): CmsTargetDateExtensionRequest {
            $reference->loadMissing('version.request');
            [$case, $family, $version] = $this->resolveVersion($actor, $reference->version->request->id, $reference->version->id, true);
            $this->authorizeResponsible($actor, $case, 'cms.extension-evidence.remove_draft');
            $this->assertVersionLock($version, $lockVersion);
            $this->assertRequestLock($family, $lockVersion);
            $this->assertDraft($family, $version);
            $evidence = CmsTargetDateExtensionEvidenceLink::query()->whereKey($reference->id)->where('cms_target_date_extension_version_id', $version->id)->lockForUpdate()->first();
            throw_unless($evidence && ! $evidence->removed_at, new HttpException(404, 'The extension evidence is unavailable.'));
            $evidence->forceFill(['removed_by' => $actor->id, 'removed_at' => now(), 'removal_reason' => $reason])->save();
            $version->forceFill(['lock_version' => $version->lock_version + 1])->save();
            $family->forceFill(['lock_version' => $family->lock_version + 1])->save();
            $this->record($request, $case, $family, $version, 'EXTENSION_EVIDENCE_REMOVED', 'cms.extension.evidence_draft_removed', $version->status_code, $version->status_code, ['evidenceLinkId' => $evidence->id, 'removalReason' => $reason]);

            return $family->fresh($this->requestRelations());
        }, 3);
    }

    public function downloadEvidence(Request $request, int $evidenceId): StreamedResponse
    {
        $actor = $request->user();
        $evidence = CmsTargetDateExtensionEvidenceLink::query()->whereNull('removed_at')->find($evidenceId);
        throw_unless($evidence, new HttpException(404, 'The extension evidence is unavailable.'));
        $evidence->load('version.request', 'document', 'documentVersion');
        [$case, , $version] = $this->resolveVersion($actor, $evidence->version->request->id, $evidence->version->id);
        throw_unless($actor->hasPermission('cms.extension-evidence.download') && $this->scope->canViewClassification($actor, $evidence->confidentiality_code_snapshot), new HttpException(403, 'You cannot download this extension evidence.'));
        $this->documentAccess->authorizeView($actor, $evidence->document);
        abort_unless(Storage::disk('local')->exists($evidence->documentVersion->storage_path), 404, 'Stored extension evidence file not found.');
        $this->recordDownloadAudit($request, $case, $evidence);

        return Storage::disk('local')->download($evidence->documentVersion->storage_path, $evidence->documentVersion->original_file_name, ['Content-Type' => $evidence->documentVersion->mime_type]);
    }

    /** @return list<string> */
    public function permittedActions(User $actor, CmsTargetDateExtensionRequest $family, ?CmsTargetDateExtensionVersion $version): array
    {
        if (! $version) {
            return [];
        }
        $family->loadMissing('case.recommendation', 'case.currentAssignment', 'case.actionPlan.acceptedVersion');
        $case = $family->case;
        $actions = [];
        if ($version->status_code === CmsTargetDateExtensionVersion::STATUS_DRAFT
            && $this->canResponsible($actor, $case, 'cms.extension.update')) {
            $actions[] = 'update';
            if ($actor->hasPermission('cms.extension.submit')) {
                $actions[] = 'submit';
            }
            if ($actor->hasPermission('cms.extension-evidence.upload')) {
                $actions[] = 'upload-evidence';
            }
        }
        if ($version->status_code === CmsTargetDateExtensionVersion::STATUS_RETURNED
            && (int) $family->current_version_id === $version->id
            && $this->canResponsible($actor, $case, 'cms.extension.revise')) {
            $actions[] = 'revise';
        }
        if ($version->status_code === CmsTargetDateExtensionVersion::STATUS_SUBMITTED
            && $this->canReviewer($actor, $case, $version, 'cms.extension.review')) {
            $actions[] = 'start-review';
        }
        if ($version->status_code === CmsTargetDateExtensionVersion::STATUS_UNDER_REVIEW) {
            if ($this->canReviewer($actor, $case, $version, 'cms.extension.return')) {
                $actions[] = 'return';
            }
            if ($this->canReviewer($actor, $case, $version, 'cms.extension.recommend')) {
                $actions[] = 'recommend';
            }
        }
        if ($version->status_code === CmsTargetDateExtensionVersion::STATUS_FOR_APPROVAL) {
            if ($this->canDecision($actor, $case, $version, 'cms.extension.return')) {
                $actions[] = 'return';
            }
            if ($this->canDecision($actor, $case, $version, 'cms.extension.approve')) {
                $actions[] = 'approve';
            }
            if ($this->canDecision($actor, $case, $version, 'cms.extension.reject')) {
                $actions[] = 'reject';
            }
        }

        return $actions;
    }

    /** @return list<string> */
    private function permittedFamilyActions(User $actor, CmsRecommendationCase $case, ?CmsTargetDateExtensionRequest $family): array
    {
        if (! $family) {
            return $this->canResponsible($actor, $case, 'cms.extension.create') ? ['create'] : [];
        }

        return $this->permittedActions($actor, $family, $family->currentVersion);
    }

    /** @return array<string, mixed> */
    private function narrativeAttributes(array $attributes): array
    {
        return [
            'extension_justification' => $attributes['extensionJustification'] ?? '',
            'cause_of_delay' => $attributes['causeOfDelay'] ?? '',
            'actions_already_taken' => $attributes['actionsAlreadyTaken'] ?? '',
            'remaining_actions' => $attributes['remainingActions'] ?? '',
            'recovery_plan' => $attributes['recoveryPlan'] ?? '',
            'impact_if_not_approved' => $attributes['impactIfNotApproved'] ?? '',
            'revised_schedule_summary' => $attributes['revisedScheduleSummary'] ?? '',
            'management_progress_summary' => $attributes['managementProgressSummary'] ?? null,
            'no_evidence_explanation' => $attributes['noEvidenceExplanation'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function copyNarratives(CmsTargetDateExtensionVersion $source): array
    {
        return collect([
            'extension_justification', 'cause_of_delay', 'actions_already_taken',
            'remaining_actions', 'recovery_plan', 'impact_if_not_approved',
            'revised_schedule_summary', 'management_progress_summary',
            'no_evidence_explanation',
        ])->mapWithKeys(fn (string $column): array => [$column => $source->{$column}])->all();
    }

    private function visibleCase(User $actor, int $caseId, bool $lock = false): CmsRecommendationCase
    {
        return $this->scope->resolveVisibleCase($actor, $caseId, 'cms.extension.view', $lock);
    }

    private function acceptedPlan(CmsRecommendationCase $case): CmsActionPlanVersion
    {
        $case->loadMissing('actionPlan.acceptedVersion');
        $accepted = $case->actionPlan?->acceptedVersion;
        throw_unless($accepted && $accepted->status_code === CmsActionPlanVersion::STATUS_ACCEPTED, ValidationException::withMessages(['actionPlan' => ['An accepted Corrective Action Plan is required before requesting a target-date extension.']]));

        return $accepted;
    }

    private function latestRecordedProgress(CmsRecommendationCase $case): ?CmsProgressUpdate
    {
        return CmsProgressUpdate::query()
            ->where('cms_recommendation_case_id', $case->id)
            ->whereNotNull('recorded_version_id')
            ->with(['recordedVersion', 'acceptedActionPlanVersion'])
            ->orderByDesc('reporting_period_end')
            ->orderByDesc('reporting_sequence')
            ->first();
    }

    private function assertCaseEligible(CmsRecommendationCase $case): void
    {
        if (! in_array($case->status_code, [CmsRecommendationCase::STATUS_MONITORING, CmsRecommendationCase::STATUS_PARTIALLY_IMPLEMENTED], true)) {
            throw ValidationException::withMessages(['recommendation' => ['Target-date extensions may be requested only while the recommendation is under monitoring or partially implemented.']]);
        }
        if (! $case->effective_target_implementation_date) {
            throw ValidationException::withMessages(['effectiveTargetDate' => ['The recommendation has no current effective target date.']]);
        }
        $this->acceptedPlan($case);
        if ($case->activeValidationReview()->exists()) {
            throw ValidationException::withMessages(['validation' => ['An active independent Validation Review blocks a target-date extension.']]);
        }
    }

    private function assertNoUnresolved(CmsRecommendationCase $case): void
    {
        if (CmsTargetDateExtensionRequest::query()->where('cms_recommendation_case_id', $case->id)->whereNull('resolved_at')->lockForUpdate()->exists()) {
            throw ValidationException::withMessages(['extension' => ['Another unresolved target-date extension request already exists.']]);
        }
    }

    private function assertRequestedDate(CmsTargetDateExtensionRequest $family, CmsTargetDateExtensionVersion $version, bool $submission = false): void
    {
        $baseline = $family->baseline_effective_target_date;
        if (! $version->requested_target_date || $version->requested_target_date->lte($baseline)) {
            throw ValidationException::withMessages(['requestedTargetDate' => ['The requested target date must be later than the family baseline effective target date.']]);
        }
        if ($submission && $version->requested_target_date->lte(CarbonImmutable::today())) {
            throw ValidationException::withMessages(['requestedTargetDate' => ['The requested target date must be in the future when submitted.']]);
        }
    }

    private function assertSubmittedOnTime(CmsRecommendationCase $case): void
    {
        if (CarbonImmutable::today()->gt($case->effective_target_implementation_date)) {
            throw ValidationException::withMessages(['submittedAt' => ['A target-date extension must be submitted on or before the current effective target date.']]);
        }
    }

    private function assertCaseSource(CmsRecommendationCase $case, CmsTargetDateExtensionVersion $version): void
    {
        $accepted = $this->acceptedPlan($case);
        $latest = $this->latestRecordedProgress($case);
        if ((int) $version->accepted_action_plan_version_id !== (int) $accepted->id
            || (int) $version->case_lock_version !== (int) $case->lock_version
            || (int) ($version->recorded_progress_update_version_id ?? 0) !== (int) ($latest?->recorded_version_id ?? 0)) {
            throw ValidationException::withMessages(['sourceContext' => ['The extension source context is stale. Refresh the accepted Action Plan and latest recorded Progress Update before continuing.']]);
        }
        if ($latest && ((int) $latest->accepted_action_plan_version_id !== (int) $accepted->id || $latest->recordedVersion?->status_code !== CmsProgressUpdateVersion::STATUS_RECORDED)) {
            throw ValidationException::withMessages(['recordedProgressUpdateVersionId' => ['The latest recorded Progress Update is no longer eligible.']]);
        }
    }

    private function assertComplete(CmsTargetDateExtensionVersion $version): void
    {
        $errors = [];
        foreach ([
            'extension_justification' => 'extensionJustification',
            'cause_of_delay' => 'causeOfDelay',
            'actions_already_taken' => 'actionsAlreadyTaken',
            'remaining_actions' => 'remainingActions',
            'recovery_plan' => 'recoveryPlan',
            'impact_if_not_approved' => 'impactIfNotApproved',
            'revised_schedule_summary' => 'revisedScheduleSummary',
        ] as $column => $field) {
            if (blank($version->{$column})) {
                $errors[$field][] = 'Complete this field before submitting the extension request.';
            }
        }
        if ($version->activeEvidenceLinks()->count() === 0 && blank($version->no_evidence_explanation)) {
            $errors['evidence'][] = 'Link at least one supporting evidence document or provide a no-evidence explanation.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function assertSnapshotIntegrity(CmsTargetDateExtensionVersion $version): void
    {
        if ($version->status_code !== CmsTargetDateExtensionVersion::STATUS_DRAFT && ! is_array($version->submission_snapshot)) {
            throw ValidationException::withMessages(['snapshot' => ['The immutable extension submission snapshot is unavailable.']]);
        }
    }

    private function assertDraft(CmsTargetDateExtensionRequest $family, CmsTargetDateExtensionVersion $version): void
    {
        throw_unless($version->status_code === CmsTargetDateExtensionVersion::STATUS_DRAFT && (int) $family->current_version_id === (int) $version->id, ValidationException::withMessages(['version' => ['Only the current draft extension version may be edited.']]));
    }

    private function assertStatus(CmsTargetDateExtensionVersion $version, string|array $statuses): void
    {
        $statuses = (array) $statuses;
        throw_unless(in_array($version->status_code, $statuses, true), ValidationException::withMessages(['status' => ['This target-date extension is not at the required workflow stage.']]));
    }

    private function assertCaseLock(CmsRecommendationCase $case, int $lockVersion): void
    {
        if ($case->lock_version !== $lockVersion) {
            throw ValidationException::withMessages(['lockVersion' => ['The recommendation changed while you were editing. Reload and try again.']]);
        }
    }

    private function assertVersionLock(CmsTargetDateExtensionVersion $version, int $lockVersion): void
    {
        if ($version->lock_version !== $lockVersion) {
            throw ValidationException::withMessages(['lockVersion' => ['The extension version changed while you were editing. Reload and try again.']]);
        }
    }

    private function assertRequestLock(CmsTargetDateExtensionRequest $family, int $lockVersion): void
    {
        if ($family->lock_version !== $lockVersion) {
            throw ValidationException::withMessages(['lockVersion' => ['The extension request changed while you were editing. Reload and try again.']]);
        }
    }

    private function authorizeResponsible(User $actor, CmsRecommendationCase $case, string $permission): void
    {
        throw_unless(
            $this->canResponsible($actor, $case, $permission),
            new HttpException(403, 'Only an authorized responsible-office user may prepare this extension request.'),
        );
    }

    private function canResponsible(User $actor, CmsRecommendationCase $case, string $permission): bool
    {
        return $this->scope->isUsableAccount($actor)
            && $actor->hasPermission($permission)
            && $case->lead_responsible_office_id
            && (int) $actor->office_id === (int) $case->lead_responsible_office_id
            && ! $actor->hasPermission('cms.administration.monitor');
    }

    private function authorizeReviewer(User $actor, CmsRecommendationCase $case, CmsTargetDateExtensionVersion $version, string $permission): void
    {
        throw_unless($this->canReviewer($actor, $case, $version, $permission), new HttpException(403, 'An independently eligible Compliance Monitor or CMS reviewer is required.'));
    }

    private function canReviewer(User $actor, CmsRecommendationCase $case, CmsTargetDateExtensionVersion $version, string $permission): bool
    {
        if (! $this->scope->isUsableAccount($actor)
            || ! $actor->hasPermission($permission)
            || (int) $actor->office_id === (int) $case->lead_responsible_office_id
            || in_array($actor->id, array_filter([$version->prepared_by, $version->submitted_by]), true)
            || ! $this->scope->canViewClassification($actor, $case->recommendation?->confidentiality_code_snapshot)) {
            return false;
        }
        $isMonitor = $case->currentAssignment()
            ->where('user_id', $actor->id)
            ->where('assignment_role_code', 'COMPLIANCE_MONITOR')
            ->where('is_current', true)
            ->exists();
        $isIndependentReviewer = $actor->hasPermission('cms.extension.review');

        return $isMonitor || $isIndependentReviewer || $actor->hasGlobalEngagementAccess();
    }

    private function authorizeDecision(User $actor, CmsRecommendationCase $case, CmsTargetDateExtensionVersion $version, string $permission): void
    {
        throw_unless($this->canDecision($actor, $case, $version, $permission), new HttpException(403, 'Only independently eligible CIAS Management may make this extension decision.'));
    }

    private function canDecision(User $actor, CmsRecommendationCase $case, CmsTargetDateExtensionVersion $version, string $permission): bool
    {
        return $this->scope->isUsableAccount($actor)
            && $actor->hasPermission($permission)
            && (int) $actor->office_id !== (int) $case->lead_responsible_office_id
            && ! in_array($actor->id, array_filter([$version->prepared_by, $version->submitted_by, $version->assessment?->assessor_user_id]), true)
            && $this->scope->canViewClassification($actor, $case->recommendation?->confidentiality_code_snapshot);
    }

    /** @return array<string, mixed> */
    private function eligibilityReasons(User $actor, CmsRecommendationCase $case, ?CmsTargetDateExtensionRequest $active, ?CmsActionPlanVersion $accepted): array
    {
        $reasons = [];
        if (! in_array($case->status_code, [CmsRecommendationCase::STATUS_MONITORING, CmsRecommendationCase::STATUS_PARTIALLY_IMPLEMENTED], true)) {
            $reasons[] = 'The recommendation must be in MONITORING or PARTIALLY_IMPLEMENTED status.';
        }
        if (! $case->effective_target_implementation_date) {
            $reasons[] = 'No current effective target date is available.';
        }
        if (! $accepted || $accepted->status_code !== CmsActionPlanVersion::STATUS_ACCEPTED) {
            $reasons[] = 'An accepted Corrective Action Plan is required.';
        }
        if ($active) {
            $reasons[] = 'Another unresolved extension request already exists.';
        }
        if ($case->activeValidationReview) {
            $reasons[] = 'An active independent Validation Review is underway.';
        }
        if (! $this->canResponsible($actor, $case, 'cms.extension.create')) {
            $reasons[] = 'The actor is not an authorized responsible-office user for this recommendation.';
        }

        return array_values(array_unique($reasons));
    }

    /** @return array<string, mixed> */
    private function submissionSnapshot(CmsRecommendationCase $case, CmsTargetDateExtensionRequest $family, CmsTargetDateExtensionVersion $version): array
    {
        $case->loadMissing('recommendation', 'leadResponsibleOffice');
        $version->loadMissing('acceptedActionPlanVersion', 'recordedProgressUpdateVersion', 'activeEvidenceLinks.documentVersion');

        return [
            'caseId' => $case->id,
            'recommendationCode' => $case->recommendation?->recommendation_code,
            'originalTargetDate' => $case->recommendation?->original_target_implementation_date?->toDateString(),
            'baselineEffectiveTargetDate' => $family->baseline_effective_target_date?->toDateString(),
            'requestedTargetDate' => $version->requested_target_date?->toDateString(),
            'caseLockVersion' => $version->case_lock_version,
            'acceptedActionPlanVersionId' => $version->accepted_action_plan_version_id,
            'recordedProgressUpdateVersionId' => $version->recorded_progress_update_version_id,
            'managementProgressSummary' => $version->management_progress_summary,
            'narratives' => $this->copyNarratives($version),
            'evidence' => $version->activeEvidenceLinks->map(fn (CmsTargetDateExtensionEvidenceLink $evidence): array => [
                'id' => $evidence->id,
                'documentId' => $evidence->document_id,
                'documentVersionId' => $evidence->document_version_id,
                'checksumSha256' => $evidence->checksum_sha256,
            ])->values()->all(),
            'submittedBy' => $version->submitted_by,
            'submittedAt' => now()->toISOString(),
        ];
    }

    private function resolveVersion(User $actor, int $extensionId, int $versionId, bool $lock = false): array
    {
        $reference = CmsTargetDateExtensionRequest::query()->find($extensionId);
        throw_unless($reference, new HttpException(404, 'The target-date extension is unavailable.'));
        $case = $this->visibleCase($actor, $reference->cms_recommendation_case_id, $lock);
        $query = CmsTargetDateExtensionRequest::query()->whereKey($extensionId)->where('cms_recommendation_case_id', $case->id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $family = $query->first();
        throw_unless($family, new HttpException(404, 'The target-date extension is unavailable.'));
        $versionQuery = CmsTargetDateExtensionVersion::query()->whereKey($versionId)->where('cms_target_date_extension_request_id', $family->id);
        if ($lock) {
            $versionQuery->lockForUpdate();
        }
        $version = $versionQuery->first();
        throw_unless($version, new HttpException(404, 'The target-date extension version is unavailable.'));
        $version->loadMissing('assessment');
        $case->loadMissing('recommendation', 'leadResponsibleOffice', 'currentAssignment.user');

        return [$case, $family, $version];
    }

    /** @return list<string> */
    private function requestRelations(): array
    {
        return [
            'case.recommendation',
            'case.leadResponsibleOffice',
            'case.currentAssignment.user',
            'creator',
            'versions.previousVersion',
            'versions.acceptedActionPlanVersion',
            'versions.recordedProgressUpdateVersion',
            'versions.preparer',
            'versions.submitter',
            'versions.reviewStarter',
            'versions.returner',
            'versions.assessment.assessor',
            'versions.decision.decider',
            'versions.activeEvidenceLinks.documentVersion',
            'currentVersion.assessment.assessor',
            'currentVersion.decision.decider',
            'currentVersion.activeEvidenceLinks.documentVersion',
            'resolvedVersion.decision.decider',
        ];
    }

    private function extensionDays(CmsTargetDateExtensionRequest $family, CmsTargetDateExtensionVersion $version): int
    {
        return $family->baseline_effective_target_date->diffInDays($version->requested_target_date);
    }

    private function record(Request $request, CmsRecommendationCase $case, CmsTargetDateExtensionRequest $family, CmsTargetDateExtensionVersion $version, string $eventCode, string $action, ?string $previousStatus, string $newStatus, array $metadata = []): void
    {
        $payload = [
            'caseId' => $case->id,
            'extensionRequestId' => $family->id,
            'extensionVersionId' => $version->id,
            'versionNumber' => $version->version_number,
            'originalTargetDate' => $case->recommendation?->original_target_implementation_date?->toDateString(),
            'previousEffectiveTargetDate' => $family->baseline_effective_target_date?->toDateString(),
            'requestedTargetDate' => $version->requested_target_date?->toDateString(),
            'requestedExtensionDays' => $this->extensionDays($family, $version),
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
            ...$metadata,
        ];
        CmsRecommendationEvent::query()->create([
            'cms_recommendation_case_id' => $case->id,
            'cms_recommendation_id' => $case->cms_recommendation_id,
            'idempotency_key' => "cms.extension.{$version->id}.{$eventCode}.{$version->lock_version}",
            'event_code' => $eventCode,
            'source_module' => 'CMS',
            'actor_id' => $request->user()->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'event_metadata' => $payload,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
        ActivityRecorder::record($request, $action, "CMS target-date extension {$action}.", metadata: [
            'module' => 'CMS',
            'recordType' => 'CMS_TARGET_DATE_EXTENSION',
            'recordId' => $family->id,
            'recordCode' => $version->display_code,
            ...$payload,
        ]);
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => CmsTargetDateExtensionVersion::class,
            'auditable_id' => $version->id,
            'old_values' => $previousStatus ? json_encode(['status' => $previousStatus]) : null,
            'new_values' => json_encode(['status' => $newStatus, ...$metadata]),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => json_encode(['module' => 'CMS', 'caseId' => $case->id, 'extensionRequestId' => $family->id]),
            'created_at' => now(),
        ]);
    }

    private function notifyAfterCommit(string $event, Request $request, CmsRecommendationCase $case, CmsTargetDateExtensionRequest $family, CmsTargetDateExtensionVersion $version): void
    {
        $case->loadMissing('currentAssignment.user', 'actionPlan.acceptedVersion.focalUser', 'recommendation');
        $recipients = collect([
            $case->currentAssignment?->user_id,
            $version->submitted_by,
            $case->actionPlan?->acceptedVersion?->focal_user_id,
        ])->filter()->unique()->values();
        if (in_array($event, ['assessed'], true)) {
            $recipients = $recipients->merge(
                User::query()->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'cms.extension.review'))->pluck('id'),
            );
        }
        if (in_array($event, ['approved', 'rejected'], true)) {
            $recipients = $recipients->merge(
                User::query()->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'cms.extension.approve'))->pluck('id'),
            );
        }
        $recipients = $recipients->filter(fn ($id): bool => (int) $id !== (int) $request->user()->id)->values();
        if ($recipients->isEmpty()) {
            return;
        }
        $labels = [
            'submitted' => ['CMS_EXTENSION_SUBMITTED', 'Target-date extension submitted', 'The responsible office submitted a target-date extension request.'],
            'review_started' => ['CMS_EXTENSION_REVIEW_STARTED', 'Target-date extension review started', 'A target-date extension request entered review.'],
            'returned' => ['CMS_EXTENSION_RETURNED', 'Target-date extension returned', 'A target-date extension request was returned for revision.'],
            'assessed' => ['CMS_EXTENSION_ASSESSED', 'Target-date extension assessed', 'A Compliance Monitor completed an extension assessment.'],
            'approved' => ['CMS_EXTENSION_APPROVED', 'Target-date extension approved', 'The effective target date was updated by an approved extension.'],
            'rejected' => ['CMS_EXTENSION_REJECTED', 'Target-date extension rejected', 'A target-date extension request was rejected; the effective target date is unchanged.'],
        ];
        [$type, $title, $message] = $labels[$event] ?? ['CMS_EXTENSION_UPDATED', 'Target-date extension updated', 'A target-date extension was updated.'];
        DB::afterCommit(fn () => $this->notifications->send($recipients, [
            'actorId' => $request->user()->id,
            'type' => $type,
            'category' => 'CMS_EXTENSION',
            'priority' => in_array($event, ['approved', 'rejected'], true) ? 'HIGH' : 'NORMAL',
            'moduleCode' => 'CMS',
            'title' => $title,
            'message' => $message,
            'actionUrl' => "/compliance-management/recommendations/{$case->id}/extensions/{$family->id}",
            'actionLabel' => 'Open extension request',
            'subjectType' => CmsTargetDateExtensionRequest::class,
            'subjectId' => $family->id,
            'subjectCode' => $version->display_code,
            'dedupeKey' => "cms-extension:{$family->id}:{$version->id}:{$event}",
            'metadata' => [
                'caseId' => $case->id,
                'extensionRequestId' => $family->id,
                'extensionVersionId' => $version->id,
                'previousEffectiveTargetDate' => $family->baseline_effective_target_date?->toDateString(),
                'requestedTargetDate' => $version->requested_target_date?->toDateString(),
            ],
        ]));
    }

    private function effectiveClassification(CmsRecommendationCase $case, MasterListItem $requested): MasterListItem
    {
        $case->loadMissing('recommendation');
        $caseCode = strtoupper((string) ($case->recommendation?->confidentiality_code_snapshot ?? 'INTERNAL'));
        $requestedCode = strtoupper((string) $requested->code);
        if ((self::CLASSIFICATION_RANK[$requestedCode] ?? 2) >= (self::CLASSIFICATION_RANK[$caseCode] ?? 2)) {
            return $requested;
        }
        if ($case->recommendation?->confidentiality_level_id) {
            return MasterListItem::query()->findOrFail($case->recommendation->confidentiality_level_id);
        }

        return MasterList::query()->where('code', 'DOCUMENT_CONFIDENTIALITY')->firstOrFail()->items()->where('code', $caseCode)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function storeFile(UploadedFile $file, CmsRecommendationCase $case): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid().($extension ? ".{$extension}" : '');
        $path = Storage::disk('local')->putFileAs("cms/recommendations/{$case->id}/extension-evidence", $file, $storedName);
        if (! $path) {
            throw ValidationException::withMessages(['file' => ['The extension evidence file could not be stored.']]);
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

    private function recordDownloadAudit(Request $request, CmsRecommendationCase $case, CmsTargetDateExtensionEvidenceLink $evidence): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'cms.extension.evidence_downloaded',
            'auditable_type' => CmsTargetDateExtensionEvidenceLink::class,
            'auditable_id' => $evidence->id,
            'old_values' => null,
            'new_values' => json_encode(['documentVersionId' => $evidence->document_version_id, 'checksumSha256' => $evidence->checksum_sha256]),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => json_encode(['module' => 'CMS', 'caseId' => $case->id]),
            'created_at' => now(),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function safeUser(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'name' => $user->name,
            'initials' => $user->initials,
        ] : null;
    }
}
