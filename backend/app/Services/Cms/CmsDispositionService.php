<?php

namespace App\Services\Cms;

use App\Models\CmsDispositionEvidenceLink;
use App\Models\CmsDispositionRequest;
use App\Models\CmsDispositionRequestVersion;
use App\Models\CmsDispositionReviewAssessment;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\DocumentAccessService;
use App\Services\NotificationService;
use App\Support\ActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Owns CMS-9A accepted-risk and no-longer-applicable professional decisions. */
class CmsDispositionService
{
    public function __construct(
        private readonly CmsRecommendationScopeService $scope,
        private readonly NotificationService $notifications,
        private readonly DocumentAccessService $documentAccess,
    ) {}

    public function forRecommendation(User $actor, int $caseId): array
    {
        $case = $this->case($actor, $caseId);
        $requests = CmsDispositionRequest::query()
            ->where('cms_recommendation_case_id', $case->id)
            ->with($this->relations())
            ->orderByDesc('request_sequence')->get();
        $requests->each(fn (CmsDispositionRequest $request) => $this->actions($actor, $request));
        return ['case' => $case, 'requests' => $requests, 'permittedActions' => $this->familyActions($actor, $case, $requests->first())];
    }

    public function options(User $actor, int $caseId): array
    {
        $case = $this->case($actor, $caseId);
        $readiness = $this->readiness($actor, $case);
        $active = CmsDispositionRequest::query()->where('cms_recommendation_case_id', $case->id)->whereNull('resolved_at')->with('currentVersion')->first();
        return [
            'case' => $case,
            'readiness' => $readiness,
            'canCreate' => $readiness['eligible'] && ! $active && $this->canInitiate($actor, $case),
            'activeRequest' => $active,
            'dispositionTypes' => [CmsDispositionRequest::ACCEPTED_RISK, CmsDispositionRequest::NO_LONGER_APPLICABLE],
            'initiatorTypes' => $this->initiatorTypes($actor, $case),
            'reasons' => collect($readiness['checklist'])->where('passed', false)->pluck('explanation')->values()->all(),
        ];
    }

    public function show(User $actor, int $id): array
    {
        $reference = CmsDispositionRequest::query()->find($id);
        throw_unless($reference, new HttpException(404, 'The disposition request is unavailable.'));
        $case = $this->case($actor, $reference->cms_recommendation_case_id);
        $request = CmsDispositionRequest::query()->with($this->relations())->findOrFail($id);
        $this->actions($actor, $request);
        return ['case' => $case, 'request' => $request];
    }

    public function create(Request $http, int $caseId, array $data): CmsDispositionRequest
    {
        $actor = $http->user();
        return DB::transaction(function () use ($http, $actor, $caseId, $data): CmsDispositionRequest {
            $case = $this->case($actor, $caseId, true);
            $this->assertInitiator($actor, $case);
            $this->assertCaseLock($case, $data);
            $ready = $this->readiness($actor, $case);
            throw_unless($ready['eligible'], ValidationException::withMessages(['disposition' => ['The recommendation is not currently eligible for a disposition.']]));
            $type = strtoupper((string) ($data['dispositionCode'] ?? ''));
            throw_unless(in_array($type, [CmsDispositionRequest::ACCEPTED_RISK, CmsDispositionRequest::NO_LONGER_APPLICABLE], true), ValidationException::withMessages(['dispositionCode' => ['Select a valid disposition type.']]));
            $active = CmsDispositionRequest::query()->where('cms_recommendation_case_id', $case->id)->whereNull('resolved_at')->lockForUpdate()->first();
            throw_if($active, ValidationException::withMessages(['disposition' => ['An unresolved disposition request already exists.']]));
            $sequence = (int) CmsDispositionRequest::query()->where('cms_recommendation_case_id', $case->id)->lockForUpdate()->max('request_sequence') + 1;
            $request = CmsDispositionRequest::create(['cms_recommendation_case_id' => $case->id, 'request_sequence' => $sequence, 'disposition_code' => $type, 'initiator_type_code' => $data['initiatorTypeCode'] ?? $this->defaultInitiator($actor, $case), 'created_by' => $actor->id, 'lock_version' => 1]);
            $version = $request->versions()->create(['version_number' => 1, 'status_code' => CmsDispositionRequestVersion::DRAFT, 'active_slot' => 'ACTIVE', 'previous_case_status' => $case->status_code, 'case_lock_version' => $case->lock_version, 'prepared_by' => $actor->id, ...$this->narratives($data)]);
            $request->forceFill(['current_version_id' => $version->id])->save();
            $this->record($http, $case, $request, $version, CmsRecommendationEvent::EVENT_DISPOSITION_CREATED, 'cms.disposition.created', null, CmsDispositionRequestVersion::DRAFT);
            return $request->fresh($this->relations());
        });
    }

