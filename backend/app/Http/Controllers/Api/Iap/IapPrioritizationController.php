<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iap\IapPrioritizationItemRequest;
use App\Http\Requests\Iap\IapPrioritizationRunRequest;
use App\Http\Resources\IapPrioritizationResource;
use App\Models\IapPrioritizationEvent;
use App\Models\IapPrioritizationItem;
use App\Models\IapPrioritizationRun;
use App\Models\IapRiskPeriod;
use App\Models\User;
use App\Services\IapSupport;
use App\Services\IapBaicsIntegrationService;
use App\Services\RuntimeConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ranks validated risk assessments and records selection or deferral decisions.
 */
class IapPrioritizationController extends Controller
{
    public function __construct(
        private readonly IapSupport $support,
        private readonly RuntimeConfiguration $runtime,
        private readonly IapBaicsIntegrationService $baicsIntegrations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => [
                'nullable',
                'string',
                'in:'.implode(',', IapPrioritizationRun::STATUSES).',ARCHIVED',
            ],
            'includeArchived' => ['nullable', 'boolean'],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sortBy' => ['nullable', 'in:run_code,name,status,created_at,updated_at'],
            'sortDirection' => ['nullable', 'in:asc,desc'],
        ]);
        $mayWork = $request->user()->hasPermission('iap.update');
        $management = $this->isManagement($request->user());
        $search = trim((string) ($validated['search'] ?? ''));

        $query = IapPrioritizationRun::query()
            ->when(
                $management && $request->boolean('includeArchived'),
                fn ($query) => $query->withTrashed(),
            )
            ->when(! $mayWork, fn ($query) => $query->where('status', 'FINALIZED'))
            ->with([
                'riskPeriod:id,period_code,name,assessment_year,status',
                'creator:id,employee_id,name,initials',
            ])
            ->withCount('items')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('run_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('methodology', 'like', "%{$search}%")
                    ->orWhereHas('riskPeriod', fn ($period) => $period
                        ->where('period_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%"));
            }))
            ->when(isset($validated['status']), function ($query) use ($validated): void {
                $validated['status'] === 'ARCHIVED'
                    ? $query->whereNotNull('deleted_at')
                    : $query->whereNull('deleted_at')->where('status', $validated['status']);
            });

        $runs = $query
            ->orderBy($validated['sortBy'] ?? 'created_at', $validated['sortDirection'] ?? 'desc')
            ->paginate((int) ($validated['perPage'] ?? app(\App\Services\RuntimeConfiguration::class)->paginationSize()))
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => [
                'prioritizations' => IapPrioritizationResource::collection($runs->getCollection()),
                'pagination' => [
                    'currentPage' => $runs->currentPage(),
                    'lastPage' => $runs->lastPage(),
                    'perPage' => $runs->perPage(),
                    'total' => $runs->total(),
                    'from' => $runs->firstItem(),
                    'to' => $runs->lastItem(),
                ],
            ],
        ]);
    }

    public function store(IapPrioritizationRunRequest $request): JsonResponse
    {
        $this->assertManagement($request->user());
        $validated = $request->validated();
        $period = IapRiskPeriod::query()
            ->whereIn('status', ['VALIDATED', 'LOCKED'])
            ->findOrFail($validated['riskPeriodId']);
        if (! $period->assessments()->whereIn('status', ['VALIDATED', 'LOCKED'])->exists()) {
            throw ValidationException::withMessages([
                'riskPeriodId' => ['The selected period has no validated risk assessments.'],
            ]);
        }
        if (IapPrioritizationRun::query()
            ->where('risk_period_id', $period->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'riskPeriodId' => ['An active prioritization run already exists for this risk period.'],
            ]);
        }

        $run = DB::transaction(function () use ($request, $validated, $period): IapPrioritizationRun {
            $run = IapPrioritizationRun::query()->create([
                'run_code' => $validated['runCode']
                    ?: $this->runtime->formatNumber(
                        'prioritization_number_format',
                        ((int) IapPrioritizationRun::withTrashed()->max('id')) + 1,
                        ['YEAR' => $period->assessment_year],
                    ),
                'name' => $validated['name'],
                'risk_period_id' => $period->id,
                'methodology' => $validated['methodology'],
                'status' => 'DRAFT',
                'created_by' => $request->user()->id,
                'lock_version' => 1,
                'is_active' => true,
            ]);
            $this->generateItems($run, $period);
            $this->event($run, $request->user(), 'CREATE', null, 'DRAFT');
            $this->support->audit(
                $request,
                'iap.prioritization.created',
                $run,
                null,
                $run->toArray(),
            );

            return $run;
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Audit prioritization run created and ranked successfully.',
            'data' => [
                'prioritization' => new IapPrioritizationResource($this->load($run)),
            ],
        ], 201);
    }

    public function show(Request $request, int $prioritization): JsonResponse
    {
        $query = IapPrioritizationRun::query();
        if ($this->isManagement($request->user())) {
            $query->withTrashed();
        }
        $run = $query->findOrFail($prioritization);
        if (! $request->user()->hasPermission('iap.update') && $run->status !== 'FINALIZED') {
            abort(403, 'This prioritization run is not available to your role.');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'prioritization' => new IapPrioritizationResource($this->load($run)),
            ],
        ]);
    }

    public function update(
        IapPrioritizationRunRequest $request,
        IapPrioritizationRun $prioritization,
    ): JsonResponse {
        $this->assertManagement($request->user());
        $validated = $request->validated();

        DB::transaction(function () use ($request, $prioritization, $validated): void {
            $locked = IapPrioritizationRun::query()
                ->lockForUpdate()
                ->findOrFail($prioritization->id);
            $this->assertEditable($locked);
            $this->assertLock($locked->lock_version, (int) $validated['lockVersion']);
            $old = $locked->toArray();
            $locked->fill([
                'run_code' => $validated['runCode'] ?: $locked->run_code,
                'name' => $validated['name'],
                'methodology' => $validated['methodology'],
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->support->audit(
                $request,
                'iap.prioritization.updated',
                $locked,
                $old,
                $locked->toArray(),
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Audit prioritization run updated successfully.',
            'data' => [
                'prioritization' => new IapPrioritizationResource(
                    $this->load($prioritization->fresh()),
                ),
            ],
        ]);
    }

    public function updateItem(
        IapPrioritizationItemRequest $request,
        IapPrioritizationRun $prioritization,
        IapPrioritizationItem $item,
    ): JsonResponse {
        abort_unless($request->user()->hasPermission('iap.update'), 403);
        abort_unless($item->prioritization_run_id === $prioritization->id, 404);
        $this->assertEditable($prioritization);
        $validated = $request->validated();
        $itemCount = $prioritization->items()->count();
        if ((int) $validated['finalRank'] > $itemCount) {
            throw ValidationException::withMessages([
                'finalRank' => ["Final rank must be between 1 and {$itemCount}."],
            ]);
        }

        $decisionChanged = $validated['decision'] !== $item->recommended_decision;
        $decisionNeedsReason = $decisionChanged
            || in_array($validated['decision'], ['DEFERRED', 'NOT_SELECTED'], true);
        if ($decisionNeedsReason && trim((string) ($validated['decisionReason'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'decisionReason' => ['A reason is required for this prioritization decision.'],
            ]);
        }
        $rankChanged = (int) $validated['finalRank'] !== $item->system_rank;
        if ($rankChanged && trim((string) ($validated['overrideReason'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'overrideReason' => ['Explain why the final rank differs from the system rank.'],
            ]);
        }

        DB::transaction(function () use (
            $request,
            $prioritization,
            $item,
            $validated,
            $decisionChanged,
            $rankChanged,
        ): void {
            $run = IapPrioritizationRun::query()
                ->lockForUpdate()
                ->findOrFail($prioritization->id);
            $this->assertEditable($run);
            $locked = IapPrioritizationItem::query()
                ->lockForUpdate()
                ->findOrFail($item->id);
            $this->assertLock($locked->lock_version, (int) $validated['lockVersion']);
            $old = $locked->toArray();
            $oldRank = $locked->final_rank;
            $newRank = (int) $validated['finalRank'];
            if ($newRank < $oldRank) {
                IapPrioritizationItem::query()
                    ->where('prioritization_run_id', $run->id)
                    ->whereKeyNot($locked->id)
                    ->whereBetween('final_rank', [$newRank, $oldRank - 1])
                    ->increment('final_rank');
            } elseif ($newRank > $oldRank) {
                IapPrioritizationItem::query()
                    ->where('prioritization_run_id', $run->id)
                    ->whereKeyNot($locked->id)
                    ->whereBetween('final_rank', [$oldRank + 1, $newRank])
                    ->decrement('final_rank');
            }
            $locked->fill([
                'final_rank' => $newRank,
                'decision' => $validated['decision'],
                'decision_reason' => $validated['decisionReason'] ?? null,
                'is_manual_override' => $rankChanged || $decisionChanged,
                'override_reason' => $validated['overrideReason'] ?? null,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $run->increment('lock_version');
            $this->support->audit(
                $request,
                'iap.prioritization.item_updated',
                $locked,
                $old,
                $locked->toArray(),
                ['run_id' => $run->id],
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Prioritization decision updated successfully.',
            'data' => [
                'prioritization' => new IapPrioritizationResource(
                    $this->load($prioritization->fresh()),
                ),
            ],
        ]);
    }

    public function transition(
        Request $request,
        IapPrioritizationRun $prioritization,
        string $action,
    ): JsonResponse {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:10000'],
        ]);
        $action = strtoupper($action);
        $map = [
            'SUBMIT' => ['from' => ['DRAFT'], 'to' => 'PENDING_REVIEW', 'permission' => 'iap.submit'],
            'RESUBMIT' => ['from' => ['RETURNED_FOR_REVISION'], 'to' => 'RESUBMITTED', 'permission' => 'iap.submit'],
            'RETURN' => ['from' => ['PENDING_REVIEW', 'RESUBMITTED'], 'to' => 'RETURNED_FOR_REVISION', 'permission' => 'iap.review'],
            'FINALIZE' => ['from' => ['PENDING_REVIEW', 'RESUBMITTED'], 'to' => 'FINALIZED', 'permission' => 'iap.approve'],
        ];
        if (! isset($map[$action])) {
            abort(404);
        }
        abort_unless($request->user()->hasPermission($map[$action]['permission']), 403);
        if ($action === 'RETURN' && trim((string) ($validated['comment'] ?? '')) === '') {
            throw ValidationException::withMessages(['comment' => ['A return reason is required.']]);
        }

        DB::transaction(function () use (
            $request,
            $prioritization,
            $validated,
            $action,
            $map,
        ): void {
            $locked = IapPrioritizationRun::query()
                ->lockForUpdate()
                ->findOrFail($prioritization->id);
            $this->assertLock($locked->lock_version, (int) $validated['lockVersion']);
            if (! in_array($locked->status, $map[$action]['from'], true)) {
                throw ValidationException::withMessages([
                    'status' => ["{$action} is unavailable while the run is {$locked->status}."],
                ]);
            }
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) {
                $this->assertComplete($locked);
            }
            if ($action === 'FINALIZE') {
                $this->assertValidatedLineage($locked);
                if ($this->runtime->boolean('baics_integration_required')) {
                    $this->baicsIntegrations->assertPrioritizationReady($locked);
                }
            }
            if ($action === 'FINALIZE' && $locked->submitted_by === $request->user()->id) {
                throw ValidationException::withMessages([
                    'finalizer' => ['The submitter cannot finalize the same prioritization run.'],
                ]);
            }

            $oldStatus = $locked->status;
            $newStatus = $map[$action]['to'];
            $attributes = [
                'status' => $newStatus,
                'lock_version' => $locked->lock_version + 1,
            ];
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) {
                $attributes += [
                    'submitted_at' => now(),
                    'submitted_by' => $request->user()->id,
                ];
            } elseif ($action === 'FINALIZE') {
                $attributes += [
                    'finalized_at' => now(),
                    'finalized_by' => $request->user()->id,
                ];
            }
            $locked->forceFill($attributes)->save();
            $this->event(
                $locked,
                $request->user(),
                $action,
                $oldStatus,
                $newStatus,
                $validated['comment'] ?? null,
            );
            $this->support->audit(
                $request,
                'iap.prioritization.'.strtolower($action),
                $locked,
                ['status' => $oldStatus],
                ['status' => $newStatus],
                ['comment' => $validated['comment'] ?? null],
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Audit prioritization workflow updated successfully.',
            'data' => [
                'prioritization' => new IapPrioritizationResource(
                    $this->load($prioritization->fresh()),
                ),
            ],
        ]);
    }

    public function destroy(
        Request $request,
        IapPrioritizationRun $prioritization,
    ): JsonResponse {
        $this->assertManagement($request->user());
        if (! in_array($prioritization->status, ['DRAFT', 'FINALIZED'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft or finalized prioritization runs may be archived.'],
            ]);
        }
        DB::transaction(function () use ($request, $prioritization): void {
            $prioritization->forceFill([
                'is_active' => false,
                'lock_version' => $prioritization->lock_version + 1,
            ])->save();
            $this->event(
                $prioritization,
                $request->user(),
                'ARCHIVE',
                $prioritization->status,
                $prioritization->status,
            );
            $this->support->audit(
                $request,
                'iap.prioritization.archived',
                $prioritization,
                null,
                $prioritization->toArray(),
            );
            $prioritization->delete();
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Audit prioritization run archived successfully.',
        ]);
    }

    public function restore(Request $request, int $prioritization): JsonResponse
    {
        $this->assertManagement($request->user());
        $run = IapPrioritizationRun::onlyTrashed()->findOrFail($prioritization);
        if (IapPrioritizationRun::query()
            ->where('risk_period_id', $run->risk_period_id)
            ->exists()) {
            throw ValidationException::withMessages([
                'riskPeriodId' => ['Another active prioritization run already uses this risk period.'],
            ]);
        }

        DB::transaction(function () use ($request, $run): void {
            $run->restore();
            $run->forceFill([
                'is_active' => true,
                'lock_version' => $run->lock_version + 1,
            ])->save();
            $this->event($run, $request->user(), 'RESTORE', $run->status, $run->status);
            $this->support->audit(
                $request,
                'iap.prioritization.restored',
                $run,
                null,
                $run->toArray(),
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Audit prioritization run restored successfully.',
            'data' => [
                'prioritization' => new IapPrioritizationResource($this->load($run)),
            ],
        ]);
    }

    private function generateItems(
        IapPrioritizationRun $run,
        IapRiskPeriod $period,
    ): void {
        $assessments = $period->assessments()
            ->whereIn('status', ['VALIDATED', 'LOCKED'])
            ->with([
                'auditUniverseItem.responsibleOffice:id,code,name',
                'auditUniverseItem.primaryAuditArea:id,code,name',
                'residualRiskLevel',
            ])
            ->get()
            ->sortBy([
                ['residual_risk_score', 'desc'],
                ['inherent_risk_score', 'desc'],
                fn ($left, $right) => strcmp(
                    $left->auditUniverseItem->name,
                    $right->auditUniverseItem->name,
                ),
            ])
            ->values();

        foreach ($assessments as $index => $assessment) {
            $priorityScore = round((float) $assessment->residual_risk_score * 20, 2);
            $recommended = $priorityScore >= 60
                ? 'SELECTED'
                : ($priorityScore >= 40 ? 'DEFERRED' : 'NOT_SELECTED');
            IapPrioritizationItem::query()->create([
                'prioritization_run_id' => $run->id,
                'risk_assessment_id' => $assessment->id,
                'audit_universe_item_id' => $assessment->audit_universe_item_id,
                'subject_code' => $assessment->auditUniverseItem->subject_code,
                'subject_name' => $assessment->auditUniverseItem->name,
                'office_code' => $assessment->auditUniverseItem->responsibleOffice?->code,
                'office_name' => $assessment->auditUniverseItem->responsibleOffice?->name,
                'audit_area_code' => $assessment->auditUniverseItem->primaryAuditArea?->code,
                'audit_area_name' => $assessment->auditUniverseItem->primaryAuditArea?->name,
                'inherent_risk_score' => $assessment->inherent_risk_score,
                'control_effectiveness_percent' => $assessment->control_effectiveness_percent,
                'residual_risk_score' => $assessment->residual_risk_score,
                'risk_level_code' => $assessment->residualRiskLevel->code,
                'risk_level_label' => $assessment->residualRiskLevel->label,
                'priority_score' => $priorityScore,
                'system_rank' => $index + 1,
                'final_rank' => $index + 1,
                'recommended_decision' => $recommended,
                'decision' => $recommended,
                'lock_version' => 1,
            ]);
        }
    }

    private function assertComplete(IapPrioritizationRun $run): void
    {
        $items = $run->items()->get();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['The prioritization run contains no ranked items.'],
            ]);
        }
        $invalidDecision = $items->first(fn ($item) => (
            in_array($item->decision, ['DEFERRED', 'NOT_SELECTED'], true)
            || $item->decision !== $item->recommended_decision
        ) && blank($item->decision_reason));
        if ($invalidDecision) {
            throw ValidationException::withMessages([
                'items' => ["{$invalidDecision->subject_code} requires a decision reason."],
            ]);
        }
        $invalidOverride = $items->first(
            fn ($item) => $item->is_manual_override && blank($item->override_reason),
        );
        if ($invalidOverride) {
            throw ValidationException::withMessages([
                'items' => ["{$invalidOverride->subject_code} requires a manual-override reason."],
            ]);
        }
    }

    private function assertValidatedLineage(IapPrioritizationRun $run): void
    {
        $run->loadMissing(['riskPeriod', 'items.riskAssessment']);
        if (! $run->riskPeriod
            || ! in_array($run->riskPeriod->status, ['VALIDATED', 'LOCKED'], true)) {
            throw ValidationException::withMessages([
                'riskPeriod' => [
                    'A prioritization run can only be finalized from a validated or locked assessment period.',
                ],
            ]);
        }

        $invalid = $run->items->first(fn ($item) => ! $item->riskAssessment
            || $item->riskAssessment->trashed()
            || ! in_array($item->riskAssessment->status, ['VALIDATED', 'LOCKED'], true));
        if ($invalid) {
            throw ValidationException::withMessages([
                'items' => [
                    "{$invalid->subject_code} no longer has a validated risk assessment.",
                ],
            ]);
        }
    }

    private function assertEditable(IapPrioritizationRun $run): void
    {
        if (! in_array($run->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft or returned prioritization runs may be changed.'],
            ]);
        }
    }

    private function assertLock(int $current, int $provided): void
    {
        if ($current !== $provided) {
            throw ValidationException::withMessages([
                'lockVersion' => ['This record changed. Refresh before continuing.'],
            ]);
        }
    }

    private function assertManagement(User $user): void
    {
        abort_unless($this->isManagement($user), 403);
    }

    private function isManagement(User $user): bool
    {
        return $user->hasPermission('iap.manage_universe');
    }

    private function event(
        IapPrioritizationRun $run,
        User $actor,
        string $action,
        ?string $from,
        string $to,
        ?string $comment = null,
    ): void {
        IapPrioritizationEvent::query()->create([
            'prioritization_run_id' => $run->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor->id,
            'comment' => $comment,
            'run_lock_version' => $run->lock_version,
        ]);
    }

    private function load(IapPrioritizationRun $run): IapPrioritizationRun
    {
        return $run->load([
            'riskPeriod:id,period_code,name,assessment_year,status',
            'creator:id,employee_id,name,initials',
            'submitter:id,employee_id,name,initials',
            'finalizer:id,employee_id,name,initials',
            'items',
            'events.actor:id,employee_id,name,initials',
        ]);
    }
}
