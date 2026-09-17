<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iap\IapRiskAssessmentRequest;
use App\Models\IapRiskAssessment;
use App\Models\IapRiskAssessmentScore;
use App\Models\InternalAuditPlan;
use App\Models\MasterListItem;
use App\Services\IapPlanGuard;
use App\Services\IapSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Scores Audit Universe subjects and manages evidence-backed assessment records.
 */
class IapRiskAssessmentController extends Controller
{
    public function __construct(
        private readonly IapPlanGuard $guard,
        private readonly IapSupport $support,
    ) {}

    public function index(Request $request, InternalAuditPlan $plan): JsonResponse
    {
        $this->guard->assertCanView($request->user(), $plan);

        $assessments = IapRiskAssessment::query()
            ->when($request->boolean('includeArchived'), fn ($query) => $query->withTrashed())
            ->where('plan_id', $plan->id)
            ->with([
                'scores.criterion',
                'office:id,code,name',
                'auditArea:id,code,name',
                'calculatedRiskLevel',
                'overrideRiskLevel',
                'finalRiskLevel',
            ])
            ->orderBy('assessment_date', 'desc')
            ->orderBy('id')
            ->get()
            ->map(fn (IapRiskAssessment $risk) => $this->payload($risk))
            ->values();

        return response()->json([
            'success' => true,
            'data' => ['riskAssessments' => $assessments],
        ]);
    }

    public function store(IapRiskAssessmentRequest $request, InternalAuditPlan $plan): JsonResponse
    {
        $this->guard->assertEditable($request->user(), $plan);
        $calculation = $this->calculation($request->validated('scores'));
        $this->assertCoverage(
            (int) $request->validated('officeId'),
            (int) $request->validated('auditAreaId'),
        );

        $risk = DB::transaction(function () use ($request, $plan, $calculation): IapRiskAssessment {
            $lockedPlan = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->guard->assertEditable($request->user(), $lockedPlan);
            if ($request->filled('lockVersion')) {
                $this->guard->assertLockVersion($lockedPlan, (int) $request->validated('lockVersion'));
            }

            $risk = IapRiskAssessment::withTrashed()
                ->where('plan_id', $plan->id)
                ->where('office_id', $request->validated('officeId'))
                ->where('audit_area_id', $request->validated('auditAreaId'))
                ->lockForUpdate()
                ->first();

            if ($risk && ! $risk->trashed()) {
                throw ValidationException::withMessages([
                    'auditAreaId' => ['This office and audit area already have a risk assessment in the plan.'],
                ]);
            }

            $attributes = $this->attributes($request, $plan, $calculation);
            if ($risk?->trashed()) {
                $risk->restore();
                $risk->fill($attributes)->save();
            } else {
                $risk = IapRiskAssessment::query()->create($attributes);
            }

            $this->replaceScores($risk, $request->validated('scores'));
            $this->incrementPlanLock($lockedPlan);
            $this->support->audit($request, 'iap.risk_assessment.created', $risk, null, $risk->toArray());

            return $risk;
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Risk assessment saved successfully.',
            'data' => ['riskAssessment' => $this->payload($risk)],
        ], 201);
    }

    public function update(
        IapRiskAssessmentRequest $request,
        InternalAuditPlan $plan,
        IapRiskAssessment $assessment,
    ): JsonResponse {
        $this->assertBelongsToPlan($plan, $assessment);
        $this->guard->assertEditable($request->user(), $plan);
        $calculation = $this->calculation($request->validated('scores'));
        $this->assertCoverage(
            (int) $request->validated('officeId'),
            (int) $request->validated('auditAreaId'),
        );

        DB::transaction(function () use ($request, $plan, $assessment, $calculation): void {
            $lockedPlan = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $locked = IapRiskAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
            $this->guard->assertEditable($request->user(), $lockedPlan);
            if ($request->filled('lockVersion')) {
                $this->guard->assertLockVersion($lockedPlan, (int) $request->validated('lockVersion'));
            }

            $duplicate = IapRiskAssessment::query()
                ->where('plan_id', $plan->id)
                ->where('office_id', $request->validated('officeId'))
                ->where('audit_area_id', $request->validated('auditAreaId'))
                ->where('id', '<>', $locked->id)
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'auditAreaId' => ['This office and audit area already have a risk assessment in the plan.'],
                ]);
            }

