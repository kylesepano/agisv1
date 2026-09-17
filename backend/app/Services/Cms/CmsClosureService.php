<?php

namespace App\Services\Cms;

use App\Models\CmsClosureDecision;
use App\Models\CmsClosureEvidenceLink;
use App\Models\CmsClosureRequest;
use App\Models\CmsClosureRequestVersion;
use App\Models\CmsClosureReviewAssessment;
use App\Models\CmsRecommendationCase;
use App\Models\CmsValidationVersion;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\DocumentAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Authoritative CMS-8A closure request, review, and decision workflow. */
class CmsClosureService
{
    public function __construct(private readonly CmsRecommendationScopeService $scope, private readonly DocumentAccessService $documentAccess) {}

    public function forRecommendation(User $actor, int $caseId): array
    {
        $case = $this->case($actor, $caseId);
        $requests = CmsClosureRequest::where('cms_recommendation_case_id', $case->id)->with($this->relations())->orderByDesc('request_sequence')->get();
        $requests->each(fn ($r) => $this->actions($actor, $r));

        return ['case' => $case, 'requests' => $requests, 'permittedActions' => $this->familyActions($actor, $case, $requests->first())];
    }

    public function options(User $actor, int $caseId): array
    {
        $case = $this->case($actor, $caseId);
        $readiness = $this->readiness($actor, $case);
        $active = CmsClosureRequest::where('cms_recommendation_case_id', $case->id)->whereNull('resolved_at')->with('currentVersion')->first();

        return ['case' => $case, 'readiness' => $readiness, 'canCreate' => $readiness['eligible'] && ! $active && $actor->hasPermission('cms.closure.request'), 'activeRequest' => $active, 'initiatorTypes' => $this->initiatorTypes($actor, $case), 'reasons' => collect($readiness['checklist'])->where('passed', false)->pluck('explanation')->values()->all()];
    }

    public function show(User $actor, int $id): array
    {
        $reference = CmsClosureRequest::find($id);
        throw_unless($reference, new HttpException(404, 'The closure request is unavailable.'));
        $case = $this->case($actor, $reference->cms_recommendation_case_id);
        $request = CmsClosureRequest::with($this->relations())->findOrFail($id);
        $this->actions($actor, $request);

        return ['case' => $case, 'request' => $request];
    }

