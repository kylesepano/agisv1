<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\DocumentRequest;
use App\Http\Requests\Core\DocumentVersionRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\MasterList;
use App\Models\MasterListItem;
use App\Services\DocumentLinkRegistry;
use App\Services\DocumentAccessService;
use App\Services\RuntimeConfiguration;
use App\Support\ActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Operates the governed document repository and immutable document versions.
 */
class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentLinkRegistry $linkRegistry,
        private readonly DocumentAccessService $access,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $documents = $this->access->visibleTo(Document::query(), $request->user())
            ->when($request->boolean('include_archived'), fn ($query) => $query->withTrashed())
            ->where('library_visible', true)
            ->with($this->relations())
            ->latest('updated_at')
            ->get()
            ->map(fn (Document $document): array => $this->data($document));

        return response()->json([
            'success' => true,
            'data' => [
                'documents' => $documents,
                'documentTypes' => $this->documentTypes(),
                'confidentialityLevels' => $this->confidentialityLevels($request),
                'linkOptions' => $this->linkRegistry->options($request->user()),
                'linkModules' => DocumentLinkRegistry::MODULES,
            ],
        ]);
    }

    public function store(DocumentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $confidentiality = MasterListItem::query()->findOrFail($validated['confidentialityLevelId']);
        $this->access->authorizeClassification($request->user(), $confidentiality);
        $links = $this->linkRegistry->resolve($request->user(), $validated['links'] ?? []);
        $storedFile = $this->storeFile($request->file('file'));

        try {
            $document = DB::transaction(function () use (
                $request,
                $validated,
                $links,
                $storedFile,
            ): Document {
                $document = Document::query()->create([
                    ...$this->attributes($validated),
                    'version' => $validated['version'] ?? null,
                    'owner_module' => null,
                    'library_visible' => true,
                    ...$storedFile,
                    'uploaded_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
                // The database ID provides a collision-free sequence while the
                // administrator controls the human-readable format.
                $document->forceFill([
                    'document_code' => $this->runtime->formatNumber(
                        'document_number_format',
                        $document->id,
                    ),
                ])->save();
                $version = $document->versions()->create([
                    'version_number' => 1,
                    'version_label' => $validated['version'] ?? null,
                    'change_summary' => 'Initial document version.',
                    ...$storedFile,
                    'uploaded_by' => $request->user()->id,
                ]);
                $document->forceFill(['current_version_id' => $version->id])->save();
                $this->syncLinks($document, $links);
                $this->record(
                    $request,
                    'document.created',
                    $document,
                    null,
                    $this->auditValues($document),
                );

                return $document;
            }, 3);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($storedFile['storage_path']);
            throw $error;
        }

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'data' => ['document' => $this->data($this->loadDocument($document))],
        ], 201);
    }

    public function update(DocumentRequest $request, Document $document): JsonResponse
    {
        $this->access->authorizeView($request->user(), $document);
        $validated = $request->validated();
        $confidentiality = MasterListItem::query()->findOrFail($validated['confidentialityLevelId']);
        $this->access->authorizeClassification($request->user(), $confidentiality);
        $links = array_key_exists('links', $validated)
            ? $this->linkRegistry->resolve($request->user(), $validated['links'])
            : null;
        $document = $this->loadDocument($document);
        $oldValues = $this->auditValues($document);

        DB::transaction(function () use (
            $request,
            $document,
            $validated,
            $links,
            $oldValues,
        ): void {
            $document->update([
                ...$this->attributes($validated, $document),
                'updated_by' => $request->user()->id,
            ]);
            if ($links !== null) {
                $this->syncLinks($document, $links);
                $document->unsetRelation('links');
            }
            $this->record(
                $request,
                'document.updated',
                $document,
                $oldValues,
                $this->auditValues($document),
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Document metadata and module links updated successfully.',
            'data' => ['document' => $this->data($this->loadDocument($document->fresh()))],
        ]);
    }

    public function storeVersion(
        DocumentVersionRequest $request,
        Document $document,
    ): JsonResponse {
        $this->access->authorizeView($request->user(), $document);
        $validated = $request->validated();
        $storedFile = $this->storeFile($request->file('file'));

        try {
            $version = DB::transaction(function () use (
                $request,
                $document,
                $validated,
                $storedFile,
            ): DocumentVersion {
                $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

                if ($locked->versions()->where('checksum_sha256', $storedFile['checksum_sha256'])->exists()) {
                    throw ValidationException::withMessages([
                        'file' => ['This exact file already exists in the document version history.'],
                    ]);
                }

                $version = $locked->versions()->create([
                    'version_number' => ((int) $locked->versions()->max('version_number')) + 1,
                    'version_label' => $validated['versionLabel'],
                    'change_summary' => $validated['changeSummary'],
                    ...$storedFile,
                    'uploaded_by' => $request->user()->id,
                ]);
                $oldVersionId = $locked->current_version_id;
                $locked->forceFill([
                    'current_version_id' => $version->id,
                    'version' => $version->version_label,
                    'updated_by' => $request->user()->id,
                ])->save();

                $this->record(
                    $request,
                    'document.version_created',
                    $locked,
                    ['currentVersionId' => $oldVersionId],
                    [
                        'currentVersionId' => $version->id,
                        'versionNumber' => $version->version_number,
                        'versionLabel' => $version->version_label,
                        'changeSummary' => $version->change_summary,
                        'checksumSha256' => $version->checksum_sha256,
                    ],
                );

                return $version;
            }, 3);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($storedFile['storage_path']);
            throw $error;
        }

        return response()->json([
            'success' => true,
            'message' => "Document version {$version->version_number} created successfully.",
            'data' => [
                'document' => $this->data($this->loadDocument($document->fresh())),
            ],
        ], 201);
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        $this->access->authorizeView($request->user(), $document);
        $document = $this->loadDocument($document);
        $oldValues = $this->auditValues($document);
        $document->forceFill([
            'is_active' => false,
            'updated_by' => $request->user()->id,
        ])->save();
        $document->delete();
        $this->record(
            $request,
            'document.archived',
            $document,
            $oldValues,
            $this->auditValues($document),
        );

        return response()->json([
            'success' => true,
            'message' => 'Document archived successfully. Its complete version history was retained.',
        ]);
    }

    public function restore(Request $request, int $document): JsonResponse
    {
        $record = $this->loadDocument(Document::onlyTrashed()->findOrFail($document));
        $this->access->authorizeView($request->user(), $record);
        $current = $record->currentVersion;
        $path = $current?->storage_path ?? $record->storage_path;

        if (! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages([
                'document' => ['The current version file is missing and this document cannot be restored.'],
            ]);
        }

        $record->restore();
        $record->forceFill([
            'is_active' => true,
            'updated_by' => $request->user()->id,
        ])->save();
        $this->record(
            $request,
            'document.restored',
            $record,
            ['isActive' => false, 'isArchived' => true],
            $this->auditValues($record),
        );

        return response()->json([
            'success' => true,
            'message' => 'Document restored successfully.',
            'data' => ['document' => $this->data($this->loadDocument($record->fresh()))],
        ]);
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $this->access->authorizeView($request->user(), $document);
        $document->loadMissing('currentVersion');
        $version = $document->currentVersion;

        ActivityRecorder::record(
            $request,
            'document.downloaded',
            "Downloaded {$document->document_code} — {$document->title}.",
            metadata: ['module' => 'CORE', 'recordType' => Document::class, 'recordId' => $document->id],
        );

        return $this->downloadFile(
            $version?->storage_path ?? $document->storage_path,
            $version?->original_file_name ?? $document->original_file_name,
            $version?->mime_type ?? $document->mime_type,
        );
    }

    public function downloadVersion(
        Request $request,
        Document $document,
        DocumentVersion $version,
    ): StreamedResponse {
        $this->access->authorizeView($request->user(), $document);
        abort_unless((int) $version->document_id === (int) $document->id, 404);

        ActivityRecorder::record(
            $request,
            'document.version_downloaded',
            "Downloaded {$document->document_code} version {$version->version_number}.",
            metadata: ['module' => 'CORE', 'recordType' => Document::class, 'recordId' => $document->id, 'versionId' => $version->id],
        );

        return $this->downloadFile(
            $version->storage_path,
            $version->original_file_name,
            $version->mime_type,
        );
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated, ?Document $document = null): array
    {
        return [
            'document_type_id' => $validated['documentTypeId'],
            'confidentiality_level_id' => $validated['confidentialityLevelId'],
            'title' => $validated['title'],
            'reference_number' => $validated['referenceNumber'] ?? null,
            'issuing_authority' => $validated['issuingAuthority'] ?? null,
            'publication_date' => $validated['publicationDate'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['isActive'] ?? $document?->is_active ?? true,
        ];
    }

    /** @return array<string, mixed> */
    private function storeFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid().($extension ? ".{$extension}" : '');
        $path = Storage::disk('local')->putFileAs('documents', $file, $storedName);

        if (! $path) {
            throw ValidationException::withMessages([
                'file' => ['The document could not be stored. Please try again.'],
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
    private function data(Document $document): array
    {
        $document = $this->loadDocument($document);
        $current = $document->currentVersion;

        return [
            'id' => $document->id,
            'documentCode' => $document->document_code,
            'documentTypeId' => $document->document_type_id,
            'documentType' => $document->documentType?->label ?? 'Unclassified',
            'documentTypeCode' => $document->documentType?->code,
            'confidentialityLevelId' => $document->confidentiality_level_id,
            'confidentialityLevel' => $document->confidentialityLevel?->label ?? 'Internal',
            'confidentialityCode' => $document->confidentialityLevel?->code ?? 'INTERNAL',
            'title' => $document->title,
            'referenceNumber' => $document->reference_number,
            'issuingAuthority' => $document->issuing_authority,
            'publicationDate' => $document->publication_date?->toDateString(),
            'version' => $current?->version_label ?? $document->version,
            'currentVersionId' => $current?->id,
            'currentVersionNumber' => $current?->version_number ?? 1,
            'versionCount' => $document->versions->count(),
            'description' => $document->description,
            'fileName' => $current?->original_file_name ?? $document->original_file_name,
            'fileExtension' => $current?->file_extension ?? $document->file_extension,
            'fileSize' => $current?->file_size ?? $document->file_size,
            'mimeType' => $current?->mime_type ?? $document->mime_type,
            'checksumSha256' => $current?->checksum_sha256 ?? $document->checksum_sha256,
            'uploadedBy' => $document->uploader?->name ?? 'System',
            'updatedBy' => $document->updater?->name ?? 'System',
            'createdAt' => $document->created_at?->toIso8601String(),
            'updatedAt' => $document->updated_at?->toIso8601String(),
            'isActive' => $document->is_active,
            'isArchived' => $document->trashed(),
            'links' => $document->links->map(fn ($link): array => [
                'id' => $link->id,
                'key' => "{$link->module_code}:{$link->record_type}:{$link->record_id}",
                'module' => $link->module_code,
                'moduleLabel' => DocumentLinkRegistry::MODULES[$link->module_code] ?? $link->module_code,
                'recordType' => $link->record_type,
                'recordId' => $link->record_id,
                'recordCode' => $link->record_code,
                'label' => $link->record_label,
                'linkedBy' => $link->linkedBy?->name ?? 'System',
                'linkedAt' => $link->created_at?->toIso8601String(),
            ])->values(),
            'linkKeys' => $document->links->map(
                fn ($link): string => "{$link->module_code}:{$link->record_type}:{$link->record_id}",
            )->values(),
            'versions' => $document->versions->map(fn (DocumentVersion $version): array => [
                'id' => $version->id,
                'versionNumber' => $version->version_number,
                'versionLabel' => $version->version_label,
                'changeSummary' => $version->change_summary,
                'fileName' => $version->original_file_name,
                'fileExtension' => $version->file_extension,
                'fileSize' => $version->file_size,
                'mimeType' => $version->mime_type,
                'checksumSha256' => $version->checksum_sha256,
                'uploadedBy' => $version->uploader?->name ?? 'System',
                'createdAt' => $version->created_at?->toIso8601String(),
                'isCurrent' => (int) $version->id === (int) $document->current_version_id,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function auditValues(Document $document): array
    {
        $document = $this->loadDocument($document);
        $current = $document->currentVersion;

        return [
            'documentTypeId' => $document->document_type_id,
            'confidentialityLevelId' => $document->confidentiality_level_id,
            'confidentialityCode' => $document->confidentialityLevel?->code,
            'title' => $document->title,
            'referenceNumber' => $document->reference_number,
            'issuingAuthority' => $document->issuing_authority,
            'publicationDate' => $document->publication_date?->toDateString(),
            'currentVersionId' => $current?->id,
            'currentVersionNumber' => $current?->version_number,
            'versionLabel' => $current?->version_label,
            'checksumSha256' => $current?->checksum_sha256,
            'links' => $document->links
                ->map(fn ($link): string => "{$link->module_code}:{$link->record_type}:{$link->record_id}")
                ->sort()
                ->values()
                ->all(),
            'isActive' => $document->is_active,
            'isArchived' => $document->trashed(),
        ];
    }

    /** @param list<array<string, mixed>> $links */
    private function syncLinks(Document $document, array $links): void
    {
        $document->links()->delete();
        if ($links !== []) {
            $document->links()->createMany($links);
        }
    }

    private function record(
        Request $request,
        string $action,
        Document $document,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => Document::class,
            'auditable_id' => $document->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);
        ActivityRecorder::record(
            $request,
            $action,
            str_replace('.', ' ', ucfirst($action)).": {$document->document_code} — {$document->title}.",
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: ['module' => 'CORE', 'recordType' => Document::class, 'recordId' => $document->id],
        );
    }

    private function downloadFile(
        string $path,
        string $fileName,
        string $mimeType,
    ): StreamedResponse {
        abort_unless(Storage::disk('local')->exists($path), 404, 'Stored file not found.');

        return Storage::disk('local')->download(
            $path,
            $fileName,
            ['Content-Type' => $mimeType],
        );
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'documentType',
            'confidentialityLevel',
            'uploader:id,name',
            'updater:id,name',
            'currentVersion.uploader:id,name',
            'versions.uploader:id,name',
            'links.linkedBy:id,name',
        ];
    }

    private function loadDocument(Document $document): Document
    {
        return $document->load($this->relations());
    }

    private function documentTypes()
    {
        $listId = MasterList::query()->where('code', 'DOCUMENT_TYPE')->value('id');

        return MasterListItem::query()
            ->where('master_list_id', $listId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get(['id', 'code', 'label', 'description']);
    }

    private function confidentialityLevels(Request $request)
    {
        $listId = MasterList::query()->where('code', 'DOCUMENT_CONFIDENTIALITY')->value('id');
        $codes = DocumentAccessService::PUBLIC_CODES;
        if ($request->user()->hasPermission('documents.view_confidential')) {
            $codes[] = 'CONFIDENTIAL';
        }
        if ($request->user()->hasPermission('documents.view_restricted')) {
            $codes[] = 'RESTRICTED';
        }

        return MasterListItem::query()
            ->where('master_list_id', $listId)
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get(['id', 'code', 'label', 'description']);
    }
}