            $old = $locked->load('scores')->toArray();
            $locked->fill([
                ...$this->attributes($request, $plan, $calculation),
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->replaceScores($locked, $request->validated('scores'));
            $this->incrementPlanLock($lockedPlan);
            $this->support->audit(
                $request,
                'iap.risk_assessment.updated',
                $locked,
                $old,
                $locked->fresh('scores')->toArray(),
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Risk assessment updated successfully.',
            'data' => ['riskAssessment' => $this->payload($assessment->fresh())],
        ]);
    }

    public function destroy(
        Request $request,
        InternalAuditPlan $plan,
        IapRiskAssessment $assessment,
    ): JsonResponse {
        $this->assertBelongsToPlan($plan, $assessment);
        $this->guard->assertEditable($request->user(), $plan);

        if ($assessment->engagements()->exists()) {
            throw ValidationException::withMessages([
                'riskAssessment' => ['Remove this assessment from its proposed engagements before archiving it.'],
            ]);
        }

        DB::transaction(function () use ($request, $plan, $assessment): void {
            $lockedPlan = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $old = $assessment->toArray();
            $assessment->delete();
            $this->incrementPlanLock($lockedPlan);
            $this->support->audit($request, 'iap.risk_assessment.archived', $assessment, $old, null);
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Risk assessment archived successfully.',
        ]);
    }

    public function restore(Request $request, InternalAuditPlan $plan, int $assessment): JsonResponse
    {
        $this->guard->assertEditable($request->user(), $plan);
        $record = IapRiskAssessment::onlyTrashed()
            ->where('plan_id', $plan->id)
            ->findOrFail($assessment);

        DB::transaction(function () use ($request, $plan, $record): void {
            $lockedPlan = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $record->restore();
            $record->forceFill(['lock_version' => $record->lock_version + 1])->save();
            $this->incrementPlanLock($lockedPlan);
            $this->support->audit($request, 'iap.risk_assessment.restored', $record, null, $record->toArray());
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Risk assessment restored successfully.',
            'data' => ['riskAssessment' => $this->payload($record)],
        ]);
    }

    /** @param list<array<string, mixed>> $scores
     * @return array{score: float, level_id: int}
     */
    private function calculation(array $scores): array
    {
        $weight = collect($scores)->sum(fn ($score) => (float) $score['weight']);
        if (abs($weight - 100.0) > 0.01) {
            throw ValidationException::withMessages([
                'scores' => ['Risk criterion weights must total exactly 100 percent.'],
            ]);
        }

        foreach ($scores as $score) {
            $this->support->masterItem((int) $score['criterionId'], 'IAP_RISK_CRITERION');
        }

        $total = round((float) collect($scores)->sum(
            fn ($score) => ((float) $score['rating'] * (float) $score['weight']) / 100,
        ), 2);
        $levelCode = match (true) {
            $total < 2 => 'LOW',
            $total < 3 => 'MEDIUM',
            $total < 4 => 'HIGH',
            default => 'CRITICAL',
        };
        $level = MasterListItem::query()
            ->where('code', $levelCode)
            ->whereHas('masterList', fn ($query) => $query->where('code', 'RISK_LEVEL'))
            ->firstOrFail();

        return ['score' => $total, 'level_id' => $level->id];
    }

    /** @param array{score: float, level_id: int} $calculation
     * @return array<string, mixed>
     */
    private function attributes(
        IapRiskAssessmentRequest $request,
        InternalAuditPlan $plan,
        array $calculation,
    ): array {
        $overrideId = $request->validated('overrideRiskLevelId');
        if ($overrideId !== null) {
            $this->support->masterItem((int) $overrideId, 'RISK_LEVEL');
        }

        return [
            'plan_id' => $plan->id,
            'office_id' => $request->validated('officeId'),
            'audit_area_id' => $request->validated('auditAreaId'),
            'assessed_by' => $request->user()->id,
            'assessment_date' => $request->validated('assessmentDate'),
            'last_audit_date' => $request->validated('lastAuditDate'),
            'inherent_risk_notes' => $request->validated('inherentRiskNotes'),
            'control_environment_notes' => $request->validated('controlEnvironmentNotes'),
            'total_weighted_score' => $calculation['score'],
            'calculated_risk_level_id' => $calculation['level_id'],
            'override_risk_level_id' => $overrideId,
            'override_reason' => $overrideId ? $request->validated('overrideReason') : null,
            'final_risk_level_id' => $overrideId ?: $calculation['level_id'],
            'justification' => $request->validated('justification'),
        ];
    }

