<?php

namespace Tests\Feature\Api;

use App\Models\AemsEvidenceRequest;
use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditIssue;
use App\Models\AuditReport;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Final AEMS acceptance contract.
 *
 * Each row binds a MDS rule to a runtime class/method or a persisted status
 * constant. This is intentionally stricter than a documentation-only marker:
 * a rule cannot pass if its executable anchor or compatibility status is gone.
 */
class AemsG10EAcceptanceTest extends TestCase
{
    #[DataProvider('semanticRuleMatrix')]
    public function test_each_mds_rule_has_an_executable_runtime_contract(
        string $rule,
        string $class,
        string $kind,
        string $anchor,
        string $token,
    ): void {
        $reflection = new ReflectionClass($class);
        $this->assertTrue($reflection->isInstantiable() || $reflection->isAbstract(), $rule.' runtime class is unavailable.');

        if ($kind === 'method') {
            $this->assertTrue($reflection->hasMethod($anchor), $rule.' runtime method is missing.');
        } elseif ($kind === 'constant') {
            $this->assertTrue($reflection->hasConstant($anchor), $rule.' runtime constant is missing.');
        } else {
            $this->assertStringContainsString($token, (string) file_get_contents($reflection->getFileName()), $rule.' runtime token is missing.');
        }
    }

    public function test_status_compatibility_map_matches_runtime_codes(): void
    {
        $contract = (string) file_get_contents(base_path('../docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md'));
        foreach (['COMPLETED', 'CLOSED', 'ACKNOWLEDGED', 'EXTENSION_REQUESTED', 'ADMINISTRATIVELY_CLOSED', 'WITHDRAWN', 'ACCEPTED', 'CLOSED_WITHOUT_SUBMISSION'] as $status) {
            $this->assertStringContainsString($status, $contract);
        }

        $this->assertContains('COMPLETED', AuditEngagement::STATUSES);
        $this->assertContains('CLOSED', AuditEngagement::STATUSES);
        $this->assertContains('ACKNOWLEDGED', AemsEvidenceRequest::STATUSES);
        $this->assertContains('CLOSED_WITHOUT_SUBMISSION', AemsEvidenceRequest::STATUSES);
        $this->assertContains('WITHDRAWN', AuditIssue::STATUSES);
        $this->assertContains('WITHDRAWN', AuditFinding::STATUSES);
        $this->assertContains('ADMINISTRATIVELY_CLOSED', AuditReport::STATUSES);
    }

    public function test_aems_download_routes_are_authenticated_and_every_registered_scr_is_unique(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/aems/'));
        $this->assertNotEmpty($routes);
        $routes->each(fn ($route) => $this->assertContains('auth:sanctum', $route->gatherMiddleware(), $route->uri().' must remain authenticated.'));

        $source = (string) file_get_contents(base_path('../src/config/navigation.js'));
        foreach (['SCR-210', 'SCR-220', 'SCR-250', 'SCR-263'] as $scr) {
            $this->assertSame(1, preg_match_all('/(?:scrId|id):\s*[\'\"]'.preg_quote($scr, '/').'[\'\"]/', $source), $scr.' must have one canonical registry entry.');
        }
    }

