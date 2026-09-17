<?php

namespace App\Services;

use App\Models\IapBaicsAssessment;
use App\Models\IapBaicsControl;
use App\Models\IapBaicsControlVersion;
use App\Models\IapBaicsInterimAnalysis;
use App\Models\IapBaicsInterimAnalysisVersion;
use App\Models\IapBaicsMethod;
use App\Models\IapBaicsReport;
use App\Models\IapBaicsReportVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** BAICS-3 Control Universe and Baseline Assessment Report services. */
class IapBaicsControlUniverseService
{
    public function __construct(
        private readonly IapSupport $support,
        private readonly NotificationService $notifications,
        private readonly RuntimeConfiguration $runtime,
        private readonly IapBaicsAssessmentService $assessments,
    ) {}

    public function loadControls(IapBaicsAssessment $assessment): IapBaicsAssessment
    {
        return $assessment->load([
            'controls.scopeItem.office:id,code,name', 'controls.scopeItem.auditArea:id,code,name',
            'controls.scopeItem.auditFocus:id,code,name', 'controls.scopeItem.auditUniverseItem:id,subject_code,name',
            'controls.component', 'controls.controlOwnerOffice:id,code,name',
            'controls.controlOwner:id,employee_id,name,initials,position',
            'controls.preparer:id,employee_id,name,initials,position', 'controls.reviewer:id,employee_id,name,initials,position',
            'controls.approver:id,employee_id,name,initials,position', 'controls.methods',
            'controls.evidenceLinks.documentVersion.document', 'controls.versions.creator:id,employee_id,name,initials',
            'interimAnalyses.preparer:id,employee_id,name,initials,position', 'interimAnalyses.reviewer:id,employee_id,name,initials,position',
            'interimAnalyses.approver:id,employee_id,name,initials,position', 'reports.controls', 'reports.interimAnalyses',
        ]);
    }

    /** @return array<string, mixed> */
    public function readiness(IapBaicsAssessment $assessment): array
    {
        $assessment->loadMissing(['controls.methods', 'controls.evidenceLinks', 'controls.component', 'interimAnalyses']);
        $controls = $assessment->controls;
        $controlItems = $controls->map(fn (IapBaicsControl $control): array => $this->controlReadiness($control))->values()->all();
        $approvedControls = $controls->where('status', 'APPROVED')->count();
        $classified = $controls->filter(fn (IapBaicsControl $control): bool => in_array($control->control_status, ['Control Gap', 'Deficiency', 'Breakdown'], true));
        return [
            'ready' => $controls->isNotEmpty() && $controls->every(fn (IapBaicsControl $control): bool => $this->controlReadiness($control)['ready']) && $assessment->interimAnalyses->every(fn (IapBaicsInterimAnalysis $analysis): bool => $analysis->status === 'APPROVED'),
            'controlCount' => $controls->count(), 'approvedControlCount' => $approvedControls,
            'classifiedControlCount' => $classified->count(), 'openClassificationCount' => $classified->whereNotIn('status', ['APPROVED'])->count(),
            'interimAnalysisCount' => $assessment->interimAnalyses->count(),
            'approvedInterimAnalysisCount' => $assessment->interimAnalyses->where('status', 'APPROVED')->count(),
            'controls' => $controlItems,
        ];
    }

