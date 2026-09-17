<?php

/*
|--------------------------------------------------------------------------
| AGIS API Routes
|--------------------------------------------------------------------------
| Public endpoints are declared explicitly; all business routes are grouped
| behind authentication and granular permission middleware below.
*/

use App\Http\Controllers\Api\Aems\AemsAeoController;
use App\Http\Controllers\Api\Aems\AemsAepController;
use App\Http\Controllers\Api\Aems\AemsClosureController;
use App\Http\Controllers\Api\Aems\AemsCompletionAssessmentController;
use App\Http\Controllers\Api\Aems\AemsCompletionTransferController;
use App\Http\Controllers\Api\Aems\AemsDashboardController;
use App\Http\Controllers\Api\Aems\AemsDocumentIndexController;
use App\Http\Controllers\Api\Aems\AemsEngagementController;
use App\Http\Controllers\Api\Aems\AemsEngagementLifecycleController;
use App\Http\Controllers\Api\Aems\AemsEntryConferenceController;
use App\Http\Controllers\Api\Aems\AemsEvidenceController;
use App\Http\Controllers\Api\Aems\AemsEvidenceRequestController;
use App\Http\Controllers\Api\Aems\AemsExitConferenceController;
use App\Http\Controllers\Api\Aems\AemsFieldworkController;
use App\Http\Controllers\Api\Aems\AemsFindingController;
use App\Http\Controllers\Api\Aems\AemsIssueController;
use App\Http\Controllers\Api\Aems\AemsProgramController;
use App\Http\Controllers\Api\Aems\AemsPlanningPackageController;
use App\Http\Controllers\Api\Aems\AemsReopenController;
use App\Http\Controllers\Api\Aems\AemsReportController;
use App\Http\Controllers\Api\Aems\AemsTeamController;
use App\Http\Controllers\Api\Aems\AemsTeamSafeguardController;
use App\Http\Controllers\Api\Aems\AemsWorkingPaperController;
use App\Http\Controllers\Api\Aems\AemsWorkQueueController;
use App\Http\Controllers\Api\Ais\AisContractController;
use App\Http\Controllers\Api\Ais\AisAggregationController;
use App\Http\Controllers\Api\Ais\AisReportController;
use App\Http\Controllers\Api\Shared\AuthController;
use App\Http\Controllers\Api\Armis\ArmisResourceController;
use App\Http\Controllers\Api\Armis\ArmisFoundationController;
use App\Http\Controllers\Api\Armis\ArmisCompetencyController;
use App\Http\Controllers\Api\Armis\ArmisAssignmentController;
use App\Http\Controllers\Api\Armis\ArmisPlanningController;
use App\Http\Controllers\Api\Armis\ArmisReportController;
use App\Http\Controllers\Api\Armis\ArmisProviderController;
use App\Http\Controllers\Api\Armis\ArmisProviderMonitoringController;
use App\Http\Controllers\Api\Cms\CmsActionPlanController;
use App\Http\Controllers\Api\Cms\CmsAemsEvidenceRequestController;
use App\Http\Controllers\Api\Cms\CmsAutomationController;
use App\Http\Controllers\Api\Cms\CmsClosureController;
use App\Http\Controllers\Api\Cms\CmsDashboardController;
use App\Http\Controllers\Api\Cms\CmsDispositionController;
use App\Http\Controllers\Api\Cms\CmsEscalationController;
use App\Http\Controllers\Api\Cms\CmsProgressUpdateController;
use App\Http\Controllers\Api\Cms\CmsRecommendationAssignmentController;
use App\Http\Controllers\Api\Cms\CmsRecommendationController;
use App\Http\Controllers\Api\Cms\CmsReportController;
use App\Http\Controllers\Api\Cms\CmsReopeningController;
use App\Http\Controllers\Api\Cms\CmsTargetDateExtensionController;
use App\Http\Controllers\Api\Cms\CmsValidationController;
use App\Http\Controllers\Api\Core\CoreRegistryController;
use App\Http\Controllers\Api\Core\CoreDashboardController;
use App\Http\Controllers\Api\Core\CoreAdministrativeReportController;
use App\Http\Controllers\Api\Shared\DemoAccountController;
use App\Http\Controllers\Api\Core\DocumentController;
use App\Http\Controllers\Api\Shared\HealthController;
use App\Http\Controllers\Api\Iap\IapAuditUniverseController;
use App\Http\Controllers\Api\Iap\IapBaicsController;
use App\Http\Controllers\Api\Iap\IapBaicsControlAssessmentController;
use App\Http\Controllers\Api\Iap\IapBaicsIntegrationController;
use App\Http\Controllers\Api\Iap\IapDashboardController;
use App\Http\Controllers\Api\Iap\IapEngagementController;
use App\Http\Controllers\Api\Iap\IapPlanController;
use App\Http\Controllers\Api\Iap\IapPlanPrioritizationController;
use App\Http\Controllers\Api\Iap\IapPrioritizationController;
use App\Http\Controllers\Api\Iap\IapReportController;
use App\Http\Controllers\Api\Iap\IapBaicsControlUniverseController;
use App\Http\Controllers\Api\Iap\IapResourceCapacityController;
use App\Http\Controllers\Api\Iap\IapRiskAssessmentController;
use App\Http\Controllers\Api\Iap\IapRiskPeriodController;
use App\Http\Controllers\Api\Iap\IapSchedulingController;
use App\Http\Controllers\Api\Iap\IapSupportingRecordController;
use App\Http\Controllers\Api\Iap\IapUniverseRiskAssessmentController;
use App\Http\Controllers\Api\Iap\IapWorkflowController;
use App\Http\Controllers\Api\Core\LogController;
use App\Http\Controllers\Api\Core\NotificationController;
use App\Http\Controllers\Api\Core\OfficeController;
use App\Http\Controllers\Api\Core\ProfileController;
use App\Http\Controllers\Api\Core\RecordViewController;
use App\Http\Controllers\Api\Core\ResetDemoDataController;
use App\Http\Controllers\Api\Core\RuntimeConfigurationController;
use App\Http\Controllers\Api\Iap\SiapPlanController;
use App\Http\Controllers\Api\Core\UserController;
use App\Http\Controllers\Api\Core\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::get('/runtime-configuration', RuntimeConfigurationController::class);
Route::get('/demo-accounts', DemoAccountController::class)->middleware('throttle:30,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/record-views', RecordViewController::class);

    Route::get('/ais/contract', AisContractController::class)
        ->middleware(['permission:ais.view', 'throttle:ais-read']);
    Route::get('/ais/hardening', [AisContractController::class, 'hardening'])
        ->middleware(['permission:ais.view', 'throttle:ais-read']);
    Route::get('/ais/integration-contract', [AisContractController::class, 'integration'])
        ->middleware(['permission:ais.view', 'throttle:ais-read']);
    Route::get('/ais/integration-health', [AisContractController::class, 'integrationHealth'])
        ->middleware(['permission:ais.view', 'throttle:ais-read']);
    Route::get('/ais/integration-health/snapshots', [AisContractController::class, 'integrationSnapshots'])
        ->middleware(['permission:ais.view', 'throttle:ais-read']);
    Route::post('/ais/integration-health/snapshots', [AisContractController::class, 'captureIntegrationSnapshot'])
        ->middleware(['permission:ais.view', 'throttle:ais-generate']);
    Route::get('/ais/aggregations', [AisAggregationController::class, 'overview'])
        ->middleware(['permission:ais.view', 'throttle:ais-read']);
    Route::get('/ais/dashboard', [AisAggregationController::class, 'dashboard'])
        ->middleware(['permission:ais.view', 'throttle:ais-read']);
    Route::get('/ais/alerts', [AisReportController::class, 'alerts'])
        ->middleware(['permission:ais.view', 'throttle:ais-read']);
    Route::get('/ais/reports', [AisReportController::class, 'catalog'])
        ->middleware(['permission:ais.view', 'throttle:ais-read']);
    Route::get('/ais/reports/runs', [AisReportController::class, 'runs'])
        ->middleware(['permission:ais.view', 'throttle:ais-read']);
    Route::get('/ais/reports/runs/{run}', [AisReportController::class, 'show'])
        ->middleware(['permission:ais.view', 'throttle:ais-read']);
    Route::post('/ais/reports/{report}/generate', [AisReportController::class, 'generate'])
        ->middleware(['permission:ais.view', 'throttle:ais-generate']);
    Route::post('/ais/reports/runs/{run}/exports', [AisReportController::class, 'export'])
        ->middleware(['permission:ais.export', 'throttle:ais-export']);
    Route::get('/ais/report-exports/{export}/download', [AisReportController::class, 'download'])
        ->middleware(['permission:ais.export', 'throttle:ais-export']);
    Route::get('/ais/aggregations/snapshots', [AisAggregationController::class, 'snapshots'])
        ->middleware(['permission:ais.view', 'throttle:ais-read']);
    Route::post('/ais/aggregations/snapshots', [AisAggregationController::class, 'generate'])
        ->middleware(['permission:ais.view', 'throttle:ais-generate']);

    Route::get('/dashboard', CoreDashboardController::class)
        ->middleware('permission:dashboard.view');
    Route::get('/administrative-reports', [CoreAdministrativeReportController::class, 'catalog'])
        ->middleware('permission:administrative_reports.view');
    Route::get('/administrative-reports/runs', [CoreAdministrativeReportController::class, 'runs'])
        ->middleware('permission:administrative_reports.view');
    Route::get('/administrative-reports/runs/{run}', [CoreAdministrativeReportController::class, 'show'])
        ->middleware('permission:administrative_reports.view');
    Route::post('/administrative-reports/{report}/generate', [CoreAdministrativeReportController::class, 'generate'])
        ->middleware('permission:administrative_reports.view');
    Route::post('/administrative-reports/runs/{run}/exports', [CoreAdministrativeReportController::class, 'export'])
        ->middleware('permission:administrative_reports.export');
    Route::get('/administrative-report-exports/{export}/download', [CoreAdministrativeReportController::class, 'download'])
        ->middleware('permission:administrative_reports.export');

    Route::get('/cms/dashboard', CmsDashboardController::class)
        ->middleware('permission:cms.dashboard.view');
    Route::get('/cms/reports', [CmsReportController::class, 'catalog'])
        ->middleware('permission:cms.report.view');
    Route::get('/cms/reports/runs', [CmsReportController::class, 'runs'])
        ->middleware('permission:cms.report.view');
    Route::get('/cms/reports/runs/{run}', [CmsReportController::class, 'show'])
        ->middleware('permission:cms.report.view');
    Route::post('/cms/reports/{report}/generate', [CmsReportController::class, 'generate'])
        ->middleware('permission:cms.report.view');
    Route::post('/cms/reports/runs/{run}/exports', [CmsReportController::class, 'export'])
        ->middleware('permission:cms.report.export');
    Route::get('/cms/report-exports/{export}/download', [CmsReportController::class, 'download'])
        ->middleware('permission:cms.report.export');
    Route::get('/cms/recommendations', [CmsRecommendationController::class, 'index'])
        ->middleware('permission:cms.recommendation.view');
    Route::get('/cms/recommendations/{recommendation}', [CmsRecommendationController::class, 'show'])
        ->middleware('permission:cms.recommendation.view');
    Route::get('/cms/recommendations/{recommendation}/assignments', [CmsRecommendationAssignmentController::class, 'index'])
        ->middleware('permission:cms.recommendation.view');
    Route::post('/cms/recommendations/{recommendation}/assignments', [CmsRecommendationAssignmentController::class, 'store'])
        ->middleware('permission:cms.recommendation.assign');
    Route::post('/cms/recommendations/{recommendation}/assignments/{assignment}/end', [CmsRecommendationAssignmentController::class, 'end'])
        ->middleware('permission:cms.recommendation.assign');
    Route::get('/cms/evidence-requests', [CmsAemsEvidenceRequestController::class, 'index'])
        ->middleware('permission:aems.evidence-request.view');
    Route::post('/cms/evidence-requests/{evidenceRequest}/acknowledge', [CmsAemsEvidenceRequestController::class, 'acknowledge'])
        ->middleware('permission:aems.evidence-request.acknowledge');
    Route::post('/cms/evidence-requests/{evidenceRequest}/responses', [CmsAemsEvidenceRequestController::class, 'respond'])
        ->middleware('permission:aems.evidence-request.respond');
    Route::get('/cms/recommendations/{recommendation}/action-plan', [CmsActionPlanController::class, 'forRecommendation'])
        ->middleware('permission:cms.action-plan.view');
    Route::post('/cms/recommendations/{recommendation}/action-plans', [CmsActionPlanController::class, 'store'])
        ->middleware('permission:cms.action-plan.create');
    Route::get('/cms/action-plans/{actionPlan}', [CmsActionPlanController::class, 'show'])
        ->middleware('permission:cms.action-plan.view');
    Route::put('/cms/action-plans/{actionPlan}/versions/{version}', [CmsActionPlanController::class, 'update'])
        ->middleware('permission:cms.action-plan.update');
    Route::post('/cms/action-plans/{actionPlan}/versions/{version}/transitions/submit', [CmsActionPlanController::class, 'submit'])
        ->middleware('permission:cms.action-plan.submit');
    Route::post('/cms/action-plans/{actionPlan}/versions/{version}/transitions/start-review', [CmsActionPlanController::class, 'startReview'])
        ->middleware('permission:cms.action-plan.review');
    Route::post('/cms/action-plans/{actionPlan}/versions/{version}/transitions/return', [CmsActionPlanController::class, 'return'])
        ->middleware('permission:cms.action-plan.return');
    Route::post('/cms/action-plans/{actionPlan}/versions/{version}/transitions/accept', [CmsActionPlanController::class, 'accept'])
        ->middleware('permission:cms.action-plan.accept');
    Route::post('/cms/action-plans/{actionPlan}/versions/{version}/revisions', [CmsActionPlanController::class, 'revise'])
        ->middleware('permission:cms.action-plan.revise');
    Route::get('/cms/recommendations/{recommendation}/progress-updates', [CmsProgressUpdateController::class, 'forRecommendation'])
        ->middleware('permission:cms.progress.view');
    Route::post('/cms/recommendations/{recommendation}/progress-updates', [CmsProgressUpdateController::class, 'store'])
        ->middleware('permission:cms.progress.create');
    Route::get('/cms/progress-updates/{progressUpdate}', [CmsProgressUpdateController::class, 'show'])
        ->middleware('permission:cms.progress.view');
    Route::put('/cms/progress-updates/{progressUpdate}/versions/{version}', [CmsProgressUpdateController::class, 'update'])
        ->middleware('permission:cms.progress.update');
    Route::post('/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/submit', [CmsProgressUpdateController::class, 'submit'])
        ->middleware('permission:cms.progress.submit');
    Route::post('/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/start-review', [CmsProgressUpdateController::class, 'startReview'])
        ->middleware('permission:cms.progress.review');
    Route::post('/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/return', [CmsProgressUpdateController::class, 'return'])
        ->middleware('permission:cms.progress.return');
    Route::post('/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/record', [CmsProgressUpdateController::class, 'record'])
        ->middleware('permission:cms.progress.record');
    Route::post('/cms/progress-updates/{progressUpdate}/versions/{version}/revisions', [CmsProgressUpdateController::class, 'revise'])
        ->middleware('permission:cms.progress.revise');
    Route::post('/cms/progress-updates/{progressUpdate}/versions/{version}/evidence', [CmsProgressUpdateController::class, 'uploadEvidence'])
        ->middleware('permission:cms.evidence.upload');
    Route::get('/cms/progress-evidence/{evidence}/download', [CmsProgressUpdateController::class, 'downloadEvidence'])
        ->middleware('permission:cms.evidence.download');
    Route::delete('/cms/progress-evidence/{evidence}', [CmsProgressUpdateController::class, 'removeEvidence'])
        ->middleware('permission:cms.evidence.remove_draft');
    Route::get('/cms/recommendations/{recommendation}/validations', [CmsValidationController::class, 'forRecommendation'])
        ->middleware('permission:cms.validation.view');
    Route::get('/cms/recommendations/{recommendation}/validation-options', [CmsValidationController::class, 'validationOptions'])
        ->middleware('permission:cms.validation.create');
    Route::post('/cms/recommendations/{recommendation}/validations', [CmsValidationController::class, 'store'])
        ->middleware('permission:cms.validation.create');
    Route::get('/cms/validations/{validation}', [CmsValidationController::class, 'show'])
        ->middleware('permission:cms.validation.view');
    Route::get('/cms/validations/{validation}/assignments', [CmsValidationController::class, 'assignments'])
        ->middleware('permission:cms.validation.view');
    Route::post('/cms/validations/{validation}/assignments', [CmsValidationController::class, 'assign'])
        ->middleware('permission:cms.validation.assign');
    Route::post('/cms/validations/{validation}/assignments/{assignment}/end', [CmsValidationController::class, 'endAssignment'])
        ->middleware('permission:cms.validation.assign');
    Route::put('/cms/validations/{validation}/versions/{version}', [CmsValidationController::class, 'update'])
        ->middleware('permission:cms.validation.update');
    Route::post('/cms/validations/{validation}/versions/{version}/transitions/submit', [CmsValidationController::class, 'submit'])
        ->middleware('permission:cms.validation.submit');
    Route::post('/cms/validations/{validation}/versions/{version}/transitions/start-review', [CmsValidationController::class, 'startReview'])
        ->middleware('permission:cms.validation.review');
    Route::post('/cms/validations/{validation}/versions/{version}/transitions/return', [CmsValidationController::class, 'return'])
        ->middleware('permission:cms.validation.return');
    Route::post('/cms/validations/{validation}/versions/{version}/transitions/finalize', [CmsValidationController::class, 'finalize'])
        ->middleware('permission:cms.validation.finalize');
    Route::post('/cms/validations/{validation}/versions/{version}/revisions', [CmsValidationController::class, 'revise'])
        ->middleware('permission:cms.validation.revise');
    Route::post('/cms/validations/{validation}/versions/{version}/evidence', [CmsValidationController::class, 'uploadEvidence'])
        ->middleware('permission:cms.validation-evidence.upload');
    Route::get('/cms/validation-evidence/{evidence}/download', [CmsValidationController::class, 'downloadEvidence'])
        ->middleware('permission:cms.validation-evidence.download');
    Route::delete('/cms/validation-evidence/{evidence}', [CmsValidationController::class, 'removeEvidence'])
        ->middleware('permission:cms.validation-evidence.remove_draft');

    Route::get('/cms/recommendations/{recommendation}/extensions', [CmsTargetDateExtensionController::class, 'forRecommendation'])
        ->middleware('permission:cms.extension.view');
    Route::get('/cms/recommendations/{recommendation}/extensions/history', [CmsTargetDateExtensionController::class, 'history'])
        ->middleware('permission:cms.extension.view');
    Route::get('/cms/recommendations/{recommendation}/extension-options', [CmsTargetDateExtensionController::class, 'options'])
        ->middleware('permission:cms.extension.create');
    Route::post('/cms/recommendations/{recommendation}/extensions', [CmsTargetDateExtensionController::class, 'store'])
        ->middleware('permission:cms.extension.create');
    Route::get('/cms/extensions/{extension}', [CmsTargetDateExtensionController::class, 'show'])
        ->middleware('permission:cms.extension.view');
    Route::put('/cms/extensions/{extension}/versions/{version}', [CmsTargetDateExtensionController::class, 'update'])
        ->middleware('permission:cms.extension.update');
    Route::post('/cms/extensions/{extension}/versions/{version}/transitions/submit', [CmsTargetDateExtensionController::class, 'submit'])
        ->middleware('permission:cms.extension.submit');
    Route::post('/cms/extensions/{extension}/versions/{version}/transitions/start-review', [CmsTargetDateExtensionController::class, 'startReview'])
        ->middleware('permission:cms.extension.review');
    Route::post('/cms/extensions/{extension}/versions/{version}/transitions/return', [CmsTargetDateExtensionController::class, 'return'])
        ->middleware('permission:cms.extension.return');
    Route::post('/cms/extensions/{extension}/versions/{version}/transitions/recommend', [CmsTargetDateExtensionController::class, 'recommend'])
        ->middleware('permission:cms.extension.recommend');
    Route::post('/cms/extensions/{extension}/versions/{version}/transitions/approve', [CmsTargetDateExtensionController::class, 'approve'])
        ->middleware('permission:cms.extension.approve');
    Route::post('/cms/extensions/{extension}/versions/{version}/transitions/reject', [CmsTargetDateExtensionController::class, 'reject'])
        ->middleware('permission:cms.extension.reject');
    Route::post('/cms/extensions/{extension}/versions/{version}/revisions', [CmsTargetDateExtensionController::class, 'revise'])
        ->middleware('permission:cms.extension.revise');
    Route::post('/cms/extensions/{extension}/versions/{version}/evidence', [CmsTargetDateExtensionController::class, 'uploadEvidence'])
        ->middleware('permission:cms.extension-evidence.upload');
    Route::get('/cms/extension-evidence/{evidence}/download', [CmsTargetDateExtensionController::class, 'downloadEvidence'])
        ->middleware('permission:cms.extension-evidence.download');
    Route::delete('/cms/extension-evidence/{evidence}', [CmsTargetDateExtensionController::class, 'removeEvidence'])
        ->middleware('permission:cms.extension-evidence.remove_draft');

    Route::get('/cms/recommendations/{recommendation}/escalations', [CmsEscalationController::class, 'forRecommendation'])
        ->middleware('permission:cms.escalation.view');
    Route::get('/cms/recommendations/{recommendation}/escalation-options', [CmsEscalationController::class, 'options'])
        ->middleware('permission:cms.escalation.create');
    Route::post('/cms/recommendations/{recommendation}/escalations', [CmsEscalationController::class, 'store'])
        ->middleware('permission:cms.escalation.create');
    Route::get('/cms/escalations/{escalation}', [CmsEscalationController::class, 'show'])
        ->middleware('permission:cms.escalation.view');
    Route::put('/cms/escalations/{escalation}/notice-versions/{version}', [CmsEscalationController::class, 'update'])
        ->middleware('permission:cms.escalation.update');
    Route::post('/cms/escalations/{escalation}/notice-versions/{version}/transitions/submit', [CmsEscalationController::class, 'submit'])
        ->middleware('permission:cms.escalation.submit');
    Route::post('/cms/escalations/{escalation}/notice-versions/{version}/transitions/start-review', [CmsEscalationController::class, 'startReview'])
        ->middleware('permission:cms.escalation.review');
    Route::post('/cms/escalations/{escalation}/notice-versions/{version}/transitions/return', [CmsEscalationController::class, 'returnNotice'])
        ->middleware('permission:cms.escalation.return');
    Route::post('/cms/escalations/{escalation}/notice-versions/{version}/transitions/issue', [CmsEscalationController::class, 'issue'])
        ->middleware('permission:cms.escalation.issue');
    Route::post('/cms/escalations/{escalation}/notice-versions/{version}/revisions', [CmsEscalationController::class, 'revise'])
        ->middleware('permission:cms.escalation.revise');
    Route::post('/cms/escalations/{escalation}/acknowledgements', [CmsEscalationController::class, 'acknowledge'])
        ->middleware('permission:cms.escalation.acknowledge');
    Route::get('/cms/escalations/{escalation}/response', [CmsEscalationController::class, 'response'])
        ->middleware('permission:cms.escalation.view');
    Route::post('/cms/escalations/{escalation}/response', [CmsEscalationController::class, 'createResponse'])
        ->middleware('permission:cms.escalation.respond');
    Route::put('/cms/escalation-responses/{response}/versions/{version}', [CmsEscalationController::class, 'updateResponse'])
        ->middleware('permission:cms.escalation.respond');
    Route::post('/cms/escalation-responses/{response}/versions/{version}/transitions/submit', [CmsEscalationController::class, 'submitResponse'])
        ->middleware('permission:cms.escalation.respond');
    Route::post('/cms/escalation-responses/{response}/versions/{version}/transitions/start-review', [CmsEscalationController::class, 'startResponseReview'])
        ->middleware('permission:cms.escalation.response-review');
    Route::post('/cms/escalation-responses/{response}/versions/{version}/transitions/return', [CmsEscalationController::class, 'returnResponse'])
        ->middleware('permission:cms.escalation.response-return');
    Route::post('/cms/escalation-responses/{response}/versions/{version}/transitions/accept', [CmsEscalationController::class, 'acceptResponse'])
        ->middleware('permission:cms.escalation.response-accept');
    Route::post('/cms/escalation-responses/{response}/versions/{version}/revisions', [CmsEscalationController::class, 'reviseResponse'])
        ->middleware('permission:cms.escalation.revise');
    Route::post('/cms/escalations/{escalation}/resolve', [CmsEscalationController::class, 'resolve'])
        ->middleware('permission:cms.escalation.resolve');
    Route::post('/cms/escalations/{escalation}/notice-versions/{version}/evidence', [CmsEscalationController::class, 'uploadNoticeEvidence'])
        ->middleware('permission:cms.escalation-evidence.upload');
    Route::get('/cms/escalation-notice-evidence/{evidence}/download', [CmsEscalationController::class, 'downloadNoticeEvidence'])
        ->middleware('permission:cms.escalation-evidence.download');
    Route::delete('/cms/escalation-notice-evidence/{evidence}', [CmsEscalationController::class, 'removeNoticeEvidence'])
        ->middleware('permission:cms.escalation-evidence.remove_draft');
    Route::post('/cms/escalation-responses/{response}/versions/{version}/evidence', [CmsEscalationController::class, 'uploadResponseEvidence'])
        ->middleware('permission:cms.escalation-evidence.upload');
    Route::get('/cms/escalation-response-evidence/{evidence}/download', [CmsEscalationController::class, 'downloadResponseEvidence'])
        ->middleware('permission:cms.escalation-evidence.download');
    Route::delete('/cms/escalation-response-evidence/{evidence}', [CmsEscalationController::class, 'removeResponseEvidence'])
        ->middleware('permission:cms.escalation-evidence.remove_draft');

    Route::get('/cms/recommendations/{recommendation}/closure-requests', [CmsClosureController::class, 'index'])->middleware('permission:cms.closure.view');
    Route::get('/cms/recommendations/{recommendation}/closure-options', [CmsClosureController::class, 'options'])->middleware('permission:cms.closure.request');
    Route::post('/cms/recommendations/{recommendation}/closure-requests', [CmsClosureController::class, 'store'])->middleware('permission:cms.closure.request');
    Route::get('/cms/closure-requests/{closureRequest}', [CmsClosureController::class, 'show'])->middleware('permission:cms.closure.view');
    Route::put('/cms/closure-requests/{closureRequest}/versions/{version}', [CmsClosureController::class, 'update'])->middleware('permission:cms.closure.update');
    Route::post('/cms/closure-requests/{closureRequest}/versions/{version}/transitions/submit', [CmsClosureController::class, 'submit'])->middleware('permission:cms.closure.submit');
    Route::post('/cms/closure-requests/{closureRequest}/versions/{version}/transitions/start-review', [CmsClosureController::class, 'startReview'])->middleware('permission:cms.closure.review');
    Route::post('/cms/closure-requests/{closureRequest}/versions/{version}/transitions/return', [CmsClosureController::class, 'returnVersion'])->middleware('permission:cms.closure.return');
    Route::post('/cms/closure-requests/{closureRequest}/versions/{version}/transitions/recommend', [CmsClosureController::class, 'recommend'])->middleware('permission:cms.closure.recommend');
    Route::post('/cms/closure-requests/{closureRequest}/versions/{version}/transitions/approve', [CmsClosureController::class, 'approve'])->middleware('permission:cms.closure.approve');
    Route::post('/cms/closure-requests/{closureRequest}/versions/{version}/transitions/reject', [CmsClosureController::class, 'reject'])->middleware('permission:cms.closure.reject');
    Route::post('/cms/closure-requests/{closureRequest}/versions/{version}/revisions', [CmsClosureController::class, 'revise'])->middleware('permission:cms.closure.revise');
    Route::post('/cms/closure-requests/{closureRequest}/versions/{version}/evidence', [CmsClosureController::class, 'uploadEvidence'])->middleware('permission:cms.closure-evidence.upload');
    Route::delete('/cms/closure-evidence/{evidence}', [CmsClosureController::class, 'removeEvidence'])->middleware('permission:cms.closure-evidence.remove_draft');
    Route::get('/cms/closure-evidence/{evidence}/download', [CmsClosureController::class, 'downloadEvidence'])->middleware('permission:cms.closure-evidence.download');

    Route::get('/cms/recommendations/{recommendation}/dispositions', [CmsDispositionController::class, 'index'])->middleware('permission:cms.disposition.view');
    Route::get('/cms/recommendations/{recommendation}/disposition-options', [CmsDispositionController::class, 'options'])->middleware('permission:cms.disposition.request');
    Route::post('/cms/recommendations/{recommendation}/dispositions', [CmsDispositionController::class, 'store'])->middleware('permission:cms.disposition.request');
    Route::get('/cms/disposition-requests/{dispositionRequest}', [CmsDispositionController::class, 'show'])->middleware('permission:cms.disposition.view');
    Route::put('/cms/disposition-requests/{dispositionRequest}/versions/{version}', [CmsDispositionController::class, 'update'])->middleware('permission:cms.disposition.update');
    Route::post('/cms/disposition-requests/{dispositionRequest}/versions/{version}/transitions/submit', [CmsDispositionController::class, 'submit'])->middleware('permission:cms.disposition.submit');
    Route::post('/cms/disposition-requests/{dispositionRequest}/versions/{version}/transitions/start-review', [CmsDispositionController::class, 'startReview'])->middleware('permission:cms.disposition.review');
    Route::post('/cms/disposition-requests/{dispositionRequest}/versions/{version}/transitions/return', [CmsDispositionController::class, 'returnVersion'])->middleware('permission:cms.disposition.return');
    Route::post('/cms/disposition-requests/{dispositionRequest}/versions/{version}/transitions/recommend', [CmsDispositionController::class, 'recommend'])->middleware('permission:cms.disposition.review');
    Route::post('/cms/disposition-requests/{dispositionRequest}/versions/{version}/transitions/approve', [CmsDispositionController::class, 'approve'])->middleware('permission:cms.disposition.approve');
    Route::post('/cms/disposition-requests/{dispositionRequest}/versions/{version}/transitions/reject', [CmsDispositionController::class, 'reject'])->middleware('permission:cms.disposition.reject');
    Route::post('/cms/disposition-requests/{dispositionRequest}/versions/{version}/revisions', [CmsDispositionController::class, 'revise'])->middleware('permission:cms.disposition.revise');
    Route::post('/cms/disposition-requests/{dispositionRequest}/versions/{version}/evidence', [CmsDispositionController::class, 'uploadEvidence'])->middleware('permission:cms.disposition-evidence.upload');
    Route::delete('/cms/disposition-evidence/{evidence}', [CmsDispositionController::class, 'removeEvidence'])->middleware('permission:cms.disposition-evidence.remove_draft');
    Route::get('/cms/disposition-evidence/{evidence}/download', [CmsDispositionController::class, 'downloadEvidence'])->middleware('permission:cms.disposition-evidence.download');

    Route::get('/cms/recommendations/{recommendation}/reopenings', [CmsReopeningController::class, 'index'])->middleware('permission:cms.reopening.view');
    Route::get('/cms/recommendations/{recommendation}/reopening-options', [CmsReopeningController::class, 'options'])->middleware('permission:cms.reopening.request');
    Route::post('/cms/recommendations/{recommendation}/reopenings', [CmsReopeningController::class, 'store'])->middleware('permission:cms.reopening.request');
    Route::get('/cms/reopening-requests/{reopeningRequest}', [CmsReopeningController::class, 'show'])->middleware('permission:cms.reopening.view');
    Route::put('/cms/reopening-requests/{reopeningRequest}/versions/{version}', [CmsReopeningController::class, 'update'])->middleware('permission:cms.reopening.update');
    Route::post('/cms/reopening-requests/{reopeningRequest}/versions/{version}/transitions/submit', [CmsReopeningController::class, 'submit'])->middleware('permission:cms.reopening.submit');
    Route::post('/cms/reopening-requests/{reopeningRequest}/versions/{version}/transitions/start-review', [CmsReopeningController::class, 'startReview'])->middleware('permission:cms.reopening.review');
    Route::post('/cms/reopening-requests/{reopeningRequest}/versions/{version}/transitions/return', [CmsReopeningController::class, 'returnVersion'])->middleware('permission:cms.reopening.return');
    Route::post('/cms/reopening-requests/{reopeningRequest}/versions/{version}/transitions/recommend', [CmsReopeningController::class, 'recommend'])->middleware('permission:cms.reopening.recommend');
    Route::post('/cms/reopening-requests/{reopeningRequest}/versions/{version}/transitions/approve', [CmsReopeningController::class, 'approve'])->middleware('permission:cms.reopening.approve');
    Route::post('/cms/reopening-requests/{reopeningRequest}/versions/{version}/transitions/reject', [CmsReopeningController::class, 'reject'])->middleware('permission:cms.reopening.reject');
    Route::post('/cms/reopening-requests/{reopeningRequest}/versions/{version}/revisions', [CmsReopeningController::class, 'revise'])->middleware('permission:cms.reopening.revise');
    Route::post('/cms/reopening-requests/{reopeningRequest}/versions/{version}/evidence', [CmsReopeningController::class, 'uploadEvidence'])->middleware('permission:cms.reopening-evidence.upload');
    Route::delete('/cms/reopening-evidence/{evidence}', [CmsReopeningController::class, 'removeEvidence'])->middleware('permission:cms.reopening-evidence.remove_draft');
    Route::get('/cms/reopening-evidence/{evidence}/download', [CmsReopeningController::class, 'downloadEvidence'])->middleware('permission:cms.reopening-evidence.download');

    Route::get('/cms/automation/rules', [CmsAutomationController::class, 'rules'])->middleware('permission:cms.automation.view');
    Route::post('/cms/automation/rules', [CmsAutomationController::class, 'storeRule'])->middleware('permission:cms.automation.manage');
    Route::put('/cms/automation/rules/{rule}', [CmsAutomationController::class, 'updateRule'])->middleware('permission:cms.automation.manage');
    Route::post('/cms/automation/run', [CmsAutomationController::class, 'run'])->middleware('permission:cms.automation.run');
    Route::get('/cms/automation/runs', [CmsAutomationController::class, 'runs'])->middleware('permission:cms.automation.view');
    Route::get('/cms/automation/dashboard', [CmsAutomationController::class, 'dashboard'])->middleware('permission:cms.automation.view');
    Route::get('/cms/automation/candidates', [CmsAutomationController::class, 'candidates'])->middleware('permission:cms.automation.view');
    Route::post('/cms/automation/closure-candidates/{candidate}/review', [CmsAutomationController::class, 'reviewClosureCandidate'])->middleware('permission:cms.automation.review');
    Route::post('/cms/automation/escalation-candidates/{candidate}/review', [CmsAutomationController::class, 'reviewEscalationCandidate'])->middleware('permission:cms.automation.review');
    Route::get('/cms/recommendations/{recommendation}/closure-readiness', [CmsAutomationController::class, 'readiness'])->middleware('permission:cms.automation.view');

    Route::get('/profile', [ProfileController::class, 'show'])
        ->middleware('permission:profile.view');
    Route::put('/profile', [ProfileController::class, 'update'])
        ->middleware('permission:profile.update');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])
        ->middleware('permission:profile.change_password');

    Route::get('/offices', [OfficeController::class, 'index'])
        ->middleware('permission:offices.view');
    Route::post('/offices', [OfficeController::class, 'store'])
        ->middleware('permission:offices.create');
    Route::put('/offices/{office}', [OfficeController::class, 'update'])
        ->middleware('permission:offices.update');
    Route::delete('/offices/{office}', [OfficeController::class, 'destroy'])
        ->middleware('permission:offices.delete');
    Route::post('/offices/{office}/restore', [OfficeController::class, 'restore'])
        ->middleware('permission:offices.restore');

    Route::get('/audit-areas', [CoreRegistryController::class, 'auditAreas'])
        ->middleware('permission:audit_areas.view');
    Route::post('/audit-areas', [CoreRegistryController::class, 'storeAuditArea'])
        ->middleware('permission:audit_areas.create');
    Route::put('/audit-areas/{auditArea}', [CoreRegistryController::class, 'updateAuditArea'])
        ->middleware('permission:audit_areas.update');
    Route::delete('/audit-areas/{auditArea}', [CoreRegistryController::class, 'destroyAuditArea'])
        ->middleware('permission:audit_areas.delete');
    Route::post('/audit-areas/{auditArea}/restore', [CoreRegistryController::class, 'restoreAuditArea'])
        ->middleware('permission:audit_areas.restore');

    Route::get('/audit-focuses', [CoreRegistryController::class, 'auditFocuses'])
        ->middleware('permission:audit_focus.view');
    Route::post('/audit-focuses', [CoreRegistryController::class, 'storeAuditFocus'])
        ->middleware('permission:audit_focus.create');
    Route::put('/audit-focuses/{auditFocus}', [CoreRegistryController::class, 'updateAuditFocus'])
        ->middleware('permission:audit_focus.update');
    Route::delete('/audit-focuses/{auditFocus}', [CoreRegistryController::class, 'destroyAuditFocus'])
        ->middleware('permission:audit_focus.delete');
    Route::post('/audit-focuses/{auditFocus}/restore', [CoreRegistryController::class, 'restoreAuditFocus'])
        ->middleware('permission:audit_focus.restore');

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.view');
    Route::get('/users/{user}', [UserController::class, 'show'])
        ->middleware('permission:users.view');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:users.create');
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.update');
    Route::delete('/users/{user}', [UserController::class, 'archive'])
        ->middleware('permission:users.archive');
    Route::post('/users/{user}/activate', [UserController::class, 'activate'])
        ->middleware('permission:users.activate');
    Route::post('/users/{user}/disable', [UserController::class, 'disable'])
        ->middleware('permission:users.deactivate');
    Route::post('/users/{user}/lock', [UserController::class, 'lock'])
        ->middleware('permission:users.lock');
    Route::post('/users/{user}/unlock', [UserController::class, 'unlock'])
        ->middleware('permission:users.unlock');
    Route::post('/users/{user}/restore', [UserController::class, 'restore'])
        ->middleware('permission:users.restore');
    Route::put('/users/{user}/password', [UserController::class, 'resetPassword'])
        ->middleware('permission:users.reset_password');

    Route::get('/roles', [CoreRegistryController::class, 'roles'])
        ->middleware('permission:roles.view');
    Route::post('/roles', [CoreRegistryController::class, 'storeRole'])
        ->middleware('permission:roles.create');
    Route::post('/roles/{role}/clone', [CoreRegistryController::class, 'cloneRole'])
        ->middleware('permission:roles.clone');
    Route::put('/roles/{role}', [CoreRegistryController::class, 'updateRole'])
        ->middleware('permission:roles.update');
    Route::delete('/roles/{role}', [CoreRegistryController::class, 'destroyRole'])
        ->middleware('permission:roles.delete');
    Route::post('/roles/{role}/restore', [CoreRegistryController::class, 'restoreRole'])
        ->middleware('permission:roles.restore');
    Route::get('/permissions', [CoreRegistryController::class, 'permissions'])
        ->middleware('permission:permissions.view');
    Route::get('/master-lists', [CoreRegistryController::class, 'masterLists'])
        ->middleware('permission:master_lists.view');
    Route::post('/master-lists', [CoreRegistryController::class, 'storeMasterList'])
        ->middleware('permission:master_lists.manage');
    Route::put('/master-lists/{masterList}', [CoreRegistryController::class, 'updateMasterList'])
        ->middleware('permission:master_lists.manage');
    Route::get('/system-configurations', [CoreRegistryController::class, 'configurations'])
        ->middleware('permission:system_configuration.view');
    Route::put('/system-configurations', [CoreRegistryController::class, 'updateConfigurations'])
        ->middleware('permission:system_configuration.manage');
    Route::post('/system-configurations/logo', [CoreRegistryController::class, 'uploadLogo'])
        ->middleware('permission:system_configuration.manage');
    Route::post('/system-configurations/test-email', [CoreRegistryController::class, 'testEmail'])
        ->middleware('permission:system_configuration.manage');
    Route::get('/activity-logs', [LogController::class, 'activities'])
        ->middleware('permission:activity_logs.view');
    Route::get('/activity-logs/export', [LogController::class, 'exportActivities'])
        ->middleware('permission:activity_logs.export');
    Route::get('/audit-logs', [LogController::class, 'audits'])
        ->middleware('permission:audit_logs.view');
    Route::get('/audit-logs/export', [LogController::class, 'exportAudits'])
        ->middleware('permission:audit_logs.export');

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->middleware('permission:notifications.view');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])
        ->middleware('permission:notifications.view');
    Route::post('/notifications', [NotificationController::class, 'deliver'])
        ->middleware('permission:notifications.manage');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->middleware('permission:notifications.view');
    Route::put('/notifications/preferences', [NotificationController::class, 'preferences'])
        ->middleware('permission:notifications.view');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->middleware('permission:notifications.view');
    Route::post('/notifications/{notification}/unread', [NotificationController::class, 'unread'])
        ->middleware('permission:notifications.view');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'archive'])
        ->middleware('permission:notifications.view');
    Route::post('/notifications/{notification}/restore', [NotificationController::class, 'restore'])
        ->middleware('permission:notifications.view');

    Route::get('/workflows', [WorkflowController::class, 'index'])
        ->middleware('permission:workflows.view');
    Route::get('/workflows/{workflow}', [WorkflowController::class, 'show'])
        ->middleware('permission:workflows.view');
    Route::post('/workflows', [WorkflowController::class, 'store'])
        ->middleware('permission:workflows.create');
    Route::put('/workflows/{workflow}', [WorkflowController::class, 'update'])
        ->middleware('permission:workflows.update');
    Route::post('/workflows/{workflow}/publish', [WorkflowController::class, 'publish'])
        ->middleware('permission:workflows.publish');
    Route::post('/workflows/{workflow}/revisions', [WorkflowController::class, 'revision'])
        ->middleware('permission:workflows.create');
    Route::delete('/workflows/{workflow}', [WorkflowController::class, 'destroy'])
        ->middleware('permission:workflows.archive');
    Route::post('/workflows/{workflow}/restore', [WorkflowController::class, 'restore'])
        ->middleware('permission:workflows.restore');
    Route::post('/workflow-instances', [WorkflowController::class, 'start'])
        ->middleware('permission:workflows.start');
    Route::get('/workflow-instances/{instance}', [WorkflowController::class, 'instance'])
        ->middleware('permission:workflows.monitor');
    Route::post('/workflow-instances/{instance}/transitions/{action}', [WorkflowController::class, 'transition'])
        ->middleware('permission:workflows.act');
    Route::post('/workflow-instances/{instance}/cancel', [WorkflowController::class, 'cancel'])
        ->middleware('permission:workflows.act');

    Route::get('/aems/engagements', [AemsEngagementController::class, 'index'])
        ->middleware('permission:aems.engagement.view');
    Route::get('/aems/dashboard', AemsDashboardController::class)
        ->middleware('permission:aems.engagement.view');
    Route::get('/aems/integrations/status', [AemsDashboardController::class, 'status'])
        ->middleware('permission:aems.engagement.view');
    Route::get('/aems/dashboard/export', [AemsDashboardController::class, 'export'])
        ->middleware('permission:aems.engagement.export');
    Route::get('/aems/dashboard/queues/export', [AemsDashboardController::class, 'exportQueues'])
        ->middleware('permission:aems.engagement.export');
    Route::get('/aems/engagements/import-options', [AemsEngagementController::class, 'importOptions'])
        ->middleware('permission:aems.engagement.create');
    Route::post('/aems/engagements/import', [AemsEngagementController::class, 'import'])
        ->middleware('permission:aems.engagement.create');
    Route::post('/aems/engagements', [AemsEngagementController::class, 'store'])
        ->middleware('permission:aems.engagement.create');
    Route::get('/aems/engagements/{engagement}/scope', [AemsEngagementController::class, 'scope'])
        ->middleware('permission:aems.foundation.view');
    Route::put('/aems/engagements/{engagement}/scope', [AemsEngagementController::class, 'updateScope'])
        ->middleware('permission:aems.foundation.manage_scope');
    Route::get('/aems/engagements/{engagement}/team', [AemsTeamController::class, 'show'])
        ->middleware('permission:aems.team.view');
    Route::get('/aems/engagements/{engagement}/team/safeguards', [AemsTeamSafeguardController::class, 'show'])
        ->middleware('permission:aems.team.safeguard_view');
    Route::post('/aems/engagements/{engagement}/team/safeguards/assess', [AemsTeamSafeguardController::class, 'assess'])
        ->middleware('permission:aems.team.safeguard_review');
    Route::post('/aems/engagements/{engagement}/team/safeguards/approve', [AemsTeamSafeguardController::class, 'approve'])
        ->middleware('permission:aems.team.safeguard_approve');
    Route::post('/aems/engagements/{engagement}/team', [AemsTeamController::class, 'store'])
        ->middleware('permission:aems.team.assign');
    Route::post('/aems/engagements/{engagement}/team/{teamMember}/safeguards/declarations', [AemsTeamSafeguardController::class, 'storeDeclaration'])
        ->middleware('permission:aems.team.safeguard_declare');
    Route::post('/aems/engagements/{engagement}/team/{teamMember}/safeguards/declarations/{declaration}/review', [AemsTeamSafeguardController::class, 'reviewDeclaration'])
        ->middleware('permission:aems.team.safeguard_review');
    Route::put('/aems/engagements/{engagement}/team/{teamMember}', [AemsTeamController::class, 'update'])
        ->middleware('permission:aems.team.assign');
    Route::post('/aems/engagements/{engagement}/team/{teamMember}/reassign', [AemsTeamController::class, 'reassign'])
        ->middleware('permission:aems.team.reassign');
    Route::delete('/aems/engagements/{engagement}/team/{teamMember}', [AemsTeamController::class, 'destroy'])
        ->middleware('permission:aems.team.assign');
    Route::get('/aems/engagements/{engagement}/aeo', [AemsAeoController::class, 'show'])
        ->middleware('permission:aems.aeo.view');
    Route::post('/aems/engagements/{engagement}/aeo', [AemsAeoController::class, 'store'])
        ->middleware('permission:aems.aeo.prepare');
    Route::put('/aems/engagements/{engagement}/aeo/{order}', [AemsAeoController::class, 'update'])
        ->middleware('permission:aems.aeo.prepare');
    Route::post('/aems/engagements/{engagement}/aeo/{order}/transition', [AemsAeoController::class, 'transition'])
        ->middleware('permission:aems.aeo.view');
    Route::post('/aems/engagements/{engagement}/aeo/{order}/revise', [AemsAeoController::class, 'revise'])
        ->middleware('permission:aems.aeo.revise');
    Route::post('/aems/engagements/{engagement}/aeo/{order}/amend', [AemsAeoController::class, 'amend'])
        ->middleware('permission:aems.aeo.amend');
    Route::get('/aems/engagements/{engagement}/aeo/{order}/distribution', [AemsAeoController::class, 'distribution'])
        ->middleware('permission:aems.aeo.view');
    Route::post('/aems/engagements/{engagement}/aeo/{order}/distribution', [AemsAeoController::class, 'distribute'])
        ->middleware('permission:aems.aeo.distribute');
    Route::post('/aems/engagements/{engagement}/aeo/{order}/distribution/{distribution}/acknowledge', [AemsAeoController::class, 'acknowledgeDistribution'])
        ->middleware('permission:aems.aeo.acknowledge');
    Route::get('/aems/engagements/{engagement}/aeo/{order}/pdf', [AemsAeoController::class, 'pdf'])
        ->middleware('permission:aems.aeo.view');
    Route::get('/aems/aeo-acknowledgements', [AemsAeoController::class, 'recipientAcknowledgements'])
        ->middleware('permission:aems.aeo.acknowledge');
    Route::post('/aems/aeo-acknowledgements/{distribution}/acknowledge', [AemsAeoController::class, 'acknowledgeRecipient'])
        ->middleware('permission:aems.aeo.acknowledge');
    Route::get('/aems/aeo-acknowledgements/{distribution}/pdf', [AemsAeoController::class, 'recipientPdf'])
        ->middleware('permission:aems.aeo.acknowledge');
    Route::get('/aems/engagements/{engagement}/aep', [AemsAepController::class, 'show'])
        ->middleware('permission:aems.aep.view');
    Route::post('/aems/engagements/{engagement}/aep', [AemsAepController::class, 'store'])
        ->middleware('permission:aems.aep.create');
    Route::put('/aems/engagements/{engagement}/aep/{plan}', [AemsAepController::class, 'update'])
        ->middleware('permission:aems.aep.create');
    Route::post('/aems/engagements/{engagement}/aep/{plan}/transition', [AemsAepController::class, 'transition'])
        ->middleware('permission:aems.aep.view');
    Route::post('/aems/engagements/{engagement}/aep/{plan}/revise', [AemsAepController::class, 'revise'])
        ->middleware('permission:aems.aep.revise');
    Route::get('/aems/engagements/{engagement}/planning-package', [AemsPlanningPackageController::class, 'show'])
        ->middleware('permission:aems.planning-package.view');
    Route::post('/aems/engagements/{engagement}/planning-package', [AemsPlanningPackageController::class, 'store'])
        ->middleware('permission:aems.planning-package.create');
    Route::put('/aems/engagements/{engagement}/planning-package/{package}', [AemsPlanningPackageController::class, 'update'])
        ->middleware('permission:aems.planning-package.update');
    Route::post('/aems/engagements/{engagement}/planning-package/{package}/transition', [AemsPlanningPackageController::class, 'transition'])
        ->middleware('permission:aems.planning-package.view');
    Route::post('/aems/engagements/{engagement}/planning-package/{package}/revise', [AemsPlanningPackageController::class, 'revise'])
        ->middleware('permission:aems.planning-package.revise');
    Route::get('/aems/engagements/{engagement}/programs', [AemsProgramController::class, 'index'])
        ->middleware('permission:aems.program.view');
    Route::post('/aems/engagements/{engagement}/programs', [AemsProgramController::class, 'store'])
        ->middleware('permission:aems.program.manage');
    Route::put('/aems/engagements/{engagement}/programs/{program}', [AemsProgramController::class, 'update'])
        ->middleware('permission:aems.program.manage');
    Route::post('/aems/engagements/{engagement}/programs/{program}/transition', [AemsProgramController::class, 'transition'])
        ->middleware('permission:aems.program.view');
    Route::post('/aems/engagements/{engagement}/programs/{program}/revise', [AemsProgramController::class, 'revise'])
        ->middleware('permission:aems.program.approve');
    Route::post('/aems/engagements/{engagement}/programs/{program}/procedures', [AemsProgramController::class, 'storeProcedure'])
        ->middleware('permission:aems.program.manage');
    Route::put('/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}', [AemsProgramController::class, 'updateProcedure'])
        ->middleware('permission:aems.program.manage');
    Route::delete('/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}', [AemsProgramController::class, 'destroyProcedure'])
        ->middleware('permission:aems.program.manage');
    Route::post('/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}/progress', [AemsProgramController::class, 'progressProcedure'])
        ->middleware('permission:aems.program.manage');
    Route::post('/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}/review', [AemsProgramController::class, 'reviewProcedure'])
        ->middleware('permission:aems.program.review');
    Route::get('/aems/engagements/{engagement}/fieldwork', [AemsFieldworkController::class, 'index'])
        ->middleware('permission:aems.fieldwork.view');
    Route::post('/aems/engagements/{engagement}/fieldwork', [AemsFieldworkController::class, 'store'])
        ->middleware('permission:aems.fieldwork.create');
    Route::put('/aems/engagements/{engagement}/fieldwork/{record}', [AemsFieldworkController::class, 'update'])
        ->middleware('permission:aems.fieldwork.create');
    Route::post('/aems/engagements/{engagement}/fieldwork/{record}/transition', [AemsFieldworkController::class, 'transition'])
        ->middleware('permission:aems.fieldwork.view');
    Route::get('/aems/engagements/{engagement}/working-papers', [AemsWorkingPaperController::class, 'index'])
        ->middleware('permission:aems.working-paper.view');
    Route::post('/aems/engagements/{engagement}/working-papers', [AemsWorkingPaperController::class, 'store'])
        ->middleware('permission:aems.working-paper.create');
    Route::put('/aems/engagements/{engagement}/working-papers/{paper}', [AemsWorkingPaperController::class, 'update'])
        ->middleware('permission:aems.working-paper.create');
    Route::post('/aems/engagements/{engagement}/working-papers/{paper}/transition', [AemsWorkingPaperController::class, 'transition'])
        ->middleware('permission:aems.working-paper.view');
    Route::post('/aems/engagements/{engagement}/working-papers/{paper}/revise', [AemsWorkingPaperController::class, 'revise'])
        ->middleware('permission:aems.working-paper.create');
    Route::post('/aems/engagements/{engagement}/evidence', [AemsEvidenceController::class, 'store'])
        ->middleware('permission:aems.evidence.upload');
    Route::post('/aems/engagements/{engagement}/evidence/{evidence}/revisions', [AemsEvidenceController::class, 'replace'])
        ->middleware('permission:aems.evidence.upload');
    Route::post('/aems/engagements/{engagement}/evidence/{evidence}/transition', [AemsEvidenceController::class, 'transition'])
        ->middleware('permission:aems.evidence.view');
    Route::get('/aems/engagements/{engagement}/evidence/{evidence}/download', [AemsEvidenceController::class, 'download'])
        ->middleware('permission:aems.evidence.view');
    Route::post('/aems/engagements/{engagement}/evidence/{evidence}/report-links', [AemsEvidenceController::class, 'linkReport'])
        ->middleware('permission:aems.evidence.link_report');
    Route::get('/aems/engagements/{engagement}/evidence-requests', [AemsEvidenceRequestController::class, 'index'])
        ->middleware('permission:aems.evidence-request.view');
    Route::post('/aems/engagements/{engagement}/evidence-requests', [AemsEvidenceRequestController::class, 'store'])
        ->middleware('permission:aems.evidence-request.create');
    Route::put('/aems/engagements/{engagement}/evidence-requests/{evidenceRequest}', [AemsEvidenceRequestController::class, 'update'])
        ->middleware('permission:aems.evidence-request.update');
    Route::post('/aems/engagements/{engagement}/evidence-requests/{evidenceRequest}/transition', [AemsEvidenceRequestController::class, 'transition'])
        ->middleware('permission:aems.evidence-request.view');
    Route::post('/aems/engagements/{engagement}/evidence-requests/{evidenceRequest}/evidence', [AemsEvidenceRequestController::class, 'receiveEvidence'])
        ->middleware('permission:aems.evidence-request.receive');
    Route::post('/aems/engagements/{engagement}/evidence-assessments', [AemsEvidenceRequestController::class, 'assessEvidence'])
        ->middleware('permission:aems.evidence.assess');
    Route::post('/aems/engagements/{engagement}/evidence-assessments/{assessment}/approve-exception', [AemsEvidenceRequestController::class, 'approveException'])
        ->middleware('permission:aems.evidence.exception_approve');
    Route::get('/aems/findings-workspaces', [AemsFindingController::class, 'engagements'])
        ->middleware('permission:aems.finding.view');
    Route::get('/aems/engagements/{engagement}/findings-workspace', [AemsFindingController::class, 'index'])
        ->middleware('permission:aems.finding.view');
    Route::post('/aems/engagements/{engagement}/issues', [AemsIssueController::class, 'store'])
        ->middleware('permission:aems.issue.create');
    Route::put('/aems/engagements/{engagement}/issues/{issue}', [AemsIssueController::class, 'update'])
        ->middleware('permission:aems.issue.create');
    Route::post('/aems/engagements/{engagement}/issues/{issue}/transition', [AemsIssueController::class, 'transition'])
        ->middleware('permission:aems.issue.view');
    Route::post('/aems/engagements/{engagement}/findings', [AemsFindingController::class, 'store'])
        ->middleware('permission:aems.finding.create');
    Route::put('/aems/engagements/{engagement}/findings/{finding}', [AemsFindingController::class, 'update'])
        ->middleware('permission:aems.finding.create');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/transition', [AemsFindingController::class, 'transition'])
        ->middleware('permission:aems.finding.view');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/transmittals', [AemsFindingController::class, 'createTransmittal'])
        ->middleware('permission:aems.afr.transmit');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/transmittals/{transmittal}/recipients/{recipient}/transition', [AemsFindingController::class, 'transitionTransmittalRecipient'])
        ->middleware('permission:aems.afr.view');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/revisions', [AemsFindingController::class, 'revise'])
        ->middleware('permission:aems.finding.revise');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/recommendations', [AemsFindingController::class, 'saveRecommendation'])
        ->middleware('permission:aems.finding.create');
    Route::put('/aems/engagements/{engagement}/findings/{finding}/recommendations/{recommendation}', [AemsFindingController::class, 'saveRecommendation'])
        ->middleware('permission:aems.finding.create');
    Route::delete('/aems/engagements/{engagement}/findings/{finding}/recommendations/{recommendation}', [AemsFindingController::class, 'deleteRecommendation'])
        ->middleware('permission:aems.finding.create');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/responses', [AemsFindingController::class, 'createResponse'])
        ->middleware('permission:aems.management-response.submit');
    Route::put('/aems/engagements/{engagement}/findings/{finding}/responses/{response}', [AemsFindingController::class, 'updateResponse'])
        ->middleware('permission:aems.management-response.submit');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/responses/{response}/transition', [AemsFindingController::class, 'transitionResponse'])
        ->middleware('permission:aems.management-response.view');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/responses/{response}/revisions', [AemsFindingController::class, 'reviseResponse'])
        ->middleware('permission:aems.management-response.submit');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/responses/{response}/attachments', [AemsFindingController::class, 'uploadResponseAttachment'])
        ->middleware('permission:aems.management-response.submit');
    Route::get('/aems/engagements/{engagement}/work-queue', [AemsWorkQueueController::class, 'show'])
        ->middleware('permission:aems.task.view');
    Route::post('/aems/engagements/{engagement}/tasks', [AemsWorkQueueController::class, 'storeTask'])
        ->middleware('permission:aems.task.create');
    Route::put('/aems/engagements/{engagement}/tasks/{task}', [AemsWorkQueueController::class, 'updateTask'])
        ->middleware('permission:aems.task.update');
    Route::post('/aems/engagements/{engagement}/tasks/{task}/transition', [AemsWorkQueueController::class, 'transitionTask'])
        ->middleware('permission:aems.task.view');
    Route::post('/aems/engagements/{engagement}/review-notes', [AemsWorkQueueController::class, 'storeReviewNote'])
        ->middleware('permission:aems.review-note.create');
    Route::put('/aems/engagements/{engagement}/review-notes/{note}', [AemsWorkQueueController::class, 'updateReviewNote'])
        ->middleware('permission:aems.review-note.update');
    Route::post('/aems/engagements/{engagement}/review-notes/{note}/transition', [AemsWorkQueueController::class, 'transitionReviewNote'])
        ->middleware('permission:aems.review-note.view');
    Route::post('/aems/engagements/{engagement}/review-notes/{note}/revisions', [AemsWorkQueueController::class, 'reviseReviewNote'])
        ->middleware('permission:aems.review-note.revise');
    Route::post('/aems/engagements/{engagement}/review-notes/{note}/attachments', [AemsWorkQueueController::class, 'attachReviewNote'])
        ->middleware('permission:aems.review-note.attach');
    Route::post('/aems/engagements/{engagement}/due-process', [AemsWorkQueueController::class, 'storeDueProcess'])
        ->middleware('permission:aems.due-process.create');
    Route::post('/aems/engagements/{engagement}/due-process/{item}/attachments', [AemsWorkQueueController::class, 'attachDueProcess'])
        ->middleware('permission:aems.due-process.attach');
    Route::post('/aems/engagements/{engagement}/escalation-candidates/{candidate}/review', [AemsWorkQueueController::class, 'reviewCandidate'])
        ->middleware('permission:aems.escalation-candidate.view');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders', [AemsFindingController::class, 'saveRejoinder'])
        ->middleware('permission:aems.rejoinder.create');
    Route::put('/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders/{rejoinder}', [AemsFindingController::class, 'saveRejoinder'])
        ->middleware('permission:aems.rejoinder.create');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders/{rejoinder}/finalize', [AemsFindingController::class, 'finalizeRejoinder'])
        ->middleware('permission:aems.rejoinder.finalize');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders/{rejoinder}/attachments', [AemsFindingController::class, 'uploadRejoinderAttachment'])
        ->middleware('permission:aems.rejoinder.create');
    Route::get('/aems/engagements/{engagement}/findings/{finding}/dialogue-attachments/{attachment}/download', [AemsFindingController::class, 'downloadAttachment'])
        ->middleware('permission:aems.finding.view');
    Route::get('/aems/exit-conference-workspaces', [AemsExitConferenceController::class, 'engagements'])
        ->middleware('permission:aems.conference.view');
    Route::get('/aems/engagements/{engagement}/exit-conferences', [AemsExitConferenceController::class, 'index'])
        ->middleware('permission:aems.conference.view');
    Route::post('/aems/engagements/{engagement}/exit-conferences', [AemsExitConferenceController::class, 'store'])
        ->middleware('permission:aems.conference.manage');
    Route::put('/aems/engagements/{engagement}/exit-conferences/{conference}', [AemsExitConferenceController::class, 'update'])
        ->middleware('permission:aems.conference.manage');
    Route::post('/aems/engagements/{engagement}/exit-conferences/{conference}/complete', [AemsExitConferenceController::class, 'complete'])
        ->middleware('permission:aems.conference.manage');
    Route::post('/aems/engagements/{engagement}/exit-conferences/{conference}/transition', [AemsExitConferenceController::class, 'transition'])
        ->middleware('permission:aems.conference.manage');
    Route::post('/aems/engagements/{engagement}/exit-conferences/{conference}/attachments', [AemsExitConferenceController::class, 'uploadAttachment'])
        ->middleware('permission:aems.conference.manage');
    Route::post('/aems/engagements/{engagement}/exit-conferences/{conference}/acknowledgements', [AemsExitConferenceController::class, 'acknowledge'])
        ->middleware('permission:aems.conference.acknowledge');
    Route::get('/aems/engagements/{engagement}/exit-conferences/{conference}/attachments/{attachment}/download', [AemsExitConferenceController::class, 'downloadAttachment'])
        ->middleware('permission:aems.conference.view');
    Route::get('/aems/report-workspaces', [AemsReportController::class, 'engagements']);
    Route::get('/aems/engagements/{engagement}/reports', [AemsReportController::class, 'index']);
    Route::post('/aems/engagements/{engagement}/reports', [AemsReportController::class, 'store'])
        ->middleware('permission:aems.report.create');
    Route::post('/aems/engagements/{engagement}/reports/interim', [AemsReportController::class, 'interim'])
        ->middleware('permission:aems.report.create');
    Route::post('/aems/engagements/{engagement}/reports/{report}/versions', [AemsReportController::class, 'revise'])
        ->middleware('permission:aems.report.create');
    Route::post('/aems/engagements/{engagement}/reports/{report}/final', [AemsReportController::class, 'createFinal'])
        ->middleware('permission:aems.report.create');
    Route::post('/aems/engagements/{engagement}/reports/{report}/transition', [AemsReportController::class, 'transition'])
        ->middleware('permission:aems.report.view');
    Route::post('/aems/engagements/{engagement}/reports/{report}/cms-transfer', [AemsReportController::class, 'transferRecommendations'])
        ->middleware('permission:aems.report.issue');
    Route::post('/aems/engagements/{engagement}/reports/{report}/successors', [AemsReportController::class, 'successor']);
    Route::post('/aems/engagements/{engagement}/reports/{report}/withdraw', [AemsReportController::class, 'withdraw']);
    Route::post('/aems/engagements/{engagement}/reports/{report}/versions/{version}/authority-decisions', [AemsReportController::class, 'authorityDecision'])
        ->middleware('permission:aems.report.authority');
    Route::post('/aems/engagements/{engagement}/reports/{report}/versions/{version}/signatories', [AemsReportController::class, 'signatory'])
        ->middleware('permission:aems.report.signatory');
    Route::post('/aems/engagements/{engagement}/reports/{report}/versions/{version}/transmittals', [AemsReportController::class, 'transmittal'])
        ->middleware('permission:aems.report.transmit');
    Route::post('/aems/engagements/{engagement}/reports/{report}/administrative-close', [AemsReportController::class, 'administrativeClose'])
        ->middleware('permission:aems.report.close_admin');
    Route::get('/aems/engagements/{engagement}/reports/{report}/versions/{version}/exports/{format}', [AemsReportController::class, 'export'])
        ->middleware('permission:aems.report.export');
    Route::post('/aems/engagements/{engagement}/reports/{report}/versions/{version}/recipients/{recipient}/decision', [AemsReportController::class, 'distributionDecision']);
    Route::get('/aems/engagements/{engagement}/reports/{report}/versions/{version}/download', [AemsReportController::class, 'download']);
    Route::get('/aems/engagements/{engagement}/lifecycle', [AemsEngagementLifecycleController::class, 'show'])
        ->middleware('permission:aems.engagement.view');
    Route::post('/aems/engagements/{engagement}/transitions/{action}', [AemsEngagementLifecycleController::class, 'transition'])
        ->middleware('permission:aems.engagement.view');
    Route::get('/aems/entry-conference-workspaces', [AemsEntryConferenceController::class, 'engagements'])
        ->middleware('permission:aems.entry-conference.view');
    Route::get('/aems/engagements/{engagement}/entry-conference', [AemsEntryConferenceController::class, 'show'])
        ->middleware('permission:aems.entry-conference.view');
    Route::post('/aems/engagements/{engagement}/entry-conference', [AemsEntryConferenceController::class, 'store'])
        ->middleware('permission:aems.entry-conference.manage');
    Route::put('/aems/engagements/{engagement}/entry-conference/{conference}', [AemsEntryConferenceController::class, 'update'])
        ->middleware('permission:aems.entry-conference.manage');
    Route::post('/aems/engagements/{engagement}/entry-conference/{conference}/transitions/{action}', [AemsEntryConferenceController::class, 'transition'])
        ->middleware('permission:aems.entry-conference.view');
    Route::post('/aems/engagements/{engagement}/entry-conference/{conference}/acknowledgements', [AemsEntryConferenceController::class, 'acknowledge'])
        ->middleware('permission:aems.entry-conference.acknowledge');
    Route::post('/aems/engagements/{engagement}/entry-conference/{conference}/attachments', [AemsEntryConferenceController::class, 'uploadAttachment'])
        ->middleware('permission:aems.entry-conference.manage');
    Route::get('/aems/engagements/{engagement}/entry-conference/{conference}/attachments/{attachment}/download', [AemsEntryConferenceController::class, 'downloadAttachment'])
        ->middleware('permission:aems.entry-conference.view');
    Route::get('/aems/engagements/{engagement}/completion-assessments', [AemsCompletionAssessmentController::class, 'index'])
        ->middleware('permission:aems.completion-assessment.view');
    Route::post('/aems/engagements/{engagement}/completion-assessments', [AemsCompletionAssessmentController::class, 'store'])
        ->middleware('permission:aems.completion-assessment.create');
    Route::put('/aems/engagements/{engagement}/completion-assessments/{assessment}', [AemsCompletionAssessmentController::class, 'update'])
        ->middleware('permission:aems.completion-assessment.update');
    Route::post('/aems/engagements/{engagement}/completion-assessments/{assessment}/transitions/{action}', [AemsCompletionAssessmentController::class, 'transition'])
        ->middleware('permission:aems.completion-assessment.view');
    Route::post('/aems/engagements/{engagement}/completion-assessments/{assessment}/items/{item}/accept-blocker', [AemsCompletionAssessmentController::class, 'acceptBlocker'])
        ->middleware('permission:aems.completion-assessment.approve');
    Route::post('/aems/engagements/{engagement}/completion-assessments/{assessment}/revisions', [AemsCompletionAssessmentController::class, 'revise'])
        ->middleware('permission:aems.completion-assessment.create');
    Route::get('/aems/engagements/{engagement}/closure', [AemsClosureController::class, 'show'])
        ->middleware('permission:aems.closure.view');
    Route::post('/aems/engagements/{engagement}/closure', [AemsClosureController::class, 'store'])
        ->middleware('permission:aems.closure.create');
    Route::put('/aems/engagements/{engagement}/closures/{closure}', [AemsClosureController::class, 'update'])
        ->middleware('permission:aems.closure.update');
    Route::get('/aems/engagements/{engagement}/closures/{closure}/checklist', [AemsClosureController::class, 'show'])
        ->middleware('permission:aems.closure.view');
    Route::post('/aems/engagements/{engagement}/closures/{closure}/refresh-checklist', [AemsClosureController::class, 'refreshChecklist'])
        ->middleware('permission:aems.closure.update');
    Route::post('/aems/engagements/{engagement}/closures/{closure}/transitions/{action}', [AemsClosureController::class, 'transition'])
        ->middleware('permission:aems.closure.view');
    Route::get('/aems/engagements/{engagement}/document-index', [AemsDocumentIndexController::class, 'show'])
        ->middleware('permission:aems.document-index.view');
    Route::post('/aems/engagements/{engagement}/document-index/refresh', [AemsDocumentIndexController::class, 'refresh'])
        ->middleware('permission:aems.document-index.manage');
    Route::post('/aems/engagements/{engagement}/document-index', [AemsDocumentIndexController::class, 'store'])
        ->middleware('permission:aems.document-index.manage');
    Route::post('/aems/engagements/{engagement}/document-index/{item}/exclude', [AemsDocumentIndexController::class, 'exclude'])
        ->middleware('permission:aems.document-index.finalize');
    Route::get('/aems/engagements/{engagement}/document-index/export', [AemsDocumentIndexController::class, 'export'])
        ->middleware('permission:aems.document-index.view');
    Route::put('/aems/engagements/{engagement}/retention', [AemsClosureController::class, 'saveRetention'])
        ->middleware('permission:aems.retention.manage');
    Route::post('/aems/engagements/{engagement}/retention/{retention}/approve', [AemsClosureController::class, 'approveRetention'])
        ->middleware('permission:aems.retention.approve');
    Route::get('/aems/engagements/{engagement}/records', [AemsClosureController::class, 'records'])
        ->middleware('permission:aems.records.view');
    Route::get('/aems/engagements/{engagement}/calendar', [AemsClosureController::class, 'calendar'])
        ->middleware('permission:aems.calendar.view');
    Route::post('/aems/engagements/{engagement}/calendar/milestones', [AemsClosureController::class, 'createMilestone'])
        ->middleware('permission:aems.calendar.manage');
    Route::put('/aems/engagements/{engagement}/calendar/milestones/{milestone}', [AemsClosureController::class, 'updateMilestone'])
        ->middleware('permission:aems.calendar.manage');
    Route::post('/aems/engagements/{engagement}/calendar/milestones/{milestone}/transition', [AemsClosureController::class, 'transitionMilestone'])
        ->middleware('permission:aems.calendar.manage');
    Route::post('/aems/engagements/{engagement}/retention/{retention}/archive', [AemsClosureController::class, 'archive'])
        ->middleware('permission:aems.retention.archive');
    Route::post('/aems/engagements/{engagement}/retention/{retention}/legal-hold-release', [AemsClosureController::class, 'releaseLegalHold'])
        ->middleware('permission:aems.retention.legal_hold_release');
    Route::post('/aems/engagements/{engagement}/retention/{retention}/destruction-review', [AemsClosureController::class, 'destructionReview'])
        ->middleware('permission:aems.retention.destruction_review');
    Route::post('/aems/engagements/{engagement}/retention/{retention}/disposition', [AemsClosureController::class, 'recordDisposition'])
        ->middleware('permission:aems.retention.disposition_execute');
    Route::post('/aems/engagements/{engagement}/lessons-learned', [AemsClosureController::class, 'addLesson'])
        ->middleware('permission:aems.closure.update');
    Route::get('/aems/engagements/{engagement}/completion-transfer', [AemsCompletionTransferController::class, 'show'])
        ->middleware('permission:aems.completion-transfer.view');
    Route::post('/aems/engagements/{engagement}/completion-transfer/reconcile', [AemsCompletionTransferController::class, 'reconcile'])
        ->middleware('permission:aems.completion-transfer.reconcile');
    Route::post('/aems/engagements/{engagement}/completion-transfer/{type}/{id}/approve', [AemsCompletionTransferController::class, 'approve'])
        ->middleware('permission:aems.completion-transfer.approve');
    Route::post('/aems/engagements/{engagement}/recommendations/{recommendation}/cms-exclusion', [AemsClosureController::class, 'excludeRecommendation'])
        ->middleware('permission:aems.closure.approve');
    Route::get('/aems/engagements/{engagement}/reopen-requests', [AemsReopenController::class, 'index'])
        ->middleware('permission:aems.closure.view');
    Route::post('/aems/engagements/{engagement}/reopen-requests', [AemsReopenController::class, 'store'])
        ->middleware('permission:aems.engagement.reopen_request');
    Route::post('/aems/engagements/{engagement}/reopen-requests/{reopen}/transitions/{action}', [AemsReopenController::class, 'transition'])
        ->middleware('permission:aems.closure.view');
    Route::get('/aems/engagements/{engagement}', [AemsEngagementController::class, 'show'])
        ->middleware('permission:aems.engagement.view');
    Route::put('/aems/engagements/{engagement}', [AemsEngagementController::class, 'update'])
        ->middleware('permission:aems.engagement.update');
    Route::delete('/aems/engagements/{engagement}', [AemsEngagementController::class, 'destroy'])
        ->middleware('permission:aems.engagement.archive');
    Route::post('/aems/engagements/{engagement}/restore', [AemsEngagementController::class, 'restore'])
        ->middleware('permission:aems.engagement.restore');

    Route::get('/iap/plans', [IapPlanController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::get('/iap/dashboard', IapDashboardController::class)
        ->middleware('permission:iap.view');
    Route::get('/iap/reports', [IapReportController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::get('/iap/reports/{report}', [IapReportController::class, 'show'])
        ->middleware('permission:iap.view');
    Route::get('/iap/reports/{report}/export', [IapReportController::class, 'export'])
        ->middleware('permission:iap.export');

    Route::get('/iap/baics', [IapBaicsController::class, 'index'])
        ->middleware('permission:iap.baics.view');
    Route::post('/iap/baics', [IapBaicsController::class, 'store'])
        ->middleware('permission:iap.baics.create');
    Route::get('/iap/baics/integrations/candidates', [IapBaicsIntegrationController::class, 'candidates'])
        ->middleware('permission:iap.baics.view|iap.baics.integration.view');
    Route::get('/iap/baics/integrations/readiness', [IapBaicsIntegrationController::class, 'readiness'])
        ->middleware('permission:iap.baics.view|iap.baics.integration.view');
    Route::post('/iap/baics/integrations/{integration}/transitions/{action}', [IapBaicsIntegrationController::class, 'transition']);
    Route::get('/iap/baics/{assessment}', [IapBaicsController::class, 'show'])
        ->middleware('permission:iap.baics.view');
    Route::put('/iap/baics/{assessment}', [IapBaicsController::class, 'update'])
        ->middleware('permission:iap.baics.update');
    Route::delete('/iap/baics/{assessment}', [IapBaicsController::class, 'destroy'])
        ->middleware('permission:iap.baics.archive');
    Route::post('/iap/baics/{assessment}/restore', [IapBaicsController::class, 'restore'])
        ->middleware('permission:iap.baics.archive');
    Route::get('/iap/baics/{assessment}/versions', [IapBaicsController::class, 'versions'])
        ->middleware('permission:iap.baics.view');
    Route::post('/iap/baics/{assessment}/transitions/{action}', [IapBaicsController::class, 'transition']);
    Route::post('/iap/baics/{assessment}/revisions', [IapBaicsController::class, 'revision'])
        ->middleware('permission:iap.baics.update');
    Route::post('/iap/baics/{assessment}/assignments', [IapBaicsController::class, 'storeAssignment'])
        ->middleware('permission:iap.baics.assign');
    Route::delete('/iap/baics/{assessment}/assignments/{assignment}', [IapBaicsController::class, 'endAssignment'])
        ->middleware('permission:iap.baics.assign');
    Route::get('/iap/baics/{assessment}/integrations', [IapBaicsIntegrationController::class, 'index'])
        ->middleware('permission:iap.baics.view|iap.baics.integration.view');
    Route::post('/iap/baics/{assessment}/integrations', [IapBaicsIntegrationController::class, 'store'])
        ->middleware('permission:iap.baics.manage-controls|iap.baics.update|iap.baics.integration.create');
    Route::put('/iap/baics/{assessment}/integrations/{integration}', [IapBaicsIntegrationController::class, 'update'])
        ->middleware('permission:iap.baics.manage-controls|iap.baics.update|iap.baics.integration.update');
    Route::get('/iap/baics/{assessment}/controls', [IapBaicsControlUniverseController::class, 'controls'])
        ->middleware('permission:iap.baics.view');
    Route::post('/iap/baics/{assessment}/controls', [IapBaicsControlUniverseController::class, 'storeControl'])
        ->middleware('permission:iap.baics.manage-controls');
    Route::put('/iap/baics/{assessment}/controls/{control}', [IapBaicsControlUniverseController::class, 'updateControl'])
        ->middleware('permission:iap.baics.manage-controls');
    Route::post('/iap/baics/{assessment}/controls/{control}/transitions/{action}', [IapBaicsControlUniverseController::class, 'transitionControl']);
    Route::get('/iap/baics/{assessment}/interim-analyses', [IapBaicsControlUniverseController::class, 'interimAnalyses'])
        ->middleware('permission:iap.baics.view');
    Route::post('/iap/baics/{assessment}/interim-analyses', [IapBaicsControlUniverseController::class, 'storeInterimAnalysis'])
        ->middleware('permission:iap.baics.manage-controls');
    Route::put('/iap/baics/{assessment}/interim-analyses/{analysis}', [IapBaicsControlUniverseController::class, 'updateInterimAnalysis'])
        ->middleware('permission:iap.baics.manage-controls');
    Route::post('/iap/baics/{assessment}/interim-analyses/{analysis}/transitions/{action}', [IapBaicsControlUniverseController::class, 'transitionInterimAnalysis']);
    Route::get('/iap/baics/{assessment}/reports', [IapBaicsControlUniverseController::class, 'reports'])
        ->middleware('permission:iap.baics.view');
    Route::post('/iap/baics/{assessment}/reports', [IapBaicsControlUniverseController::class, 'storeReport'])
        ->middleware('permission:iap.baics.manage-controls');
    Route::put('/iap/baics/{assessment}/reports/{report}', [IapBaicsControlUniverseController::class, 'updateReport'])
        ->middleware('permission:iap.baics.manage-controls');
    Route::post('/iap/baics/{assessment}/reports/{report}/transitions/{action}', [IapBaicsControlUniverseController::class, 'transitionReport']);
    Route::get('/iap/baics/{assessment}/reports/{report}/export', [IapBaicsControlUniverseController::class, 'export'])
        ->middleware('permission:iap.baics.export');
    Route::get('/iap/baics/{assessment}/readiness', [IapBaicsControlAssessmentController::class, 'readiness'])
        ->middleware('permission:iap.baics.view');
    Route::get('/iap/baics/{assessment}/components', [IapBaicsControlAssessmentController::class, 'components'])
        ->middleware('permission:iap.baics.view');
    Route::get('/iap/baics/{assessment}/components/{component}', [IapBaicsControlAssessmentController::class, 'showComponent'])
        ->middleware('permission:iap.baics.view');
    Route::put('/iap/baics/{assessment}/components/{component}', [IapBaicsControlAssessmentController::class, 'updateComponent']);
    Route::post('/iap/baics/{assessment}/components/{component}/transitions/{action}', [IapBaicsControlAssessmentController::class, 'transitionComponent']);
    Route::get('/iap/baics/{assessment}/components/{component}/methods', [IapBaicsControlAssessmentController::class, 'methods'])
        ->middleware('permission:iap.baics.view');
    Route::post('/iap/baics/{assessment}/components/{component}/methods', [IapBaicsControlAssessmentController::class, 'storeMethod']);
    Route::put('/iap/baics/{assessment}/components/{component}/methods/{method}', [IapBaicsControlAssessmentController::class, 'updateMethod']);
    Route::post('/iap/baics/{assessment}/components/{component}/methods/{method}/transitions/{action}', [IapBaicsControlAssessmentController::class, 'transitionMethod']);
    Route::get('/iap/baics/{assessment}/components/{component}/evidence', [IapBaicsControlAssessmentController::class, 'evidence'])
        ->middleware('permission:iap.baics.view');
    Route::post('/iap/baics/{assessment}/components/{component}/evidence', [IapBaicsControlAssessmentController::class, 'storeEvidence']);
    Route::delete('/iap/baics/{assessment}/components/{component}/evidence/{link}', [IapBaicsControlAssessmentController::class, 'destroyEvidence']);
    Route::get('/iap/baics/{assessment}/exceptions', [IapBaicsControlAssessmentController::class, 'exceptions'])
        ->middleware('permission:iap.baics.view');
    Route::post('/iap/baics/{assessment}/exceptions', [IapBaicsControlAssessmentController::class, 'storeException']);
    Route::put('/iap/baics/{assessment}/exceptions/{exception}', [IapBaicsControlAssessmentController::class, 'updateException']);
    Route::post('/iap/baics/{assessment}/exceptions/{exception}/transitions/{action}', [IapBaicsControlAssessmentController::class, 'transitionException']);

    Route::get('/iap/strategic-plans', [SiapPlanController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::post('/iap/strategic-plans', [SiapPlanController::class, 'store'])
        ->middleware('permission:iap.create');
    Route::get('/iap/strategic-plans/{strategicPlan}', [SiapPlanController::class, 'show'])
        ->middleware('permission:iap.view');
    Route::put('/iap/strategic-plans/{strategicPlan}', [SiapPlanController::class, 'update'])
        ->middleware('permission:iap.update');
    Route::delete('/iap/strategic-plans/{strategicPlan}', [SiapPlanController::class, 'destroy'])
        ->middleware('permission:iap.archive');
    Route::post('/iap/strategic-plans/{strategicPlan}/restore', [SiapPlanController::class, 'restore'])
        ->middleware('permission:iap.restore');
    Route::get('/iap/strategic-plans/{strategicPlan}/completeness', [SiapPlanController::class, 'completeness'])
        ->middleware('permission:iap.view');
    Route::post('/iap/strategic-plans/{strategicPlan}/transitions/{action}', [SiapPlanController::class, 'transition']);
    Route::post('/iap/strategic-plans/{strategicPlan}/revisions', [SiapPlanController::class, 'revision'])
        ->middleware('permission:iap.create_revision');

    Route::post('/iap/plans', [IapPlanController::class, 'store'])
        ->middleware('permission:iap.create');
    Route::get('/iap/plans/{plan}', [IapPlanController::class, 'show'])
        ->middleware('permission:iap.view');
    Route::put('/iap/plans/{plan}', [IapPlanController::class, 'update'])
        ->middleware('permission:iap.update');
    Route::delete('/iap/plans/{plan}', [IapPlanController::class, 'destroy'])
        ->middleware('permission:iap.archive');
    Route::post('/iap/plans/{plan}/restore', [IapPlanController::class, 'restore'])
        ->middleware('permission:iap.restore');

    Route::get('/iap/plans/{plan}/completeness', [IapWorkflowController::class, 'completeness'])
        ->middleware('permission:iap.view');
    Route::post('/iap/plans/{plan}/transitions/{action}', [IapWorkflowController::class, 'transition']);
    Route::post('/iap/plans/{plan}/revisions', [IapWorkflowController::class, 'revision'])
        ->middleware('permission:iap.create_revision');
    Route::get('/iap/plans/{plan}/supporting-records', [IapSupportingRecordController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::post('/iap/plans/{plan}/attachments', [IapSupportingRecordController::class, 'storeAttachment'])
        ->middleware('permission:iap.update');
    Route::get('/iap/plans/{plan}/attachments/{attachment}/download', [IapSupportingRecordController::class, 'download'])
        ->middleware('permission:iap.view');
    Route::delete('/iap/plans/{plan}/attachments/{attachment}', [IapSupportingRecordController::class, 'destroyAttachment'])
        ->middleware('permission:iap.update');
    Route::post('/iap/plans/{plan}/attachments/{attachment}/restore', [IapSupportingRecordController::class, 'restoreAttachment'])
        ->middleware('permission:iap.update');
    Route::post('/iap/plans/{plan}/comments', [IapSupportingRecordController::class, 'storeComment'])
        ->middleware('permission:iap.review');

    Route::post('/iap/plans/{plan}/risk-assessments', [IapRiskAssessmentController::class, 'store'])
        ->middleware('permission:iap.assess_risk');
    Route::get('/iap/plans/{plan}/risk-assessments', [IapRiskAssessmentController::class, 'index'])
        ->middleware('permission:iap.assess_risk');
    Route::put('/iap/plans/{plan}/risk-assessments/{assessment}', [IapRiskAssessmentController::class, 'update'])
        ->middleware('permission:iap.assess_risk');
    Route::delete('/iap/plans/{plan}/risk-assessments/{assessment}', [IapRiskAssessmentController::class, 'destroy'])
        ->middleware('permission:iap.assess_risk');
    Route::post('/iap/plans/{plan}/risk-assessments/{assessment}/restore', [IapRiskAssessmentController::class, 'restore'])
        ->middleware('permission:iap.assess_risk');

    Route::post('/iap/plans/{plan}/engagements', [IapEngagementController::class, 'store'])
        ->middleware('permission:iap.manage_engagements');
    Route::put('/iap/plans/{plan}/prioritization', [IapPlanPrioritizationController::class, 'update'])
        ->middleware('permission:iap.manage_engagements');
    Route::get('/iap/schedules', [IapSchedulingController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::post('/iap/schedules/{engagement}/conflicts', [IapSchedulingController::class, 'conflicts'])
        ->middleware('permission:iap.assign_team');
    Route::put('/iap/schedules/{engagement}', [IapSchedulingController::class, 'update'])
        ->middleware('permission:iap.assign_team');
    Route::post('/iap/schedules/{engagement}/cancel', [IapSchedulingController::class, 'cancel'])
        ->middleware('permission:iap.assign_team');
    Route::put('/iap/schedules/capacities/{user}', [IapSchedulingController::class, 'updateCapacity'])
        ->middleware('permission:iap.assign_team');
    Route::get('/iap/resources', [IapResourceCapacityController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::put('/iap/resources/auditors/{user}/capacity', [IapResourceCapacityController::class, 'updateCapacity'])
        ->middleware('permission:iap.assign_team');
    Route::post('/iap/resources/auditors/{user}/unavailability', [IapResourceCapacityController::class, 'storeUnavailability'])
        ->middleware('permission:iap.assign_team');
    Route::put('/iap/resources/unavailability/{unavailability}', [IapResourceCapacityController::class, 'updateUnavailability'])
        ->middleware('permission:iap.assign_team');
    Route::delete('/iap/resources/unavailability/{unavailability}', [IapResourceCapacityController::class, 'destroyUnavailability'])
        ->middleware('permission:iap.assign_team');
    Route::post('/iap/resources/unavailability/{unavailability}/restore', [IapResourceCapacityController::class, 'restoreUnavailability'])
        ->middleware('permission:iap.assign_team');
    Route::put('/iap/resources/auditors/{user}/skills', [IapResourceCapacityController::class, 'syncSkills'])
        ->middleware('permission:iap.assign_team');
    Route::put('/iap/resources/engagements/{engagement}/requirements', [IapResourceCapacityController::class, 'syncRequirements'])
        ->middleware('permission:iap.assign_team');
    Route::put('/iap/plans/{plan}/engagements/{engagement}', [IapEngagementController::class, 'update'])
        ->middleware('permission:iap.manage_engagements');
    Route::delete('/iap/plans/{plan}/engagements/{engagement}', [IapEngagementController::class, 'destroy'])
        ->middleware('permission:iap.manage_engagements');
    Route::post('/iap/plans/{plan}/engagements/{engagement}/restore', [IapEngagementController::class, 'restore'])
        ->middleware('permission:iap.manage_engagements');
    Route::put('/iap/plans/{plan}/engagements/{engagement}/team', [IapEngagementController::class, 'updateTeam'])
        ->middleware('permission:iap.assign_team');

    Route::get('/armis/metadata', [ArmisResourceController::class, 'metadata'])
        ->middleware('permission:armis.resource.view');
    Route::get('/armis/foundation', [ArmisFoundationController::class, 'index'])
        ->middleware('permission:armis.resource.view');
    Route::get('/armis/identities', [ArmisResourceController::class, 'identities'])
        ->middleware('permission:armis.resource.view');
    Route::get('/armis/resources', [ArmisResourceController::class, 'index'])
        ->middleware('permission:armis.resource.view');
    Route::post('/armis/resources', [ArmisResourceController::class, 'store'])
        ->middleware('permission:armis.resource.create');
    Route::get('/armis/resources/{profile}', [ArmisResourceController::class, 'show'])
        ->middleware('permission:armis.resource.view');
    Route::get('/armis/resources/{profile}/events', [ArmisResourceController::class, 'events'])
        ->middleware('permission:armis.resource.view');
    Route::put('/armis/resources/{profile}', [ArmisResourceController::class, 'update'])
        ->middleware('permission:armis.resource.update');
    Route::post('/armis/resources/{profile}/transition', [ArmisResourceController::class, 'transition'])
        ->middleware('permission:armis.resource.update');
    Route::post('/armis/resources/{profile}/restore', [ArmisResourceController::class, 'restore'])
        ->middleware('permission:armis.resource.restore');

    Route::get('/armis/competencies/metadata', [ArmisCompetencyController::class, 'metadata'])
        ->middleware('permission:armis.competency.view');
    Route::get('/armis/competencies', [ArmisCompetencyController::class, 'index'])
        ->middleware('permission:armis.competency.view');
    Route::post('/armis/competencies', [ArmisCompetencyController::class, 'store'])
        ->middleware('permission:armis.competency.manage');
    Route::get('/armis/competencies/{competency}', [ArmisCompetencyController::class, 'show'])
        ->middleware('permission:armis.competency.view');
    Route::get('/armis/competencies/{competency}/events', [ArmisCompetencyController::class, 'events'])
        ->middleware('permission:armis.competency.view');
    Route::put('/armis/competencies/{competency}', [ArmisCompetencyController::class, 'update'])
        ->middleware('permission:armis.competency.manage');
    Route::post('/armis/competencies/{competency}/submit', [ArmisCompetencyController::class, 'submit'])
        ->middleware('permission:armis.competency.manage');
    Route::post('/armis/competencies/{competency}/review', [ArmisCompetencyController::class, 'review'])
        ->middleware('permission:armis.competency.verify');
    Route::post('/armis/competencies/{competency}/revisions', [ArmisCompetencyController::class, 'revise'])
        ->middleware('permission:armis.competency.manage');

    Route::get('/armis/planning/metadata', [ArmisPlanningController::class, 'metadata'])
        ->middleware('permission:armis.availability.view');
    Route::get('/armis/availability', [ArmisPlanningController::class, 'availabilityIndex'])
        ->middleware('permission:armis.availability.view');
    Route::post('/armis/availability', [ArmisPlanningController::class, 'availabilityStore'])
        ->middleware('permission:armis.availability.manage');
    Route::get('/armis/availability/{availability}', [ArmisPlanningController::class, 'availabilityShow'])
        ->middleware('permission:armis.availability.view');
    Route::put('/armis/availability/{availability}', [ArmisPlanningController::class, 'availabilityUpdate'])
        ->middleware('permission:armis.availability.manage');
    Route::post('/armis/availability/{availability}/submit', [ArmisPlanningController::class, 'availabilitySubmit'])
        ->middleware('permission:armis.availability.manage');
    Route::post('/armis/availability/{availability}/revisions', [ArmisPlanningController::class, 'availabilityRevise'])
        ->middleware('permission:armis.availability.manage');
    Route::post('/armis/availability/{availability}/review', [ArmisPlanningController::class, 'availabilityReview'])
        ->middleware('permission:armis.availability.review');
    Route::post('/armis/availability/{availability}/lock', [ArmisPlanningController::class, 'availabilityLock'])
        ->middleware('permission:armis.availability.approve');

    Route::get('/armis/capacity', [ArmisPlanningController::class, 'capacityIndex'])
        ->middleware('permission:armis.capacity.view');
    Route::post('/armis/capacity', [ArmisPlanningController::class, 'capacityStore'])
        ->middleware('permission:armis.capacity.manage');
    Route::get('/armis/capacity/{capacity}', [ArmisPlanningController::class, 'capacityShow'])
        ->middleware('permission:armis.capacity.view');
    Route::put('/armis/capacity/{capacity}', [ArmisPlanningController::class, 'capacityUpdate'])
        ->middleware('permission:armis.capacity.manage');
    Route::post('/armis/capacity/{capacity}/submit', [ArmisPlanningController::class, 'capacitySubmit'])
        ->middleware('permission:armis.capacity.manage');
    Route::post('/armis/capacity/{capacity}/review', [ArmisPlanningController::class, 'capacityReview'])
        ->middleware('permission:armis.capacity.review');
    Route::post('/armis/capacity/{capacity}/lock', [ArmisPlanningController::class, 'capacityLock'])
        ->middleware('permission:armis.capacity.approve');

    Route::get('/armis/workload', [ArmisPlanningController::class, 'workloadIndex'])
        ->middleware('permission:armis.workload.view');
    Route::post('/armis/workload', [ArmisPlanningController::class, 'workloadStore'])
        ->middleware('permission:armis.workload.manage');
    Route::get('/armis/workload/{workload}', [ArmisPlanningController::class, 'workloadShow'])
        ->middleware('permission:armis.workload.view');
    Route::put('/armis/workload/{workload}', [ArmisPlanningController::class, 'workloadUpdate'])
        ->middleware('permission:armis.workload.manage');
    Route::post('/armis/workload/{workload}/submit', [ArmisPlanningController::class, 'workloadSubmit'])
        ->middleware('permission:armis.workload.manage');
    Route::post('/armis/workload/{workload}/review', [ArmisPlanningController::class, 'workloadReview'])
        ->middleware('permission:armis.workload.review');
    Route::post('/armis/workload/{workload}/lock', [ArmisPlanningController::class, 'workloadLock'])
        ->middleware('permission:armis.workload.approve');
    Route::get('/armis/utilization', [ArmisPlanningController::class, 'utilization'])
        ->middleware('permission:armis.capacity.view');

    Route::get('/armis/assignments/metadata', [ArmisAssignmentController::class, 'metadata'])
        ->middleware('permission:armis.assignment.view');
    Route::get('/armis/assignments', [ArmisAssignmentController::class, 'assignmentIndex'])
        ->middleware('permission:armis.assignment.view');
    Route::post('/armis/assignments', [ArmisAssignmentController::class, 'assignmentStore'])
        ->middleware('permission:armis.assignment.manage');
    Route::get('/armis/assignments/{assignment}', [ArmisAssignmentController::class, 'assignmentShow'])
        ->middleware('permission:armis.assignment.view');
    Route::put('/armis/assignments/{assignment}', [ArmisAssignmentController::class, 'assignmentUpdate'])
        ->middleware('permission:armis.assignment.manage');
    Route::post('/armis/assignments/{assignment}/submit', [ArmisAssignmentController::class, 'assignmentSubmit'])
        ->middleware('permission:armis.assignment.manage');
    Route::post('/armis/assignments/{assignment}/review', [ArmisAssignmentController::class, 'assignmentReview'])
        ->middleware('permission:armis.assignment.review');
    Route::post('/armis/assignments/{assignment}/lock', [ArmisAssignmentController::class, 'assignmentLock'])
        ->middleware('permission:armis.assignment.approve');
    Route::post('/armis/assignments/{assignment}/revisions', [ArmisAssignmentController::class, 'assignmentRevise'])
        ->middleware('permission:armis.assignment.manage');
    Route::get('/armis/assignments/{assignment}/conflicts', [ArmisAssignmentController::class, 'assignmentConflicts'])
        ->middleware('permission:armis.assignment.view');

    Route::get('/armis/actuals', [ArmisAssignmentController::class, 'actualIndex'])
        ->middleware('permission:armis.actuals.view');
    Route::post('/armis/actuals', [ArmisAssignmentController::class, 'actualStore'])
        ->middleware('permission:armis.actuals.record');
    Route::get('/armis/actuals/{actual}', [ArmisAssignmentController::class, 'actualShow'])
        ->middleware('permission:armis.actuals.view');
    Route::put('/armis/actuals/{actual}', [ArmisAssignmentController::class, 'actualUpdate'])
        ->middleware('permission:armis.actuals.record');
    Route::post('/armis/actuals/{actual}/submit', [ArmisAssignmentController::class, 'actualSubmit'])
        ->middleware('permission:armis.actuals.record');
    Route::post('/armis/actuals/{actual}/review', [ArmisAssignmentController::class, 'actualReview'])
        ->middleware('permission:armis.actuals.review');
    Route::post('/armis/actuals/{actual}/lock', [ArmisAssignmentController::class, 'actualLock'])
        ->middleware('permission:armis.actuals.approve');
    Route::post('/armis/actuals/{actual}/revisions', [ArmisAssignmentController::class, 'actualRevise'])
        ->middleware('permission:armis.actuals.revise');

    Route::get('/armis/reports', [ArmisReportController::class, 'catalog'])
        ->middleware('permission:armis.report.view');
    Route::get('/armis/reports/runs', [ArmisReportController::class, 'runs'])
        ->middleware('permission:armis.report.view');
    Route::get('/armis/reports/runs/{run}', [ArmisReportController::class, 'show'])
        ->middleware('permission:armis.report.view');
    Route::post('/armis/reports/{report}/generate', [ArmisReportController::class, 'generate'])
        ->middleware('permission:armis.report.view');
    Route::post('/armis/reports/runs/{run}/exports', [ArmisReportController::class, 'export'])
        ->middleware('permission:armis.report.export');
    Route::get('/armis/report-exports/{export}/download', [ArmisReportController::class, 'download'])
        ->middleware('permission:armis.report.export');
    Route::get('/armis/administration', [ArmisReportController::class, 'administration'])
        ->middleware('permission:armis.report.view');
    Route::get('/armis/provider/status', [ArmisProviderController::class, 'status'])
        ->middleware('permission:armis.provider.view');
    Route::get('/armis/provider/reconciliations', [ArmisProviderController::class, 'index'])
        ->middleware('permission:armis.provider.view');
    Route::post('/armis/provider/reconciliations', [ArmisProviderController::class, 'store'])
        ->middleware('permission:armis.provider.reconcile');
    Route::get('/armis/provider/reconciliations/{run}', [ArmisProviderController::class, 'show'])
        ->middleware('permission:armis.provider.view');
    Route::post('/armis/provider/reconciliations/{run}/review', [ArmisProviderController::class, 'review'])
        ->middleware('permission:armis.provider.review');
    Route::post('/armis/provider/reconciliations/{run}/activate', [ArmisProviderController::class, 'activate'])
        ->middleware('permission:armis.provider.switch');
    Route::post('/armis/provider/rollback', [ArmisProviderController::class, 'rollback'])
        ->middleware('permission:armis.provider.rollback');
    Route::get('/armis/provider/monitoring/status', [ArmisProviderMonitoringController::class, 'status'])
        ->middleware('permission:armis.provider.view');
    Route::get('/armis/provider/monitoring/checks', [ArmisProviderMonitoringController::class, 'index'])
        ->middleware('permission:armis.provider.view');
    Route::post('/armis/provider/monitoring/checks', [ArmisProviderMonitoringController::class, 'store'])
        ->middleware('permission:armis.provider.monitor');
    Route::get('/armis/provider/monitoring/checks/{check}', [ArmisProviderMonitoringController::class, 'show'])
        ->middleware('permission:armis.provider.view');

    Route::get('/iap/audit-universe', [IapAuditUniverseController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::post('/iap/audit-universe', [IapAuditUniverseController::class, 'store'])
        ->middleware('permission:iap.manage_universe');
    Route::put('/iap/audit-universe/{auditUniverse}', [IapAuditUniverseController::class, 'update'])
        ->middleware('permission:iap.manage_universe');
    Route::delete('/iap/audit-universe/{auditUniverse}', [IapAuditUniverseController::class, 'destroy'])
        ->middleware('permission:iap.manage_universe');
    Route::post('/iap/audit-universe/{auditUniverse}/restore', [IapAuditUniverseController::class, 'restore'])
        ->middleware('permission:iap.manage_universe');

    Route::get('/iap/risk-periods', [IapRiskPeriodController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::post('/iap/risk-periods', [IapRiskPeriodController::class, 'store'])
        ->middleware('permission:iap.create');
    Route::get('/iap/risk-periods/{period}', [IapRiskPeriodController::class, 'show'])
        ->middleware('permission:iap.view');
    Route::put('/iap/risk-periods/{period}', [IapRiskPeriodController::class, 'update'])
        ->middleware('permission:iap.update');
    Route::delete('/iap/risk-periods/{period}', [IapRiskPeriodController::class, 'destroy'])
        ->middleware('permission:iap.archive');
    Route::post('/iap/risk-periods/{period}/restore', [IapRiskPeriodController::class, 'restore'])
        ->middleware('permission:iap.restore');
    Route::post('/iap/risk-periods/{period}/transitions/{action}', [IapRiskPeriodController::class, 'transition']);
    Route::post('/iap/risk-periods/{period}/assessments', [IapUniverseRiskAssessmentController::class, 'store'])
        ->middleware('permission:iap.assess_risk');
    Route::put('/iap/risk-periods/{period}/assessments/{assessment}', [IapUniverseRiskAssessmentController::class, 'update'])
        ->middleware('permission:iap.assess_risk');
    Route::delete('/iap/risk-periods/{period}/assessments/{assessment}', [IapUniverseRiskAssessmentController::class, 'destroy'])
        ->middleware('permission:iap.assess_risk');
    Route::post('/iap/risk-periods/{period}/assessments/{assessment}/restore', [IapUniverseRiskAssessmentController::class, 'restore'])
        ->middleware('permission:iap.assess_risk');
    Route::post('/iap/risk-periods/{period}/assessments/{assessment}/evidence', [IapUniverseRiskAssessmentController::class, 'uploadEvidence'])
        ->middleware('permission:iap.assess_risk');
    Route::get('/iap/risk-periods/{period}/assessments/{assessment}/evidence/{evidence}', [IapUniverseRiskAssessmentController::class, 'downloadEvidence'])
        ->middleware('permission:iap.view');
    Route::delete('/iap/risk-periods/{period}/assessments/{assessment}/evidence/{evidence}', [IapUniverseRiskAssessmentController::class, 'destroyEvidence'])
        ->middleware('permission:iap.assess_risk');

    Route::get('/iap/prioritizations', [IapPrioritizationController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::post('/iap/prioritizations', [IapPrioritizationController::class, 'store'])
        ->middleware('permission:iap.create');
    Route::get('/iap/prioritizations/{prioritization}', [IapPrioritizationController::class, 'show'])
        ->middleware('permission:iap.view');
    Route::put('/iap/prioritizations/{prioritization}', [IapPrioritizationController::class, 'update'])
        ->middleware('permission:iap.update');
    Route::put('/iap/prioritizations/{prioritization}/items/{item}', [IapPrioritizationController::class, 'updateItem'])
        ->middleware('permission:iap.update');
    Route::post('/iap/prioritizations/{prioritization}/transitions/{action}', [IapPrioritizationController::class, 'transition']);
    Route::delete('/iap/prioritizations/{prioritization}', [IapPrioritizationController::class, 'destroy'])
        ->middleware('permission:iap.archive');
    Route::post('/iap/prioritizations/{prioritization}/restore', [IapPrioritizationController::class, 'restore'])
        ->middleware('permission:iap.restore');

    Route::get('/documents', [DocumentController::class, 'index'])
        ->middleware('permission:documents.view');
    Route::post('/documents', [DocumentController::class, 'store'])
        ->middleware('permission:documents.upload');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])
        ->middleware('permission:documents.update');
    Route::post('/documents/{document}/versions', [DocumentController::class, 'storeVersion'])
        ->middleware('permission:documents.update');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
        ->middleware('permission:documents.delete');
    Route::post('/documents/{document}/restore', [DocumentController::class, 'restore'])
        ->middleware('permission:documents.restore');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
        ->middleware('permission:documents.download');
    Route::get('/documents/{document}/versions/{version}/download', [DocumentController::class, 'downloadVersion'])
        ->middleware('permission:documents.download');

    Route::post('/demo/reset', ResetDemoDataController::class)
        ->middleware('permission:system_configuration.manage');
});
