<?php

namespace App\Services\Cms;

use App\Models\AuditLog;
use App\Models\CmsRecommendationCase;
use App\Models\CmsReportExport;
use App\Models\CmsReportRun;
use App\Models\User;
use App\Support\ActivityRecorder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates scope-aware, reproducible CMS report snapshots and private files.
 *
 * Report runs pin the visible case IDs, filter set, source-query version, row
 * snapshot, and checksum. Export files are derived only from that immutable
 * snapshot, never from a second live query.
 */
class CmsReportService
{
    public const SOURCE_QUERY_VERSION = 'CMS-12A-v1';

    /** @var array<string, array<string, mixed>> */
    public const REPORTS = [
        'portfolio-status' => [
            'title' => 'CMS Recommendation Portfolio Status',
            'description' => 'Current status, risk, responsibility, targets, and source lineage for visible recommendations.',
            'filters' => ['search', 'status', 'officeId', 'riskCode', 'dateFrom', 'dateTo'],
            'columns' => [
                ['key' => 'caseCode', 'label' => 'CMS Case'],
                ['key' => 'recommendationCode', 'label' => 'Recommendation'],
                ['key' => 'engagementCode', 'label' => 'Engagement'],
                ['key' => 'findingCode', 'label' => 'Finding'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'risk', 'label' => 'Risk'],
                ['key' => 'responsibleOffice', 'label' => 'Responsible Office'],
                ['key' => 'targetDate', 'label' => 'Effective Target Date'],
                ['key' => 'overdueDays', 'label' => 'Days Overdue'],
                ['key' => 'sourceReport', 'label' => 'Source Report'],
            ],
        ],
        'implementation-progress' => [
            'title' => 'CMS Implementation Progress Report',
            'description' => 'Action Plan acceptance, management-reported progress, independent validation, and current case status.',
            'filters' => ['search', 'status', 'officeId', 'riskCode', 'dateFrom', 'dateTo'],
            'columns' => [
                ['key' => 'caseCode', 'label' => 'CMS Case'],
                ['key' => 'recommendationCode', 'label' => 'Recommendation'],
                ['key' => 'status', 'label' => 'Case Status'],
                ['key' => 'actionPlanStatus', 'label' => 'Action Plan'],
                ['key' => 'acceptedTargetDate', 'label' => 'Accepted Target Date'],
                ['key' => 'latestProgressStatus', 'label' => 'Latest Progress'],
                ['key' => 'reportedProgress', 'label' => 'Reported Progress %'],
                ['key' => 'lastReportingPeriodEnd', 'label' => 'Last Reporting Period'],
                ['key' => 'validationStatus', 'label' => 'Validation'],
                ['key' => 'validationConclusion', 'label' => 'Validation Conclusion'],
            ],
        ],
        'target-date-monitoring' => [
            'title' => 'CMS Target-Date Monitoring Report',
            'description' => 'Effective target dates, approved extensions, overdue age, and active escalation indicators.',
            'filters' => ['search', 'status', 'officeId', 'riskCode', 'dateFrom', 'dateTo'],
            'columns' => [
                ['key' => 'caseCode', 'label' => 'CMS Case'],
                ['key' => 'recommendationCode', 'label' => 'Recommendation'],
                ['key' => 'responsibleOffice', 'label' => 'Responsible Office'],
                ['key' => 'status', 'label' => 'Case Status'],
                ['key' => 'targetDate', 'label' => 'Effective Target Date'],
                ['key' => 'targetDateSource', 'label' => 'Target Date Source'],
                ['key' => 'overdueDays', 'label' => 'Days Overdue'],
                ['key' => 'extensionStatus', 'label' => 'Extension Request'],
                ['key' => 'activeEscalation', 'label' => 'Active Escalation'],
            ],
        ],
        'closure-readiness' => [
            'title' => 'CMS Closure and Disposition Readiness Report',
            'description' => 'Implementation, validation, closure-candidate, disposition, and escalation signals without making a final decision.',
            'filters' => ['search', 'status', 'officeId', 'riskCode', 'dateFrom', 'dateTo'],
            'columns' => [
                ['key' => 'caseCode', 'label' => 'CMS Case'],
                ['key' => 'recommendationCode', 'label' => 'Recommendation'],
                ['key' => 'status', 'label' => 'Case Status'],
                ['key' => 'validationConclusion', 'label' => 'Validation Conclusion'],
                ['key' => 'closureCandidate', 'label' => 'Closure Candidate'],
                ['key' => 'closureRequestStatus', 'label' => 'Closure Request'],
                ['key' => 'dispositionStatus', 'label' => 'Disposition Request'],
                ['key' => 'activeEscalation', 'label' => 'Active Escalation'],
                ['key' => 'professionalDecision', 'label' => 'Professional Decision'],
            ],
        ],
    ];

