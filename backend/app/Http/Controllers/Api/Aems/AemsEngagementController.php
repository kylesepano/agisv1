<?php

namespace App\Http\Controllers\Api\Aems;

use App\Http\Controllers\Controller;
use App\Http\Requests\Aems\AemsEngagementRequest;
use App\Http\Requests\Aems\AemsIapImportRequest;
use App\Http\Requests\Aems\AemsScopeRequest;
use App\Http\Resources\AemsEngagementResource;
use App\Models\AuditEngagement;
use App\Services\AemsEngagementRegistryService;
use App\Services\AemsSupport;
use App\Services\RuntimeConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Provides the searchable AEMS Engagement Registry and controlled IAP/special
 * engagement creation entry points.
 */
class AemsEngagementController extends Controller
{
    public function __construct(
        private readonly AemsEngagementRegistryService $registry,
        private readonly AemsSupport $support,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AuditEngagement::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sourceType' => ['nullable', 'in:PLANNED,SPECIAL'],
            'status' => ['nullable', 'in:'.implode(',', AuditEngagement::STATUSES)],
            'officeId' => ['nullable', 'integer', 'exists:offices,id'],
            'auditAreaId' => ['nullable', 'integer', 'exists:audit_areas,id'],
            'includeArchived' => ['nullable', 'boolean'],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sortBy' => [
                'nullable',
                'in:engagement_code,title,source_type,status,planned_start_date,planned_person_days,updated_at',
            ],
            'sortDirection' => ['nullable', 'in:asc,desc'],
        ]);
        $includeArchived = (bool) ($validated['includeArchived'] ?? false)
            && $request->user()->hasAnyPermission([
                'aems.engagement.archive',
                'aems.engagement.restore',
            ]);
        $search = mb_strtolower(trim((string) ($validated['search'] ?? '')));

        $query = AuditEngagement::query()
            ->when($includeArchived, fn (Builder $query) => $query->withTrashed())
            ->visibleTo($request->user())
            ->with($this->registryRelations())
            ->withCount(['teamMembers', 'workingPapers', 'findings', 'reports']);

        $engagements = $query
            ->when($search !== '', fn (Builder $query) => $query
                ->where(function (Builder $searchQuery) use ($search): void {
                    $pattern = "%{$search}%";
                    $searchQuery
                        ->whereRaw('LOWER(engagement_code) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(title) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(COALESCE(special_authority_reference, \'\')) LIKE ?', [$pattern])
                        ->orWhereHas('offices', fn (Builder $office) => $office
                            ->whereRaw('LOWER(name) LIKE ?', [$pattern])
                            ->orWhereRaw('LOWER(code) LIKE ?', [$pattern]))
                        ->orWhereHas('auditAreas', fn (Builder $area) => $area
                            ->whereRaw('LOWER(name) LIKE ?', [$pattern])
                            ->orWhereRaw('LOWER(code) LIKE ?', [$pattern]));
                }))
            ->when(
                isset($validated['sourceType']),
                fn (Builder $query) => $query->where('source_type', $validated['sourceType']),
            )
            ->when(
                isset($validated['status']),
                fn (Builder $query) => $query->where('status', $validated['status']),
            )
            ->when(
                isset($validated['officeId']),
                fn (Builder $query) => $query->whereHas(
                    'offices',
                    fn (Builder $office) => $office->whereKey($validated['officeId']),
                ),
            )
            ->when(
                isset($validated['auditAreaId']),
                fn (Builder $query) => $query->whereHas(
                    'auditAreas',
                    fn (Builder $area) => $area->whereKey($validated['auditAreaId']),
                ),
            )
            ->orderBy(
                $validated['sortBy'] ?? 'updated_at',
                $validated['sortDirection'] ?? 'desc',
            )
            ->paginate((int) ($validated['perPage'] ?? $this->runtime->paginationSize()))
            ->withQueryString();

        $summaryQuery = AuditEngagement::query()
            ->withTrashed()
            ->visibleTo($request->user());
        $summary = [
            'total' => (clone $summaryQuery)->whereNull('deleted_at')->count(),
            'planned' => (clone $summaryQuery)
                ->whereNull('deleted_at')->where('source_type', 'PLANNED')->count(),
            'special' => (clone $summaryQuery)
                ->whereNull('deleted_at')->where('source_type', 'SPECIAL')->count(),
            'ongoing' => (clone $summaryQuery)
                ->whereNull('deleted_at')
                ->whereNotIn('status', ['ISSUED', 'CLOSED', 'CANCELLED'])
                ->count(),
            'archived' => (clone $summaryQuery)->whereNotNull('deleted_at')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'engagements' => AemsEngagementResource::collection(
                    $engagements->getCollection(),
                ),
                'summary' => $summary,
                'pagination' => [
                    'currentPage' => $engagements->currentPage(),
                    'lastPage' => $engagements->lastPage(),
                    'perPage' => $engagements->perPage(),
                    'total' => $engagements->total(),
                    'from' => $engagements->firstItem(),
                    'to' => $engagements->lastItem(),
                ],
            ],
        ]);
    }

    public function importOptions(): JsonResponse
    {
        Gate::authorize('create', AuditEngagement::class);

        return response()->json([
            'success' => true,
            'data' => [
                'iapEngagements' => $this->registry->eligibleIapEngagements()
                    ->map(fn ($source): array => [
                        'id' => $source->id,
                        'engagementCode' => $source->engagement_code,
                        'title' => $source->title,
                        'plan' => [
                            'id' => $source->plan->id,
                            'planCode' => $source->plan->plan_code,
                            'title' => $source->plan->title,
                            'fiscalYear' => $source->plan->fiscal_year,
                            'status' => $source->plan->status,
                            'revisionNumber' => $source->plan->revision_number,
                        ],
                        'offices' => $source->offices
                            ->map->only(['id', 'code', 'name'])->values(),
                        'auditAreas' => $source->auditAreas
                            ->map->only(['id', 'code', 'name'])->values(),
                        'plannedStartDate' => $source->planned_start_date?->toDateString(),
                        'plannedEndDate' => $source->planned_end_date?->toDateString(),
                        'plannedPersonDays' => (float) $source->estimated_person_days,
                        'priorityScore' => $source->source_priority_score === null
                            ? null : (float) $source->source_priority_score,
                        'residualRiskScore' => $source->source_residual_risk_score === null
                            ? null : (float) $source->source_residual_risk_score,
                        'riskLevelCode' => $source->source_risk_level_code,
                        'finalRank' => $source->source_final_rank,
                    ])->values(),
            ],
        ]);
    }

    public function import(AemsIapImportRequest $request): JsonResponse
    {
        Gate::authorize('create', AuditEngagement::class);
        $engagement = $this->registry->import(
            $request,
            (int) $request->validated('iapPlanEngagementId'),
            $request->validated('engagementCode'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Approved IAP engagement imported with a historical source snapshot.',
            'data' => [
                'engagement' => new AemsEngagementResource(
                    $this->loadEngagement($engagement),
                ),
            ],
        ], 201);
    }

    public function store(AemsEngagementRequest $request): JsonResponse
    {
        Gate::authorize('create', AuditEngagement::class);
        $engagement = $this->registry->createSpecial($request, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Special engagement draft created successfully.',
            'data' => [
                'engagement' => new AemsEngagementResource(
                    $this->loadEngagement($engagement),
                ),
            ],
        ], 201);
    }

    public function show(Request $request, int $engagement): JsonResponse
    {
        $record = AuditEngagement::withTrashed()->findOrFail($engagement);
        Gate::authorize('view', $record);

        return response()->json([
            'success' => true,
            'data' => [
                'engagement' => new AemsEngagementResource(
                    $this->loadEngagement($record, true),
                ),
            ],
        ]);
    }

    public function update(
        AemsEngagementRequest $request,
        AuditEngagement $engagement,
    ): JsonResponse {
        Gate::authorize('update', $engagement);
        $record = $this->registry->update($request, $engagement, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Engagement registry details updated successfully.',
            'data' => [
                'engagement' => new AemsEngagementResource(
                    $this->loadEngagement($record, true),
                ),
            ],
        ]);
    }

    public function scope(Request $request, int $engagement): JsonResponse
    {
        $record = AuditEngagement::withTrashed()->findOrFail($engagement);
        Gate::authorize('view', $record);
        if (! $request->user()->hasPermission('aems.foundation.view')) {
            abort(403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'scope' => new AemsEngagementResource($this->loadEngagement($record, true)),
                'contract' => [
                    'screenId' => 'SCR-212',
                    'officeCount' => 1,
                    'mutableStatuses' => ['DRAFT', 'AUTHORIZATION_PREPARATION', 'AUTHORIZED'],
                    'sourceVarianceDecisions' => ['ALIGNED', 'VARIANCE_APPROVED', 'NOT_APPLICABLE'],
                ],
            ],
        ]);
    }

    public function updateScope(
        AemsScopeRequest $request,
        AuditEngagement $engagement,
    ): JsonResponse {
        Gate::authorize('view', $engagement);
        $record = $this->registry->updateScope($request, $engagement, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Engagement scope updated successfully.',
            'data' => [
                'scope' => new AemsEngagementResource($this->loadEngagement($record, true)),
            ],
        ]);
    }

    public function destroy(Request $request, AuditEngagement $engagement): JsonResponse
    {
        Gate::authorize('archive', $engagement);
        DB::transaction(function () use ($request, $engagement): void {
            $locked = AuditEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            $oldValues = $this->registry->auditSnapshot($locked);
            $locked->forceFill([
                'administrative_status' => 'ARCHIVED',
                'is_active' => false,
                'updated_by' => $request->user()->id,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $locked->delete();
            $newValues = $this->registry->auditSnapshot($locked);
            $this->support->event(
                $request,
                $locked,
                'ARCHIVE',
                $locked->status,
                $locked->status,
                $oldValues,
                $newValues,
                'Engagement registry record archived without hard deletion.',
            );
            $this->support->audit(
                $request,
                'aems.engagement.archived',
                $locked,
                $oldValues,
                $newValues,
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Engagement archived successfully and remains recoverable.',
        ]);
    }

    public function restore(Request $request, int $engagement): JsonResponse
    {
        $record = AuditEngagement::onlyTrashed()->findOrFail($engagement);
        Gate::authorize('restore', $record);

        DB::transaction(function () use ($request, $record): void {
            $locked = AuditEngagement::onlyTrashed()->lockForUpdate()->findOrFail($record->id);
            if ($locked->iap_plan_engagement_id
                && AuditEngagement::query()
                    ->where('id', '<>', $locked->id)
                    ->where('iap_plan_engagement_id', $locked->iap_plan_engagement_id)
                    ->exists()) {
                throw ValidationException::withMessages([
                    'engagement' => [
                        'Another active engagement already uses this IAP source.',
                    ],
                ]);
            }
            $oldValues = $this->registry->auditSnapshot($locked);
            $locked->restore();
            $locked->forceFill([
                ...AuditEngagement::lifecycleProjectionForStatus($locked->status),
                'is_active' => true,
                'updated_by' => $request->user()->id,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->registry->relinkIapSource($locked);
            $newValues = $this->registry->auditSnapshot($locked);
            $this->support->event(
                $request,
                $locked,
                'RESTORE',
                $locked->status,
                $locked->status,
                $oldValues,
                $newValues,
                'Archived engagement restored.',
            );
            $this->support->audit(
                $request,
                'aems.engagement.restored',
                $locked,
                $oldValues,
                $newValues,
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Engagement restored successfully.',
            'data' => [
                'engagement' => new AemsEngagementResource(
                    $this->loadEngagement($record->fresh(), true),
                ),
            ],
        ]);
    }

    /** @return list<string> */
    private function registryRelations(): array
    {
        return [
            'auditType',
            'engagementApproach',
            'specialAuthorityApprover:id,employee_id,name,initials',
            'creator:id,employee_id,name,initials',
            'updater:id,employee_id,name,initials',
            'offices:id,code,name',
            'auditAreas:id,code,name',
            'auditFocuses:id,code,name',
            'engagementOrder.approver:id,name',
            'engagementOrder.issuer:id,name',
            'scopeBackfillReview',
        ];
    }

    private function loadEngagement(
        AuditEngagement $engagement,
        bool $details = false,
    ): AuditEngagement {
        $relations = $this->registryRelations();
        if ($details) {
            $relations = [
                ...$relations,
                'teamMembers.user:id,employee_id,name,initials',
                'events.actor:id,employee_id,name,initials',
                'sourcePlanEngagement.plan',
                'sourcePlanEngagement.prioritizationItem.run.riskPeriod',
                'sourcePlanEngagement.universeRiskAssessment.residualRiskLevel',
                'sourcePlanEngagement.auditUniverseItem',
            ];
        }

        return $engagement
            ->load($relations)
            ->loadCount(['teamMembers', 'workingPapers', 'findings', 'reports']);
    }
}
