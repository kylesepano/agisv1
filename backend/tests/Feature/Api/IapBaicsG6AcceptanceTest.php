<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * BAICS-6 conformance checks: governance, schema rehearsal, ownership, and
 * canonical navigation. Mutation behavior remains covered by the BAICS
 * workflow suites; this class protects the final cross-cutting contract.
 */
class IapBaicsG6AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_governance_matrix_and_role_permission_contract_are_complete(): void
    {
        $contract = file_get_contents(dirname(base_path()).'/docs/BAICS_GOVERNANCE_CONTRACT.md');

        $this->assertIsString($contract);
        foreach (range(1, 15) as $rule) {
            $this->assertStringContainsString(sprintf('BAICS-%02d', $rule), $contract);
        }

        $expectedPermissions = [
            'iap.baics.view', 'iap.baics.create', 'iap.baics.update',
            'iap.baics.assign', 'iap.baics.submit', 'iap.baics.review',
            'iap.baics.return', 'iap.baics.approve', 'iap.baics.publish',
            'iap.baics.archive', 'iap.baics.export', 'iap.baics.manage-controls',
            'iap.baics.integration.view', 'iap.baics.integration.create',
            'iap.baics.integration.update', 'iap.baics.integration.submit',
            'iap.baics.integration.review', 'iap.baics.integration.return',
            'iap.baics.integration.approve', 'iap.baics.integration.retire',
        ];

        $this->assertSame([], array_values(array_diff(
            $expectedPermissions,
            Permission::query()->pluck('code')->all(),
        )));

        $administrator = User::query()->where('username', 'agisadmin')->firstOrFail();
        $management = User::query()->where('username', 'departmenthead')->firstOrFail();
        $auditor = User::query()->where('username', 'auditor')->firstOrFail();

        $this->assertTrue($administrator->hasPermission('iap.baics.view'));
        $this->assertFalse($administrator->hasPermission('iap.baics.integration.approve'));
        $this->assertTrue($management->hasAllPermissions([
            'iap.baics.integration.create',
            'iap.baics.integration.review',
            'iap.baics.integration.approve',
        ]));
        $this->assertTrue($auditor->hasPermission('iap.baics.integration.review'));
    }

    public function test_baics_schema_contract_and_shared_ownership_survive_rehearsal(): void
    {
        $tables = [
            'iap_baics_assessments', 'iap_baics_scope_items', 'iap_baics_assignments',
            'iap_baics_versions', 'iap_baics_components', 'iap_baics_methods',
            'iap_baics_component_versions', 'iap_baics_method_versions',
            'iap_baics_evidence_links', 'iap_baics_exceptions',
            'iap_baics_exception_versions', 'iap_baics_controls',
            'iap_baics_control_versions', 'iap_baics_control_methods',
            'iap_baics_control_evidence', 'iap_baics_interim_analyses',
            'iap_baics_interim_analysis_versions', 'iap_baics_reports',
            'iap_baics_report_versions', 'iap_baics_report_controls',
            'iap_baics_report_interim_analyses', 'iap_baics_integrations',
            'iap_baics_integration_versions',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing BAICS table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('iap_baics_assessments', [
            'assessment_code', 'responsible_office_id', 'prepared_by', 'lock_version',
        ]));
        $this->assertTrue(Schema::hasColumns('iap_baics_integrations', [
            'consumer_type', 'consumer_id', 'decision_type', 'status',
            'reviewer_id', 'authority_user_id', 'source_snapshot', 'lock_version',
        ]));
        $this->assertTrue(Schema::hasColumns('iap_baics_integration_versions', [
            'integration_id', 'version_number', 'snapshot', 'snapshot_sha256',
        ]));
        $this->assertTrue(Schema::hasColumns('iap_baics_report_versions', [
            'report_id', 'source_manifest_sha256', 'content_sha256',
            'pdf_checksum_sha256', 'csv_checksum_sha256',
        ]));

        // BAICS references shared owners; it does not introduce duplicate
        // offices, users, areas, focuses, documents, or risk ledgers.
        foreach ([
            'offices', 'users', 'audit_areas', 'audit_focuses',
            'document_versions', 'iap_risk_assessments',
            'iap_universe_risk_assessments',
        ] as $ownerTable) {
            $this->assertTrue(Schema::hasTable($ownerTable));
            $this->assertFalse(Schema::hasTable('iap_baics_'.$ownerTable));
        }
    }

    public function test_canonical_baics_navigation_paths_are_unique_and_explicit(): void
    {
        $navigation = file_get_contents(dirname(base_path()).'/src/config/navigation.js');

        $this->assertIsString($navigation);
        foreach ([
            '/internal-audit-planning/baics',
            '/internal-audit-planning/baics/control-universe',
            '/internal-audit-planning/baics/integration',
        ] as $path) {
            $this->assertSame(1, substr_count($navigation, 'path: "'.$path.'"'), "Duplicate or missing navigation path: {$path}");
        }
    }
}