    public function saveControl(Request $request, IapBaicsAssessment $assessment, array $data, ?IapBaicsControl $control = null): IapBaicsControl
    {
        $this->assertAssessmentEditable($assessment);
        $this->assertEditable($control);
        if ($control && (int) $control->assessment_id !== (int) $assessment->id) abort(404);
        $scope = $assessment->scopeItems()->findOrFail($data['scopeItemId']);
        $component = ! empty($data['componentId']) ? $assessment->components()->findOrFail($data['componentId']) : null;
        $this->assertOfficeScope($request->user(), (int) $data['controlOwnerOfficeId']);
        if ((int) $data['reviewerId'] === (int) $request->user()->id) {
            throw ValidationException::withMessages(['reviewerId' => ['The control preparer cannot be its independent reviewer.']]);
        }
        if (in_array($data['controlStatus'], ['Control Gap', 'Deficiency', 'Breakdown'], true)) {
            $classification = $data['deficiencyClassification'] ?? null;
            $reason = collect([$data['gapDetails'] ?? null, $data['breakdownDetails'] ?? null, $data['contradictionDetails'] ?? null])->filter()->isNotEmpty();
            if (! $classification || ! $reason) throw ValidationException::withMessages(['deficiencyClassification' => ['A gap, deficiency, or breakdown requires a classification and documented basis.']]);
        }
        $attributes = $this->controlAttributes($data);
        $saved = DB::transaction(function () use ($request, $assessment, $control, $scope, $component, $attributes, $data): IapBaicsControl {
            if ($control) {
                $this->assertLock($control->lock_version, (int) ($data['lockVersion'] ?? 0));
                $control->forceFill([...$attributes, 'scope_item_id' => $scope->id, 'component_id' => $component?->id, 'lock_version' => $control->lock_version + 1])->save();
                $this->syncTraceability($control, $data, $request->user());
                $this->controlSnapshot($control, $request->user(), 'Control draft updated');
                $this->support->audit($request, 'iap.baics.control.updated', $control, null, $this->controlValues($control));
                return $control->fresh();
            }
            $control = $assessment->controls()->create([...$attributes, 'scope_item_id' => $scope->id, 'component_id' => $component?->id, 'status' => 'DRAFT', 'prepared_by' => $request->user()->id, 'reviewer_id' => $data['reviewerId'], 'version_number' => 1, 'lock_version' => 1, 'is_current_revision' => true]);
            $this->syncTraceability($control, $data, $request->user());
            $this->controlSnapshot($control, $request->user(), 'Control created');
            $this->support->audit($request, 'iap.baics.control.created', $control, null, $this->controlValues($control));
            return $control;
        }, 3);
        return $this->loadControl($saved);
    }

    public function transitionControl(Request $request, IapBaicsControl $control, string $action, ?string $comment = null): IapBaicsControl
    {
        $action = strtoupper($action);
        $map = ['SUBMIT' => [['DRAFT', 'RETURNED'], 'PENDING_REVIEW'], 'RETURN' => [['PENDING_REVIEW'], 'RETURNED'], 'APPROVE' => [['PENDING_REVIEW'], 'APPROVED']];
        abort_unless(isset($map[$action]), 404);
        $control->loadMissing('assessment', 'methods', 'evidenceLinks');
        $this->assertAssessmentVisible($request->user(), $control->assessment);
        $this->assertLock($control->lock_version, (int) $request->input('lockVersion'));
        if (! in_array($control->status, $map[$action][0], true)) throw ValidationException::withMessages(['status' => ["{$action} is not available while this control is {$control->status}."]]);
        if ($action === 'RETURN' && blank(trim((string) $comment))) throw ValidationException::withMessages(['comment' => ['A return reason is required.']]);
        if ($action === 'APPROVE') {
            $this->assertControlReady($control);
            if ((int) $request->user()->id === (int) $control->prepared_by) throw ValidationException::withMessages(['approver' => ['The control preparer cannot approve the same control.']]);
            if (! $control->reviewer_id || (int) $request->user()->id !== (int) $control->reviewer_id) throw ValidationException::withMessages(['reviewer' => ['The assigned independent reviewer must approve this control.']]);
        }
        $old = $control->status;
        $control->forceFill(['status' => $map[$action][1], 'lock_version' => $control->lock_version + 1, ...($action === 'RETURN' ? ['reviewed_at' => now(), 'reviewer_id' => $request->user()->id] : []), ...($action === 'APPROVE' ? ['approved_by' => $request->user()->id, 'approved_at' => now(), 'immutable_at' => now(), 'version_number' => $control->version_number + 1] : [])])->save();
        $this->controlSnapshot($control, $request->user(), $comment ?: $action);
        $this->support->audit($request, 'iap.baics.control.'.strtolower($action), $control, ['status' => $old], ['status' => $control->status], ['comment' => $comment]);
        $this->notify($control->assessment, $request->user(), 'BAICS_CONTROL_WORKFLOW', "Control {$action}", "{$control->control_code} moved to {$control->status}.");
        return $this->loadControl($control->fresh());
    }

