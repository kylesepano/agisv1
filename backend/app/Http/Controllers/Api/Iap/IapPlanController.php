<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iap\IapPlanRequest;
use App\Http\Resources\IapPlanResource;
use App\Models\IapWorkflowEvent;
use App\Models\InternalAuditPlan;
use App\Models\User;
use App\Services\IapPlanGuard;
use App\Services\IapSupport;
use App\Services\RuntimeConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages Annual Internal Audit Plan revisions and guarded lifecycle operations.
 */
class IapPlanController extends Controller
{
    public function __construct(
        private readonly IapPlanGuard $guard,
        private readonly IapSupport $support,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.implode(',', InternalAuditPlan::STATUSES)],
            'fiscalYear' => ['nullable', 'integer', 'min:2000', 'max:2200'],
            'includeArchived' => ['nullable', 'boolean'],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sortBy' => ['nullable', 'in:plan_code,fiscal_year,title,status,updated_at'],
            'sortDirection' => ['nullable', 'in:asc,desc'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $query = InternalAuditPlan::query()
            ->when(
                (bool) ($validated['includeArchived'] ?? false)
                    && $request->user()->hasPermission('iap.manage_universe'),
                fn ($query) => $query->withTrashed(),
            )
            ->with(['planningPeriodType', 'preparer:id,employee_id,name,initials'])
            ->withCount(['riskAssessments', 'engagements']);

        $this->guard->scopeVisible($query, $request->user());

        $plans = $query
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query
                    ->where('plan_code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('preparer', fn ($user) => $user->where('name', 'like', "%{$search}%"));
            }))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['fiscalYear']), fn ($query) => $query->where('fiscal_year', $validated['fiscalYear']))
            ->orderBy($validated['sortBy'] ?? 'fiscal_year', $validated['sortDirection'] ?? 'desc')
            ->paginate((int) ($validated['perPage'] ?? app(\App\Services\RuntimeConfiguration::class)->paginationSize()))
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => [
                'plans' => IapPlanResource::collection($plans->getCollection()),
                'pagination' => [
                    'currentPage' => $plans->currentPage(),
                    'lastPage' => $plans->lastPage(),
                    'perPage' => $plans->perPage(),
                    'total' => $plans->total(),
                    'from' => $plans->firstItem(),
                    'to' => $plans->lastItem(),
                ],
            ],
        ]);
    }

    public function store(IapPlanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->support->masterItem(
            (int) $validated['planningPeriodTypeId'],
            'IAP_PLANNING_PERIOD_TYPE',
        );
        $preparedBy = $this->planningUser(
            (int) ($validated['preparedBy'] ?? $request->user()->id),
            'preparedBy',
        );
        $coordinator = isset($validated['coordinatorId'])
            ? $this->planningUser((int) $validated['coordinatorId'], 'coordinatorId')
            : null;

        $plan = DB::transaction(function () use ($request, $validated, $preparedBy, $coordinator): InternalAuditPlan {
            if (InternalAuditPlan::query()
                ->where('fiscal_year', $validated['fiscalYear'])
                ->where('is_current_revision', true)
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'fiscalYear' => ['A current plan revision already exists for this fiscal year.'],
                ]);
            }

            $plan = InternalAuditPlan::query()->create([
                ...$this->attributes($validated),
                'plan_code' => $validated['planCode']
                    ?? $this->runtime->formatNumber(
                        'iap_plan_number_format',
                        1,
                        ['YEAR' => $validated['fiscalYear']],
                    ),
                'status' => 'DRAFT',
                'revision_number' => 0,
                'is_current_revision' => true,
                'prepared_by' => $preparedBy->id,
                'coordinator_id' => $coordinator?->id,
                'lock_version' => 1,
                'is_active' => true,
            ]);

            $this->workflowEvent(
                $request,
                $plan,
                'CREATE',
                null,
                'DRAFT',
                'Annual Internal Audit Plan created.',
                [],
                $this->workflowSnapshot($plan),
            );
            $this->support->audit($request, 'iap.plan.created', $plan, null, $plan->toArray());

            return $plan;
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Annual Internal Audit Plan created successfully.',
            'data' => ['plan' => new IapPlanResource($this->loadPlan($plan))],
        ], 201);
    }

    public function show(Request $request, InternalAuditPlan $plan): JsonResponse
    {
        $this->guard->assertCanView($request->user(), $plan);

        return response()->json([
            'success' => true,
            'data' => ['plan' => new IapPlanResource($this->loadPlan($plan))],
        ]);
    }

    public function update(IapPlanRequest $request, InternalAuditPlan $plan): JsonResponse
    {
        $this->guard->assertEditable($request->user(), $plan);
        $validated = $request->validated();
        $this->support->masterItem(
            (int) $validated['planningPeriodTypeId'],
            'IAP_PLANNING_PERIOD_TYPE',
        );
        $preparedBy = isset($validated['preparedBy'])
            ? $this->planningUser((int) $validated['preparedBy'], 'preparedBy')
            : $plan->preparer;
        $coordinator = array_key_exists('coordinatorId', $validated)
            ? ($validated['coordinatorId'] === null
                ? null
                : $this->planningUser((int) $validated['coordinatorId'], 'coordinatorId'))
            : $plan->coordinator;

        DB::transaction(function () use ($request, $plan, $validated, $preparedBy, $coordinator): void {
            $locked = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->guard->assertEditable($request->user(), $locked);
            $this->guard->assertLockVersion($locked, (int) $validated['lockVersion']);

            if ((int) $validated['fiscalYear'] !== $locked->fiscal_year
                && InternalAuditPlan::query()
                    ->where('fiscal_year', $validated['fiscalYear'])
                    ->where('is_current_revision', true)
                    ->where('id', '<>', $locked->id)
                    ->exists()) {
                throw ValidationException::withMessages([
                    'fiscalYear' => ['A current plan revision already exists for this fiscal year.'],
                ]);
            }

            $old = $locked->toArray();
            $locked->fill([
                ...$this->attributes($validated),
                'plan_code' => $validated['planCode'] ?? $locked->plan_code,
                'prepared_by' => $preparedBy->id,
                'coordinator_id' => $coordinator?->id,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->support->audit($request, 'iap.plan.updated', $locked, $old, $locked->fresh()->toArray());
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Annual Internal Audit Plan updated successfully.',
            'data' => ['plan' => new IapPlanResource($this->loadPlan($plan->fresh()))],
        ]);
    }

    public function destroy(Request $request, InternalAuditPlan $plan): JsonResponse
    {
        $this->guard->assertManagement($request->user());

        if (! in_array($plan->status, ['DRAFT', 'RETURNED_FOR_REVISION', 'REJECTED', 'COMPLETED'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft, returned, rejected, or completed plans may be archived.'],
            ]);
        }

        DB::transaction(function () use ($request, $plan): void {
            $locked = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $old = $locked->toArray();
            $locked->forceFill([
                'is_active' => false,
                'is_current_revision' => false,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->workflowEvent(
                $request,
                $locked,
                'ARCHIVE',
                $locked->status,
                $locked->status,
                'Annual Internal Audit Plan archived.',
                $old,
                $this->workflowSnapshot($locked),
            );
            $this->support->audit($request, 'iap.plan.archived', $locked, $old, $locked->toArray());
            $locked->delete();
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Annual Internal Audit Plan archived successfully.',
        ]);
    }

    public function restore(Request $request, int $plan): JsonResponse
    {
        $this->guard->assertManagement($request->user());
        $record = InternalAuditPlan::onlyTrashed()->findOrFail($plan);

        DB::transaction(function () use ($request, $record): void {
            $old = $this->workflowSnapshot($record);
            $hasCurrent = InternalAuditPlan::query()
                ->where('fiscal_year', $record->fiscal_year)
                ->where('is_current_revision', true)
                ->lockForUpdate()
                ->exists();

            $record->restore();
            $record->forceFill([
                'is_active' => true,
                'is_current_revision' => ! $hasCurrent,
                'lock_version' => $record->lock_version + 1,
            ])->save();
            $this->workflowEvent(
                $request,
                $record,
                'RESTORE',
                $record->status,
                $record->status,
                'Annual Internal Audit Plan restored.',
                $old,
                $this->workflowSnapshot($record),
            );
            $this->support->audit(
                $request,
                'iap.plan.restored',
                $record,
                ['is_active' => false, 'is_archived' => true],
                [
                    'is_active' => true,
                    'is_archived' => false,
                    'is_current_revision' => ! $hasCurrent,
                ],
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Annual Internal Audit Plan restored successfully.',
            'data' => ['plan' => new IapPlanResource($this->loadPlan($record->fresh()))],
        ]);
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'fiscal_year' => $validated['fiscalYear'],
            'planning_period_type_id' => $validated['planningPeriodTypeId'],
            'planning_period_start' => $validated['planningPeriodStart'],
            'planning_period_end' => $validated['planningPeriodEnd'],
            'title' => $validated['title'],
            'executive_summary' => $validated['executiveSummary'] ?? null,
            'planning_methodology' => $validated['planningMethodology'] ?? null,
            'overall_objective' => $validated['overallObjective'],
            'overall_scope' => $validated['overallScope'],
            'limitations' => $validated['limitations'] ?? null,
        ];
    }

    private function planningUser(int $id, string $field): User
    {
        $user = User::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'iap.assign_team'))
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                $field => ['Select an active CIAS planning user.'],
            ]);
        }

        return $user;
    }

    private function workflowEvent(
        Request $request,
        InternalAuditPlan $plan,
        string $action,
        ?string $fromStatus,
        string $toStatus,
        ?string $comment,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        IapWorkflowEvent::query()->create([
            'plan_id' => $plan->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_id' => $request->user()->id,
            'actor_role_code' => $request->user()->role->code,
            'comment' => $comment,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'plan_lock_version' => $plan->lock_version,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);
    }

    /** @return array<string, mixed> */
    private function workflowSnapshot(InternalAuditPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'planCode' => $plan->plan_code,
            'status' => $plan->status,
            'revisionNumber' => $plan->revision_number,
            'isCurrentRevision' => $plan->is_current_revision,
            'lockVersion' => $plan->lock_version,
        ];
    }

    private function loadPlan(InternalAuditPlan $plan): InternalAuditPlan
    {
        return $plan->load([
            'planningPeriodType',
            'prioritizationRun.riskPeriod',
            'prioritizationRun.items',
            'preparer:id,employee_id,name,initials',
            'coordinator:id,employee_id,name,initials',
            'submitter:id,employee_id,name,initials',
            'approver:id,employee_id,name,initials',
            'activator:id,employee_id,name,initials',
            'completer:id,employee_id,name,initials',
            'riskAssessments.office:id,code,name',
            'riskAssessments.auditArea:id,code,name',
            'riskAssessments.calculatedRiskLevel',
            'riskAssessments.overrideRiskLevel',
            'riskAssessments.finalRiskLevel',
            'riskAssessments.scores.criterion',
            'engagements.engagementType',
            'engagements.auditApproach',
            'engagements.priority',
            'engagements.riskLevel',
            'engagements.prioritizationItem',
            'engagements.offices:id,code,name',
            'engagements.auditAreas:id,code,name',
            'engagements.auditFocuses:id,code,name',
            'engagements.teamMembers.user:id,employee_id,name,initials',
            'engagements.teamMembers.teamRole',
            'workflowEvents.actor:id,employee_id,name,initials',
        ]);
    }
}
