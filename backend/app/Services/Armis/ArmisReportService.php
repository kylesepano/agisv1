<?php

namespace App\Services;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Models\ArmisCompetency;
use App\Models\ArmisEngagementAssignment;
use App\Models\ArmisReportExport;
use App\Models\ArmisReportRun;
use App\Models\ArmisResourceProfile;
use App\Models\AuditEngagement;
use App\Models\User;
use App\Support\ActivityRecorder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Builds scope-aware ARMIS snapshots and private reproducible exports.
 *
 * Report runs contain the complete result snapshot used by later exports. A
 * download never re-queries operational ledgers, so a report remains
 * reproducible even after planning data changes.
 */
class ArmisReportService
{
    public const SOURCE_QUERY_VERSION = 'ARMIS-5A-v1';

    /** @var array<string, array<string, mixed>> */
    public const REPORTS = [
        'resource-utilization' => [
            'title' => 'ARMIS Resource Utilization Report',
            'description' => 'Available capacity, planned workload, approved assignments, actual person-days, and utilization by ARMIS resource.',
            'filters' => ['search', 'status', 'officeId', 'fiscalYear'],
            'columns' => [
                ['key' => 'resourceCode', 'label' => 'Resource Code'],
                ['key' => 'resource', 'label' => 'Resource'],
                ['key' => 'office', 'label' => 'Office'],
                ['key' => 'status', 'label' => 'Profile Status'],
                ['key' => 'fiscalYear', 'label' => 'Fiscal Year'],
                ['key' => 'availablePersonDays', 'label' => 'Available Person-Days'],
                ['key' => 'plannedWorkload', 'label' => 'Planned Workload'],
                ['key' => 'approvedAssignments', 'label' => 'Approved Assignments'],
                ['key' => 'actualPersonDays', 'label' => 'Actual Person-Days'],
                ['key' => 'remainingCapacity', 'label' => 'Remaining Capacity'],
                ['key' => 'utilizationPercent', 'label' => 'Utilization'],
            ],
        ],
        'assignment-register' => [
            'title' => 'ARMIS Assignment Register',
            'description' => 'Current approved and locked ARMIS assignments, required roles, planned days, actuals, and variance.',
            'filters' => ['search', 'status', 'officeId', 'fiscalYear'],
            'columns' => [
                ['key' => 'assignmentCode', 'label' => 'Assignment'],
                ['key' => 'engagement', 'label' => 'Engagement'],
                ['key' => 'resource', 'label' => 'Resource'],
                ['key' => 'office', 'label' => 'Office'],
                ['key' => 'role', 'label' => 'Assignment Role'],
                ['key' => 'assignedFrom', 'label' => 'From'],
                ['key' => 'assignedUntil', 'label' => 'Until'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'plannedPersonDays', 'label' => 'Planned Days'],
                ['key' => 'actualPersonDays', 'label' => 'Actual Days'],
                ['key' => 'variance', 'label' => 'Variance'],
            ],
        ],
        'capacity-workload' => [
            'title' => 'ARMIS Capacity and Workload Report',
            'description' => 'Approved annual capacity compared with approved planned workload and remaining capacity.',
            'filters' => ['search', 'officeId', 'fiscalYear'],
            'columns' => [
                ['key' => 'resourceCode', 'label' => 'Resource Code'],
                ['key' => 'resource', 'label' => 'Resource'],
                ['key' => 'office', 'label' => 'Office'],
                ['key' => 'fiscalYear', 'label' => 'Fiscal Year'],
                ['key' => 'availablePersonDays', 'label' => 'Available Capacity'],
                ['key' => 'plannedWorkload', 'label' => 'Approved Workload'],
                ['key' => 'remainingCapacity', 'label' => 'Remaining Capacity'],
                ['key' => 'utilizationPercent', 'label' => 'Utilization'],
                ['key' => 'capacityStatus', 'label' => 'Capacity Status'],
            ],
        ],
        'competency-coverage' => [
            'title' => 'ARMIS Competency Coverage Report',
            'description' => 'Current verified competency claims, expiries, and assignment competency coverage by resource.',
            'filters' => ['search', 'status', 'officeId'],
            'columns' => [
                ['key' => 'resourceCode', 'label' => 'Resource Code'],
                ['key' => 'resource', 'label' => 'Resource'],
                ['key' => 'office', 'label' => 'Office'],
                ['key' => 'status', 'label' => 'Profile Status'],
                ['key' => 'verifiedCompetencies', 'label' => 'Verified Claims'],
                ['key' => 'expiringCompetencies', 'label' => 'Expiring Within 90 Days'],
                ['key' => 'expiredCompetencies', 'label' => 'Expired Claims'],
                ['key' => 'assignmentRequirements', 'label' => 'Assignment Requirements'],
                ['key' => 'coverageStatus', 'label' => 'Coverage Status'],
            ],
        ],
    ];

