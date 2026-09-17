<?php

namespace App\Services;

use App\Models\AemsEvidenceAssessment;
use App\Models\AemsEvidenceRequest;
use App\Models\AemsEvidenceRequestEvidence;
use App\Models\AemsEvidenceRequestResponse;
use App\Models\AemsEvidenceRequestVersion;
use App\Models\AuditEngagement;
use App\Models\AuditEvidence;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\EngagementEvent;
use App\Models\MasterList;
use App\Models\MasterListItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Governs evidence requests, exact received-document links, and assessments. */
class AemsEvidenceRequestService
{
    private const ASSESSMENT_DIMENSIONS = [
        'sufficiency', 'appropriateness', 'relevance', 'reliability',
        'competence', 'accuracy', 'completeness', 'corroboration',
        'contradiction', 'authenticity', 'integrity',
    ];

    private const REQUEST_OPEN_STATUSES = [
        'SENT', 'ACKNOWLEDGED', 'PARTIALLY_RECEIVED', 'OVERDUE',
        'EXTENDED', 'ESCALATED', 'EXTENSION_REQUESTED',
    ];

    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly AemsNotificationService $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(Request $request, AuditEngagement $engagement): array
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.evidence-request.view');
        $requests = AemsEvidenceRequest::query()
            ->visibleTo($request->user())
            ->where('audit_engagement_id', $engagement->id)
            ->with($this->requestRelations())
            ->orderBy('request_code')
            ->get();
        $assessments = AemsEvidenceAssessment::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('is_current_revision', true)
            ->with(['evidence', 'request', 'documentVersion', 'assessor', 'exceptionApprover'])
            ->orderByDesc('id')
            ->get();

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
            ],
            'requestStatuses' => AemsEvidenceRequest::STATUSES,
            'requestReferences' => [
                'offices' => $engagement->offices()->orderBy('name')->get(['offices.id', 'offices.code', 'offices.name'])->map(fn ($office): array => ['id' => $office->id, 'code' => $office->code, 'name' => $office->name])->values(),
                'users' => User::query()->where('is_active', true)->where(function ($query) use ($engagement): void {
                    $query->whereIn('office_id', $engagement->offices()->pluck('offices.id'))
                        ->orWhereIn('id', $engagement->teamMembers()->where('is_active', true)->whereNull('ended_at')->pluck('user_id'));
                })->orderBy('name')->get(['id', 'name', 'office_id'])->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'officeId' => $user->office_id])->values(),
            ],
            'requestActions' => ['SUBMIT', 'SEND', 'ACKNOWLEDGE', 'MARK_OVERDUE', 'REQUEST_EXTENSION', 'APPROVE_EXTENSION', 'REJECT_EXTENSION', 'ESCALATE', 'MARK_PARTIALLY_RECEIVED', 'MARK_RECEIVED', 'FOR_REVIEW', 'ASSESS', 'CLOSE_WITHOUT_SUBMISSION', 'CANCEL', 'CLOSE'],
            'assessmentStatuses' => AemsEvidenceAssessment::STATUSES,
            'requests' => $requests->map(fn (AemsEvidenceRequest $item): array => $this->requestData($item))->values(),
            'assessments' => $assessments->map(fn (AemsEvidenceAssessment $item): array => $this->assessmentData($item))->values(),
            'evidence' => AuditEvidence::query()
                ->visibleTo($request->user())
                ->where('audit_engagement_id', $engagement->id)
                ->where('is_current_revision', true)
                ->with('currentAssessment')
                ->orderBy('evidence_code')
                ->get()
                ->map(fn (AuditEvidence $item): array => $this->evidenceSummary($item))
                ->values(),
        ];
    }

    /** @return array<string, mixed> */
    public function cmsWorkspace(Request $request): array
    {
        $user = $request->user();
        throw_unless(
            $user->hasPermission('aems.evidence-request.view')
                && $user->hasPermission('access.auditee_scope'),
            new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'You do not have auditee Evidence Request access.'),
        );

        $requests = AemsEvidenceRequest::query()
            ->whereNotIn('status', ['DRAFT', 'SUBMITTED', 'CANCELLED'])
            ->where(function ($query) use ($user): void {
                $query
                    ->where('requested_from_user_id', $user->id)
                    ->orWhere(function ($office) use ($user): void {
                        $office->whereNull('requested_from_user_id')
                            ->where('requested_from_office_id', $user->office_id);
                    })
                    ->orWhere(function ($office) use ($user): void {
                        $office->whereNull('requested_from_user_id')
                            ->where('requested_from_office_id', $user->office_id);
                    })
                    ->orWhere(function ($unassigned) use ($user): void {
                        $unassigned->whereNull('requested_from_user_id')
                            ->whereNull('requested_from_office_id')
                            ->whereHas('engagement.offices', fn ($office) => $office->whereKey($user->office_id));
                    });
            })
            ->with($this->requestRelations())
            ->with('engagement:id,engagement_code,title,status')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->get();

        return [
            'requests' => $requests->map(function (AemsEvidenceRequest $record): array {
                $data = $this->requestData($record);
                $data['engagement'] = [
                    'id' => $record->engagement?->id,
                    'engagementCode' => $record->engagement?->engagement_code,
                    'title' => $record->engagement?->title,
                    'status' => $record->engagement?->status,
                ];
                return $data;
            })->values(),
        ];
    }

    public function respondWithEvidence(
        Request $request,
        AemsEvidenceRequest $record,
        array $attributes,
        UploadedFile $file,
    ): AemsEvidenceRequestResponse {
        $record = $record->fresh(['engagement']);
        $this->access->authorizeEvidenceRequestResponse($request->user(), $record);
        if (! in_array($record->status, self::REQUEST_OPEN_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['Evidence can only be submitted while this request is open.'],
            ]);
        }
        $stored = $this->storeResponseFile($file, $record->engagement);

        try {
            return DB::transaction(function () use ($request, $record, $attributes, $stored): AemsEvidenceRequestResponse {
                $locked = $this->lockRequest($record->engagement, $record, (int) $attributes['lockVersion']);
                $this->access->authorizeEvidenceRequestResponse($request->user(), $locked);
                if (! in_array($locked->status, self::REQUEST_OPEN_STATUSES, true)) {
                    throw ValidationException::withMessages(['status' => ['Evidence can only be submitted while this request is open.']]);
                }
                $this->ensureFieldworkAvailable($locked->engagement);

                $documentType = MasterList::query()->where('code', 'DOCUMENT_TYPE')->firstOrFail()
                    ->items()->where('code', 'OTHER')->firstOrFail();
                $classification = MasterList::query()->where('code', 'DOCUMENT_CONFIDENTIALITY')->firstOrFail()
                    ->items()->where('code', 'INTERNAL')->firstOrFail();
                $category = MasterList::query()->where('code', 'AEMS_EVIDENCE_CATEGORY')->firstOrFail()
                    ->items()->where('code', 'DOCUMENTARY')->firstOrFail();
                $source = MasterList::query()->where('code', 'AEMS_EVIDENCE_SOURCE_TYPE')->firstOrFail()
                    ->items()->where('code', 'AUDITEE')->firstOrFail();

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
                    'document_code' => app(RuntimeConfiguration::class)->formatNumber('document_number_format', $document->id),
                ])->save();
                $documentVersion = $document->versions()->create([
                    'version_number' => 1,
                    'version_label' => 'Evidence Request response version 1',
                    'change_summary' => 'Auditee response to an AEMS Evidence Request.',
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
                    'record_id' => $locked->engagement->id,
                    'record_code' => $locked->engagement->engagement_code,
                    'record_label' => "{$locked->engagement->engagement_code} — {$locked->engagement->title}",
                    'linked_by' => $request->user()->id,
                ]);

                $evidence = AuditEvidence::query()->create([
                    'evidence_family_uuid' => (string) Str::uuid(),
                    'version_number' => 1,
                    'is_current_revision' => true,
                    'audit_engagement_id' => $locked->engagement->id,
                    'evidence_code' => $this->nextEvidenceCode($locked->engagement),
                    'title' => $attributes['title'],
                    'evidence_category_id' => $category->id,
                    'evidence_source_type_id' => $source->id,
                    'source_description' => $attributes['sourceDescription'],
                    'date_obtained' => $attributes['dateObtained'],
                    'custodian_name' => $request->user()->name,
                    'custodian_office_id' => $request->user()->office_id,
                    'confidentiality_level_id' => $classification->id,
                    'document_version_id' => $documentVersion->id,
                    'checksum_sha256' => $stored['checksum_sha256'],
                    'status' => 'DRAFT',
                    'outcome' => 'REGISTERED',
                    'assessment_required' => true,
                    'acquisition_method' => 'REQUESTED',
                    'acquisition_form' => 'ELECTRONIC',
                    'uploaded_by' => $request->user()->id,
                    'lock_version' => 1,
                ]);
                $response = AemsEvidenceRequestResponse::query()->create([
                    'evidence_request_id' => $locked->id,
                    'audit_evidence_id' => $evidence->id,
                    'document_version_id' => $documentVersion->id,
                    'submitted_by' => $request->user()->id,
                    'submitted_at' => now(),
                    'response_note' => $attributes['responseNote'] ?? null,
                ]);
                $locked->update(['lock_version' => $locked->lock_version + 1]);
                $this->event($request, $locked->engagement, $locked, 'RESPONSE_SUBMITTED', $locked->status, $locked->status, $attributes['responseNote'] ?? null, [$documentVersion->id]);
                $this->support->audit(
                    $request,
                    'aems.evidence-request.response_submitted',
                    $locked->engagement,
                    null,
                    $this->auditValues($locked),
                    ['evidenceRequestId' => $locked->id, 'evidenceId' => $evidence->id, 'documentVersionId' => $documentVersion->id],
                );
                $this->notifications->evidenceRequestTransition($request, $locked->engagement, $locked, 'RESPONSE_SUBMITTED');

                return $response->fresh(['evidence.documentVersion', 'documentVersion', 'submitter']);
            }, 3);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($stored['storage_path']);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $attributes */
    public function create(Request $request, AuditEngagement $engagement, array $attributes): AemsEvidenceRequest
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.evidence-request.create');
        $record = DB::transaction(function () use ($request, $engagement, $attributes): AemsEvidenceRequest {
            $this->assertOfficeAndUser($engagement, $attributes);
            $record = AemsEvidenceRequest::query()->create([
                'request_family_uuid' => (string) Str::uuid(),
                'audit_engagement_id' => $engagement->id,
                'request_code' => $this->nextCode($engagement),
                ...$this->requestAttributes($attributes),
                'status' => 'DRAFT',
                'current_version_number' => 1,
                'prepared_by' => $request->user()->id,
                'lock_version' => 1,
                'is_active' => true,
            ]);
            $this->createVersion($request, $record, $attributes, 1, null);
            $this->event($request, $engagement, $record, 'CREATED', null, 'DRAFT', null);
            $this->support->audit($request, 'aems.evidence-request.created', $engagement, null, $this->auditValues($record), ['evidenceRequestId' => $record->id]);
            return $record;
        }, 3);
        return $this->loadRequest($record);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Request $request, AuditEngagement $engagement, AemsEvidenceRequest $record, array $attributes): AemsEvidenceRequest
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.evidence-request.update');
        $record = DB::transaction(function () use ($request, $engagement, $record, $attributes): AemsEvidenceRequest {
            $locked = $this->lockRequest($engagement, $record, (int) $attributes['lockVersion']);
            if ($locked->status !== 'DRAFT') {
                throw ValidationException::withMessages(['status' => ['Only draft Evidence Requests can be edited.']]);
            }
            $this->assertOfficeAndUser($engagement, $attributes);
            $number = $locked->current_version_number + 1;
            $before = $this->auditValues($locked);
            $this->createVersion($request, $locked, $attributes, $number, $attributes['changeReason'] ?? null);
            $locked->update([
                ...$this->requestAttributes($attributes),
                'current_version_number' => $number,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($request, $engagement, $locked, 'VERSION_CREATED', 'DRAFT', 'DRAFT', $attributes['changeReason'] ?? null);
            $this->support->audit($request, 'aems.evidence-request.version_created', $engagement, $before, $this->auditValues($locked), ['evidenceRequestId' => $locked->id, 'changeReason' => $attributes['changeReason'] ?? null]);
            return $locked;
        }, 3);
        return $this->loadRequest($record);
    }

    public function transition(Request $request, AuditEngagement $engagement, AemsEvidenceRequest $record, string $action, int $lockVersion, ?string $comment, array $attributes = []): AemsEvidenceRequest
    {
        $permission = match ($action) {
            'SUBMIT' => 'aems.evidence-request.submit',
            'SEND' => 'aems.evidence-request.send',
            'MARK_PARTIALLY_RECEIVED', 'MARK_RECEIVED' => 'aems.evidence-request.receive',
            'FOR_REVIEW', 'ASSESS' => 'aems.evidence-request.assess',
            'ACKNOWLEDGE' => 'aems.evidence-request.acknowledge',
            'REQUEST_EXTENSION' => 'aems.evidence-request.extend',
            'APPROVE_EXTENSION' => 'aems.evidence-request.extension_approve',
            'REJECT_EXTENSION' => 'aems.evidence-request.extension_approve',
            'MARK_OVERDUE' => 'aems.evidence-request.overdue',
            'ESCALATE' => 'aems.evidence-request.escalate',
            'CANCEL' => 'aems.evidence-request.cancel',
            'CLOSE_WITHOUT_SUBMISSION', 'CLOSE' => 'aems.evidence-request.close',
            default => throw ValidationException::withMessages(['action' => ['Unsupported Evidence Request action.']]),
        };
        if ($action === 'ACKNOWLEDGE') {
            $this->access->authorizeEvidenceRequestAcknowledgement($request->user(), $record->fresh(['engagement']));
        } else {
            $this->access->authorizeEngagementAction($request->user(), $engagement, $permission, $record->prepared_by);
        }
        $record = DB::transaction(function () use ($request, $engagement, $record, $action, $lockVersion, $comment, $attributes): AemsEvidenceRequest {
            $locked = $this->lockRequest($engagement, $record, $lockVersion);
            $from = $locked->status;
            $to = match ($action) {
                'SUBMIT' => $from === 'DRAFT' ? 'SUBMITTED' : null,
                'SEND' => $from === 'SUBMITTED' ? 'SENT' : null,
                'ACKNOWLEDGE' => $from === 'SENT' ? 'ACKNOWLEDGED' : null,
                'MARK_PARTIALLY_RECEIVED' => in_array($from, ['SENT', 'ACKNOWLEDGED', 'PARTIALLY_RECEIVED', 'OVERDUE', 'EXTENDED', 'ESCALATED'], true) ? 'PARTIALLY_RECEIVED' : null,
                'MARK_RECEIVED' => in_array($from, ['SENT', 'ACKNOWLEDGED', 'PARTIALLY_RECEIVED', 'OVERDUE', 'EXTENDED', 'ESCALATED'], true) ? 'RECEIVED' : null,
                'FOR_REVIEW' => $from === 'RECEIVED' ? 'FOR_REVIEW' : null,
                'ASSESS' => in_array($from, ['RECEIVED', 'FOR_REVIEW'], true) ? 'ASSESSED' : null,
                'REQUEST_EXTENSION' => in_array($from, ['SENT', 'ACKNOWLEDGED', 'OVERDUE', 'ESCALATED'], true) ? 'EXTENSION_REQUESTED' : null,
                'APPROVE_EXTENSION' => $from === 'EXTENSION_REQUESTED' ? 'EXTENDED' : null,
                'REJECT_EXTENSION' => $from === 'EXTENSION_REQUESTED' ? 'SENT' : null,
                'MARK_OVERDUE' => in_array($from, ['SENT', 'ACKNOWLEDGED', 'EXTENDED'], true) ? 'OVERDUE' : null,
                'ESCALATE' => $from === 'OVERDUE' ? 'ESCALATED' : null,
                'CANCEL' => in_array($from, ['DRAFT', 'SUBMITTED', ...self::REQUEST_OPEN_STATUSES], true) ? 'CANCELLED' : null,
                'CLOSE_WITHOUT_SUBMISSION' => in_array($from, ['SENT', 'ACKNOWLEDGED', 'PARTIALLY_RECEIVED', 'OVERDUE', 'EXTENDED', 'ESCALATED'], true) ? 'CLOSED_WITHOUT_SUBMISSION' : null,
                'CLOSE' => $from === 'ASSESSED' ? 'CLOSED' : null,
            };
            if (! $to) {
                throw ValidationException::withMessages(['status' => ["{$action} is not available while the request is {$from}."]]);
            }
            if (in_array($action, ['MARK_PARTIALLY_RECEIVED', 'MARK_RECEIVED', 'ASSESS'], true)
                && $locked->evidenceLinks->isEmpty()) {
                throw ValidationException::withMessages(['evidence' => ['Link at least one exact Evidence/Core Document Version first.']]);
            }
            if ($action === 'ASSESS') {
                $this->ensureRequestEvidenceAssessed($locked);
            }
            if (in_array($action, ['CLOSE', 'CLOSE_WITHOUT_SUBMISSION', 'CANCEL', 'ESCALATE', 'REQUEST_EXTENSION'], true) && mb_strlen(trim((string) $comment)) < 5) {
                throw ValidationException::withMessages(['comment' => ['A closure reason is required.']]);
            }
            if ($action === 'MARK_OVERDUE' && (! $locked->due_date || $locked->due_date->isFuture())) {
                throw ValidationException::withMessages(['dueDate' => ['A request can be marked overdue only after its due date.']]);
            }
            if ($action === 'REQUEST_EXTENSION') {
                $requestedDate = $attributes['extensionDueDate'] ?? null;
                if (! $requestedDate || now()->startOfDay()->gte(\Illuminate\Support\Carbon::parse($requestedDate))) {
                    throw ValidationException::withMessages(['extensionDueDate' => ['A future extension due date is required.']]);
                }
            }
            if ($action === 'APPROVE_EXTENSION' && ! $locked->extension_requested_due_date) {
                throw ValidationException::withMessages(['extensionDueDate' => ['No extension request is pending.']]);
            }
            $changes = ['status' => $to, 'lock_version' => $locked->lock_version + 1];
            if ($action === 'SUBMIT') { $changes += ['submitted_by' => $request->user()->id, 'submitted_at' => now()]; }
            if ($action === 'SEND') { $changes += ['sent_by' => $request->user()->id, 'sent_at' => now()]; }
            if ($action === 'MARK_PARTIALLY_RECEIVED') { $changes['partially_received_at'] = now(); }
            if ($action === 'MARK_RECEIVED') { $changes['received_at'] = now(); }
            if ($action === 'ASSESS') { $changes['assessed_at'] = now(); }
            if ($action === 'ACKNOWLEDGE') { $changes += ['acknowledged_by' => $request->user()->id, 'acknowledged_at' => now(), 'acknowledgement_note' => $comment]; }
            if ($action === 'REQUEST_EXTENSION') { $changes += ['extension_requested_due_date' => $attributes['extensionDueDate'], 'extension_requested_by' => $request->user()->id, 'extension_requested_at' => now(), 'extension_reason' => $comment]; }
            if ($action === 'APPROVE_EXTENSION') { $changes += ['extension_due_date' => $locked->extension_requested_due_date, 'extension_approved_by' => $request->user()->id, 'extension_approved_at' => now(), 'due_date' => $locked->extension_requested_due_date]; }
            if ($action === 'MARK_OVERDUE') { $changes['overdue_at'] = now(); }
            if ($action === 'ESCALATE') { $changes += ['escalated_by' => $request->user()->id, 'escalated_at' => now(), 'escalation_reason' => $comment]; }
            if ($action === 'CANCEL') { $changes += ['cancelled_by' => $request->user()->id, 'cancelled_at' => now(), 'cancellation_reason' => $comment, 'closure_type' => 'CANCELLED', 'closed_at' => now(), 'closed_by' => $request->user()->id, 'closure_reason' => $comment]; }
            if ($action === 'CLOSE_WITHOUT_SUBMISSION') { $changes += ['closed_at' => now(), 'closed_by' => $request->user()->id, 'closure_reason' => $comment, 'closure_type' => 'WITHOUT_SUBMISSION']; }
            if ($action === 'CLOSE') { $changes += ['closed_at' => now(), 'closed_by' => $request->user()->id, 'closure_reason' => $comment, 'closure_type' => 'ASSESSED']; }
            $locked->update($changes);
            $this->event($request, $engagement, $locked, $action, $from, $to, $comment);
            $this->support->audit($request, 'aems.evidence-request.'.str($action)->lower(), $engagement, ['status' => $from], $this->auditValues($locked), ['evidenceRequestId' => $locked->id, 'comment' => $comment]);
            $this->notifications->evidenceRequestTransition($request, $engagement, $locked, $action);
            return $locked;
        }, 3);
        return $this->loadRequest($record);
    }

    /** @param array<string, mixed> $attributes */
    public function receiveEvidence(Request $request, AuditEngagement $engagement, AemsEvidenceRequest $record, array $attributes): AemsEvidenceRequestEvidence
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.evidence-request.receive', $record->prepared_by);
        return DB::transaction(function () use ($request, $engagement, $record, $attributes): AemsEvidenceRequestEvidence {
            $locked = $this->lockRequest($engagement, $record, (int) $attributes['lockVersion']);
            if (! in_array($locked->status, ['SENT', 'ACKNOWLEDGED', 'PARTIALLY_RECEIVED', 'OVERDUE', 'EXTENDED', 'ESCALATED'], true)) {
                throw ValidationException::withMessages(['status' => ['Evidence can only be received after the request is sent.']]);
            }
            $evidence = AuditEvidence::query()->where('audit_engagement_id', $engagement->id)->whereKey((int) $attributes['evidenceId'])->whereNot('status', 'VOIDED')->with('documentVersion')->first();
            if (! $evidence) throw ValidationException::withMessages(['evidenceId' => ['Evidence does not belong to this engagement or is voided.']]);
            if ((int) $evidence->document_version_id !== (int) $attributes['documentVersionId']) throw ValidationException::withMessages(['documentVersionId' => ['The request link must pin the Evidence row to its exact Core Document Version.']]);
            $version = DocumentVersion::query()->whereKey((int) $attributes['documentVersionId'])->where('document_id', $evidence->documentVersion->document_id)->first();
            if (! $version) throw ValidationException::withMessages(['documentVersionId' => ['The Core Document Version is not part of this Evidence record.']]);
            $link = AemsEvidenceRequestEvidence::query()->firstOrCreate(
                ['evidence_request_id' => $locked->id, 'audit_evidence_id' => $evidence->id, 'document_version_id' => $version->id],
                ['received_by' => $request->user()->id, 'received_at' => now(), 'receipt_notes' => $attributes['receiptNotes'] ?? null, 'receipt_status' => 'RECEIVED', 'receipt_outcome' => $attributes['receiptOutcome'] ?? null, 'received_form' => $attributes['receivedForm'] ?? null, 'acquisition_method' => $attributes['acquisitionMethod'] ?? null],
            );
            $locked->increment('lock_version');
            $this->event($request, $engagement, $locked, 'EVIDENCE_LINKED', $locked->status, $locked->status, $attributes['receiptNotes'] ?? null, [$version->id]);
            $this->support->audit($request, 'aems.evidence-request.evidence_linked', $engagement, null, $this->auditValues($locked), ['evidenceRequestId' => $locked->id, 'evidenceId' => $evidence->id, 'documentVersionId' => $version->id]);
            return $link->fresh(['evidence', 'documentVersion', 'receiver']);
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function assessEvidence(Request $request, AuditEngagement $engagement, array $attributes): AemsEvidenceAssessment
    {
        $assessment = DB::transaction(function () use ($request, $engagement, $attributes): AemsEvidenceAssessment {
            $evidence = AuditEvidence::query()->where('audit_engagement_id', $engagement->id)->whereKey((int) $attributes['evidenceId'])->whereNot('status', 'VOIDED')->with('documentVersion')->first();
            if (! $evidence) throw ValidationException::withMessages(['evidenceId' => ['Evidence does not belong to this engagement or is voided.']]);
            if (! in_array($evidence->status, ['VERIFIED', 'LOCKED'], true)) throw ValidationException::withMessages(['evidenceId' => ['Only verified or locked Evidence can be professionally assessed.']]);
            $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.evidence.assess', $evidence->uploaded_by);
            if ((int) $evidence->document_version_id !== (int) $attributes['documentVersionId']) throw ValidationException::withMessages(['documentVersionId' => ['Assessment must cite the exact current Core Document Version.']]);
            $requestRecord = null;
            if (! empty($attributes['evidenceRequestId'])) {
                $requestRecord = AemsEvidenceRequest::query()->where('audit_engagement_id', $engagement->id)->find((int) $attributes['evidenceRequestId']);
                if (! $requestRecord) throw ValidationException::withMessages(['evidenceRequestId' => ['Evidence Request does not belong to this engagement.']]);
                $linked = AemsEvidenceRequestEvidence::query()
                    ->where('evidence_request_id', $requestRecord->id)
                    ->where('audit_evidence_id', $evidence->id)
                    ->where('document_version_id', $evidence->document_version_id)
                    ->exists();
                if (! $linked) throw ValidationException::withMessages(['evidenceRequestId' => ['The assessment request must already contain the exact received Evidence/Core Document Version.']]);
            }
            if (($attributes['exceptionRequired'] ?? false) && blank($attributes['exceptionReason'] ?? null)) throw ValidationException::withMessages(['exceptionReason' => ['An exception reason is required when exception approval is requested.']]);
            $current = $evidence->currentAssessment()->first();
            $number = $current ? $current->version_number + 1 : 1;
            if ($current) {
                if (empty($attributes['changeReason'])) throw ValidationException::withMessages(['changeReason' => ['A correction reason is required for a new assessment version.']]);
                $this->supersedeAssessment($current);
            }
            $assessment = AemsEvidenceAssessment::query()->create([
                'assessment_family_uuid' => $current?->assessment_family_uuid ?? (string) Str::uuid(),
                'audit_engagement_id' => $engagement->id,
                'audit_evidence_id' => $evidence->id,
                'evidence_request_id' => $requestRecord?->id,
                'document_version_id' => $evidence->document_version_id,
                'version_number' => $number,
                'supersedes_assessment_id' => $current?->id,
                'is_current_revision' => true,
                'status' => 'ASSESSED',
                ...$this->assessmentAttributes($attributes),
                'assessed_by' => $request->user()->id,
                'assessed_at' => now(),
                'change_reason' => $attributes['changeReason'] ?? null,
                'lock_version' => 1,
            ]);
            $assessmentEligible = $this->assessmentValuesForEligibility($assessment);
            if (($attributes['evidenceOutcome'] ?? null) === 'ACCEPTED' && ! $assessmentEligible) {
                throw ValidationException::withMessages(['evidenceOutcome' => ['Evidence cannot be marked accepted while its professional assessment is negative, incomplete, restricted, or unresolved.']]);
            }
            $evidence->forceFill([
                'outcome' => $attributes['evidenceOutcome'] ?? ($assessmentEligible ? 'ACCEPTED' : (($assessment->is_restricted || filled($assessment->limitations)) ? 'LIMITED' : 'ADDITIONAL_REQUIRED')),
            ])->save();
            $this->support->event($request, $engagement, 'EVIDENCE_ASSESSED', null, 'ASSESSED', $current ? ['versionNumber' => $current->version_number] : null, $this->assessmentValues($assessment), $attributes['changeReason'] ?? null, 'AEMS_EVIDENCE_ASSESSMENT', $assessment->id, $number, $evidence->evidence_code, $assessment->assessment_family_uuid, [$assessment->document_version_id]);
            $this->support->audit($request, 'aems.evidence.assessed', $engagement, null, $this->assessmentValues($assessment), ['evidenceId' => $evidence->id, 'assessmentId' => $assessment->id]);
            $this->notifications->evidenceAssessmentRecorded($request, $engagement, $assessment);
            return $assessment;
        }, 3);
        return $assessment->fresh(['evidence', 'request', 'documentVersion', 'assessor', 'exceptionApprover']);
    }

    public function approveException(Request $request, AuditEngagement $engagement, AemsEvidenceAssessment $assessment, int $lockVersion, string $comment): AemsEvidenceAssessment
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.evidence.exception_approve', $assessment->assessed_by);
        $updated = DB::transaction(function () use ($request, $engagement, $assessment, $lockVersion, $comment): AemsEvidenceAssessment {
            $locked = AemsEvidenceAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
            if ((int) $locked->audit_engagement_id !== (int) $engagement->id || $locked->lock_version !== $lockVersion) throw ValidationException::withMessages(['lockVersion' => ['This assessment changed in another session. Refresh before continuing.']]);
            if (! $locked->is_current_revision || ! $locked->exception_required) throw ValidationException::withMessages(['assessment' => ['Only a current assessment requiring an exception can be approved.']]);
            if (mb_strlen(trim($comment)) < 5) throw ValidationException::withMessages(['comment' => ['A clear exception approval explanation is required.']]);
            $this->supersedeAssessment($locked);
            $revision = AemsEvidenceAssessment::query()->create([
                'assessment_family_uuid' => $locked->assessment_family_uuid,
                'audit_engagement_id' => $locked->audit_engagement_id,
                'audit_evidence_id' => $locked->audit_evidence_id,
                'evidence_request_id' => $locked->evidence_request_id,
                'document_version_id' => $locked->document_version_id,
                'version_number' => $locked->version_number + 1,
                'supersedes_assessment_id' => $locked->id,
                'is_current_revision' => true,
                'status' => 'ASSESSED',
                ...$this->assessmentSnapshotAttributes($locked),
                'exception_approved_by' => $request->user()->id,
                'exception_approved_at' => now(),
                'exception_approval_comment' => $comment,
                'assessed_by' => $locked->assessed_by,
                'assessed_at' => $locked->assessed_at,
                'change_reason' => 'Authorized evidence exception approval: '.$comment,
                'lock_version' => 1,
            ]);
            $revision->load(['evidence', 'request', 'documentVersion', 'assessor', 'exceptionApprover']);
            if ($revision->evidence) {
                $revision->evidence->forceFill(['outcome' => 'ACCEPTED'])->save();
            }
            $this->support->event($request, $engagement, 'EVIDENCE_EXCEPTION_APPROVED', 'ASSESSED', 'ASSESSED', ['versionNumber' => $locked->version_number], $this->assessmentValues($revision), $comment, 'AEMS_EVIDENCE_ASSESSMENT', $revision->id, $revision->version_number, $revision->evidence?->evidence_code, $revision->assessment_family_uuid, [$revision->document_version_id]);
            $this->support->audit($request, 'aems.evidence.exception_approved', $engagement, null, $this->assessmentValues($revision), ['assessmentId' => $revision->id, 'supersedesAssessmentId' => $locked->id]);
            $this->notifications->evidenceAssessmentRecorded($request, $engagement, $revision, 'EXCEPTION_APPROVED');
            return $revision;
        }, 3);
        return $updated->fresh(['evidence', 'request', 'documentVersion', 'assessor', 'exceptionApprover']);
    }

    /** @return array<string, mixed> */
    public function requestData(AemsEvidenceRequest $record): array
    {
        $record = $this->loadRequest($record);
        return [
            'id' => $record->id, 'familyUuid' => $record->request_family_uuid,
            'requestCode' => $record->request_code, 'title' => $record->title,
            'purpose' => $record->purpose, 'status' => $record->status,
            'dueDate' => $record->due_date?->toDateString(),
            'currentVersionNumber' => $record->current_version_number,
            'requestedFromOffice' => $record->requestedFromOffice?->only(['id', 'code', 'name']),
            'requestedFromUser' => $this->user($record->requestedFromUser),
            'preparedBy' => $this->user($record->preparer), 'submittedBy' => $this->user($record->submitter),
            'sentBy' => $this->user($record->sender), 'closedBy' => $this->user($record->closer),
            'acknowledgedBy' => $this->user($record->acknowledger), 'acknowledgedAt' => $record->acknowledged_at?->toIso8601String(), 'acknowledgementNote' => $record->acknowledgement_note,
            'extensionRequestedBy' => $this->user($record->extensionRequester), 'extensionRequestedAt' => $record->extension_requested_at?->toIso8601String(), 'extensionRequestedDueDate' => $record->extension_requested_due_date?->toDateString(),
            'extensionApprovedBy' => $this->user($record->extensionApprover), 'extensionApprovedAt' => $record->extension_approved_at?->toIso8601String(), 'extensionDueDate' => $record->extension_due_date?->toDateString(), 'extensionReason' => $record->extension_reason,
            'overdueAt' => $record->overdue_at?->toIso8601String(), 'escalatedBy' => $this->user($record->escalator), 'escalatedAt' => $record->escalated_at?->toIso8601String(), 'escalationReason' => $record->escalation_reason,
            'cancelledBy' => $this->user($record->canceller), 'cancelledAt' => $record->cancelled_at?->toIso8601String(), 'cancellationReason' => $record->cancellation_reason, 'closureType' => $record->closure_type,
            'submittedAt' => $record->submitted_at?->toIso8601String(), 'sentAt' => $record->sent_at?->toIso8601String(),
            'partiallyReceivedAt' => $record->partially_received_at?->toIso8601String(), 'receivedAt' => $record->received_at?->toIso8601String(),
            'assessedAt' => $record->assessed_at?->toIso8601String(), 'closedAt' => $record->closed_at?->toIso8601String(),
            'closureReason' => $record->closure_reason, 'lockVersion' => $record->lock_version,
            'events' => $record->events->map(fn ($event): array => ['id' => $event->id, 'eventType' => $event->event_type, 'fromStatus' => $event->from_status, 'toStatus' => $event->to_status, 'reason' => $event->reason, 'actor' => $this->user($event->actor), 'createdAt' => $event->created_at?->toIso8601String(), 'versionNumber' => $event->version_number])->values(),
            'latestVersion' => $record->latestVersion ? $this->versionData($record->latestVersion) : null,
            'versions' => $record->versions->map(fn (AemsEvidenceRequestVersion $version): array => $this->versionData($version))->values(),
            'evidence' => $record->evidenceLinks->map(fn (AemsEvidenceRequestEvidence $link): array => $this->linkData($link))->values(),
            'responses' => $record->responses->map(fn (AemsEvidenceRequestResponse $response): array => $this->responseData($response))->values(),
        ];
    }

    /** @return array<string, mixed> */
    public function assessmentData(AemsEvidenceAssessment $assessment): array
    {
        $assessment->loadMissing(['evidence', 'request', 'documentVersion', 'assessor', 'exceptionApprover']);
        $eligibility = $this->evidenceEligibility($assessment);
        return [
            'id' => $assessment->id, 'familyUuid' => $assessment->assessment_family_uuid,
            'evidenceId' => $assessment->audit_evidence_id, 'evidenceCode' => $assessment->evidence?->evidence_code,
            'evidenceRequestId' => $assessment->evidence_request_id, 'documentVersionId' => $assessment->document_version_id,
            'versionNumber' => $assessment->version_number, 'isCurrentRevision' => $assessment->is_current_revision,
            'status' => $assessment->status, 'sufficiency' => $assessment->sufficiency,
            'appropriateness' => $assessment->appropriateness, 'relevance' => $assessment->relevance,
            'reliability' => $assessment->reliability, 'competence' => $assessment->competence,
            'accuracy' => $assessment->accuracy, 'completeness' => $assessment->completeness,
            'corroboration' => $assessment->corroboration, 'contradiction' => $assessment->contradiction,
            'authenticity' => $assessment->authenticity, 'integrity' => $assessment->integrity,
            'confidentiality' => $assessment->confidentiality, 'isRestricted' => $assessment->is_restricted,
            'accessRestrictions' => $assessment->access_restrictions, 'limitations' => $assessment->limitations,
            'evidenceGaps' => $assessment->evidence_gaps, 'exceptionRequired' => $assessment->exception_required,
            'exceptionReason' => $assessment->exception_reason, 'exceptionApprovedBy' => $this->user($assessment->exceptionApprover),
            'exceptionApprovedAt' => $assessment->exception_approved_at?->toIso8601String(),
            'exceptionApprovalComment' => $assessment->exception_approval_comment,
            'assessedBy' => $this->user($assessment->assessor), 'assessedAt' => $assessment->assessed_at?->toIso8601String(),
            'changeReason' => $assessment->change_reason, 'lockVersion' => $assessment->lock_version,
            'eligibleForFinalizedFinding' => $eligibility['eligible'],
            'eligibilityReasons' => $eligibility['reasons'],
        ];
    }

    public function eligibleForFinalizedFinding(?AemsEvidenceAssessment $assessment): bool
    {
        return $this->evidenceEligibility($assessment)['eligible'];
    }

    /** @return array{eligible: bool, reasons: list<string>} */
    public function evidenceEligibility(?AemsEvidenceAssessment $assessment): array
    {
        if (! $assessment) {
            return ['eligible' => false, 'reasons' => ['No professional evidence assessment has been recorded.']];
        }
        $assessment->loadMissing(['evidence', 'documentVersion']);
        $reasons = [];
        if (! $assessment->is_current_revision || $assessment->status !== 'ASSESSED') {
            $reasons[] = 'The assessment is not the current assessed version.';
        }
        $evidence = $assessment->evidence;
        if (! $evidence || ! $evidence->is_current_revision || ! in_array($evidence->status, ['VERIFIED', 'LOCKED'], true)) {
            $reasons[] = 'The Evidence record is not a current verified or locked version.';
        }
        if (! $evidence || ! in_array($evidence->outcome, ['ACCEPTED', 'LIMITED'], true)) {
            $reasons[] = 'Evidence must have an explicit accepted outcome before it can support reporting.';
        }
        if (! $assessment->documentVersion || (int) $assessment->document_version_id !== (int) $evidence?->document_version_id) {
            $reasons[] = 'The assessment does not cite the exact current Core Document Version.';
        }
        foreach (self::ASSESSMENT_DIMENSIONS as $dimension) {
            $value = strtoupper(trim((string) $assessment->{$dimension}));
            $acceptable = $dimension === 'contradiction'
                ? in_array($value, ['NO', 'ADEQUATE'], true)
                : in_array($value, ['YES', 'HIGH', 'ADEQUATE'], true);
            if (! $acceptable) {
                $reasons[] = str($dimension)->lower()->headline()->toString().' is incomplete or professionally negative.';
            }
        }
        if (blank($assessment->confidentiality)) {
            $reasons[] = 'Evidence confidentiality must be classified.';
        }
        if (filled($assessment->evidence_gaps)) {
            $reasons[] = 'Unresolved evidence gaps remain.';
        }
        if (filled($assessment->limitations) && ! $assessment->exception_approved_at) {
            $reasons[] = 'Evidence limitations require an approved exception or resolution.';
        }
        if (($assessment->is_restricted || filled($assessment->access_restrictions) || $assessment->exception_required)
            && ! $assessment->exception_approved_at) {
            $reasons[] = 'Restricted or exception-controlled evidence requires separate approval.';
        }

        return ['eligible' => $reasons === [], 'reasons' => array_values(array_unique($reasons))];
    }

    /** @return list<string> */
    private function requestRelations(): array
    {
        return ['requestedFromOffice', 'requestedFromUser', 'preparer', 'submitter', 'sender', 'closer', 'acknowledger', 'extensionRequester', 'extensionApprover', 'escalator', 'canceller', 'latestVersion.creator', 'versions.creator', 'evidenceLinks.evidence.documentVersion', 'evidenceLinks.documentVersion', 'evidenceLinks.receiver', 'responses.evidence.documentVersion', 'responses.documentVersion', 'responses.submitter', 'events.actor'];
    }

    private function loadRequest(AemsEvidenceRequest $record): AemsEvidenceRequest
    {
        return $record->fresh($this->requestRelations());
    }

    /** @param array<string, mixed> $attributes */
    private function requestAttributes(array $attributes): array
    {
        return ['title' => $attributes['title'], 'purpose' => $attributes['purpose'], 'requested_from_office_id' => $attributes['requestedFromOfficeId'] ?? null, 'requested_from_user_id' => $attributes['requestedFromUserId'] ?? null, 'due_date' => $attributes['dueDate'] ?? null];
    }

    /** @param array<string, mixed> $attributes */
    private function createVersion(Request $request, AemsEvidenceRequest $record, array $attributes, int $number, ?string $reason): AemsEvidenceRequestVersion
    {
        return AemsEvidenceRequestVersion::query()->create([
            'evidence_request_id' => $record->id, 'version_number' => $number, ...$this->requestAttributes($attributes),
            'requested_items' => array_values($attributes['requestedItems'] ?? []), 'change_reason' => $reason,
            'created_by' => $request->user()->id, 'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function assessmentAttributes(array $attributes): array
    {
        return collect(['sufficiency', 'appropriateness', 'relevance', 'reliability', 'competence', 'accuracy', 'completeness', 'corroboration', 'contradiction', 'authenticity', 'integrity', 'confidentiality'])
            ->mapWithKeys(fn (string $key): array => [$key => $attributes[$key] ?? null])->all() + [
                'is_restricted' => (bool) ($attributes['isRestricted'] ?? false),
                'access_restrictions' => $attributes['accessRestrictions'] ?? null,
                'limitations' => $attributes['limitations'] ?? null,
                'evidence_gaps' => $attributes['evidenceGaps'] ?? null,
                'exception_required' => (bool) ($attributes['exceptionRequired'] ?? false),
                'exception_reason' => $attributes['exceptionReason'] ?? null,
            ];
    }

    private function assertOfficeAndUser(AuditEngagement $engagement, array $attributes): void
    {
        if (! empty($attributes['requestedFromOfficeId']) && ! $engagement->offices()->whereKey((int) $attributes['requestedFromOfficeId'])->exists()) throw ValidationException::withMessages(['requestedFromOfficeId' => ['The requested office must be covered by this engagement.']]);
        if (! empty($attributes['requestedFromUserId']) && ! User::query()->whereKey((int) $attributes['requestedFromUserId'])->where('is_active', true)->where(function ($query) use ($engagement): void {
            $query->whereIn('office_id', $engagement->offices()->pluck('offices.id'))
                ->orWhereIn('id', $engagement->teamMembers()->where('is_active', true)->whereNull('ended_at')->pluck('user_id'));
        })->exists()) throw ValidationException::withMessages(['requestedFromUserId' => ['The requested user must belong to the engagement office or active engagement team.']]);
    }

    private function lockRequest(AuditEngagement $engagement, AemsEvidenceRequest $record, int $lockVersion): AemsEvidenceRequest
    {
        $locked = AemsEvidenceRequest::query()->lockForUpdate()->findOrFail($record->id);
        if ((int) $locked->audit_engagement_id !== (int) $engagement->id) throw ValidationException::withMessages(['request' => ['The Evidence Request does not belong to this engagement.']]);
        if ($locked->lock_version !== $lockVersion) throw ValidationException::withMessages(['lockVersion' => ['This Evidence Request changed in another session. Refresh before continuing.']]);
        $locked->load($this->requestRelations());
        return $locked;
    }

    private function ensureRequestEvidenceAssessed(AemsEvidenceRequest $record): void
    {
        foreach ($record->evidenceLinks as $link) {
            $assessment = $link->evidence?->currentAssessment;
            if ((int) $link->document_version_id !== (int) $assessment?->document_version_id || ! $this->eligibleForFinalizedFinding($assessment)) throw ValidationException::withMessages(['evidence' => ['Every received Evidence/Core Document Version must have an eligible assessment before this request can be assessed.']]);
        }
    }

    private function supersedeAssessment(AemsEvidenceAssessment $assessment): void
    {
        DB::table('aems_evidence_assessments')
            ->where('id', $assessment->id)
            ->where('is_current_revision', true)
            ->update(['is_current_revision' => false, 'updated_at' => now()]);
    }

    /** @return array<string, mixed> */
    private function assessmentSnapshotAttributes(AemsEvidenceAssessment $assessment): array
    {
        return collect([
            ...self::ASSESSMENT_DIMENSIONS,
            'confidentiality', 'is_restricted', 'access_restrictions', 'limitations',
            'evidence_gaps', 'exception_required', 'exception_reason',
        ])->mapWithKeys(fn (string $field): array => [$field => $assessment->{$field}])->all();
    }

    private function assessmentValuesForEligibility(AemsEvidenceAssessment $assessment): bool
    {
        foreach (self::ASSESSMENT_DIMENSIONS as $dimension) {
            $value = strtoupper(trim((string) $assessment->{$dimension}));
            $acceptable = $dimension === 'contradiction'
                ? in_array($value, ['NO', 'ADEQUATE'], true)
                : in_array($value, ['YES', 'HIGH', 'ADEQUATE'], true);
            if (! $acceptable) return false;
        }
        return filled($assessment->confidentiality)
            && blank($assessment->evidence_gaps)
            && blank($assessment->limitations)
            && ! $assessment->is_restricted
            && blank($assessment->access_restrictions)
            && ! $assessment->exception_required;
    }

    private function nextCode(AuditEngagement $engagement): string
    {
        $number = AemsEvidenceRequest::withTrashed()->where('audit_engagement_id', $engagement->id)->count() + 1;
        do { $code = sprintf('ERQ-%s-%03d', $engagement->engagement_code, $number++); } while (AemsEvidenceRequest::withTrashed()->where('audit_engagement_id', $engagement->id)->where('request_code', $code)->exists());
        return $code;
    }

    private function event(Request $request, AuditEngagement $engagement, AemsEvidenceRequest $record, string $action, ?string $from, ?string $to, ?string $comment = null, ?array $documentIds = null): void
    {
        $this->support->event($request, $engagement, 'EVIDENCE_REQUEST_'.$action, $from, $to, ['requestCode' => $record->request_code, 'status' => $from], ['requestCode' => $record->request_code, 'status' => $to], $comment, 'AEMS_EVIDENCE_REQUEST', $record->id, $record->current_version_number, $record->request_code, $record->request_family_uuid, $documentIds);
        \App\Models\AemsEvidenceRequestEvent::query()->create([
            'evidence_request_id' => $record->id,
            'audit_engagement_id' => $engagement->id,
            'event_type' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $request->user()?->id,
            'reason' => $comment,
            'metadata' => ['documentVersionIds' => $documentIds ?? []],
            'version_number' => $record->current_version_number,
            'created_at' => now(),
        ]);
    }

    private function auditValues(AemsEvidenceRequest $record): array { return ['id' => $record->id, 'requestCode' => $record->request_code, 'status' => $record->status, 'versionNumber' => $record->current_version_number, 'lockVersion' => $record->lock_version]; }
    private function assessmentValues(AemsEvidenceAssessment $assessment): array { return ['id' => $assessment->id, 'evidenceId' => $assessment->audit_evidence_id, 'status' => $assessment->status, 'versionNumber' => $assessment->version_number, 'documentVersionId' => $assessment->document_version_id, 'isRestricted' => $assessment->is_restricted, 'exceptionApprovedAt' => $assessment->exception_approved_at?->toIso8601String()]; }

    private function versionData(AemsEvidenceRequestVersion $version): array { return ['id' => $version->id, 'versionNumber' => $version->version_number, 'title' => $version->title, 'purpose' => $version->purpose, 'requestedFromOfficeId' => $version->requested_from_office_id, 'requestedFromUserId' => $version->requested_from_user_id, 'dueDate' => $version->due_date?->toDateString(), 'requestedItems' => $version->requested_items ?? [], 'changeReason' => $version->change_reason, 'createdBy' => $this->user($version->creator), 'createdAt' => $version->created_at?->toIso8601String()]; }
    private function linkData(AemsEvidenceRequestEvidence $link): array { return ['id' => $link->id, 'evidenceId' => $link->audit_evidence_id, 'evidenceCode' => $link->evidence?->evidence_code, 'documentVersionId' => $link->document_version_id, 'fileName' => $link->documentVersion?->original_file_name, 'checksumSha256' => $link->documentVersion?->checksum_sha256, 'receivedBy' => $this->user($link->receiver), 'receivedAt' => $link->received_at?->toIso8601String(), 'receiptNotes' => $link->receipt_notes, 'receiptStatus' => $link->receipt_status, 'receiptOutcome' => $link->receipt_outcome, 'receivedForm' => $link->received_form, 'acquisitionMethod' => $link->acquisition_method, 'assessment' => $link->evidence?->currentAssessment ? $this->assessmentData($link->evidence->currentAssessment) : null]; }
    private function responseData(AemsEvidenceRequestResponse $response): array { return ['id' => $response->id, 'evidenceId' => $response->audit_evidence_id, 'evidenceCode' => $response->evidence?->evidence_code, 'documentVersionId' => $response->document_version_id, 'fileName' => $response->documentVersion?->original_file_name, 'checksumSha256' => $response->documentVersion?->checksum_sha256, 'submittedBy' => $this->user($response->submitter), 'submittedAt' => $response->submitted_at?->toIso8601String(), 'responseNote' => $response->response_note, 'evidenceStatus' => $response->evidence?->status]; }
    private function evidenceSummary(AuditEvidence $evidence): array { return ['id' => $evidence->id, 'evidenceCode' => $evidence->evidence_code, 'title' => $evidence->title, 'status' => $evidence->status, 'outcome' => $evidence->outcome, 'versionNumber' => $evidence->version_number, 'documentVersionId' => $evidence->document_version_id, 'acquisitionMethod' => $evidence->acquisition_method, 'acquisitionForm' => $evidence->acquisition_form, 'planningObjectiveId' => $evidence->planning_objective_id, 'riskMatrixItemId' => $evidence->risk_matrix_item_id, 'controlReference' => $evidence->control_reference, 'assessment' => $evidence->currentAssessment ? $this->assessmentData($evidence->currentAssessment) : null]; }
    private function user(mixed $user): ?array { return $user ? ['id' => $user->id, 'employeeId' => $user->employee_id, 'name' => $user->name, 'initials' => $user->initials] : null; }

    /** @return array<string, mixed> */
    private function storeResponseFile(UploadedFile $file, AuditEngagement $engagement): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = Storage::disk('local')->putFileAs(
            "aems/engagements/{$engagement->id}/evidence",
            $file,
            Str::uuid().($extension ? ".{$extension}" : ''),
        );
        if (! $path) {
            throw ValidationException::withMessages(['file' => ['The evidence file could not be stored. Please try again.']]);
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

    private function ensureFieldworkAvailable(AuditEngagement $engagement): void
    {
        if (! $engagement->programs()->where('is_current_revision', true)->where('status', 'ACTIVE')->exists()) {
            throw ValidationException::withMessages(['engagement' => ['Evidence can be submitted only after the approved Audit Program is active.']]);
        }
    }

    private function nextEvidenceCode(AuditEngagement $engagement): string
    {
        $sequence = AuditEvidence::query()->withTrashed()->where('audit_engagement_id', $engagement->id)->distinct('evidence_family_uuid')->count('evidence_family_uuid') + 1;
        do {
            $code = sprintf('EVD-%s-%03d', $engagement->engagement_code, $sequence++);
        } while (AuditEvidence::query()->withTrashed()->where('audit_engagement_id', $engagement->id)->where('evidence_code', $code)->exists());
        return $code;
    }
}
