<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iap\IapUniverseRiskAssessmentRequest;
use App\Http\Resources\IapRiskPeriodResource;
use App\Models\IapRiskEvidence;
use App\Models\IapRiskPeriod;
use App\Models\IapUniverseRiskAssessment;
use App\Models\IapUniverseRiskScore;
use App\Models\MasterListItem;
use App\Services\IapSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Provides the Audit Universe-centered view of assessment scoring and validation.
 */
class IapUniverseRiskAssessmentController extends Controller
{
    public function __construct(private readonly IapSupport $support) {}

    public function store(IapUniverseRiskAssessmentRequest $request, IapRiskPeriod $period): JsonResponse
    {
        $this->assertEditable($period);
        $validated = $request->validated();
        $calculation = $this->calculate($period, $validated['scores'], (float) $validated['controlEffectivenessPercent']);

        $assessment = DB::transaction(function () use ($request, $period, $validated, $calculation): IapUniverseRiskAssessment {
            if ($period->assessments()->withTrashed()->where('audit_universe_item_id', $validated['auditUniverseItemId'])->exists()) {
                throw ValidationException::withMessages(['auditUniverseItemId' => ['This Audit Universe subject is already assessed in this period.']]);
            }
            $assessment = IapUniverseRiskAssessment::query()->create([
                ...$this->attributes($validated, $calculation),
                'period_id' => $period->id,
                'assessed_by' => $request->user()->id,
                'status' => $period->status === 'RETURNED_FOR_REVISION' ? 'RETURNED_FOR_REVISION' : 'DRAFT',
                'lock_version' => 1,
            ]);
            $this->syncScores($assessment, $calculation['scores']);
            $this->support->audit($request, 'iap.risk_assessment.created', $assessment, null, $assessment->toArray());

            return $assessment;
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Risk assessment created successfully.',
            'data' => ['riskPeriod' => new IapRiskPeriodResource($this->loadPeriod($period->fresh()))],
        ], 201);
    }

    public function update(
        IapUniverseRiskAssessmentRequest $request,
        IapRiskPeriod $period,
        IapUniverseRiskAssessment $assessment,
    ): JsonResponse {
        $this->assertRelated($period, $assessment);
        $this->assertEditable($period);
        $this->assertOwnerOrManagement($request, $assessment);
        $validated = $request->validated();
        $calculation = $this->calculate($period, $validated['scores'], (float) $validated['controlEffectivenessPercent']);

        DB::transaction(function () use ($request, $assessment, $validated, $calculation): void {
            $locked = IapUniverseRiskAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
            if ($locked->lock_version !== (int) $validated['lockVersion']) {
                throw ValidationException::withMessages(['lockVersion' => ['This assessment changed. Refresh before saving.']]);
            }
            $duplicate = IapUniverseRiskAssessment::withTrashed()
                ->where('period_id', $locked->period_id)
                ->where('audit_universe_item_id', $validated['auditUniverseItemId'])
                ->whereKeyNot($locked->id)
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['auditUniverseItemId' => ['This Audit Universe subject is already assessed in this period.']]);
            }
            $old = $locked->toArray();
            $locked->fill([
                ...$this->attributes($validated, $calculation),
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->syncScores($locked, $calculation['scores']);
            $this->support->audit($request, 'iap.risk_assessment.updated', $locked, $old, $locked->toArray());
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Risk assessment updated successfully.',
            'data' => ['riskPeriod' => new IapRiskPeriodResource($this->loadPeriod($period->fresh()))],
        ]);
    }

    public function destroy(Request $request, IapRiskPeriod $period, IapUniverseRiskAssessment $assessment): JsonResponse
    {
        $this->assertRelated($period, $assessment);
        $this->assertEditable($period);
        $this->assertOwnerOrManagement($request, $assessment);
        $assessment->delete();
        $this->support->audit($request, 'iap.risk_assessment.archived', $assessment, null, $assessment->toArray());

        return response()->json(['success' => true, 'message' => 'Risk assessment archived successfully.']);
    }

