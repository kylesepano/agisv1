<?php

namespace App\Services\Cms;

use App\Models\AuditLog;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\CmsValidationEvidenceLink;
use App\Models\CmsValidationReview;
use App\Models\CmsValidationVersion;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\MasterList;
use App\Models\MasterListItem;
use App\Models\User;
use App\Services\DocumentAccessService;
use App\Services\RuntimeConfiguration;
use App\Support\ActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Pins validator-obtained files to exact private Core Document Versions. */
class CmsValidationEvidenceService
{
    private const CLASSIFICATION_RANK = [
        'PUBLIC' => 1,
        'INTERNAL' => 2,
        'CONFIDENTIAL' => 3,
        'RESTRICTED' => 4,
    ];

    public function __construct(
        private readonly CmsValidationService $validations,
        private readonly CmsRecommendationScopeService $scope,
        private readonly DocumentAccessService $documentAccess,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function upload(
        Request $request,
        int $reviewId,
        int $versionId,
        array $attributes,
        UploadedFile $file,
    ): CmsValidationEvidenceLink {
        $actor = $request->user();
        [$case, $review, $version] = $this->validations->resolveVersionForActor(
            $actor,
            $reviewId,
            $versionId,
        );
        $this->validations->authorizeAssignedValidatorAction(
            $actor,
            $case,
            $review,
            'cms.validation-evidence.upload',
        );
        $requested = MasterListItem::query()
            ->findOrFail((int) $attributes['confidentialityLevelId']);
        $effective = $this->effectiveClassification($case, $requested);
        $this->documentAccess->authorizeClassification($actor, $effective);
        $stored = $this->storeFile($file, $case, $review);

        try {
            $evidence = DB::transaction(function () use (
                $request,
                $actor,
                $review,
                $version,
                $attributes,
                $effective,
                $stored,
            ): CmsValidationEvidenceLink {
                [$lockedCase, $lockedReview, $lockedVersion] =
                    $this->validations->resolveVersionForActor(
                        $actor,
                        $review->id,
                        $version->id,
                        true,
                    );
                $this->validations->authorizeAssignedValidatorAction(
                    $actor,
                    $lockedCase,
                    $lockedReview,
                    'cms.validation-evidence.upload',
                );
                $this->validations->assertDraftEvidenceMutation(
                    $lockedReview,
                    $lockedVersion,
                    (int) $attributes['lockVersion'],
                );
                $itemId = $attributes['validationItemId'] ?? null;
                if ($itemId && ! $lockedVersion->items()->whereKey($itemId)->exists()) {
                    throw ValidationException::withMessages([
                        'validationItemId' => [
                            'The Validation Item is unavailable for this draft.',
                        ],
                    ]);
                }
                if ($lockedVersion->activeEvidenceLinks()
                    ->where('checksum_sha256', $stored['checksum_sha256'])
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'file' => [
                            'This exact file is already linked to the Validation draft.',
                        ],
                    ]);
                }

                $documentType = MasterList::query()
                    ->where('code', 'DOCUMENT_TYPE')
                    ->firstOrFail()
                    ->items()
                    ->where('code', 'OTHER')
                    ->firstOrFail();
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
                    'document_code' => $this->runtime->formatNumber(
                        'document_number_format',
                        $document->id,
                    ),
                ])->save();
                $documentVersion = $document->versions()->create([
                    'version_number' => 1,
                    'version_label' => 'CMS validation evidence version 1',
                    'change_summary' => 'Initial independent-validation evidence upload.',
                    ...$stored,
                    'uploaded_by' => $actor->id,
                ]);
                $document->forceFill([
                    'current_version_id' => $documentVersion->id,
                    'version' => $documentVersion->version_label,
                ])->save();

                $evidence = $lockedVersion->evidenceLinks()->create([
                    'cms_validation_item_id' => $itemId,
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
                $lockedVersion->evidenceAssessments()->create([
                    'cms_validation_item_id' => $itemId,
                    'cms_validation_evidence_link_id' => $evidence->id,
                    'evidence_source_code' => 'VALIDATOR_OBTAINED',
                    'relevance_code' => 'NOT_ASSESSED',
                    'reliability_code' => 'NOT_ASSESSED',
                    'sufficiency_code' => 'NOT_ASSESSED',
                    'relied_upon' => false,
                ]);
                $this->createDocumentLinks(
                    $document,
                    $lockedReview,
                    $lockedVersion,
                    $evidence,
                    $actor,
                );
                $lockedVersion->forceFill([
                    'lock_version' => $lockedVersion->lock_version + 1,
                ])->save();
                $lockedReview->forceFill([
                    'lock_version' => $lockedReview->lock_version + 1,
                ])->save();
                $this->validations->recordEvidence(
                    $request,
                    $lockedCase,
                    $lockedReview,
                    $lockedVersion,
                    $evidence,
                    CmsRecommendationEvent::EVENT_VALIDATION_EVIDENCE_LINKED,
                    'cms.validation.evidence_linked',
                );
                $this->recordDocumentCreation(
                    $request,
                    $document,
                    $documentVersion,
                    $evidence,
                );

                return $evidence;
            }, 3);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($stored['storage_path']);
            throw $error;
        }