    public function __construct(private readonly CmsRecommendationScopeService $scope) {}

    /** @return array<string, mixed> */
    public function catalog(User $user): array
    {
        $this->authorize($user, 'cms.report.view');

        return [
            'reports' => collect(self::REPORTS)->map(
                fn (array $definition, string $code): array => [
                    'code' => $code,
                    'title' => $definition['title'],
                    'description' => $definition['description'],
                    'filters' => $definition['filters'],
                    'columns' => $definition['columns'],
                ],
            )->values(),
            'formats' => [
                ['code' => 'csv', 'label' => 'CSV', 'mimeType' => 'text/csv'],
                ['code' => 'pdf', 'label' => 'PDF', 'mimeType' => 'application/pdf'],
            ],
            'scope' => $this->scope->summary($user),
            'canExport' => $user->hasPermission('cms.report.export'),
        ];
    }

    /** @return Collection<int, CmsReportRun> */
    public function runs(User $user, int $limit = 50): Collection
    {
        $this->authorize($user, 'cms.report.view');
        $visibleCaseIds = $this->visibleCaseIds($user);

        return CmsReportRun::query()
            ->with(['generator:id,name,employee_id', 'exports'])
            ->latest('generated_at')
            ->limit($limit)
            ->get()
            ->filter(fn (CmsReportRun $run): bool => $this->runIsVisible($run, $visibleCaseIds))
            ->values();
    }

    public function show(User $user, int $runId): CmsReportRun
    {
        $this->authorize($user, 'cms.report.view');
        $run = CmsReportRun::query()
            ->with(['generator:id,name,employee_id', 'exports'])
            ->findOrFail($runId);
        $this->ensureRunVisible($user, $run);

        return $run;
    }

