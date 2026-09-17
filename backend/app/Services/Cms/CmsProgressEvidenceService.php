<?php

namespace App\Services\Cms;

use App\Models\AuditLog;
use App\Models\CmsProgressEvidenceLink;
use App\Models\CmsProgressUpdate;
use App\Models\CmsProgressUpdateVersion;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
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

/** Pins private Core Document Versions to draft management progress reports. */
class CmsProgressEvidenceService
{
    private const CLASSIFICATION_RANK = [
        'PUBLIC' => 1,
        'INTERNAL' => 2,
        'CONFIDENTIAL' => 3,
        'RESTRICTED' => 4,
    ];

    public function __construct(
        private readonly CmsProgressUpdateService $progress,
        private readonly CmsRecommendationScopeService $scope,
        private readonly DocumentAccessService $documentAccess,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function upload(
        Request $request,
        int $updateId,
        int $versionId,
        array $attributes,
        UploadedFile $file,
    ): CmsProgressEvidenceLink {
        $actor = $request->user();
        [$case, $update, $version] = $this->progress->resolveVersionForActor(
            $actor,
            $updateId,
            $versionId,
        );
        $this->progress->authorizeResponsibleAction(
            $actor,
            $case,
            'cms.evidence.upload',
        );
        $requested = MasterListItem::query()
            ->findOrFail((int) $attributes['confidentialityLevelId']);
        $effective = $this->effectiveClassification($case, $requested);
        $this->documentAccess->authorizeClassification($actor, $effective);
        $stored = $this->storeFile($file, $case);

        try {
            $evidence = DB::transaction(function () use (
                $request,
                $actor,
                $update,
                $version,
                $attributes,
                $effective,
                $stored,
            ): CmsProgressEvidenceLink {
                [$lockedCase, $lockedUpdate, $lockedVersion] =
                    $this->progress->resolveVersionForActor(
                        $actor,
                        $update->id,
                        $version->id,
                        true,
                    );
                $this->progress->assertDraftEvidenceMutation(
                    $lockedVersion,
                    (int) $attributes['lockVersion'],
                );
                $milestoneProgressId = $attributes['milestoneProgressId'] ?? null;
                if ($milestoneProgressId
                    && ! $lockedVersion->milestoneProgress()
                        ->whereKey($milestoneProgressId)
                        ->exists()) {
                    throw ValidationException::withMessages([
                        'milestoneProgressId' => [
                            'The milestone progress record is unavailable for this draft.',
                        ],
                    ]);
                }
                if ($lockedVersion->activeEvidenceLinks()
                    ->where('checksum_sha256', $stored['checksum_sha256'])
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'file' => [
                            'This exact file is already linked to the Progress Update draft.',
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
                    'version_label' => 'CMS progress evidence version 1',
                    'change_summary' => 'Initial CMS management progress evidence upload.',
                    ...$stored,
                    'uploaded_by' => $actor->id,
                ]);
                $document->forceFill([
                    'current_version_id' => $documentVersion->id,
                    'version' => $documentVersion->version_label,
                ])->save();

                $evidence = $lockedVersion->evidenceLinks()->create([
                    'cms_milestone_progress_id' => $milestoneProgressId,
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
                $this->createDocumentLinks($document, $lockedUpdate, $lockedVersion, $evidence, $actor);
                $lockedVersion->forceFill([
                    'lock_version' => $lockedVersion->lock_version + 1,
                ])->save();
                $lockedUpdate->forceFill([
                    'lock_version' => $lockedUpdate->lock_version + 1,
                ])->save();
                $this->progress->recordEvidence(
                    $request,
                    $lockedCase,
                    $lockedUpdate,
                    $lockedVersion,
                    $evidence,
                    CmsRecommendationEvent::EVENT_PROGRESS_EVIDENCE_LINKED,
                    'cms.evidence.linked',
                );
                $this->recordDocumentCreation($request, $document, $documentVersion, $evidence);

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
    ): CmsProgressUpdate {
        $actor = $request->user();
        $reference = CmsProgressEvidenceLink::query()->find($evidenceId);
        throw_unless(
            $reference,
            new HttpException(404, 'The progress evidence is unavailable.'),
        );

        return DB::transaction(function () use (
            $request,
            $actor,
            $reference,
            $lockVersion,
            $reason,
        ): CmsProgressUpdate {
            $reference->loadMissing('version');
            [$case, $update, $version] = $this->progress->resolveVersionForActor(
                $actor,
                $reference->version->cms_progress_update_id,
                $reference->cms_progress_update_version_id,
                true,
            );
            $this->progress->authorizeResponsibleAction(
                $actor,
                $case,
                'cms.evidence.remove_draft',
            );
            $this->progress->assertDraftEvidenceMutation($version, $lockVersion);
            $evidence = CmsProgressEvidenceLink::query()
                ->whereKey($reference->id)
                ->where('cms_progress_update_version_id', $version->id)
                ->lockForUpdate()
                ->first();
            throw_unless(
                $evidence && ! $evidence->removed_at,
                new HttpException(404, 'The progress evidence is unavailable.'),
            );

            $evidence->forceFill([
                'removed_by' => $actor->id,
                'removed_at' => now(),
                'removal_reason' => $reason,
            ])->save();
            $version->forceFill(['lock_version' => $version->lock_version + 1])->save();
            $update->forceFill(['lock_version' => $update->lock_version + 1])->save();
            $this->progress->recordEvidence(
                $request,
                $case,
                $update,
                $version,
                $evidence,
                CmsRecommendationEvent::EVENT_PROGRESS_EVIDENCE_REMOVED,
                'cms.evidence.draft_removed',
            );

            return $update->fresh();
        }, 3);
    }

    public function download(Request $request, int $evidenceId): StreamedResponse
    {
        $actor = $request->user();
        $reference = CmsProgressEvidenceLink::query()
            ->whereNull('removed_at')
            ->find($evidenceId);
        throw_unless(
            $reference,
            new HttpException(404, 'The progress evidence is unavailable.'),
        );
        $reference->loadMissing('version');
        [$case, , $version] = $this->progress->resolveVersionForActor(
            $actor,
            $reference->version->cms_progress_update_id,
            $reference->cms_progress_update_version_id,
        );
        throw_unless(
            $actor->hasPermission('cms.evidence.download')
                && $this->scope->canViewClassification(
                    $actor,
                    $reference->confidentiality_code_snapshot,
                ),
            new HttpException(403, 'You cannot download this progress evidence.'),
        );
        $evidence = $version->activeEvidenceLinks()
            ->with(['document', 'documentVersion'])
            ->whereKey($reference->id)
            ->first();
        throw_unless(
            $evidence,
            new HttpException(404, 'The progress evidence is unavailable.'),
        );
        $this->documentAccess->authorizeView($actor, $evidence->document);
        abort_unless(
            Storage::disk('local')->exists($evidence->documentVersion->storage_path),
            404,
            'Stored progress evidence file not found.',
        );
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => 'cms.evidence.downloaded',
            'auditable_type' => CmsProgressEvidenceLink::class,
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
                'progressUpdateVersionId' => $version->id,
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
    ): array {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid().($extension ? ".{$extension}" : '');
        $path = Storage::disk('local')->putFileAs(
            "cms/recommendations/{$case->id}/progress-evidence",
            $file,
            $storedName,
        );
        if (! $path) {
            throw ValidationException::withMessages([
                'file' => ['The progress evidence file could not be stored. Please try again.'],
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
        CmsProgressUpdate $update,
        CmsProgressUpdateVersion $version,
        CmsProgressEvidenceLink $evidence,
        User $actor,
    ): void {
        $document->links()->create([
            'module_code' => 'CMS',
            'record_type' => 'PROGRESS_UPDATE_VERSION',
            'record_id' => $version->id,
            'record_code' => sprintf(
                'CMS-UPD-%06d-%03d-V%d',
                $update->cms_recommendation_case_id,
                $update->reporting_sequence,
                $version->version_number,
            ),
            'record_label' => 'CMS management-reported Progress Update',
            'linked_by' => $actor->id,
        ]);
        if ($evidence->cms_milestone_progress_id) {
            $document->links()->create([
                'module_code' => 'CMS',
                'record_type' => 'MILESTONE_PROGRESS',
                'record_id' => $evidence->cms_milestone_progress_id,
                'record_code' => "CMS-MPR-{$evidence->cms_milestone_progress_id}",
                'record_label' => 'CMS management-reported milestone progress',
                'linked_by' => $actor->id,
            ]);
        }
    }

    private function recordDocumentCreation(
        Request $request,
        Document $document,
        DocumentVersion $version,
        CmsProgressEvidenceLink $evidence,
    ): void {
        $values = [
            'documentId' => $document->id,
            'documentVersionId' => $version->id,
            'checksumSha256' => $version->checksum_sha256,
            'confidentialityLevelId' => $document->confidentiality_level_id,
            'progressEvidenceLinkId' => $evidence->id,
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
            "Created private CMS supporting evidence {$document->document_code}.",
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
            'milestoneProgress',
            'document.confidentialityLevel',
            'documentVersion',
            'confidentialityLevel',
            'linker',
        ];
    }
}
