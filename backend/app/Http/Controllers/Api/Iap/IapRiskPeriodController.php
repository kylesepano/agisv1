<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iap\IapRiskPeriodRequest;
use App\Http\Resources\IapRiskPeriodResource;
use App\Models\IapRiskPeriod;
use App\Models\IapRiskPeriodCriterion;
use App\Models\IapRiskPeriodEvent;
use App\Models\User;
use App\Services\IapSupport;
use App\Services\IapBaicsIntegrationService;
use App\Services\RuntimeConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Controls risk-assessment periods, criteria, validation, and baseline locking.
 */
class IapRiskPeriodController extends Controller
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
            'status' => ['nullable', 'string', 'in:'.implode(',', IapRiskPeriod::STATUSES).',ARCHIVED'],
            'includeArchived' => ['nullable', 'boolean'],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sortBy' => ['nullable', 'in:period_code,name,assessment_year,status,updated_at'],
            'sortDirection' => ['nullable', 'in:asc,desc'],
        ]);
        $management = $this->isManagement($request->user());
        $search = trim((string) ($validated['search'] ?? ''));

        $query = IapRiskPeriod::query()
            ->when($management && $request->boolean('includeArchived'), fn ($query) => $query->withTrashed())
            ->when(
                ! $management && ! $request->user()->hasPermission('iap.assess_risk'),
                fn ($query) => $query->whereIn('status', ['VALIDATED', 'LOCKED']),
            )
            ->with('creator:id,employee_id,name,initials')
            ->withCount('assessments')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('period_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('assessment_year', 'like', "%{$search}%");
            }))
            ->when(isset($validated['status']), function ($query) use ($validated): void {
                $validated['status'] === 'ARCHIVED'
                    ? $query->whereNotNull('deleted_at')
                    : $query->whereNull('deleted_at')->where('status', $validated['status']);
            });

        $periods = $query
            ->orderBy($validated['sortBy'] ?? 'assessment_year', $validated['sortDirection'] ?? 'desc')
            ->paginate((int) ($validated['perPage'] ?? app(\App\Services\RuntimeConfiguration::class)->paginationSize()))
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => [
                'riskPeriods' => IapRiskPeriodResource::collection($periods->getCollection()),
                'pagination' => [
                    'currentPage' => $periods->currentPage(),
                    'lastPage' => $periods->lastPage(),
                    'perPage' => $periods->perPage(),
                    'total' => $periods->total(),
                    'from' => $periods->firstItem(),
                    'to' => $periods->lastItem(),
                ],
            ],
        ]);
    }

    public function store(IapRiskPeriodRequest $request): JsonResponse
    {
        $this->assertManagement($request->user());
        $validated = $request->validated();
        $this->validateCriteria($validated['criteria']);

        $period = DB::transaction(function () use ($request, $validated): IapRiskPeriod {
            $period = IapRiskPeriod::query()->create([
                'period_code' => $validated['periodCode'] ?: $this->runtime->formatNumber(
                    'risk_period_number_format',
                    ((int) IapRiskPeriod::withTrashed()->max('id')) + 1,
                    ['YEAR' => $validated['assessmentYear']],
                ),
                'name' => $validated['name'],
                'assessment_year' => $validated['assessmentYear'],
                'start_date' => $validated['startDate'],
                'end_date' => $validated['endDate'],
                'instructions' => $validated['instructions'] ?? null,
                'status' => 'DRAFT',
                'created_by' => $request->user()->id,
                'lock_version' => 1,
                'is_active' => true,
            ]);
            $this->syncCriteria($period, $validated['criteria']);
            $this->event($period, $request->user(), 'CREATE', null, 'DRAFT');
            $this->support->audit($request, 'iap.risk_period.created', $period, null, $period->toArray());

            return $period;
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Risk assessment period created successfully.',
            'data' => ['riskPeriod' => new IapRiskPeriodResource($this->load($period))],
        ], 201);
    }

    public function show(Request $request, int $period): JsonResponse
    {
        $query = IapRiskPeriod::query();
        if ($this->isManagement($request->user())) {
            $query->withTrashed();
        }
        $record = $query->findOrFail($period);
        if (! $this->isManagement($request->user())
            && ! $request->user()->hasPermission('iap.assess_risk')
            && ! in_array($record->status, ['VALIDATED', 'LOCKED'], true)) {
            abort(403, 'This risk assessment period is not available to your role.');
        }

        return response()->json([
            'success' => true,
            'data' => ['riskPeriod' => new IapRiskPeriodResource($this->load($record))],
        ]);
    }

    public function update(IapRiskPeriodRequest $request, IapRiskPeriod $period): JsonResponse
    {
        $this->assertManagement($request->user());
        $validated = $request->validated();
        $this->validateCriteria($validated['criteria']);

        DB::transaction(function () use ($request, $period, $validated): void {
            $locked = IapRiskPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $this->assertEditablePeriod($locked);
            $this->assertLock($locked->lock_version, (int) $validated['lockVersion']);
            $old = $locked->toArray();
            $locked->fill([
                'period_code' => $validated['periodCode'] ?: $locked->period_code,
                'name' => $validated['name'],
                'assessment_year' => $validated['assessmentYear'],
                'start_date' => $validated['startDate'],
                'end_date' => $validated['endDate'],
                'instructions' => $validated['instructions'] ?? null,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->syncCriteria($locked, $validated['criteria']);
            $this->support->audit($request, 'iap.risk_period.updated', $locked, $old, $locked->toArray());
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Risk assessment period updated successfully.',
            'data' => ['riskPeriod' => new IapRiskPeriodResource($this->load($period->fresh()))],
        ]);
    }

    public function transition(Request $request, IapRiskPeriod $period, string $action): JsonResponse
    {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:10000'],
        ]);
        $action = strtoupper($action);
        $map = [
            'OPEN' => ['from' => ['DRAFT'], 'to' => 'OPEN', 'permission' => 'iap.update'],
            'SUBMIT' => ['from' => ['OPEN'], 'to' => 'PENDING_VALIDATION', 'permission' => 'iap.submit'],
            'RESUBMIT' => ['from' => ['RETURNED_FOR_REVISION'], 'to' => 'RESUBMITTED', 'permission' => 'iap.submit'],
            'RETURN' => ['from' => ['PENDING_VALIDATION', 'RESUBMITTED'], 'to' => 'RETURNED_FOR_REVISION', 'permission' => 'iap.review'],
            'VALIDATE' => ['from' => ['PENDING_VALIDATION', 'RESUBMITTED'], 'to' => 'VALIDATED', 'permission' => 'iap.approve'],
            'LOCK' => ['from' => ['VALIDATED'], 'to' => 'LOCKED', 'permission' => 'iap.activate'],
        ];
        if (! isset($map[$action])) {
            abort(404);
        }
        abort_unless($request->user()->hasPermission($map[$action]['permission']), 403);
        if ($action === 'RETURN' && trim((string) ($validated['comment'] ?? '')) === '') {
            throw ValidationException::withMessages(['comment' => ['A return reason is required.']]);
        }

        DB::transaction(function () use ($request, $period, $validated, $action, $map): void {
            $locked = IapRiskPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $this->assertLock($locked->lock_version, (int) $validated['lockVersion']);
            if (! in_array($locked->status, $map[$action]['from'], true)) {
                throw ValidationException::withMessages([
                    'status' => ["{$action} is not available while the period is {$locked->status}."],
                ]);
            }
            if ($action === 'OPEN') {
                $this->assertWeightTotal($locked);
            }
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) {
                if (! $locked->assessments()->exists()) {
                    throw ValidationException::withMessages(['assessments' => ['At least one risk assessment is required.']]);
                }
            }
            if ($action === 'VALIDATE' && $locked->submitted_by === $request->user()->id) {
                throw ValidationException::withMessages(['validator' => ['The submitter cannot validate the same period.']]);
            }
            if ($action === 'VALIDATE' && $this->runtime->boolean('baics_integration_required')) {
                $this->baicsIntegrations->assertRiskPeriodReady($locked);
            }

            $oldStatus = $locked->status;
            $newStatus = $map[$action]['to'];
            $attributes = ['status' => $newStatus, 'lock_version' => $locked->lock_version + 1];
            if ($action === 'OPEN') {
                $attributes += ['opened_at' => now(), 'opened_by' => $request->user()->id];
            } elseif (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) {
                $attributes += ['submitted_at' => now(), 'submitted_by' => $request->user()->id];
            } elseif ($action === 'VALIDATE') {
                $attributes += ['validated_at' => now(), 'validated_by' => $request->user()->id];
            } elseif ($action === 'LOCK') {
                $attributes += ['locked_at' => now(), 'locked_by' => $request->user()->id];
            }
            $locked->forceFill($attributes)->save();

            $assessmentStatus = match ($action) {
                'SUBMIT', 'RESUBMIT' => 'SUBMITTED',
                'RETURN' => 'RETURNED_FOR_REVISION',
                'VALIDATE' => 'VALIDATED',
                'LOCK' => 'LOCKED',
                default => null,
            };
            if ($assessmentStatus) {
                $assessmentAttributes = ['status' => $assessmentStatus];
                if ($action === 'RETURN') {
                    $assessmentAttributes['validation_comment'] = $validated['comment'];
                }
                if ($action === 'VALIDATE') {
                    $assessmentAttributes += ['validated_by' => $request->user()->id, 'validated_at' => now()];
                }
                $locked->assessments()->update($assessmentAttributes);
            }
            $this->event($locked, $request->user(), $action, $oldStatus, $newStatus, $validated['comment'] ?? null);
            $this->support->audit(
                $request,
                'iap.risk_period.'.strtolower($action),
                $locked,
                ['status' => $oldStatus],
                ['status' => $newStatus],
                ['comment' => $validated['comment'] ?? null],
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Risk assessment workflow updated successfully.',
            'data' => ['riskPeriod' => new IapRiskPeriodResource($this->load($period->fresh()))],
        ]);
    }

    public function destroy(Request $request, IapRiskPeriod $period): JsonResponse
    {
        $this->assertManagement($request->user());
        if (! in_array($period->status, ['DRAFT', 'LOCKED'], true)) {
            throw ValidationException::withMessages(['status' => ['Only draft or locked periods may be archived.']]);
        }
        DB::transaction(function () use ($request, $period): void {
            $period->forceFill(['is_active' => false, 'lock_version' => $period->lock_version + 1])->save();
            $this->event($period, $request->user(), 'ARCHIVE', $period->status, $period->status);
            $this->support->audit($request, 'iap.risk_period.archived', $period, null, $period->toArray());
            $period->delete();
        }, 3);

        return response()->json(['success' => true, 'message' => 'Risk assessment period archived successfully.']);
    }

    public function restore(Request $request, int $period): JsonResponse
    {
        $this->assertManagement($request->user());
        $record = IapRiskPeriod::onlyTrashed()->findOrFail($period);
        DB::transaction(function () use ($request, $record): void {
            $record->restore();
            $record->forceFill(['is_active' => true, 'lock_version' => $record->lock_version + 1])->save();
            $this->event($record, $request->user(), 'RESTORE', $record->status, $record->status);
            $this->support->audit($request, 'iap.risk_period.restored', $record, null, $record->toArray());
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Risk assessment period restored successfully.',
            'data' => ['riskPeriod' => new IapRiskPeriodResource($this->load($record))],
        ]);
    }

    /** @param array<int, array<string, mixed>> $criteria */
    private function validateCriteria(array $criteria): void
    {
        if (abs(array_sum(array_column($criteria, 'weight')) - 100) > 0.001) {
            throw ValidationException::withMessages(['criteria' => ['Criterion weights must total exactly 100%.']]);
        }
        foreach ($criteria as $criterion) {
            $this->support->masterItem((int) $criterion['criterionId'], 'IAP_RISK_CRITERION');
        }
    }

    /** @param array<int, array<string, mixed>> $criteria */
    private function syncCriteria(IapRiskPeriod $period, array $criteria): void
    {
        $period->criteria()->delete();
        foreach (array_values($criteria) as $index => $criterion) {
            IapRiskPeriodCriterion::query()->create([
                'period_id' => $period->id,
                'criterion_id' => $criterion['criterionId'],
                'weight' => $criterion['weight'],
                'display_order' => $index + 1,
            ]);
        }
    }

    private function assertWeightTotal(IapRiskPeriod $period): void
    {
        if (abs((float) $period->criteria()->sum('weight') - 100) > 0.001) {
            throw ValidationException::withMessages(['criteria' => ['Criterion weights must total exactly 100%.']]);
        }
    }

    private function assertEditablePeriod(IapRiskPeriod $period): void
    {
        if ($period->status !== 'DRAFT' || $period->assessments()->exists()) {
            throw ValidationException::withMessages(['status' => ['Only an unused draft period can be edited.']]);
        }
    }

    private function assertLock(int $current, int $provided): void
    {
        if ($current !== $provided) {
            throw ValidationException::withMessages(['lockVersion' => ['This record changed. Refresh before continuing.']]);
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
        IapRiskPeriod $period,
        User $actor,
        string $action,
        ?string $from,
        string $to,
        ?string $comment = null,
    ): void {
        IapRiskPeriodEvent::query()->create([
            'period_id' => $period->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor->id,
            'comment' => $comment,
            'period_lock_version' => $period->lock_version,
        ]);
    }

    private function load(IapRiskPeriod $period): IapRiskPeriod
    {
        return $period->load([
            'creator:id,employee_id,name,initials',
            'submitter:id,employee_id,name,initials',
            'validator:id,employee_id,name,initials',
            'criteria.criterion',
            'assessments' => fn ($query) => $query->withTrashed()->orderBy('residual_risk_score', 'desc'),
            'assessments.auditUniverseItem.responsibleOffice:id,code,name',
            'assessments.auditUniverseItem.primaryAuditArea:id,code,name',
            'assessments.assessor:id,employee_id,name,initials',
            'assessments.inherentRiskLevel',
            'assessments.residualRiskLevel',
            'assessments.scores.criterion',
            'assessments.evidence.uploader:id,employee_id,name,initials',
            'events.actor:id,employee_id,name,initials',
        ]);
    }
}
