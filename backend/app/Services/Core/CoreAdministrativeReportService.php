<?php

namespace App\Services\Core;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\CoreReportExport;
use App\Models\CoreReportRun;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\Office;
use App\Support\ActivityRecorder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Generates immutable, scope-aware Core administrative report snapshots. */
class CoreAdministrativeReportService
{
    public const SOURCE_QUERY_VERSION = 'CORE-1-v1';

    public const REPORTS = [
        'office-directory' => [
            'title' => 'Office Directory',
            'description' => 'Active offices and their current user counts within your authorized scope.',
            'columns' => [
                ['key' => 'code', 'label' => 'Office Code'], ['key' => 'name', 'label' => 'Office'],
                ['key' => 'type', 'label' => 'Type'], ['key' => 'userCount', 'label' => 'Active Users'],
            ],
        ],
        'user-access' => [
            'title' => 'User Access Register',
            'description' => 'Active users, offices, roles, and access scopes visible to the authenticated administrator.',
            'columns' => [
                ['key' => 'employeeId', 'label' => 'Employee ID'], ['key' => 'name', 'label' => 'Name'],
                ['key' => 'office', 'label' => 'Office'], ['key' => 'roles', 'label' => 'Roles'],
                ['key' => 'officeScope', 'label' => 'Office Scope'], ['key' => 'engagementScope', 'label' => 'Engagement Scope'],
            ],
        ],
        'workflow-register' => [
            'title' => 'Workflow Register',
            'description' => 'Published and active workflow definitions configured for AGIS modules.',
            'columns' => [
                ['key' => 'code', 'label' => 'Workflow Code'], ['key' => 'name', 'label' => 'Name'],
                ['key' => 'module', 'label' => 'Module'], ['key' => 'version', 'label' => 'Version'], ['key' => 'status', 'label' => 'Status'],
            ],
        ],
        'activity-summary' => [
            'title' => 'Activity Summary',
            'description' => 'Activity counts grouped by action for the selected reporting period.',
            'columns' => [
                ['key' => 'action', 'label' => 'Action'], ['key' => 'count', 'label' => 'Occurrences'], ['key' => 'lastOccurredAt', 'label' => 'Last Occurrence'],
            ],
        ],
    ];

    public function catalog(User $user): array
    {
        $this->authorize($user, 'administrative_reports.view');

        return [
            'reports' => collect(self::REPORTS)->map(fn (array $definition, string $code): array => [
                'code' => $code, 'title' => $definition['title'], 'description' => $definition['description'], 'columns' => $definition['columns'],
            ])->values(),
            'formats' => [['code' => 'csv', 'label' => 'CSV', 'mimeType' => 'text/csv'], ['code' => 'pdf', 'label' => 'PDF', 'mimeType' => 'application/pdf']],
            'scope' => $this->scopeSummary($user),
            'canExport' => $user->hasPermission('administrative_reports.export'),
        ];
    }

    public function runs(User $user, int $limit = 50)
    {
        $this->authorize($user, 'administrative_reports.view');
        return CoreReportRun::query()->with(['generator:id,name,employee_id', 'exports'])->latest('generated_at')->limit($limit)->get()
            ->filter(fn (CoreReportRun $run): bool => $this->runVisible($run, $user))->values();
    }

    public function show(User $user, int $runId): CoreReportRun
    {
        $this->authorize($user, 'administrative_reports.view');
        $run = CoreReportRun::query()->with(['generator:id,name,employee_id', 'exports'])->findOrFail($runId);
        abort_unless($this->runVisible($run, $user), 404, 'The Core report is unavailable.');
        return $run;
    }