    public function saveInterimAnalysis(Request $request, IapBaicsAssessment $assessment, array $data, ?IapBaicsInterimAnalysis $analysis = null): IapBaicsInterimAnalysis
    {
        $this->assertAssessmentEditable($assessment);
        if ($analysis && ((int) $analysis->assessment_id !== (int) $assessment->id || ! in_array($analysis->status, ['DRAFT', 'RETURNED'], true))) throw ValidationException::withMessages(['status' => ['Only draft or returned interim analyses can be changed.']]);
        if ((int) $request->user()->id === (int) $data['reviewerId']) throw ValidationException::withMessages(['reviewerId' => ['The preparer cannot be the independent interim-analysis reviewer.']]);
        $attributes = ['analysis_code' => $data['analysisCode'], 'title' => $data['title'], 'analysis_period_start' => $data['analysisPeriodStart'] ?? null, 'analysis_period_end' => $data['analysisPeriodEnd'] ?? null, 'analysis_narrative' => $data['analysisNarrative'], 'findings_summary' => $data['findingsSummary'] ?? null, 'recommendations_summary' => $data['recommendationsSummary'] ?? null, 'limitations' => $data['limitations'] ?? null, 'source_manifest' => $data['sourceManifest'] ?? [], 'reviewer_id' => $data['reviewerId']];
        if ($analysis) {
            $this->assertLock($analysis->lock_version, (int) ($data['lockVersion'] ?? 0));
            $analysis->forceFill([...$attributes, 'lock_version' => $analysis->lock_version + 1])->save();
        } else {
            $analysis = $assessment->interimAnalyses()->create([...$attributes, 'prepared_by' => $request->user()->id, 'status' => 'DRAFT', 'version_number' => 1, 'lock_version' => 1]);
        }
        $this->interimSnapshot($analysis, $request->user(), $analysis->exists ? 'Interim analysis saved' : 'Interim analysis created');
        $this->support->audit($request, 'iap.baics.interim_analysis.saved', $analysis, null, $analysis->only(['id', 'analysis_code', 'status', 'version_number']));
        return $analysis->fresh()->load(['preparer:id,employee_id,name,initials,position', 'reviewer:id,employee_id,name,initials,position', 'approver:id,employee_id,name,initials,position']);
    }

    public function transitionInterimAnalysis(Request $request, IapBaicsInterimAnalysis $analysis, string $action, ?string $comment = null): IapBaicsInterimAnalysis
    {
        $map = ['SUBMIT' => [['DRAFT', 'RETURNED'], 'PENDING_REVIEW'], 'RETURN' => [['PENDING_REVIEW'], 'RETURNED'], 'APPROVE' => [['PENDING_REVIEW'], 'APPROVED']];
        $action = strtoupper($action); abort_unless(isset($map[$action]), 404);
        $analysis->loadMissing('assessment'); $this->assertAssessmentVisible($request->user(), $analysis->assessment);
        if (! in_array($analysis->status, $map[$action][0], true)) throw ValidationException::withMessages(['status' => ["{$action} is not available while this analysis is {$analysis->status}."]]);
        if ($action === 'RETURN' && blank(trim((string) $comment))) throw ValidationException::withMessages(['comment' => ['A return reason is required.']]);
        if ($action === 'APPROVE') {
            if ((int) $request->user()->id === (int) $analysis->prepared_by || (int) $request->user()->id !== (int) $analysis->reviewer_id) throw ValidationException::withMessages(['reviewerId' => ['The independent reviewer must approve the interim analysis.']]);
        }
        $old = $analysis->status; $analysis->forceFill(['status' => $map[$action][1], 'lock_version' => $analysis->lock_version + 1, ...($action === 'RETURN' ? ['reviewer_id' => $request->user()->id, 'reviewed_at' => now()] : []), ...($action === 'APPROVE' ? ['approved_by' => $request->user()->id, 'approved_at' => now(), 'immutable_at' => now(), 'version_number' => $analysis->version_number + 1] : [])])->save();
        $this->interimSnapshot($analysis, $request->user(), $comment ?: $action); $this->support->audit($request, 'iap.baics.interim_analysis.'.strtolower($action), $analysis, ['status' => $old], ['status' => $analysis->status], ['comment' => $comment]);
        return $analysis->fresh()->load(['preparer:id,employee_id,name,initials,position', 'reviewer:id,employee_id,name,initials,position', 'approver:id,employee_id,name,initials,position']);
    }

    public function loadReports(IapBaicsAssessment $assessment): IapBaicsAssessment
    {
        return $assessment->load(['reports.controls.component', 'reports.interimAnalyses', 'reports.preparer:id,employee_id,name,initials,position', 'reports.reviewer:id,employee_id,name,initials,position', 'reports.approver:id,employee_id,name,initials,position', 'reports.issuer:id,employee_id,name,initials,position', 'reports.versions.creator:id,employee_id,name,initials']);
    }

