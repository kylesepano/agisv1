<?php

namespace App\Services\Cms;

use App\Models\CmsActionPlanVersion;
use App\Models\CmsDispositionDecision;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\CmsReopeningDecision;
use App\Models\CmsReopeningEvidenceLink;
use App\Models\CmsReopeningRequest;
use App\Models\CmsReopeningRequestVersion;
use App\Models\CmsReopeningReviewAssessment;
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

/** Owns the controlled CMS-10A reopening decision workflow. */
class CmsReopeningService
{
    private const REASONS = [
        'CLOSED' => [
            'NEW_MATERIAL_EVIDENCE', 'IMPLEMENTATION_FAILURE', 'CONTROL_FAILURE_RECURRENCE',
            'MATERIAL_CONDITION_CHANGED', 'MANAGEMENT_COMMITMENT_BREACHED', 'OTHER',
        ],
        'ACCEPTED_RISK' => [
            'ACCEPTED_RISK_EXPIRED', 'ACCEPTED_RISK_WITHDRAWN', 'ACCEPTED_RISK_CONDITIONS_BREACHED',
            'MATERIAL_CONDITION_CHANGED', 'LEGAL_OR_POLICY_BASIS_CHANGED', 'OTHER',
        ],
        'NO_LONGER_APPLICABLE' => [
            'NO_LONGER_APPLICABLE_BASIS_INVALIDATED', 'PROCESS_OR_PROGRAM_REACTIVATED',
            'SYSTEM_OR_ASSET_REACTIVATED', 'SUPERSEDING_AUTHORITY_CHANGED',
            'LEGAL_OR_POLICY_BASIS_CHANGED', 'MATERIAL_CONDITION_CHANGED', 'OTHER',
        ],
    ];

    private const REASON_LABELS = [
        'NEW_MATERIAL_EVIDENCE' => 'New material evidence',
        'IMPLEMENTATION_FAILURE' => 'Implementation failure',
        'CONTROL_FAILURE_RECURRENCE' => 'Control failure recurrence',
        'MATERIAL_CONDITION_CHANGED' => 'Material condition changed',
        'MANAGEMENT_COMMITMENT_BREACHED' => 'Management commitment breached',
        'ACCEPTED_RISK_EXPIRED' => 'Accepted risk expired',
        'ACCEPTED_RISK_WITHDRAWN' => 'Accepted risk withdrawn',
        'ACCEPTED_RISK_CONDITIONS_BREACHED' => 'Accepted-risk conditions breached',
        'NO_LONGER_APPLICABLE_BASIS_INVALIDATED' => 'No-longer-applicable basis invalidated',
        'PROCESS_OR_PROGRAM_REACTIVATED' => 'Process or program reactivated',
        'SYSTEM_OR_ASSET_REACTIVATED' => 'System or asset reactivated',
        'SUPERSEDING_AUTHORITY_CHANGED' => 'Superseding authority changed',
        'LEGAL_OR_POLICY_BASIS_CHANGED' => 'Legal or policy basis changed',
        'OTHER' => 'Other material professional basis',
    ];

    public function __construct(
        private readonly CmsRecommendationScopeService $scope,
        private readonly NotificationService $notifications,
        private readonly DocumentAccessService $documentAccess,
    ) {}

    public function forRecommendation(User $actor, int $caseId): array
    {
        $case = $this->case($actor, $caseId);
        $requests = CmsReopeningRequest::query()
            ->where('cms_recommendation_case_id', $case->id)
            ->with($this->relations())
            ->orderByDesc('request_sequence')->get();
        $requests->each(fn (CmsReopeningRequest $request) => $this->permittedActions($actor, $request, $request->currentVersion));

        return ['case' => $case, 'requests' => $requests, 'permittedActions' => $this->familyActions($actor, $case, $requests->first())];
    }

    public function options(User $actor, int $caseId): array
    {
        $case = $this->case($actor, $caseId);
        $readiness = $this->readiness($actor, $case);
        $active = CmsReopeningRequest::query()->where('cms_recommendation_case_id', $case->id)->whereNull('resolved_at')->with('currentVersion')->first();

        return [
            'case' => $case,
            'readiness' => $readiness,
            'canCreate' => $readiness['eligible'] && ! $active && $this->canInitiate($actor, $case),
            'activeRequest' => $active,
            'sourceTerminalStatus' => $case->status_code,
            'sourceDecision' => $this->sourcePayload($readiness['source']),
            'reasons' => collect(self::REASONS[$case->status_code] ?? [])->map(fn (string $code): array => ['code' => $code, 'label' => self::REASON_LABELS[$code] ?? $code])->values()->all(),
            'destinations' => $this->destinations($case),
            'initiatorTypes' => $this->initiatorTypes($actor, $case),
        ];
    }

    public function show(User $actor, int $id): array
    {
        $reference = CmsReopeningRequest::query()->find($id);
        throw_unless($reference, new HttpException(404, 'The reopening request is unavailable.'));
        $case = $this->case($actor, $reference->cms_recommendation_case_id);
        $request = CmsReopeningRequest::query()->with($this->relations())->findOrFail($id);
        $this->permittedActions($actor, $request, $request->currentVersion);

        return ['case' => $case, 'request' => $request];
    }

