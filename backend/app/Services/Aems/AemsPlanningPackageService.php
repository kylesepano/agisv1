<?php

namespace App\Services;

use App\Models\AemsPlanningObjective;
use App\Models\AemsPlanningPackage;
use App\Models\AemsPlanningPackageReview;
use App\Models\AemsPlanningPackageVersion;
use App\Models\AemsPlanningKpi;
use App\Models\AemsPlannedWorkingPaperRequirement;
use App\Models\AemsProcessFlowDocument;
use App\Models\AemsRiskMatrix;
use App\Models\AemsRiskMatrixItem;
use App\Models\AemsRiskWorkingPaperLink;
use App\Models\AuditEngagement;
use App\Models\AuditProgramProcedure;
use App\Models\EngagementEvent;
use App\Models\DocumentVersion;
use App\Models\WorkingPaper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Versioned planning package and risk-planning workflow for an AEMS engagement. */
class AemsPlanningPackageService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly AemsNotificationService $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(AuditEngagement $engagement): array
    {
        $engagement->loadMissing([
            'engagementOrder', 'engagementPlan', 'planningPackage.preparer', 'planningPackage.submitter',
            'planningPackage.approver', 'planningPackage.versions.creator', 'planningPackage.versions.objectives',
            'planningPackage.versions.processFlows.processOwnerOffice',
            'planningPackage.versions.riskMatrices.items.responsibleOffice',
            'planningPackage.versions.riskMatrices.items.objectives',
            'planningPackage.versions.riskMatrices.items.procedures.program',
            'planningPackage.versions.riskMatrices.items.workingPaperLinks.workingPaper',
            'planningPackage.versions.kpis.responsibleOffice',
            'planningPackage.versions.plannedWorkingPaperRequirements',
            'planningPackage.versions.reviews.reviewer',
            'planningPackage.reviews.reviewer',
            'planningPackage.reviews.version',
            'auditAreas:id,code,name',
            'auditFocuses:id,audit_area_id,code,name',
            'offices:id,code,name',
        ]);
        $package = $engagement->planningPackage;
        $versions = $package?->versions ?? collect();
        $version = $versions->last();
        $program = $engagement->programs()->where('is_current_revision', true)->where('is_active', true)->latest('revision_number')->first();
        $procedures = $program?->procedures()->with('program')->get() ?? collect();

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
                'sourceType' => $engagement->source_type,
                'offices' => $engagement->offices->map(fn ($office): array => [
                    'id' => $office->id,
                    'code' => $office->code,
                    'name' => $office->name,
                ])->values(),
                'auditAreas' => $engagement->auditAreas->map(fn ($area): array => [
                    'id' => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                ])->values(),
                'auditFocuses' => $engagement->auditFocuses->map(fn ($focus): array => [
                    'id' => $focus->id,
                    'auditAreaId' => $focus->audit_area_id,
                    'code' => $focus->code,
                    'name' => $focus->name,
                ])->values(),
            ],
            'lineage' => $this->lineage($engagement),
            'approvedAep' => $engagement->engagementPlan?->status === 'APPROVED',
            'approvedProgram' => (bool) ($program && in_array($program->status, ['APPROVED', 'ACTIVE', 'COMPLETED'], true)),
            'package' => $package ? $this->packageSnapshot($package, $version, $versions) : null,
            'readiness' => $version ? $this->readiness($engagement, $package, $version, $procedures) : $this->emptyReadiness(),
            'procedures' => $procedures->map(fn (AuditProgramProcedure $procedure): array => ['id' => $procedure->id, 'code' => $procedure->procedure_code, 'objective' => $procedure->objective, 'auditAreaId' => $procedure->audit_area_id, 'auditFocusId' => $procedure->audit_focus_id, 'processName' => $procedure->process_name, 'auditMethod' => $procedure->audit_method, 'auditCriteria' => $procedure->audit_criteria, 'plannedPersonDays' => $procedure->planned_person_days, 'samplingRequirement' => $procedure->sampling_requirement ?? [], 'plannedWorkingPaperRequirement' => $procedure->planned_working_paper_requirement ?? []])->values(),
            'capabilities' => ['canCreate' => ! $package && $engagement->status === 'ENGAGEMENT_PLANNING', 'canEdit' => $engagement->status === 'ENGAGEMENT_PLANNING' && (bool) $package && in_array($package->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true), 'canReview' => (bool) $package && in_array($package->status, ['PENDING_REVIEW', 'RESUBMITTED'], true), 'canApprove' => (bool) $package && in_array($package->status, ['PENDING_REVIEW', 'RESUBMITTED'], true), 'canRevise' => $engagement->status === 'ENGAGEMENT_PLANNING' && $package?->status === 'APPROVED'],
        ];
    }

    /** @param array<string,mixed> $attributes */
    public function create(Request $request, AuditEngagement $engagement, array $attributes): AemsPlanningPackage
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.planning-package.create');
        return DB::transaction(function () use ($request, $engagement, $attributes): AemsPlanningPackage {
            $locked = AuditEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            if ($locked->status !== 'ENGAGEMENT_PLANNING') {
                throw ValidationException::withMessages([
                    'engagement' => ['Planning Workspace is locked until the engagement reaches ENGAGEMENT_PLANNING after an issued AEO is acknowledged by the auditee office.'],
                ]);
            }
            if ($locked->planningPackage()->exists()) {
                throw ValidationException::withMessages(['package' => ['This engagement already has a planning package.']]);
            }
            if ($locked->source_type === 'PLANNED' && $locked->iap_plan_engagement_id
                && AemsPlanningPackage::query()->where('iap_plan_engagement_id', $locked->iap_plan_engagement_id)->exists()) {
                throw ValidationException::withMessages(['source' => ['This approved IAP source already has an AEMS planning package.']]);
            }
            $package = AemsPlanningPackage::query()->create([
                'audit_engagement_id' => $locked->id, 'package_code' => 'APP-'.$locked->engagement_code,
                'status' => 'DRAFT', 'current_version_number' => 1, 'source_type' => $locked->source_type,
                'iap_plan_engagement_id' => $locked->iap_plan_engagement_id, 'iap_plan_id' => $locked->iap_plan_id,
                'iap_prioritization_item_id' => $locked->iap_prioritization_item_id, 'iap_risk_assessment_id' => $locked->iap_risk_assessment_id,
                'iap_audit_universe_item_id' => $locked->iap_audit_universe_item_id, 'prepared_by' => $request->user()->id,
                'lock_version' => 1, 'is_active' => true,
            ]);
            $version = $this->createVersion($request, $locked, $package, $attributes, 1, null);
            $this->support->event($request, $locked, 'PLANNING_PACKAGE_CREATE', null, 'DRAFT', null, ['versionNumber' => 1], null, 'PLANNING_PACKAGE', $package->id, 1, $package->package_code);
            $this->support->audit($request, 'aems.planning-package.created', $locked, null, $this->versionSnapshot($version), ['planningPackageId' => $package->id, 'planningPackageCode' => $package->package_code]);
            return $package->fresh();
        });
    }

    /** @param array<string,mixed> $attributes */
    public function update(Request $request, AuditEngagement $engagement, AemsPlanningPackage $package, array $attributes): AemsPlanningPackage
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.planning-package.update');
        return DB::transaction(function () use ($request, $engagement, $package, $attributes): AemsPlanningPackage {
            $locked = $this->lockPackage($engagement, $package, (int) $attributes['lockVersion']);
            if ($engagement->status !== 'ENGAGEMENT_PLANNING') {
                throw ValidationException::withMessages([
                    'engagement' => ['Planning Workspace is locked until the engagement reaches ENGAGEMENT_PLANNING.'],
                ]);
            }
            if (! in_array($locked->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
                throw ValidationException::withMessages(['status' => ['Only a draft or returned planning package can be edited.']]);
            }
            $old = $locked->latestVersion()->firstOrFail();
            $number = $locked->current_version_number + 1;
            $version = $this->createVersion($request, $engagement, $locked, $attributes, $number, $attributes['changeReason'] ?? null);
            $locked->update(['current_version_number' => $number, 'prepared_by' => $request->user()->id, 'submitted_by' => null, 'submitted_at' => null, 'approved_by' => null, 'approved_at' => null, 'lock_version' => $locked->lock_version + 1]);
            $this->logChange($request, $engagement, $locked, $version, 'UPDATE', $old, $version, $attributes['changeReason'] ?? null);
            return $locked->fresh();
        });
    }

    public function transition(Request $request, AuditEngagement $engagement, AemsPlanningPackage $package, string $action, int $lockVersion, ?string $comment): AemsPlanningPackage
    {
        $action = strtoupper($action);
        $permissions = ['SUBMIT' => 'aems.planning-package.update', 'RESUBMIT' => 'aems.planning-package.update', 'REVIEW' => 'aems.planning-package.review', 'RETURN' => 'aems.planning-package.review', 'APPROVE' => 'aems.planning-package.approve'];
        if (! isset($permissions[$action])) throw ValidationException::withMessages(['action' => ['Unsupported planning package workflow action.']]);
        $this->access->authorizeEngagementAction($request->user(), $engagement, $permissions[$action], in_array($action, ['REVIEW', 'RETURN', 'APPROVE'], true) ? $package->prepared_by : null);
        return DB::transaction(function () use ($request, $engagement, $package, $action, $lockVersion, $comment): AemsPlanningPackage {
            if ($engagement->status !== 'ENGAGEMENT_PLANNING') {
                throw ValidationException::withMessages([
                    'engagement' => ['Planning Workspace workflow is unavailable until the engagement reaches ENGAGEMENT_PLANNING.'],
                ]);
            }
            $locked = $this->lockPackage($engagement, $package, $lockVersion);
            $version = $locked->latestVersion()->firstOrFail();
            $from = $locked->status;
            $to = $this->nextStatus($from, $action);
            if ($action === 'RETURN' && mb_strlen(trim((string) $comment)) < 5) throw ValidationException::withMessages(['comment' => ['A clear return instruction is required.']]);
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) $this->ensureReady($engagement, $locked, $version);
            if ($action === 'REVIEW') {
                $mayUseSingleCiasAuthority = $this->access->mayUseSingleCiasHeadReviewException(
                    $request->user(),
                    'aems.planning-package.review',
                );
                if ((int) $request->user()->id === (int) $locked->prepared_by && ! $mayUseSingleCiasAuthority) throw ValidationException::withMessages(['review' => ['The preparer cannot independently review the planning package.']]);
                if (AemsPlanningPackageReview::query()->where('planning_package_id', $locked->id)->where('planning_package_version_id', $version->id)->where('reviewer_id', $request->user()->id)->exists()) {
                    throw ValidationException::withMessages(['review' => ['This reviewer has already assessed the current planning package version.']]);
                }
                AemsPlanningPackageReview::query()->create(['planning_package_id' => $locked->id, 'planning_package_version_id' => $version->id, 'reviewer_id' => $request->user()->id, 'result' => 'ACCEPTED', 'comment' => $comment, 'reviewed_at' => now()]);
            }
            if ($action === 'APPROVE') {
                $mayUseSingleCiasAuthority = $this->access->mayUseSingleCiasHeadReviewException(
                    $request->user(),
                    'aems.planning-package.approve',
                );
                $reviewed = $locked->reviews()
                    ->where('planning_package_version_id', $version->id)
                    ->where('result', 'ACCEPTED')
                    ->when(
                        ! $mayUseSingleCiasAuthority,
                        fn ($query) => $query->where('reviewer_id', '<>', $locked->prepared_by),
                    )
                    ->exists();
                if (! $reviewed) throw ValidationException::withMessages(['action' => ['The current planning package version must be independently reviewed before approval.']]);
                $this->ensureReady($engagement, $locked, $version);
            }
            $changes = ['lock_version' => $locked->lock_version + 1];
            if ($action !== 'REVIEW') $changes['status'] = $to;
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) { $changes['submitted_by'] = $request->user()->id; $changes['submitted_at'] = now(); }
            if ($action === 'APPROVE') { $changes['approved_by'] = $request->user()->id; $changes['approved_at'] = now(); $changes['approved_version_number'] = $version->version_number; }
            $locked->update($changes);
            $this->support->event($request, $engagement, 'PLANNING_PACKAGE_'.$action, $from, $to, ['status' => $from], ['status' => $to, 'versionNumber' => $version->version_number], $comment, 'PLANNING_PACKAGE', $locked->id, $version->version_number, $locked->package_code);
            $this->support->audit($request, 'aems.planning-package.'.str($action)->lower(), $engagement, ['status' => $from], ['status' => $to, 'versionNumber' => $version->version_number], ['planningPackageId' => $locked->id, 'planningPackageCode' => $locked->package_code, 'comment' => $comment]);
            $this->notifications->controlledDocumentTransition($request, $engagement, 'PLANNING_PACKAGE', $locked->id, $locked->package_code, 'Planning Package', $action, $version->version_number, $locked->prepared_by, $locked->submitted_by, 'aems.planning-package.review', "/audit-engagement-management/planning-package?engagementId={$engagement->id}");
            return $locked->fresh();
        });
    }

    public function revise(Request $request, AuditEngagement $engagement, AemsPlanningPackage $package, int $lockVersion, string $reason): AemsPlanningPackage
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.planning-package.revise', $package->prepared_by);
        return DB::transaction(function () use ($request, $engagement, $package, $lockVersion, $reason): AemsPlanningPackage {
            $locked = $this->lockPackage($engagement, $package, $lockVersion);
            if ($locked->status !== 'APPROVED') throw ValidationException::withMessages(['status' => ['Only an approved planning package can start a formal revision.']]);
            $source = $locked->latestVersion()->firstOrFail()->load([
                'objectives',
                'processFlows',
                'riskMatrices.items.objectives',
                'riskMatrices.items.procedures',
                'riskMatrices.items.workingPaperLinks',
                'kpis',
                'plannedWorkingPaperRequirements',
            ]);
            $number = $locked->current_version_number + 1;
            $payload = [
                'preliminarySurvey' => $source->preliminary_survey,
                'planningAttributes' => $source->planning_attributes,
                'preliminarySurveyDocumentVersionId' => $source->preliminary_survey_document_version_id,
                'objectives' => $source->objectives->map(fn ($objective) => ['code' => $objective->objective_code, 'statement' => $objective->objective_statement, 'sourceType' => $objective->source_type, 'sourceReference' => $objective->source_reference, 'sequence' => $objective->sequence])->all(),
                'processFlows' => $source->processFlows->map(fn ($flow) => ['code' => $flow->flow_code, 'title' => $flow->title, 'description' => $flow->description, 'processOwnerOfficeId' => $flow->process_owner_office_id, 'documentVersionId' => $flow->document_version_id, 'sourceType' => $flow->source_type, 'sourceReference' => $flow->source_reference, 'sequence' => $flow->sequence, 'auditAreaId' => $flow->audit_area_id, 'auditFocusId' => $flow->audit_focus_id, 'scopeStatement' => $flow->scope_statement, 'steps' => $flow->steps, 'inputs' => $flow->inputs, 'outputs' => $flow->outputs, 'recordsSystems' => $flow->records_systems, 'controls' => $flow->controls, 'decisionPoints' => $flow->decision_points, 'riskPoints' => $flow->risk_points, 'limitations' => $flow->limitations])->all(),
                'kpis' => $source->kpis->map(fn ($kpi) => ['code' => $kpi->kpi_code, 'name' => $kpi->name, 'target' => $kpi->target, 'measurementMethod' => $kpi->measurement_method, 'sourceReference' => $kpi->source_reference, 'responsibleOfficeId' => $kpi->responsible_office_id, 'status' => $kpi->status, 'sequence' => $kpi->sequence])->all(),
                'plannedWorkingPapers' => $source->plannedWorkingPaperRequirements->map(fn ($wp) => ['procedureId' => $wp->audit_program_procedure_id, 'riskItemId' => $wp->risk_matrix_item_id, 'reference' => $wp->working_paper_reference, 'title' => $wp->title, 'objective' => $wp->objective, 'requiredEvidence' => $wp->required_evidence, 'isRequired' => $wp->is_required, 'sequence' => $wp->sequence])->all(),
                'riskMatrices' => $source->riskMatrices->map(fn ($matrix) => ['code' => $matrix->matrix_code, 'title' => $matrix->title, 'methodology' => $matrix->methodology, 'riskAppetite' => $matrix->risk_appetite, 'overallConclusion' => $matrix->overall_conclusion, 'auditAreaId' => $matrix->audit_area_id, 'auditFocusId' => $matrix->audit_focus_id, 'allAuditFocuses' => $matrix->all_audit_focuses, 'matrixType' => $matrix->matrix_type, 'status' => $matrix->status, 'riskItems' => $matrix->items->map(fn ($item) => ['riskCode' => $item->risk_code, 'riskStatement' => $item->risk_statement, 'riskCategory' => $item->risk_category, 'inherentLikelihood' => $item->inherent_likelihood, 'inherentImpact' => $item->inherent_impact, 'inherentScore' => $item->inherent_score, 'controlDescription' => $item->control_description, 'controlEffectiveness' => $item->control_effectiveness, 'residualLikelihood' => $item->residual_likelihood, 'residualImpact' => $item->residual_impact, 'residualScore' => $item->residual_score, 'residualRating' => $item->residual_rating, 'riskResponse' => $item->risk_response, 'responsibleOfficeId' => $item->responsible_office_id, 'sequence' => $item->sequence, 'status' => $item->status, 'auditAreaId' => $item->audit_area_id, 'auditFocusId' => $item->audit_focus_id, 'processFlowId' => $item->process_flow_id, 'processName' => $item->process_name, 'riskArea' => $item->risk_area, 'plannedAuditApproach' => $item->planned_audit_approach, 'criteria' => $item->criteria, 'responseRationale' => $item->response_rationale, 'sourceReference' => $item->source_reference, 'objectiveCodes' => $item->objectives()->pluck('objective_code')->all(), 'procedureIds' => $item->procedures()->pluck('audit_program_procedures.id')->all(), 'workingPapers' => $item->workingPaperLinks->map(fn ($link) => ['workingPaperId' => $link->working_paper_id, 'reference' => $link->working_paper_reference, 'basis' => $link->relationship_basis])->all()])->all()])->all(),
            ];
            $version = $this->createVersion($request, $engagement, $locked, $payload, $number, $reason);
            $locked->update(['status' => 'DRAFT', 'current_version_number' => $number, 'prepared_by' => $request->user()->id, 'submitted_by' => null, 'submitted_at' => null, 'lock_version' => $locked->lock_version + 1]);
            $this->logChange($request, $engagement, $locked, $version, 'REVISE', $source, $version, $reason);
            return $locked->fresh();
        });
    }

    /** @return array<string,mixed> */
    public function readiness(AuditEngagement $engagement, AemsPlanningPackage $package, AemsPlanningPackageVersion $version, $procedures = null): array
    {
        $procedures ??= AuditProgramProcedure::query()->with('program')->whereHas('program', fn ($q) => $q->where('audit_engagement_id', $engagement->id)->where('is_current_revision', true)->where('is_active', true))->get();
        $version->loadMissing([
            'objectives',
            'processFlows',
            'riskMatrices.items.objectives',
            'riskMatrices.items.procedures',
            'riskMatrices.items.workingPaperLinks.workingPaper',
            'kpis',
            'plannedWorkingPaperRequirements',
        ]);
        $engagement->loadMissing('auditAreas:id');
        $survey = $version->preliminary_survey ?? [];
        $objectives = $version->objectives;
        $flows = $version->processFlows;
        $matrices = $version->riskMatrices;
        $items = $matrices->flatMap(fn ($matrix) => $matrix->items);
        $program = $procedures->first()?->program;
        $legacyChecks = [
            ['key' => 'iapLineage', 'label' => 'IAP source lineage is preserved', 'met' => $this->lineageValid($engagement, $package)],
            ['key' => 'survey', 'label' => 'Preliminary survey is complete', 'met' => collect(['purpose','background','informationSources','observations','planningImplications'])->every(fn ($key) => filled(data_get($survey, $key)))],
            ['key' => 'objectives', 'label' => 'At least one planning objective exists', 'met' => $objectives->isNotEmpty()],
            ['key' => 'processFlows', 'label' => 'Process flow documentation is complete', 'met' => $flows->isNotEmpty() && $flows->every(fn ($flow) => filled($flow->title) && (filled($flow->description) || filled($flow->document_version_id) || filled($flow->source_reference)))],
            ['key' => 'riskMatrix', 'label' => 'Risk matrix and risk items exist', 'met' => $matrices->isNotEmpty() && $items->isNotEmpty()],
            ['key' => 'riskObjectives', 'label' => 'Every risk links to an objective in this version', 'met' => $items->every(fn ($item) => $item->objectives->contains(fn ($objective) => (int) $objective->planning_package_version_id === (int) $version->id)) && $items->isNotEmpty()],
            ['key' => 'riskProcedures', 'label' => 'Every risk links to an approved-program procedure', 'met' => $items->every(fn ($item) => $item->procedures->contains(fn ($procedure) => $procedures->contains(fn ($required) => (int) $required->id === (int) $procedure->id))) && $items->isNotEmpty()],
            ['key' => 'riskWorkingPapers', 'label' => 'Every risk has a working-paper reference', 'met' => $items->every(fn ($item) => $item->workingPaperLinks->contains(fn ($link) => $link->working_paper_id === null || ($link->workingPaper && (int) $link->workingPaper->audit_engagement_id === (int) $engagement->id))) && $items->isNotEmpty()],
            ['key' => 'approvedAep', 'label' => 'Current AEP is approved', 'met' => $engagement->engagementPlan?->status === 'APPROVED'],
            ['key' => 'approvedProgram', 'label' => 'Current Audit Program is approved', 'met' => (bool) $program && in_array($program->status, ['APPROVED','ACTIVE','COMPLETED'], true)],
        ];
        $requiredAreaIds = collect($engagement->auditAreas->modelKeys());
        $structuredFlow = $flows->isNotEmpty() && $flows->every(fn ($flow) => filled($flow->scope_statement) && is_array($flow->steps) && $flow->steps !== [] && is_array($flow->inputs) && is_array($flow->outputs) && is_array($flow->controls) && is_array($flow->risk_points));
        $matrixCoverage = $matrices->isNotEmpty() && ($requiredAreaIds->isEmpty() || $requiredAreaIds->every(fn ($areaId) => $matrices->contains(fn ($matrix) => (int) $matrix->audit_area_id === (int) $areaId)));
        $rule35 = $items->isNotEmpty() && $items->every(fn ($item) => filled($item->process_name) && filled($item->risk_area) && filled($item->planned_audit_approach) && filled($item->criteria) && $item->audit_area_id && $item->process_flow_id);
        $programDefinition = (bool) $program && $program->audit_area_id && filled($program->audit_period_start) && filled($program->audit_period_end) && filled($program->audit_criteria) && filled($program->sampling_approach);
        $procedureDefinition = $procedures->isNotEmpty() && $procedures->every(fn ($procedure) => $procedure->audit_area_id && filled($procedure->process_name) && filled($procedure->audit_method) && filled($procedure->audit_criteria) && (float) $procedure->planned_person_days > 0 && is_array($procedure->sampling_requirement) && filled(data_get($procedure->sampling_requirement, 'method')) && is_array($procedure->planned_working_paper_requirement) && filled(data_get($procedure->planned_working_paper_requirement, 'reference')));
        $kpis = $version->kpis;
        $kpiDecision = strtoupper((string) data_get($version->planning_attributes, 'kpis.decision', ''));
        $kpiReady = $kpis->isNotEmpty() && $kpis->every(fn ($kpi) => filled($kpi->name) && filled($kpi->target) && filled($kpi->measurement_method));
        $kpiReady = $kpiReady || ($kpiDecision === 'NOT_APPLICABLE' && filled(data_get($version->planning_attributes, 'kpis.reason')));
        $plannedWps = $version->plannedWorkingPaperRequirements->where('is_required', true);
        $plannedWpReady = $plannedWps->isNotEmpty() && $plannedWps->every(fn ($wp) => filled($wp->working_paper_reference) && filled($wp->title) && filled($wp->required_evidence));
        $conformanceChecks = [
            ['key' => 'structuredProcessFlows', 'label' => 'Process flows include scope, steps, inputs, outputs, controls, and risk points', 'met' => $structuredFlow],
            ['key' => 'matrixCoverage', 'label' => 'Risk matrices cover each authorized audit area', 'met' => $matrixCoverage],
            ['key' => 'rule35RiskItems', 'label' => 'Risk items contain Rule 35 area, process, approach, criteria, and flow traceability', 'met' => $rule35],
            ['key' => 'programDefinition', 'label' => 'Audit Program area, period, criteria, and sampling approach are defined', 'met' => (bool) $programDefinition],
            ['key' => 'procedureDefinition', 'label' => 'Every procedure has process, method, criteria, person-days, sampling, and planned WP requirements', 'met' => $procedureDefinition],
            ['key' => 'kpis', 'label' => 'Planning KPIs are defined or formally not applicable', 'met' => $kpiReady],
            ['key' => 'plannedWorkingPapers', 'label' => 'Required planned Working Papers and evidence are recorded', 'met' => $plannedWpReady],
        ];
        $checks = [...$legacyChecks, ...$conformanceChecks];
        return [
            'ready' => collect($legacyChecks)->every(fn ($check) => $check['met']),
            'fieldworkReady' => collect($checks)->every(fn ($check) => $check['met']),
            'checks' => $checks,
            'legacyChecks' => $legacyChecks,
            'conformanceChecks' => $conformanceChecks,
        ];
    }

    /** @param array<string,mixed> $attributes */
    private function createVersion(Request $request, AuditEngagement $engagement, AemsPlanningPackage $package, array $attributes, int $number, ?string $reason): AemsPlanningPackageVersion
    {
        $this->validateReferences($engagement, $attributes);
        $version = AemsPlanningPackageVersion::query()->create(['planning_package_id' => $package->id, 'version_number' => $number, 'preliminary_survey' => $attributes['preliminarySurvey'] ?? [], 'planning_attributes' => $attributes['planningAttributes'] ?? [], 'iap_lineage_snapshot' => $this->lineage($engagement), 'preliminary_survey_document_version_id' => $attributes['preliminarySurveyDocumentVersionId'] ?? data_get($attributes, 'preliminarySurvey.documentVersionId'), 'checksum_sha256' => hash('sha256', json_encode(['preliminarySurvey' => $attributes['preliminarySurvey'] ?? [], 'planningAttributes' => $attributes['planningAttributes'] ?? [], 'objectives' => $attributes['objectives'] ?? [], 'processFlows' => $attributes['processFlows'] ?? [], 'riskMatrix' => $attributes['riskMatrix'] ?? null, 'riskMatrices' => $attributes['riskMatrices'] ?? [], 'riskItems' => $attributes['riskItems'] ?? [], 'kpis' => $attributes['kpis'] ?? [], 'plannedWorkingPapers' => $attributes['plannedWorkingPapers'] ?? []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 'change_reason' => $reason, 'created_by' => $request->user()->id]);
        foreach (($attributes['objectives'] ?? []) as $index => $objective) AemsPlanningObjective::query()->create(['planning_package_version_id' => $version->id, 'objective_code' => $objective['code'] ?? 'OBJ-'.($index + 1), 'objective_statement' => $objective['statement'] ?? $objective['objectiveStatement'] ?? '', 'source_type' => $objective['sourceType'] ?? 'AEMS', 'source_reference' => $objective['sourceReference'] ?? null, 'sequence' => $objective['sequence'] ?? $index]);
        foreach (($attributes['processFlows'] ?? []) as $index => $flow) AemsProcessFlowDocument::query()->create(['planning_package_version_id' => $version->id, 'flow_code' => $flow['code'] ?? $flow['flowCode'] ?? 'FLOW-'.($index + 1), 'title' => $flow['title'] ?? '', 'description' => $flow['description'] ?? null, 'process_owner_office_id' => $flow['processOwnerOfficeId'] ?? null, 'document_version_id' => $flow['documentVersionId'] ?? null, 'source_type' => $flow['sourceType'] ?? 'AEMS', 'source_reference' => $flow['sourceReference'] ?? null, 'sequence' => $flow['sequence'] ?? $index, 'audit_area_id' => $flow['auditAreaId'] ?? null, 'audit_focus_id' => $flow['auditFocusId'] ?? null, 'scope_statement' => $flow['scopeStatement'] ?? null, 'steps' => $flow['steps'] ?? [], 'inputs' => $flow['inputs'] ?? [], 'outputs' => $flow['outputs'] ?? [], 'records_systems' => $flow['recordsSystems'] ?? [], 'controls' => $flow['controls'] ?? [], 'decision_points' => $flow['decisionPoints'] ?? [], 'risk_points' => $flow['riskPoints'] ?? [], 'limitations' => $flow['limitations'] ?? null]);
        foreach (($attributes['kpis'] ?? []) as $index => $kpi) AemsPlanningKpi::query()->create(['planning_package_version_id' => $version->id, 'kpi_code' => $kpi['code'] ?? 'KPI-'.($index + 1), 'name' => $kpi['name'] ?? $kpi['indicator'] ?? '', 'target' => (string) ($kpi['target'] ?? $kpi['targetValue'] ?? ''), 'measurement_method' => $kpi['measurementMethod'] ?? $kpi['method'] ?? '', 'source_reference' => $kpi['sourceReference'] ?? null, 'responsible_office_id' => $kpi['responsibleOfficeId'] ?? null, 'status' => $kpi['status'] ?? 'DEFINED', 'sequence' => $kpi['sequence'] ?? $index]);
        $matrices = $attributes['riskMatrices'] ?? null;
        if (! is_array($matrices) || $matrices === []) $matrices = is_array($attributes['riskMatrix'] ?? null) ? [$attributes['riskMatrix']] : [];
        $objectiveIds = $version->objectives()->pluck('id', 'objective_code');
        $flowIds = $version->processFlows()->pluck('id', 'flow_code');
        $submittedFlowCodesById = collect($attributes['processFlows'] ?? [])
            ->filter(fn ($flow) => is_array($flow) && filled($flow['id'] ?? null))
            ->mapWithKeys(fn (array $flow): array => [
                (string) $flow['id'] => $flow['code'] ?? $flow['flowCode'] ?? '',
            ]);
        foreach ($matrices as $matrixPayload) {
            if (! is_array($matrixPayload)) continue;
            $matrix = AemsRiskMatrix::query()->create(['planning_package_version_id' => $version->id, 'matrix_code' => $matrixPayload['code'] ?? $matrixPayload['matrixCode'] ?? 'RM-'.$number, 'title' => $matrixPayload['title'] ?? 'Risk Matrix', 'methodology' => $matrixPayload['methodology'] ?? null, 'risk_appetite' => $matrixPayload['riskAppetite'] ?? null, 'overall_conclusion' => $matrixPayload['overallConclusion'] ?? null, 'audit_area_id' => $matrixPayload['auditAreaId'] ?? null, 'audit_focus_id' => ($matrixPayload['allAuditFocuses'] ?? false) ? null : ($matrixPayload['auditFocusId'] ?? null), 'all_audit_focuses' => (bool) ($matrixPayload['allAuditFocuses'] ?? false), 'matrix_type' => $matrixPayload['matrixType'] ?? null, 'status' => $matrixPayload['status'] ?? 'DRAFT']);
            foreach (($matrixPayload['riskItems'] ?? ($attributes['riskItems'] ?? [])) as $index => $risk) {
                $submittedFlowId = $risk['processFlowId'] ?? null;
                $processFlowId = filled($submittedFlowId)
                    ? ($flowIds->get($submittedFlowCodesById->get((string) $submittedFlowId)) ?? $submittedFlowId)
                    : ($flowIds->get($risk['processFlowCode'] ?? '') ?: null);
                $item = AemsRiskMatrixItem::query()->create(['risk_matrix_id' => $matrix->id, 'risk_code' => $risk['riskCode'] ?? 'RISK-'.($index + 1), 'risk_statement' => $risk['riskStatement'] ?? '', 'risk_category' => $risk['riskCategory'] ?? null, 'inherent_likelihood' => $risk['inherentLikelihood'] ?? null, 'inherent_impact' => $risk['inherentImpact'] ?? null, 'inherent_score' => $risk['inherentScore'] ?? null, 'control_description' => $risk['controlDescription'] ?? null, 'control_effectiveness' => $risk['controlEffectiveness'] ?? null, 'residual_likelihood' => $risk['residualLikelihood'] ?? null, 'residual_impact' => $risk['residualImpact'] ?? null, 'residual_score' => $risk['residualScore'] ?? null, 'residual_rating' => $risk['residualRating'] ?? null, 'risk_response' => $risk['riskResponse'] ?? null, 'responsible_office_id' => $risk['responsibleOfficeId'] ?? null, 'sequence' => $risk['sequence'] ?? $index, 'status' => $risk['status'] ?? 'OPEN', 'audit_area_id' => $risk['auditAreaId'] ?? $matrixPayload['auditAreaId'] ?? null, 'audit_focus_id' => $risk['auditFocusId'] ?? $matrixPayload['auditFocusId'] ?? null, 'process_flow_id' => $processFlowId, 'process_name' => $risk['processName'] ?? null, 'risk_area' => $risk['riskArea'] ?? null, 'planned_audit_approach' => $risk['plannedAuditApproach'] ?? null, 'criteria' => $risk['criteria'] ?? null, 'response_rationale' => $risk['responseRationale'] ?? null, 'source_reference' => $risk['sourceReference'] ?? null]);
                foreach (($risk['objectiveCodes'] ?? []) as $code) if ($objectiveIds->get($code)) DB::table('aems_risk_objective_links')->insert(['risk_matrix_item_id' => $item->id, 'planning_objective_id' => $objectiveIds->get($code), 'relationship_basis' => $risk['relationshipBasis'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
                foreach (($risk['procedureIds'] ?? []) as $procedureId) DB::table('aems_risk_procedure_links')->insertOrIgnore(['risk_matrix_item_id' => $item->id, 'audit_program_procedure_id' => $procedureId, 'relationship_basis' => $risk['relationshipBasis'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
                foreach (($risk['workingPapers'] ?? []) as $paper) AemsRiskWorkingPaperLink::query()->create(['risk_matrix_item_id' => $item->id, 'working_paper_id' => $paper['workingPaperId'] ?? null, 'working_paper_reference' => $paper['reference'] ?? '', 'relationship_basis' => $paper['basis'] ?? null]);
            }
        }
        foreach (($attributes['plannedWorkingPapers'] ?? []) as $index => $paper) AemsPlannedWorkingPaperRequirement::query()->create(['planning_package_version_id' => $version->id, 'audit_program_procedure_id' => $paper['procedureId'] ?? null, 'risk_matrix_item_id' => $paper['riskItemId'] ?? null, 'working_paper_reference' => $paper['reference'] ?? $paper['workingPaperReference'] ?? 'WP-'.($index + 1), 'title' => $paper['title'] ?? 'Planned Working Paper', 'objective' => $paper['objective'] ?? null, 'required_evidence' => $paper['requiredEvidence'] ?? null, 'is_required' => $paper['isRequired'] ?? true, 'sequence' => $paper['sequence'] ?? $index]);
        return $version->fresh(['objectives','processFlows','riskMatrices.items','kpis','plannedWorkingPaperRequirements']);
    }

    private function validateReferences(AuditEngagement $engagement, array $attributes): void
    {
        foreach ([$attributes['preliminarySurveyDocumentVersionId'] ?? null, data_get($attributes, 'preliminarySurvey.documentVersionId')] as $id) if ($id && ! DocumentVersion::query()->whereKey($id)->exists()) throw ValidationException::withMessages(['documentVersionId' => ['The selected Core Document Version does not exist.']]);
        foreach (($attributes['processFlows'] ?? []) as $flow) if (! empty($flow['documentVersionId']) && ! DocumentVersion::query()->whereKey($flow['documentVersionId'])->exists()) throw ValidationException::withMessages(['processFlows' => ['Each process-flow document must reference an existing Core Document Version.']]);
        foreach (($attributes['processFlows'] ?? []) as $flow) if (! empty($flow['processOwnerOfficeId']) && ! $engagement->offices()->whereKey($flow['processOwnerOfficeId'])->exists()) throw ValidationException::withMessages(['processFlows' => ['Process-owner offices must be linked to this engagement.']]);
        $matrixPayloads = $attributes['riskMatrices'] ?? (is_array($attributes['riskMatrix'] ?? null) ? [$attributes['riskMatrix']] : []);
        foreach ($matrixPayloads as $matrix) foreach (($matrix['riskItems'] ?? ($attributes['riskItems'] ?? [])) as $risk) {
            if (! empty($risk['responsibleOfficeId']) && ! $engagement->offices()->whereKey($risk['responsibleOfficeId'])->exists()) throw ValidationException::withMessages(['riskItems' => ['Risk responsible offices must be linked to this engagement.']]);
            foreach (($risk['procedureIds'] ?? []) as $procedureId) if (! AuditProgramProcedure::query()->whereKey($procedureId)->whereHas('program', fn ($q) => $q->where('audit_engagement_id', $engagement->id))->exists()) throw ValidationException::withMessages(['riskItems' => ['Risk relationships may only use procedures from this engagement.']]);
            foreach (($risk['workingPapers'] ?? []) as $paper) {
            if (blank($paper['reference'] ?? null)) throw ValidationException::withMessages(['riskItems' => ['Every working-paper relationship requires a reference.']]);
            if (! empty($paper['workingPaperId']) && ! WorkingPaper::query()->whereKey($paper['workingPaperId'])->where('audit_engagement_id', $engagement->id)->exists()) throw ValidationException::withMessages(['riskItems' => ['Working-paper relationships may only use papers from this engagement.']]);
            }
        }
    }

    private function ensureReady(AuditEngagement $engagement, AemsPlanningPackage $package, AemsPlanningPackageVersion $version): void { $result = $this->readiness($engagement, $package, $version); if (! $result['ready']) throw ValidationException::withMessages(['readiness' => collect($result['checks'])->where('met', false)->pluck('label')->values()->all()]); }
    private function lockPackage(AuditEngagement $engagement, AemsPlanningPackage $package, int $lockVersion): AemsPlanningPackage { $locked = AemsPlanningPackage::query()->lockForUpdate()->findOrFail($package->id); if ((int) $locked->audit_engagement_id !== (int) $engagement->id) throw ValidationException::withMessages(['package' => ['The planning package does not belong to this engagement.']]); if ($locked->lock_version !== $lockVersion) throw ValidationException::withMessages(['lockVersion' => ['This planning package changed in another session. Refresh before continuing.']]); return $locked; }
    private function nextStatus(string $status, string $action): string
    {
        $next = ['DRAFT' => ['SUBMIT' => 'PENDING_REVIEW'], 'PENDING_REVIEW' => ['REVIEW' => 'PENDING_REVIEW', 'RETURN' => 'RETURNED_FOR_REVISION', 'APPROVE' => 'APPROVED'], 'RETURNED_FOR_REVISION' => ['RESUBMIT' => 'RESUBMITTED'], 'RESUBMITTED' => ['REVIEW' => 'RESUBMITTED', 'RETURN' => 'RETURNED_FOR_REVISION', 'APPROVE' => 'APPROVED']][$status][$action] ?? null;
        if (! $next) throw ValidationException::withMessages(['action' => ["{$action} is not allowed while the planning package is {$status}."]]);
        return $next;
    }
    private function lineage(AuditEngagement $engagement): array { return ['sourceType' => $engagement->source_type, 'iapPlanEngagementId' => $engagement->iap_plan_engagement_id, 'iapPlanId' => $engagement->iap_plan_id, 'iapPrioritizationItemId' => $engagement->iap_prioritization_item_id, 'iapRiskAssessmentId' => $engagement->iap_risk_assessment_id, 'iapAuditUniverseItemId' => $engagement->iap_audit_universe_item_id, 'sourceSnapshot' => $engagement->source_snapshot, 'capturedAt' => now()->toISOString()]; }
    private function lineageValid(AuditEngagement $engagement, AemsPlanningPackage $package): bool
    {
        if ($package->source_type !== $engagement->source_type) return false;
        if ($engagement->source_type === 'PLANNED' && blank($engagement->source_snapshot)) return false;
        return (int) $package->iap_plan_engagement_id === (int) $engagement->iap_plan_engagement_id
            && (int) $package->iap_plan_id === (int) $engagement->iap_plan_id
            && (int) $package->iap_prioritization_item_id === (int) $engagement->iap_prioritization_item_id
            && (int) $package->iap_risk_assessment_id === (int) $engagement->iap_risk_assessment_id
            && (int) $package->iap_audit_universe_item_id === (int) $engagement->iap_audit_universe_item_id;
    }
    private function emptyReadiness(): array { return ['ready' => false, 'checks' => [['key'=>'package','label'=>'Planning package exists','met'=>false]]]; }
    private function logChange(Request $request, AuditEngagement $engagement, AemsPlanningPackage $package, AemsPlanningPackageVersion $version, string $action, $old, $new, ?string $comment): void { $this->support->event($request, $engagement, 'PLANNING_PACKAGE_'.$action, $package->status, 'DRAFT', is_object($old) ? ['versionNumber'=>$old->version_number] : null, ['versionNumber'=>$version->version_number], $comment, 'PLANNING_PACKAGE', $package->id, $version->version_number, $package->package_code); $this->support->audit($request, 'aems.planning-package.'.str($action)->lower(), $engagement, null, ['versionNumber'=>$version->version_number], ['planningPackageId'=>$package->id]); }
    /** @return array<string,mixed> */
    private function packageSnapshot(AemsPlanningPackage $package, ?AemsPlanningPackageVersion $version, $versions = null): array { $reviews = $package->relationLoaded('reviews') ? $package->reviews : $package->reviews()->with('reviewer','version')->get(); return ['id'=>$package->id,'packageCode'=>$package->package_code,'status'=>$package->status,'currentVersionNumber'=>$package->current_version_number,'approvedVersionNumber'=>$package->approved_version_number,'lockVersion'=>$package->lock_version,'preparedBy'=>$package->preparer?->only(['id','name','employee_id']),'submittedBy'=>$package->submitter?->only(['id','name','employee_id']),'submittedAt'=>$package->submitted_at?->toISOString(),'approvedBy'=>$package->approver?->only(['id','name','employee_id']),'approvedAt'=>$package->approved_at?->toISOString(),'latestVersion'=>$version ? $this->versionSnapshot($version) : null,'versions'=>collect($versions ?? $package->versions)->map(fn ($entry)=>$this->versionSnapshot($entry))->values(),'reviews'=>$reviews->map(fn ($review)=>['id'=>$review->id,'versionNumber'=>$review->version?->version_number,'result'=>$review->result,'comment'=>$review->comment,'reviewedAt'=>$review->reviewed_at?->toISOString(),'reviewer'=>$review->reviewer?->only(['id','name','employee_id'])])->values()]; }
    /** @return array<string,mixed> */
    private function versionSnapshot(AemsPlanningPackageVersion $version): array
    {
        $matrices = $version->relationLoaded('riskMatrices') ? $version->riskMatrices : $version->riskMatrices()->with(['items.objectives','items.procedures','items.workingPaperLinks'])->get();
        $matrixSnapshot = fn ($matrix) => ['id'=>$matrix->id,'code'=>$matrix->matrix_code,'title'=>$matrix->title,'methodology'=>$matrix->methodology,'riskAppetite'=>$matrix->risk_appetite,'overallConclusion'=>$matrix->overall_conclusion,'auditAreaId'=>$matrix->audit_area_id,'auditFocusId'=>$matrix->audit_focus_id,'allAuditFocuses'=>(bool) $matrix->all_audit_focuses,'matrixType'=>$matrix->matrix_type,'status'=>$matrix->status,'items'=>$matrix->items->map(fn ($item)=>['id'=>$item->id,'riskCode'=>$item->risk_code,'riskStatement'=>$item->risk_statement,'riskCategory'=>$item->risk_category,'inherentLikelihood'=>$item->inherent_likelihood,'inherentImpact'=>$item->inherent_impact,'inherentScore'=>$item->inherent_score,'controlDescription'=>$item->control_description,'controlEffectiveness'=>$item->control_effectiveness,'residualLikelihood'=>$item->residual_likelihood,'residualImpact'=>$item->residual_impact,'residualScore'=>$item->residual_score,'residualRating'=>$item->residual_rating,'riskResponse'=>$item->risk_response,'responsibleOfficeId'=>$item->responsible_office_id,'sequence'=>$item->sequence,'status'=>$item->status,'auditAreaId'=>$item->audit_area_id,'auditFocusId'=>$item->audit_focus_id,'processFlowId'=>$item->process_flow_id,'processName'=>$item->process_name,'riskArea'=>$item->risk_area,'plannedAuditApproach'=>$item->planned_audit_approach,'criteria'=>$item->criteria,'responseRationale'=>$item->response_rationale,'sourceReference'=>$item->source_reference,'objectives'=>$item->objectives->map(fn ($objective)=>['code'=>$objective->objective_code,'statement'=>$objective->objective_statement])->values(),'procedures'=>$item->procedures->map(fn ($procedure)=>['id'=>$procedure->id,'code'=>$procedure->procedure_code,'objective'=>$procedure->objective])->values(),'workingPapers'=>$item->workingPaperLinks->map(fn ($link)=>['workingPaperId'=>$link->working_paper_id,'reference'=>$link->working_paper_reference,'basis'=>$link->relationship_basis])->values()])->values()];
        return ['id'=>$version->id,'versionNumber'=>$version->version_number,'preliminarySurvey'=>$version->preliminary_survey ?? [],'preliminarySurveyDocumentVersionId'=>$version->preliminary_survey_document_version_id,'planningAttributes'=>$version->planning_attributes ?? [],'iapLineageSnapshot'=>$version->iap_lineage_snapshot ?? [],'checksumSha256'=>$version->checksum_sha256,'changeReason'=>$version->change_reason,'createdBy'=>$version->creator?->only(['id','name','employee_id']),'createdAt'=>$version->created_at?->toISOString(),'objectives'=>$version->objectives->map(fn ($o)=>['id'=>$o->id,'code'=>$o->objective_code,'statement'=>$o->objective_statement,'sourceType'=>$o->source_type,'sourceReference'=>$o->source_reference,'sequence'=>$o->sequence])->values(),'processFlows'=>$version->processFlows->map(fn ($f)=>['id'=>$f->id,'code'=>$f->flow_code,'title'=>$f->title,'description'=>$f->description,'processOwnerOfficeId'=>$f->process_owner_office_id,'documentVersionId'=>$f->document_version_id,'sourceType'=>$f->source_type,'sourceReference'=>$f->source_reference,'sequence'=>$f->sequence,'auditAreaId'=>$f->audit_area_id,'auditFocusId'=>$f->audit_focus_id,'scopeStatement'=>$f->scope_statement,'steps'=>$f->steps ?? [],'inputs'=>$f->inputs ?? [],'outputs'=>$f->outputs ?? [],'recordsSystems'=>$f->records_systems ?? [],'controls'=>$f->controls ?? [],'decisionPoints'=>$f->decision_points ?? [],'riskPoints'=>$f->risk_points ?? [],'limitations'=>$f->limitations])->values(),'riskMatrix'=>$matrices->first() ? $matrixSnapshot($matrices->first()) : null,'riskMatrices'=>$matrices->map($matrixSnapshot)->values(),'kpis'=>$version->kpis->map(fn ($kpi)=>['id'=>$kpi->id,'code'=>$kpi->kpi_code,'name'=>$kpi->name,'target'=>$kpi->target,'measurementMethod'=>$kpi->measurement_method,'sourceReference'=>$kpi->source_reference,'responsibleOfficeId'=>$kpi->responsible_office_id,'status'=>$kpi->status,'sequence'=>$kpi->sequence])->values(),'plannedWorkingPapers'=>$version->plannedWorkingPaperRequirements->map(fn ($wp)=>['id'=>$wp->id,'procedureId'=>$wp->audit_program_procedure_id,'riskItemId'=>$wp->risk_matrix_item_id,'reference'=>$wp->working_paper_reference,'title'=>$wp->title,'objective'=>$wp->objective,'requiredEvidence'=>$wp->required_evidence,'isRequired'=>$wp->is_required,'sequence'=>$wp->sequence])->values()];
    }
}