    public static function semanticRuleMatrix(): array
    {
        return [
            'Rule 01' => ['Rule 01', 'App\\Services\\AemsAccessService', 'method', 'visibleEngagements', ''],
            'Rule 02' => ['Rule 02', 'App\\Services\\AemsAccessService', 'method', 'authorizeEngagementAction', ''],
            'Rule 03' => ['Rule 03', 'App\\Services\\AemsEngagementRegistryService', 'token', '', 'engagement_office_id'],
            'Rule 04' => ['Rule 04', 'App\\Services\\AemsEngagementRegistryService', 'method', 'import', ''],
            'Rule 05' => ['Rule 05', 'App\\Services\\AemsEngagementRegistryService', 'method', 'iapRiskSourceType', ''],
            'Rule 06' => ['Rule 06', 'App\\Services\\AemsEngagementRegistryService', 'token', '', 'lock_version'],
            'Rule 07' => ['Rule 07', 'App\\Models\\AuditEngagement', 'method', 'lifecycleProjectionForStatus', ''],
            'Rule 08' => ['Rule 08', 'App\\Models\\AuditEngagement', 'constant', 'STATUSES', ''],
            'Rule 09' => ['Rule 09', 'App\\Services\\AemsEngagementTransitionService', 'method', 'transition', ''],
            'Rule 10' => ['Rule 10', 'App\\Services\\AemsAeoService', 'method', 'ensureSignature', ''],
            'Rule 11' => ['Rule 11', 'App\\Services\\AemsAeoService', 'method', 'distribution', ''],
            'Rule 12' => ['Rule 12', 'App\\Services\\AemsTeamSafeguardService', 'method', 'competencyChecks', ''],
            'Rule 13' => ['Rule 13', 'App\\Services\\AemsTeamSafeguardService', 'method', 'evaluate', ''],
            'Rule 14' => ['Rule 14', 'App\\Models\\AemsProcessFlowDocument', 'token', '', 'decision_points'],
            'Rule 15' => ['Rule 15', 'App\\Models\\AemsRiskMatrixItem', 'method', 'processFlow', ''],
            'Rule 16' => ['Rule 16', 'App\\Models\\AemsRiskMatrixItem', 'token', '', 'risk_response'],
            'Rule 17' => ['Rule 17', 'App\\Services\\AemsProgramService', 'token', '', 'audit_criteria'],
            'Rule 18' => ['Rule 18', 'App\\Services\\AemsProgramService', 'token', '', 'planned_person_days'],
            'Rule 19' => ['Rule 19', 'App\\Models\\AemsPlanningKpi', 'token', '', 'immutable'],
            'Rule 20' => ['Rule 20', 'App\\Models\\AemsFieldworkRecord', 'constant', 'EXECUTION_STATUSES', ''],
            'Rule 21' => ['Rule 21', 'App\\Services\\AemsProgramService', 'token', '', 'finalized Fieldwork Record'],
            'Rule 22' => ['Rule 22', 'App\\Services\\AemsWorkingPaperService', 'token', '', "'conclusion'"],
            'Rule 23' => ['Rule 23', 'App\\Services\\AemsEvidenceService', 'token', '', 'checksum_sha256'],
            'Rule 24' => ['Rule 24', 'App\\Models\\AemsEvidenceRequest', 'constant', 'STATUSES', ''],
            'Rule 25' => ['Rule 25', 'App\\Services\\AemsEvidenceRequestService', 'method', 'evidenceEligibility', ''],
            'Rule 26' => ['Rule 26', 'App\\Models\\AemsEvidenceAssessment', 'token', '', 'immutable'],
            'Rule 27' => ['Rule 27', 'App\\Services\\AemsFindingService', 'token', '', 'withdrawal_reason'],
            'Rule 28' => ['Rule 28', 'App\\Services\\AemsFindingService', 'token', '', "'conclusion'"],
            'Rule 29' => ['Rule 29', 'App\\Services\\AemsFindingService', 'token', '', 'fieldworkRecordVersionIds'],
            'Rule 30' => ['Rule 30', 'App\\Services\\AemsFindingService', 'token', '', 'transmittal_method'],
            'Rule 31' => ['Rule 31', 'App\\Services\\AemsWorkQueueService', 'method', 'reviewCandidate', ''],
            'Rule 32' => ['Rule 32', 'App\\Services\\AemsReportService', 'token', '', 'sourceManifestSha256'],
            'Rule 33' => ['Rule 33', 'App\\Services\\AemsReportService', 'token', '', 'ADMINISTRATIVELY_CLOSED'],
            'Rule 34' => ['Rule 34', 'App\\Services\\AemsCompletionTransferService', 'token', '', 'manifest_snapshot_json'],
            'Rule 35' => ['Rule 35', 'App\\Services\\AemsRecordsCalendarService', 'token', '', 'legalHoldReleaseReference'],
        ];
    }
}