    public function saveReport(Request $request, IapBaicsAssessment $assessment, array $data, ?IapBaicsReport $report = null): IapBaicsReport
    {
        $this->assertAssessmentVisible($request->user(), $assessment);
        if ($report && ((int) $report->assessment_id !== (int) $assessment->id || ! in_array($report->status, ['DRAFT', 'RETURNED'], true))) throw ValidationException::withMessages(['status' => ['Only draft or returned BARs can be changed.']]);
        $controlIds = collect($data['controlIds'])->map(fn ($id): int => (int) $id)->unique()->values();
        $controls = IapBaicsControl::query()->where('assessment_id', $assessment->id)->whereIn('id', $controlIds)->where('is_current_revision', true)->with(['component', 'methods', 'evidenceLinks.documentVersion'])->get();
        if ($controls->count() !== $controlIds->count()) throw ValidationException::withMessages(['controlIds' => ['Every selected control must belong to the BAICS cycle.']]);
        $analysisIds = collect($data['interimAnalysisIds'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values();
        $analyses = $assessment->interimAnalyses()->whereIn('id', $analysisIds)->get();
        if ($analyses->count() !== $analysisIds->count()) throw ValidationException::withMessages(['interimAnalysisIds' => ['Every selected interim analysis must belong to the BAICS cycle.']]);
        $attributes = ['title' => $data['title'], 'executive_summary' => $data['executiveSummary'], 'objectives_scope_methodology' => $data['objectivesScopeMethodology'], 'overall_findings' => $data['overallFindings'], 'control_gap_summary' => $data['controlGapSummary'], 'recommendations_summary' => $data['recommendationsSummary'] ?? null, 'limitations_exceptions' => $data['limitationsExceptions'] ?? null, 'reviewer_id' => $data['reviewerId']];
        $isNew = $report === null;
        $saved = DB::transaction(function () use ($request, $assessment, $report, $attributes, $controls, $analyses, $data, $isNew): IapBaicsReport {
            if ($report) { $this->assertLock($report->lock_version, (int) ($data['lockVersion'] ?? 0)); $report->forceFill([...$attributes, 'version_number' => $report->version_number + 1, 'lock_version' => $report->lock_version + 1])->save(); }
            else { $report = $assessment->reports()->create([...$attributes, 'report_code' => $this->nextReportCode($assessment), 'status' => 'DRAFT', 'prepared_by' => $request->user()->id, 'version_number' => 1, 'lock_version' => 1, 'is_current_revision' => true]); }
            $report->controls()->sync($controls->pluck('id')); $report->interimAnalyses()->sync($analyses->pluck('id'));
            $report->source_manifest = $this->sourceManifest($assessment, $controls, $analyses); $report->save();
            $this->reportSnapshot($report, $request->user(), $isNew ? 'BAR created' : 'BAR draft saved');
            $this->support->audit($request, 'iap.baics.report.saved', $report, null, ['reportCode' => $report->report_code, 'controlCount' => $controls->count(), 'interimAnalysisCount' => $analyses->count()]);
            return $report;
        }, 3);
        return $this->loadReport($saved);
    }

    public function transitionReport(Request $request, IapBaicsReport $report, string $action, ?string $comment = null): IapBaicsReport
    {
        $action = strtoupper($action); $map = ['SUBMIT' => [['DRAFT', 'RETURNED'], 'PENDING_REVIEW'], 'RETURN' => [['PENDING_REVIEW'], 'RETURNED'], 'APPROVE' => [['PENDING_REVIEW'], 'APPROVED'], 'ISSUE' => [['APPROVED'], 'ISSUED'], 'SUPERSEDE' => [['ISSUED'], 'SUPERSEDED']]; abort_unless(isset($map[$action]), 404);
        $report->loadMissing('assessment', 'controls.methods', 'controls.evidenceLinks', 'interimAnalyses'); $this->assertAssessmentVisible($request->user(), $report->assessment);
        if (! in_array($report->status, $map[$action][0], true)) throw ValidationException::withMessages(['status' => ["{$action} is not available while this BAR is {$report->status}."]]);
        if ($action === 'RETURN' && blank(trim((string) $comment))) throw ValidationException::withMessages(['comment' => ['A return reason is required.']]);
        if ($action === 'SUBMIT') $this->assertReportReady($report);
        if ($action === 'APPROVE') { $this->assertReportReady($report); if ((int) $request->user()->id === (int) $report->prepared_by || (int) $request->user()->id !== (int) $report->reviewer_id) throw ValidationException::withMessages(['reviewerId' => ['The independent reviewer must approve the BAR.']]); }
        if ($action === 'ISSUE' && (int) $request->user()->id === (int) $report->approved_by) throw ValidationException::withMessages(['issuer' => ['The approving authority cannot issue the same BAR.']]);
        $old = $report->status; $report->forceFill(['status' => $map[$action][1], 'version_number' => $report->version_number + 1, 'lock_version' => $report->lock_version + 1, ...($action === 'SUBMIT' ? ['submitted_at' => now()] : []), ...($action === 'RETURN' ? ['reviewed_at' => now(), 'reviewer_id' => $request->user()->id] : []), ...($action === 'APPROVE' ? ['approved_by' => $request->user()->id, 'approved_at' => now()] : []), ...($action === 'ISSUE' ? ['issued_by' => $request->user()->id, 'issued_at' => now()] : []), ...($action === 'SUPERSEDE' ? ['superseded_at' => now(), 'is_current_revision' => false] : [])])->save();
        $this->reportSnapshot($report, $request->user(), $comment ?: $action); $this->support->audit($request, 'iap.baics.report.'.strtolower($action), $report, ['status' => $old], ['status' => $report->status], ['comment' => $comment]); $this->notify($report->assessment, $request->user(), 'BAICS_REPORT_WORKFLOW', "BAR {$action}", "{$report->report_code} moved to {$report->status}.");
        return $this->loadReport($report->fresh());
    }

    /** @return array<string, mixed> */
    public function exportData(IapBaicsReport $report): array
    {
        $report->loadMissing('assessment', 'controls.scopeItem', 'controls.component', 'controls.controlOwnerOffice', 'interimAnalyses');
        if (! in_array($report->status, ['APPROVED', 'ISSUED', 'SUPERSEDED'], true)) throw ValidationException::withMessages(['status' => ['Only an approved or issued BAR can be exported.']]);
        $version = $report->versions()->first(); if (! $version) throw ValidationException::withMessages(['version' => ['No immutable BAR version is available.']]);
        $snapshot = $version->snapshot;
        return ['title' => $report->title, 'reportCode' => $report->report_code, 'status' => $report->status, 'fileVersion' => $version->file_version, 'contentSha256' => $version->content_sha256, 'pdfChecksumSha256' => $version->pdf_checksum_sha256, 'csvChecksumSha256' => $version->csv_checksum_sha256, 'sourceManifestSha256' => $version->source_manifest_sha256, 'meta' => $snapshot['meta'] ?? [], 'columns' => $snapshot['columns'] ?? [], 'rows' => $snapshot['rows'] ?? [], 'sections' => $snapshot['sections'] ?? [], 'snapshot' => $snapshot];
    }

    private function assertReportReady(IapBaicsReport $report): void
    {
        $this->assessments->assertReady($report->assessment, true);
        if ($report->controls->isEmpty()) throw ValidationException::withMessages(['controls' => ['Select at least one Control Universe record for the BAR.']]);
        $notReady = $report->controls->filter(fn (IapBaicsControl $control): bool => ! $this->controlReadiness($control)['ready'] || $control->status !== 'APPROVED');
        if ($notReady->isNotEmpty()) throw ValidationException::withMessages(['controls' => ['Every selected control must be independently approved and traceable to approved methods and exact evidence.', 'Pending controls: '.$notReady->pluck('control_code')->join(', ').'.']]);
        if ($report->interimAnalyses->contains(fn (IapBaicsInterimAnalysis $analysis): bool => $analysis->status !== 'APPROVED')) throw ValidationException::withMessages(['interimAnalyses' => ['Selected interim analyses must be approved before the BAR can proceed.']]);
    }

    /** @return array<string, mixed> */
    private function controlReadiness(IapBaicsControl $control): array
    {
        $methodCount = $control->methods->count(); $approvedMethods = $control->methods->where('status', 'APPROVED')->count(); $evidenceCount = $control->evidenceLinks->count();
        $checks = ['scope' => (bool) $control->scope_item_id, 'ownerOffice' => (bool) $control->control_owner_office_id, 'component' => (bool) $control->component_id, 'fields' => filled($control->process_step) && filled($control->objective) && filled($control->related_risk) && filled($control->control_description) && filled($control->expected_result), 'assessorEvidence' => $methodCount > 0 && $approvedMethods === $methodCount, 'exactEvidence' => $evidenceCount > 0, 'reviewer' => (bool) $control->reviewer_id, 'classificationBasis' => ! in_array($control->control_status, ['Control Gap', 'Deficiency', 'Breakdown'], true) || (filled($control->deficiency_classification) && ($control->gap_details || $control->breakdown_details || $control->contradiction_details))];
        return ['ready' => ! in_array(false, $checks, true), 'checks' => $checks, 'methodCount' => $methodCount, 'approvedMethodCount' => $approvedMethods, 'evidenceCount' => $evidenceCount, 'status' => $control->status];
    }

    private function assertControlReady(IapBaicsControl $control): void { $readiness = $this->controlReadiness($control); if (! $readiness['ready']) throw ValidationException::withMessages(['control' => ['Complete owner, scope, component, assessment, reviewer, approved method, exact evidence, and classification basis before approving the control.']]); }
    private function controlAttributes(array $data): array { return ['control_code' => $data['controlCode'], 'process_step' => $data['processStep'], 'responsible_unit' => $data['responsibleUnit'] ?? null, 'control_owner_office_id' => $data['controlOwnerOfficeId'], 'control_owner_user_id' => $data['controlOwnerUserId'] ?? null, 'objective' => $data['objective'], 'related_risk' => $data['relatedRisk'], 'control_description' => $data['controlDescription'], 'expected_result' => $data['expectedResult'], 'control_type' => $data['controlType'], 'execution_mode' => $data['executionMode'], 'frequency' => $data['frequency'] ?? null, 'evidence_produced' => $data['evidenceProduced'] ?? null, 'approval_required' => (bool) ($data['approvalRequired'] ?? false), 'segregation_of_duties_required' => (bool) ($data['segregationOfDutiesRequired'] ?? false), 'design_assessment' => $data['designAssessment'], 'operating_assessment' => $data['operatingAssessment'], 'control_status' => $data['controlStatus'], 'deficiency_classification' => $data['deficiencyClassification'] ?? null, 'limitation_details' => $data['limitationDetails'] ?? null, 'gap_details' => $data['gapDetails'] ?? null, 'breakdown_details' => $data['breakdownDetails'] ?? null, 'contradiction_details' => $data['contradictionDetails'] ?? null, 'recommendation_action' => $data['recommendationAction'] ?? null]; }
    private function syncTraceability(IapBaicsControl $control, array $data, User $actor): void { $methodIds = collect($data['methodIds'] ?? [])->map(fn ($id): int => (int) $id)->unique(); if ($methodIds->isNotEmpty()) { $valid = IapBaicsMethod::query()->whereIn('id', $methodIds)->whereHas('component.assessment', fn ($query) => $query->where('id', $control->assessment_id))->pluck('id'); if ($valid->count() !== $methodIds->count()) throw ValidationException::withMessages(['methodIds' => ['Every method link must belong to this BAICS cycle.']]); } $evidenceIds = collect($data['evidenceLinkIds'] ?? [])->map(fn ($id): int => (int) $id)->unique(); if ($evidenceIds->isNotEmpty()) { $validEvidence = \App\Models\IapBaicsEvidenceLink::query()->whereIn('id', $evidenceIds)->whereHas('component.assessment', fn ($query) => $query->where('id', $control->assessment_id))->pluck('id'); if ($validEvidence->count() !== $evidenceIds->count()) throw ValidationException::withMessages(['evidenceLinkIds' => ['Every evidence link must belong to this BAICS cycle.']]); } $control->methods()->sync($methodIds->all(), ['linked_by' => $actor->id]); $control->evidenceLinks()->sync($evidenceIds->all(), ['linked_by' => $actor->id]); }
    private function sourceManifest(IapBaicsAssessment $assessment, $controls, $analyses): array { return ['assessment' => ['id' => $assessment->id, 'code' => $assessment->assessment_code, 'version' => $assessment->version_number, 'status' => $assessment->status], 'controls' => $controls->map(fn (IapBaicsControl $control) => ['id' => $control->id, 'code' => $control->control_code, 'version' => $control->version_number, 'status' => $control->status, 'scopeItemId' => $control->scope_item_id, 'componentId' => $control->component_id, 'evidenceVersions' => $control->evidenceLinks->map(fn ($link) => ['documentVersionId' => $link->document_version_id, 'checksumSha256' => $link->documentVersion?->checksum_sha256])->values()->all()])->values()->all(), 'interimAnalyses' => $analyses->map(fn (IapBaicsInterimAnalysis $analysis) => ['id' => $analysis->id, 'code' => $analysis->analysis_code, 'version' => $analysis->version_number, 'status' => $analysis->status])->values()->all()]; }
    private function reportSnapshot(IapBaicsReport $report, User $actor, string $reason): IapBaicsReportVersion { $report->loadMissing(['assessment', 'controls.scopeItem', 'controls.component', 'controls.controlOwnerOffice', 'controls.evidenceLinks.documentVersion', 'interimAnalyses']); $rows = $report->controls->map(fn (IapBaicsControl $control) => ['controlCode' => $control->control_code, 'processStep' => $control->process_step, 'scope' => data_get($control->scopeItem?->source_snapshot, 'name'), 'office' => $control->controlOwnerOffice?->code, 'component' => $control->component?->component_code, 'status' => $control->control_status, 'classification' => $control->deficiency_classification, 'designAssessment' => $control->design_assessment, 'operatingAssessment' => $control->operating_assessment, 'recommendation' => $control->recommendation_action])->values()->all(); $snapshot = ['meta' => [['label' => 'BAR', 'value' => $report->report_code], ['label' => 'Assessment', 'value' => $report->assessment?->assessment_code], ['label' => 'Status', 'value' => $report->status], ['label' => 'Report Version', 'value' => (string) $report->version_number]], 'sections' => [['title' => 'Executive Summary', 'text' => $report->executive_summary], ['title' => 'Objectives, Scope and Methodology', 'text' => $report->objectives_scope_methodology], ['title' => 'Overall Findings', 'text' => $report->overall_findings], ['title' => 'Control-gap Summary', 'text' => $report->control_gap_summary], ['title' => 'Recommendations', 'text' => $report->recommendations_summary], ['title' => 'Limitations and Exceptions', 'text' => $report->limitations_exceptions]], 'columns' => [['key' => 'controlCode', 'label' => 'Control Code'], ['key' => 'processStep', 'label' => 'Process Step'], ['key' => 'scope', 'label' => 'Source Process / Subject'], ['key' => 'office', 'label' => 'Owner Office'], ['key' => 'component', 'label' => 'Component'], ['key' => 'status', 'label' => 'Control Status'], ['key' => 'classification', 'label' => 'Classification'], ['key' => 'designAssessment', 'label' => 'Design Assessment'], ['key' => 'operatingAssessment', 'label' => 'Operating Assessment'], ['key' => 'recommendation', 'label' => 'Recommendation']], 'rows' => $rows, 'sourceManifest' => $report->source_manifest ?? [], 'content' => ['executiveSummary' => $report->executive_summary, 'objectivesScopeMethodology' => $report->objectives_scope_methodology, 'overallFindings' => $report->overall_findings, 'controlGapSummary' => $report->control_gap_summary, 'recommendationsSummary' => $report->recommendations_summary, 'limitationsExceptions' => $report->limitations_exceptions]]; $canonical = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); $manifestCanonical = json_encode($report->source_manifest ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); $contentHash = hash('sha256', $canonical); $manifestHash = hash('sha256', $manifestCanonical); return IapBaicsReportVersion::query()->create(['report_id' => $report->id, 'version_number' => $report->version_number, 'status' => $report->status, 'snapshot' => $snapshot, 'source_manifest' => $report->source_manifest ?? [], 'source_manifest_sha256' => $manifestHash, 'content_sha256' => $contentHash, 'pdf_checksum_sha256' => hash('sha256', 'PDF|'.$canonical), 'csv_checksum_sha256' => hash('sha256', 'CSV|'.$canonical), 'file_version' => 'BAR-'.$report->report_code.'-v'.$report->version_number, 'reason' => $reason, 'created_by' => $actor->id]); }
    private function controlSnapshot(IapBaicsControl $control, User $actor, string $reason): void { $values = $this->controlValues($control); IapBaicsControlVersion::query()->create(['control_id' => $control->id, 'version_number' => $control->version_number, 'status' => $control->status, 'snapshot' => $values, 'snapshot_hash' => hash('sha256', json_encode($values, JSON_THROW_ON_ERROR)), 'reason' => $reason, 'created_by' => $actor->id]); }
    private function interimSnapshot(IapBaicsInterimAnalysis $analysis, User $actor, string $reason): void { $values = $analysis->only(['id', 'assessment_id', 'analysis_code', 'title', 'analysis_period_start', 'analysis_period_end', 'analysis_narrative', 'findings_summary', 'recommendations_summary', 'limitations', 'source_manifest', 'status', 'version_number', 'lock_version']); IapBaicsInterimAnalysisVersion::query()->create(['interim_analysis_id' => $analysis->id, 'version_number' => $analysis->version_number, 'status' => $analysis->status, 'snapshot' => $values, 'snapshot_hash' => hash('sha256', json_encode($values, JSON_THROW_ON_ERROR)), 'reason' => $reason, 'created_by' => $actor->id]); }
    private function loadControl(IapBaicsControl $control): IapBaicsControl { return $control->load(['scopeItem.office:id,code,name', 'scopeItem.auditArea:id,code,name', 'scopeItem.auditFocus:id,code,name', 'scopeItem.auditUniverseItem:id,subject_code,name', 'component', 'controlOwnerOffice:id,code,name', 'controlOwner:id,employee_id,name,initials,position', 'preparer:id,employee_id,name,initials,position', 'reviewer:id,employee_id,name,initials,position', 'approver:id,employee_id,name,initials,position', 'methods', 'evidenceLinks.documentVersion', 'versions']); }
    private function loadReport(IapBaicsReport $report): IapBaicsReport { return $report->load(['assessment', 'controls.component', 'controls.scopeItem', 'controls.controlOwnerOffice', 'controls.methods', 'controls.evidenceLinks.documentVersion', 'interimAnalyses', 'preparer:id,employee_id,name,initials,position', 'reviewer:id,employee_id,name,initials,position', 'approver:id,employee_id,name,initials,position', 'issuer:id,employee_id,name,initials,position', 'versions.creator:id,employee_id,name,initials']); }
    private function nextReportCode(IapBaicsAssessment $assessment): string { $sequence = (int) IapBaicsReport::withTrashed()->where('assessment_id', $assessment->id)->max('id') + 1; return $this->runtime->formatNumber('baics_report_number_format', $sequence, ['YEAR' => $assessment->assessment_year]); }
    private function assertAssessmentEditable(IapBaicsAssessment $assessment): void { if (in_array($assessment->status, ['ARCHIVED'], true)) throw ValidationException::withMessages(['status' => ['Archived BAICS cycles cannot be changed.']]); }
    private function assertEditable(?Model $record): void { if ($record && ! in_array($record->status, ['DRAFT', 'RETURNED'], true)) throw ValidationException::withMessages(['status' => ['Only draft or returned Control Universe records can be changed.']]); }
    private function assertAssessmentVisible(User $user, IapBaicsAssessment $assessment): void { abort_unless($user->hasGlobalOfficeAccess() || (int) $assessment->responsible_office_id === (int) $user->office_id || $assessment->scopeItems()->where('office_id', $user->office_id)->exists(), 403, 'This BAICS cycle is outside your office scope.'); }
    private function assertOfficeScope(User $user, int $officeId): void { abort_unless($user->hasGlobalOfficeAccess() || (int) $user->office_id === $officeId, 403, 'The control owner office is outside your scope.'); }
    private function assertLock(int $current, int $provided): void { if ($current !== $provided) throw ValidationException::withMessages(['lockVersion' => ['This BAICS record changed. Refresh before continuing.']]); }
    private function controlValues(IapBaicsControl $control): array { return $control->only(['id', 'assessment_id', 'scope_item_id', 'component_id', 'control_code', 'process_step', 'responsible_unit', 'control_owner_office_id', 'control_owner_user_id', 'objective', 'related_risk', 'control_description', 'expected_result', 'control_type', 'execution_mode', 'frequency', 'evidence_produced', 'approval_required', 'segregation_of_duties_required', 'design_assessment', 'operating_assessment', 'control_status', 'deficiency_classification', 'limitation_details', 'gap_details', 'breakdown_details', 'contradiction_details', 'recommendation_action', 'status', 'prepared_by', 'reviewer_id', 'approved_by', 'version_number', 'lock_version']); }
    private function notify(IapBaicsAssessment $assessment, User $actor, string $type, string $title, string $message): void
    {
        $recipients = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($assessment): void {
                $query->where('office_id', $assessment->responsible_office_id)
                    ->orWhereHas('roles.permissions', fn ($permission) => $permission->where('code', 'iap.baics.review'));
            })
            ->pluck('id');
        $this->notifications->send($recipients, ['actorId' => $actor->id, 'type' => $type, 'category' => 'WORKFLOW', 'moduleCode' => 'IAP', 'title' => $title, 'message' => $message, 'actionUrl' => '/internal-audit-planning/baics', 'actionLabel' => 'Open BAICS', 'subjectType' => IapBaicsAssessment::class, 'subjectId' => $assessment->id, 'subjectCode' => $assessment->assessment_code, 'dedupeKey' => 'baics:'.Str::slug($type).':'.$assessment->id.':'.now()->timestamp]);
    }
}
