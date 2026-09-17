<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AuditArea;
use App\Models\AuditFocus;
use App\Models\AuditLog;
use App\Models\DocumentVersion;
use App\Models\MasterListItem;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\ActivityRecorder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Searches and exports operational activity and immutable audit-trail records.
 */
class LogController extends Controller
{
    private const MODULES = ['CORE', 'IAP', 'AEM', 'AFR', 'CMS', 'ARMIS', 'AIS'];

    public function activities(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $query = $this->activityQuery($filters);
        $summaryQuery = clone $query;
        $page = $query
            ->with(['user:id,name,initials,employee_id', 'subjectUser:id,name,employee_id'])
            ->latest()
            ->paginate($filters['perPage']);

        return $this->success([
            'activityLogs' => collect($page->items())
                ->map(fn (ActivityLog $log): array => $this->activityData($log))
                ->values(),
            'pagination' => $this->pagination($page),
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'today' => (clone $summaryQuery)->whereDate('created_at', today())->count(),
                'actors' => (clone $summaryQuery)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
                'security' => (clone $summaryQuery)
                    ->where(fn (Builder $query) => $query
                        ->where('action', 'like', 'auth.%')
                        ->orWhere('action', 'like', 'password.%')
                        ->orWhere('action', 'like', 'user.password%'))
                    ->count(),
            ],
            'options' => $this->options(ActivityLog::query()),
        ]);
    }

    public function audits(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $query = $this->auditQuery($filters);
        $summaryQuery = clone $query;
        $page = $query
            ->with(['user:id,name,initials,employee_id'])
            ->latest()
            ->paginate($filters['perPage']);

        $valueLabels = $this->auditValueLabels(collect($page->items()));

        return $this->success([
            'auditLogs' => collect($page->items())
                ->map(fn (AuditLog $log): array => $this->auditData($log, $valueLabels))
                ->values(),
            'pagination' => $this->pagination($page),
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'today' => (clone $summaryQuery)->whereDate('created_at', today())->count(),
                'actors' => (clone $summaryQuery)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
                'changedRecords' => (clone $summaryQuery)
                    ->whereNotNull('auditable_type')
                    ->get(['auditable_type', 'auditable_id'])
                    ->unique(fn (AuditLog $log): string => $log->auditable_type.':'.$log->auditable_id)
                    ->count(),
            ],
            'options' => $this->options(AuditLog::query(), true),
        ]);
    }

    public function exportActivities(Request $request): Response|StreamedResponse
    {
        abort_unless($request->user()->hasPermission('activity_logs.export'), 403);
        $filters = $this->filters($request, false);
        $rows = $this->activityQuery($filters)
            ->with(['user:id,name,employee_id', 'subjectUser:id,name,employee_id'])
            ->latest()
            ->limit(10000)
            ->get()
            ->map(fn (ActivityLog $log): array => [
                'Date and Time' => $log->created_at?->format('Y-m-d H:i:s'),
                'Actor' => $log->user?->name ?? 'System',
                'Employee ID' => $log->user?->employee_id,
                'Module' => $this->moduleFor($log->action, $log->metadata),
                'Action' => $log->action,
                'Description' => $log->description,
                'Subject User' => $log->subjectUser?->name,
                'IP Address' => $log->ip_address,
            ]);

        ActivityRecorder::record(
            $request,
            'activity_log.exported',
            "Exported {$rows->count()} Activity Log records as {$filters['format']}.",
            metadata: ['filters' => $filters, 'recordCount' => $rows->count()],
        );

        return $this->export('Activity Log', $rows, $filters['format']);
    }

    public function exportAudits(Request $request): Response|StreamedResponse
    {
        abort_unless($request->user()->hasPermission('audit_logs.export'), 403);
        $filters = $this->filters($request, false);
        $logs = $this->auditQuery($filters)
            ->with('user:id,name,employee_id')
            ->latest()
            ->limit(10000)
            ->get();
        $valueLabels = $this->auditValueLabels($logs);
        $rows = $logs->map(fn (AuditLog $log): array => [
                'Date and Time' => $log->created_at?->format('Y-m-d H:i:s'),
                'Actor' => $log->user?->name ?? 'System',
                'Employee ID' => $log->user?->employee_id,
                'Module' => $this->moduleFor($log->action, $log->metadata),
                'Action' => $log->action,
                'Record Type' => $this->recordType($log->auditable_type),
                'Record' => $this->recordLabel($log),
                'Old Values' => $this->json($this->displayValues($log->old_values, $valueLabels)),
                'New Values' => $this->json($this->displayValues($log->new_values, $valueLabels)),
                'IP Address' => $log->ip_address,
            ]);

        ActivityRecorder::record(
            $request,
            'audit_trail.exported',
            "Exported {$rows->count()} Audit Trail records as {$filters['format']}.",
            metadata: ['filters' => $filters, 'recordCount' => $rows->count()],
        );

        return $this->export('Audit Trail', $rows, $filters['format']);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request, bool $paginate = true): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'action' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'in:'.implode(',', self::MODULES)],
            'userId' => ['nullable', 'integer', 'exists:users,id'],
            'recordType' => ['nullable', 'string', 'max:255'],
            'recordId' => ['nullable', 'integer'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date', 'after_or_equal:dateFrom'],
            'page' => $paginate ? ['nullable', 'integer', 'min:1'] : ['nullable'],
            'perPage' => $paginate ? ['nullable', 'integer', 'min:5', 'max:100'] : ['nullable'],
            'format' => $paginate ? ['nullable'] : ['required', 'in:pdf,excel,csv,print'],
        ]) + ['perPage' => app(\App\Services\RuntimeConfiguration::class)->paginationSize()];
    }

    private function activityQuery(array $filters): Builder
    {
        return ActivityLog::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where(function (Builder $query) use ($search): void {
                    $like = '%'.mb_strtolower($search).'%';
                    $query
                        ->whereRaw('LOWER(action) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(ip_address, \'\')) LIKE ?', [$like])
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->whereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(employee_id) LIKE ?', [$like]))
                        ->orWhereHas('subjectUser', fn (Builder $user) => $user
                            ->whereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(employee_id) LIKE ?', [$like]));
                }))
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['userId'] ?? null, fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['dateFrom'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['dateTo'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['module'] ?? null, fn (Builder $query, string $module) => $this->scopeModule($query, $module));
    }

    private function auditQuery(array $filters): Builder
    {
        return AuditLog::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where(function (Builder $query) use ($search): void {
                    $like = '%'.mb_strtolower($search).'%';
                    $query
                        ->whereRaw('LOWER(action) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(auditable_type, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('CAST(auditable_id AS TEXT) LIKE ?', ['%'.$search.'%'])
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->whereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(employee_id) LIKE ?', [$like]));
                }))
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['userId'] ?? null, fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['recordType'] ?? null, fn (Builder $query, string $type) => $query->where('auditable_type', $type))
            ->when($filters['recordId'] ?? null, fn (Builder $query, int $id) => $query->where('auditable_id', $id))
            ->when($filters['dateFrom'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['dateTo'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['module'] ?? null, fn (Builder $query, string $module) => $this->scopeModule($query, $module));
    }

    private function scopeModule(Builder $query, string $module): void
    {
        if ($module === 'CORE') {
            $query->where(function (Builder $query): void {
                foreach (['iap.', 'siap.', 'aem.', 'afr.', 'cms.', 'armis.', 'ais.'] as $prefix) {
                    $query->where('action', 'not like', $prefix.'%');
                }
            });

            return;
        }

        $query->where(function (Builder $query) use ($module): void {
            $query->where('action', 'like', strtolower($module).'.%');
            if ($module === 'IAP') {
                $query->orWhere('action', 'like', 'siap.%');
            }
        });
    }

    private function activityData(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'module' => $this->moduleFor($log->action, $log->metadata),
            'description' => $log->description,
            'actor' => $log->user?->name ?? 'System',
            'actorId' => $log->user_id,
            'actorEmployeeId' => $log->user?->employee_id,
            'actorInitials' => $log->user?->initials ?? 'SY',
            'subject' => $log->subjectUser?->name,
            'subjectEmployeeId' => $log->subjectUser?->employee_id,
            'oldValues' => $log->old_values,
            'newValues' => $log->new_values,
            'metadata' => $log->metadata,
            'ipAddress' => $log->ip_address,
            'userAgent' => $log->user_agent,
            'createdAt' => $log->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $valueLabels
     */
    private function auditData(AuditLog $log, array $valueLabels = []): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'module' => $this->moduleFor($log->action, $log->metadata),
            'actor' => $log->user?->name ?? 'System',
            'actorId' => $log->user_id,
            'actorEmployeeId' => $log->user?->employee_id,
            'actorInitials' => $log->user?->initials ?? 'SY',
            'recordType' => $this->recordType($log->auditable_type),
            'recordTypeValue' => $log->auditable_type,
            'recordId' => $log->auditable_id,
            'recordLabel' => $this->recordLabel($log),
            'oldValues' => $log->old_values,
            'newValues' => $log->new_values,
            // Raw values remain available for machine-level audit integrity;
            // the display values are resolved labels for human review.
            'displayOldValues' => $this->displayValues($log->old_values, $valueLabels),
            'displayNewValues' => $this->displayValues($log->new_values, $valueLabels),
            'metadata' => $log->metadata,
            'displayMetadata' => $this->displayValues($log->metadata, $valueLabels),
            'ipAddress' => $log->ip_address,
            'userAgent' => $log->user_agent,
            'createdAt' => $log->created_at?->toIso8601String(),
        ];
    }

    private function recordLabel(AuditLog $log): string
    {
        foreach ([$log->new_values, $log->old_values] as $values) {
            foreach (['name', 'title', 'planCode', 'plan_code', 'code', 'employeeId', 'employee_id'] as $key) {
                if (! empty($values[$key])) {
                    return (string) $values[$key];
                }
            }
        }

        return $this->recordType($log->auditable_type).' (unnamed record)';
    }

    private function recordType(?string $type): string
    {
        return $type ? Str::headline(class_basename($type)) : 'System record';
    }

    /**
     * Resolve foreign-key values in the current page in bulk. This keeps the
     * persisted audit payload immutable while avoiding one query per changed
     * field or log row.
     *
     * @param  Collection<int, AuditLog>  $logs
     * @return array<string, array<int, string>>
     */
    private function auditValueLabels(Collection $logs): array
    {
        $ids = [
            'offices' => collect(),
            'areas' => collect(),
            'focuses' => collect(),
            'users' => collect(),
            'masterLists' => collect(),
            'roles' => collect(),
            'permissions' => collect(),
            'documents' => collect(),
        ];

        $logs->each(function (AuditLog $log) use (&$ids): void {
            foreach ([$log->old_values, $log->new_values, $log->metadata] as $values) {
                $this->collectAuditValueIds($values, $ids);
            }
        });

        return [
            'offices' => Office::withTrashed()->whereIn('id', $ids['offices']->unique())->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Office $office): array => [$office->id => $this->codedLabel($office->code, $office->name)])->all(),
            'areas' => AuditArea::withTrashed()->whereIn('id', $ids['areas']->unique())->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (AuditArea $area): array => [$area->id => $this->codedLabel($area->code, $area->name)])->all(),
            'focuses' => AuditFocus::withTrashed()->whereIn('id', $ids['focuses']->unique())->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (AuditFocus $focus): array => [$focus->id => $this->codedLabel($focus->code, $focus->name)])->all(),
            'users' => User::withTrashed()->whereIn('id', $ids['users']->unique())->get(['id', 'name', 'employee_id'])
                ->mapWithKeys(fn (User $user): array => [$user->id => $user->employee_id ? $user->name.' ('.$user->employee_id.')' : $user->name])->all(),
            'masterLists' => MasterListItem::withTrashed()->whereIn('id', $ids['masterLists']->unique())->get(['id', 'code', 'label'])
                ->mapWithKeys(fn (MasterListItem $item): array => [$item->id => $this->codedLabel($item->code, $item->label)])->all(),
            'roles' => Role::withTrashed()->whereIn('id', $ids['roles']->unique())->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Role $role): array => [$role->id => $this->codedLabel($role->code, $role->name)])->all(),
            'permissions' => Permission::whereIn('id', $ids['permissions']->unique())->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Permission $permission): array => [$permission->id => $this->codedLabel($permission->code, $permission->name)])->all(),
            'documents' => DocumentVersion::with(['document:id,title'])->whereIn('id', $ids['documents']->unique())->get(['id', 'document_id', 'version_number', 'version_label', 'original_file_name'])
                ->mapWithKeys(fn (DocumentVersion $version): array => [
                    $version->id => ($version->document?->title ?: $version->original_file_name ?: 'Document').' · '.($version->version_label ?: 'Version '.$version->version_number),
                ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @param  array<string, Collection<int, int>>  $ids
     */
    private function collectAuditValueIds(?array $values, array &$ids): void
    {
        if (! is_array($values)) {
            return;
        }

        foreach ($values as $key => $value) {
            $kind = $this->auditValueKind((string) $key);
            if ($kind && $kind !== 'unknown') {
                foreach (is_array($value) ? $value : [$value] as $id) {
                    if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                        $ids[$kind]->push((int) $id);
                    }
                }
            }

            if (is_array($value) && ! $kind) {
                $this->collectAuditValueIds($value, $ids);
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @param  array<string, array<int, string>>  $labels
     * @return array<string, mixed>|null
     */
    private function displayValues(?array $values, array $labels): ?array
    {
        if (! is_array($values)) {
            return $values;
        }

        $display = [];
        foreach ($values as $key => $value) {
            $kind = $this->auditValueKind((string) $key);
            if ($kind && $kind !== 'unknown') {
                $resolved = collect(is_array($value) ? $value : [$value])
                    ->map(fn ($id) => $labels[$kind][(int) $id] ?? $id)
                    ->values();
                $display[$key] = is_array($value) ? $resolved->all() : $resolved->first();
            } elseif (is_array($value)) {
                $display[$key] = $this->displayValues($value, $labels);
            } else {
                $display[$key] = $value;
            }
        }

        return $display;
    }

    private function auditValueKind(string $key): ?string
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));

        return match (true) {
            preg_match('/(?:^|)officeids?$/', $normalized) === 1 => 'offices',
            preg_match('/(?:^|)(?:audit)?areaids?$/', $normalized) === 1 => 'areas',
            preg_match('/(?:^|)(?:audit)?focusids?$/', $normalized) === 1 => 'focuses',
            $normalized === 'userid' || str_ends_with($normalized, 'userid') || str_ends_with($normalized, 'userids') || str_ends_with($normalized, 'byid') => 'users',
            in_array($normalized, ['audittypeid', 'engagementtypeid', 'officetypeid', 'auditareatypeid'], true) => 'masterLists',
            $normalized === 'roleid' || str_ends_with($normalized, 'roleid') => 'roles',
            $normalized === 'permissionid' || str_ends_with($normalized, 'permissionid') => 'permissions',
            $normalized === 'documentversionid' || str_ends_with($normalized, 'documentversionids') => 'documents',
            default => null,
        };
    }

    private function codedLabel(?string $code, ?string $label): string
    {
        return $code && $label ? $code.' — '.$label : ($label ?: ($code ?: 'Unknown'));
    }

    private function moduleFor(string $action, ?array $metadata = null): string
    {
        if (! empty($metadata['module']) && in_array(strtoupper($metadata['module']), self::MODULES, true)) {
            return strtoupper($metadata['module']);
        }
        $prefix = strtolower(Str::before($action, '.'));

        return match ($prefix) {
            'iap', 'siap' => 'IAP',
            'aem' => 'AEM',
            'afr' => 'AFR',
            'cms' => 'CMS',
            'armis' => 'ARMIS',
            'ais' => 'AIS',
            default => 'CORE',
        };
    }

    private function options(Builder $query, bool $audit = false): array
    {
        $types = $audit
            ? (clone $query)->whereNotNull('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type')
                ->map(fn (string $value): array => ['value' => $value, 'label' => $this->recordType($value)])->values()
            : [];

        return [
            'modules' => self::MODULES,
            'actions' => (clone $query)->distinct()->orderBy('action')->pluck('action'),
            'users' => User::withTrashed()->whereIn('id', (clone $query)->whereNotNull('user_id')->select('user_id'))
                ->orderBy('name')->get(['id', 'name', 'employee_id'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'employeeId' => $user->employee_id,
                ])->values(),
            'recordTypes' => $types,
        ];
    }

    private function export(string $title, Collection $rows, string $format): Response|StreamedResponse
    {
        $file = Str::slug($title).'-'.now()->format('Ymd-His');
        $report = [
            'title' => $title,
            'generatedAt' => now()->format('F j, Y g:i A'),
            'columns' => $rows->first() ? array_keys($rows->first()) : [],
            'rows' => $rows->values()->all(),
        ];

        return match ($format) {
            'pdf' => Pdf::loadView('reports.logs', compact('report'))->setPaper('a4', 'landscape')->download($file.'.pdf'),
            'excel' => response("\xEF\xBB\xBF".view('reports.logs', compact('report'))->render(), 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$file.'.xls"',
            ]),
            'print' => response()->view('reports.logs', compact('report')),
            default => response()->streamDownload(function () use ($report): void {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, $report['columns']);
                foreach ($report['rows'] as $row) {
                    fputcsv($handle, array_values($row));
                }
                fclose($handle);
            }, $file.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']),
        };
    }

    private function json(?array $values): string
    {
        return $values ? json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
    }

    private function pagination($page): array
    {
        return [
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'perPage' => $page->perPage(),
            'total' => $page->total(),
            'from' => $page->firstItem(),
            'to' => $page->lastItem(),
        ];
    }

    private function success(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