    public function update(Request $http, int $id, int $versionId, array $data): CmsDispositionRequest
    {
        $actor = $http->user();
        return DB::transaction(function () use ($http, $actor, $id, $versionId, $data): CmsDispositionRequest {
            [$case, $request, $version] = $this->lockedVersion($actor, $id, $versionId);
            $this->assertVersionLock($version, $data);
            throw_unless($version->status_code === CmsDispositionRequestVersion::DRAFT, ValidationException::withMessages(['version' => ['Only drafts can be edited.']]));
            $this->assertInitiator($actor, $case);
            $version->update([...$this->narratives($data), 'lock_version' => $version->lock_version + 1]);
            $request->increment('lock_version');
            $this->record($http, $case, $request, $version->fresh(), CmsRecommendationEvent::EVENT_DISPOSITION_UPDATED, 'cms.disposition.updated', CmsDispositionRequestVersion::DRAFT, CmsDispositionRequestVersion::DRAFT);
            return $request->fresh($this->relations());
        });
    }

    public function submit(Request $http, int $id, int $versionId, array $data): CmsDispositionRequest
    {
        $actor = $http->user();
        return DB::transaction(function () use ($http, $actor, $id, $versionId, $data): CmsDispositionRequest {
            [$case, $request, $version] = $this->lockedVersion($actor, $id, $versionId, true);
            $this->assertVersionLock($version, $data);
            throw_unless($version->status_code === CmsDispositionRequestVersion::DRAFT, ValidationException::withMessages(['version' => ['Only a draft can be submitted.']]));
            $this->assertInitiator($actor, $case);
            $ready = $this->readiness($actor, $case);
            $merged = [...$version->toArray(), ...$this->narratives($data)];
            $this->assertComplete($request->disposition_code, $merged, $ready);
            $version->forceFill(['status_code' => CmsDispositionRequestVersion::SUBMITTED, 'active_slot' => 'ACTIVE', 'submitted_by' => $actor->id, 'submitted_at' => now(), 'submission_snapshot' => $this->snapshot($case, $request, $version, $ready), 'lock_version' => $version->lock_version + 1])->save();
            $case->forceFill(['status_code' => CmsRecommendationCase::STATUS_FOR_DISPOSITION, 'lock_version' => $case->lock_version + 1])->save();
            $this->record($http, $case, $request, $version, CmsRecommendationEvent::EVENT_DISPOSITION_SUBMITTED, 'cms.disposition.submitted', $version->previous_case_status, CmsRecommendationCase::STATUS_FOR_DISPOSITION);
            $this->notifyAfterCommit($http, $case, $request, $version, 'submitted');
            return $request->fresh($this->relations());
        });
    }

    public function startReview(Request $http, int $id, int $versionId, array $data): CmsDispositionRequest
    {
        return $this->transition($http, $id, $versionId, CmsDispositionRequestVersion::SUBMITTED, CmsDispositionRequestVersion::UNDER_REVIEW, 'review_started_by', 'review_started_at', 'cms.disposition.review', CmsRecommendationEvent::EVENT_DISPOSITION_REVIEW_STARTED, 'cms.disposition.review_started');
    }

    public function returnVersion(Request $http, int $id, int $versionId, array $data): CmsDispositionRequest
    {
        $actor = $http->user();
        return DB::transaction(function () use ($http, $actor, $id, $versionId, $data): CmsDispositionRequest {
            [$case, $request, $version] = $this->lockedVersion($actor, $id, $versionId);
            throw_unless(in_array($version->status_code, [CmsDispositionRequestVersion::UNDER_REVIEW, CmsDispositionRequestVersion::FOR_DECISION], true), ValidationException::withMessages(['version' => ['This disposition cannot be returned.']]));
            $this->authorizeReviewer($actor, $case, $version, 'cms.disposition.return');
            throw_if(blank($data['returnReason'] ?? null), ValidationException::withMessages(['returnReason' => ['A return reason is required.']]));
            $from = $version->status_code;
            $version->forceFill(['status_code' => CmsDispositionRequestVersion::RETURNED, 'active_slot' => 'ACTIVE', 'returned_by' => $actor->id, 'returned_at' => now(), 'return_reason' => $data['returnReason'], 'lock_version' => $version->lock_version + 1])->save();
            $this->record($http, $case, $request, $version, CmsRecommendationEvent::EVENT_DISPOSITION_RETURNED, 'cms.disposition.returned', $from, CmsDispositionRequestVersion::RETURNED, ['returnReason' => $data['returnReason']]);
            $this->notifyAfterCommit($http, $case, $request, $version, 'returned');
            return $request->fresh($this->relations());
        });
    }

