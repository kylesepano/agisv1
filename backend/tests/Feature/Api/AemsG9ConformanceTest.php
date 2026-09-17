<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AEMS-G9 as-built conformance checks.
 *
 * These tests deliberately exercise the source contract rather than inventing
 * a second workflow implementation.  The matrices are the verification index
 * used by the AEMS truth-pass documentation and keep every rule/SCR visible
 * to the regression suite.
 */
class AemsG9ConformanceTest extends TestCase
{
    #[DataProvider('ruleMatrix')]
    public function test_rule_is_represented_in_the_as_built_contract(string $rule, string $file, string $marker): void
    {
        $path = base_path($file);

        $this->assertFileExists($path, $rule.' source contract is missing.');
        $this->assertStringContainsString(
            $marker,
            (string) file_get_contents($path),
            $rule.' is no longer represented by its registered source contract.',
        );
    }

    #[DataProvider('scrMatrix')]
    public function test_scr_is_registered_once_in_the_canonical_inventory(string $scrId): void
    {
        $navigation = (string) file_get_contents(dirname(base_path()).DIRECTORY_SEPARATOR.'src/config/navigation.js');
        $occurrences = preg_match_all('/(?:scrId|id):\s*[\'\"]'.preg_quote($scrId, '/').'[\'\"]/', $navigation);

        $this->assertSame(1, $occurrences, $scrId.' must have exactly one canonical registry entry.');
    }