    /** @param list<array<string, mixed>> $scores */
    private function replaceScores(IapRiskAssessment $risk, array $scores): void
    {
        $risk->scores()->delete();

        foreach ($scores as $score) {
            IapRiskAssessmentScore::query()->create([
                'risk_assessment_id' => $risk->id,
                'risk_criterion_id' => $score['criterionId'],
                'criterion_weight' => $score['weight'],
                'rating' => $score['rating'],
                'weighted_score' => round(
                    ((float) $score['rating'] * (float) $score['weight']) / 100,
                    4,
                ),
                'comment' => $score['comment'] ?? null,
            ]);
        }
    }

    private function assertCoverage(int $officeId, int $auditAreaId): void
    {
        $covered = DB::table('audit_area_office')
            ->where('office_id', $officeId)
            ->where('audit_area_id', $auditAreaId)
            ->exists();

        if (! $covered) {
            throw ValidationException::withMessages([
                'auditAreaId' => ['The selected audit area is not linked to this office.'],
            ]);
        }
    }

    private function assertBelongsToPlan(InternalAuditPlan $plan, IapRiskAssessment $assessment): void
    {
        if ($assessment->plan_id !== $plan->id) {
            abort(404);
        }
    }

    private function incrementPlanLock(InternalAuditPlan $plan): void
    {
        $plan->forceFill(['lock_version' => $plan->lock_version + 1])->save();
    }

    /** @return array<string, mixed> */
    private function payload(IapRiskAssessment $risk): array
    {
        $risk->load([
            'scores.criterion',
            'office:id,code,name',
            'auditArea:id,code,name',
            'calculatedRiskLevel',
            'overrideRiskLevel',
            'finalRiskLevel',
        ]);

        return [
            'id' => $risk->id,
            'officeId' => $risk->office_id,
            'office' => [
                'id' => $risk->office?->id,
                'code' => $risk->office?->code,
                'name' => $risk->office?->name,
            ],
            'auditAreaId' => $risk->audit_area_id,
            'auditArea' => [
                'id' => $risk->auditArea?->id,
                'code' => $risk->auditArea?->code,
                'name' => $risk->auditArea?->name,
            ],
            'assessedBy' => $risk->assessed_by,
            'assessmentDate' => $risk->assessment_date?->toDateString(),
            'lastAuditDate' => $risk->last_audit_date?->toDateString(),
            'inherentRiskNotes' => $risk->inherent_risk_notes,
            'controlEnvironmentNotes' => $risk->control_environment_notes,
            'totalWeightedScore' => (float) $risk->total_weighted_score,
            'calculatedRiskLevel' => [
                'id' => $risk->calculatedRiskLevel?->id,
                'code' => $risk->calculatedRiskLevel?->code,
                'label' => $risk->calculatedRiskLevel?->label,
            ],
            'overrideRiskLevelId' => $risk->override_risk_level_id,
            'overrideRiskLevel' => $risk->overrideRiskLevel ? [
                'id' => $risk->overrideRiskLevel->id,
                'code' => $risk->overrideRiskLevel->code,
                'label' => $risk->overrideRiskLevel->label,
            ] : null,
            'overrideReason' => $risk->override_reason,
            'finalRiskLevel' => [
                'id' => $risk->finalRiskLevel?->id,
                'code' => $risk->finalRiskLevel?->code,
                'label' => $risk->finalRiskLevel?->label,
            ],
            'justification' => $risk->justification,
            'lockVersion' => $risk->lock_version,
            'isArchived' => $risk->trashed(),
            'scores' => $risk->scores->map(fn ($score) => [
                'id' => $score->id,
                'criterionId' => $score->risk_criterion_id,
                'criterion' => [
                    'id' => $score->criterion?->id,
                    'code' => $score->criterion?->code,
                    'label' => $score->criterion?->label,
                    'description' => $score->criterion?->description,
                ],
                'weight' => (float) $score->criterion_weight,
                'rating' => (float) $score->rating,
                'weightedScore' => (float) $score->weighted_score,
                'comment' => $score->comment,
            ])->values(),
        ];
    }
}