    public function __construct(
        private readonly ArmisResourceService $resources,
        private readonly ResourcePlanningGateway $provider,
    ) {}

    /** @return array<string, mixed> */
    public function catalog(User $user): array
    {
        $this->authorize($user, 'armis.report.view');

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
            'scope' => $this->scopeSummary($user),
            'canExport' => $user->hasPermission('armis.report.export'),
            'provider' => $this->provider->status(),
        ];
    }

    /** @return Collection<int, ArmisReportRun> */
    public function runs(User $user, int $limit = 50): Collection
    {
        $this->authorize($user, 'armis.report.view');
        $visibleProfileIds = $this->visibleProfileIds($user);
        $visibleAssignmentIds = $this->visibleAssignmentIds($user);

        return ArmisReportRun::query()
            ->with(['generator:id,name,employee_id', 'exports'])
            ->latest('generated_at')
            ->limit($limit)
            ->get()
            ->filter(fn (ArmisReportRun $run): bool => $this->runIsVisible($run, $visibleProfileIds, $visibleAssignmentIds))
            ->values();
    }

    public function show(User $user, int $runId): ArmisReportRun
    {
        $this->authorize($user, 'armis.report.view');
        $run = ArmisReportRun::query()
            ->with(['generator:id,name,employee_id', 'exports'])
            ->findOrFail($runId);
        $this->ensureRunVisible($user, $run);

        return $run;
    }

    /** @param array<string, mixed> $input */
    public function generate(Request $http, string $code, array $input): ArmisReportRun
    {
        $actor = $this->actor($http);
        $this->authorize($actor, 'armis.report.view');
        $definition = self::REPORTS[$code] ?? null;
        abort_unless($definition, 404, 'The ARMIS report is unavailable.');
        $filters = $this->filters($input, $actor);
        $rows = $this->rows($code, $actor, $filters)
            ->filter(fn (array $row): bool => $this->matchesFilters($row, $filters))
            ->sortBy(fn (array $row): string => (string) ($row['resourceCode'] ?? $row['assignmentCode'] ?? ''))
            ->values();

        $internalRows = $rows->all();
        $profileIds = $rows
            ->pluck('_profileId')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $assignmentIds = $rows
            ->pluck('_assignmentId')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $publicRows = $rows->map(function (array $row): array {
            unset($row['_profileId'], $row['_assignmentId'], $row['_officeId']);

            return $row;
        })->all();
        $snapshot = [
            'meta' => $this->snapshotMeta($code, $filters, $publicRows),
            'columns' => $definition['columns'],
            'rows' => $publicRows,
        ];
        $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $run = DB::transaction(function () use ($actor, $code, $definition, $filters, $profileIds, $assignmentIds, $snapshot, $publicRows, $encoded): ArmisReportRun {
            return ArmisReportRun::query()->create([
                'report_code' => $code,
                'report_title' => $definition['title'],
                'source_query_version' => self::SOURCE_QUERY_VERSION,
                'filters' => $filters,
                'scope_snapshot' => [
                    'profileIds' => $profileIds,
                    'assignmentIds' => $assignmentIds,
                    'visibility' => $this->scopeSummary($actor),
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
            'armis.report.generated',
            "Generated the {$definition['title']} with {$run->row_count} row(s).",
            $run,
            [
                'reportCode' => $code,
                'filters' => $filters,
                'rowCount' => $run->row_count,
                'checksumSha256' => $run->result_checksum_sha256,
            ],
        );

        return $run->load(['generator:id,name,employee_id', 'exports']);
    }

    public function export(Request $http, int $runId, string $format): ArmisReportExport
    {
        $actor = $this->actor($http);
        $this->authorize($actor, 'armis.report.export');
        $format = strtolower($format);
        abort_unless(in_array($format, ['csv', 'pdf'], true), 422, 'The export format is unsupported.');
        $run = $this->show($actor, $runId);
        $latest = ArmisReportExport::query()
            ->where('armis_report_run_id', $run->id)
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
        $path = 'armis/reports/'.$run->id.'/'.$format.'/v'.$version.'-'.$checksum.'.'.$extension;
        Storage::disk('local')->put($path, $contents);

        $export = ArmisReportExport::query()->create([
            'armis_report_run_id' => $run->id,
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
            'armis.report.exported',
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

        return $export->load('run');
    }

    public function download(Request $http, int $exportId): StreamedResponse
    {
        $actor = $this->actor($http);
        $this->authorize($actor, 'armis.report.export');
        $export = ArmisReportExport::query()->with('run')->findOrFail($exportId);
        $this->ensureRunVisible($actor, $export->run);
        abort_unless(Storage::disk('local')->exists($export->storage_path), 404, 'The private ARMIS report export file is unavailable.');

        $this->record(
            $http,
            'armis.report.downloaded',
            "Downloaded ARMIS report export {$export->file_name}.",
            $export,
            ['runId' => $export->armis_report_run_id, 'format' => strtolower($export->format), 'checksumSha256' => $export->checksum_sha256],
        );

        return Storage::disk('local')->download(
            $export->storage_path,
            $export->file_name,
            ['Content-Type' => $export->mime_type, 'X-AGIS-Checksum-SHA256' => $export->checksum_sha256],
        );
    }

    /** @return array<string, mixed> */
    public function administration(User $user): array
    {
        $this->authorize($user, 'armis.report.view');
        $notifications = DB::table('notifications')
            ->where('module_code', 'ARMIS')
            ->where('recipient_id', $user->id)
            ->whereNull('archived_at');

        return [
            'provider' => $this->provider->status(),
            'scope' => $this->scopeSummary($user),
            'permissions' => collect([
                'armis.report.view', 'armis.report.export',
                'armis.resource.view', 'armis.competency.view',
                'armis.capacity.view', 'armis.workload.view',
                'armis.assignment.view', 'armis.actuals.view',
                'armis.provider.view',
            ])->mapWithKeys(fn (string $permission): array => [$permission => $user->hasPermission($permission)]),
            'workflows' => [
                'planning' => ['DRAFT', 'SUBMITTED', 'RETURNED', 'APPROVED', 'LOCKED'],
                'assignments' => ['DRAFT', 'SUBMITTED', 'RETURNED', 'APPROVED', 'LOCKED'],
                'actuals' => ['DRAFT', 'SUBMITTED', 'RETURNED', 'APPROVED', 'LOCKED'],
                'competencies' => ['DRAFT', 'PENDING_VERIFICATION', 'RETURNED', 'VERIFIED', 'EXPIRED', 'REVOKED'],
            ],
            'notifications' => [
                'supportedCategories' => ['SYSTEM', 'ASSIGNMENT'],
                'emittedTypes' => ['ARMIS_ASSIGNMENT', 'ARMIS_PLANNING', 'ARMIS_COMPETENCY'],
                'visibleCount' => (clone $notifications)->count(),
                'unreadCount' => (clone $notifications)->whereNull('read_at')->count(),
            ],
            'hardening' => [
                'immutableReportRuns' => true,
                'immutableExports' => true,
                'scopePinnedSnapshots' => true,
                'privateDownloads' => true,
                'checksumHeaders' => true,
                'csvFormulaMitigation' => true,
                'providerAuthority' => 'ARMIS_AUTHORITATIVE',
            ],
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function filters(array $input, User $user): array
    {
        $validated = validator($input, [
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', 'max:40'],
            'officeId' => ['nullable', 'integer', 'exists:offices,id'],
            'fiscalYear' => ['nullable', 'integer', 'min:2000', 'max:2200'],
        ])->validate();
        if (($validated['officeId'] ?? null) !== null
            && ! $user->hasGlobalOfficeAccess()
            && (int) $validated['officeId'] !== (int) $user->office_id) {
            throw new HttpException(404, 'The ARMIS report is unavailable in the requested office scope.');
        }

        return $validated;
    }

    /** @param array<string, mixed> $filters @return Collection<int, array<string, mixed>> */
    private function rows(string $code, User $user, array $filters): Collection
    {
        return match ($code) {
            'resource-utilization' => $this->resourceRows($user, $filters),
            'assignment-register' => $this->assignmentRows($user, $filters),
            'capacity-workload' => $this->capacityRows($user, $filters),
            'competency-coverage' => $this->competencyRows($user, $this->visibleEngagementIds($user)),
            default => collect(),
        };
    }

    /** @return Collection<int, ArmisResourceProfile> */
    private function profileQuery(User $user): Collection
    {
        $query = ArmisResourceProfile::query()->with([
            'user:id,employee_id,name',
            'office:id,code,name',
            'competencies' => fn ($item) => $item
                ->where('is_current_revision', true)
                ->with('competency:id,code,label'),
            'capacitySubmissions' => fn ($item) => $item
                ->where('is_current_revision', true)
                ->whereIn('status', ['APPROVED', 'LOCKED']),
            'workloadAllocations' => fn ($item) => $item
                ->where('is_current_revision', true)
                ->whereIn('status', ['APPROVED', 'LOCKED']),
            'engagementAssignments' => fn ($item) => $item
                ->where('is_current_revision', true)
                ->whereIn('status', ['APPROVED', 'LOCKED'])
                ->with([
                    'engagement:id,engagement_code,title,status',
                    'competencies',
                    'actualPersonDays' => fn ($actual) => $actual
                        ->where('is_current_revision', true)
                        ->whereIn('status', ['APPROVED', 'LOCKED']),
                ]),
        ]);

        return $this->resources->scopeVisible($query, $user)->orderBy('resource_code')->get();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function resourceRows(User $user, array $filters): Collection
    {
        $fiscalYear = $filters['fiscalYear'] ?? null;
        $engagementIds = $this->visibleEngagementIds($user);

        return $this->profileQuery($user)->map(function (ArmisResourceProfile $profile) use ($fiscalYear, $engagementIds): array {
            $capacity = $profile->capacitySubmissions
                ->when($fiscalYear, fn (Collection $records): Collection => $records->where('fiscal_year', (int) $fiscalYear))
                ->sum(fn ($record): float => (float) $record->available_person_days);
            $workload = $profile->workloadAllocations
                ->when($fiscalYear, fn (Collection $records): Collection => $records->where('fiscal_year', (int) $fiscalYear))
                ->sum(fn ($record): float => (float) $record->planned_person_days);
            $assignments = $profile->engagementAssignments
                ->filter(fn (ArmisEngagementAssignment $assignment): bool => $engagementIds === null
                    || in_array((int) $assignment->audit_engagement_id, $engagementIds, true))
                ->when($fiscalYear, fn (Collection $records): Collection => $records->filter(fn (ArmisEngagementAssignment $assignment): bool => $this->dateOverlapsYear($assignment->assigned_from, $assignment->assigned_until, (int) $fiscalYear)));
            $assigned = $assignments->sum(fn ($record): float => (float) $record->planned_person_days);
            $actual = $assignments->flatMap->actualPersonDays
                ->when($fiscalYear, fn (Collection $records): Collection => $records->filter(fn ($record): bool => $this->dateOverlapsYear($record->period_start, $record->period_end, (int) $fiscalYear)))
                ->sum(fn ($record): float => (float) $record->actual_person_days);
            $available = $capacity > 0 ? $capacity : 0.0;
            $used = $workload > 0 ? $workload : $assigned;

            return [
                '_profileId' => $profile->id,
                '_officeId' => $profile->office_id,
                'resourceCode' => $profile->resource_code,
                'resource' => $profile->user?->name ?? $profile->user?->employee_id,
                'office' => $profile->office ? "{$profile->office->code} - {$profile->office->name}" : null,
                'status' => $profile->status,
                'fiscalYear' => $fiscalYear ?? 'ALL',
                'availablePersonDays' => $this->number($available),
                'plannedWorkload' => $this->number($workload),
                'approvedAssignments' => $this->number($assigned),
                'actualPersonDays' => $this->number($actual),
                'remainingCapacity' => $this->number($available - $used),
                'utilizationPercent' => $this->number($available > 0 ? ($used / $available) * 100 : 0).'%',
            ];
        })->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function assignmentRows(User $user, array $filters): Collection
    {
        $query = $this->assignmentQuery($user)
            ->where('is_current_revision', true)
            ->whereIn('status', ['APPROVED', 'LOCKED']);
        $assignments = $query->get();
        $fiscalYear = $filters['fiscalYear'] ?? null;

        return $assignments
            ->filter(fn (ArmisEngagementAssignment $assignment): bool => ! $fiscalYear
                || $this->dateOverlapsYear($assignment->assigned_from, $assignment->assigned_until, (int) $fiscalYear))
            ->map(function (ArmisEngagementAssignment $assignment): array {
                $actual = $assignment->actualPersonDays->sum(fn ($record): float => (float) $record->actual_person_days);

                return [
                    '_profileId' => $assignment->resource_profile_id,
                    '_assignmentId' => $assignment->id,
                    '_officeId' => $assignment->resourceProfile?->office_id,
                    'assignmentCode' => sprintf('ARMIS-ASG-%06d', $assignment->id),
                    'engagement' => $assignment->engagement
                        ? "{$assignment->engagement->engagement_code} - {$assignment->engagement->title}"
                        : null,
                    'resource' => $assignment->resourceProfile?->user?->name
                        ?? $assignment->resourceProfile?->resource_code,
                    'office' => $assignment->resourceProfile?->office?->code,
                    'role' => str($assignment->assignment_role_code)->replace('_', ' ')->lower()->headline()->toString(),
                    'assignedFrom' => $assignment->assigned_from?->toDateString(),
                    'assignedUntil' => $assignment->assigned_until?->toDateString(),
                    'status' => $assignment->status,
                    'plannedPersonDays' => $this->number((float) $assignment->planned_person_days),
                    'actualPersonDays' => $this->number($actual),
                    'variance' => $this->number($actual - (float) $assignment->planned_person_days),
                ];
            })->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function capacityRows(User $user, array $filters): Collection
    {
        $fiscalYear = (int) ($filters['fiscalYear'] ?? now()->year);

        return $this->profileQuery($user)->map(function (ArmisResourceProfile $profile) use ($fiscalYear): array {
            $capacity = (float) $profile->capacitySubmissions
                ->where('fiscal_year', $fiscalYear)
                ->sum(fn ($record): float => (float) $record->available_person_days);
            $workload = (float) $profile->workloadAllocations
                ->where('fiscal_year', $fiscalYear)
                ->sum(fn ($record): float => (float) $record->planned_person_days);
            $remaining = $capacity - $workload;

            return [
                '_profileId' => $profile->id,
                '_officeId' => $profile->office_id,
                'resourceCode' => $profile->resource_code,
                'resource' => $profile->user?->name ?? $profile->user?->employee_id,
                'office' => $profile->office?->code,
                'fiscalYear' => $fiscalYear,
                'availablePersonDays' => $this->number($capacity),
                'plannedWorkload' => $this->number($workload),
                'remainingCapacity' => $this->number($remaining),
                'utilizationPercent' => $this->number($capacity > 0 ? ($workload / $capacity) * 100 : 0).'%',
                'capacityStatus' => $capacity <= 0
                    ? 'NO_APPROVED_CAPACITY'
                    : ($remaining < 0 ? 'OVER_CAPACITY' : 'WITHIN_CAPACITY'),
            ];
        })->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function competencyRows(User $user, ?array $engagementIds): Collection
    {
        $today = CarbonImmutable::today();
        $expiryThreshold = $today->addDays(90);

        return $this->profileQuery($user)->map(function (ArmisResourceProfile $profile) use ($today, $expiryThreshold): array {
            $claims = $profile->competencies->filter(fn (ArmisCompetency $claim): bool => $claim->status === 'VERIFIED');
            $expiring = $claims->filter(fn (ArmisCompetency $claim): bool => $claim->expires_at
                && $claim->expires_at->betweenIncluded($today, $expiryThreshold))->count();
            $expired = $profile->competencies->filter(fn (ArmisCompetency $claim): bool => $claim->status === 'EXPIRED'
                || ($claim->status === 'VERIFIED' && $claim->expires_at?->lt($today)))->count();
            $requirements = $profile->engagementAssignments
                ->filter(fn (ArmisEngagementAssignment $assignment): bool => $assignment->is_current_revision
                    && ($engagementIds === null || in_array((int) $assignment->audit_engagement_id, $engagementIds, true)))
                ->sum(fn (ArmisEngagementAssignment $assignment): int => $assignment->competencies->count());

            return [
                '_profileId' => $profile->id,
                '_officeId' => $profile->office_id,
                'resourceCode' => $profile->resource_code,
                'resource' => $profile->user?->name ?? $profile->user?->employee_id,
                'office' => $profile->office?->code,
                'status' => $profile->status,
                'verifiedCompetencies' => $claims->count(),
                'expiringCompetencies' => $expiring,
                'expiredCompetencies' => $expired,
                'assignmentRequirements' => $requirements,
                'coverageStatus' => $expired > 0 ? 'REVIEW_REQUIRED' : 'CURRENT',
            ];
        })->values();
    }

    /** @return Builder<ArmisEngagementAssignment> */
    private function assignmentQuery(User $user): Builder
    {
        return ArmisEngagementAssignment::query()
            ->with([
                'engagement:id,engagement_code,title,status',
                'resourceProfile.user:id,employee_id,name',
                'resourceProfile.office:id,code,name',
                'actualPersonDays' => fn ($actual) => $actual
                    ->where('is_current_revision', true)
                    ->whereIn('status', ['APPROVED', 'LOCKED']),
            ])
            ->whereHas('resourceProfile', fn (Builder $profile): Builder => $profile
                ->when(! $user->hasGlobalOfficeAccess(), fn (Builder $scoped): Builder => $scoped->where('office_id', $user->office_id)))
            ->whereHas('engagement', fn (Builder $engagement): Builder => $this->scopeEngagements($engagement, $user));
    }

    private function scopeEngagements(Builder $query, User $user): Builder
    {
        if ($user->hasGlobalEngagementAccess()) {
            return $query;
        }
        if (! $user->hasPermission('aems.engagement.view')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('teamMembers', fn (Builder $team): Builder => $team
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNull('ended_at'));
    }

    /** @return list<int>|null */
    private function visibleEngagementIds(User $user): ?array
    {
        if ($user->hasGlobalEngagementAccess()) {
            return null;
        }
        if (! $user->hasPermission('aems.engagement.view')) {
            return [];
        }

        return AuditEngagement::query()
            ->scopeVisibleTo($user)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $filters */
    private function matchesFilters(array $row, array $filters): bool
    {
        if (($filters['status'] ?? null) && ($row['status'] ?? null) !== $filters['status']) {
            return false;
        }
        if (($filters['officeId'] ?? null) && (int) ($row['_officeId'] ?? 0) !== (int) $filters['officeId']) {
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

    /** @param array<string, mixed> $filters @param list<array<string, mixed>> $rows */
    private function snapshotMeta(string $code, array $filters, array $rows): array
    {
        return [
            ['label' => 'Report', 'value' => self::REPORTS[$code]['title']],
            ['label' => 'Rows', 'value' => count($rows)],
            ['label' => 'Filters', 'value' => collect($filters)->filter(fn ($value): bool => $value !== null && $value !== '')->map(fn ($value, $key): string => "{$key}: {$value}")->join(', ') ?: 'None'],
            ['label' => 'Source Query', 'value' => self::SOURCE_QUERY_VERSION],
        ];
    }

    private function dateOverlapsYear(mixed $start, mixed $end, int $year): bool
    {
        $rangeStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $rangeEnd = CarbonImmutable::create($year, 12, 31)->endOfDay();
        $startDate = $start ? CarbonImmutable::parse($start) : $rangeStart;
        $endDate = $end ? CarbonImmutable::parse($end) : $rangeEnd;

        return $startDate->lte($rangeEnd) && $endDate->gte($rangeStart);
    }

    private function number(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /** @return list<int> */
    private function visibleProfileIds(User $user): array
    {
        return $this->resources->scopeVisible(ArmisResourceProfile::query(), $user)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function runIsVisible(ArmisReportRun $run, array $visibleProfileIds, ?array $visibleAssignmentIds = null): bool
    {
        $profileIds = collect(data_get($run->scope_snapshot, 'profileIds', []))
            ->map(fn ($id): int => (int) $id)
            ->all();
        $assignmentIds = collect(data_get($run->scope_snapshot, 'assignmentIds', []))
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($assignmentIds !== []) {
            $visibleAssignmentIds ??= [];
            if (count(array_diff($assignmentIds, $visibleAssignmentIds)) !== 0) {
                return false;
            }
        }

        return $profileIds === [] || count(array_diff($profileIds, $visibleProfileIds)) === 0;
    }

    private function ensureRunVisible(User $user, ArmisReportRun $run): void
    {
        throw_unless(
            $this->runIsVisible($run, $this->visibleProfileIds($user), $this->visibleAssignmentIds($user)),
            new HttpException(404, 'The ARMIS report is unavailable in your scope.'),
        );
    }

    /** @return list<int> */
    private function visibleAssignmentIds(User $user): array
    {
        return $this->assignmentQuery($user)
            ->pluck('armis_engagement_assignments.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function authorize(?User $user, string $permission): void
    {
        throw_unless(
            $user?->is_active && ! $user->trashed() && $user->hasPermission($permission),
            new HttpException(403, 'You are not authorized to access ARMIS reports.'),
        );
    }

    /** @return array<string, mixed> */
    private function scopeSummary(User $user): array
    {
        $query = $this->resources->scopeVisible(ArmisResourceProfile::query(), $user);

        return [
            'officeScope' => $user->hasGlobalOfficeAccess() ? 'ALL' : 'OWN_OFFICE',
            'officeId' => $user->office_id,
            'profileCount' => (clone $query)->count(),
            'engagementScope' => $user->hasGlobalEngagementAccess() ? 'ALL' : 'ASSIGNED',
        ];
    }

    private function csv(ArmisReportRun $run): string
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

    private function pdf(ArmisReportRun $run): string
    {
        return Pdf::loadView('reports.armis-report', [
            'run' => $run,
            'meta' => data_get($run->result_snapshot, 'meta', []),
            'columns' => data_get($run->result_snapshot, 'columns', []),
            'rows' => data_get($run->result_snapshot, 'rows', []),
        ])->setPaper('a4', 'landscape')->output();
    }

    /** @param array<string, mixed> $metadata */
    private function record(Request $http, string $action, string $description, object $subject, array $metadata): void
    {
        ActivityRecorder::record($http, $action, $description, metadata: [
            'module' => 'ARMIS',
            'recordType' => $subject::class,
            'recordId' => $subject->getKey(),
            ...$metadata,
        ]);
        \App\Models\AuditLog::query()->create([
            'user_id' => $http->user()?->id,
            'action' => $action,
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(),
            'new_values' => $metadata,
            'ip_address' => $http->ip(),
            'user_agent' => mb_substr((string) $http->userAgent(), 0, 1000),
            'metadata' => ['module' => 'ARMIS', ...$metadata],
        ]);
    }
}
