<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Http\Resources\IapPlanResource;
use App\Models\IapPrioritizationRun;
use App\Models\InternalAuditPlan;
use App\Services\IapPlanGuard;
use App\Services\IapSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Imports finalized prioritization decisions into an Annual Plan without duplicates.
 */
class IapPlanPrioritizationController extends Controller
{
    public function __construct(
        private readonly IapPlanGuard $guard,
        private readonly IapSupport $support,
    ) {}

    public function update(Request $request, InternalAuditPlan $plan): JsonResponse
    {
        $this->guard->assertEditable($request->user(), $plan);
        $validated = $request->validate([
            'prioritizationRunId' => [
                'required',
                'integer',
                'exists:iap_prioritization_runs,id',
            ],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);

        $run = IapPrioritizationRun::query()
            ->whereKey($validated['prioritizationRunId'])
            ->where('status', 'FINALIZED')
            ->where('is_active', true)
            ->first();
        if (! $run) {
            throw ValidationException::withMessages([
                'prioritizationRunId' => ['Select an active, finalized prioritization run.'],
            ]);
        }
        $run->loadMissing('riskPeriod');
        $validatedItems = $run->items()
            ->whereHas('riskAssessment', fn ($assessment) => $assessment
                ->whereIn('status', ['VALIDATED', 'LOCKED'])
                ->whereNull('deleted_at'))
            ->count();
        if (! $run->riskPeriod
            || ! in_array($run->riskPeriod->status, ['VALIDATED', 'LOCKED'], true)
            || $validatedItems !== $run->items()->count()) {
            throw ValidationException::withMessages([
                'prioritizationRunId' => [
                    'The finalized prioritization must retain validated assessments for every ranked subject.',
                ],
            ]);
        }

        DB::transaction(function () use ($request, $plan, $run, $validated): void {
            $locked = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->guard->assertEditable($request->user(), $locked);
            $this->guard->assertLockVersion($locked, (int) $validated['lockVersion']);

            if ($locked->prioritization_run_id !== null
                && $locked->prioritization_run_id !== $run->id
                && $locked->engagements()->whereNotNull('prioritization_item_id')->exists()) {
                throw ValidationException::withMessages([
                    'prioritizationRunId' => [
                        'This source cannot be changed after prioritized subjects have been imported.',
                    ],
                ]);
            }

            $old = $locked->toArray();
            $locked->forceFill([
                'prioritization_run_id' => $run->id,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->support->audit(
                $request,
                'iap.plan.prioritization_linked',
                $locked,
                $old,
                $locked->toArray(),
                ['prioritization_run_id' => $run->id],
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Finalized prioritization connected to the annual plan.',
            'data' => [
                'plan' => new IapPlanResource(
                    $plan->fresh()->load([
                        'prioritizationRun.riskPeriod',
                        'prioritizationRun.items',
                        'engagements.prioritizationItem',
                    ]),
                ),
            ],
        ]);
    }
}