        return $evidence->fresh($this->relations());
    }

    public function remove(
        Request $request,
        int $evidenceId,
        int $lockVersion,
        string $reason,
    ): CmsValidationReview {
        $actor = $request->user();
        $reference = CmsValidationEvidenceLink::query()->find($evidenceId);
        throw_unless(
            $reference,
            new HttpException(404, 'The validation evidence is unavailable.'),
        );

        return DB::transaction(function () use (
            $request,
            $actor,
            $reference,
            $lockVersion,
            $reason,
        ): CmsValidationReview {
            $reference->loadMissing('version');
            [$case, $review, $version] = $this->validations->resolveVersionForActor(
                $actor,
                $reference->version->cms_validation_review_id,
                $reference->cms_validation_version_id,
                true,
            );
            $this->validations->authorizeAssignedValidatorAction(
                $actor,
                $case,
                $review,
                'cms.validation-evidence.remove_draft',
            );
            $this->validations->assertDraftEvidenceMutation($review, $version, $lockVersion);
            $evidence = CmsValidationEvidenceLink::query()
                ->whereKey($reference->id)
                ->where('cms_validation_version_id', $version->id)
                ->lockForUpdate()
                ->first();
            throw_unless(
                $evidence && ! $evidence->removed_at,
                new HttpException(404, 'The validation evidence is unavailable.'),
            );

            $version->evidenceAssessments()
                ->where('cms_validation_evidence_link_id', $evidence->id)
                ->get()
                ->each->delete();
            $evidence->forceFill([
                'removed_by' => $actor->id,
                'removed_at' => now(),
                'removal_reason' => $reason,
            ])->save();
            $version->forceFill(['lock_version' => $version->lock_version + 1])->save();
            $review->forceFill(['lock_version' => $review->lock_version + 1])->save();
            $this->validations->recordEvidence(
                $request,
                $case,
                $review,
                $version,
                $evidence,
                CmsRecommendationEvent::EVENT_VALIDATION_EVIDENCE_REMOVED,
                'cms.validation.evidence_draft_removed',
            );

            return $review->fresh();
        }, 3);
    }

    public function download(Request $request, int $evidenceId): StreamedResponse
    {
        $actor = $request->user();
        $reference = CmsValidationEvidenceLink::query()
            ->whereNull('removed_at')
            ->find($evidenceId);
        throw_unless(
            $reference,
            new HttpException(404, 'The validation evidence is unavailable.'),
        );
        $reference->loadMissing('version');
        [$case, $review, $version] = $this->validations->resolveVersionForActor(
            $actor,
            $reference->version->cms_validation_review_id,
            $reference->cms_validation_version_id,
        );
        throw_unless(
            $actor->hasPermission('cms.validation-evidence.download')
                && $this->scope->canViewClassification(
                    $actor,
                    $reference->confidentiality_code_snapshot,
                ),
            new HttpException(403, 'You cannot download this validation evidence.'),
        );
        $evidence = $version->activeEvidenceLinks()
            ->with(['document', 'documentVersion'])
            ->whereKey($reference->id)
            ->first();
        throw_unless(
            $evidence,
            new HttpException(404, 'The validation evidence is unavailable.'),
        );
        $this->documentAccess->authorizeView($actor, $evidence->document);
        abort_unless(
            Storage::disk('local')->exists($evidence->documentVersion->storage_path),
            404,
            'Stored validation evidence file not found.',
        );
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => 'cms.validation.evidence_downloaded',
            'auditable_type' => CmsValidationEvidenceLink::class,
            'auditable_id' => $evidence->id,
            'old_values' => null,
            'new_values' => [
                'documentVersionId' => $evidence->document_version_id,
                'checksumSha256' => $evidence->checksum_sha256,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => [
                'module' => 'CMS',
                'caseId' => $case->id,
                'validationReviewId' => $review->id,
                'validationVersionId' => $version->id,
            ],
        ]);

        return Storage::disk('local')->download(
            $evidence->documentVersion->storage_path,
            $evidence->documentVersion->original_file_name,
            ['Content-Type' => $evidence->documentVersion->mime_type],
        );
    }

    private function effectiveClassification(
        CmsRecommendationCase $case,
        MasterListItem $requested,
    ): MasterListItem {
        $case->loadMissing('recommendation');
        $caseCode = strtoupper(
            (string) ($case->recommendation?->confidentiality_code_snapshot ?? 'INTERNAL'),
        );
        $requestedCode = strtoupper((string) $requested->code);
        if ((self::CLASSIFICATION_RANK[$requestedCode] ?? 2)
            >= (self::CLASSIFICATION_RANK[$caseCode] ?? 2)) {
            return $requested;
        }
        if ($case->recommendation?->confidentiality_level_id) {
            return MasterListItem::query()->findOrFail(
                $case->recommendation->confidentiality_level_id,
            );
        }

        return MasterList::query()
            ->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->firstOrFail()
            ->items()
            ->where('code', $caseCode)
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function storeFile(
        UploadedFile $file,
        CmsRecommendationCase $case,
        CmsValidationReview $review,
    ): array {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid().($extension ? ".{$extension}" : '');
        $path = Storage::disk('local')->putFileAs(
            "cms/recommendations/{$case->id}/validations/{$review->id}/evidence",
            $file,
            $storedName,
        );
        if (! $path) {
            throw ValidationException::withMessages([
                'file' => ['The validation evidence file could not be stored. Please try again.'],
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

    private function createDocumentLinks(
        Document $document,
        CmsValidationReview $review,
        CmsValidationVersion $version,
        CmsValidationEvidenceLink $evidence,
        User $actor,
    ): void {
        $code = sprintf(
            'VAL-CMS-REC-%06d-%03d-V%d',
            $review->cms_recommendation_case_id,
            $review->validation_sequence,
            $version->version_number,
        );
        $document->links()->create([
            'module_code' => 'CMS',
            'record_type' => 'VALIDATION_VERSION',
            'record_id' => $version->id,
            'record_code' => $code,
            'record_label' => 'CMS Independent Validation Version',
            'linked_by' => $actor->id,
        ]);
        if ($evidence->cms_validation_item_id) {
            $document->links()->create([
                'module_code' => 'CMS',
                'record_type' => 'VALIDATION_ITEM',
                'record_id' => $evidence->cms_validation_item_id,
                'record_code' => "CMS-VAL-ITEM-{$evidence->cms_validation_item_id}",
                'record_label' => 'CMS Independent Validation Item',
                'linked_by' => $actor->id,
            ]);
        }
    }

    private function recordDocumentCreation(
        Request $request,
        Document $document,
        DocumentVersion $version,
        CmsValidationEvidenceLink $evidence,
    ): void {
        $values = [
            'documentId' => $document->id,
            'documentVersionId' => $version->id,
            'checksumSha256' => $version->checksum_sha256,
            'confidentialityLevelId' => $document->confidentiality_level_id,
            'validationEvidenceLinkId' => $evidence->id,
        ];
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'document.created',
            'auditable_type' => Document::class,
            'auditable_id' => $document->id,
            'old_values' => null,
            'new_values' => $values,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => ['module' => 'CMS'],
        ]);
        ActivityRecorder::record(
            $request,
            'document.created',
            "Created private CMS validation evidence {$document->document_code}.",
            newValues: $values,
            metadata: [
                'module' => 'CMS',
                'recordType' => Document::class,
                'recordId' => $document->id,
            ],
        );
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'item',
            'document.confidentialityLevel',
            'documentVersion',
            'confidentialityLevel',
            'linker',
        ];
    }
}
