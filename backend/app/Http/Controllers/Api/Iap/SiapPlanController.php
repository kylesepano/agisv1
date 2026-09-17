<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iap\IapTransitionRequest;
use App\Http\Requests\Iap\SiapPlanRequest;
use App\Http\Resources\SiapPlanResource;
use App\Models\SiapObjective;
use App\Models\SiapPriority;
use App\Models\StrategicInternalAuditPlan;
use App\Models\User;
use App\Services\IapSupport;
use App\Services\SiapPlanGuard;
use App\Services\SiapWorkflowService;
use App\Services\RuntimeConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages strategic plan revisions, objectives, priorities, and approval actions.
 */
class SiapPlanController extends Controller
{
    public function __construct(
        private readonly SiapPlanGuard $guard,
        private readonly SiapWorkflowService $workflow,
        private readonly IapSupport $support,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => [
                'nullable',
                'string',
                'in:'.implode(',', StrategicInternalAuditPlan::STATUSES).',ARCHIVED',
            ],
            'includeArchived' => ['nullable', 'boolean'],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sortBy' => [
                'nullable',
                'in:plan_code,start_year,end_year,title,status,updated_at',
            ],
            'sortDirection' => ['nullable', 'in:asc,desc'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $maySeeArchived = $request->user()->hasPermission('iap.manage_universe');

        $query = StrategicInternalAuditPlan::query()
            ->when(
                (bool) ($validated['includeArchived'] ?? false) && $maySeeArchived,
                fn ($query) => $query->withTrashed(),
            )
            ->with(['preparer:id,employee_id,name,initials'])
            ->withCount(['objectives', 'priorities']);
        $this->guard->scopeVisible($query, $request->user());

        $plans = $query
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query
                    ->where('plan_code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('strategic_context', 'like', "%{$search}%")
                    ->orWhereHas('preparer', fn ($user) => $user->where('name', 'like', "%{$search}%"));
            }))
            ->when(isset($validated['status']), function ($query) use ($validated): void {
                if ($validated['status'] === 'ARCHIVED') {
                    $query->whereNotNull('deleted_at');
                } else {
                    $query->whereNull('deleted_at')->where('status', $validated['status']);
                }
            })
            ->orderBy($validated['sortBy'] ?? 'start_year', $validated['sortDirection'] ?? 'desc')
            ->paginate((int) ($validated['perPage'] ?? app(\App\Services\RuntimeConfiguration::class)->paginationSize()))
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => [
                'strategicPlans' => SiapPlanResource::collection($plans->getCollection()),
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

    public function store(SiapPlanRequest $request): JsonResponse
    {
        $this->guard->assertManagement($request->user());
        $validated = $request->validated();
        $coordinator = isset($validated['coordinatorId'])
            ? $this->planningUser((int) $validated['coordinatorId'], 'coordinatorId')
            : null;

        $plan = DB::transaction(function () use (
            $request,
            $validated,
            $coordinator,
        ): StrategicInternalAuditPlan {
            $this->assertNoCurrentPlan(
                (int) $validated['startYear'],
                (int) $validated['endYear'],
            );
            $plan = StrategicInternalAuditPlan::query()->create([
                ...$this->attributes($validated),
                'plan_code' => $validated['planCode'] ?? $this->runtime->formatNumber(
                    'siap_plan_number_format',
                    1,
                    [
                        'YEAR' => $validated['startYear'],
                        'START_YEAR' => $validated['startYear'],
                        'END_YEAR' => $validated['endYear'],
                    ],
                ),
                'status' => 'DRAFT',
                'revision_number' => 0,
                'is_current_revision' => true,
                'prepared_by' => $request->user()->id,
                'coordinator_id' => $coordinator?->id,
                'lock_version' => 1,
                'is_active' => true,
            ]);
            $this->syncContent($plan, $validated);
            $this->workflow->event($request, $plan, 'CREATE', null, 'DRAFT', null);
            $this->support->audit(
                $request,
                'iap.siap.created',
                $plan,
                null,
                $this->snapshot($plan),
            );

            return $plan;
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Strategic Internal Audit Plan created successfully.',
            'data' => ['strategicPlan' => new SiapPlanResource($this->loadPlan($plan))],
        ], 201);
    }

    public function show(Request $request, int $strategicPlan): JsonResponse
    {
        $query = StrategicInternalAuditPlan::query();
        if ($request->user()->hasPermission('iap.manage_universe')) {
            $query->withTrashed();
        }
        $record = $query->findOrFail($strategicPlan);
        if ($record->trashed()) {
            $this->guard->assertManagement($request->user());
        } else {
            $this->guard->assertCanView($request->user(), $record);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'strategicPlan' => new SiapPlanResource($this->loadPlan($record)),
            ],
        ]);
    }

    public function update(
        SiapPlanRequest $request,
        StrategicInternalAuditPlan $strategicPlan,
    ): JsonResponse {
        $this->guard->assertEditable($request->user(), $strategicPlan);
        $validated = $request->validated();
        $coordinator = array_key_exists('coordinatorId', $validated)
            ? ($validated['coordinatorId'] === null
                ? null
                : $this->planningUser((int) $validated['coordinatorId'], 'coordinatorId'))
            : $strategicPlan->coordinator;

        DB::transaction(function () use (
            $request,
            $strategicPlan,
            $validated,
            $coordinator,
        ): void {
            $locked = StrategicInternalAuditPlan::query()
                ->lockForUpdate()
                ->findOrFail($strategicPlan->id);
            $this->guard->assertEditable($request->user(), $locked);
            $this->guard->assertLockVersion($locked, (int) $validated['lockVersion']);

            if ((int) $validated['startYear'] !== $locked->start_year
                || (int) $validated['endYear'] !== $locked->end_year) {
                $this->assertNoCurrentPlan(
                    (int) $validated['startYear'],
                    (int) $validated['endYear'],
                    $locked->id,
                );
            }

            $old = $this->snapshot($locked);
            $locked->fill([
                ...$this->attributes($validated),
                'plan_code' => $validated['planCode'] ?? $locked->plan_code,
                'coordinator_id' => $coordinator?->id,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->syncContent($locked, $validated);
            $this->support->audit(
                $request,
                'iap.siap.updated',
                $locked,
                $old,
                $this->snapshot($locked),
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Strategic Internal Audit Plan updated successfully.',
            'data' => [
                'strategicPlan' => new SiapPlanResource(
                    $this->loadPlan($strategicPlan->fresh()),
                ),
            ],
        ]);
    }

    public function completeness(
        Request $request,
        StrategicInternalAuditPlan $strategicPlan,
    ): JsonResponse {
        $this->guard->assertCanView($request->user(), $strategicPlan);

        return response()->json([
            'success' => true,
            'data' => [
                'completeness' => $this->workflow->completeness($strategicPlan),
            ],
        ]);
    }

    public function transition(
        IapTransitionRequest $request,
        StrategicInternalAuditPlan $strategicPlan,
        string $action,
    ): JsonResponse {
        $updated = $this->workflow->transition(
            $request,
            $strategicPlan,
            $action,
            (int) $request->validated('lockVersion'),
            $request->validated('comment'),
            (bool) $request->validated('completionConfirmed', false),
        );

        return response()->json([
            'success' => true,
            'message' => 'Strategic plan workflow updated successfully.',
            'data' => [
                'strategicPlan' => new SiapPlanResource($this->loadPlan($updated->fresh())),
            ],
        ]);
    }

    public function revision(
        Request $request,
        StrategicInternalAuditPlan $strategicPlan,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:10000'],
        ]);
        $revision = $this->workflow->createRevision(
            $request,
            $strategicPlan,
            (int) $validated['lockVersion'],
            $validated['reason'],
        );

        return response()->json([
            'success' => true,
            'message' => 'A new strategic-plan revision was created successfully.',
            'data' => [
                'strategicPlan' => new SiapPlanResource($this->loadPlan($revision)),
            ],
        ], 201);
    }

    public function destroy(
        Request $request,
        StrategicInternalAuditPlan $strategicPlan,
    ): JsonResponse {
        $this->guard->assertManagement($request->user());
        if (! in_array(
            $strategicPlan->status,
            ['DRAFT', 'RETURNED_FOR_REVISION', 'COMPLETED'],
            true,
        )) {
            throw ValidationException::withMessages([
                'status' => [
                    'Only draft, returned, or completed strategic plans may be archived.',
                ],
            ]);
        }

        DB::transaction(function () use ($request, $strategicPlan): void {
            $locked = StrategicInternalAuditPlan::query()
                ->lockForUpdate()
                ->findOrFail($strategicPlan->id);
            $old = $this->snapshot($locked);
            $locked->forceFill([
                'is_active' => false,
                'is_current_revision' => false,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->workflow->event(
                $request,
                $locked,
                'ARCHIVE',
                $locked->status,
                $locked->status,
                null,
            );
            $this->support->audit(
                $request,
                'iap.siap.archived',
                $locked,
                $old,
                $locked->toArray(),
            );
            $locked->delete();
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Strategic Internal Audit Plan archived successfully.',
        ]);
    }

    public function restore(Request $request, int $strategicPlan): JsonResponse
    {
        $this->guard->assertManagement($request->user());
        $record = StrategicInternalAuditPlan::onlyTrashed()->findOrFail($strategicPlan);

        DB::transaction(function () use ($request, $record): void {
            $hasCurrent = StrategicInternalAuditPlan::query()
                ->where('start_year', $record->start_year)
                ->where('end_year', $record->end_year)
                ->where('is_current_revision', true)
                ->lockForUpdate()
                ->exists();
            $record->restore();
            $record->forceFill([
                'is_active' => true,
                'is_current_revision' => ! $hasCurrent,
                'lock_version' => $record->lock_version + 1,
            ])->save();
            $this->workflow->event(
                $request,
                $record,
                'RESTORE',
                $record->status,
                $record->status,
                null,
            );
            $this->support->audit(
                $request,
                'iap.siap.restored',
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
            'message' => 'Strategic Internal Audit Plan restored successfully.',
            'data' => [
                'strategicPlan' => new SiapPlanResource($this->loadPlan($record->fresh())),
            ],
        ]);
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'start_year' => $validated['startYear'],
            'end_year' => $validated['endYear'],
            'title' => $validated['title'],
            'strategic_context' => $validated['strategicContext'] ?? null,
            'vision' => $validated['vision'] ?? null,
            'mission_alignment' => $validated['missionAlignment'] ?? null,
            'planning_methodology' => $validated['planningMethodology'] ?? null,
            'expected_outcomes' => $validated['expectedOutcomes'],
        ];
    }

    /** @param array<string, mixed> $validated */
    private function syncContent(
        StrategicInternalAuditPlan $plan,
        array $validated,
    ): void {
        $plan->objectives()->delete();
        foreach ($validated['objectives'] as $index => $objective) {
            $record = SiapObjective::query()->create([
                'strategic_plan_id' => $plan->id,
                'objective_code' => strtoupper(trim($objective['objectiveCode'])),
                'title' => $objective['title'],
                'description' => $objective['description'],
                'expected_outcome' => $objective['expectedOutcome'],
                'display_order' => $index + 1,
            ]);
            $record->auditAreas()->sync($objective['auditAreaIds']);
        }

        $plan->priorities()->delete();
        foreach ($validated['priorities'] as $index => $priority) {
            SiapPriority::query()->create([
                'strategic_plan_id' => $plan->id,
                'priority_code' => strtoupper(trim($priority['priorityCode'])),
                'title' => $priority['title'],
                'theme' => $priority['theme'],
                'description' => $priority['description'],
                'expected_outcome' => $priority['expectedOutcome'],
                'display_order' => $index + 1,
            ]);
        }
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

    private function assertNoCurrentPlan(
        int $startYear,
        int $endYear,
        ?int $exceptId = null,
    ): void {
        if (StrategicInternalAuditPlan::query()
            ->where('start_year', $startYear)
            ->where('end_year', $endYear)
            ->where('is_current_revision', true)
            ->when($exceptId, fn ($query) => $query->where('id', '<>', $exceptId))
            ->lockForUpdate()
            ->exists()) {
            throw ValidationException::withMessages([
                'startYear' => [
                    'A current strategic-plan revision already exists for this planning period.',
                ],
            ]);
        }
    }

    private function loadPlan(
        StrategicInternalAuditPlan $plan,
    ): StrategicInternalAuditPlan {
        return $plan->load([
            'preparer:id,employee_id,name,initials',
            'coordinator:id,employee_id,name,initials',
            'submitter:id,employee_id,name,initials',
            'approver:id,employee_id,name,initials',
            'activator:id,employee_id,name,initials',
            'completer:id,employee_id,name,initials',
            'objectives.auditAreas:id,code,name',
            'priorities',
            'workflowEvents.actor:id,employee_id,name,initials',
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(StrategicInternalAuditPlan $plan): array
    {
        return $plan->load(['objectives.auditAreas', 'priorities'])->toArray();
    }
}