    public function recommend(Request $http, int $id, int $versionId, array $data): CmsDispositionRequest
    {
        $actor = $http->user();
        return DB::transaction(function () use ($http, $actor, $id, $versionId, $data): CmsDispositionRequest {
            [$case, $request, $version] = $this->lockedVersion($actor, $id, $versionId);
            throw_unless($version->status_code === CmsDispositionRequestVersion::UNDER_REVIEW, ValidationException::withMessages(['version' => ['Review is not available.']]));
            $this->authorizeReviewer($actor, $case, $version, 'cms.disposition.review');
            foreach (['readinessAssessment', 'basisAssessment', 'evidenceAssessment', 'riskAssessment'] as $field) throw_if(blank($data[$field] ?? null), ValidationException::withMessages([$field => ['This assessment field is required.']]));
            CmsDispositionReviewAssessment::query()->create(['cms_disposition_request_version_id' => $version->id, 'reviewer_user_id' => $actor->id, 'recommendation_code' => $data['recommendationCode'] ?? 'RECOMMEND_APPROVAL', 'readiness_assessment' => $data['readinessAssessment'], 'basis_assessment' => $data['basisAssessment'], 'evidence_assessment' => $data['evidenceAssessment'], 'risk_assessment' => $data['riskAssessment'], 'conditions_or_observations' => $data['conditionsOrObservations'] ?? 'No additional conditions.', 'reviewed_at' => now()]);
            $version->forceFill(['status_code' => CmsDispositionRequestVersion::FOR_DECISION, 'active_slot' => 'ACTIVE', 'lock_version' => $version->lock_version + 1])->save();
            $this->record($http, $case, $request, $version, CmsRecommendationEvent::EVENT_DISPOSITION_REVIEWED, 'cms.disposition.reviewed', CmsDispositionRequestVersion::UNDER_REVIEW, CmsDispositionRequestVersion::FOR_DECISION);
            $this->notifyAfterCommit($http, $case, $request, $version, 'reviewed');
            return $request->fresh($this->relations());
        });
    }

