<?php

namespace App\Services\Cms;

use App\Models\CmsEscalation;
use App\Models\CmsEscalationAcknowledgement;
use App\Models\CmsEscalationNoticeEvidenceLink;
use App\Models\CmsEscalationNoticeVersion;
use App\Models\CmsEscalationResolution;
use App\Models\CmsEscalationResponse;
use App\Models\CmsEscalationResponseEvidenceLink;
use App\Models\CmsEscalationResponseVersion;
use App\Models\CmsProgressUpdate;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\CmsValidationReview;
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
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** CMS-7A formal escalation backend. Escalation state never mutates implementation state. */
class CmsEscalationService
{
    public function __construct(
        private readonly CmsRecommendationScopeService $scope,
        private readonly NotificationService $notifications,
        private readonly DocumentAccessService $documentAccess,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    public function forRecommendation(User $actor, int $caseId): array
    {
        $case = $this->case($actor, $caseId);
        $escalations = CmsEscalation::query()->where('cms_recommendation_case_id', $case->id)
            ->with($this->relations())->orderByDesc('escalation_sequence')->get();

        $escalations->each(fn (CmsEscalation $escalation) => $this->decorateAvailableActions($actor, $escalation));

        return ['case' => $case, 'escalations' => $escalations, 'permittedActions' => $this->permittedCaseActions($actor, $case, $escalations->first())];
    }

    public function show(User $actor, int $id): CmsEscalation
    {
        $reference = CmsEscalation::query()->find($id);
        throw_unless($reference, new HttpException(404, 'The escalation is unavailable.'));
        $this->case($actor, $reference->cms_recommendation_case_id);

        $escalation = CmsEscalation::query()->whereKey($id)->with($this->relations())->firstOrFail();

        return $escalation;
    }

    /** Decorate an escalation graph with actor-scoped, record-specific actions. */
    public function decorateAvailableActions(User $actor, CmsEscalation $escalation): CmsEscalation
    {
        $case = $escalation->case;
        $actions = [];
        $notice = $escalation->currentNotice;
        $usable = $this->scope->isUsableAccount($actor);
        $independent = $usable && $this->canIndependent($actor, $case, $notice?->prepared_by, $notice?->review_started_by, ['cms.escalation.review', 'cms.escalation.response-review', 'cms.escalation.response-accept']);
        $preparer = $usable && (int) $actor->office_id !== (int) $case?->lead_responsible_office_id
            && $actor->hasPermission('cms.escalation.create');
        $responsible = $usable && (int) $actor->office_id === (int) $case?->lead_responsible_office_id
            && $actor->hasPermission('cms.escalation.respond');

        if ($notice) {
            if ($notice->status_code === CmsEscalationNoticeVersion::STATUS_DRAFT && $preparer) {
                if ($actor->hasPermission('cms.escalation.update')) {
                    $actions[] = 'update';
                }
                if ($actor->hasPermission('cms.escalation.submit')) {
                    $actions[] = 'submit';
                }
            }
            if ($notice->status_code === CmsEscalationNoticeVersion::STATUS_SUBMITTED && $independent && $actor->hasPermission('cms.escalation.review')) {
                $actions[] = 'start-review';
            }
            if ($notice->status_code === CmsEscalationNoticeVersion::STATUS_UNDER_REVIEW) {
                if ($actor->hasPermission('cms.escalation.return')) {
                    $actions[] = 'return';
                }
                if ($independent && $actor->hasPermission('cms.escalation.issue')) {
                    $actions[] = 'issue';
                }
            }
            if ($notice->status_code === CmsEscalationNoticeVersion::STATUS_RETURNED && $preparer && $actor->hasPermission('cms.escalation.revise')) {
                $actions[] = 'revise';
            }
        }
        if ($escalation->issuedNotice && $responsible && $actor->hasPermission('cms.escalation.acknowledge')
            && ! $escalation->issuedNotice->acknowledgements?->contains('office_id', $actor->office_id)) {
            $actions[] = 'acknowledge';
        }
        if ($escalation->issuedNotice && ! $escalation->response && $responsible) {
            $actions[] = 'respond';
        }
        $response = $escalation->response;
        $version = $response?->currentVersion;
        if ($version) {
            if ($version->status_code === CmsEscalationResponseVersion::STATUS_DRAFT && $responsible) {
                if ($actor->hasPermission('cms.escalation.respond')) {
                    $actions[] = 'update-response';
                }
                if ($actor->hasPermission('cms.escalation.respond')) {
                    $actions[] = 'submit-response';
                }
            }
            if ($version->status_code === CmsEscalationResponseVersion::STATUS_SUBMITTED && $independent && $actor->hasPermission('cms.escalation.response-review')) {
                $actions[] = 'start-response-review';
            }
            if ($version->status_code === CmsEscalationResponseVersion::STATUS_UNDER_REVIEW) {
                if ($actor->hasPermission('cms.escalation.response-return')) {
                    $actions[] = 'return-response';
                }
                if ($independent && $actor->hasPermission('cms.escalation.response-accept')) {
                    $actions[] = 'accept-response';
                }
            }
            if ($version->status_code === CmsEscalationResponseVersion::STATUS_RETURNED && $responsible && $actor->hasPermission('cms.escalation.revise')) {
                $actions[] = 'revise-response';
            }
            $version->setAttribute('available_actions', array_values(array_unique($actions)));
        }
        if ($escalation->operational_status_code !== CmsEscalation::STATUS_RESOLVED
            && $actor->hasGlobalEngagementAccess() && $actor->hasPermission('cms.escalation.resolve')
            && (int) $actor->office_id !== (int) $case?->lead_responsible_office_id && $usable) {
            $actions[] = 'resolve';
        }

        $escalation->setAttribute('available_actions', array_values(array_unique($actions)));
        $notice?->setAttribute('available_actions', array_values(array_unique($actions)));

        return $escalation;
    }

    private function canIndependent(User $actor, ?CmsRecommendationCase $case, ...$args): bool
    {
        $permissions = ['cms.escalation.review'];
        if ($args !== [] && is_array(end($args))) {
            $permissions = array_pop($args);
        }
        $monitor = $case?->currentAssignment?->user_id === $actor->id && $actor->hasAnyPermission($permissions);
        $management = $actor->hasGlobalEngagementAccess() && $actor->hasPermission('cms.escalation.review');
        if (! $case || (! $management && ! $monitor)) {
            return false;
        }

        return ! in_array($actor->id, array_filter($args), true)
            && (int) $actor->office_id !== (int) $case->lead_responsible_office_id;
    }

    public function options(User $actor, int $caseId): array
    {
        $case = $this->case($actor, $caseId);
        $case->load(['recommendation', 'leadResponsibleOffice', 'currentAssignment.user', 'actionPlan.acceptedVersion', 'activeValidationReview.finalizedVersion']);
        $active = CmsEscalation::query()->where('cms_recommendation_case_id', $case->id)->whereNull('resolved_at')->with('currentNotice')->first();
        $reasons = $this->eligibilityReasons($actor, $case, $active);

        return [
            'caseContext' => $this->caseContext($case),
            'creationAllowed' => $reasons === [] && $actor->hasPermission('cms.escalation.create'),
            'triggerCodes' => CmsEscalation::TRIGGERS,
            'responseDatePolicy' => null,
            'unresolvedEscalation' => $active ? ['id' => $active->id, 'displayCode' => $active->display_code, 'status' => $active->operational_status_code] : null,
            'priorEscalationCount' => CmsEscalation::query()->where('cms_recommendation_case_id', $case->id)->count(),
            'permittedActions' => $reasons === [] ? ['create'] : [],
            'unavailableReasons' => $reasons,
            'defaultRecipients' => $this->recipientOptions($case),
            'caseLockVersion' => $case->lock_version,
        ];
    }

    public function createNotice(Request $request, int $caseId, array $data): CmsEscalation
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $actor, $caseId, $data): CmsEscalation {
            $case = $this->case($actor, $caseId, true);
            $this->authorizePreparation($actor, $case);
            $this->assertCaseLock($case, (int) $data['lockVersion']);
            $this->assertEligible($actor, $case);
            $this->assertTrigger($data['primaryTriggerCode'], $data['additionalTriggerExplanation'] ?? null);
            $sequence = (int) CmsEscalation::query()->where('cms_recommendation_case_id', $case->id)->lockForUpdate()->max('escalation_sequence') + 1;
            $escalation = CmsEscalation::query()->create([
                'cms_recommendation_case_id' => $case->id, 'escalation_sequence' => $sequence,
                'primary_trigger_code' => strtoupper($data['primaryTriggerCode']), 'trigger_snapshot' => $this->sourceSnapshot($case, $actor),
                'source_effective_target_date' => $case->effective_target_implementation_date,
                'source_case_status' => $case->status_code, 'operational_status_code' => CmsEscalation::STATUS_PREPARATION,
                'created_by' => $actor->id, 'lock_version' => 1,
            ]);
            $notice = $escalation->noticeVersions()->create($this->noticeAttributes($data, $actor, 1));
            $escalation->forceFill(['current_notice_version_id' => $notice->id])->save();
            $this->record($request, $case, $escalation, 'ESCALATION_CREATED', 'cms.escalation.created', null, CmsEscalation::STATUS_PREPARATION);

            return $escalation->fresh($this->relations());
        }, 3);
    }

    public function updateNotice(Request $request, int $id, int $versionId, array $data): CmsEscalation
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $actor, $id, $versionId, $data): CmsEscalation {
            [$case, $escalation, $notice] = $this->resolveNotice($actor, $id, $versionId, true);
            $this->authorizePreparation($actor, $case);
            $this->assertDraftNotice($escalation, $notice, (int) $data['lockVersion']);
            $this->assertTrigger($data['primaryTriggerCode'], $data['additionalTriggerExplanation'] ?? null);
            $notice->fill($this->noticeAttributes($data, $actor, $notice->version_number, false));
            $notice->lock_version++;
            $notice->save();
            $escalation->lock_version++;
            $escalation->save();
            $this->record($request, $case, $escalation, 'ESCALATION_NOTICE_UPDATED', 'cms.escalation.notice_updated', CmsEscalation::STATUS_PREPARATION, CmsEscalation::STATUS_PREPARATION);

            return $escalation->fresh($this->relations());
        }, 3);
    }

    public function submitNotice(Request $request, int $id, int $versionId, int $lock): CmsEscalation
    {
        return $this->noticeTransition($request, $id, $versionId, $lock, CmsEscalationNoticeVersion::STATUS_SUBMITTED, 'cms.escalation.submit', function ($case, $escalation, $notice, $actor): void {
            $this->assertNoticeComplete($notice);
            $notice->forceFill(['submitted_by' => $actor->id, 'submitted_at' => now(), 'submission_snapshot' => $this->sourceSnapshot($case, $actor)]);
            $escalation->operational_status_code = CmsEscalation::STATUS_AWAITING_ISSUANCE;
        }, 'ESCALATION_NOTICE_SUBMITTED', 'cms.escalation.notice_submitted');
    }

    public function startNoticeReview(Request $request, int $id, int $versionId, int $lock): CmsEscalation
    {
        return $this->noticeTransition($request, $id, $versionId, $lock, CmsEscalationNoticeVersion::STATUS_UNDER_REVIEW, 'cms.escalation.review', function ($case, $escalation, $notice, $actor): void {
            $this->authorizeIndependent($actor, $case, $notice->prepared_by);
            $notice->forceFill(['review_started_by' => $actor->id, 'review_started_at' => now()]);
        }, 'ESCALATION_NOTICE_REVIEW_STARTED', 'cms.escalation.notice_review_started');
    }

    public function returnNotice(Request $request, int $id, int $versionId, int $lock, string $reason): CmsEscalation
    {
        return $this->noticeTransition($request, $id, $versionId, $lock, CmsEscalationNoticeVersion::STATUS_RETURNED, 'cms.escalation.return', function ($case, $escalation, $notice, $actor) use ($reason): void {
            $notice->forceFill(['returned_by' => $actor->id, 'returned_at' => now(), 'return_reason' => $reason]);
        }, 'ESCALATION_NOTICE_RETURNED', 'cms.escalation.notice_returned');
    }

    public function issueNotice(Request $request, int $id, int $versionId, int $lock, string $comment): CmsEscalation
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $actor, $id, $versionId, $lock, $comment): CmsEscalation {
            [$case, $escalation, $notice] = $this->resolveNotice($actor, $id, $versionId, true);
            $this->assertActorPermission($actor, 'cms.escalation.issue');
            $this->assertStatus($notice, CmsEscalationNoticeVersion::STATUS_UNDER_REVIEW);
            $this->assertLock($notice, $lock);
            $this->authorizeIndependent($actor, $case, $notice->prepared_by, $notice->review_started_by);
            $this->assertDueDate($notice);
            $recipients = $this->resolveRecipients($case, $actor);
            foreach ($recipients as $recipient) {
                $notice->recipients()->create($recipient);
            }
            $notice->forceFill(['status_code' => CmsEscalationNoticeVersion::STATUS_ISSUED, 'active_slot' => null, 'issued_by' => $actor->id, 'issued_at' => now(), 'issuance_comment' => $comment, 'issuance_snapshot' => $this->sourceSnapshot($case, $actor), 'lock_version' => $notice->lock_version + 1])->save();
            $escalation->forceFill(['issued_notice_version_id' => $notice->id, 'current_notice_version_id' => $notice->id, 'operational_status_code' => CmsEscalation::STATUS_ISSUED, 'lock_version' => $escalation->lock_version + 1])->save();
            $this->record($request, $case, $escalation, 'ESCALATION_NOTICE_ISSUED', 'cms.escalation.notice_issued', CmsEscalation::STATUS_AWAITING_ISSUANCE, CmsEscalation::STATUS_ISSUED);
            DB::afterCommit(fn () => $this->notify($case, $actor, 'Escalation notice issued', 'A formal CMS escalation notice has been issued.', "cms-escalation:issued:{$escalation->id}"));

            return $escalation->fresh($this->relations());
        }, 3);
    }

    public function reviseNotice(Request $request, int $id, int $versionId, int $lock, string $reason): CmsEscalation
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $actor, $id, $versionId, $lock, $reason): CmsEscalation {
            [$case, $escalation, $old] = $this->resolveNotice($actor, $id, $versionId, true);
            $this->authorizePreparation($actor, $case);
            $this->assertStatus($old, CmsEscalationNoticeVersion::STATUS_RETURNED);
            $this->assertLock($escalation, $lock);
            $version = $escalation->noticeVersions()->lockForUpdate()->max('version_number') + 1;
            $new = $escalation->noticeVersions()->create($this->noticeAttributes($old->toArray(), $actor, $version, true) + ['previous_version_id' => $old->id, 'revision_reason' => $reason]);
            foreach ($old->activeEvidenceLinks as $evidence) {
                $new->evidenceLinks()->create($evidence->only(['document_id', 'document_version_id', 'evidence_category', 'title', 'description', 'source_or_custodian', 'linked_by', 'linked_at', 'checksum_sha256', 'confidentiality_level_id', 'confidentiality_code_snapshot']));
            }
            $escalation->forceFill(['current_notice_version_id' => $new->id, 'lock_version' => $escalation->lock_version + 1, 'operational_status_code' => CmsEscalation::STATUS_PREPARATION])->save();
            $this->record($request, $case, $escalation, 'ESCALATION_NOTICE_REVISION_CREATED', 'cms.escalation.notice_revision_created', CmsEscalation::STATUS_AWAITING_ISSUANCE, CmsEscalation::STATUS_PREPARATION);

            return $escalation->fresh($this->relations());
        }, 3);
    }

    public function acknowledge(Request $request, int $id, ?string $comment): CmsEscalation
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $actor, $id, $comment): CmsEscalation {
            $escalation = $this->show($actor, $id);
            $case = $escalation->case->load('leadResponsibleOffice');
            $this->assertActorPermission($actor, 'cms.escalation.acknowledge');
            $this->assertStatus($escalation->issuedNotice, CmsEscalationNoticeVersion::STATUS_ISSUED);
            throw_unless($actor->office_id && (int) $actor->office_id === (int) $case->lead_responsible_office_id, new HttpException(403, 'Only a responsible-office representative may acknowledge this notice.'));
            throw_if(CmsEscalationAcknowledgement::query()->where('cms_escalation_notice_version_id', $escalation->issued_notice_version_id)->where('office_id', $actor->office_id)->exists(), new HttpException(409, 'This office has already acknowledged the notice.'));
            CmsEscalationAcknowledgement::query()->create(['cms_escalation_id' => $escalation->id, 'cms_escalation_notice_version_id' => $escalation->issued_notice_version_id, 'office_id' => $actor->office_id, 'user_id' => $actor->id, 'acknowledged_at' => now(), 'acknowledgement_comment' => $comment, 'metadata' => ['agreement' => false]]);
            $escalation->forceFill(['operational_status_code' => CmsEscalation::STATUS_ACKNOWLEDGED, 'lock_version' => $escalation->lock_version + 1])->save();
            $this->record($request, $case, $escalation, 'ESCALATION_ACKNOWLEDGED', 'cms.escalation.acknowledged', CmsEscalation::STATUS_ISSUED, CmsEscalation::STATUS_ACKNOWLEDGED);

            return $escalation->fresh($this->relations());
        }, 3);
    }

    public function createResponse(Request $request, int $id, array $data): CmsEscalationResponse
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $actor, $id, $data): CmsEscalationResponse {
            $escalation = $this->show($actor, $id);
            $case = $escalation->case->load('leadResponsibleOffice');
            $this->authorizeResponsibleResponse($actor, $case);
            $this->assertStatus($escalation->issuedNotice, CmsEscalationNoticeVersion::STATUS_ISSUED);
            throw_if($escalation->response, new HttpException(409, 'A response family already exists for this escalation.'));
            $response = CmsEscalationResponse::query()->create(['cms_escalation_id' => $escalation->id, 'issued_notice_version_id' => $escalation->issued_notice_version_id, 'created_by' => $actor->id, 'lock_version' => 1]);
            $version = $response->versions()->create($this->responseAttributes($data, $actor, 1));
            $response->forceFill(['current_version_id' => $version->id])->save();
            $escalation->forceFill(['current_response_id' => $response->id, 'operational_status_code' => CmsEscalation::STATUS_AWAITING_RESPONSE, 'lock_version' => $escalation->lock_version + 1])->save();
            $this->record($request, $case, $escalation, 'ESCALATION_RESPONSE_CREATED', 'cms.escalation.response_created', CmsEscalation::STATUS_ISSUED, CmsEscalation::STATUS_AWAITING_RESPONSE);

            return $response->fresh($this->responseRelations());
        }, 3);
    }

    public function updateResponse(Request $request, int $responseId, int $versionId, array $data): CmsEscalationResponse
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $actor, $responseId, $versionId, $data): CmsEscalationResponse {
            [$case, $escalation, $response, $version] = $this->resolveResponse($actor, $responseId, $versionId, true);
            $this->authorizeResponsibleResponse($actor, $case);
            $this->assertStatus($version, CmsEscalationResponseVersion::STATUS_DRAFT);
            $this->assertLock($version, (int) $data['lockVersion']);
            $this->assertLock($response, (int) $data['lockVersion']);
            $version->fill($this->responseAttributes($data, $actor, $version->version_number, false));
            $version->lock_version++;
            $version->save();
            $response->lock_version++;
            $response->save();
            $this->record($request, $case, $escalation, 'ESCALATION_RESPONSE_UPDATED', 'cms.escalation.response_updated', CmsEscalation::STATUS_AWAITING_RESPONSE, CmsEscalation::STATUS_AWAITING_RESPONSE);

            return $response->fresh($this->responseRelations());
        }, 3);
    }

    public function submitResponse(Request $request, int $responseId, int $versionId, int $lock): CmsEscalationResponse
    {
        return $this->responseTransition($request, $responseId, $versionId, $lock, CmsEscalationResponseVersion::STATUS_SUBMITTED, 'cms.escalation.respond', function ($case, $escalation, $response, $version, $actor): void {
            $this->assertResponseComplete($version);
            $version->forceFill(['submitted_by' => $actor->id, 'submitted_at' => now(), 'submission_snapshot' => $this->sourceSnapshot($case, $actor)]);
            $escalation->operational_status_code = CmsEscalation::STATUS_RESPONSE_UNDER_REVIEW;
        }, 'ESCALATION_RESPONSE_SUBMITTED', 'cms.escalation.response_submitted');
    }

    public function startResponseReview(Request $request, int $responseId, int $versionId, int $lock): CmsEscalationResponse
    {
        return $this->responseTransition($request, $responseId, $versionId, $lock, CmsEscalationResponseVersion::STATUS_UNDER_REVIEW, 'cms.escalation.response-review', function ($case, $escalation, $response, $version, $actor): void {
            $this->authorizeIndependent($actor, $case, $version->prepared_by, $version->submitted_by);
            $version->forceFill(['review_started_by' => $actor->id, 'review_started_at' => now()]);
        }, 'ESCALATION_RESPONSE_REVIEW_STARTED', 'cms.escalation.response_review_started');
    }

    public function returnResponse(Request $request, int $responseId, int $versionId, int $lock, string $reason): CmsEscalationResponse
    {
        return $this->responseTransition($request, $responseId, $versionId, $lock, CmsEscalationResponseVersion::STATUS_RETURNED, 'cms.escalation.response-return', function ($case, $escalation, $response, $version, $actor) use ($reason): void {
            $version->forceFill(['returned_by' => $actor->id, 'returned_at' => now(), 'return_reason' => $reason]);
        }, 'ESCALATION_RESPONSE_RETURNED', 'cms.escalation.response_returned');
    }

    public function acceptResponse(Request $request, int $responseId, int $versionId, int $lock, string $comment): CmsEscalationResponse
    {
        return $this->responseTransition($request, $responseId, $versionId, $lock, CmsEscalationResponseVersion::STATUS_ACCEPTED_FOR_FOLLOW_UP, 'cms.escalation.response-accept', function ($case, $escalation, $response, $version, $actor) use ($comment): void {
            $this->authorizeIndependent($actor, $case, $version->prepared_by, $version->submitted_by);
            $version->forceFill(['accepted_by' => $actor->id, 'accepted_at' => now(), 'acceptance_comment' => $comment]);
            $response->accepted_version_id = $version->id;
            $escalation->operational_status_code = CmsEscalation::STATUS_FOLLOW_UP;
        }, 'ESCALATION_RESPONSE_ACCEPTED', 'cms.escalation.response_accepted');
    }

    public function reviseResponse(Request $request, int $responseId, int $versionId, int $lock, string $reason): CmsEscalationResponse
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $actor, $responseId, $versionId, $lock, $reason): CmsEscalationResponse {
            [$case, $escalation, $response, $old] = $this->resolveResponse($actor, $responseId, $versionId, true);
            $this->authorizeResponsibleResponse($actor, $case);
            $this->assertStatus($old, CmsEscalationResponseVersion::STATUS_RETURNED);
            $this->assertLock($old, $lock);
            $number = (int) $response->versions()->lockForUpdate()->max('version_number') + 1;
            $new = $response->versions()->create($this->responseAttributes($old->toArray(), $actor, $number, true) + ['previous_version_id' => $old->id, 'revision_reason' => $reason]);
            foreach ($old->activeEvidenceLinks as $evidence) {
                $new->evidenceLinks()->create($evidence->only(['document_id', 'document_version_id', 'evidence_category', 'title', 'description', 'source_or_custodian', 'linked_by', 'linked_at', 'checksum_sha256', 'confidentiality_level_id', 'confidentiality_code_snapshot']));
            } $response->forceFill(['current_version_id' => $new->id, 'lock_version' => $response->lock_version + 1])->save();
            $this->record($request, $case, $escalation, 'ESCALATION_RESPONSE_REVISION_CREATED', 'cms.escalation.response_revision_created', CmsEscalation::STATUS_AWAITING_RESPONSE, CmsEscalation::STATUS_AWAITING_RESPONSE);

            return $response->fresh($this->responseRelations());
        }, 3);
    }

    public function resolve(Request $request, int $id, int $lock, array $data): CmsEscalation
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $actor, $id, $lock, $data): CmsEscalation {
            $escalation = $this->show($actor, $id);
            $case = $escalation->case;
            $this->assertActorPermission($actor, 'cms.escalation.resolve');
            throw_unless($actor->hasPermission('cms.escalation.resolve') && (int) $actor->office_id !== (int) $case->lead_responsible_office_id, new HttpException(403, 'You do not have permission to resolve this escalation independently.'));
            $this->assertLock($escalation, $lock);
            throw_unless($escalation->operational_status_code !== CmsEscalation::STATUS_RESOLVED, new HttpException(409, 'This escalation is already resolved.'));
            throw_unless($escalation->response?->acceptedVersion || $escalation->issuedNotice, new HttpException(422, 'An issued escalation with an eligible documented basis is required.'));
            throw_if($escalation->resolution, new HttpException(409, 'This escalation already has a resolution.'));
            CmsEscalationResolution::query()->create(['cms_escalation_id' => $escalation->id, 'resolution_code' => 'RESOLVED_FOR_ESCALATION_PURPOSES', 'resolution_summary' => $data['resolutionSummary'], 'basis_for_resolution' => $data['basisForResolution'], 'follow_up_requirements' => $data['followUpRequirements'], 'resolved_by' => $actor->id, 'resolved_at' => now(), 'recommendation_case_status_snapshot' => $case->status_code, 'accepted_response_version_id' => $escalation->response?->accepted_version_id, 'metadata' => ['recommendationClosed' => false]]);
            $escalation->forceFill(['operational_status_code' => CmsEscalation::STATUS_RESOLVED, 'resolved_at' => now(), 'lock_version' => $escalation->lock_version + 1])->save();
            $this->record($request, $case, $escalation, 'ESCALATION_RESOLVED', 'cms.escalation.resolved', null, CmsEscalation::STATUS_RESOLVED);
            DB::afterCommit(fn () => $this->notify($case, $actor, 'Escalation resolved', 'The escalation process was resolved; the recommendation was not automatically closed.', "cms-escalation:resolved:{$escalation->id}"));

            return $escalation->fresh($this->relations());
        }, 3);
    }

    public function uploadNoticeEvidence(Request $request, int $id, int $versionId, array $data, UploadedFile $file): CmsEscalation
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $actor, $id, $versionId, $data, $file): CmsEscalation {
            [$case, $escalation, $notice] = $this->resolveNotice($actor, $id, $versionId, true);
            $this->authorizePreparation($actor, $case);
            $this->assertDraftNotice($escalation, $notice, (int) $data['lockVersion']);
            $link = $this->createEvidenceDocument($actor, $case, $data, $file);
            $notice->evidenceLinks()->create([...$link, 'linked_by' => $actor->id, 'linked_at' => now()]);
            $notice->lock_version++;
            $notice->save();
            $escalation->lock_version++;
            $escalation->save();
            $this->record($request, $case, $escalation, 'ESCALATION_EVIDENCE_LINKED', 'cms.escalation.evidence_linked', CmsEscalation::STATUS_PREPARATION, CmsEscalation::STATUS_PREPARATION);

            return $escalation->fresh($this->relations());
        }, 3);
    }

    public function uploadResponseEvidence(Request $request, int $responseId, int $versionId, array $data, UploadedFile $file): CmsEscalationResponse
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $actor, $responseId, $versionId, $data, $file): CmsEscalationResponse {
            [$case, $escalation, $response, $version] = $this->resolveResponse($actor, $responseId, $versionId, true);
            $this->authorizeResponsibleResponse($actor, $case);
            $this->assertStatus($version, CmsEscalationResponseVersion::STATUS_DRAFT);
            $this->assertLock($version, (int) $data['lockVersion']);
            $link = $this->createEvidenceDocument($actor, $case, $data, $file);
            $version->evidenceLinks()->create([...$link, 'linked_by' => $actor->id, 'linked_at' => now()]);
            $version->lock_version++;
            $version->save();
            $response->lock_version++;
            $response->save();
            $this->record($request, $case, $escalation, 'ESCALATION_EVIDENCE_LINKED', 'cms.escalation.evidence_linked', CmsEscalation::STATUS_AWAITING_RESPONSE, CmsEscalation::STATUS_AWAITING_RESPONSE);

            return $response->fresh($this->responseRelations());
        }, 3);
    }

    public function removeEvidence(Request $request, string $type, int $evidenceId, int $lock, string $reason): CmsEscalation|CmsEscalationResponse
    {
        $actor = $request->user();

        return DB::transaction(function () use ($actor, $type, $evidenceId, $lock, $reason) {
            if ($type === 'notice') {
                $e = CmsEscalationNoticeEvidenceLink::query()->with('noticeVersion.escalation.case')->find($evidenceId);
                throw_unless($e, new HttpException(404, 'The escalation evidence is unavailable.'));
                $esc = $e->noticeVersion->escalation;
                $case = $esc->case;
                $this->authorizePreparation($actor, $case);
                $this->assertDraftNotice($esc, $e->noticeVersion, $lock);
                $e->forceFill(['removed_by' => $actor->id, 'removed_at' => now(), 'removal_reason' => $reason])->save();

                return $esc->fresh($this->relations());
            } $e = CmsEscalationResponseEvidenceLink::query()->with('responseVersion.response.escalation.case')->find($evidenceId);
            throw_unless($e, new HttpException(404, 'The escalation evidence is unavailable.'));
            $response = $e->responseVersion->response;
            $esc = $response->escalation;
            $this->authorizeResponsibleResponse($actor, $esc->case);
            $this->assertStatus($e->responseVersion, CmsEscalationResponseVersion::STATUS_DRAFT);
            $this->assertLock($e->responseVersion, $lock);
            $e->forceFill(['removed_by' => $actor->id, 'removed_at' => now(), 'removal_reason' => $reason])->save();

            return $response->fresh($this->responseRelations());
        }, 3);
    }

    public function downloadEvidence(Request $request, string $type, int $evidenceId): StreamedResponse
    {
        $actor = $request->user();
        $model = $type === 'notice' ? CmsEscalationNoticeEvidenceLink::class : CmsEscalationResponseEvidenceLink::class;
        $e = $model::query()->whereNull('removed_at')->with(['documentVersion', 'document'])->find($evidenceId);
        throw_unless($e, new HttpException(404, 'The escalation evidence is unavailable.'));
        $parent = $type === 'notice' ? $e->noticeVersion : $e->responseVersion;
        $esc = $type === 'notice' ? $parent->escalation : $parent->response->escalation;
        $case = $esc->case;
        throw_unless($actor->hasPermission('cms.escalation-evidence.download') && $this->scope->canViewClassification($actor, $e->confidentiality_code_snapshot), new HttpException(403, 'You cannot download this escalation evidence.'));
        $this->documentAccess->authorizeView($actor, $e->document);
        abort_unless(Storage::disk('local')->exists($e->documentVersion->storage_path), 404, 'Stored escalation evidence file not found.');

        return Storage::disk('local')->download($e->documentVersion->storage_path, $e->documentVersion->original_file_name, ['Content-Type' => $e->documentVersion->mime_type]);
    }

    private function uploadNoticeEvidenceInternal(Request $request, int $id, int $versionId, array $data, UploadedFile $file): CmsEscalation
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $actor, $id, $versionId, $data, $file): CmsEscalation {
            [$case, $escalation, $notice] = $this->resolveNotice($actor, $id, $versionId, true);
            $this->assertActorPermission($actor, 'cms.escalation-evidence.upload');
            $this->assertStatus($notice, CmsEscalationNoticeVersion::STATUS_DRAFT);
            $this->assertLock($notice, (int) $data['lockVersion']);
            $this->storeEvidence($actor, $case, $file, $data, $notice);
            $notice->forceFill(['lock_version' => $notice->lock_version + 1])->save();
            $escalation->forceFill(['lock_version' => $escalation->lock_version + 1])->save();
            $this->record($request, $case, $escalation, 'ESCALATION_EVIDENCE_LINKED', 'cms.escalation.evidence_linked', CmsEscalation::STATUS_PREPARATION, CmsEscalation::STATUS_PREPARATION);

            return $escalation->fresh($this->relations());
        }, 3);
    }

    private function uploadResponseEvidenceInternal(Request $request, int $responseId, int $versionId, array $data, UploadedFile $file): CmsEscalationResponse
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $actor, $responseId, $versionId, $data, $file): CmsEscalationResponse {
            [$case, $escalation, $response, $version] = $this->resolveResponse($actor, $responseId, $versionId, true);
            $this->authorizeResponsibleResponse($actor, $case);
            $this->assertActorPermission($actor, 'cms.escalation-evidence.upload');
            $this->assertStatus($version, CmsEscalationResponseVersion::STATUS_DRAFT);
            $this->assertLock($version, (int) $data['lockVersion']);
            $this->storeEvidence($actor, $case, $file, $data, $version);
            $version->forceFill(['lock_version' => $version->lock_version + 1])->save();
            $response->forceFill(['lock_version' => $response->lock_version + 1])->save();
            $this->record($request, $case, $escalation, 'ESCALATION_EVIDENCE_LINKED', 'cms.escalation.evidence_linked', CmsEscalation::STATUS_AWAITING_RESPONSE, CmsEscalation::STATUS_AWAITING_RESPONSE);

            return $response->fresh($this->responseRelations());
        }, 3);
    }

    public function removeNoticeEvidence(Request $request, int $evidenceId, int $lock, string $reason): CmsEscalation
    {
        $actor = $request->user();
        $link = CmsEscalationNoticeEvidenceLink::query()->with('noticeVersion')->find($evidenceId);
        throw_unless($link, new HttpException(404, 'The escalation evidence is unavailable.'));

        return DB::transaction(function () use ($request, $actor, $link, $lock, $reason): CmsEscalation {
            [$case, $escalation, $notice] = $this->resolveNotice($actor, $link->noticeVersion->cms_escalation_id, $link->cms_escalation_notice_version_id, true);
            $this->assertActorPermission($actor, 'cms.escalation-evidence.remove_draft');
            $this->assertStatus($notice, CmsEscalationNoticeVersion::STATUS_DRAFT);
            $this->assertLock($notice, $lock);
            $link->forceFill(['removed_by' => $actor->id, 'removed_at' => now(), 'removal_reason' => $reason])->save();
            $notice->forceFill(['lock_version' => $notice->lock_version + 1])->save();
            $this->record($request, $case, $escalation, 'ESCALATION_EVIDENCE_REMOVED', 'cms.escalation.evidence_draft_removed', CmsEscalation::STATUS_PREPARATION, CmsEscalation::STATUS_PREPARATION);

            return $escalation->fresh($this->relations());
        }, 3);
    }

    public function removeResponseEvidence(Request $request, int $evidenceId, int $lock, string $reason): CmsEscalationResponse
    {
        $actor = $request->user();
        $link = CmsEscalationResponseEvidenceLink::query()->with('responseVersion')->find($evidenceId);
        throw_unless($link, new HttpException(404, 'The escalation evidence is unavailable.'));

        return DB::transaction(function () use ($request, $actor, $link, $lock, $reason): CmsEscalationResponse {
            [$case, $escalation, $response, $version] = $this->resolveResponse($actor, $link->responseVersion->cms_escalation_response_id, $link->cms_escalation_response_version_id, true);
            $this->authorizeResponsibleResponse($actor, $case);
            $this->assertActorPermission($actor, 'cms.escalation-evidence.remove_draft');
            $this->assertStatus($version, CmsEscalationResponseVersion::STATUS_DRAFT);
            $this->assertLock($version, $lock);
            $link->forceFill(['removed_by' => $actor->id, 'removed_at' => now(), 'removal_reason' => $reason])->save();
            $version->forceFill(['lock_version' => $version->lock_version + 1])->save();
            $this->record($request, $case, $escalation, 'ESCALATION_EVIDENCE_REMOVED', 'cms.escalation.evidence_draft_removed', CmsEscalation::STATUS_AWAITING_RESPONSE, CmsEscalation::STATUS_AWAITING_RESPONSE);

            return $response->fresh($this->responseRelations());
        }, 3);
    }

    public function downloadNoticeEvidence(Request $request, int $evidenceId)
    {
        $actor = $request->user();
        $link = CmsEscalationNoticeEvidenceLink::query()->whereNull('removed_at')->with('noticeVersion.escalation', 'document', 'documentVersion')->find($evidenceId);
        throw_unless($link, new HttpException(404, 'The escalation evidence is unavailable.'));
        $this->show($actor, $link->noticeVersion->escalation->id);
        $this->assertActorPermission($actor, 'cms.escalation-evidence.download');
        $this->documentAccess->authorizeView($actor, $link->document);
        abort_unless(Storage::disk('local')->exists($link->documentVersion->storage_path), 404, 'Stored escalation evidence file not found.');

        return Storage::disk('local')->download($link->documentVersion->storage_path, $link->documentVersion->original_file_name, ['Content-Type' => $link->documentVersion->mime_type]);
    }

    public function downloadResponseEvidence(Request $request, int $evidenceId)
    {
        $actor = $request->user();
        $link = CmsEscalationResponseEvidenceLink::query()->whereNull('removed_at')->with('responseVersion.response.escalation', 'document', 'documentVersion')->find($evidenceId);
        throw_unless($link, new HttpException(404, 'The escalation evidence is unavailable.'));
        $this->show($actor, $link->responseVersion->response->cms_escalation_id);
        $this->assertActorPermission($actor, 'cms.escalation-evidence.download');
        $this->documentAccess->authorizeView($actor, $link->document);
        abort_unless(Storage::disk('local')->exists($link->documentVersion->storage_path), 404, 'Stored escalation evidence file not found.');

        return Storage::disk('local')->download($link->documentVersion->storage_path, $link->documentVersion->original_file_name, ['Content-Type' => $link->documentVersion->mime_type]);
    }

    private function storeEvidence(User $actor, CmsRecommendationCase $case, UploadedFile $file, array $data, $parent): void
    {
        $level = MasterListItem::query()->findOrFail((int) $data['confidentialityLevelId']);
        $this->documentAccess->authorizeClassification($actor, $level);
        $stored = ['storage_path' => $file->store('cms/escalations', 'local'), 'original_file_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'file_extension' => $file->getClientOriginalExtension(), 'file_size' => (int) $file->getSize(), 'checksum_sha256' => hash_file('sha256', $file->getRealPath())];
        $type = MasterList::query()->where('code', 'DOCUMENT_TYPE')->firstOrFail()->items()->where('code', 'OTHER')->firstOrFail();
        $document = Document::query()->create(['document_type_id' => $type->id, 'confidentiality_level_id' => $level->id, 'title' => $data['title'], 'description' => $data['description'] ?? null, 'owner_module' => 'CMS', 'library_visible' => false, ...$stored, 'uploaded_by' => $actor->id, 'updated_by' => $actor->id, 'is_active' => true]);
        $document->forceFill(['document_code' => $this->runtime->formatNumber('document_number_format', $document->id)])->save();
        $docVersion = $document->versions()->create(['version_number' => 1, 'version_label' => 'CMS escalation evidence version 1', 'change_summary' => 'Initial escalation evidence upload.', ...$stored, 'uploaded_by' => $actor->id]);
        $document->forceFill(['current_version_id' => $docVersion->id, 'version' => $docVersion->version_label])->save();
        $attrs = ['document_id' => $document->id, 'document_version_id' => $docVersion->id, 'evidence_category' => strtoupper($data['evidenceCategory']), 'title' => $data['title'], 'description' => $data['description'] ?? null, 'source_or_custodian' => $data['sourceOrCustodian'] ?? null, 'linked_by' => $actor->id, 'linked_at' => now(), 'checksum_sha256' => $docVersion->checksum_sha256, 'confidentiality_level_id' => $level->id, 'confidentiality_code_snapshot' => $level->code];
        if ($parent instanceof CmsEscalationNoticeVersion) {
            $parent->evidenceLinks()->create($attrs);
        } else {
            $parent->evidenceLinks()->create($attrs);
        }
    }

    private function noticeTransition(Request $request, int $id, int $versionId, int $lock, string $status, string $permission, callable $mutate, string $event, string $action): CmsEscalation
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $actor, $id, $versionId, $lock, $status, $permission, $mutate, $event, $action): CmsEscalation {
            [$case, $escalation, $notice] = $this->resolveNotice($actor, $id, $versionId, true);
            $this->assertActorPermission($actor, $permission);
            $this->assertLock($notice, $lock);
            $from = $notice->status_code;
            $mutate($case, $escalation, $notice, $actor);
            $notice->forceFill(['status_code' => $status, 'active_slot' => in_array($status, CmsEscalationNoticeVersion::ACTIVE_STATUSES, true) ? 'ACTIVE' : null, 'lock_version' => $notice->lock_version + 1])->save();
            $escalation->forceFill(['current_notice_version_id' => $notice->id, 'lock_version' => $escalation->lock_version + 1])->save();
            $this->record($request, $case, $escalation, $event, $action, $from, $status);

            return $escalation->fresh($this->relations());
        }, 3);
    }

    private function responseTransition(Request $request, int $responseId, int $versionId, int $lock, string $status, string $permission, callable $mutate, string $event, string $action): CmsEscalationResponse
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $actor, $responseId, $versionId, $lock, $status, $permission, $mutate, $event, $action): CmsEscalationResponse {
            [$case, $escalation, $response, $version] = $this->resolveResponse($actor, $responseId, $versionId, true);
            $this->assertActorPermission($actor, $permission);
            $this->assertLock($version, $lock);
            $this->assertLock($response, $lock);
            $from = $version->status_code;
            $mutate($case, $escalation, $response, $version, $actor);
            $version->forceFill(['status_code' => $status, 'active_slot' => in_array($status, CmsEscalationResponseVersion::ACTIVE_STATUSES, true) ? 'ACTIVE' : null, 'lock_version' => $version->lock_version + 1])->save();
            $response->forceFill(['current_version_id' => $version->id, 'lock_version' => $response->lock_version + 1])->save();
            $escalation->forceFill(['current_response_id' => $response->id, 'lock_version' => $escalation->lock_version + 1])->save();
            $this->record($request, $case, $escalation, $event, $action, $from, $status);

            return $response->fresh($this->responseRelations());
        }, 3);
    }

    private function resolveNotice(User $actor, int $id, int $versionId, bool $lock): array
    {
        $escalation = CmsEscalation::query()->with('case')->find($id);
        throw_unless($escalation, new HttpException(404, 'The escalation is unavailable.'));
        $case = $this->case($actor, $escalation->cms_recommendation_case_id);
        $query = CmsEscalationNoticeVersion::query()->whereKey($versionId)->where('cms_escalation_id', $id);
        if ($lock) {
            $query->lockForUpdate();
        } $notice = $query->first();
        throw_unless($notice, new HttpException(404, 'The escalation notice version is unavailable.'));

        return [$case, $escalation, $notice];
    }

    private function resolveResponse(User $actor, int $responseId, int $versionId, bool $lock): array
    {
        $response = CmsEscalationResponse::query()->with('escalation')->find($responseId);
        throw_unless($response, new HttpException(404, 'The escalation response is unavailable.'));
        $escalation = $this->show($actor, $response->cms_escalation_id);
        $case = $escalation->case;
        $query = CmsEscalationResponseVersion::query()->whereKey($versionId)->where('cms_escalation_response_id', $responseId);
        if ($lock) {
            $query->lockForUpdate();
        } $version = $query->first();
        throw_unless($version, new HttpException(404, 'The escalation response version is unavailable.'));

        return [$case, $escalation, $response, $version];
    }

    private function case(User $actor, int $id, bool $lock = false): CmsRecommendationCase
    {
        return $this->scope->resolveVisibleCase($actor, $id, 'cms.escalation.view', $lock);
    }

    private function authorizePreparation(User $actor, CmsRecommendationCase $case): void
    {
        $this->assertActorPermission($actor, 'cms.escalation.create');
        $this->assertUsable($actor);
        throw_unless((int) $actor->office_id !== (int) $case->lead_responsible_office_id, new HttpException(403, 'Responsible-office users cannot prepare escalation notices.'));
    }

    private function authorizeResponsibleResponse(User $actor, CmsRecommendationCase $case): void
    {
        $this->assertActorPermission($actor, 'cms.escalation.respond');
        $this->assertUsable($actor);
        throw_unless($actor->office_id && (int) $actor->office_id === (int) $case->lead_responsible_office_id, new HttpException(403, 'Only the responsible office may submit this response.'));
    }

    private function authorizeIndependent(User $actor, CmsRecommendationCase $case, ?int ...$excluded): void
    {
        $this->assertUsable($actor);
        $case->loadMissing('currentAssignment');
        $monitorAuthority = $case->currentAssignment?->user_id === $actor->id && $actor->hasAnyPermission(['cms.escalation.response-review', 'cms.escalation.response-accept']);
        $managementAuthority = $actor->hasGlobalEngagementAccess() && $actor->hasPermission('cms.escalation.review');
        throw_unless($managementAuthority || $monitorAuthority, new HttpException(403, 'Independent CIAS review authority is required.'));
        throw_unless(! in_array($actor->id, array_filter($excluded), true), new HttpException(403, 'Separation of duties prevents self-review.'));
        throw_unless((int) $actor->office_id !== (int) $case->lead_responsible_office_id, new HttpException(403, 'Responsible-office users cannot perform independent review.'));
    }

    private function assertEligible(User $actor, CmsRecommendationCase $case): void
    {
        $reasons = $this->eligibilityReasons($actor, $case, null);
        if ($reasons !== []) {
            throw ValidationException::withMessages(['escalation' => $reasons]);
        }
    }

    private function eligibilityReasons(User $actor, CmsRecommendationCase $case, ?CmsEscalation $active): array
    {
        $reasons = [];
        if (! in_array($case->status_code, [CmsRecommendationCase::STATUS_MONITORING, CmsRecommendationCase::STATUS_PARTIALLY_IMPLEMENTED], true)) {
            $reasons[] = 'Escalation creation is available only while the recommendation is in monitoring or partially implemented status.';
        } if ($active) {
            $reasons[] = 'An unresolved escalation already exists for this recommendation.';
        } if (! $actor->hasPermission('cms.escalation.create')) {
            $reasons[] = 'You do not have escalation preparation authority.';
        } if ((int) $actor->office_id === (int) $case->lead_responsible_office_id) {
            $reasons[] = 'Responsible-office users cannot prepare escalation notices.';
        }

        return $reasons;
    }

    private function assertTrigger(string $trigger, ?string $explanation): void
    {
        $trigger = strtoupper($trigger);
        throw_unless(in_array($trigger, CmsEscalation::TRIGGERS, true), ValidationException::withMessages(['primaryTriggerCode' => ['The escalation trigger is not supported.']]));
        if ($trigger === 'OTHER' && blank($explanation)) {
            throw ValidationException::withMessages(['additionalTriggerExplanation' => ['Explain the OTHER escalation trigger.']]);
        }
    }

    private function assertNoticeComplete(CmsEscalationNoticeVersion $notice): void
    {
        foreach (['subject', 'escalation_summary', 'basis_and_context', 'required_management_actions', 'required_response_contents'] as $field) {
            if (blank($notice->{$field})) {
                throw ValidationException::withMessages([$field => ['Complete this notice field before submission.']]);
            }
        } $this->assertDueDate($notice);
    }

    private function assertResponseComplete(CmsEscalationResponseVersion $version): void
    {
        foreach (['management_response_summary', 'root_cause_or_explanation', 'actions_completed', 'remaining_actions', 'committed_actions', 'responsible_person_or_office'] as $field) {
            if (blank($version->{$field})) {
                throw ValidationException::withMessages([$field => ['Complete this response field before submission.']]);
            }
        } if (blank($version->no_evidence_explanation) && $version->activeEvidenceLinks()->count() === 0) {
            throw ValidationException::withMessages(['evidence' => ['Link response evidence or explain why no evidence is available.']]);
        }
    }

    private function assertDueDate(CmsEscalationNoticeVersion $notice): void
    {
        throw_if(! $notice->response_due_date || $notice->response_due_date->isToday() || $notice->response_due_date->isPast(), ValidationException::withMessages(['responseDueDate' => ['The response due date must be after the issue date.']]));
    }

    private function assertDraftNotice(CmsEscalation $escalation, CmsEscalationNoticeVersion $notice, int $lock): void
    {
        $this->assertStatus($notice, CmsEscalationNoticeVersion::STATUS_DRAFT);
        $this->assertLock($notice, $lock);
        $this->assertLock($escalation, $lock);
    }

    private function assertStatus($model, string $status): void
    {
        throw_unless($model && $model->status_code === $status, new HttpException(409, "The record must be {$status}."));
    }

    private function assertLock($model, int $lock): void
    {
        throw_unless((int) $model->lock_version === $lock, new HttpException(409, 'The record changed since it was loaded. Reload the latest version.'));
    }

    private function assertCaseLock(CmsRecommendationCase $case, int $lock): void
    {
        $this->assertLock($case, $lock);
    }

    private function assertActorPermission(User $actor, string $permission): void
    {
        throw_unless($actor->hasPermission($permission), new HttpException(403, 'You do not have permission for this escalation action.'));
    }

    private function assertUsable(User $actor): void
    {
        throw_unless($this->scope->isUsableAccount($actor), new HttpException(403, 'The account is inactive, locked, or archived.'));
    }

    private function noticeAttributes(array $data, User $actor, int $version, bool $fromModel = false): array
    {
        return ['version_number' => $version, 'status_code' => CmsEscalationNoticeVersion::STATUS_DRAFT, 'active_slot' => 'ACTIVE', 'subject' => $data['subject'] ?? '', 'escalation_summary' => $data['escalationSummary'] ?? $data['escalation_summary'] ?? '', 'basis_and_context' => $data['basisAndContext'] ?? $data['basis_and_context'] ?? '', 'required_management_actions' => $data['requiredManagementActions'] ?? $data['required_management_actions'] ?? '', 'required_response_contents' => $data['requiredResponseContents'] ?? $data['required_response_contents'] ?? '', 'response_due_date' => $data['responseDueDate'] ?? $data['response_due_date'] ?? null, 'consequence_or_follow_up_statement' => $data['consequenceOrFollowUpStatement'] ?? $data['consequence_or_follow_up_statement'] ?? null, 'management_attention_requested' => $data['managementAttentionRequested'] ?? $data['management_attention_requested'] ?? true, 'additional_trigger_explanation' => $data['additionalTriggerExplanation'] ?? $data['additional_trigger_explanation'] ?? null, 'prepared_by' => $actor->id, 'lock_version' => 1];
    }

    private function responseAttributes(array $data, User $actor, int $version, bool $fromModel = false): array
    {
        return ['version_number' => $version, 'status_code' => CmsEscalationResponseVersion::STATUS_DRAFT, 'active_slot' => 'ACTIVE', 'management_response_summary' => $data['managementResponseSummary'] ?? $data['management_response_summary'] ?? '', 'root_cause_or_explanation' => $data['rootCauseOrExplanation'] ?? $data['root_cause_or_explanation'] ?? '', 'actions_completed' => $data['actionsCompleted'] ?? $data['actions_completed'] ?? '', 'remaining_actions' => $data['remainingActions'] ?? $data['remaining_actions'] ?? '', 'committed_actions' => $data['committedActions'] ?? $data['committed_actions'] ?? '', 'responsible_person_or_office' => $data['responsiblePersonOrOffice'] ?? $data['responsible_person_or_office'] ?? '', 'commitment_start_date' => $data['commitmentStartDate'] ?? $data['commitment_start_date'] ?? null, 'commitment_target_date' => $data['commitmentTargetDate'] ?? $data['commitment_target_date'] ?? null, 'resource_or_dependency_needs' => $data['resourceOrDependencyNeeds'] ?? $data['resource_or_dependency_needs'] ?? null, 'request_for_cias_guidance' => $data['requestForCiasGuidance'] ?? $data['request_for_cias_guidance'] ?? null, 'no_evidence_explanation' => $data['noEvidenceExplanation'] ?? $data['no_evidence_explanation'] ?? null, 'prepared_by' => $actor->id, 'lock_version' => 1];
    }

    private function sourceSnapshot(CmsRecommendationCase $case, User $actor): array
    {
        $case->loadMissing(['recommendation', 'leadResponsibleOffice', 'currentAssignment.user', 'unresolvedTargetDateExtensionRequest.currentVersion']);
        $progress = CmsProgressUpdate::query()->where('cms_recommendation_case_id', $case->id)->whereNotNull('recorded_version_id')->with('recordedVersion')->orderByDesc('reporting_period_end')->first();
        $validation = CmsValidationReview::query()->where('cms_recommendation_case_id', $case->id)->whereNotNull('finalized_version_id')->with('finalizedVersion')->orderByDesc('validation_sequence')->first();

        $wording = data_get($case->recommendation?->source_snapshot ?? [], 'recommendation.wording');

        return ['caseId' => $case->id, 'displayCode' => sprintf('CMS-REC-%06d', $case->id), 'recommendationWording' => $wording, 'responsibleOffice' => $case->leadResponsibleOffice?->only(['id', 'code', 'name', 'acronym']), 'originalTargetDate' => $case->recommendation?->original_target_implementation_date?->toDateString(), 'effectiveTargetDate' => $case->effective_target_implementation_date?->toDateString(), 'overdue' => $case->effective_target_implementation_date?->isPast() ?? false, 'overdueDays' => $case->effective_target_implementation_date?->isPast() ? CarbonImmutable::parse($case->effective_target_implementation_date)->diffInDays(now()) : 0, 'latestProgressUpdate' => $progress?->recordedVersion?->only(['id', 'version_number', 'management_reported_overall_percentage']), 'latestValidation' => $validation?->finalizedVersion?->only(['id', 'version_number', 'final_conclusion_code', 'validated_completion_percentage']), 'activeExtension' => $case->unresolvedTargetDateExtensionRequest?->currentVersion?->only(['id', 'status_code', 'requested_target_date']), 'activeComplianceMonitor' => $case->currentAssignment?->user?->only(['id', 'employee_id', 'name', 'initials']), 'priorEscalationCount' => CmsEscalation::query()->where('cms_recommendation_case_id', $case->id)->count(), 'actorId' => $actor->id, 'capturedAt' => now()->toISOString()];
    }

    private function caseContext(CmsRecommendationCase $case): array
    {
        $case->loadMissing(['recommendation', 'leadResponsibleOffice', 'currentAssignment.user']);

        return ['id' => $case->id, 'cmsRecommendationCode' => sprintf('CMS-REC-%06d', $case->id), 'status' => $case->status_code, 'lockVersion' => $case->lock_version, 'originalTargetDate' => $case->recommendation?->original_target_implementation_date?->toDateString(), 'effectiveTargetDate' => $case->effective_target_implementation_date?->toDateString(), 'responsibleOffice' => $case->leadResponsibleOffice?->only(['id', 'code', 'name', 'acronym']), 'overdue' => $case->effective_target_implementation_date?->isPast() ?? false];
    }

    private function recipientOptions(CmsRecommendationCase $case): array
    {
        $case->loadMissing('leadResponsibleOffice');
        $office = $case->leadResponsibleOffice;

        return $office ? [['recipientType' => 'PRIMARY', 'officeId' => $office->id, 'officeName' => $office->name]] : [];
    }

    private function resolveRecipients(CmsRecommendationCase $case, User $actor): array
    {
        $case->loadMissing('leadResponsibleOffice');
        $office = $case->leadResponsibleOffice;
        $head = $office ? User::query()->where('office_id', $office->id)->where('is_office_head', true)->where('is_active', true)->first() : null;
        $recipients = [];
        if ($head) {
            $recipients[] = ['recipient_type' => 'PRIMARY', 'office_id' => $office->id, 'user_id' => $head->id, 'recipient_name_snapshot' => $head->name, 'office_name_snapshot' => $office->name, 'position_or_role_snapshot' => $head->position, 'selected_by' => $actor->id, 'selected_at' => now()];
        } elseif ($office) {
            $recipients[] = ['recipient_type' => 'PRIMARY', 'office_id' => $office->id, 'user_id' => null, 'recipient_name_snapshot' => $office->name, 'office_name_snapshot' => $office->name, 'position_or_role_snapshot' => 'Responsible office', 'selected_by' => $actor->id, 'selected_at' => now()];
        } $management = User::query()->where('is_active', true)->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'cms.escalation.review'))->limit(25)->get();
        foreach ($management as $user) {
            if (! $head || $user->id !== $head->id) {
                $recipients[] = ['recipient_type' => 'INTERNAL_CIAS', 'office_id' => $user->office_id, 'user_id' => $user->id, 'recipient_name_snapshot' => $user->name, 'office_name_snapshot' => $user->office?->name, 'position_or_role_snapshot' => $user->position, 'selected_by' => $actor->id, 'selected_at' => now()];
            }
        }

        return $recipients;
    }

    private function permittedCaseActions(User $actor, CmsRecommendationCase $case, ?CmsEscalation $active): array
    {
        $actions = [];
        if (! $active && $actor->hasPermission('cms.escalation.create')) {
            $actions[] = 'create';
        }

        return $actions;
    }

    private function relations(): array
    {
        return ['case.recommendation', 'case.leadResponsibleOffice', 'case.currentAssignment.user', 'creator', 'currentNotice.preparer', 'currentNotice.reviewer', 'currentNotice.issuer', 'currentNotice.recipients', 'currentNotice.acknowledgements', 'currentNotice.activeEvidenceLinks.documentVersion', 'issuedNotice.recipients', 'issuedNotice.acknowledgements', 'response.currentVersion.preparer', 'response.currentVersion.submitter', 'response.currentVersion.reviewer', 'response.currentVersion.accepter', 'response.currentVersion.activeEvidenceLinks.documentVersion', 'resolution.resolver', 'noticeVersions.previousVersion', 'noticeVersions.recipients', 'noticeVersions.acknowledgements', 'noticeVersions.activeEvidenceLinks.documentVersion'];
    }

    private function responseRelations(): array
    {
        return ['escalation.case.recommendation', 'escalation.case.leadResponsibleOffice', 'versions.previousVersion', 'versions.activeEvidenceLinks.documentVersion', 'currentVersion.preparer', 'currentVersion.submitter', 'currentVersion.reviewer', 'currentVersion.accepter', 'acceptedVersion'];
    }

    private function record(Request $request, CmsRecommendationCase $case, CmsEscalation $escalation, string $event, string $action, ?string $previous, ?string $new): void
    {
        CmsRecommendationEvent::query()->create(['cms_recommendation_case_id' => $case->id, 'cms_recommendation_id' => $case->cms_recommendation_id, 'idempotency_key' => "cms-escalation:{$escalation->id}:{$event}:{$escalation->lock_version}", 'event_code' => $event, 'source_module' => 'CMS', 'actor_id' => $request->user()?->id, 'previous_status' => $previous, 'new_status' => $new, 'event_metadata' => ['escalationId' => $escalation->id, 'displayCode' => $escalation->display_code, 'primaryTrigger' => $escalation->primary_trigger_code, 'caseStatus' => $case->status_code]]);
        ActivityRecorder::record($request, $action, "CMS escalation {$event} for {$escalation->display_code}.", null, null, null, ['module' => 'CMS', 'caseId' => $case->id, 'escalationId' => $escalation->id, 'event' => $event]);
    }

    private function notify(CmsRecommendationCase $case, User $actor, string $title, string $message, string $dedupe): void
    {
        $ids = collect([$case->currentAssignment?->user_id])->filter();
        if ($case->lead_responsible_office_id) {
            $ids = $ids->merge(User::query()->where('office_id', $case->lead_responsible_office_id)->where('is_active', true)->pluck('id'));
        } $ids = $ids->merge(User::query()->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'cms.escalation.review'))->where('is_active', true)->pluck('id'));
        $this->notifications->send($ids->unique(), ['actorId' => $actor->id, 'type' => 'CMS_ESCALATION', 'category' => 'SYSTEM', 'priority' => 'HIGH', 'moduleCode' => 'CMS', 'title' => $title, 'message' => $message, 'actionUrl' => "/compliance-management/recommendations/{$case->id}", 'actionLabel' => 'Open escalation', 'subjectType' => CmsEscalation::class, 'subjectId' => $case->id, 'subjectCode' => sprintf('CMS-REC-%06d', $case->id), 'dedupeKey' => $dedupe]);
    }

    private function createEvidenceDocument(User $actor, CmsRecommendationCase $case, array $data, UploadedFile $file): array
    {
        $case->loadMissing('recommendation');
        $requested = MasterListItem::query()->findOrFail((int) $data['confidentialityLevelId']);
        $effective = $requested;
        $stored = ['original_file_name' => $file->getClientOriginalName(), 'storage_path' => $file->store('cms/escalations', 'local'), 'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream', 'file_extension' => $file->getClientOriginalExtension(), 'file_size' => $file->getSize(), 'checksum_sha256' => hash_file('sha256', $file->getRealPath())];
        $type = MasterList::query()->where('code', 'DOCUMENT_TYPE')->firstOrFail()->items()->where('code', 'OTHER')->firstOrFail();
        $document = Document::query()->create(['document_type_id' => $type->id, 'confidentiality_level_id' => $effective->id, 'title' => $data['title'], 'description' => $data['description'] ?? null, 'owner_module' => 'CMS', 'library_visible' => false, ...$stored, 'uploaded_by' => $actor->id, 'updated_by' => $actor->id, 'is_active' => true]);
        $document->forceFill(['document_code' => $this->runtime->formatNumber('document_number_format', $document->id)])->save();
        $version = $document->versions()->create(['version_number' => 1, 'version_label' => 'CMS escalation evidence version 1', 'change_summary' => 'Initial escalation evidence upload.', ...$stored, 'uploaded_by' => $actor->id]);
        $document->forceFill(['current_version_id' => $version->id, 'version' => $version->version_label])->save();

        return ['document_id' => $document->id, 'document_version_id' => $version->id, 'evidence_category' => strtoupper($data['evidenceCategory']), 'title' => $data['title'], 'description' => $data['description'] ?? null, 'source_or_custodian' => $data['sourceOrCustodian'] ?? null, 'checksum_sha256' => $version->checksum_sha256, 'confidentiality_level_id' => $effective->id, 'confidentiality_code_snapshot' => $effective->code];
    }
}
