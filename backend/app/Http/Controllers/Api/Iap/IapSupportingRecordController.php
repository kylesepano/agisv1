<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\IapAttachment;
use App\Models\IapComment;
use App\Models\IapPlanEngagement;
use App\Models\IapRiskAssessment;
use App\Models\InternalAuditPlan;
use App\Services\IapPlanGuard;
use App\Services\IapSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stores IAP attachments and management or reviewer comments against supported records.
 */
class IapSupportingRecordController extends Controller
{
    public function __construct(
        private readonly IapPlanGuard $guard,
        private readonly IapSupport $support,
    ) {}

    public function index(Request $request, InternalAuditPlan $plan): JsonResponse
    {
        $user = $request->user()->loadMissing('role.permissions');
        $this->guard->assertCanView($user, $plan);
        $isManagement = $user->hasPermission('iap.manage_universe');
        $includeArchived = $request->boolean('includeArchived') && $isManagement;

        $attachments = IapAttachment::query()
            ->when($includeArchived, fn ($query) => $query->withTrashed())
            ->where('plan_id', $plan->id)
            ->when(! $isManagement, fn ($query) => $query->where('visibility', 'INTERNAL'))
            ->with([
                'attachmentType',
                'document',
                'uploader:id,employee_id,name,initials',
                'engagement:id,engagement_code,title',
                'riskAssessment.office:id,code,name',
                'riskAssessment.auditArea:id,code,name',
            ])
            ->latest()
            ->get()
            ->map(fn (IapAttachment $attachment): array => $this->attachmentData($attachment))
            ->values();

        $comments = IapComment::query()
            ->where('plan_id', $plan->id)
            ->when(! $isManagement, fn ($query) => $query->where('visibility', 'INTERNAL'))
            ->with([
                'commentType',
                'author:id,employee_id,name,initials',
                'engagement:id,engagement_code,title',
                'parent:id,body',
            ])
            ->oldest()
            ->get()
            ->map(fn (IapComment $comment): array => $this->commentData($comment))
            ->values();

        $attachmentTypes = $this->support
            ->masterItemByCode('IAP_ATTACHMENT_TYPE', 'OTHER')
            ->masterList
            ->items()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get(['id', 'code', 'label', 'description']);

        $riskAssessments = $plan->riskAssessments()
            ->with(['office:id,code,name', 'auditArea:id,code,name'])
            ->orderByDesc('assessment_date')
            ->get()
            ->map(fn (IapRiskAssessment $assessment): array => [
                'id' => $assessment->id,
                'label' => trim(
                    ($assessment->office?->code ?? 'Office').' / '.
                    ($assessment->auditArea?->code ?? 'Audit area'),
                ),
                'office' => $assessment->office?->only(['id', 'code', 'name']),
                'auditArea' => $assessment->auditArea?->only(['id', 'code', 'name']),
                'assessmentDate' => $assessment->assessment_date?->toDateString(),
            ])->values();

        $engagements = $plan->engagements()
            ->get(['id', 'engagement_code', 'title'])
            ->map(fn (IapPlanEngagement $engagement): array => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
            ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'attachments' => $attachments,
                'comments' => $comments,
                'attachmentTypes' => $attachmentTypes,
                'riskAssessments' => $riskAssessments,
                'engagements' => $engagements,
                'capabilities' => [
                    'canUpload' => $this->mayMutateAttachments($user, $plan),
                    'canArchive' => $this->mayMutateAttachments($user, $plan),
                    'canRestore' => $this->mayMutateAttachments($user, $plan),
                    'canComment' => $isManagement
                        && $user->hasPermission('iap.review')
                        && in_array($plan->status, ['PENDING_REVIEW', 'RESUBMITTED'], true),
                    'canViewArchived' => $isManagement,
                    'isFrozen' => in_array($plan->status, ['APPROVED', 'ACTIVE', 'COMPLETED'], true),
                ],
            ],
        ]);
    }

    public function storeAttachment(Request $request, InternalAuditPlan $plan): JsonResponse
    {
        $this->assertMayMutateAttachments($request, $plan);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.app(\App\Services\RuntimeConfiguration::class)->documentUploadMaxKilobytes(), 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png'],
            'attachmentTypeId' => ['required', 'integer'],
            'displayName' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'visibility' => ['required', Rule::in(['INTERNAL', 'MANAGEMENT'])],
            'planEngagementId' => ['nullable', 'integer'],
            'riskAssessmentId' => ['nullable', 'integer'],
        ]);

        if (! $request->user()->hasPermission('iap.manage_universe')) {
            $validated['visibility'] = 'INTERNAL';
        }
        if (! empty($validated['planEngagementId']) && ! empty($validated['riskAssessmentId'])) {
            throw ValidationException::withMessages([
                'linkedRecord' => ['Choose either an engagement or a risk assessment, not both.'],
            ]);
        }

        $attachmentType = $this->support->masterItem(
            (int) $validated['attachmentTypeId'],
            'IAP_ATTACHMENT_TYPE',
        );
        $engagement = $this->engagement($plan, $validated['planEngagementId'] ?? null);
        $riskAssessment = $this->riskAssessment($plan, $validated['riskAssessmentId'] ?? null);
        $storedFile = $this->storeFile($request->file('file'), $plan);

        try {
            $attachment = DB::transaction(function () use (
                $request,
                $plan,
                $validated,
                $attachmentType,
                $engagement,
                $riskAssessment,
                $storedFile,
            ): IapAttachment {
                $documentType = $this->support->masterItemByCode('DOCUMENT_TYPE', 'OTHER');
                $document = Document::query()->create([
                    'document_type_id' => $documentType->id,
                    'title' => $validated['displayName'],
                    'description' => $validated['description'] ?? null,
                    'owner_module' => 'IAP',
                    'library_visible' => false,
                    ...$storedFile,
                    'uploaded_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                    'is_active' => true,
                ]);
                $version = $document->versions()->create([
                    'version_number' => 1,
                    'version_label' => 'Initial',
                    'change_summary' => 'Initial IAP supporting-document version.',
                    ...$storedFile,
                    'uploaded_by' => $request->user()->id,
                ]);
                $document->forceFill([
                    'current_version_id' => $version->id,
                    'version' => $version->version_label,
                ])->save();
                $document->links()->create([
                    'module_code' => 'IAP',
                    'record_type' => 'ANNUAL_PLAN',
                    'record_id' => $plan->id,
                    'record_code' => $plan->plan_code,
                    'record_label' => "{$plan->plan_code} — {$plan->title}",
                    'linked_by' => $request->user()->id,
                ]);
                if ($engagement) {
                    $document->links()->create([
                        'module_code' => 'IAP',
                        'record_type' => 'PLAN_ENGAGEMENT',
                        'record_id' => $engagement->id,
                        'record_code' => $engagement->engagement_code,
                        'record_label' => "{$engagement->engagement_code} — {$engagement->title}",
                        'linked_by' => $request->user()->id,
                    ]);
                }

                $attachment = IapAttachment::query()->create([
                    'plan_id' => $plan->id,
                    'plan_engagement_id' => $engagement?->id,
                    'risk_assessment_id' => $riskAssessment?->id,
                    'document_id' => $document->id,
                    'attachment_type_id' => $attachmentType->id,
                    'display_name' => $validated['displayName'],
                    'visibility' => $validated['visibility'],
                    'uploaded_by' => $request->user()->id,
                ]);

                $this->support->audit(
                    $request,
                    'iap.supporting_attachment.created',
                    $attachment,
                    null,
                    $this->attachmentAuditValues($attachment),
                    ['planId' => $plan->id, 'documentId' => $document->id],
                );

                return $attachment;
            }, 3);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($storedFile['storage_path']);
            throw $error;
        }

        return response()->json([
            'success' => true,
            'message' => 'Supporting document uploaded successfully.',
            'data' => [
                'attachment' => $this->attachmentData($attachment->load([
                    'attachmentType', 'document', 'uploader:id,employee_id,name,initials',
                    'engagement:id,engagement_code,title',
                    'riskAssessment.office:id,code,name', 'riskAssessment.auditArea:id,code,name',
                ])),
            ],
        ], 201);
    }

    public function download(
        Request $request,
        InternalAuditPlan $plan,
        int $attachment,
    ): StreamedResponse {
        $this->guard->assertCanView($request->user(), $plan);
        $record = IapAttachment::withTrashed()
            ->with('document.currentVersion')
            ->where('plan_id', $plan->id)
            ->findOrFail($attachment);
        abort_if(
            $record->visibility === 'MANAGEMENT'
            && ! $request->user()->hasPermission('iap.manage_universe'),
            403,
        );

        $document = $record->document;
        $version = $document?->currentVersion;
        $path = $version?->storage_path ?? $document?->storage_path;
        abort_unless($document && $path && Storage::disk('local')->exists($path), 404, 'Stored file not found.');

        return Storage::disk('local')->download(
            $path,
            $version?->original_file_name ?? $document->original_file_name,
            ['Content-Type' => $version?->mime_type ?? $document->mime_type],
        );
    }

    public function destroyAttachment(
        Request $request,
        InternalAuditPlan $plan,
        IapAttachment $attachment,
    ): JsonResponse {
        $this->assertMayMutateAttachments($request, $plan);
        $this->assertAttachmentBelongsToPlan($plan, $attachment);
        $oldValues = $this->attachmentAuditValues($attachment);
        $attachment->delete();
        $this->support->audit(
            $request,
            'iap.supporting_attachment.archived',
            $attachment,
            $oldValues,
            $this->attachmentAuditValues($attachment),
            ['planId' => $plan->id],
        );

        return response()->json([
            'success' => true,
            'message' => 'Supporting document archived successfully.',
        ]);
    }

    public function restoreAttachment(
        Request $request,
        InternalAuditPlan $plan,
        int $attachment,
    ): JsonResponse {
        $this->assertMayMutateAttachments($request, $plan);
        $record = IapAttachment::onlyTrashed()
            ->with('document.currentVersion')
            ->where('plan_id', $plan->id)
            ->findOrFail($attachment);
        abort_unless(
            $record->document
            && Storage::disk('local')->exists(
                $record->document->currentVersion?->storage_path
                    ?? $record->document->storage_path,
            ),
            422,
            'The stored file is missing and this attachment cannot be restored.',
        );
        $oldValues = $this->attachmentAuditValues($record);
        $record->restore();
        $this->support->audit(
            $request,
            'iap.supporting_attachment.restored',
            $record,
            $oldValues,
            $this->attachmentAuditValues($record),
            ['planId' => $plan->id],
        );

        return response()->json([
            'success' => true,
            'message' => 'Supporting document restored successfully.',
        ]);
    }

    public function storeComment(Request $request, InternalAuditPlan $plan): JsonResponse
    {
        $user = $request->user()->loadMissing('role.permissions');
        $this->guard->assertCanView($user, $plan);
        $this->guard->assertManagement($user);

        if (! in_array($plan->status, ['PENDING_REVIEW', 'RESUBMITTED'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Reviewer comments may only be added while a plan is pending review or resubmitted.'],
            ]);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'planEngagementId' => ['nullable', 'integer'],
            'parentCommentId' => ['nullable', 'integer'],
        ]);
        $engagement = $this->engagement($plan, $validated['planEngagementId'] ?? null);
        $parent = null;
        if (! empty($validated['parentCommentId'])) {
            $parent = IapComment::query()
                ->where('plan_id', $plan->id)
                ->findOrFail((int) $validated['parentCommentId']);
        }
        $type = $this->support->masterItemByCode('IAP_COMMENT_TYPE', 'REVIEW');

        $comment = IapComment::query()->create([
            'plan_id' => $plan->id,
            'plan_engagement_id' => $engagement?->id,
            'author_id' => $user->id,
            'comment_type_id' => $type->id,
            'parent_comment_id' => $parent?->id,
            'visibility' => 'INTERNAL',
            'body' => trim($validated['body']),
            'is_immutable' => true,
        ]);
        $this->support->audit(
            $request,
            'iap.reviewer_comment.created',
            $comment,
            null,
            [
                'planId' => $plan->id,
                'engagementId' => $engagement?->id,
                'commentType' => 'REVIEW',
                'body' => $comment->body,
                'isImmutable' => true,
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Reviewer comment recorded successfully.',
            'data' => [
                'comment' => $this->commentData($comment->load([
                    'commentType', 'author:id,employee_id,name,initials',
                    'engagement:id,engagement_code,title', 'parent:id,body',
                ])),
            ],
        ], 201);
    }

    private function assertMayMutateAttachments(Request $request, InternalAuditPlan $plan): void
    {
        $user = $request->user()->loadMissing('role.permissions');
        $this->guard->assertCanView($user, $plan);

        if (in_array($plan->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
            $this->guard->assertEditable($user, $plan);

            return;
        }

        if (
            in_array($plan->status, ['PENDING_REVIEW', 'RESUBMITTED'], true)
            && $user->hasPermission('iap.manage_universe')
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => ['Supporting documents cannot be changed in the plan’s current status.'],
        ]);
    }

    private function mayMutateAttachments($user, InternalAuditPlan $plan): bool
    {
        if (in_array($plan->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
            return $user->hasPermission('iap.update')
                && ($user->hasPermission('iap.manage_universe') || $plan->prepared_by === $user->id);
        }

        return in_array($plan->status, ['PENDING_REVIEW', 'RESUBMITTED'], true)
            && $user->hasPermission('iap.update')
            && $user->hasPermission('iap.manage_universe');
    }

    private function engagement(InternalAuditPlan $plan, mixed $id): ?IapPlanEngagement
    {
        return $id
            ? IapPlanEngagement::query()->where('plan_id', $plan->id)->findOrFail((int) $id)
            : null;
    }

    private function riskAssessment(InternalAuditPlan $plan, mixed $id): ?IapRiskAssessment
    {
        return $id
            ? IapRiskAssessment::query()->where('plan_id', $plan->id)->findOrFail((int) $id)
            : null;
    }

    private function assertAttachmentBelongsToPlan(InternalAuditPlan $plan, IapAttachment $attachment): void
    {
        abort_unless($attachment->plan_id === $plan->id, 404);
    }

    /** @return array<string, mixed> */
    private function storeFile(UploadedFile $file, InternalAuditPlan $plan): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid().($extension ? ".{$extension}" : '');
        $path = Storage::disk('local')->putFileAs("iap/plans/{$plan->id}", $file, $storedName);
        if (! $path) {
            throw ValidationException::withMessages([
                'file' => ['The supporting document could not be stored. Please try again.'],
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

    /** @return array<string, mixed> */
    private function attachmentData(IapAttachment $attachment): array
    {
        $document = $attachment->document;

        return [
            'id' => $attachment->id,
            'displayName' => $attachment->display_name,
            'visibility' => $attachment->visibility,
            'attachmentTypeId' => $attachment->attachment_type_id,
            'attachmentType' => $attachment->attachmentType?->only(['id', 'code', 'label', 'description']),
            'fileName' => $document?->original_file_name,
            'fileExtension' => $document?->file_extension,
            'fileSize' => $document?->file_size,
            'mimeType' => $document?->mime_type,
            'description' => $document?->description,
            'engagement' => $attachment->engagement ? [
                'id' => $attachment->engagement->id,
                'engagementCode' => $attachment->engagement->engagement_code,
                'title' => $attachment->engagement->title,
            ] : null,
            'riskAssessment' => $attachment->riskAssessment ? [
                'id' => $attachment->riskAssessment->id,
                'office' => $attachment->riskAssessment->office?->only(['id', 'code', 'name']),
                'auditArea' => $attachment->riskAssessment->auditArea?->only(['id', 'code', 'name']),
            ] : null,
            'uploader' => $attachment->uploader?->only(['id', 'employee_id', 'name', 'initials']),
            'createdAt' => $attachment->created_at?->toISOString(),
            'isArchived' => $attachment->trashed(),
        ];
    }

    /** @return array<string, mixed> */
    private function commentData(IapComment $comment): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'visibility' => $comment->visibility,
            'isImmutable' => $comment->is_immutable,
            'commentType' => $comment->commentType?->only(['id', 'code', 'label', 'description']),
            'author' => $comment->author?->only(['id', 'employee_id', 'name', 'initials']),
            'engagement' => $comment->engagement ? [
                'id' => $comment->engagement->id,
                'engagementCode' => $comment->engagement->engagement_code,
                'title' => $comment->engagement->title,
            ] : null,
            'parentCommentId' => $comment->parent_comment_id,
            'createdAt' => $comment->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function attachmentAuditValues(IapAttachment $attachment): array
    {
        return [
            'planId' => $attachment->plan_id,
            'engagementId' => $attachment->plan_engagement_id,
            'riskAssessmentId' => $attachment->risk_assessment_id,
            'documentId' => $attachment->document_id,
            'attachmentTypeId' => $attachment->attachment_type_id,
            'displayName' => $attachment->display_name,
            'visibility' => $attachment->visibility,
            'isArchived' => $attachment->trashed(),
        ];
    }
}