    public function create(Request $http, int $caseId, array $data): CmsClosureRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($actor, $caseId, $data) {
            $case = $this->case($actor, $caseId, true);
            $this->assertInitiator($actor, $case);
            $ready = $this->readiness($actor, $case);
            throw_unless($ready['eligible'], ValidationException::withMessages(['closure' => ['The recommendation is not currently eligible for closure.']]));
            $active = CmsClosureRequest::where('cms_recommendation_case_id', $case->id)->whereNull('resolved_at')->lockForUpdate()->first();
            throw_if($active, ValidationException::withMessages(['closure' => ['An unresolved closure request already exists.']]));
            $sequence = ((int) CmsClosureRequest::where('cms_recommendation_case_id', $case->id)->max('request_sequence')) + 1;
            $line = $this->lineage($case);
            $request = CmsClosureRequest::create(['cms_recommendation_case_id' => $case->id, 'request_sequence' => $sequence, 'initiator_type_code' => $data['initiatorTypeCode'] ?? $this->defaultInitiator($actor, $case), 'created_by' => $actor->id, 'lock_version' => 1]);
            $version = $request->versions()->create([...$line, 'version_number' => 1, 'status_code' => CmsClosureRequestVersion::DRAFT, 'active_slot' => 'ACTIVE', 'prepared_by' => $actor->id, ...$this->narratives($data)]);
            $request->update(['current_version_id' => $version->id]);

            return $request->fresh($this->relations());
        });
    }

    public function update(Request $http, int $id, int $versionId, array $data): CmsClosureRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($actor, $id, $versionId, $data) {
            $r = $this->locked($actor, $id);
            $v = $r->versions()->lockForUpdate()->findOrFail($versionId);
            $this->assertLock($v, $data);
            throw_unless($v->status_code === CmsClosureRequestVersion::DRAFT, ValidationException::withMessages(['version' => ['Only drafts can be edited.']]));
            $this->assertInitiator($actor, $r->case);
            $v->update([...$this->narratives($data), 'lock_version' => $v->lock_version + 1]);

            return $r->fresh($this->relations());
        });
    }

    public function submit(Request $http, int $id, int $versionId, array $data): CmsClosureRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($actor, $id, $versionId, $data) {
            $r = $this->locked($actor, $id);
            $case = $r->case()->lockForUpdate()->first();
            $v = $r->versions()->lockForUpdate()->findOrFail($versionId);
            $this->assertLock($v, $data);
            throw_unless($v->status_code === CmsClosureRequestVersion::DRAFT, ValidationException::withMessages(['version' => ['Only a draft can be submitted.']]));
            $this->assertInitiator($actor, $case);
            $ready = $this->readiness($actor, $case);
            $merged = [...$v->toArray(), ...$this->narratives($data)];
            $this->assertComplete($merged, $ready);
            $v->forceFill(['status_code' => CmsClosureRequestVersion::SUBMITTED, 'active_slot' => 'ACTIVE', 'submitted_by' => $actor->id, 'submitted_at' => now(), 'submission_snapshot' => $this->snapshot($case, $v, $ready), 'lock_version' => $v->lock_version + 1])->save();
            $case->forceFill(['status_code' => CmsRecommendationCase::STATUS_FOR_CLOSURE, 'lock_version' => $case->lock_version + 1])->save();

            return $r->fresh($this->relations());
        });
    }

    public function startReview(Request $http, int $id, int $versionId, array $data): CmsClosureRequest
    {
        return $this->transition($http, $id, $versionId, CmsClosureRequestVersion::SUBMITTED, CmsClosureRequestVersion::UNDER_REVIEW, 'review_started_by', 'review_started_at', 'cms.closure.review');
    }

    public function returnVersion(Request $http, int $id, int $versionId, array $data): CmsClosureRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($actor, $id, $versionId, $data) {
            $r = $this->locked($actor, $id);
            $v = $r->versions()->lockForUpdate()->findOrFail($versionId);
            throw_unless(in_array($v->status_code, [CmsClosureRequestVersion::UNDER_REVIEW, CmsClosureRequestVersion::FOR_DECISION], true), ValidationException::withMessages(['version' => ['This version cannot be returned.']]));
            $this->authority($actor, 'cms.closure.return');
            throw_if(blank($data['returnReason'] ?? null), ValidationException::withMessages(['returnReason' => ['A return reason is required.']]));
            $v->forceFill(['status_code' => CmsClosureRequestVersion::RETURNED, 'active_slot' => 'ACTIVE', 'returned_by' => $actor->id, 'returned_at' => now(), 'return_reason' => $data['returnReason'], 'lock_version' => $v->lock_version + 1])->save();

            return $r->fresh($this->relations());
        });
    }

    public function recommend(Request $http, int $id, int $versionId, array $data): CmsClosureRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($actor, $id, $versionId, $data) {
            $r = $this->locked($actor, $id);
            $v = $r->versions()->lockForUpdate()->findOrFail($versionId);
            throw_unless($v->status_code === CmsClosureRequestVersion::UNDER_REVIEW, ValidationException::withMessages(['version' => ['Review is not available.']]));
            $this->independent($actor, $r->case, $v);
            $required = ['readinessSummary', 'validationLineageAssessment', 'documentAndEvidenceAssessment', 'residualMatterAssessment', 'escalationAndExtensionAssessment', 'recordsCompletenessAssessment'];
            foreach ($required as $key) {
                throw_if(blank($data[$key] ?? null), ValidationException::withMessages([$key => ['This assessment field is required.']]));
            }CmsClosureReviewAssessment::create(['cms_closure_request_version_id' => $v->id, 'reviewer_user_id' => $actor->id, 'recommendation_code' => $data['recommendationCode'] ?? 'RECOMMEND_APPROVAL', ...collect($data)->only($required)->mapWithKeys(fn ($x, $k) => [str($k)->snake()->toString() => $x])->all(), 'conditions_or_observations' => $data['conditionsOrObservations'] ?? null, 'reviewed_at' => now()]);
            $v->forceFill(['status_code' => CmsClosureRequestVersion::FOR_DECISION, 'active_slot' => 'ACTIVE', 'lock_version' => $v->lock_version + 1])->save();

            return $r->fresh($this->relations());
        });
    }

    public function decide(Request $http, int $id, int $versionId, array $data, string $decision): CmsClosureRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($actor, $id, $versionId, $data, $decision) {
            $r = $this->locked($actor, $id);
            $case = $r->case()->lockForUpdate()->first();
            $v = $r->versions()->with('assessment')->lockForUpdate()->findOrFail($versionId);
            throw_unless($v->status_code === CmsClosureRequestVersion::FOR_DECISION, ValidationException::withMessages(['version' => ['A final decision is not available.']]));
            $this->authority($actor, $decision === 'APPROVED' ? 'cms.closure.approve' : 'cms.closure.reject');
            $this->independent($actor, $case, $v);
            throw_if(blank($data['decisionComment'] ?? null), ValidationException::withMessages(['decisionComment' => ['A decision comment is required.']]));
            $new = $decision === 'APPROVED' ? CmsRecommendationCase::STATUS_CLOSED : CmsRecommendationCase::STATUS_IMPLEMENTED;
            $snapshot = $this->snapshot($case, $v, $this->readiness($actor, $case));
            $d = CmsClosureDecision::create(['cms_closure_request_version_id' => $v->id, 'decision_code' => $decision, 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_comment' => $data['decisionComment'], 'override_reason' => $data['overrideReason'] ?? null, 'previous_case_status' => $case->status_code, 'new_case_status' => $new, 'closure_effective_date' => $data['closureEffectiveDate'] ?? now()->toDateString(), 'final_snapshot' => $snapshot]);
            $v->forceFill(['status_code' => $decision, 'active_slot' => null, 'lock_version' => $v->lock_version + 1])->save();
            $r->forceFill(['current_version_id' => $v->id, 'resolved_version_id' => $v->id, 'resolved_at' => now(), 'lock_version' => $r->lock_version + 1])->save();
            $case->forceFill(['status_code' => $new, 'lock_version' => $case->lock_version + 1, 'closed_at' => $decision === 'APPROVED' ? now() : null, 'closed_by' => $decision === 'APPROVED' ? $actor->id : null, 'closure_decision_id' => $decision === 'APPROVED' ? $d->id : null])->save();

            return $r->fresh($this->relations());
        });
    }

    public function revise(Request $http, int $id, int $versionId, array $data): CmsClosureRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($actor, $id, $versionId, $data) {
            $r = $this->locked($actor, $id);
            $old = $r->versions()->lockForUpdate()->findOrFail($versionId);
            throw_unless($old->status_code === CmsClosureRequestVersion::RETURNED, ValidationException::withMessages(['version' => ['Only returned versions can be revised.']]));
            $this->assertInitiator($actor, $r->case);
            $line = $this->lineage($r->case);
            $v = $r->versions()->create([...$line, 'version_number' => ((int) $r->versions()->max('version_number')) + 1, 'previous_version_id' => $old->id, 'status_code' => CmsClosureRequestVersion::DRAFT, 'active_slot' => 'ACTIVE', 'prepared_by' => $actor->id, ...$this->narratives($old->toArray()), 'revision_reason' => $data['revisionReason'] ?? null]);
            $old->update(['active_slot' => null]);
            $r->update(['current_version_id' => $v->id, 'lock_version' => $r->lock_version + 1]);

            return $r->fresh($this->relations());
        });
    }

    public function linkEvidence(Request $http, int $id, int $versionId, array $data): CmsClosureRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($actor, $id, $versionId, $data): CmsClosureRequest {
            $request = $this->locked($actor, $id);
            $version = $request->versions()->lockForUpdate()->findOrFail($versionId);
            $this->assertInitiator($actor, $request->case);
            throw_unless($version->status_code === CmsClosureRequestVersion::DRAFT, ValidationException::withMessages(['version' => ['Evidence can only be linked to a draft.']]));
            $documentVersion = DocumentVersion::query()->with('document')->findOrFail((int) ($data['documentVersionId'] ?? 0));
            $version->evidenceLinks()->create(['document_id' => $documentVersion->document_id, 'document_version_id' => $documentVersion->id, 'evidence_category' => strtoupper($data['evidenceCategory'] ?? 'CLOSURE_SUPPORT'), 'title' => $data['title'] ?? $documentVersion->original_file_name, 'description' => $data['description'] ?? null, 'source_or_custodian' => $data['sourceOrCustodian'] ?? null, 'linked_by' => $actor->id, 'linked_at' => now(), 'checksum_sha256' => $documentVersion->checksum_sha256]);
            $version->increment('lock_version');
            $request->increment('lock_version');

            return $request->fresh($this->relations());
        });
    }

    public function removeEvidence(Request $http, int $evidenceId, array $data): CmsClosureRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($actor, $evidenceId, $data): CmsClosureRequest {
            $evidence = CmsClosureEvidenceLink::query()->with('version.request')->findOrFail($evidenceId);
            $request = $this->locked($actor, $evidence->version->request->id);
            $this->assertInitiator($actor, $request->case);
            throw_unless($evidence->version->status_code === CmsClosureRequestVersion::DRAFT, ValidationException::withMessages(['version' => ['Submitted evidence is immutable.']]));
            $evidence->forceFill(['removed_by' => $actor->id, 'removed_at' => now(), 'removal_reason' => $data['reason'] ?? 'Removed from draft.'])->save();

            return $request->fresh($this->relations());
        });
    }

    public function downloadEvidence(Request $http, int $evidenceId)
    {
        $evidence = CmsClosureEvidenceLink::query()->with(['version.request.case', 'documentVersion.document'])->findOrFail($evidenceId);
        $this->case($http->user(), $evidence->version->request->cms_recommendation_case_id, 'cms.closure-evidence.view');
        $this->authority($http->user(), 'cms.closure-evidence.download');
        throw_if($evidence->removed_at, new HttpException(404, 'The closure evidence is unavailable.'));
        $this->documentAccess->authorizeView($http->user(), $evidence->documentVersion->document);

        return Storage::download($evidence->documentVersion->storage_path, $evidence->documentVersion->original_file_name, ['Content-Type' => $evidence->documentVersion->mime_type]);
    }

    public function readiness(User $actor, CmsRecommendationCase $case): array
    {
        $case->loadMissing(['actionPlan.acceptedVersion', 'activeValidationReview', 'unresolvedTargetDateExtensionRequest', 'activeEscalation']);
        $final = CmsValidationVersion::whereHas('review', fn ($q) => $q->where('cms_recommendation_case_id', $case->id))->where('status_code', CmsValidationVersion::STATUS_FINALIZED)->where('final_conclusion_code', 'IMPLEMENTED')->latest('finalized_at')->with('review')->first();
        $checks = [];
        $add = function ($code, $label, $passed, $blocking = true, $explanation = '') use (&$checks) {
            $checks[] = ['code' => $code, 'label' => $label, 'passed' => (bool) $passed, 'blocking' => $blocking, 'explanation' => $explanation];
        };
        $add('case_status', 'Recommendation is IMPLEMENTED', $case->status_code === CmsRecommendationCase::STATUS_IMPLEMENTED, true, 'Formal closure starts from IMPLEMENTED.');
        $add('validation', 'Finalized validation concludes IMPLEMENTED', (bool) $final, true, 'A finalized independent IMPLEMENTED conclusion is required.');
        $add('active_validation', 'No active validation review', ! $case->activeValidationReview, true, 'Complete the active validation review first.');
        $add('extension', 'No unresolved target-date extension', ! $case->unresolvedTargetDateExtensionRequest, true, 'Resolve the pending extension first.');
        $add('escalation', 'No unresolved escalation', ! $case->activeEscalation, true, 'Resolve the escalation first.');
        $add('request', 'No unresolved closure request', ! $case->unresolvedClosureRequest, true, 'An unresolved closure request already exists.');

        return ['eligible' => ! collect($checks)->contains(fn ($c) => $c['blocking'] && ! $c['passed']), 'checklist' => $checks, 'finalizedValidation' => $final ? ['id' => $final->id, 'conclusion' => $final->final_conclusion_code] : null, 'acceptedActionPlan' => $case->actionPlan?->acceptedVersion?->only(['id', 'version_number']), 'recordedProgressUpdate' => null];
    }

    private function transition(Request $http, int $id, int $versionId, string $from, string $to, string $userField, string $timeField, string $permission): CmsClosureRequest
    {
        $actor = $http->user();

        return DB::transaction(function () use ($actor, $id, $versionId, $from, $to, $userField, $timeField, $permission) {
            $r = $this->locked($actor, $id);
            $v = $r->versions()->lockForUpdate()->findOrFail($versionId);
            throw_unless($v->status_code === $from, ValidationException::withMessages(['version' => ['Invalid closure transition.']]));
            $this->authority($actor, $permission);
            $v->forceFill(['status_code' => $to, 'active_slot' => 'ACTIVE', $userField => $actor->id, $timeField => now(), 'lock_version' => $v->lock_version + 1])->save();

            return $r->fresh($this->relations());
        });
    }

    private function case(User $actor, int $id, bool $lock = false): CmsRecommendationCase
    {
        return $this->scope->resolveVisibleCase($actor, $id, 'cms.closure.view', $lock);
    }

    private function locked(User $actor, int $id): CmsClosureRequest
    {
        $r = CmsClosureRequest::find($id);
        throw_unless($r, new HttpException(404, 'The closure request is unavailable.'));
        $this->case($actor, $r->cms_recommendation_case_id, true);

        return CmsClosureRequest::with($this->relations())->lockForUpdate()->findOrFail($id);
    }

    private function authority(User $u, string $p): void
    {
        throw_unless($u->hasPermission($p) && $u->is_active, new HttpException(403, 'You are not authorised for this closure action.'));
    }

    private function assertInitiator(User $u, CmsRecommendationCase $c): void
    {
        throw_unless($u->hasPermission('cms.closure.request') && ($u->office_id === $c->lead_responsible_office_id || $c->currentAssignment?->user_id === $u->id || $u->hasGlobalEngagementAccess()), new HttpException(403, 'You cannot initiate this closure request.'));
    }

    private function independent(User $u, CmsRecommendationCase $c, CmsClosureRequestVersion $v): void
    {
        $this->authority($u, 'cms.closure.review');
        throw_if($v->prepared_by === $u->id || $v->submitted_by === $u->id || $v->review_started_by === $u->id || $c->lead_responsible_office_id === $u->office_id, new HttpException(403, 'Separation of duties prevents this action.'));
    }

    private function defaultInitiator(User $u, CmsRecommendationCase $c): string
    {
        return $u->office_id === $c->lead_responsible_office_id ? CmsClosureRequest::INITIATOR_RESPONSIBLE_OFFICE : CmsClosureRequest::INITIATOR_COMPLIANCE_MONITOR;
    }

    private function lineage(CmsRecommendationCase $c): array
    {
        $review = $c->validationReviews()->whereNotNull('finalized_version_id')->latest('validation_sequence')->firstOrFail();
        $review->load(['finalizedVersion']);
        $progress = $review->recorded_progress_update_version_id;

        return ['finalized_validation_review_id' => $review->id, 'finalized_validation_version_id' => $review->finalized_version_id, 'accepted_action_plan_version_id' => $review->accepted_action_plan_version_id, 'recorded_progress_update_version_id' => $progress];
    }

    private function narratives(array $d): array
    {
        $map = ['closureRequestSummary' => 'closure_request_summary', 'implementationBasis' => 'implementation_basis', 'validatedImplementationSummary' => 'validated_implementation_summary', 'residualMattersSummary' => 'residual_matters_summary', 'residualRiskStatement' => 'residual_risk_statement', 'ongoingMonitoringRequirements' => 'ongoing_monitoring_requirements', 'recordsAndDocumentationSummary' => 'records_and_documentation_summary', 'resolvedEscalationSummary' => 'resolved_escalation_summary', 'managementConfirmation' => 'management_confirmation', 'complianceMonitorRecommendationSummary' => 'compliance_monitor_recommendation_summary', 'noAdditionalEvidenceExplanation' => 'no_additional_evidence_explanation'];
        $o = [];
        foreach ($map as $k => $v) {
            if (array_key_exists($k, $d)) {
                $o[$v] = $d[$k];
            }
        }

        return $o;
    }

    private function assertComplete(array $d, array $ready): void
    {
        throw_unless($ready['eligible'], ValidationException::withMessages(['readiness' => ['Blocking readiness criteria remain.']]));
        foreach (['closure_request_summary', 'implementation_basis', 'validated_implementation_summary', 'records_and_documentation_summary'] as $f) {
            throw_if(blank($d[$f] ?? null), ValidationException::withMessages([$f => ['This field is required.']]));
        }throw_if(blank($d['no_additional_evidence_explanation'] ?? null), ValidationException::withMessages(['noAdditionalEvidenceExplanation' => ['Explain why no additional closure evidence is required.']]));
    }

    private function snapshot(CmsRecommendationCase $c, CmsClosureRequestVersion $v, array $ready): array
    {
        return ['caseId' => $c->id, 'caseStatus' => $c->status_code, 'recommendationId' => $c->cms_recommendation_id, 'validation' => $ready['finalizedValidation'], 'acceptedActionPlan' => $ready['acceptedActionPlan'], 'recordedProgressUpdate' => $ready['recordedProgressUpdate'], 'versionId' => $v->id, 'capturedAt' => now()->toIso8601String()];
    }

    private function relations(): array
    {
        return ['case.recommendation', 'case.leadResponsibleOffice', 'case.currentAssignment.user', 'creator', 'versions.previousVersion', 'versions.validationReview', 'versions.validationVersion', 'versions.acceptedActionPlanVersion', 'versions.recordedProgressUpdateVersion', 'versions.preparer', 'versions.submitter', 'versions.reviewer', 'versions.returner', 'versions.assessment.reviewer', 'versions.decision.decider', 'versions.evidenceLinks.documentVersion', 'currentVersion.assessment.reviewer', 'currentVersion.decision.decider'];
    }

    private function familyActions(User $u, CmsRecommendationCase $c, ?CmsClosureRequest $r): array
    {
        if (! $r) {
            return $c->status_code === CmsRecommendationCase::STATUS_IMPLEMENTED && $u->hasPermission('cms.closure.request') ? ['request'] : [];
        }

        return $this->actions($u, $r);
    }

    private function actions(User $u, CmsClosureRequest $r): array
    {
        $v = $r->currentVersion;
        if (! $v) {
            return [];
        }$a = [];
        if ($v->status_code === 'DRAFT' && $u->hasPermission('cms.closure.update')) {
            $a[] = 'update';
        }if ($v->status_code === 'DRAFT' && $u->hasPermission('cms.closure.submit')) {
            $a[] = 'submit';
        }if ($v->status_code === 'SUBMITTED' && $u->hasPermission('cms.closure.review')) {
            $a[] = 'start-review';
        }if (in_array($v->status_code, ['UNDER_REVIEW', 'FOR_DECISION'], true) && $u->hasPermission('cms.closure.return')) {
            $a[] = 'return';
        }if ($v->status_code === 'UNDER_REVIEW' && $u->hasPermission('cms.closure.recommend')) {
            $a[] = 'recommend';
        }if ($v->status_code === 'FOR_DECISION' && $u->hasPermission('cms.closure.approve')) {
            $a[] = 'approve';
        }if ($v->status_code === 'FOR_DECISION' && $u->hasPermission('cms.closure.reject')) {
            $a[] = 'reject';
        }if ($v->status_code === 'RETURNED' && $u->hasPermission('cms.closure.revise')) {
            $a[] = 'revise';
        }$v->setAttribute('available_actions', $a);
        $r->setAttribute('available_actions', $a);

        return $a;
    }

    private function initiatorTypes(User $u, CmsRecommendationCase $c): array
    {
        $a = [];
        if ($u->office_id === $c->lead_responsible_office_id) {
            $a[] = 'RESPONSIBLE_OFFICE';
        }if ($c->currentAssignment?->user_id === $u->id) {
            $a[] = 'COMPLIANCE_MONITOR';
        }

        return $a;
    }

    private function assertLock($m, array $d): void
    {
        if (array_key_exists('lockVersion', $d) && ((int) $d['lockVersion'] !== (int) $m->lock_version)) {
            throw ValidationException::withMessages(['lockVersion' => ['This record has changed. Refresh and retry.']]);
        }
    }
}