    public function create(Request $http, int $caseId, array $data): CmsReopeningRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($http, $actor, $caseId, $data): CmsReopeningRequest {
            $case = $this->case($actor, $caseId, true);
            $this->assertInitiator($actor, $case);
            $this->assertCaseLock($case, $data);
            $source = $this->resolveSource($case);
            $this->assertBaseReadiness($case, $source);
            $active = CmsReopeningRequest::query()->where('cms_recommendation_case_id', $case->id)->whereNull('resolved_at')->lockForUpdate()->first();
            throw_if($active, ValidationException::withMessages(['reopening' => ['An unresolved reopening request already exists.']]));
            $sequence = (int) CmsReopeningRequest::query()->where('cms_recommendation_case_id', $case->id)->lockForUpdate()->max('request_sequence') + 1;
            $request = CmsReopeningRequest::create([
                'cms_recommendation_case_id' => $case->id,
                'request_sequence' => $sequence,
                'initiator_type_code' => $data['initiatorTypeCode'] ?? $this->defaultInitiator($actor, $case),
                'created_by' => $actor->id,
                'source_terminal_status' => $case->status_code,
                'source_closure_decision_id' => $source['closureDecision']?->id,
                'source_disposition_decision_id' => $source['dispositionDecision']?->id,
                'lock_version' => 1,
            ]);
            $version = $request->versions()->create([
                'version_number' => 1,
                'status_code' => CmsReopeningRequestVersion::DRAFT,
                'active_slot' => 'ACTIVE',
                'reopening_reason_code' => strtoupper((string) ($data['reopeningReasonCode'] ?? '')),
                'prepared_by' => $actor->id,
                'lock_version' => 1,
                ...$this->narratives($data),
            ]);
            $request->forceFill(['current_version_id' => $version->id])->save();
            $this->record($http, $case, $request, $version, CmsRecommendationEvent::EVENT_REOPENING_REQUEST_CREATED, 'cms.reopening.created', null, CmsReopeningRequestVersion::DRAFT);

            return $request->fresh($this->relations());
        });
    }

    public function update(Request $http, int $id, int $versionId, array $data): CmsReopeningRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($http, $actor, $id, $versionId, $data): CmsReopeningRequest {
            [$case, $request, $version] = $this->lockedVersion($actor, $id, $versionId);
            $this->assertVersionLock($version, $data);
            throw_unless($version->status_code === CmsReopeningRequestVersion::DRAFT, ValidationException::withMessages(['version' => ['Only reopening drafts can be edited.']]));
            $this->assertInitiator($actor, $case);
            $this->validateReason($case->status_code, array_key_exists('reopeningReasonCode', $data) ? $data['reopeningReasonCode'] : $version->reopening_reason_code, [...$version->toArray(), ...$data]);
            $version->update([...$this->narratives($data), 'lock_version' => $version->lock_version + 1]);
            $request->increment('lock_version');
            $this->record($http, $case, $request, $version->fresh(), CmsRecommendationEvent::EVENT_REOPENING_REQUEST_UPDATED, 'cms.reopening.updated', CmsReopeningRequestVersion::DRAFT, CmsReopeningRequestVersion::DRAFT);

            return $request->fresh($this->relations());
        });
    }

    public function submit(Request $http, int $id, int $versionId, array $data): CmsReopeningRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($http, $actor, $id, $versionId, $data): CmsReopeningRequest {
            [$case, $request, $version] = $this->lockedVersion($actor, $id, $versionId, true);
            $this->assertVersionLock($version, $data);
            throw_unless($version->status_code === CmsReopeningRequestVersion::DRAFT, ValidationException::withMessages(['version' => ['Only a draft can be submitted.']]));
            $this->assertInitiator($actor, $case);
            $previousVersionStatus = $version->status_code;
            $source = $this->resolveSource($case);
            $this->assertSourceMatches($request, $case, $source);
            $merged = [...$version->toArray(), ...$this->narratives($data)];
            $this->assertComplete($case, $request, $version, $merged, $source);
            $version->forceFill([
                'status_code' => CmsReopeningRequestVersion::SUBMITTED,
                'active_slot' => 'ACTIVE',
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'submission_snapshot' => $this->snapshot($case, $request, $version, $source),
                'lock_version' => $version->lock_version + 1,
                ...$this->narratives($data),
            ])->save();
            $request->increment('lock_version');
            $this->record($http, $case, $request, $version, CmsRecommendationEvent::EVENT_REOPENING_REQUEST_SUBMITTED, 'cms.reopening.submitted', $previousVersionStatus, CmsReopeningRequestVersion::SUBMITTED);
            $this->notifyAfterCommit($http, $case, $request, $version, 'submitted');

            return $request->fresh($this->relations());
        });
    }

    public function startReview(Request $http, int $id, int $versionId, array $data): CmsReopeningRequest
    {
        return $this->transition($http, $id, $versionId, CmsReopeningRequestVersion::SUBMITTED, CmsReopeningRequestVersion::UNDER_REVIEW, 'review_started_by', 'review_started_at', 'cms.reopening.review', CmsRecommendationEvent::EVENT_REOPENING_REVIEW_STARTED, 'cms.reopening.review_started');
    }

    public function returnVersion(Request $http, int $id, int $versionId, array $data): CmsReopeningRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($http, $actor, $id, $versionId, $data): CmsReopeningRequest {
            [$case, $request, $version] = $this->lockedVersion($actor, $id, $versionId);
            throw_unless(in_array($version->status_code, [CmsReopeningRequestVersion::UNDER_REVIEW, CmsReopeningRequestVersion::FOR_DECISION], true), ValidationException::withMessages(['version' => ['This reopening version cannot be returned.']]));
            $this->authorizeReviewer($actor, $case, $version, 'cms.reopening.return');
            throw_if(blank($data['returnReason'] ?? null), ValidationException::withMessages(['returnReason' => ['A return reason is required.']]));
            $from = $version->status_code;
            $version->forceFill(['status_code' => CmsReopeningRequestVersion::RETURNED, 'active_slot' => 'ACTIVE', 'returned_by' => $actor->id, 'returned_at' => now(), 'return_reason' => $data['returnReason'], 'lock_version' => $version->lock_version + 1])->save();
            $this->record($http, $case, $request, $version, CmsRecommendationEvent::EVENT_REOPENING_REQUEST_RETURNED, 'cms.reopening.returned', $from, CmsReopeningRequestVersion::RETURNED, ['returnReason' => $data['returnReason']]);
            $this->notifyAfterCommit($http, $case, $request, $version, 'returned');

            return $request->fresh($this->relations());
        });
    }

    public function recommend(Request $http, int $id, int $versionId, array $data): CmsReopeningRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($http, $actor, $id, $versionId, $data): CmsReopeningRequest {
            [$case, $request, $version] = $this->lockedVersion($actor, $id, $versionId);
            throw_unless($version->status_code === CmsReopeningRequestVersion::UNDER_REVIEW, ValidationException::withMessages(['version' => ['Reopening review is not available.']]));
            $this->authorizeReviewer($actor, $case, $version, 'cms.reopening.review');
            $fields = ['sourceDecisionIntegrityAssessment', 'newEvidenceOrChangedConditionAssessment', 'materialityAssessment', 'riskAssessment', 'destinationStatusAssessment', 'actionPlanRequirementAssessment', 'assignmentAndMonitoringAssessment', 'evidenceSufficiencyAssessment', 'recommendationRationale'];
            foreach ($fields as $field) {
                throw_if(blank($data[$field] ?? null), ValidationException::withMessages([$field => ['This assessment field is required.']]));
            }
            $recommendation = strtoupper((string) ($data['recommendationCode'] ?? ''));
            throw_unless(in_array($recommendation, ['RECOMMEND_APPROVAL', 'RECOMMEND_REJECTION'], true), ValidationException::withMessages(['recommendationCode' => ['Select a valid reopening recommendation.']]));
            CmsReopeningReviewAssessment::create([
                'cms_reopening_request_version_id' => $version->id,
                'reviewer_user_id' => $actor->id,
                'recommendation_code' => $recommendation,
                'source_decision_integrity_assessment' => $data['sourceDecisionIntegrityAssessment'],
                'new_evidence_or_changed_condition_assessment' => $data['newEvidenceOrChangedConditionAssessment'],
                'materiality_assessment' => $data['materialityAssessment'],
                'risk_assessment' => $data['riskAssessment'],
                'destination_status_assessment' => $data['destinationStatusAssessment'],
                'action_plan_requirement_assessment' => $data['actionPlanRequirementAssessment'],
                'assignment_and_monitoring_assessment' => $data['assignmentAndMonitoringAssessment'],
                'evidence_sufficiency_assessment' => $data['evidenceSufficiencyAssessment'],
                'recommendation_rationale' => $data['recommendationRationale'],
                'conditions_or_observations' => $data['conditionsOrObservations'] ?? null,
                'reviewed_at' => now(),
            ]);
            $version->forceFill(['status_code' => CmsReopeningRequestVersion::FOR_DECISION, 'active_slot' => 'ACTIVE', 'lock_version' => $version->lock_version + 1])->save();
            $this->record($http, $case, $request, $version, CmsRecommendationEvent::EVENT_REOPENING_REVIEW_COMPLETED, 'cms.reopening.review_completed', CmsReopeningRequestVersion::UNDER_REVIEW, CmsReopeningRequestVersion::FOR_DECISION, ['recommendationCode' => $recommendation]);
            $this->notifyAfterCommit($http, $case, $request, $version, 'reviewed');

            return $request->fresh($this->relations());
        });
    }

    public function decide(Request $http, int $id, int $versionId, array $data, string $decision): CmsReopeningRequest
    {
        $actor = $http->user();
        $decision = strtoupper($decision);

        return DB::transaction(function () use ($http, $actor, $id, $versionId, $data, $decision): CmsReopeningRequest {
            [$case, $request, $version] = $this->lockedVersion($actor, $id, $versionId, true);
            $version->loadMissing('assessment');
            throw_unless($version->status_code === CmsReopeningRequestVersion::FOR_DECISION && $version->assessment, ValidationException::withMessages(['version' => ['A final reopening decision requires an independent assessment.']]));
            throw_unless(in_array($decision, ['APPROVED', 'REJECTED'], true), ValidationException::withMessages(['decision' => ['Select a valid reopening decision.']]));
            $this->authorizeDecision($actor, $case, $version, $decision === 'APPROVED' ? 'cms.reopening.approve' : 'cms.reopening.reject');
            throw_if(blank($data['decisionComment'] ?? null), ValidationException::withMessages(['decisionComment' => ['A decision comment is required.']]));
            $recommendation = $version->assessment->recommendation_code;
            if (($decision === 'APPROVED' && $recommendation === 'RECOMMEND_REJECTION') || ($decision === 'REJECTED' && $recommendation === 'RECOMMEND_APPROVAL')) {
                throw_if(blank($data['overrideReason'] ?? null), ValidationException::withMessages(['overrideReason' => ['An override reason is required when the final decision differs from the independent recommendation.']]));
            }
            $source = $this->resolveSource($case);
            $this->assertSourceMatches($request, $case, $source);
            $destination = $decision === 'APPROVED' ? strtoupper((string) ($data['approvedDestinationStatus'] ?? $version->proposed_destination_code)) : null;
            if ($decision === 'APPROVED') {
                $this->assertDestination($case, $version, $destination);
            }
            $priorCycle = (int) ($case->active_cycle_number ?: 1);
            $plan = $case->actionPlan?->acceptedVersion;
            $retained = $decision === 'APPROVED' && $destination === CmsRecommendationCase::STATUS_MONITORING && $plan?->status_code === CmsActionPlanVersion::STATUS_ACCEPTED;
            $newCycle = $decision === 'APPROVED' ? $priorCycle + 1 : null;
            $newStatus = $decision === 'APPROVED' ? $destination : $case->status_code;
            $previousCaseStatus = $case->status_code;
            $snapshot = $this->decisionSnapshot($case, $request, $version, $source, $decision, $destination, $priorCycle, $newCycle);
            $row = CmsReopeningDecision::create([
                'cms_reopening_request_version_id' => $version->id,
                'decision_code' => $decision,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_comment' => $data['decisionComment'],
                'override_reason' => $data['overrideReason'] ?? null,
                'source_terminal_status' => $previousCaseStatus,
                'approved_destination_status' => $destination,
                'previous_active_cycle_number' => $priorCycle,
                'new_active_cycle_number' => $newCycle,
                'existing_action_plan_retained' => $retained,
                'retained_action_plan_version_id' => $retained ? $plan->id : null,
                'new_action_plan_required' => $decision === 'APPROVED' && $destination === CmsRecommendationCase::STATUS_FOR_ACTION_PLAN,
                'assignment_follow_up_required' => $decision === 'APPROVED' && $destination === CmsRecommendationCase::STATUS_FOR_ACTION_PLAN && ! $case->currentAssignment,
                'target_date_follow_up_required' => $decision === 'APPROVED' && $destination === CmsRecommendationCase::STATUS_FOR_ACTION_PLAN,
                'reopening_effective_date' => $data['reopeningEffectiveDate'] ?? now()->toDateString(),
                'final_snapshot' => $snapshot,
            ]);
            $version->forceFill(['status_code' => $decision, 'active_slot' => null, 'lock_version' => $version->lock_version + 1])->save();
            $request->forceFill(['current_version_id' => $version->id, 'resolved_version_id' => $version->id, 'resolved_at' => now(), 'lock_version' => $request->lock_version + 1])->save();
            if ($decision === 'APPROVED') {
                $case->forceFill(['status_code' => $newStatus, 'active_cycle_number' => $newCycle, 'reopening_count' => ((int) $case->reopening_count) + 1, 'last_reopened_at' => now(), 'last_reopened_by' => $actor->id, 'last_reopening_decision_id' => $row->id, 'lock_version' => $case->lock_version + 1])->save();
            }
            $event = $decision === 'APPROVED' ? CmsRecommendationEvent::EVENT_REOPENING_APPROVED : CmsRecommendationEvent::EVENT_REOPENING_REJECTED;
            $this->record($http, $case, $request, $version, $event, 'cms.reopening.'.strtolower($decision), $previousCaseStatus, $newStatus, ['decisionId' => $row->id, 'approvedDestinationStatus' => $destination, 'previousActiveCycleNumber' => $priorCycle, 'newActiveCycleNumber' => $newCycle]);
            if ($decision === 'APPROVED') {
                $this->record($http, $case, $request, $version, CmsRecommendationEvent::EVENT_RECOMMENDATION_REOPENED, 'cms.recommendation.reopened', $request->source_terminal_status, $newStatus, ['decisionId' => $row->id]);
                $this->record($http, $case, $request, $version, CmsRecommendationEvent::EVENT_ACTIVE_CYCLE_STARTED, 'cms.active_cycle.started', $request->source_terminal_status, $newStatus, ['decisionId' => $row->id, 'activeCycleNumber' => $newCycle]);
            }
            $this->notifyAfterCommit($http, $case, $request, $version, strtolower($decision));

            return $request->fresh($this->relations());
        });
    }

    public function revise(Request $http, int $id, int $versionId, array $data): CmsReopeningRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($http, $actor, $id, $versionId, $data): CmsReopeningRequest {
            [$case, $request, $old] = $this->lockedVersion($actor, $id, $versionId);
            throw_unless($old->status_code === CmsReopeningRequestVersion::RETURNED, ValidationException::withMessages(['version' => ['Only returned reopening versions can be revised.']]));
            $this->assertInitiator($actor, $case);
            $old->update(['active_slot' => null]);
            $new = $request->versions()->create(['version_number' => ((int) $request->versions()->max('version_number')) + 1, 'previous_version_id' => $old->id, 'status_code' => CmsReopeningRequestVersion::DRAFT, 'active_slot' => 'ACTIVE', 'reopening_reason_code' => $old->reopening_reason_code, 'prepared_by' => $actor->id, 'revision_reason' => $data['revisionReason'] ?? null, 'lock_version' => 1, ...$this->narratives($old->toArray())]);
            foreach ($old->activeEvidenceLinks as $evidence) {
                $new->evidenceLinks()->create($evidence->only(['document_id', 'document_version_id', 'evidence_category', 'title', 'description', 'source_or_custodian', 'linked_by', 'linked_at', 'checksum_sha256', 'confidentiality_code_snapshot']));
            }
            $request->forceFill(['current_version_id' => $new->id, 'lock_version' => $request->lock_version + 1])->save();
            $this->record($http, $case, $request, $new, CmsRecommendationEvent::EVENT_REOPENING_REVISION_CREATED, 'cms.reopening.revision_created', CmsReopeningRequestVersion::RETURNED, CmsReopeningRequestVersion::DRAFT);

            return $request->fresh($this->relations());
        });
    }

    public function linkEvidence(Request $http, int $id, int $versionId, array $data): CmsReopeningRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($http, $actor, $id, $versionId, $data): CmsReopeningRequest {
            [$case, $request, $version] = $this->lockedVersion($actor, $id, $versionId);
            $this->assertInitiator($actor, $case);
            throw_unless($version->status_code === CmsReopeningRequestVersion::DRAFT, ValidationException::withMessages(['version' => ['Evidence can only be linked to a reopening draft.']]));
            $documentVersion = DocumentVersion::query()->with('document')->findOrFail((int) ($data['documentVersionId'] ?? 0));
            $this->documentAccess->authorizeView($actor, $documentVersion->document);
            $link = $version->evidenceLinks()->create(['document_id' => $documentVersion->document_id, 'document_version_id' => $documentVersion->id, 'evidence_category' => strtoupper($data['evidenceCategory'] ?? 'REOPENING_SUPPORT'), 'title' => $data['title'] ?? $documentVersion->original_file_name, 'description' => $data['description'] ?? null, 'source_or_custodian' => $data['sourceOrCustodian'] ?? null, 'linked_by' => $actor->id, 'linked_at' => now(), 'checksum_sha256' => $documentVersion->checksum_sha256, 'confidentiality_code_snapshot' => $case->recommendation?->confidentiality_code_snapshot]);
            $version->increment('lock_version');
            $request->increment('lock_version');
            $this->record($http, $case, $request, $version->fresh(), CmsRecommendationEvent::EVENT_REOPENING_EVIDENCE_LINKED, 'cms.reopening.evidence_linked', CmsReopeningRequestVersion::DRAFT, CmsReopeningRequestVersion::DRAFT, ['evidenceLinkId' => $link->id, 'documentVersionId' => $link->document_version_id, 'checksumSha256' => $link->checksum_sha256]);

            return $request->fresh($this->relations());
        });
    }

    public function removeEvidence(Request $http, int $evidenceId, array $data): CmsReopeningRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($http, $actor, $evidenceId, $data): CmsReopeningRequest {
            $evidence = CmsReopeningEvidenceLink::query()->with('version.request')->findOrFail($evidenceId);
            [$case, $request, $version] = $this->lockedVersion($actor, $evidence->version->request->id, $evidence->version->id);
            $this->assertInitiator($actor, $case);
            throw_unless($version->status_code === CmsReopeningRequestVersion::DRAFT && ! $evidence->removed_at, ValidationException::withMessages(['version' => ['Submitted reopening evidence is immutable.']]));
            $evidence->forceFill(['removed_by' => $actor->id, 'removed_at' => now(), 'removal_reason' => $data['reason'] ?? 'Removed from draft.'])->save();
            $version->increment('lock_version');
            $request->increment('lock_version');
            $this->record($http, $case, $request, $version->fresh(), CmsRecommendationEvent::EVENT_REOPENING_EVIDENCE_REMOVED, 'cms.reopening.evidence_draft_removed', CmsReopeningRequestVersion::DRAFT, CmsReopeningRequestVersion::DRAFT, ['evidenceLinkId' => $evidence->id]);

            return $request->fresh($this->relations());
        });
    }

    public function downloadEvidence(Request $http, int $evidenceId)
    {
        $actor = $http->user();
        $evidence = CmsReopeningEvidenceLink::query()->with('version.request.case', 'documentVersion')->findOrFail($evidenceId);
        throw_if($evidence->removed_at, new HttpException(404, 'The reopening evidence is unavailable.'));
        $case = $this->case($actor, $evidence->version->request->cms_recommendation_case_id);
        throw_unless($actor->hasPermission('cms.reopening-evidence.download'), new HttpException(403, 'You cannot download reopening evidence.'));
        $this->documentAccess->authorizeView($actor, $evidence->documentVersion->document);

        return Storage::download($evidence->documentVersion->storage_path, $evidence->documentVersion->original_file_name, ['Content-Type' => $evidence->documentVersion->mime_type]);
    }

    public function permittedActions(User $actor, CmsReopeningRequest $request, ?CmsReopeningRequestVersion $version = null): array
    {
        $version ??= $request->currentVersion;
        if (! $version) {
            return [];
        }
        $actions = [];
        if ($version->status_code === CmsReopeningRequestVersion::DRAFT && $actor->hasPermission('cms.reopening.update')) {
            $actions[] = 'update';
        }
        if ($version->status_code === CmsReopeningRequestVersion::DRAFT && $actor->hasPermission('cms.reopening.submit')) {
            $actions[] = 'submit';
        }
        if ($version->status_code === CmsReopeningRequestVersion::SUBMITTED && $actor->hasPermission('cms.reopening.review')) {
            $actions[] = 'start-review';
        }
        if (in_array($version->status_code, [CmsReopeningRequestVersion::UNDER_REVIEW, CmsReopeningRequestVersion::FOR_DECISION], true) && $actor->hasPermission('cms.reopening.return')) {
            $actions[] = 'return';
        }
        if ($version->status_code === CmsReopeningRequestVersion::UNDER_REVIEW && $actor->hasPermission('cms.reopening.recommend')) {
            $actions[] = 'recommend';
        }
        if ($version->status_code === CmsReopeningRequestVersion::FOR_DECISION && $actor->hasPermission('cms.reopening.approve')) {
            $actions[] = 'approve';
        }
        if ($version->status_code === CmsReopeningRequestVersion::FOR_DECISION && $actor->hasPermission('cms.reopening.reject')) {
            $actions[] = 'reject';
        }
        if ($version->status_code === CmsReopeningRequestVersion::RETURNED && $actor->hasPermission('cms.reopening.revise')) {
            $actions[] = 'revise';
        }
        $version->setAttribute('available_actions', $actions);
        $request->setAttribute('available_actions', $actions);

        return $actions;
    }

    public function readiness(User $actor, CmsRecommendationCase $case): array
    {
        $source = null;
        try {
            $source = $this->resolveSource($case);
        } catch (\Throwable) {
            $source = null;
        }
        $checks = [];
        $add = function (string $code, string $label, bool $passed, string $explanation) use (&$checks): void {
            $checks[] = ['code' => $code, 'label' => $label, 'passed' => $passed, 'blocking' => true, 'explanation' => $explanation];
        };
        $add('terminal_status', 'Recommendation has an eligible terminal status', in_array($case->status_code, array_keys(self::REASONS), true), 'Only CLOSED, ACCEPTED_RISK, or NO_LONGER_APPLICABLE recommendations may be reopened.');
        $add('source_decision', 'Source terminal decision is present and consistent', $source !== null, 'The immutable Closure or Disposition Decision must match the current terminal status.');
        $add('no_unresolved_request', 'No unresolved reopening request exists', ! $case->unresolvedReopeningRequest, 'Resolve the existing reopening request before starting another family.');
        $add('no_active_closure', 'No unresolved closure request exists', ! $case->unresolvedClosureRequest, 'A conflicting closure workflow must be resolved first.');
        $add('no_active_disposition', 'No unresolved disposition request exists', ! $case->unresolvedDispositionRequest, 'A conflicting disposition workflow must be resolved first.');
        $add('no_active_escalation', 'No unresolved escalation exists', ! $case->activeEscalation, 'Resolve the active escalation before reopening.');

        return ['eligible' => ! collect($checks)->contains(fn (array $check): bool => ! $check['passed']), 'checklist' => $checks, 'source' => $source, 'destinations' => $this->destinations($case)];
    }

    private function destinations(CmsRecommendationCase $case): array
    {
        $plan = $case->actionPlan?->acceptedVersion;
        $assignment = $case->currentAssignment;
        $target = $case->effective_target_implementation_date;
        $monitoring = (bool) $plan && $plan->status_code === CmsActionPlanVersion::STATUS_ACCEPTED && (bool) $assignment && $target && ! $target->isPast();

        return [
            ['code' => CmsRecommendationCase::STATUS_FOR_ACTION_PLAN, 'label' => 'For Action Plan', 'eligible' => true, 'requiresNewActionPlan' => true, 'explanation' => 'Approval starts a new active cycle without creating an Action Plan automatically.'],
            ['code' => CmsRecommendationCase::STATUS_MONITORING, 'label' => 'Monitoring', 'eligible' => $monitoring, 'requiresNewActionPlan' => false, 'explanation' => $monitoring ? 'The accepted Action Plan, current Compliance Monitor assignment, and target date remain suitable.' : 'A suitable accepted Action Plan, active Compliance Monitor, and valid target date are required.'],
        ];
    }

    private function assertDestination(CmsRecommendationCase $case, CmsReopeningRequestVersion $version, ?string $destination): void
    {
        throw_unless(in_array($destination, [CmsRecommendationCase::STATUS_FOR_ACTION_PLAN, CmsRecommendationCase::STATUS_MONITORING], true), ValidationException::withMessages(['approvedDestinationStatus' => ['Select a safe active destination status.']]));
        if ($destination === CmsRecommendationCase::STATUS_MONITORING) {
            $valid = collect($this->destinations($case))->firstWhere('code', CmsRecommendationCase::STATUS_MONITORING)['eligible'] ?? false;
            throw_unless($valid, ValidationException::withMessages(['approvedDestinationStatus' => ['Monitoring requires a suitable accepted Action Plan, current Compliance Monitor, and valid target date.']]));
            throw_if(blank($version->existing_action_plan_suitability_assessment), ValidationException::withMessages(['existingActionPlanSuitabilityAssessment' => ['Explain why the retained Action Plan remains suitable for monitoring.']]));
        }
        if ($destination === CmsRecommendationCase::STATUS_FOR_ACTION_PLAN) {
            throw_if(blank($version->new_action_plan_requirement_explanation), ValidationException::withMessages(['newActionPlanRequirementExplanation' => ['Explain the new Action Plan requirement.']]));
        }
    }

    private function assertComplete(CmsRecommendationCase $case, CmsReopeningRequest $request, CmsReopeningRequestVersion $version, array $data, array $source): void
    {
        $reason = strtoupper((string) ($data['reopening_reason_code'] ?? $version->reopening_reason_code));
        $this->validateReason($case->status_code, $reason, $data);
        foreach (['request_summary', 'changed_condition_or_new_fact', 'materiality_assessment', 'source_terminal_decision_assessment', 'risk_impact', 'proposed_follow_up_approach', 'proposed_destination_code'] as $field) {
            throw_if(blank($data[$field] ?? null), ValidationException::withMessages([$field => ['This field is required.']]));
        }
        $destination = strtoupper((string) $data['proposed_destination_code']);
        if ($destination === CmsRecommendationCase::STATUS_MONITORING) {
            throw_if(blank($data['existing_action_plan_suitability_assessment'] ?? null), ValidationException::withMessages(['existingActionPlanSuitabilityAssessment' => ['This assessment is required for Monitoring.']]));
        }
        if ($destination === CmsRecommendationCase::STATUS_FOR_ACTION_PLAN) {
            throw_if(blank($data['new_action_plan_requirement_explanation'] ?? null), ValidationException::withMessages(['newActionPlanRequirementExplanation' => ['This explanation is required for For Action Plan.']]));
        }
        $hasEvidence = $version->activeEvidenceLinks()->exists();
        throw_if(! $hasEvidence && blank($data['no_additional_evidence_explanation'] ?? null), ValidationException::withMessages(['noAdditionalEvidenceExplanation' => ['Link authoritative evidence or explain why no additional evidence is available.']]));
        $this->assertDestination($case, $version->forceFill($this->narratives($data)), $destination);
    }

    private function validateReason(string $status, mixed $reason, array $data = []): void
    {
        $reason = strtoupper((string) $reason);
        throw_unless(in_array($reason, self::REASONS[$status] ?? [], true), ValidationException::withMessages(['reopeningReasonCode' => ['Select a reason permitted for the source terminal status.']]));
        if ($reason === 'OTHER') {
            foreach ([['requestSummary', 'request_summary'], ['changedConditionOrNewFact', 'changed_condition_or_new_fact'], ['materialityAssessment', 'materiality_assessment']] as [$camel, $snake]) {
                throw_if(blank($data[$camel] ?? $data[$snake] ?? null), ValidationException::withMessages([$camel => ['Other requires a specific material factual basis.']]));
            }
        }
    }

    private function sourcePayload(?array $source): ?array
    {
        if (! $source) {
            return null;
        }
        $decision = $source['closureDecision'] ?? $source['dispositionDecision'];

        return ['type' => $source['closureDecision'] ? 'CLOSURE' : 'DISPOSITION', 'id' => $decision?->id, 'decisionCode' => $decision?->decision_code, 'decidedAt' => $decision?->decided_at?->toISOString(), 'finalSnapshot' => $decision?->final_snapshot];
    }

    private function sourceSnapshot(CmsRecommendationCase $case, array $source): array
    {
        return ['terminalStatus' => $case->status_code, 'closureDecisionId' => $source['closureDecision']?->id, 'dispositionDecisionId' => $source['dispositionDecision']?->id, 'decision' => $this->sourcePayload($source), 'capturedAt' => now()->toIso8601String()];
    }

    private function snapshot(CmsRecommendationCase $case, CmsReopeningRequest $request, CmsReopeningRequestVersion $version, array $source): array
    {
        return ['caseId' => $case->id, 'requestId' => $request->id, 'versionId' => $version->id, 'source' => $this->sourceSnapshot($case, $source), 'caseStatus' => $case->status_code, 'activeCycleNumber' => $case->active_cycle_number, 'capturedAt' => now()->toIso8601String()];
    }

    private function decisionSnapshot(CmsRecommendationCase $case, CmsReopeningRequest $request, CmsReopeningRequestVersion $version, array $source, string $decision, ?string $destination, int $priorCycle, ?int $newCycle): array
    {
        return ['requestSnapshot' => $version->submission_snapshot, 'source' => $this->sourceSnapshot($case, $source), 'decision' => $decision, 'destination' => $destination, 'previousActiveCycleNumber' => $priorCycle, 'newActiveCycleNumber' => $newCycle, 'requestId' => $request->id, 'versionId' => $version->id, 'capturedAt' => now()->toIso8601String()];
    }

    private function narratives(array $data): array
    {
        $map = ['reopeningReasonCode' => 'reopening_reason_code', 'requestSummary' => 'request_summary', 'changedConditionOrNewFact' => 'changed_condition_or_new_fact', 'materialityAssessment' => 'materiality_assessment', 'sourceTerminalDecisionAssessment' => 'source_terminal_decision_assessment', 'implementationOrControlFailureAssessment' => 'implementation_or_control_failure_assessment', 'riskImpact' => 'risk_impact', 'responsibleOfficeImpact' => 'responsible_office_impact', 'proposedFollowUpApproach' => 'proposed_follow_up_approach', 'proposedDestinationCode' => 'proposed_destination_code', 'newActionPlanRequirementExplanation' => 'new_action_plan_requirement_explanation', 'existingActionPlanSuitabilityAssessment' => 'existing_action_plan_suitability_assessment', 'complianceMonitorRequirement' => 'compliance_monitor_requirement', 'targetDateImplications' => 'target_date_implications', 'relatedRecurrenceSummary' => 'related_recurrence_summary', 'relatedEscalationSummary' => 'related_escalation_summary', 'managementPosition' => 'management_position', 'ciasInitiatorPosition' => 'cias_initiator_position', 'noAdditionalEvidenceExplanation' => 'no_additional_evidence_explanation'];
        $output = [];
        foreach ($map as $key => $column) {
            $valueKey = array_key_exists($key, $data) ? $key : $column;
            if (array_key_exists($valueKey, $data)) {
                $output[$column] = is_string($data[$valueKey]) ? trim($data[$valueKey]) : $data[$valueKey];
            }
        }

        return $output;
    }

    private function resolveSource(CmsRecommendationCase $case): ?array
    {
        if ($case->status_code === CmsRecommendationCase::STATUS_CLOSED) {
            $decision = $case->closureDecision?->loadMissing('version');

            return $decision && $decision->decision_code === 'APPROVED' && $decision->new_case_status === CmsRecommendationCase::STATUS_CLOSED ? ['closureDecision' => $decision, 'dispositionDecision' => null] : null;
        }
        if (in_array($case->status_code, [CmsRecommendationCase::STATUS_ACCEPTED_RISK, CmsRecommendationCase::STATUS_NO_LONGER_APPLICABLE], true)) {
            $decision = CmsDispositionDecision::query()->where('decision_code', 'APPROVED')->where('new_case_status', $case->status_code)->whereHas('version.request', fn ($q) => $q->where('cms_recommendation_case_id', $case->id))->latest('decided_at')->first();

            return $decision ? ['closureDecision' => null, 'dispositionDecision' => $decision] : null;
        }

        return null;
    }

    private function assertBaseReadiness(CmsRecommendationCase $case, ?array $source): void
    {
        throw_unless(in_array($case->status_code, array_keys(self::REASONS), true), ValidationException::withMessages(['reopening' => ['Only terminal recommendations may be reopened.']]));
        throw_unless($source, ValidationException::withMessages(['sourceDecision' => ['The source terminal decision cannot be resolved safely.']]));
        throw_if($case->unresolvedClosureRequest, ValidationException::withMessages(['closure' => ['A conflicting closure request exists.']]));
        throw_if($case->unresolvedDispositionRequest, ValidationException::withMessages(['disposition' => ['A conflicting disposition request exists.']]));
        throw_if($case->activeEscalation, ValidationException::withMessages(['escalation' => ['A conflicting escalation exists.']]));
    }

    private function assertSourceMatches(CmsReopeningRequest $request, CmsRecommendationCase $case, ?array $source): void
    {
        throw_unless($source && $request->source_terminal_status === $case->status_code, ValidationException::withMessages(['sourceDecision' => ['The source terminal decision or status changed. Refresh and retry.']]));
        $id = $source['closureDecision']?->id ?? $source['dispositionDecision']?->id;
        throw_unless((int) ($request->source_closure_decision_id ?? $request->source_disposition_decision_id) === (int) $id, ValidationException::withMessages(['sourceDecision' => ['The pinned source decision is no longer current. Create a new request.']]));
    }

    private function transition(Request $http, int $id, int $versionId, string $from, string $to, string $userField, string $timeField, string $permission, string $event, string $action): CmsReopeningRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($http, $actor, $id, $versionId, $from, $to, $userField, $timeField, $permission, $event, $action): CmsReopeningRequest {
            [$case, $request, $version] = $this->lockedVersion($actor, $id, $versionId);
            throw_unless($version->status_code === $from, ValidationException::withMessages(['version' => ['Invalid reopening transition.']]));
            $this->authorizeReviewer($actor, $case, $version, $permission);
            $version->forceFill(['status_code' => $to, 'active_slot' => 'ACTIVE', $userField => $actor->id, $timeField => now(), 'lock_version' => $version->lock_version + 1])->save();
            $this->record($http, $case, $request, $version, $event, $action, $from, $to);
            $this->notifyAfterCommit($http, $case, $request, $version, 'review_started');

            return $request->fresh($this->relations());
        });
    }

    private function lockedVersion(User $actor, int $id, int $versionId, bool $lockCase = false): array
    {
        $reference = CmsReopeningRequest::query()->find($id);
        throw_unless($reference, new HttpException(404, 'The reopening request is unavailable.'));
        $case = $this->case($actor, $reference->cms_recommendation_case_id, $lockCase);
        $request = CmsReopeningRequest::query()->with($this->relations())->lockForUpdate()->findOrFail($id);
        $version = $request->versions()->lockForUpdate()->findOrFail($versionId);

        return [$case, $request, $version];
    }

    private function case(User $actor, int $caseId, bool $lock = false): CmsRecommendationCase
    {
        return $this->scope->resolveVisibleCase($actor, $caseId, 'cms.reopening.view', $lock);
    }

    private function assertInitiator(User $actor, CmsRecommendationCase $case): void
    {
        $allowed = $actor->hasPermission('cms.reopening.request') && ($actor->hasGlobalEngagementAccess() || $actor->office_id === $case->lead_responsible_office_id || $case->currentAssignment?->user_id === $actor->id);
        throw_unless($allowed, new HttpException(403, 'You cannot initiate this reopening request.'));
    }

    private function canInitiate(User $actor, CmsRecommendationCase $case): bool
    {
        return $actor->is_active && $actor->hasPermission('cms.reopening.request') && ($actor->hasGlobalEngagementAccess() || $actor->office_id === $case->lead_responsible_office_id || $case->currentAssignment?->user_id === $actor->id);
    }

    private function authorizeReviewer(User $actor, CmsRecommendationCase $case, CmsReopeningRequestVersion $version, string $permission): void
    {
        throw_unless($actor->is_active && $actor->hasPermission($permission), new HttpException(403, 'You are not authorised for this reopening action.'));
        throw_if($version->prepared_by === $actor->id || $version->submitted_by === $actor->id || $version->review_started_by === $actor->id || $case->lead_responsible_office_id === $actor->office_id, new HttpException(403, 'Separation of duties prevents this reopening action.'));
    }

    private function authorizeDecision(User $actor, CmsRecommendationCase $case, CmsReopeningRequestVersion $version, string $permission): void
    {
        throw_unless($actor->is_active && $actor->hasPermission($permission), new HttpException(403, 'You do not have permission to decide this reopening request.'));
        throw_if($version->prepared_by === $actor->id || $version->submitted_by === $actor->id || $version->review_started_by === $actor->id || $case->lead_responsible_office_id === $actor->office_id, new HttpException(403, 'Separation of duties prevents this final reopening decision.'));
    }

    private function defaultInitiator(User $actor, CmsRecommendationCase $case): string
    {
        return $actor->office_id === $case->lead_responsible_office_id ? CmsReopeningRequest::INITIATOR_RESPONSIBLE_OFFICE : CmsReopeningRequest::INITIATOR_COMPLIANCE_MONITOR;
    }

    private function initiatorTypes(User $actor, CmsRecommendationCase $case): array
    {
        $types = [];
        if ($actor->office_id === $case->lead_responsible_office_id) {
            $types[] = CmsReopeningRequest::INITIATOR_RESPONSIBLE_OFFICE;
        } if ($case->currentAssignment?->user_id === $actor->id) {
            $types[] = CmsReopeningRequest::INITIATOR_COMPLIANCE_MONITOR;
        }

        return $types;
    }

    private function assertCaseLock(CmsRecommendationCase $case, array $data): void
    {
        if (array_key_exists('caseLockVersion', $data) && (int) $data['caseLockVersion'] !== (int) $case->lock_version) {
            throw ValidationException::withMessages(['caseLockVersion' => ['The recommendation changed. Refresh and retry.']]);
        }
    }

    private function assertVersionLock(CmsReopeningRequestVersion $version, array $data): void
    {
        if (array_key_exists('lockVersion', $data) && (int) $data['lockVersion'] !== (int) $version->lock_version) {
            throw ValidationException::withMessages(['lockVersion' => ['This reopening version changed. Refresh and retry.']]);
        }
    }

    private function familyActions(User $actor, CmsRecommendationCase $case, ?CmsReopeningRequest $request): array
    {
        if (! $request) {
            return $this->canInitiate($actor, $case) && ($this->readiness($actor, $case)['eligible'] ?? false) ? ['request'] : [];
        }

        return $this->permittedActions($actor, $request, $request->currentVersion);
    }

    private function relations(): array
    {
        return ['case.recommendation', 'case.leadResponsibleOffice', 'case.currentAssignment.user', 'creator', 'sourceClosureDecision.version', 'sourceDispositionDecision.version', 'versions.previousVersion', 'versions.preparer', 'versions.submitter', 'versions.reviewStarter', 'versions.returner', 'versions.assessment.reviewer', 'versions.decision.decider', 'versions.decision.retainedActionPlanVersion', 'versions.evidenceLinks.documentVersion', 'currentVersion.assessment.reviewer', 'currentVersion.decision.decider'];
    }

    private function record(Request $http, CmsRecommendationCase $case, CmsReopeningRequest $request, CmsReopeningRequestVersion $version, string $event, string $action, ?string $previous, ?string $new, array $metadata = []): void
    {
        $payload = ['caseId' => $case->id, 'reopeningRequestId' => $request->id, 'reopeningVersionId' => $version->id, 'versionNumber' => $version->version_number, 'sourceTerminalStatus' => $request->source_terminal_status, 'previousStatus' => $previous, 'newStatus' => $new, ...$metadata];
        CmsRecommendationEvent::query()->firstOrCreate(['idempotency_key' => "cms.reopening.{$version->id}.{$event}.{$version->lock_version}"], ['cms_recommendation_case_id' => $case->id, 'cms_recommendation_id' => $case->cms_recommendation_id, 'event_code' => $event, 'source_module' => 'CMS', 'actor_id' => $http->user()->id, 'previous_status' => $previous, 'new_status' => $new ?? $case->status_code, 'event_metadata' => $payload, 'ip_address' => $http->ip(), 'user_agent' => mb_substr((string) $http->userAgent(), 0, 1000), 'created_at' => now()]);
        ActivityRecorder::record($http, $action, 'CMS reopening workflow action completed.', metadata: ['module' => 'CMS', 'recordType' => 'CMS_REOPENING', 'recordId' => $request->id, 'recordCode' => $version->display_code, ...$payload]);
        DB::table('audit_logs')->insert(['user_id' => $http->user()->id, 'action' => $action, 'auditable_type' => CmsReopeningRequestVersion::class, 'auditable_id' => $version->id, 'old_values' => $previous ? json_encode(['status' => $previous]) : null, 'new_values' => json_encode(['status' => $new, ...$metadata]), 'ip_address' => $http->ip(), 'user_agent' => mb_substr((string) $http->userAgent(), 0, 1000), 'metadata' => json_encode(['module' => 'CMS', 'caseId' => $case->id, 'reopeningRequestId' => $request->id]), 'created_at' => now()]);
    }

    private function notifyAfterCommit(Request $http, CmsRecommendationCase $case, CmsReopeningRequest $request, CmsReopeningRequestVersion $version, string $event): void
    {
        $case->loadMissing('currentAssignment.user', 'actionPlan.acceptedVersion');
        $recipients = collect([$case->currentAssignment?->user_id, $version->prepared_by, $version->submitted_by])->filter()->unique();
        if (in_array($event, ['reviewed', 'approved', 'rejected'], true)) {
            $recipients = $recipients->merge(User::query()->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'cms.reopening.review'))->pluck('id'));
        }
        $recipients = $recipients->filter(fn ($id) => (int) $id !== (int) $http->user()->id);
        if ($recipients->isEmpty()) {
            return;
        }
        $labels = ['submitted' => ['CMS_REOPENING_SUBMITTED', 'Reopening submitted', 'A controlled reopening request was submitted for independent review.'], 'review_started' => ['CMS_REOPENING_REVIEW_STARTED', 'Reopening review started', 'A controlled reopening request entered independent review.'], 'returned' => ['CMS_REOPENING_RETURNED', 'Reopening returned', 'A controlled reopening request was returned for revision.'], 'reviewed' => ['CMS_REOPENING_REVIEWED', 'Reopening assessed', 'An independent reopening assessment is ready for decision.'], 'approved' => ['CMS_REOPENING_APPROVED', 'Recommendation reopened', "The recommendation was reopened; the {$request->source_terminal_status} decision remains historical."], 'rejected' => ['CMS_REOPENING_REJECTED', 'Reopening rejected', "The reopening request was rejected; the {$request->source_terminal_status} status remains effective."]];
        [$type, $title, $message] = $labels[$event] ?? ['CMS_REOPENING_UPDATED', 'Reopening updated', 'A CMS reopening request was updated.'];
        DB::afterCommit(fn () => $this->notifications->send($recipients, ['actorId' => $http->user()->id, 'type' => $type, 'category' => 'CMS_REOPENING', 'priority' => in_array($event, ['approved', 'rejected'], true) ? 'HIGH' : 'NORMAL', 'moduleCode' => 'CMS', 'title' => $title, 'message' => $message, 'actionUrl' => "/compliance-management/recommendations/{$case->id}", 'actionLabel' => 'Open recommendation', 'subjectType' => CmsReopeningRequest::class, 'subjectId' => $request->id, 'subjectCode' => $version->display_code, 'dedupeKey' => "cms-reopening:{$request->id}:{$version->id}:{$event}", 'metadata' => ['caseId' => $case->id, 'sourceTerminalStatus' => $request->source_terminal_status, 'destination' => $version->proposed_destination_code]]));
    }
}
