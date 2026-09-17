<?php

namespace Tests\Feature\Api;

use App\Models\IapBaicsAssessment;
use App\Models\IapBaicsReport;
use App\Models\IapBaicsReportVersion;
use App\Models\IapBaicsIntegration;
use App\Models\IapRiskPeriod;
use App\Models\SystemNotification;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IapBaicsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_legacy_decision_has_independent_review_and_approval_and_unblocks_readiness(): void
    {
        [$creator, $reviewer, $authority, $assessment, $period] = $this->context();
        $payload = [
            'consumerType' => 'RISK_PERIOD',
            'consumerId' => $period->id,
            'decisionType' => 'LEGACY_EXCEPTION',
            'legacyReason' => 'Historic IAP cycle predates the BAICS baseline.',
            'compensatingSource' => 'Approved legacy risk-period memorandum LEGACY-2026-01.',
            'expiresAt' => now()->addMonths(3)->toDateString(),
            'reviewerId' => $reviewer->id,
            'authorityUserId' => $authority->id,
        ];

        Sanctum::actingAs($creator);
        $draft = $this->postJson("/api/iap/baics/{$assessment->id}/integrations", $payload)
            ->assertCreated()->json('data.integration');
        $this->assertSame('DRAFT', $draft['status']);

        $this->postJson("/api/iap/baics/integrations/{$draft['id']}/transitions/SUBMIT", [])->assertOk();
        Sanctum::actingAs($authority);
        $this->postJson("/api/iap/baics/integrations/{$draft['id']}/transitions/APPROVE", [])->assertUnprocessable()->assertJsonValidationErrors('reviewer');
        Sanctum::actingAs($reviewer);
        $this->postJson("/api/iap/baics/integrations/{$draft['id']}/transitions/REVIEW", ['comment' => 'Reviewed source and compensating controls.'])->assertOk();
        Sanctum::actingAs($authority);
        $approved = $this->postJson("/api/iap/baics/integrations/{$draft['id']}/transitions/APPROVE", [])->assertOk()->json('data.integration');

        $this->assertSame('APPROVED', $approved['status']);
        $this->assertNotNull($approved['reviewedAt']);
        $this->assertDatabaseCount('iap_baics_integration_versions', 4);
        $this->assertDatabaseHas('iap_baics_integrations', ['id' => $draft['id'], 'decision_type' => 'LEGACY_EXCEPTION', 'status' => 'APPROVED']);

        Sanctum::actingAs($creator);
        $readiness = $this->getJson("/api/iap/baics/integrations/readiness?consumerType=RISK_PERIOD&consumerId={$period->id}")
            ->assertOk()->json('data.readiness');
        $this->assertTrue($readiness['ready']);
        $this->assertSame('LEGACY_EXCEPTION', $readiness['decision']['type']);
    }

    public function test_baics_backed_decision_requires_an_approved_immutable_report_version(): void
    {
        [$creator, $reviewer, $authority, $assessment, $period] = $this->context();
        $report = IapBaicsReport::query()->create([
            'assessment_id' => $assessment->id,
            'report_code' => 'BAR-INT-001',
            'title' => 'Baseline Assessment Report',
            'status' => 'DRAFT',
            'prepared_by' => $creator->id,
            'version_number' => 1,
            'lock_version' => 1,
            'is_current_revision' => true,
        ]);
        $version = IapBaicsReportVersion::query()->create([
            'report_id' => $report->id,
            'version_number' => 1,
            'status' => 'DRAFT',
            'snapshot' => ['reportCode' => $report->report_code],
            'source_manifest_sha256' => hash('sha256', 'manifest'),
            'content_sha256' => hash('sha256', 'content'),
            'file_version' => 'BAR-INT-001-v1',
            'created_by' => $creator->id,
        ]);

        Sanctum::actingAs($creator);
        $this->postJson("/api/iap/baics/{$assessment->id}/integrations", [
            'consumerType' => 'RISK_PERIOD', 'consumerId' => $period->id,
            'decisionType' => 'BAICS_BACKED', 'reportId' => $report->id,
            'reportVersionId' => $version->id, 'reviewerId' => $reviewer->id,
            'authorityUserId' => $authority->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('reportVersionId');

        $this->assertDatabaseCount('iap_baics_integrations', 0);
        $this->assertDatabaseHas('iap_baics_reports', ['id' => $report->id, 'status' => 'DRAFT']);
    }

    public function test_approved_bar_lineage_is_snapshotted_without_writing_the_source_records(): void
    {
        [$creator, $reviewer, $authority, $assessment, $period] = $this->context();
        $report = IapBaicsReport::query()->create([
            'assessment_id' => $assessment->id, 'report_code' => 'BAR-APP-001',
            'title' => 'Approved Baseline Assessment Report', 'status' => 'APPROVED',
            'prepared_by' => $creator->id, 'approved_by' => $authority->id,
            'approved_at' => now(), 'version_number' => 1, 'lock_version' => 1,
            'is_current_revision' => true, 'source_manifest' => ['controls' => [1, 2]],
        ]);
        $sourceBefore = $report->fresh()->toArray();
        $version = IapBaicsReportVersion::query()->create([
            'report_id' => $report->id, 'version_number' => 1, 'status' => 'APPROVED',
            'snapshot' => ['reportCode' => $report->report_code, 'findingCount' => 2],
            'source_manifest' => $report->source_manifest,
            'source_manifest_sha256' => hash('sha256', json_encode($report->source_manifest)),
            'content_sha256' => hash('sha256', 'approved-content'),
            'file_version' => 'BAR-APP-001-v1', 'created_by' => $creator->id,
        ]);

        Sanctum::actingAs($creator);
        $draft = $this->postJson("/api/iap/baics/{$assessment->id}/integrations", [
            'consumerType' => 'RISK_PERIOD', 'consumerId' => $period->id,
            'decisionType' => 'BAICS_BACKED', 'reportId' => $report->id,
            'reportVersionId' => $version->id, 'reviewerId' => $reviewer->id,
            'authorityUserId' => $authority->id,
        ])->assertCreated()->json('data.integration');

        $this->assertSame($report->report_code, $draft['sourceSnapshot']['reportCode']);
        $this->assertSame($version->id, $draft['sourceSnapshot']['reportVersionId']);
        $this->assertSame($sourceBefore, $report->fresh()->toArray());
        $this->assertDatabaseHas('iap_baics_integration_versions', ['integration_id' => $draft['id'], 'version_number' => 1]);
    }

    public function test_readiness_endpoint_enforces_office_scope(): void
    {
        [$creator, , , $assessment, $period] = $this->context();
        $outOfScope = User::query()->where('username', 'auditee')->firstOrFail();
        Sanctum::actingAs($outOfScope);
        $this->getJson("/api/iap/baics/integrations/readiness?consumerType=RISK_PERIOD&consumerId={$period->id}")
            ->assertForbidden();
        $this->assertDatabaseCount('iap_baics_integrations', 0);
        $this->assertNotNull($creator->id);
        $this->assertNotNull($assessment->id);
    }

    public function test_integration_notifications_are_scoped_to_participants_and_deduplicated(): void
    {
        [$creator, $reviewer, $authority, $assessment, $period] = $this->context();
        $payload = [
            'consumerType' => 'RISK_PERIOD',
            'consumerId' => $period->id,
            'decisionType' => 'LEGACY_EXCEPTION',
            'legacyReason' => 'The prior planning cycle predates the baseline.',
            'compensatingSource' => 'Approved legacy planning memorandum.',
            'expiresAt' => now()->addMonths(2)->toDateString(),
            'reviewerId' => $reviewer->id,
            'authorityUserId' => $authority->id,
        ];

        Sanctum::actingAs($creator);
        $draft = $this->postJson("/api/iap/baics/{$assessment->id}/integrations", $payload)
            ->assertCreated()->json('data.integration');

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $reviewer->id,
            'type' => 'IAP_BAICS_INTEGRATION_DRAFTED',
            'subject_id' => $draft['id'],
        ]);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $authority->id,
            'type' => 'IAP_BAICS_INTEGRATION_DRAFTED',
            'subject_id' => $draft['id'],
        ]);
        $this->assertDatabaseMissing('notifications', [
            'recipient_id' => $creator->id,
            'type' => 'IAP_BAICS_INTEGRATION_DRAFTED',
            'subject_id' => $draft['id'],
        ]);

        $this->postJson("/api/iap/baics/integrations/{$draft['id']}/transitions/SUBMIT", [])->assertOk();
        Sanctum::actingAs($reviewer);
        $this->postJson("/api/iap/baics/integrations/{$draft['id']}/transitions/REVIEW", ['comment' => 'Reviewed independently.'])->assertOk();
        Sanctum::actingAs($authority);
        $this->postJson("/api/iap/baics/integrations/{$draft['id']}/transitions/APPROVE", [])->assertOk();

        $this->assertDatabaseHas('notifications', ['recipient_id' => $creator->id, 'type' => 'IAP_BAICS_INTEGRATION_REVIEW', 'subject_id' => $draft['id']]);
        $this->assertDatabaseHas('notifications', ['recipient_id' => $reviewer->id, 'type' => 'IAP_BAICS_INTEGRATION_APPROVE', 'subject_id' => $draft['id']]);
        $this->assertSame(
            1,
            SystemNotification::query()
                ->where('recipient_id', $reviewer->id)
                ->where('type', 'IAP_BAICS_INTEGRATION_DRAFTED')
                ->where('subject_id', $draft['id'])
                ->count(),
        );
    }

    public function test_read_only_administrator_can_read_but_cannot_create_an_integration_decision(): void
    {
        [, $reviewer, $authority, $assessment, $period] = $this->context();
        $administrator = User::query()->where('username', 'agisadmin')->firstOrFail();
        $this->assertDatabaseHas('permissions', ['code' => 'iap.baics.integration.view']);
        $this->assertDatabaseHas('permissions', ['code' => 'iap.baics.integration.approve']);
        $this->assertFalse($administrator->hasPermission('iap.baics.integration.create'));
        Sanctum::actingAs($administrator);

        $this->getJson('/api/iap/baics/integrations/candidates')->assertOk();
        $this->postJson("/api/iap/baics/{$assessment->id}/integrations", [
            'consumerType' => 'RISK_PERIOD',
            'consumerId' => $period->id,
            'decisionType' => 'LEGACY_EXCEPTION',
            'legacyReason' => 'Not permitted.',
            'compensatingSource' => 'Not permitted.',
            'expiresAt' => now()->addMonth()->toDateString(),
            'reviewerId' => $reviewer->id,
            'authorityUserId' => $authority->id,
        ])->assertForbidden();
    }

    public function test_integration_assignment_rejects_inactive_or_unprivileged_reviewers(): void
    {
        [$creator, , $authority, $assessment, $period] = $this->context();
        $auditee = User::query()->where('username', 'auditee')->firstOrFail();
        Sanctum::actingAs($creator);

        $this->postJson("/api/iap/baics/{$assessment->id}/integrations", [
            'consumerType' => 'RISK_PERIOD',
            'consumerId' => $period->id,
            'decisionType' => 'LEGACY_EXCEPTION',
            'legacyReason' => 'Invalid reviewer assignment.',
            'compensatingSource' => 'The auditee is not an IAP reviewer.',
            'expiresAt' => now()->addMonth()->toDateString(),
            'reviewerId' => $auditee->id,
            'authorityUserId' => $authority->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('reviewerId');
    }

    /** @return array{0: User, 1: User, 2: User, 3: IapBaicsAssessment, 4: IapRiskPeriod} */
    private function context(): array
    {
        $creator = User::query()->where('username', 'departmenthead')->firstOrFail();
        $reviewer = User::query()->where('username', 'auditor')->firstOrFail();
        $authority = User::query()->where('username', 'admin')->firstOrFail();
        $assessment = IapBaicsAssessment::query()->create([
            'family_uuid' => (string) Str::uuid(),
            'assessment_code' => 'BAICS-INT-'.Str::upper(Str::random(6)),
            'version_number' => 1,
            'assessment_year' => 2026,
            'name' => 'Integration Test Baseline',
            'status' => 'APPROVED',
            'responsible_office_id' => $creator->office_id,
            'scope_summary' => 'Integration test scope',
            'objectives' => 'Confirm source lineage',
            'methodology' => 'Document review',
            'prepared_by' => $creator->id,
            'approved_by' => $authority->id,
            'approved_at' => now(),
            'is_current_revision' => true,
            'lock_version' => 1,
        ]);
        $period = IapRiskPeriod::query()->create([
            'period_code' => 'RISK-INT-'.Str::upper(Str::random(6)),
            'name' => 'Integration test risk period',
            'assessment_year' => 2026,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'status' => 'VALIDATED',
            'created_by' => $creator->id,
            'lock_version' => 1,
            'is_active' => true,
        ]);
        return [$creator, $reviewer, $authority, $assessment, $period];
    }
}
