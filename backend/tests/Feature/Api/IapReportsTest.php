<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\InternalAuditPlan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\IapSchedulingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IapReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_catalog_lists_all_reports_role_scoped_contexts_and_export_capability(): void
    {
        $plan = $this->approveDemoPlan();
        Sanctum::actingAs($this->user('departmenthead'));

        $this->getJson('/api/iap/reports')
            ->assertOk()
            ->assertJsonCount(9, 'data.reports')
            ->assertJsonPath('data.canExport', true)
            ->assertJsonFragment(['code' => 'approved-siap'])
            ->assertJsonFragment(['code' => 'risk-heat-map'])
            ->assertJsonFragment(['code' => 'auditor-allocation'])
            ->assertJsonFragment(['id' => $plan->id]);

        Sanctum::actingAs($this->user('mayor'));
        $this->getJson('/api/iap/reports')
            ->assertOk()
            ->assertJsonPath('data.canExport', false)
            ->assertJsonCount(1, 'data.approvedPlans');
    }

    public function test_all_nine_report_previews_are_generated_from_live_seeded_data(): void
    {
        $plan = $this->approveDemoPlan();
        Sanctum::actingAs($this->user('departmenthead'));

        $requests = [
            '/api/iap/reports/approved-siap',
            '/api/iap/reports/audit-universe',
            '/api/iap/reports/risk-assessment-matrix',
            '/api/iap/reports/risk-heat-map',
            '/api/iap/reports/prioritization-ranking',
            "/api/iap/reports/approved-annual-plan?planId={$plan->id}",
            "/api/iap/reports/annual-audit-schedule?planId={$plan->id}",
            "/api/iap/reports/auditor-allocation?fiscalYear={$plan->fiscal_year}",
            "/api/iap/reports/plan-revision-history?fiscalYear={$plan->fiscal_year}",
        ];

        foreach ($requests as $request) {
            $response = $this->getJson($request)
                ->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        'report' => [
                            'code',
                            'title',
                            'description',
                            'generatedAt',
                            'fileName',
                            'meta',
                            'columns',
                            'rows',
                            'sections',
                            'visualization',
                        ],
                    ],
                ]);
            $this->assertNotEmpty(
                $response->json('data.report.columns'),
                "The report at {$request} did not define columns.",
            );
        }

        $this->getJson('/api/iap/reports/risk-heat-map')
            ->assertOk()
            ->assertJsonPath('data.report.visualization.type', 'riskHeatMap')
            ->assertJsonCount(4, 'data.report.visualization.levels')
            ->assertJsonCount(4, 'data.report.visualization.matrix');
    }

    public function test_authorized_users_can_export_pdf_excel_csv_and_print(): void
    {
        Sanctum::actingAs($this->user('departmenthead'));

        $csv = $this->get('/api/iap/reports/audit-universe/export?format=csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'attachment;',
            (string) $csv->headers->get('content-disposition'),
        );
        $this->assertStringContainsString(
            'Audit Universe Report',
            $csv->streamedContent(),
        );

        $this->get('/api/iap/reports/audit-universe/export?format=excel')
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.ms-excel; charset=UTF-8',
            )
            ->assertSee('Audit Universe Report');

        $pdf = $this->get('/api/iap/reports/audit-universe/export?format=pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $this->get('/api/iap/reports/audit-universe/export?format=print')
            ->assertOk()
            ->assertSee('Audit Universe Report')
            ->assertSee('Print report / Save as PDF');

        $exports = AuditLog::query()
            ->where('action', 'iap.report.exported')
            ->get();
        $this->assertCount(4, $exports);
        $this->assertEqualsCanonicalizing(
            ['csv', 'excel', 'pdf', 'print'],
            $exports->pluck('metadata.format')->all(),
        );
        $exports->each(function (AuditLog $export): void {
            $this->assertNotNull($export->user_id);
            $this->assertNotNull($export->ip_address);
            $this->assertSame('audit-universe', $export->metadata['report']);
            $this->assertNotEmpty($export->metadata['file_name']);
        });
    }

    public function test_report_preview_and_export_permissions_are_enforced(): void
    {
        Sanctum::actingAs($this->user('auditor'));
        $this->getJson('/api/iap/reports/audit-universe')->assertOk();
        $this->get('/api/iap/reports/audit-universe/export?format=csv')
            ->assertForbidden();

        Sanctum::actingAs($this->user('mayor'));
        $this->getJson('/api/iap/reports/audit-universe')->assertOk();
        $this->get('/api/iap/reports/audit-universe/export?format=pdf')
            ->assertForbidden();

        Sanctum::actingAs($this->user('auditee'));
        $this->getJson('/api/iap/reports')->assertForbidden();
        $this->getJson('/api/iap/reports/audit-universe')->assertForbidden();
    }

    public function test_read_only_users_cannot_request_an_unapproved_annual_plan(): void
    {
        $plan = $this->demoPlan();
        Sanctum::actingAs($this->user('mayor'));

        $this->getJson(
            "/api/iap/reports/approved-annual-plan?planId={$plan->id}",
        )->assertNotFound();
    }

    private function approveDemoPlan(): InternalAuditPlan
    {
        $plan = $this->demoPlan();
        $approver = $this->user('admin');
        $plan->forceFill([
            'status' => 'APPROVED',
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ])->save();

        return $plan->fresh();
    }

    private function demoPlan(): InternalAuditPlan
    {
        return InternalAuditPlan::query()
            ->where('plan_code', IapSchedulingSeeder::DEMO_PLAN_CODE)
            ->firstOrFail();
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