    public function test_aems_download_and_export_routes_are_authenticated(): void
    {
        $downloadRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route): bool {
                $uri = $route->uri();

                return str_starts_with($uri, 'api/aems/')
                    && (str_contains($uri, 'download') || str_contains($uri, '/exports/'));
            });

        $this->assertNotEmpty($downloadRoutes, 'The AEMS protected download contract must expose at least one route.');

        foreach ($downloadRoutes as $route) {
            $this->assertContains(
                'auth:sanctum',
                $route->gatherMiddleware(),
                $route->uri().' must remain authenticated.',
            );
        }
    }

    public function test_migration_rehearsal_manifest_contains_the_current_aems_contract(): void
    {
        $migrationDirectory = base_path('database/migrations');
        $requiredMigrations = [
            '2026_08_30_000000_add_aems_g3_planning_conformance.php',
            '2026_08_31_000000_add_aems_g4_authority_controls.php',
            '2026_09_01_000000_add_aems_g5_evidence_lifecycle.php',
            '2026_09_02_000000_add_aems_g6_issue_dialogue_contract.php',
            '2026_09_03_000000_add_aems_g7_reporting_distribution_contract.php',
            '2026_09_04_000000_add_aems_g8_records_calendar_closure.php',
            '2026_09_04_000001_add_aems_g8_legal_hold_release_reference.php',
        ];

        foreach ($requiredMigrations as $migration) {
            $path = $migrationDirectory.DIRECTORY_SEPARATOR.$migration;
            $this->assertFileExists($path, $migration.' is absent from the migration rehearsal set.');
            $this->assertStringContainsString('Schema::', (string) file_get_contents($path));
        }
    }

    /**
     * The 35 MDS rules are represented by the authoritative source marker that
     * implements or guards each control.  Keep this list explicit: adding a
     * rule requires adding its source proof and a focused test row.
     *
     * @return array<string, array{string,string,string}>
     */
    public static function ruleMatrix(): array
    {
        return [
            'Rule 01 — engagement scope visibility' => ['Rule 01', 'app/Services/Aems/AemsAccessService.php', 'visibleEngagements'],
            'Rule 02 — action authorization' => ['Rule 02', 'app/Services/Aems/AemsAccessService.php', 'authorizeEngagementAction'],
            'Rule 03 — one-office source field' => ['Rule 03', 'app/Services/Aems/AemsEngagementRegistryService.php', 'engagement_office_id'],
            'Rule 04 — IAP lineage' => ['Rule 04', 'app/Services/Aems/AemsEngagementRegistryService.php', 'iap_plan_engagement_id'],
            'Rule 05 — IAP risk source discriminator' => ['Rule 05', 'app/Services/Aems/AemsEngagementRegistryService.php', 'iapRiskSourceType'],
            'Rule 06 — optimistic locking' => ['Rule 06', 'app/Services/Aems/AemsEngagementRegistryService.php', 'lock_version'],
            'Rule 07 — lifecycle projection' => ['Rule 07', 'app/Models/Aems/AuditEngagement.php', 'lifecycleProjectionForStatus'],
            'Rule 08 — completed state' => ['Rule 08', 'app/Models/Aems/AuditEngagement.php', 'COMPLETED'],
            'Rule 09 — fieldwork transition gate' => ['Rule 09', 'app/Services/Aems/AemsEngagementTransitionService.php', 'START_FIELDWORK'],
            'Rule 10 — AEO signatory control' => ['Rule 10', 'app/Services/Aems/AemsAeoService.php', 'signatory'],
            'Rule 11 — AEO distribution control' => ['Rule 11', 'app/Services/Aems/AemsAeoService.php', 'distribution'],
            'Rule 12 — competency safeguards' => ['Rule 12', 'app/Services/Aems/AemsTeamSafeguardService.php', 'competencyChecks'],
            'Rule 13 — independence safeguards' => ['Rule 13', 'app/Services/Aems/AemsTeamSafeguardService.php', 'independence declarations'],
            'Rule 14 — process-flow structure' => ['Rule 14', 'app/Models/Aems/AemsProcessFlowDocument.php', 'decision_points'],
            'Rule 15 — risk matrix relationships' => ['Rule 15', 'app/Models/Aems/AemsRiskMatrixItem.php', 'process_flow_id'],
            'Rule 16 — risk response' => ['Rule 16', 'app/Models/Aems/AemsRiskMatrixItem.php', 'risk_response'],
            'Rule 17 — program criteria' => ['Rule 17', 'app/Services/Aems/AemsProgramService.php', 'audit_criteria'],
            'Rule 18 — planned effort' => ['Rule 18', 'app/Services/Aems/AemsProgramService.php', 'planned_person_days'],
            'Rule 19 — planning KPI immutability' => ['Rule 19', 'app/Models/Aems/AemsPlanningKpi.php', 'immutable'],
            'Rule 20 — fieldwork execution status' => ['Rule 20', 'app/Models/Aems/AemsFieldworkRecord.php', 'EXECUTION_STATUSES'],
            'Rule 21 — completed procedure traceability' => ['Rule 21', 'app/Services/Aems/AemsProgramService.php', 'finalized Fieldwork Record'],
            'Rule 22 — working-paper conclusion' => ['Rule 22', 'app/Services/Aems/AemsWorkingPaperService.php', "'conclusion'"],
            'Rule 23 — evidence checksum' => ['Rule 23', 'app/Services/Aems/AemsEvidenceService.php', 'checksum_sha256'],
            'Rule 24 — evidence request lifecycle' => ['Rule 24', 'app/Models/Aems/AemsEvidenceRequest.php', 'CLOSED_WITHOUT_SUBMISSION'],
            'Rule 25 — evidence eligibility' => ['Rule 25', 'app/Services/Aems/AemsEvidenceRequestService.php', 'evidenceEligibility'],
            'Rule 26 — evidence version locking' => ['Rule 26', 'app/Models/Aems/AemsEvidenceAssessment.php', 'versions are immutable'],
            'Rule 27 — issue withdrawal' => ['Rule 27', 'app/Services/Aems/AemsFindingService.php', 'withdrawal_reason'],
            'Rule 28 — finding conclusion' => ['Rule 28', 'app/Services/Aems/AemsFindingService.php', "'conclusion'"],
            'Rule 29 — finding fieldwork traceability' => ['Rule 29', 'app/Services/Aems/AemsFindingService.php', 'fieldworkRecordVersionIds'],
            'Rule 30 — AFR transmittal' => ['Rule 30', 'app/Services/Aems/AemsFindingService.php', 'transmittal_method'],
            'Rule 31 — dialogue queues' => ['Rule 31', 'app/Services/Aems/AemsWorkQueueService.php', 'reviewCandidate'],
            'Rule 32 — immutable report export' => ['Rule 32', 'app/Services/Aems/AemsReportService.php', 'sourceManifestSha256'],
            'Rule 33 — report administrative closure' => ['Rule 33', 'app/Services/Aems/AemsReportService.php', 'ADMINISTRATIVELY_CLOSED'],
            'Rule 34 — CMS transfer snapshot' => ['Rule 34', 'app/Services/Aems/AemsCompletionTransferService.php', 'manifest_snapshot_json'],
            'Rule 35 — records and legal hold controls' => ['Rule 35', 'app/Services/Aems/AemsRecordsCalendarService.php', 'legalHoldReleaseReference'],
        ];
    }

    /**
     * The current UID/DGM contract has 32 SCR identifiers.  AEMS dashboard and
     * the two legacy sidebar entries intentionally have no SCR id and are not
     * counted here.
     *
     * @return array<string, array{string}>
     */
    public static function scrMatrix(): array
    {
        $ids = [
            'SCR-210', 'SCR-211', 'SCR-212', 'SCR-213', 'SCR-214', 'SCR-220',
            'SCR-221', 'SCR-222', 'SCR-223', 'SCR-224', 'SCR-225', 'SCR-226',
            'SCR-227', 'SCR-228', 'SCR-229', 'SCR-230', 'SCR-231', 'SCR-232',
            'SCR-240', 'SCR-241', 'SCR-242', 'SCR-243', 'SCR-244', 'SCR-250',
            'SCR-251', 'SCR-252', 'SCR-253', 'SCR-254', 'SCR-260', 'SCR-261',
            'SCR-262', 'SCR-263',
        ];

        return collect($ids)->mapWithKeys(fn (string $id): array => [$id => [$id]])->all();
    }
}
