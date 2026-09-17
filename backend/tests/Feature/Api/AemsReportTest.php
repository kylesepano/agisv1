<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditLog;
use App\Models\AuditRecommendation;
use App\Models\AuditReportVersion;
use App\Models\CmsRecommendation;
use App\Models\CmsRecommendationEvent;
use App\Models\EngagementTeam;
use App\Models\ExitConference;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AemsReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_report_versions_review_issuance_confidential_download_and_cms_transfer(): void
    {
        [$management, $auditor, $auditee, $engagement, $finding, $recommendation] =
            $this->reportContext();
        $confidential = MasterList::query()->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->firstOrFail()->items()->where('code', 'CONFIDENTIAL')->firstOrFail();

        Sanctum::actingAs($auditor);
        $report = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/reports",
            $this->content($finding, $confidential->id),
        )->assertCreated()
            ->assertJsonPath('data.report.reportStage', 'DRAFT_REPORT')
            ->assertJsonPath('data.report.status', 'DRAFT')
            ->assertJsonPath('data.report.versions.0.versionNumber', 1)
            ->assertJsonPath('data.report.versions.0.contentSnapshot.findings.0.id', $finding->id)
            ->json('data.report');
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $report['versions'][0]['checksumSha256'],
        );
        $this->assertFalse($report['versions'][0]['isLocked']);

        $report = $this->transition($engagement, $report, 'SUBMIT')
            ->assertJsonPath('data.report.status', 'PENDING_REVIEW')
            ->json('data.report');

        Sanctum::actingAs($management);
        $report = $this->transition($engagement, $report, 'RETURN', [
            'comment' => 'Clarify the control impact and make the executive summary more concise.',
        ])->assertJsonPath('data.report.status', 'RETURNED_FOR_REVISION')
            ->assertJsonPath('data.report.versions.0.reviewComments.0.action', 'RETURNED')
            ->json('data.report');

        Sanctum::actingAs($auditor);
        $revision = $this->content($finding, $confidential->id);
        $revision['executiveSummary'] = 'The audit identified a material reconciliation weakness requiring immediate supervisory control.';
        $revision['changeReason'] = 'Addressed the reviewer comments on impact and executive-summary clarity.';
        $revision['lockVersion'] = $report['lockVersion'];
        $report = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/reports/{$report['id']}/versions",
            $revision,
        )->assertOk()
            ->assertJsonPath('data.report.status', 'RESUBMITTED')
            ->assertJsonCount(2, 'data.report.versions')
            ->assertJsonPath('data.report.versions.1.versionNumber', 2)
            ->json('data.report');

        Sanctum::actingAs($management);
        $report = $this->transition($engagement, $report, 'APPROVE', [
            'comment' => 'The Draft Report is approved for finalization after dialogue.',
        ])->assertJsonPath('data.report.status', 'APPROVED')
            ->json('data.report');

        Sanctum::actingAs($auditor);
        $final = $this->finalContent(
            $finding,
            $confidential->id,
            $auditee,
            $report['lockVersion'],
        );
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/reports/{$report['id']}/final",
            $final,
        )->assertUnprocessable()->assertJsonValidationErrors('findingIds');

        $finding->forceFill([
            'status' => 'FINALIZED',
            'finalized_at' => now(),
            'finalized_by' => $management->id,
            'finalized_snapshot' => ['findingCode' => $finding->finding_code],
            'lock_version' => $finding->lock_version + 1,
        ])->save();
        $recommendation->forceFill([
            'status' => 'FINALIZED',
            'finalized_at' => now(),
            'finalized_by' => $management->id,
            'finalized_snapshot' => ['recommendation' => $recommendation->recommendation],
        ])->save();
        ExitConference::query()->create([
            'audit_engagement_id' => $engagement->id,
            'conference_code' => 'EXIT-REPORT-001',
            'scheduled_start_at' => now()->subDay(),
            'agenda' => 'Discuss the finalized finding and recommendation.',
            'minutes' => 'Management agreed with the final recommendation.',
            'status' => 'COMPLETED',
            'created_by' => $management->id,
            'completed_at' => now(),
            'completed_by' => $management->id,
        ]);

        $final['lockVersion'] = $report['lockVersion'];
        $report = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/reports/{$report['id']}/final",
            $final,
        )->assertOk()
            ->assertJsonPath('data.report.reportStage', 'FINAL_REPORT')
            ->assertJsonPath('data.report.status', 'DRAFT')
            ->assertJsonCount(3, 'data.report.versions')
            ->assertJsonPath('data.report.versions.2.findings.0.status', 'FINALIZED')
            ->assertJsonCount(1, 'data.report.versions.2.recipients')
            ->json('data.report');

        $report = $this->transition($engagement, $report, 'SUBMIT')
            ->assertJsonPath('data.report.status', 'PENDING_REVIEW')
            ->json('data.report');
        Sanctum::actingAs($management);
        $report = $this->transition($engagement, $report, 'APPROVE', [
            'comment' => 'Final content, authority, confidentiality, and recipients verified.',
        ])->assertJsonPath('data.report.status', 'APPROVED')
            ->json('data.report');
        $report = $this->transition($engagement, $report, 'ISSUE', [
            'issuanceDate' => now()->toDateString(),
        ])->assertJsonPath('data.report.status', 'ISSUED')
            ->assertJsonPath('data.report.versions.2.isLocked', true)
            ->assertJsonPath('data.report.versions.2.recipients.0.deliveryStatus', 'SENT')
            ->assertJsonCount(1, 'data.report.cmsTransfers')
            ->json('data.report');

        Sanctum::actingAs($auditee);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/reports/{$report['id']}/versions/{$report['versions'][2]['id']}/recipients/{$report['versions'][2]['recipients'][0]['id']}/decision",
            ['decision' => 'ACKNOWLEDGED', 'comment' => 'The office received the issued report.'],
        )->assertOk()->assertJsonPath('data.decision.decision', 'ACKNOWLEDGED');
        $this->assertDatabaseHas('audit_report_distribution_decisions', [
            'audit_report_id' => $report['id'],
            'report_recipient_id' => $report['versions'][2]['recipients'][0]['id'],
            'decision_code' => 'ACKNOWLEDGED',
        ]);

        $this->assertDatabaseCount('cms_recommendations', 1);
        $this->assertDatabaseCount('cms_recommendation_cases', 1);
        $this->assertDatabaseCount('cms_recommendation_events', 1);
        $this->assertDatabaseHas('audit_recommendations', [
            'id' => $recommendation->id,
            'status' => 'TRANSFERRED',
        ]);
        $intake = CmsRecommendation::query()->with('case.events')->firstOrFail();
        $this->assertSame('TRANSFERRED', $intake->status);
        $this->assertSame($engagement->id, $intake->audit_engagement_id);
        $this->assertSame($report['id'], $intake->audit_report_id);
        $this->assertSame($report['versions'][2]['id'], $intake->audit_report_version_id);
        $this->assertSame(
            $report['versions'][2]['checksumSha256'],
            $intake->report_checksum_sha256,
        );
        $this->assertSame('CONFIDENTIAL', $intake->confidentiality_code_snapshot);
        $this->assertSame('HIGH', $intake->risk_code_snapshot);
        $this->assertSame($auditee->office_id, $intake->lead_responsible_office_id);
        $this->assertSame(
            $recommendation->target_implementation_date->toDateString(),
            $intake->original_target_implementation_date->toDateString(),
        );
        $this->assertSame(1, $intake->source_schema_version);
        $this->assertSame(
            $recommendation->recommendation,
            $intake->source_snapshot['recommendation']['wording'],
        );
        $this->assertSame(
            $report['versions'][2]['checksumSha256'],
            $intake->source_snapshot['report']['checksumSha256'],
        );
        $this->assertSame('TRANSFERRED', $intake->case->status_code);
        $this->assertSame(1, $intake->case->lock_version);
        $this->assertSame(
            CmsRecommendationEvent::EVENT_INTAKE_CREATED,
            $intake->case->events->sole()->event_code,
        );
        $this->assertSame(
            1,
            ActivityLog::query()
                ->where('action', 'cms.recommendation.intake_created')
                ->count(),
        );
        $this->assertSame(
            1,
            AuditLog::query()
                ->where('action', 'cms.recommendation.intake_created')
                ->count(),
        );
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $auditee->id,
            'type' => 'AEMS_REPORT_ISSUED',
            'module_code' => 'AEMS',
        ]);
        $this->assertDatabaseCount('aems_report_authority_decisions', 2);
        $this->assertDatabaseCount('aems_report_signatories', 2);
        $this->assertDatabaseCount('aems_report_transmittals', 1);
        $this->assertNotEmpty($report['versions'][2]['sourceManifestSha256']);
        $this->assertNotEmpty($report['versions'][2]['reproducibilityKey']);
        Sanctum::actingAs($management);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/reports/{$report['id']}/cms-transfer",
            ['lockVersion' => $report['lockVersion']],
        )->assertOk()->assertJsonCount(1, 'data.transfers');
        $this->assertDatabaseCount('cms_recommendations', 1);
        $this->assertDatabaseCount('cms_recommendation_cases', 1);
        $this->assertDatabaseCount('cms_recommendation_events', 1);
        $this->assertSame(
            1,
            ActivityLog::query()
                ->where('action', 'cms.recommendation.intake_created')
                ->count(),
        );
        $this->assertSame(
            1,
            AuditLog::query()
                ->where('action', 'cms.recommendation.intake_created')
                ->count(),
        );

        Sanctum::actingAs($auditee);
        $this->get(
            "/api/aems/engagements/{$engagement->id}/reports/{$report['id']}/versions/{$report['versions'][2]['id']}/download",
            ['Accept' => 'application/pdf'],
        )->assertOk();
        $this->get(
            "/api/aems/engagements/{$engagement->id}/reports/{$report['id']}/versions/{$report['versions'][0]['id']}/download",
            ['Accept' => 'application/pdf'],
        )->assertForbidden();

        Sanctum::actingAs($management);
        $this->get(
            "/api/aems/engagements/{$engagement->id}/reports/{$report['id']}/versions/{$report['versions'][2]['id']}/exports/CSV",
            ['Accept' => 'text/csv'],
        )->assertOk();
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/reports/{$report['id']}/administrative-close",
            ['lockVersion' => $report['lockVersion'], 'reason' => 'Distribution and records reconciliation completed.', 'reference' => 'G7-REPORT-CLOSE-001'],
        )->assertOk()->assertJsonPath('data.report.status', 'ADMINISTRATIVELY_CLOSED');
        $this->assertDatabaseHas('audit_reports', [
            'id' => $report['id'],
            'status' => 'ADMINISTRATIVELY_CLOSED',
            'administrative_closure_reference' => 'G7-REPORT-CLOSE-001',
        ]);

        $this->expectException(LogicException::class);
        AuditReportVersion::query()
            ->findOrFail($report['versions'][2]['id'])
            ->update(['change_reason' => 'Attempted issued-version mutation.']);
    }

    public function test_final_approval_requires_completed_or_waived_exit_conference(): void
    {
        [$management, $auditor, $auditee, $engagement, $finding, $recommendation] =
            $this->reportContext();
        $internal = MasterList::query()->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->firstOrFail()->items()->where('code', 'INTERNAL')->firstOrFail();
        $finding->forceFill(['status' => 'FINALIZED'])->save();
        $recommendation->forceFill(['status' => 'FINALIZED'])->save();

        Sanctum::actingAs($auditor);
        $report = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/reports",
            $this->content($finding, $internal->id),
        )->assertCreated()->json('data.report');
        $report = $this->transition($engagement, $report, 'SUBMIT')->json('data.report');
        Sanctum::actingAs($management);
        $report = $this->transition($engagement, $report, 'APPROVE')->json('data.report');
        Sanctum::actingAs($auditor);
        $report = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/reports/{$report['id']}/final",
            $this->finalContent($finding, $internal->id, $auditee, $report['lockVersion']),
        )->assertOk()->json('data.report');
        $report = $this->transition($engagement, $report, 'SUBMIT')->json('data.report');
        Sanctum::actingAs($management);
        $this->transition($engagement, $report, 'APPROVE')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('report');
    }

    public function test_interim_report_preserves_quality_checklist_and_assembly_order(): void
    {
        [$management, $auditor, , $engagement, $finding] = $this->reportContext();
        $confidentiality = MasterList::query()->where('code', 'DOCUMENT_CONFIDENTIALITY')->firstOrFail()->items()->firstOrFail();

        Sanctum::actingAs($auditor);
        $response = $this->postJson("/api/aems/engagements/{$engagement->id}/reports/interim", [
            ...$this->content($finding, $confidentiality->id),
            'qualityChecklist' => [
                ['code' => 'FINDINGS', 'label' => 'Eligible findings reviewed', 'completed' => true],
                ['code' => 'QUALITY', 'label' => 'Quality review completed', 'completed' => false],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.report.reportStage', 'INTERIM_REPORT')
            ->assertJsonPath('data.report.versions.0.contentSnapshot.qualityChecklist.0.completed', true)
            ->assertJsonPath('data.report.versions.0.contentSnapshot.sections.0.sequenceNumber', 1);

        $report = $response->json('data.report');
        $this->assertSame('DRAFT', $report['status']);
        $this->assertSame(1, $report['currentVersionNumber']);
    }

    public function test_issued_report_amendment_creates_immutable_successor_version(): void
    {
        [$management, $auditor, $auditee, $engagement, $finding, $recommendation] = $this->reportContext();
        $internal = MasterList::query()->where('code', 'DOCUMENT_CONFIDENTIALITY')->firstOrFail()->items()->where('code', 'INTERNAL')->firstOrFail();
        $finding->forceFill(['status' => 'FINALIZED', 'finalized_at' => now(), 'finalized_by' => $management->id])->save();
        $recommendation->forceFill(['status' => 'FINALIZED', 'finalized_at' => now(), 'finalized_by' => $management->id])->save();
        ExitConference::query()->create(['audit_engagement_id' => $engagement->id, 'conference_code' => 'EXIT-RPT-SUCCESSOR', 'scheduled_start_at' => now()->subDay(), 'agenda' => 'Review report.', 'minutes' => 'Completed.', 'status' => 'COMPLETED', 'created_by' => $management->id, 'completed_at' => now(), 'completed_by' => $management->id]);

        Sanctum::actingAs($auditor);
        $report = $this->postJson("/api/aems/engagements/{$engagement->id}/reports", $this->content($finding, $internal->id))->json('data.report');
        $report = $this->transition($engagement, $report, 'SUBMIT')->json('data.report');
        Sanctum::actingAs($management);
        $report = $this->transition($engagement, $report, 'APPROVE')->json('data.report');
        Sanctum::actingAs($auditor);
        $final = $this->finalContent($finding, $internal->id, $auditee, $report['lockVersion']);
        $report = $this->postJson("/api/aems/engagements/{$engagement->id}/reports/{$report['id']}/final", $final)->json('data.report');
        $report = $this->transition($engagement, $report, 'SUBMIT')->json('data.report');
        Sanctum::actingAs($management);
        $report = $this->transition($engagement, $report, 'APPROVE')->json('data.report');
        $report = $this->transition($engagement, $report, 'ISSUE')->json('data.report');

        $successor = $this->postJson("/api/aems/engagements/{$engagement->id}/reports/{$report['id']}/successors", [
            'action' => 'AMEND', 'lockVersion' => $report['lockVersion'], 'reason' => 'Corrected an issued report presentation error.',
        ])->assertOk()->assertJsonPath('data.report.status', 'DRAFT')->json('data.report');
        $this->assertSame($report['id'], $successor['id']);
        $this->assertSame($report['currentVersionNumber'] + 1, $successor['currentVersionNumber']);
        $predecessor = collect($successor['versions'])->firstWhere('id', $report['currentVersionId']);
        $successorVersion = collect($successor['versions'])->last();
        $this->assertTrue($predecessor['isLocked']);
        $this->assertFalse($successorVersion['isLocked']);
        $this->assertSame($predecessor['id'], $successorVersion['contentSnapshot']['supersedesVersionId']);
        $this->assertDatabaseHas('audit_reports', ['id' => $report['id'], 'status' => 'DRAFT', 'is_active' => true]);
    }

    /** @return array{User, User, User, AuditEngagement, AuditFinding, AuditRecommendation} */
    private function reportContext(): array
    {
        $management = User::query()->where('username', 'departmenthead')->firstOrFail();
        $auditor = User::query()->where('username', 'auditor')->firstOrFail();
        $auditee = User::query()->where('username', 'auditee')->firstOrFail();
        $office = Office::query()->findOrFail($auditee->office_id);
        $risk = MasterList::query()->where('code', 'RISK_LEVEL')
            ->firstOrFail()->items()->where('code', 'HIGH')->firstOrFail();
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => 'AEMS-REPORT-001',
            'title' => 'Revenue Collection Audit',
            'source_type' => 'SPECIAL',
            'special_authority_reference' => 'AUTH-REPORT-001',
            'special_authority_date' => now()->subMonths(2)->toDateString(),
            'special_authority_approved_by' => $management->id,
            'objectives' => 'Assess revenue collection controls.',
            'scope' => 'Daily collection and reconciliation.',
            'status' => 'REPORTING',
            'created_by' => $management->id,
            'updated_by' => $management->id,
        ]);
        $engagement->offices()->attach($office->id, ['is_primary' => true]);
        EngagementTeam::query()->create([
            'audit_engagement_id' => $engagement->id,
            'user_id' => $auditor->id,
            'assignment_role_code' => 'TEAM_LEADER',
            'assigned_by' => $management->id,
            'is_active' => true,
        ]);
        $finding = AuditFinding::query()->create([
            'finding_family_uuid' => (string) Str::uuid(),
            'revision_number' => 1,
            'is_current_revision' => true,
            'audit_engagement_id' => $engagement->id,
            'finding_code' => 'FND-REPORT-001',
            'title' => 'Daily collections are not reconciled',
            'criteria' => 'Daily collections must be reconciled by a supervisor.',
            'condition' => 'Three collection batches lacked documented reconciliation.',
            'cause' => 'No supervisor is assigned to the reconciliation control.',
            'effect' => 'Collection posting errors may remain undetected.',
            'risk_rating_id' => $risk->id,
            'responsible_office_id' => $office->id,
            'status' => 'VALIDATED',
            'authored_by' => $auditor->id,
            'validated_at' => now(),
            'validated_by' => $management->id,
        ]);
        $recommendation = AuditRecommendation::query()->create([
            'audit_finding_id' => $finding->id,
            'recommendation_code' => 'REC-REPORT-001',
            'recommendation' => 'Require and document supervisory reconciliation for every daily batch.',
            'responsible_office_id' => $office->id,
            'target_implementation_date' => now()->addMonths(3)->toDateString(),
            'status' => 'DRAFT',
            'created_by' => $auditor->id,
            'updated_by' => $auditor->id,
        ]);

        return [$management, $auditor, $auditee, $engagement, $finding, $recommendation];
    }

    /** @return array<string, mixed> */
    private function content(AuditFinding $finding, int $confidentialityId): array
    {
        return [
            'title' => 'Revenue Collection Draft Audit Report',
            'executiveSummary' => 'The audit identified a reconciliation control weakness requiring management action.',
            'sections' => [
                [
                    'title' => 'Background and Scope',
                    'content' => 'The audit covered daily collection, posting, and reconciliation controls.',
                ],
                [
                    'title' => 'Overall Conclusion',
                    'content' => 'Supervisory reconciliation should be formalized and consistently documented.',
                ],
            ],
            'findingIds' => [$finding->id],
            'confidentialityLevelId' => $confidentialityId,
        ];
    }

    /** @return array<string, mixed> */
    private function finalContent(
        AuditFinding $finding,
        int $confidentialityId,
        User $auditee,
        int $lockVersion,
    ): array {
        return [
            ...$this->content($finding, $confidentialityId),
            'title' => 'Revenue Collection Final Audit Report',
            'approvingAuthority' => 'City Internal Auditor',
            'recipients' => [[
                'recipientType' => 'OFFICE',
                'officeId' => $auditee->office_id,
                'deliveryMethod' => 'SYSTEM',
            ]],
            'changeReason' => 'Converted the approved Draft Report using finalized findings.',
            'lockVersion' => $lockVersion,
        ];
    }

    private function transition(
        AuditEngagement $engagement,
        array $report,
        string $action,
        array $extra = [],
    ) {
        return $this->postJson(
            "/api/aems/engagements/{$engagement->id}/reports/{$report['id']}/transition",
            [
                'action' => $action,
                'lockVersion' => $report['lockVersion'],
                ...$extra,
            ],
        );
    }
}
