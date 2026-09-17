<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\DocumentVersion;
use App\Models\EngagementReopenRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AemsReopenService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly AemsNotificationService $notifications,
    ) {}

    /** @return list<array<string, mixed>> */
    public function index(Request $request, AuditEngagement $engagement): array
    {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.closure.view',
        );

        return $engagement->reopenRequests()
            ->with('authorityDocumentVersion')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EngagementReopenRequest $reopen): array => $this->data($reopen))
            ->values()
            ->all();
    }

    public function create(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): EngagementReopenRequest {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.engagement.reopen_request',
        );
        if ($engagement->status !== 'CLOSED' || ! $engagement->closure
            || $engagement->closure->status_code !== 'CLOSED') {
            throw ValidationException::withMessages([
                'engagement' => ['Exceptional reopening is available only for a formally closed engagement.'],
            ]);
        }
        if ($engagement->reopenRequests()
            ->whereIn('status_code', ['DRAFT', 'PENDING_APPROVAL', 'APPROVED'])
            ->exists()) {
            throw ValidationException::withMessages([
                'request' => ['An active reopening request already exists.'],
            ]);
        }
        $version = DocumentVersion::query()->with('document')->findOrFail(
            $attributes['authorityDocumentVersionId'],
        );
        if (! $version->document) {
            throw ValidationException::withMessages([
                'authorityDocumentVersionId' => ['The written authority document is unavailable.'],
            ]);
        }
        $sequence = $engagement->reopenRequests()->count() + 1;
        $reopen = $engagement->reopenRequests()->create([
            'request_code' => sprintf('%s-REOPEN-%02d', $engagement->engagement_code, $sequence),
            'reason_code' => strtoupper($attributes['reasonCode']),
            'reason_text' => trim($attributes['reasonText']),
            'authority_document_id' => $version->document_id,
            'authority_document_version_id' => $version->id,
            'requested_by' => $request->user()->id,
            'status_code' => 'DRAFT',
            'original_closed_snapshot_json' => $this->closedSnapshot($engagement),
            'lock_version' => 1,
        ]);
        $this->record($request, $engagement, $reopen, 'CREATE_REOPEN_REQUEST', null, 'DRAFT');

        return $reopen->fresh('authorityDocumentVersion');
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        EngagementReopenRequest $reopen,
        string $action,
        int $lockVersion,
        ?string $comment,
    ): EngagementReopenRequest {
        $this->ensureRequest($engagement, $reopen);
        $action = strtoupper($action);
        $permission = $action === 'SUBMIT_REOPEN_REQUEST'
            ? 'aems.engagement.reopen_request'
            : 'aems.engagement.reopen_approve';
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            $permission,
            $action === 'SUBMIT_REOPEN_REQUEST' ? null : $reopen->requested_by,
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $reopen,
            $action,
            $lockVersion,
            $comment,
        ): EngagementReopenRequest {
            $locked = EngagementReopenRequest::query()->lockForUpdate()->findOrFail($reopen->id);
            if ($locked->lock_version !== $lockVersion) {
                throw ValidationException::withMessages([
                    'lockVersion' => ['The reopening request changed in another session. Refresh first.'],
                ]);
            }
            $from = $locked->status_code;
            $to = match ($action) {
                'SUBMIT_REOPEN_REQUEST' => $from === 'DRAFT' ? 'PENDING_APPROVAL' : null,
                'APPROVE_REOPEN_REQUEST' => $from === 'PENDING_APPROVAL' ? 'APPROVED' : null,
                'REJECT_REOPEN_REQUEST' => $from === 'PENDING_APPROVAL' ? 'REJECTED' : null,
                'IMPLEMENT_REOPEN_REQUEST' => $from === 'APPROVED' ? 'IMPLEMENTED' : null,
                default => null,
            };
            if (! $to) {
                throw ValidationException::withMessages([
                    'action' => ["{$action} is not allowed while the reopening request is {$from}."],
                ]);
            }
            if (in_array($action, ['REJECT_REOPEN_REQUEST', 'IMPLEMENT_REOPEN_REQUEST'], true)
                && blank($comment)) {
                throw ValidationException::withMessages(['comment' => ['A review comment is required.']]);
            }
            $changes = [
                'status_code' => $to,
                'review_comment' => $comment,
                'lock_version' => $locked->lock_version + 1,
            ];
            if ($action === 'SUBMIT_REOPEN_REQUEST') {
                $changes['requested_at'] = now();
            }
            if (in_array($action, ['APPROVE_REOPEN_REQUEST', 'REJECT_REOPEN_REQUEST'], true)) {
                $changes['reviewed_by'] = $request->user()->id;
            }
            if ($action === 'APPROVE_REOPEN_REQUEST') {
                $changes['approved_by'] = $request->user()->id;
                $changes['approved_at'] = now();
            }
            if ($action === 'IMPLEMENT_REOPEN_REQUEST') {
                $changes['implemented_at'] = now();
                $this->implement($request, $engagement, $locked);
            }
            $locked->fill($changes)->save();
            $this->record($request, $engagement, $locked, $action, $from, $to, $comment);
            $this->notifications->reopen($request, $engagement, $locked, $action);

            return $locked->fresh('authorityDocumentVersion');
        });
    }

    private function implement(
        Request $request,
        AuditEngagement $engagement,
        EngagementReopenRequest $reopen,
    ): void {
        $lockedEngagement = AuditEngagement::query()->withTrashed()
            ->lockForUpdate()->findOrFail($engagement->id);
        if ($lockedEngagement->status !== 'CLOSED') {
            throw ValidationException::withMessages([
                'engagement' => ['The engagement is no longer in the closed state captured by this request.'],
            ]);
        }
        $currentClosure = $lockedEngagement->closure()->lockForUpdate()->firstOrFail();
        if ($currentClosure->status_code !== 'CLOSED') {
            throw ValidationException::withMessages([
                'closure' => ['The original Closure record is not closed.'],
            ]);
        }
        DB::table('engagement_closures')->where('id', $currentClosure->id)->update([
            'is_current_revision' => false,
            'updated_at' => now(),
        ]);
        $before = [
            'status' => $lockedEngagement->status,
            'lockVersion' => $lockedEngagement->lock_version,
            'closedAt' => $lockedEngagement->closed_at?->toISOString(),
            'closureId' => $currentClosure->id,
        ];
        $lockedEngagement->forceFill([
            'status' => 'CLOSURE_REVIEW',
            ...AuditEngagement::lifecycleProjectionForStatus('CLOSURE_REVIEW'),
            'status_reason' => "Exceptionally reopened under {$reopen->request_code}.",
            'current_reopen_request_id' => $reopen->id,
            'reopen_revision_number' => $lockedEngagement->reopen_revision_number + 1,
            'reopened_by' => $request->user()->id,
            'reopened_at' => now(),
            'transitioned_by' => $request->user()->id,
            'transitioned_at' => now(),
            'updated_by' => $request->user()->id,
            'lock_version' => $lockedEngagement->lock_version + 1,
        ])->save();
        $this->support->event(
            $request,
            $lockedEngagement,
            'IMPLEMENT_REOPEN_REQUEST',
            'CLOSED',
            'CLOSURE_REVIEW',
            $before,
            [
                'status' => $lockedEngagement->status,
                'phase' => $lockedEngagement->phase,
                'administrativeStatus' => $lockedEngagement->administrative_status,
                'lockVersion' => $lockedEngagement->lock_version,
                'reopenRevisionNumber' => $lockedEngagement->reopen_revision_number,
                'originalClosureId' => $currentClosure->id,
            ],
            $reopen->reason_text,
            'ENGAGEMENT_REOPEN_REQUEST',
            $reopen->id,
            $lockedEngagement->reopen_revision_number,
            $reopen->request_code,
            null,
            [$reopen->authority_document_version_id],
        );
        $this->support->audit(
            $request,
            'aems.engagement.reopened',
            $lockedEngagement,
            $before,
            [
                'status' => 'CLOSURE_REVIEW',
                'phase' => $lockedEngagement->phase,
                'administrativeStatus' => $lockedEngagement->administrative_status,
                'reopenRevisionNumber' => $lockedEngagement->reopen_revision_number,
                'originalClosedSnapshot' => $reopen->original_closed_snapshot_json,
            ],
            [
                'reopenRequestId' => $reopen->id,
                'authorityDocumentVersionId' => $reopen->authority_document_version_id,
                'originalClosureId' => $currentClosure->id,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function closedSnapshot(AuditEngagement $engagement): array
    {
        $closure = $engagement->closure;

        return [
            'engagementId' => $engagement->id,
            'engagementCode' => $engagement->engagement_code,
            'status' => $engagement->status,
            'lockVersion' => $engagement->lock_version,
            'closedBy' => $engagement->closed_by,
            'closedAt' => $engagement->closed_at?->toISOString(),
            'closureId' => $closure?->id,
            'closureCode' => $closure?->closure_code,
            'closureRevisionNumber' => $closure?->revision_number,
            'closureSnapshot' => $closure?->closed_snapshot_json,
            'finalReport' => $engagement->reports()
                ->where('report_stage', 'FINAL_REPORT')
                ->where('status', 'ISSUED')
                ->first()?->only(['id', 'report_code', 'current_version_id', 'issued_at']),
        ];
    }

    /** @return array<string, mixed> */
    private function data(EngagementReopenRequest $reopen): array
    {
        return [
            'id' => $reopen->id,
            'requestCode' => $reopen->request_code,
            'reasonCode' => $reopen->reason_code,
            'reasonText' => $reopen->reason_text,
            'authorityDocumentId' => $reopen->authority_document_id,
            'authorityDocumentVersionId' => $reopen->authority_document_version_id,
            'authorityChecksumSha256' => $reopen->authorityDocumentVersion?->checksum_sha256,
            'requestedBy' => $reopen->requested_by,
            'reviewedBy' => $reopen->reviewed_by,
            'approvedBy' => $reopen->approved_by,
            'statusCode' => $reopen->status_code,
            'requestedAt' => $reopen->requested_at?->toISOString(),
            'approvedAt' => $reopen->approved_at?->toISOString(),
            'implementedAt' => $reopen->implemented_at?->toISOString(),
            'reviewComment' => $reopen->review_comment,
            'lockVersion' => $reopen->lock_version,
            'originalClosedSnapshot' => $reopen->original_closed_snapshot_json,
        ];
    }

    private function ensureRequest(
        AuditEngagement $engagement,
        EngagementReopenRequest $reopen,
    ): void {
        if ((int) $reopen->audit_engagement_id !== (int) $engagement->id) {
            abort(404);
        }
    }

    private function record(
        Request $request,
        AuditEngagement $engagement,
        EngagementReopenRequest $reopen,
        string $action,
        ?string $from,
        ?string $to,
        ?string $comment = null,
    ): void {
        $snapshot = $this->data($reopen);
        $this->support->event(
            $request,
            $engagement,
            $action,
            $from,
            $to,
            null,
            $snapshot,
            $comment,
            'ENGAGEMENT_REOPEN_REQUEST',
            $reopen->id,
            null,
            $reopen->request_code,
            null,
            [$reopen->authority_document_version_id],
        );
        $this->support->audit(
            $request,
            'aems.engagement.reopen.'.strtolower($action),
            $engagement,
            null,
            $snapshot,
            [
                'reopenRequestId' => $reopen->id,
                'authorityDocumentVersionId' => $reopen->authority_document_version_id,
            ],
        );
    }
}
