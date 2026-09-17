<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Models\AuditEngagement;
use App\Models\EngagementDocumentIndexItem;
use App\Services\AemsDocumentIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AemsDocumentIndexController extends Controller
{
    public function __construct(private readonly AemsDocumentIndexService $index) {}

    public function show(Request $request, AuditEngagement $engagement): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->index->workspace($request, $engagement),
        ]);
    }

    public function refresh(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $this->index->refresh($request, $engagement);

        return response()->json([
            'success' => true,
            'message' => 'Eligible authoritative records discovered and indexed.',
            'data' => $this->index->workspace($request, $engagement),
        ]);
    }

    public function store(Request $request, AuditEngagement $engagement): JsonResponse
    {
        $validated = $request->validate([
            'documentVersionId' => ['required', 'exists:document_versions,id'],
            'recordCategoryCode' => ['required', 'string', 'max:60'],
            'recordType' => ['nullable', 'string', 'max:120'],
            'recordId' => ['nullable', 'integer', 'min:1'],
            'referenceCode' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:255'],
            'documentDate' => ['nullable', 'date'],
            'confidentialityCode' => ['nullable', 'string', 'max:60'],
            'retentionRuleCode' => ['nullable', 'string', 'max:100'],
        ]);
        $item = $this->index->add($request, $engagement, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Authorized supporting record added to the final index.',
            'data' => ['item' => $item],
        ], 201);
    }

    public function exclude(
        Request $request,
        AuditEngagement $engagement,
        EngagementDocumentIndexItem $item,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:10000'],
        ]);
        $item = $this->index->exclude($request, $engagement, $item, $validated['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Document-index record excluded with reason and authority.',
            'data' => ['item' => $item],
        ]);
    }

    public function export(Request $request, AuditEngagement $engagement): StreamedResponse
    {
        $workspace = $this->index->workspace($request, $engagement);
        $fileName = "{$engagement->engagement_code}-final-document-index.csv";

        return response()->streamDownload(function () use ($workspace): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Sequence',
                'Category',
                'Reference',
                'Title',
                'Version',
                'DocumentVersion ID',
                'Checksum SHA-256',
                'Included',
                'File Available',
                'Exclusion Reason',
            ]);
            foreach ($workspace['items'] as $item) {
                fputcsv($handle, [
                    $item['sequenceNo'],
                    $item['recordCategoryCode'],
                    $item['referenceCode'],
                    $item['title'],
                    $item['versionLabel'],
                    $item['documentVersionId'],
                    $item['checksumSha256'],
                    $item['includedFlag'] ? 'YES' : 'NO',
                    $item['fileAvailable'] ? 'YES' : 'NO',
                    $item['exclusionReason'],
                ]);
            }
            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
