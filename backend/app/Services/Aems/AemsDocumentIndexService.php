<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\DocumentVersion;
use App\Models\EngagementDocumentIndexItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AemsDocumentIndexService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(Request $request, AuditEngagement $engagement): array
    {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.document-index.view',
        );
        $items = $engagement->documentIndexItems()
            ->with(['documentVersion.document', 'closure'])
            ->orderBy('sequence_no')
            ->get();
        $eligible = $this->discover($engagement);
        $indexedKeys = $items->map(fn ($item): string => $this->sourceKey(
            $item->record_type,
            $item->record_id,
            $item->document_version_id,
        ));
        $missing = $eligible->reject(
            fn (array $record): bool => $indexedKeys->contains($this->sourceKey(
                $record['recordType'],
                $record['recordId'],
                $record['documentVersionId'],
            )),
        )->values();

        return [
            'items' => $items->map(fn ($item): array => $this->itemData($item))->values()->all(),
            'eligibleMissing' => $missing->all(),
            'summary' => $this->summary($items, $missing),
            'lockedAt' => $engagement->closure?->document_index_locked_at?->toISOString(),
        ];
    }

    public function refresh(Request $request, AuditEngagement $engagement): Collection
    {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.document-index.manage',
        );
        if ($engagement->closure?->document_index_locked_at) {
            throw ValidationException::withMessages(['documentIndex' => ['The final document index is locked.']]);
        }
        $eligible = $this->discover($engagement);
        $next = (int) $engagement->documentIndexItems()->max('sequence_no') + 1;
        foreach ($eligible as $record) {
            $exists = $engagement->documentIndexItems()
                ->where('record_type', $record['recordType'])
                ->where('record_id', $record['recordId'])
                ->where('document_version_id', $record['documentVersionId'])
                ->exists();
            if ($exists) {
                continue;
            }
            $engagement->documentIndexItems()->create([
                'engagement_closure_id' => $engagement->closure?->id,
                'sequence_no' => $next++,
                'record_category_code' => $record['recordCategoryCode'],
                'record_type' => $record['recordType'],
                'record_id' => $record['recordId'],
                'document_id' => $record['documentId'],
                'document_version_id' => $record['documentVersionId'],
                'reference_code' => $record['referenceCode'],
                'title' => $record['title'],
                'version_label' => $record['versionLabel'],
                'document_date' => $record['documentDate'],
                'confidentiality_code' => $record['confidentialityCode'],
                'included_flag' => true,
                'indexed_by' => $request->user()->id,
                'indexed_at' => now(),
            ]);
        }
        $this->record($request, $engagement, 'aems.document_index.refreshed');

        return $engagement->documentIndexItems()->with('documentVersion.document')
            ->orderBy('sequence_no')->get();
    }

    public function add(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): EngagementDocumentIndexItem {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.document-index.manage',
        );
        if ($engagement->closure?->document_index_locked_at) {
            throw ValidationException::withMessages(['documentIndex' => ['The final document index is locked.']]);
        }
        $version = DocumentVersion::query()->with('document')->findOrFail($attributes['documentVersionId']);
        $sequence = (int) $engagement->documentIndexItems()->max('sequence_no') + 1;
        $item = $engagement->documentIndexItems()->create([
            'engagement_closure_id' => $engagement->closure?->id,
            'sequence_no' => $sequence,
            'record_category_code' => strtoupper($attributes['recordCategoryCode']),
            'record_type' => $attributes['recordType'] ?? 'AUTHORIZED_SUPPORT',
            'record_id' => $attributes['recordId'] ?? null,
            'document_id' => $version->document_id,
            'document_version_id' => $version->id,
            'reference_code' => $attributes['referenceCode'],
            'title' => $attributes['title'],
            'version_label' => $version->version_label,
            'document_date' => $attributes['documentDate'] ?? null,
            'confidentiality_code' => strtoupper($attributes['confidentialityCode'] ?? 'INTERNAL'),
            'retention_rule_code' => $attributes['retentionRuleCode'] ?? null,
            'included_flag' => true,
            'indexed_by' => $request->user()->id,
            'indexed_at' => now(),
        ]);
        $this->record($request, $engagement, 'aems.document_index.supporting_record_added');

        return $item->load('documentVersion.document');
    }

    public function exclude(
        Request $request,
        AuditEngagement $engagement,
        EngagementDocumentIndexItem $item,
        string $reason,
    ): EngagementDocumentIndexItem {
        $this->ensureItem($engagement, $item);
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.document-index.finalize',
        );
        if ($engagement->closure?->document_index_locked_at) {
            throw ValidationException::withMessages(['documentIndex' => ['The final document index is locked.']]);
        }
        $item->fill([
            'included_flag' => false,
            'exclusion_reason' => $reason,
            'exclusion_authorized_by' => $request->user()->id,
        ])->save();
        $this->record($request, $engagement, 'aems.document_index.record_excluded', [
            'itemId' => $item->id,
            'reason' => $reason,
        ]);

        return $item->fresh('documentVersion.document');
    }

    /** @return array{ready: bool, blockers: list<string>, total: int, included: int, broken: int} */
    public function readiness(AuditEngagement $engagement): array
    {
        $items = $engagement->documentIndexItems()->with('documentVersion')->get();
        $missing = $this->discover($engagement)->reject(function (array $record) use ($items): bool {
            return $items->contains(fn ($item): bool => $this->sourceKey(
                $item->record_type,
                $item->record_id,
                $item->document_version_id,
            ) === $this->sourceKey(
                $record['recordType'],
                $record['recordId'],
                $record['documentVersionId'],
            ));
        });
        $broken = $items->where('included_flag', true)->filter(
            fn ($item): bool => ! $item->documentVersion
                || ! Storage::disk('local')->exists($item->documentVersion->storage_path),
        );
        $invalidExclusions = $items->where('included_flag', false)->filter(
            fn ($item): bool => blank($item->exclusion_reason) || ! $item->exclusion_authorized_by,
        );
        $blockers = [];
        if ($items->where('included_flag', true)->isEmpty()) {
            $blockers[] = 'The final document index has no included records.';
        }
        if ($missing->isNotEmpty()) {
            $blockers[] = "{$missing->count()} eligible official record(s) are not indexed.";
        }
        if ($broken->isNotEmpty()) {
            $blockers[] = "{$broken->count()} included document file reference(s) are missing or broken.";
        }
        if ($invalidExclusions->isNotEmpty()) {
            $blockers[] = "{$invalidExclusions->count()} exclusion(s) lack a reason or authority.";
        }

        return [
            'ready' => $blockers === [],
            'blockers' => $blockers,
            'total' => $items->count(),
            'included' => $items->where('included_flag', true)->count(),
            'broken' => $broken->count(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function discover(AuditEngagement $engagement): Collection
    {
        $engagement->loadMissing([
            'specialAuthorityDocumentVersion.document',
            'engagementOrder.versions.documentVersion.document',
            'engagementPlan.versions.documentVersion.document',
            'entryConference.attachments.documentVersion.document',
            'workingPapers.versions.documentVersion.document',
            'evidence.documentVersion.document',
            'findings.managementResponses.attachments.documentVersion.document',
            'findings.managementResponses.rejoinders.attachments.documentVersion.document',
            'exitConferences.minutesDocumentVersion.document',
            'exitConferences.attachments.documentVersion.document',
            'reports.versions.documentVersion.document',
            'completionAssessments.versions.documentVersion.document',
            'closure',
        ]);
        $records = collect();
        if ($engagement->specialAuthorityDocumentVersion) {
            $records->push($this->eligible(
                'AUTHORITY',
                'SPECIAL_AUTHORITY',
                $engagement->id,
                $engagement->engagement_code,
                'Special engagement authority',
                $engagement->specialAuthorityDocumentVersion,
            ));
        }
        foreach ($engagement->engagementOrder?->versions ?? [] as $version) {
            if ($version->documentVersion) {
                $records->push($this->eligible(
                    'AUTHORITY',
                    'AUDIT_ENGAGEMENT_ORDER_VERSION',
                    $version->id,
                    $engagement->engagementOrder->order_code,
                    'Audit Engagement Order',
                    $version->documentVersion,
                ));
            }
        }
        foreach ($engagement->engagementPlan?->versions ?? [] as $version) {
            if ($version->documentVersion) {
                $records->push($this->eligible(
                    'PLANNING',
                    'AUDIT_ENGAGEMENT_PLAN_VERSION',
                    $version->id,
                    $engagement->engagementPlan->plan_code,
                    'Audit Engagement Plan',
                    $version->documentVersion,
                ));
            }
        }
        foreach ($engagement->entryConference?->attachments ?? [] as $attachment) {
            $records->push($this->eligible(
                'ENTRY_CONFERENCE',
                'ENTRY_CONFERENCE_ATTACHMENT',
                $attachment->id,
                $attachment->attachment_code,
                $attachment->caption ?: 'Entry Conference attachment',
                $attachment->documentVersion,
            ));
        }
        foreach ($engagement->workingPapers as $paper) {
            foreach ($paper->versions as $version) {
                if ($version->documentVersion) {
                    $records->push($this->eligible(
                        'WORKING_PAPER',
                        'WORKING_PAPER_VERSION',
                        $version->id,
                        $paper->working_paper_code,
                        $paper->title,
                        $version->documentVersion,
                    ));
                }
            }
        }
        foreach ($engagement->evidence as $evidence) {
            $records->push($this->eligible(
                'EVIDENCE',
                'AUDIT_EVIDENCE',
                $evidence->id,
                $evidence->evidence_code,
                $evidence->title,
                $evidence->documentVersion,
            ));
        }
        foreach ($engagement->findings as $finding) {
            foreach ($finding->managementResponses as $response) {
                foreach ($response->attachments as $attachment) {
                    $records->push($this->eligible(
                        'MANAGEMENT_RESPONSE',
                        'MANAGEMENT_RESPONSE_ATTACHMENT',
                        $attachment->id,
                        $response->response_code,
                        'Management response attachment',
                        $attachment->documentVersion,
                    ));
                }
                foreach ($response->rejoinders as $rejoinder) {
                    foreach ($rejoinder->attachments as $attachment) {
                        $records->push($this->eligible(
                            'REJOINDER',
                            'REJOINDER_ATTACHMENT',
                            $attachment->id,
                            $finding->finding_code,
                            'Auditor rejoinder attachment',
                            $attachment->documentVersion,
                        ));
                    }
                }
            }
        }
        foreach ($engagement->exitConferences as $conference) {
            if ($conference->minutesDocumentVersion) {
                $records->push($this->eligible(
                    'EXIT_CONFERENCE',
                    'EXIT_CONFERENCE_MINUTES',
                    $conference->id,
                    $conference->conference_code,
                    'Exit Conference minutes',
                    $conference->minutesDocumentVersion,
                ));
            }
            foreach ($conference->attachments as $attachment) {
                $records->push($this->eligible(
                    'EXIT_CONFERENCE',
                    'EXIT_CONFERENCE_ATTACHMENT',
                    $attachment->id,
                    $attachment->attachment_code,
                    $attachment->caption ?: 'Exit Conference attachment',
                    $attachment->documentVersion,
                ));
            }
        }
        foreach ($engagement->reports as $report) {
            foreach ($report->versions as $version) {
                if ($version->documentVersion) {
                    $records->push($this->eligible(
                        $version->report_stage === 'FINAL_REPORT' ? 'FINAL_REPORT' : 'DRAFT_REPORT',
                        'AUDIT_REPORT_VERSION',
                        $version->id,
                        $report->report_code,
                        $report->title,
                        $version->documentVersion,
                    ));
                }
            }
        }
        foreach ($engagement->completionAssessments as $assessment) {
            foreach ($assessment->versions as $version) {
                if ($version->documentVersion) {
                    $records->push($this->eligible(
                        'COMPLETION_ASSESSMENT',
                        'COMPLETION_ASSESSMENT_VERSION',
                        $version->id,
                        $assessment->assessment_code,
                        'Completion Assessment',
                        $version->documentVersion,
                    ));
                }
            }
        }
        if ($engagement->closure?->closureDocumentVersion) {
            $records->push($this->eligible(
                'CLOSURE',
                'ENGAGEMENT_CLOSURE',
                $engagement->closure->id,
                $engagement->closure->closure_code,
                'Engagement Closure',
                $engagement->closure->closureDocumentVersion,
            ));
        }

        return $records->filter(fn ($record) => $record['documentVersionId'] !== null)
            ->unique(fn ($record) => $this->sourceKey(
                $record['recordType'],
                $record['recordId'],
                $record['documentVersionId'],
            ))
            ->values();
    }

    /** @return array<string, mixed> */
    private function eligible(
        string $category,
        string $recordType,
        int $recordId,
        string $reference,
        string $title,
        ?DocumentVersion $version,
    ): array {
        return [
            'recordCategoryCode' => $category,
            'recordType' => $recordType,
            'recordId' => $recordId,
            'documentId' => $version?->document_id,
            'documentVersionId' => $version?->id,
            'referenceCode' => $reference,
            'title' => $title,
            'versionLabel' => $version?->version_label,
            'documentDate' => $version?->created_at?->toDateString(),
            'confidentialityCode' => $version?->document?->confidentialityLevel?->code ?? 'INTERNAL',
            'fileAvailable' => $version
                ? Storage::disk('local')->exists($version->storage_path)
                : false,
        ];
    }

    private function sourceKey(string $type, ?int $recordId, ?int $versionId): string
    {
        return "{$type}:{$recordId}:{$versionId}";
    }

    /** @return array<string, mixed> */
    private function itemData(EngagementDocumentIndexItem $item): array
    {
        return [
            'id' => $item->id,
            'sequenceNo' => $item->sequence_no,
            'recordCategoryCode' => $item->record_category_code,
            'recordType' => $item->record_type,
            'recordId' => $item->record_id,
            'documentId' => $item->document_id,
            'documentVersionId' => $item->document_version_id,
            'referenceCode' => $item->reference_code,
            'title' => $item->title,
            'versionLabel' => $item->version_label,
            'documentDate' => $item->document_date?->toDateString(),
            'confidentialityCode' => $item->confidentiality_code,
            'retentionRuleCode' => $item->retention_rule_code,
            'includedFlag' => $item->included_flag,
            'exclusionReason' => $item->exclusion_reason,
            'fileAvailable' => $item->documentVersion
                ? Storage::disk('local')->exists($item->documentVersion->storage_path)
                : false,
            'checksumSha256' => $item->documentVersion?->checksum_sha256,
        ];
    }

    /** @return array<string, mixed> */
    private function summary(Collection $items, Collection $missing): array
    {
        $broken = $items->where('included_flag', true)->filter(
            fn ($item) => ! $item->documentVersion
                || ! Storage::disk('local')->exists($item->documentVersion->storage_path),
        )->count();

        return [
            'total' => $items->count(),
            'included' => $items->where('included_flag', true)->count(),
            'excluded' => $items->where('included_flag', false)->count(),
            'eligibleMissing' => $missing->count(),
            'brokenReferences' => $broken,
            'complete' => $items->where('included_flag', true)->isNotEmpty()
                && $missing->isEmpty()
                && $broken === 0,
        ];
    }

    private function ensureItem(
        AuditEngagement $engagement,
        EngagementDocumentIndexItem $item,
    ): void {
        if ((int) $item->audit_engagement_id !== (int) $engagement->id) {
            abort(404);
        }
    }

    /** @param array<string, mixed>|null $metadata */
    private function record(
        Request $request,
        AuditEngagement $engagement,
        string $action,
        ?array $metadata = null,
    ): void {
        $snapshot = $this->readiness($engagement);
        $this->support->event(
            $request,
            $engagement,
            $action,
            null,
            null,
            null,
            $snapshot,
            null,
            'FINAL_DOCUMENT_INDEX',
            $engagement->id,
            null,
            $engagement->engagement_code,
        );
        $this->support->audit($request, $action, $engagement, null, $snapshot, $metadata);
    }
}