    public function restore(Request $request, IapRiskPeriod $period, int $assessment): JsonResponse
    {
        $this->assertEditable($period);
        $record = IapUniverseRiskAssessment::onlyTrashed()
            ->where('period_id', $period->id)->findOrFail($assessment);
        $record->restore();
        $record->increment('lock_version');
        $this->support->audit($request, 'iap.risk_assessment.restored', $record, null, $record->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Risk assessment restored successfully.',
            'data' => ['riskPeriod' => new IapRiskPeriodResource($this->loadPeriod($period->fresh()))],
        ]);
    }

    public function uploadEvidence(Request $request, IapRiskPeriod $period, IapUniverseRiskAssessment $assessment): JsonResponse
    {
        $this->assertRelated($period, $assessment);
        $this->assertEditable($period);
        $this->assertOwnerOrManagement($request, $assessment);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.app(\App\Services\RuntimeConfiguration::class)->documentUploadMaxKilobytes(), 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png'],
        ]);
        $file = $validated['file'];
        $name = Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs("iap-risk-evidence/{$period->id}/{$assessment->id}", $name, 'local');
        $evidence = IapRiskEvidence::query()->create([
            'assessment_id' => $assessment->id,
            'original_file_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_extension' => strtolower($file->getClientOriginalExtension()),
            'file_size' => $file->getSize(),
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $request->user()->id,
        ]);
        $this->support->audit($request, 'iap.risk_evidence.uploaded', $evidence, null, $evidence->toArray());

        return response()->json(['success' => true, 'message' => 'Supporting evidence uploaded successfully.'], 201);
    }

    public function downloadEvidence(
        Request $request,
        IapRiskPeriod $period,
        IapUniverseRiskAssessment $assessment,
        IapRiskEvidence $evidence,
    ): StreamedResponse {
        $this->assertRelated($period, $assessment);
        abort_unless($evidence->assessment_id === $assessment->id, 404);
        abort_unless(Storage::disk('local')->exists($evidence->storage_path), 404);

        return Storage::disk('local')->download($evidence->storage_path, $evidence->original_file_name);
    }

    public function destroyEvidence(
        Request $request,
        IapRiskPeriod $period,
        IapUniverseRiskAssessment $assessment,
        IapRiskEvidence $evidence,
    ): JsonResponse {
        $this->assertRelated($period, $assessment);
        $this->assertEditable($period);
        $this->assertOwnerOrManagement($request, $assessment);
        abort_unless($evidence->assessment_id === $assessment->id, 404);
        $evidence->delete();
        $this->support->audit($request, 'iap.risk_evidence.archived', $evidence, null, $evidence->toArray());

        return response()->json(['success' => true, 'message' => 'Supporting evidence archived successfully.']);
    }

    /** @return array<string, mixed> */
    private function calculate(IapRiskPeriod $period, array $scores, float $controlEffectiveness): array
    {
        $criteria = $period->criteria()->with('criterion')->get();
        $provided = collect($scores)->keyBy(fn ($score) => (int) $score['criterionId']);
        if ($criteria->count() !== $provided->count()
            || $criteria->contains(fn ($criterion) => ! $provided->has($criterion->criterion_id))) {
            throw ValidationException::withMessages(['scores' => ['Score every criterion configured for this period exactly once.']]);
        }
        $normalized = $criteria->map(function ($criterion) use ($provided): array {
            $score = $provided->get($criterion->criterion_id);

            return [
                'criterion_id' => $criterion->criterion_id,
                'criterion_weight' => (float) $criterion->weight,
                'rating' => (float) $score['rating'],
                'weighted_score' => round((float) $score['rating'] * (float) $criterion->weight / 100, 2),
                'comment' => $score['comment'] ?? null,
            ];
        })->values()->all();
        $inherent = round((float) collect($normalized)->sum('weighted_score'), 2);
        $residual = round($inherent * (1 - ($controlEffectiveness / 100)), 2);

        return [
            'inherent' => $inherent,
            'residual' => $residual,
            'inherent_level_id' => $this->riskLevel($inherent)->id,
            'residual_level_id' => $this->riskLevel($residual)->id,
            'scores' => $normalized,
        ];
    }

    private function riskLevel(float $score): MasterListItem
    {
        $code = $score >= 4 ? 'CRITICAL' : ($score >= 3 ? 'HIGH' : ($score >= 2 ? 'MEDIUM' : 'LOW'));

        return MasterListItem::query()->where('code', $code)
            ->whereHas('masterList', fn ($query) => $query->where('code', 'RISK_LEVEL'))
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function attributes(array $validated, array $calculation): array
    {
        return [
            'audit_universe_item_id' => $validated['auditUniverseItemId'],
            'assessment_date' => $validated['assessmentDate'],
            'control_effectiveness_percent' => $validated['controlEffectivenessPercent'],
            'control_effectiveness_notes' => $validated['controlEffectivenessNotes'],
            'justification' => $validated['justification'],
            'evidence_summary' => $validated['evidenceSummary'] ?? null,
            'inherent_risk_score' => $calculation['inherent'],
            'residual_risk_score' => $calculation['residual'],
            'inherent_risk_level_id' => $calculation['inherent_level_id'],
            'residual_risk_level_id' => $calculation['residual_level_id'],
        ];
    }

    private function syncScores(IapUniverseRiskAssessment $assessment, array $scores): void
    {
        $assessment->scores()->delete();
        foreach ($scores as $score) {
            IapUniverseRiskScore::query()->create(['assessment_id' => $assessment->id, ...$score]);
        }
    }

    private function assertEditable(IapRiskPeriod $period): void
    {
        if (! in_array($period->status, ['OPEN', 'RETURNED_FOR_REVISION'], true)) {
            throw ValidationException::withMessages(['status' => ['Assessments may only be changed in an open or returned period.']]);
        }
    }

    private function assertRelated(IapRiskPeriod $period, IapUniverseRiskAssessment $assessment): void
    {
        abort_unless($assessment->period_id === $period->id, 404);
    }

    private function assertOwnerOrManagement(Request $request, IapUniverseRiskAssessment $assessment): void
    {
        abort_unless(
            $assessment->assessed_by === $request->user()->id
            || $request->user()->hasPermission('iap.manage_universe'),
            403,
        );
    }

    private function loadPeriod(IapRiskPeriod $period): IapRiskPeriod
    {
        return $period->load([
            'creator:id,employee_id,name,initials', 'submitter:id,employee_id,name,initials',
            'validator:id,employee_id,name,initials', 'criteria.criterion',
            'assessments' => fn ($query) => $query->withTrashed()->orderBy('residual_risk_score', 'desc'),
            'assessments.auditUniverseItem.responsibleOffice:id,code,name',
            'assessments.auditUniverseItem.primaryAuditArea:id,code,name',
            'assessments.assessor:id,employee_id,name,initials',
            'assessments.inherentRiskLevel', 'assessments.residualRiskLevel',
            'assessments.scores.criterion', 'assessments.evidence.uploader:id,employee_id,name,initials',
            'events.actor:id,employee_id,name,initials',
        ]);
    }
}