    public function generate(Request $request, string $code, array $input): CoreReportRun
    {
        $actor = $request->user();
        $this->authorize($actor, 'administrative_reports.view');
        abort_unless(isset(self::REPORTS[$code]), 404, 'The administrative report is unavailable.');
        $filters = validator($input, ['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']])->validate();
        $definition = self::REPORTS[$code];
        $rows = $this->rows($code, $actor, $filters);
        $snapshot = ['meta' => ['reportCode' => $code, 'filters' => $filters, 'scope' => $this->scopeSummary($actor)], 'columns' => $definition['columns'], 'rows' => $rows];
        $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $run = DB::transaction(fn (): CoreReportRun => CoreReportRun::query()->create([
            'report_code' => $code, 'report_title' => $definition['title'], 'source_query_version' => self::SOURCE_QUERY_VERSION,
            'filters' => $filters, 'scope_snapshot' => $this->scopeSummary($actor), 'result_snapshot' => $snapshot,
            'row_count' => count($rows), 'result_checksum_sha256' => hash('sha256', $encoded), 'generated_by' => $actor->id, 'generated_at' => now(),
        ]));
        $this->record($request, 'core.report.generated', "Generated {$definition['title']} with {$run->row_count} row(s).", $run, ['reportCode' => $code, 'checksumSha256' => $run->result_checksum_sha256]);
        return $run->load(['generator:id,name,employee_id', 'exports']);
    }

    public function export(Request $request, int $runId, string $format): CoreReportExport
    {
        $actor = $request->user();
        $this->authorize($actor, 'administrative_reports.export');
        $format = strtolower($format);
        abort_unless(in_array($format, ['csv', 'pdf'], true), 422, 'The export format is unsupported.');
        $run = $this->show($actor, $runId);
        $latest = CoreReportExport::query()->where('core_report_run_id', $run->id)->where('format', strtoupper($format))->latest('version_number')->first();
        if ($latest && Storage::disk('local')->exists($latest->storage_path)) return $latest;
        $version = ($latest?->version_number ?? 0) + 1;
        [$contents, $mime, $extension] = $format === 'csv' ? [$this->csv($run), 'text/csv; charset=UTF-8', 'csv'] : [$this->pdf($run), 'application/pdf', 'pdf'];
        $checksum = hash('sha256', $contents);
        $name = Str::slug($run->report_title).'-'.$run->display_code.'-v'.$version.'.'.$extension;
        $path = 'core/reports/'.$run->id.'/'.$format.'/v'.$version.'-'.$checksum.'.'.$extension;
        Storage::disk('local')->put($path, $contents);
        $export = CoreReportExport::query()->create(['core_report_run_id' => $run->id, 'format' => strtoupper($format), 'version_number' => $version, 'file_name' => $name, 'storage_path' => $path, 'mime_type' => $mime, 'file_size' => strlen($contents), 'checksum_sha256' => $checksum, 'generated_by' => $actor->id, 'generated_at' => now()]);
        $this->record($request, 'core.report.exported', "Generated {$format} export {$name}.", $export, ['runId' => $run->id, 'checksumSha256' => $checksum]);
        return $export->load('run');
    }

    public function download(Request $request, int $exportId): StreamedResponse
    {
        $actor = $request->user();
        $this->authorize($actor, 'administrative_reports.export');
        $export = CoreReportExport::query()->with('run')->findOrFail($exportId);
        abort_unless($this->runVisible($export->run, $actor), 404, 'The Core report export is unavailable.');
        abort_unless(Storage::disk('local')->exists($export->storage_path), 404, 'The private Core report export file is unavailable.');
        $this->record($request, 'core.report.downloaded', "Downloaded Core report export {$export->file_name}.", $export, ['runId' => $export->core_report_run_id, 'checksumSha256' => $export->checksum_sha256]);
        return Storage::disk('local')->download($export->storage_path, $export->file_name, ['Content-Type' => $export->mime_type, 'X-AGIS-Checksum-SHA256' => $export->checksum_sha256]);
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $code, User $user, array $filters): array
    {
        return match ($code) {
            'office-directory' => $this->officeRows($user),
            'user-access' => $this->userRows($user),
            'workflow-register' => $this->workflowRows($user),
            'activity-summary' => $this->activityRows($user, $filters),
            default => [],
        };
    }

    private function officeRows(User $user): array
    {
        $query = Office::query()->withCount(['users as user_count' => fn (Builder $users): Builder => $users->where('is_active', true)])->with('officeType:id,label')->where('is_active', true);
        $this->scopeOffice($query, $user);
        return $query->orderBy('code')->get()->map(fn (Office $office): array => ['code' => $office->code, 'name' => $office->name, 'type' => $office->officeType?->label, 'userCount' => (int) $office->user_count])->all();
    }

    private function userRows(User $user): array
    {
        $query = User::query()->with(['office:id,code,name', 'roles:id,code,name,office_access_scope,engagement_access_scope'])->where('is_active', true);
        if (! $user->hasGlobalOfficeAccess()) $query->where('office_id', $user->office_id);
        return $query->orderBy('name')->get()->map(fn (User $record): array => ['employeeId' => $record->employee_id, 'name' => $record->name, 'office' => $record->office?->code, 'roles' => $record->roles->pluck('name')->join('; '), 'officeScope' => $record->roles->pluck('office_access_scope')->unique()->join('; '), 'engagementScope' => $record->roles->pluck('engagement_access_scope')->unique()->join('; ')])->all();
    }

    private function workflowRows(User $user): array
    {
        if (! $user->hasGlobalOfficeAccess()) return [];
        return WorkflowDefinition::query()->where('is_active', true)->whereIn('status', ['PUBLISHED', 'DRAFT'])->orderBy('module_code')->orderBy('code')->get()->map(fn (WorkflowDefinition $workflow): array => ['code' => $workflow->code, 'name' => $workflow->name, 'module' => $workflow->module_code, 'version' => $workflow->version, 'status' => $workflow->status])->all();
    }

    private function activityRows(User $user, array $filters): array
    {
        $query = ActivityLog::query()->select('action', DB::raw('count(*) as aggregate'), DB::raw('max(created_at) as last_occurred_at'));
        if (! $user->hasGlobalOfficeAccess()) $query->where('user_id', $user->id);
        if (($filters['from'] ?? null) !== null) $query->whereDate('created_at', '>=', $filters['from']);
        if (($filters['to'] ?? null) !== null) $query->whereDate('created_at', '<=', $filters['to']);
        return $query->groupBy('action')->orderByDesc('aggregate')->get()->map(fn ($row): array => ['action' => $row->action, 'count' => (int) $row->aggregate, 'lastOccurredAt' => $row->last_occurred_at])->all();
    }

    private function scopeOffice(Builder $query, User $user): Builder
    {
        return $user->hasGlobalOfficeAccess() ? $query : $query->whereKey($user->office_id ?: 0);
    }

    private function scopeSummary(User $user): array
    {
        return ['officeScope' => $user->hasGlobalOfficeAccess() ? 'ALL' : 'OWN_OFFICE', 'officeId' => $user->hasGlobalOfficeAccess() ? null : $user->office_id, 'generatedBy' => $user->id];
    }

    private function runVisible(CoreReportRun $run, User $user): bool
    {
        $scope = $run->scope_snapshot ?? [];
        return $user->hasGlobalOfficeAccess() || (int) ($scope['officeId'] ?? 0) === (int) $user->office_id;
    }

    private function authorize(User $user, string $permission): void
    {
        abort_unless($user && $user->hasPermission($permission), 403, 'You do not have permission to access Core administrative reports.');
    }

    private function csv(CoreReportRun $run): string
    {
        $handle = fopen('php://temp', 'w+');
        $columns = $run->result_snapshot['columns'] ?? [];
        fputcsv($handle, array_column($columns, 'label'));
        foreach ($run->result_snapshot['rows'] ?? [] as $row) fputcsv($handle, array_map(fn ($column): string => $this->safeCell($row[$column['key']] ?? ''), $columns));
        rewind($handle); $contents = stream_get_contents($handle); fclose($handle);
        return "\xEF\xBB\xBF".$contents;
    }

    private function safeCell(mixed $value): string
    {
        $value = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        return preg_match('/^\s*[=+\-@]/', $value) ? "'".$value : $value;
    }

    private function pdf(CoreReportRun $run): string
    {
        $columns = $run->result_snapshot['columns'] ?? [];
        $rows = $run->result_snapshot['rows'] ?? [];
        $head = collect($columns)->map(fn (array $column): string => '<th>'.e($column['label']).'</th>')->implode('');
        $body = collect($rows)->map(fn (array $row): string => '<tr>'.collect($columns)->map(fn (array $column): string => '<td>'.e((string) ($row[$column['key']] ?? '')).'</td>')->implode('').'</tr>')->implode('');
        return Pdf::loadHTML('<html><body><h1>'.e($run->report_title).'</h1><p>'.$run->display_code.' | Checksum: '.e($run->result_checksum_sha256).'</p><table border="1" cellpadding="4"><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table></body></html>')->output();
    }

    /** @param array<string, mixed> $metadata */
    private function record(Request $request, string $action, string $description, object $subject, array $metadata): void
    {
        ActivityRecorder::record($request, $action, $description, metadata: [
            'module' => 'CORE', 'recordType' => $subject::class, 'recordId' => $subject->getKey(), ...$metadata,
        ]);
        AuditLog::query()->create([
            'user_id' => $request->user()?->id, 'action' => $action, 'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(), 'new_values' => $metadata, 'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000), 'metadata' => ['module' => 'CORE', ...$metadata],
        ]);
    }
}