    public function decide(Request $http, int $id, int $versionId, array $data, string $decision): CmsDispositionRequest
    {
        $actor = $http->user();
        return DB::transaction(function () use ($http, $actor, $id, $versionId, $data, $decision): CmsDispositionRequest {
            [$case, $request, $version] = $this->lockedVersion($actor, $id, $versionId, true);
            throw_unless($version->status_code === CmsDispositionRequestVersion::FOR_DECISION && $version->assessment, ValidationException::withMessages(['version' => ['A disposition decision requires an independent review assessment.']]));
            $this->authorizeDecision($actor, $case, $version, $decision === CmsDispositionRequestVersion::APPROVED ? 'cms.disposition.approve' : 'cms.disposition.reject');
            throw_if(blank($data['decisionComment'] ?? null), ValidationException::withMessages(['decisionComment' => ['A decision comment is required.']]));
            $newStatus = $decision === CmsDispositionRequestVersion::APPROVED ? $request->disposition_code : $version->previous_case_status;
            $snapshot = $this->snapshot($case, $request, $version, $this->readiness($actor, $case));
            $decisionRow = $version->decision()->create(['decision_code' => $decision, 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_comment' => $data['decisionComment'], 'override_reason' => $data['overrideReason'] ?? null, 'previous_case_status' => $case->status_code, 'new_case_status' => $newStatus, 'effective_date' => $data['effectiveDate'] ?? now()->toDateString(), 'final_snapshot' => $snapshot]);
            $version->forceFill(['status_code' => $decision, 'active_slot' => null, 'lock_version' => $version->lock_version + 1])->save();
            $request->forceFill(['current_version_id' => $version->id, 'resolved_version_id' => $version->id, 'resolved_at' => now(), 'lock_version' => $request->lock_version + 1])->save();
            $case->forceFill(['status_code' => $newStatus, 'lock_version' => $case->lock_version + 1])->save();
            $this->record($http, $case, $request, $version, $decision === CmsDispositionRequestVersion::APPROVED ? CmsRecommendationEvent::EVENT_DISPOSITION_APPROVED : CmsRecommendationEvent::EVENT_DISPOSITION_REJECTED, 'cms.disposition.'.strtolower($decision), $version->previous_case_status, $newStatus, ['decisionId' => $decisionRow->id]);
            $this->notifyAfterCommit($http, $case, $request, $version, strtolower($decision));
            return $request->fresh($this->relations());
        });
    }

    public function revise(Request $http, int $id, int $versionId, array $data): CmsDispositionRequest
    {
        $actor = $http->user();
        return DB::transaction(function () use ($http, $actor, $id, $versionId, $data): CmsDispositionRequest {
            [$case, $request, $old] = $this->lockedVersion($actor, $id, $versionId);
            throw_unless($old->status_code === CmsDispositionRequestVersion::RETURNED, ValidationException::withMessages(['version' => ['Only returned versions can be revised.']]));
            $this->assertInitiator($actor, $case);
            // Release the previous active slot before creating the new active
            // revision; the request-level unique active-slot constraint is
            // intentionally enforced inside this transaction.
            $old->update(['active_slot' => null]);
            $new = $request->versions()->create(['version_number' => ((int) $request->versions()->max('version_number')) + 1, 'previous_version_id' => $old->id, 'status_code' => CmsDispositionRequestVersion::DRAFT, 'active_slot' => 'ACTIVE', 'previous_case_status' => $old->previous_case_status, 'case_lock_version' => $case->lock_version, 'prepared_by' => $actor->id, 'revision_reason' => $data['revisionReason'] ?? null, ...$this->narratives($old->toArray())]);
            $request->forceFill(['current_version_id' => $new->id, 'lock_version' => $request->lock_version + 1])->save();
            $this->record($http, $case, $request, $new, CmsRecommendationEvent::EVENT_DISPOSITION_REVISION_CREATED, 'cms.disposition.revision_created', CmsDispositionRequestVersion::RETURNED, CmsDispositionRequestVersion::DRAFT);
            return $request->fresh($this->relations());
        });
    }

    public function linkEvidence(Request $http, int $id, int $versionId, array $data): CmsDispositionRequest
    {
        $actor = $http->user();
        return DB::transaction(function () use ($http, $actor, $id, $versionId, $data): CmsDispositionRequest {
            [$case, $request, $version] = $this->lockedVersion($actor, $id, $versionId);
            $this->assertInitiator($actor, $case);
            throw_unless($version->status_code === CmsDispositionRequestVersion::DRAFT, ValidationException::withMessages(['version' => ['Evidence can only be linked to a draft.']]));
            $documentVersion = DocumentVersion::query()->with('document')->findOrFail((int) ($data['documentVersionId'] ?? 0));
            $this->documentAccess->authorizeView($actor, $documentVersion->document);
            $link = $version->evidenceLinks()->create(['document_id' => $documentVersion->document_id, 'document_version_id' => $documentVersion->id, 'evidence_category' => strtoupper($data['evidenceCategory'] ?? 'DISPOSITION_SUPPORT'), 'title' => $data['title'] ?? $documentVersion->original_file_name, 'description' => $data['description'] ?? null, 'source_or_custodian' => $data['sourceOrCustodian'] ?? null, 'linked_by' => $actor->id, 'linked_at' => now(), 'checksum_sha256' => $documentVersion->checksum_sha256, 'confidentiality_code_snapshot' => $case->recommendation?->confidentiality_code_snapshot]);
            $version->increment('lock_version');
            $request->increment('lock_version');
            $this->record($http, $case, $request, $version->fresh(), CmsRecommendationEvent::EVENT_DISPOSITION_EVIDENCE_LINKED, 'cms.disposition.evidence_linked', CmsDispositionRequestVersion::DRAFT, CmsDispositionRequestVersion::DRAFT, ['evidenceLinkId' => $link->id, 'documentVersionId' => $link->document_version_id, 'checksumSha256' => $link->checksum_sha256]);
            return $request->fresh($this->relations());
        });
    }

    public function removeEvidence(Request $http, int $evidenceId, array $data): CmsDispositionRequest
    {
        $actor = $http->user();
        return DB::transaction(function () use ($http, $actor, $evidenceId, $data): CmsDispositionRequest {
            $evidence = CmsDispositionEvidenceLink::query()->with('version.request')->findOrFail($evidenceId);
            [$case, $request, $version] = $this->lockedVersion($actor, $evidence->version->request->id, $evidence->version->id);
            $this->assertInitiator($actor, $case);
            throw_unless($version->status_code === CmsDispositionRequestVersion::DRAFT && ! $evidence->removed_at, ValidationException::withMessages(['version' => ['Submitted evidence is immutable.']]));
            $evidence->forceFill(['removed_by' => $actor->id, 'removed_at' => now(), 'removal_reason' => $data['reason'] ?? 'Removed from draft.'])->save();
            $version->increment('lock_version');
            $request->increment('lock_version');
            $this->record($http, $case, $request, $version->fresh(), CmsRecommendationEvent::EVENT_DISPOSITION_EVIDENCE_REMOVED, 'cms.disposition.evidence_draft_removed', CmsDispositionRequestVersion::DRAFT, CmsDispositionRequestVersion::DRAFT, ['evidenceLinkId' => $evidence->id]);
            return $request->fresh($this->relations());
        });
    }

    public function downloadEvidence(Request $http, int $evidenceId)
    {
        $actor = $http->user();
        $evidence = CmsDispositionEvidenceLink::query()->whereNull('removed_at')->with(['version.request.case', 'documentVersion.document'])->findOrFail($evidenceId);
        $this->case($actor, $evidence->version->request->cms_recommendation_case_id);
        throw_unless($actor->hasPermission('cms.disposition-evidence.download'), new HttpException(403, 'You cannot download this disposition evidence.'));
        $this->documentAccess->authorizeView($actor, $evidence->documentVersion->document);
        abort_unless(Storage::disk('local')->exists($evidence->documentVersion->storage_path), 404, 'Stored disposition evidence file not found.');
        return Storage::disk('local')->download($evidence->documentVersion->storage_path, $evidence->documentVersion->original_file_name, ['Content-Type' => $evidence->documentVersion->mime_type]);
    }

    public function readiness(User $actor, CmsRecommendationCase $case): array
    {
        $case->loadMissing(['actionPlan.acceptedVersion', 'activeValidationReview', 'unresolvedTargetDateExtensionRequest', 'activeEscalation', 'unresolvedClosureRequest', 'unresolvedDispositionRequest']);
        $checks = [];
        $add = fn (string $code, string $label, bool $passed, string $explanation) => $checks[] = ['code' => $code, 'label' => $label, 'passed' => $passed, 'blocking' => true, 'explanation' => $explanation];
        $add('case_status', 'Recommendation is under monitoring', in_array($case->status_code, [CmsRecommendationCase::STATUS_MONITORING, CmsRecommendationCase::STATUS_PARTIALLY_IMPLEMENTED], true), 'A disposition may only be started from MONITORING or PARTIALLY_IMPLEMENTED.');
        $add('active_validation', 'No active validation review', ! $case->activeValidationReview, 'Complete the active validation review first.');
        $add('extension', 'No unresolved target-date extension', ! $case->unresolvedTargetDateExtensionRequest, 'Resolve the pending extension first.');
        $add('escalation', 'No unresolved escalation', ! $case->activeEscalation, 'Resolve the escalation first.');
        $add('closure', 'No unresolved closure request', ! $case->unresolvedClosureRequest, 'An unresolved closure request already exists.');
        $add('disposition', 'No unresolved disposition request', ! $case->unresolvedDispositionRequest, 'An unresolved disposition request already exists.');
        return ['eligible' => ! collect($checks)->contains(fn ($check) => ! $check['passed']), 'checklist' => $checks, 'caseStatus' => $case->status_code, 'acceptedActionPlan' => $case->actionPlan?->acceptedVersion?->only(['id', 'version_number'])];
    }

    public function permittedActions(User $actor, CmsDispositionRequest $request, ?CmsDispositionRequestVersion $version): array { return $this->actions($actor, $request, false); }

    private function transition(Request $http, int $id, int $versionId, string $from, string $to, string $userField, string $timeField, string $permission, string $event, string $action): CmsDispositionRequest
    {
        $actor = $http->user();
        return DB::transaction(function () use ($http, $actor, $id, $versionId, $from, $to, $userField, $timeField, $permission, $event, $action): CmsDispositionRequest {
            [$case, $request, $version] = $this->lockedVersion($actor, $id, $versionId);
            throw_unless($version->status_code === $from, ValidationException::withMessages(['version' => ['Invalid disposition transition.']]));
            $this->authorizeReviewer($actor, $case, $version, $permission);
            $version->forceFill(['status_code' => $to, 'active_slot' => 'ACTIVE', $userField => $actor->id, $timeField => now(), 'lock_version' => $version->lock_version + 1])->save();
            $this->record($http, $case, $request, $version, $event, $action, $from, $to);
            $this->notifyAfterCommit($http, $case, $request, $version, 'review_started');
            return $request->fresh($this->relations());
        });
    }

    private function case(User $actor, int $id, bool $lock = false): CmsRecommendationCase { return $this->scope->resolveVisibleCase($actor, $id, 'cms.disposition.view', $lock); }

    private function lockedVersion(User $actor, int $id, int $versionId, bool $lockCase = false): array
    {
        $request = CmsDispositionRequest::query()->find($id);
        throw_unless($request, new HttpException(404, 'The disposition request is unavailable.'));
        $case = $this->case($actor, $request->cms_recommendation_case_id, $lockCase);
        $locked = CmsDispositionRequest::query()->with($this->relations())->lockForUpdate()->findOrFail($id);
        $version = $locked->versions()->lockForUpdate()->findOrFail($versionId);
        return [$case, $locked, $version];
    }

    private function assertInitiator(User $actor, CmsRecommendationCase $case): void
    {
        throw_unless($this->canInitiate($actor, $case), new HttpException(403, 'You cannot prepare this disposition request.'));
    }

    private function canInitiate(User $actor, CmsRecommendationCase $case): bool
    {
        return $actor->is_active && $actor->hasPermission('cms.disposition.request') && ($actor->office_id === $case->lead_responsible_office_id || $case->currentAssignment?->user_id === $actor->id || $actor->hasGlobalEngagementAccess());
    }

    private function authorizeReviewer(User $actor, CmsRecommendationCase $case, CmsDispositionRequestVersion $version, string $permission): void
    {
        throw_unless($actor->is_active && $actor->hasPermission($permission), new HttpException(403, 'You are not authorised for this disposition review action.'));
        throw_if(in_array($actor->id, array_filter([$version->prepared_by, $version->submitted_by, $version->review_started_by]), true) || $actor->office_id === $case->lead_responsible_office_id, new HttpException(403, 'Separation of duties prevents this review action.'));
    }

    private function authorizeDecision(User $actor, CmsRecommendationCase $case, CmsDispositionRequestVersion $version, string $permission): void
    {
        throw_unless($actor->is_active && $actor->hasPermission($permission), new HttpException(403, 'You do not have permission to decide this disposition.'));
        throw_if(in_array($actor->id, array_filter([$version->prepared_by, $version->submitted_by, $version->review_started_by, $version->assessment?->reviewer_user_id]), true), new HttpException(403, 'Separation of duties prevents this decision.'));
    }

    private function assertCaseLock(CmsRecommendationCase $case, array $data): void { if (array_key_exists('lockVersion', $data) && (int) $data['lockVersion'] !== (int) $case->lock_version) throw ValidationException::withMessages(['lockVersion' => ['This recommendation has changed. Refresh and retry.']]); }
    private function assertVersionLock(CmsDispositionRequestVersion $version, array $data): void { if (array_key_exists('lockVersion', $data) && (int) $data['lockVersion'] !== (int) $version->lock_version) throw ValidationException::withMessages(['lockVersion' => ['This version has changed. Refresh and retry.']]); }

    private function assertComplete(string $type, array $data, array $ready): void
    {
        throw_unless($ready['eligible'], ValidationException::withMessages(['readiness' => ['Blocking readiness criteria remain.']]));
        foreach (['disposition_summary', 'basis_and_criteria', 'risk_impact_assessment', 'management_position', 'responsible_office_confirmation', 'no_additional_evidence_explanation'] as $field) if (blank($data[$field] ?? null)) throw ValidationException::withMessages([$this->camel($field) => ['This field is required before submission.']]);
        $typeField = $type === CmsDispositionRequest::ACCEPTED_RISK ? 'accepted_risk_rationale' : 'no_longer_applicable_basis';
        if (blank($data[$typeField] ?? null)) throw ValidationException::withMessages([$this->camel($typeField) => ['This disposition-specific basis is required before submission.']]);
    }

    private function narratives(array $data): array
    {
        $map = ['dispositionSummary' => 'disposition_summary', 'basisAndCriteria' => 'basis_and_criteria', 'riskImpactAssessment' => 'risk_impact_assessment', 'managementPosition' => 'management_position', 'responsibleOfficeConfirmation' => 'responsible_office_confirmation', 'acceptedRiskRationale' => 'accepted_risk_rationale', 'riskTreatmentAndMonitoring' => 'risk_treatment_and_monitoring', 'noLongerApplicableBasis' => 'no_longer_applicable_basis', 'transitionOrRecordsImpact' => 'transition_or_records_impact', 'residualRiskStatement' => 'residual_risk_statement', 'noAdditionalEvidenceExplanation' => 'no_additional_evidence_explanation', 'requestedEffectiveDate' => 'requested_effective_date'];
        return collect($map)->filter(fn ($column, $key) => array_key_exists($key, $data))->mapWithKeys(fn ($column, $key) => [$column => $data[$key]])->all();
    }

    private function camel(string $value): string { return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value)))); }

    private function snapshot(CmsRecommendationCase $case, CmsDispositionRequest $request, CmsDispositionRequestVersion $version, array $ready): array { return ['caseId' => $case->id, 'caseStatus' => $case->status_code, 'previousCaseStatus' => $version->previous_case_status, 'dispositionCode' => $request->disposition_code, 'requestId' => $request->id, 'versionId' => $version->id, 'readiness' => $ready['checklist'], 'capturedAt' => now()->toIso8601String()]; }

    private function relations(): array { return ['case.recommendation', 'case.leadResponsibleOffice', 'case.currentAssignment.user', 'creator', 'versions.previousVersion', 'versions.preparer', 'versions.submitter', 'versions.reviewStarter', 'versions.returner', 'versions.assessment.reviewer', 'versions.decision.decider', 'versions.evidenceLinks.documentVersion', 'currentVersion.assessment.reviewer', 'currentVersion.decision.decider']; }

    private function familyActions(User $actor, CmsRecommendationCase $case, ?CmsDispositionRequest $request): array { return $request ? $this->actions($actor, $request, false) : ($this->canInitiate($actor, $case) && $this->readiness($actor, $case)['eligible'] ? ['create'] : []); }

    private function actions(User $actor, CmsDispositionRequest $request, bool $unused = false): array
    {
        $version = $request->currentVersion;
        if (! $version) return [];
        $case = $request->case;
        $actions = [];
        if ($version->status_code === CmsDispositionRequestVersion::DRAFT && $this->canInitiate($actor, $case)) { if ($actor->hasPermission('cms.disposition.update')) $actions[] = 'update'; if ($actor->hasPermission('cms.disposition.submit')) $actions[] = 'submit'; if ($actor->hasPermission('cms.disposition-evidence.upload')) $actions[] = 'upload-evidence'; }
        if ($version->status_code === CmsDispositionRequestVersion::RETURNED && $this->canInitiate($actor, $case) && $actor->hasPermission('cms.disposition.revise')) $actions[] = 'revise';
        if ($version->status_code === CmsDispositionRequestVersion::SUBMITTED && $actor->hasPermission('cms.disposition.review') && $this->canReview($actor, $case, $version)) $actions[] = 'start-review';
        if ($version->status_code === CmsDispositionRequestVersion::UNDER_REVIEW && $this->canReview($actor, $case, $version)) { if ($actor->hasPermission('cms.disposition.return')) $actions[] = 'return'; if ($actor->hasPermission('cms.disposition.review')) $actions[] = 'recommend'; }
        if ($version->status_code === CmsDispositionRequestVersion::FOR_DECISION && $this->canDecision($actor, $case, $version)) { if ($actor->hasPermission('cms.disposition.return')) $actions[] = 'return'; if ($actor->hasPermission('cms.disposition.approve')) $actions[] = 'approve'; if ($actor->hasPermission('cms.disposition.reject')) $actions[] = 'reject'; }
        $version->setAttribute('available_actions', $actions); $request->setAttribute('available_actions', $actions);
        return $actions;
    }

    private function canReview(User $actor, CmsRecommendationCase $case, CmsDispositionRequestVersion $version): bool { return $actor->is_active && ! in_array($actor->id, array_filter([$version->prepared_by, $version->submitted_by, $version->review_started_by]), true) && $actor->office_id !== $case->lead_responsible_office_id; }
    private function canDecision(User $actor, CmsRecommendationCase $case, CmsDispositionRequestVersion $version): bool { return $actor->is_active && $actor->hasAnyPermission(['cms.disposition.approve', 'cms.disposition.reject']) && ! in_array($actor->id, array_filter([$version->prepared_by, $version->submitted_by, $version->review_started_by, $version->assessment?->reviewer_user_id]), true); }
    private function initiatorTypes(User $actor, CmsRecommendationCase $case): array { $types = []; if ($actor->office_id === $case->lead_responsible_office_id) $types[] = CmsDispositionRequest::INITIATOR_RESPONSIBLE_OFFICE; if ($case->currentAssignment?->user_id === $actor->id) $types[] = CmsDispositionRequest::INITIATOR_COMPLIANCE_MONITOR; return $types; }
    private function defaultInitiator(User $actor, CmsRecommendationCase $case): string { return $actor->office_id === $case->lead_responsible_office_id ? CmsDispositionRequest::INITIATOR_RESPONSIBLE_OFFICE : CmsDispositionRequest::INITIATOR_COMPLIANCE_MONITOR; }

    private function record(Request $http, CmsRecommendationCase $case, CmsDispositionRequest $request, CmsDispositionRequestVersion $version, string $event, string $action, ?string $previous, ?string $new, array $metadata = []): void
    {
        $payload = ['caseId' => $case->id, 'dispositionRequestId' => $request->id, 'dispositionVersionId' => $version->id, 'dispositionCode' => $request->disposition_code, 'versionNumber' => $version->version_number, 'previousStatus' => $previous, 'newStatus' => $new, ...$metadata];
        CmsRecommendationEvent::query()->firstOrCreate(['idempotency_key' => "cms.disposition.{$version->id}.{$event}.{$version->lock_version}"], ['cms_recommendation_case_id' => $case->id, 'cms_recommendation_id' => $case->cms_recommendation_id, 'event_code' => $event, 'source_module' => 'CMS', 'actor_id' => $http->user()->id, 'previous_status' => $previous, 'new_status' => $new, 'event_metadata' => $payload, 'ip_address' => $http->ip(), 'user_agent' => mb_substr((string) $http->userAgent(), 0, 1000), 'created_at' => now()]);
        ActivityRecorder::record($http, $action, "CMS disposition {$action}.", metadata: ['module' => 'CMS', 'recordType' => 'CMS_DISPOSITION', 'recordId' => $request->id, 'recordCode' => $version->display_code, ...$payload]);
        DB::table('audit_logs')->insert(['user_id' => $http->user()->id, 'action' => $action, 'auditable_type' => CmsDispositionRequestVersion::class, 'auditable_id' => $version->id, 'old_values' => $previous ? json_encode(['status' => $previous]) : null, 'new_values' => json_encode(['status' => $new, ...$metadata]), 'ip_address' => $http->ip(), 'user_agent' => mb_substr((string) $http->userAgent(), 0, 1000), 'metadata' => json_encode(['module' => 'CMS', 'caseId' => $case->id, 'dispositionRequestId' => $request->id]), 'created_at' => now()]);
    }

    private function notifyAfterCommit(Request $http, CmsRecommendationCase $case, CmsDispositionRequest $request, CmsDispositionRequestVersion $version, string $event): void
    {
        $case->loadMissing('currentAssignment.user', 'actionPlan.acceptedVersion', 'recommendation');
        $recipients = collect([$case->currentAssignment?->user_id, $version->submitted_by, $case->actionPlan?->acceptedVersion?->focal_user_id])->filter()->unique();
        if (in_array($event, ['reviewed', 'approved', 'rejected'], true)) $recipients = $recipients->merge(User::query()->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'cms.disposition.review'))->pluck('id'));
        $recipients = $recipients->filter(fn ($id) => (int) $id !== (int) $http->user()->id);
        if ($recipients->isEmpty()) return;
        $labels = ['submitted' => ['CMS_DISPOSITION_SUBMITTED', 'Disposition submitted', 'A CMS disposition request was submitted for independent review.'], 'review_started' => ['CMS_DISPOSITION_REVIEW_STARTED', 'Disposition review started', 'A CMS disposition request entered independent review.'], 'returned' => ['CMS_DISPOSITION_RETURNED', 'Disposition returned', 'A CMS disposition request was returned for revision.'], 'reviewed' => ['CMS_DISPOSITION_REVIEWED', 'Disposition assessed', 'An independent disposition assessment is ready for decision.'], 'approved' => ['CMS_DISPOSITION_APPROVED', 'Disposition approved', "The {$request->disposition_code} disposition was approved."], 'rejected' => ['CMS_DISPOSITION_REJECTED', 'Disposition rejected', "The {$request->disposition_code} disposition was rejected; the prior case status remains authoritative."]];
        [$type, $title, $message] = $labels[$event] ?? ['CMS_DISPOSITION_UPDATED', 'Disposition updated', 'A CMS disposition request was updated.'];
        DB::afterCommit(fn () => $this->notifications->send($recipients, ['actorId' => $http->user()->id, 'type' => $type, 'category' => 'CMS_DISPOSITION', 'priority' => in_array($event, ['approved', 'rejected'], true) ? 'HIGH' : 'NORMAL', 'moduleCode' => 'CMS', 'title' => $title, 'message' => $message, 'actionUrl' => "/compliance-management/recommendations/{$case->id}", 'actionLabel' => 'Open recommendation', 'subjectType' => CmsDispositionRequest::class, 'subjectId' => $request->id, 'subjectCode' => $version->display_code, 'dedupeKey' => "cms-disposition:{$request->id}:{$version->id}:{$event}", 'metadata' => ['caseId' => $case->id, 'dispositionCode' => $request->disposition_code]]));
    }
}