    /** @param array<string, mixed> $input */
    public function generate(Request $http, string $code, array $input): CmsReportRun
    {
        $actor = $http->user();
        $this->authorize($actor, 'cms.report.view');
        $definition = self::REPORTS[$code] ?? null;
        abort_unless($definition, 404, 'The CMS report is unavailable.');
        $filters = $this->filters($input);

        $cases = $this->visibleCases($actor);
        $rows = $this->rows($code, $cases)
            ->filter(fn (array $row): bool => $this->matchesFilters($row, $filters))
            ->sortBy('caseCode', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $caseIds = $rows->pluck('_caseId')->map(fn ($id): int => (int) $id)->values()->all();
        $publicRows = $rows->map(function (array $row): array {
            unset($row['_caseId']);

            return $row;
        })->all();
        $snapshot = [
            'columns' => $definition['columns'],
            'rows' => $publicRows,
        ];
        $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $run = DB::transaction(function () use ($actor, $code, $definition, $filters, $caseIds, $snapshot, $publicRows, $encoded): CmsReportRun {
            return CmsReportRun::query()->create([
                'report_code' => $code,
                'report_title' => $definition['title'],
                'source_query_version' => self::SOURCE_QUERY_VERSION,
                'filters' => $filters,
                'scope_snapshot' => [
                    'caseIds' => $caseIds,
                    'visibility' => $this->scope->summary($actor),
                    'generatedByUserId' => $actor->id,
                ],
                'result_snapshot' => $snapshot,
                'row_count' => count($publicRows),
                'result_checksum_sha256' => hash('sha256', $encoded),
                'generated_by' => $actor->id,
                'generated_at' => now(),
            ]);
        });

        $this->record(
            $http,
            'cms.report.generated',
            "Generated the {$definition['title']} CMS report with {$run->row_count} row(s).",
            $run,
            ['reportCode' => $code, 'filters' => $filters, 'rowCount' => $run->row_count, 'checksumSha256' => $run->result_checksum_sha256],
        );

        return $run->load(['generator:id,name,employee_id', 'exports']);
    }

    public function export(Request $http, int $runId, string $format): CmsReportExport
    {
        $actor = $http->user();
        $this->authorize($actor, 'cms.report.export');
        $format = strtolower($format);
        abort_unless(in_array($format, ['csv', 'pdf'], true), 422, 'The export format is unsupported.');
        $run = $this->show($actor, $runId);
        $latest = CmsReportExport::query()
            ->where('cms_report_run_id', $run->id)
            ->where('format', strtoupper($format))
            ->latest('version_number')
            ->first();

        if ($latest && Storage::disk('local')->exists($latest->storage_path)) {
            return $latest;
        }

        $version = ($latest?->version_number ?? 0) + 1;
        [$contents, $mimeType, $extension] = $format === 'csv'
            ? [$this->csv($run), 'text/csv; charset=UTF-8', 'csv']
            : [$this->pdf($run), 'application/pdf', 'pdf'];
        $checksum = hash('sha256', $contents);
        $name = Str::slug($run->report_title).'-'.$run->display_code.'-v'.$version.'.'.$extension;
        $path = 'cms/reports/'.$run->id.'/'.$format.'/v'.$version.'-'.$checksum.'.'.$extension;
        Storage::disk('local')->put($path, $contents);

        $export = CmsReportExport::query()->create([
            'cms_report_run_id' => $run->id,
            'format' => strtoupper($format),
            'version_number' => $version,
            'file_name' => $name,
            'storage_path' => $path,
            'mime_type' => $mimeType,
            'file_size' => strlen($contents),
            'checksum_sha256' => $checksum,
            'generated_by' => $actor->id,
            'generated_at' => now(),
        ]);

        $this->record(
            $http,
            'cms.report.exported',
            "Generated {$format} export {$name} from {$run->display_code}.",
            $export,
            [
                'runId' => $run->id,
                'reportCode' => $run->report_code,
                'format' => $format,
                'versionNumber' => $version,
                'rowCount' => $run->row_count,
                'checksumSha256' => $checksum,
            ],
        );

        return $export;
    }

    public function download(Request $http, int $exportId): StreamedResponse
    {
        $actor = $http->user();
        $this->authorize($actor, 'cms.report.export');
        $export = CmsReportExport::query()->with('run')->findOrFail($exportId);
        $this->ensureRunVisible($actor, $export->run);
        abort_unless(Storage::disk('local')->exists($export->storage_path), 404, 'The private report export file is unavailable.');

        $this->record(
            $http,
            'cms.report.downloaded',
            "Downloaded CMS report export {$export->file_name}.",
            $export,
            ['runId' => $export->cms_report_run_id, 'format' => strtolower($export->format), 'checksumSha256' => $export->checksum_sha256],
        );

        return Storage::disk('local')->download(
            $export->storage_path,
            $export->file_name,
            ['Content-Type' => $export->mime_type, 'X-AGIS-Checksum-SHA256' => $export->checksum_sha256],
        );
    }

    /** @return array<string, mixed> */
    private function filters(array $input): array
    {
        return validator($input, [
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', 'max:40'],
            'officeId' => ['nullable', 'integer', 'exists:offices,id'],
            'riskCode' => ['nullable', 'string', 'max:80'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date', 'after_or_equal:dateFrom'],
        ])->validate();
    }

    /** @return Collection<int, CmsRecommendationCase> */
    private function visibleCases(User $user): Collection
    {
        return $this->scope->visibleCases(
            CmsRecommendationCase::query()->with([
                'recommendation',
                'leadResponsibleOffice:id,code,name,acronym',
                'currentAssignment.user:id,name,employee_id',
                'actionPlan.acceptedVersion',
                'progressUpdates.recordedVersion',
                'activeValidationReview.finalizedVersion',
                'unresolvedTargetDateExtensionRequest.currentVersion',
                'closureRequests.currentVersion',
                'dispositionRequests.currentVersion',
                'openClosureCandidate',
                'openEscalationCandidate',
            ])->orderBy('id'),
            $user,
            'cms.report.view',
        )->get();
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(string $code, Collection $cases): Collection
    {
        return $cases->map(function (CmsRecommendationCase $case) use ($code): array {
            $recommendation = $case->recommendation;
            $snapshot = $recommendation?->source_snapshot ?? [];
            $target = $case->effective_target_implementation_date;
            $today = CarbonImmutable::today();
            $overdueDays = $target && $target->lt($today)
                && ! in_array($case->status_code, ['CLOSED', 'ACCEPTED_RISK', 'NO_LONGER_APPLICABLE'], true)
                ? (int) $target->diffInDays($today)
                : 0;
            $planVersion = $case->actionPlan?->acceptedVersion;
            $progress = $case->progressUpdates->first(
                fn ($update): bool => $update->recordedVersion !== null,
            );
            $progressVersion = $progress?->recordedVersion;
            $validationVersion = $case->activeValidationReview?->finalizedVersion;
            $extensionVersion = $case->unresolvedTargetDateExtensionRequest?->currentVersion;
            $closureVersion = $case->closureRequests->first()?->currentVersion;
            $dispositionVersion = $case->dispositionRequests->first()?->currentVersion;
            $professionalDecision = match ($case->status_code) {
                'CLOSED' => 'CLOSED',
                'ACCEPTED_RISK' => 'ACCEPTED_RISK',
                'NO_LONGER_APPLICABLE' => 'NO_LONGER_APPLICABLE',
                default => 'NONE',
            };
            $common = [
                '_caseId' => $case->id,
                'caseCode' => sprintf('CMS-REC-%06d', $case->id),
                'recommendationCode' => $recommendation?->recommendation_code,
                'engagementCode' => data_get($snapshot, 'engagement.code'),
                'findingCode' => data_get($snapshot, 'finding.code'),
                'status' => $case->status_code,
                'risk' => $recommendation?->risk_code_snapshot,
                'responsibleOffice' => $case->leadResponsibleOffice?->name
                    ?? data_get($snapshot, 'recommendation.responsibleOffices.0.name'),
                'officeId' => $case->lead_responsible_office_id,
                'targetDate' => $target?->toDateString(),
                'overdueDays' => $overdueDays,
                'sourceReport' => $recommendation?->report_code_snapshot,
                'validationConclusion' => $validationVersion?->final_conclusion_code,
                'activeEscalation' => $case->openEscalationCandidate ? 'YES' : 'NO',
                'professionalDecision' => $professionalDecision,
                'openedAt' => $case->opened_at?->toDateString(),
            ];

            return match ($code) {
                'implementation-progress' => $common + [
                    'actionPlanStatus' => $planVersion?->status_code ?? 'NOT_STARTED',
                    'acceptedTargetDate' => $planVersion?->planned_target_date?->toDateString(),
                    'latestProgressStatus' => $progressVersion?->status_code ?? 'NOT_REPORTED',
                    'reportedProgress' => $progressVersion?->management_reported_overall_percentage,
                    'lastReportingPeriodEnd' => $progress?->reporting_period_end?->toDateString(),
                    'validationStatus' => $validationVersion?->status_code ?? 'NOT_STARTED',
                ],
                'target-date-monitoring' => $common + [
                    'targetDateSource' => $extensionVersion && $extensionVersion->status_code === 'APPROVED'
                        ? 'APPROVED_EXTENSION'
                        : 'ORIGINAL_OR_CURRENT',
                    'extensionStatus' => $extensionVersion?->status_code ?? 'NONE',
                ],
                'closure-readiness' => $common + [
                    'closureCandidate' => $case->openClosureCandidate ? 'OPEN' : 'NONE',
                    'closureRequestStatus' => $closureVersion?->status_code ?? 'NONE',
                    'dispositionStatus' => $dispositionVersion?->status_code ?? 'NONE',
                ],
                default => $common,
            };
        });
    }

    /** @param array<string, mixed> $filters */
    private function matchesFilters(array $row, array $filters): bool
    {
        if (($filters['status'] ?? null) && $row['status'] !== $filters['status']) {
            return false;
        }
        if (($filters['officeId'] ?? null) && (int) $row['officeId'] !== (int) $filters['officeId']) {
            return false;
        }
        if (($filters['riskCode'] ?? null) && strcasecmp((string) $row['risk'], (string) $filters['riskCode']) !== 0) {
            return false;
        }
        if (($filters['dateFrom'] ?? null) && (! $row['openedAt'] || $row['openedAt'] < $filters['dateFrom'])) {
            return false;
        }
        if (($filters['dateTo'] ?? null) && (! $row['openedAt'] || $row['openedAt'] > $filters['dateTo'])) {
            return false;
        }
        if (($filters['search'] ?? null)) {
            $haystack = mb_strtolower(implode(' ', array_map(
                fn ($value): string => is_scalar($value) ? (string) $value : json_encode($value),
                $row,
            )));
            if (! str_contains($haystack, mb_strtolower($filters['search']))) {
                return false;
            }
        }

        return true;
    }

    private function csv(CmsReportRun $run): string
    {
        $columns = collect(data_get($run->result_snapshot, 'columns', []));
        $rows = collect(data_get($run->result_snapshot, 'rows', []));
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $columns->pluck('label')->map(fn ($value): string => $this->csvCell($value))->all());
        foreach ($rows as $row) {
            fputcsv($stream, $columns->pluck('key')->map(fn ($key): string => $this->csvCell($row[$key] ?? null))->all());
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF".$contents;
    }

    private function csvCell(mixed $value): string
    {
        $text = is_scalar($value) || $value === null
            ? (string) ($value ?? '')
            : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return preg_match('/^\s*[=+\-@]/u', $text) === 1 ? "'{$text}" : $text;
    }

    private function pdf(CmsReportRun $run): string
    {
        return Pdf::loadView('reports.cms-report', [
            'run' => $run,
            'columns' => data_get($run->result_snapshot, 'columns', []),
            'rows' => data_get($run->result_snapshot, 'rows', []),
        ])->setPaper('a4', 'landscape')->output();
    }

    /** @return list<int> */
    private function visibleCaseIds(User $user): array
    {
        return $this->visibleCases($user)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    private function runIsVisible(CmsReportRun $run, array $visibleCaseIds): bool
    {
        $caseIds = collect(data_get($run->scope_snapshot, 'caseIds', []))
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $caseIds === [] || count(array_diff($caseIds, $visibleCaseIds)) === 0;
    }

    private function ensureRunVisible(User $user, CmsReportRun $run): void
    {
        throw_unless(
            $this->runIsVisible($run, $this->visibleCaseIds($user)),
            new \Symfony\Component\HttpKernel\Exception\HttpException(404, 'The CMS report is unavailable.'),
        );
    }

    private function authorize(?User $user, string $permission): void
    {
        throw_unless(
            $user?->is_active && ! $user->trashed() && $user->hasPermission($permission),
            new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'You are not authorized to access CMS reports.'),
        );
    }

    /** @param array<string, mixed> $metadata */
    private function record(Request $http, string $action, string $description, object $subject, array $metadata): void
    {
        ActivityRecorder::record($http, $action, $description, metadata: [
            'module' => 'CMS',
            'recordType' => $subject::class,
            'recordId' => $subject->getKey(),
            ...$metadata,
        ]);
        AuditLog::query()->create([
            'user_id' => $http->user()?->id,
            'action' => $action,
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(),
            'new_values' => $metadata,
            'ip_address' => $http->ip(),
            'user_agent' => mb_substr((string) $http->userAgent(), 0, 1000),
            'metadata' => ['module' => 'CMS', ...$metadata],
        ]);
    }
}
