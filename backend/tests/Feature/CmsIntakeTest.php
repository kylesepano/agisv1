<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditLog;
use App\Models\AuditRecommendation;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Models\CmsRecommendation;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\Permission;
use App\Models\User;
use App\Services\Cms\CmsIntakeService;
use App\Support\CmsIntakeReferentialPreflight;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class CmsIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_hardened_intake_preserves_source_initializes_case_and_is_idempotent(): void
    {
        $context = $this->context('BASE');

        $first = $this->transfer($context);
        $second = $this->transfer($context);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->transfer_key, $second->transfer_key);
        $this->assertDatabaseCount('cms_recommendations', 1);
        $this->assertDatabaseCount('cms_recommendation_cases', 1);
        $this->assertDatabaseCount('cms_recommendation_events', 1);

        $intake = CmsRecommendation::query()
            ->with(['case.events', 'confidentialityLevel', 'riskRating'])
            ->firstOrFail();
        $case = $intake->case;
        $event = $case->events->sole();
        $recommendation = $context['recommendation']->fresh();

        $this->assertSame(CmsRecommendation::STATUS_TRANSFERRED, $intake->status);
        $this->assertSame($context['engagement']->id, $intake->audit_engagement_id);
        $this->assertSame($context['report']->id, $intake->audit_report_id);
        $this->assertSame($context['version']->id, $intake->audit_report_version_id);
        $this->assertSame($context['version']->checksum_sha256, $intake->report_checksum_sha256);
        $this->assertSame('CONFIDENTIAL', $intake->confidentiality_code_snapshot);
        $this->assertSame('Confidential', $intake->confidentiality_label_snapshot);
        $this->assertSame('HIGH', $intake->risk_code_snapshot);
        $this->assertSame('High', $intake->risk_label_snapshot);
        $this->assertSame($context['office']->id, $intake->lead_responsible_office_id);
        $this->assertSame(1, $intake->source_schema_version);
        $this->assertSame(
            $context['recommendation']->recommendation,
            $intake->source_snapshot['recommendation']['wording'],
        );
        $this->assertSame(
            $context['report']->report_code,
            $intake->source_snapshot['report']['code'],
        );
        $this->assertSame(
            $context['version']->checksum_sha256,
            $intake->source_snapshot['report']['checksumSha256'],
        );
        $this->assertSame(
            $context['office']->id,
            $intake->responsible_office_snapshot[0]['id'],
        );
        $this->assertSame(
            $context['recommendation']->target_implementation_date->toDateString(),
            $intake->original_target_implementation_date->toDateString(),
        );

        $this->assertSame(CmsRecommendationCase::STATUS_TRANSFERRED, $case->status_code);
        $this->assertSame(1, $case->lock_version);
        $this->assertSame(
            $intake->original_target_implementation_date->toDateString(),
            $case->effective_target_implementation_date->toDateString(),
        );
        $this->assertSame($intake->lead_responsible_office_id, $case->lead_responsible_office_id);
        $this->assertSame(CmsRecommendationEvent::EVENT_INTAKE_CREATED, $event->event_code);
        $this->assertSame("cms-intake:{$intake->id}", $event->idempotency_key);
        $this->assertSame($context['report']->id, $event->event_metadata['reportId']);
        $this->assertSame($intake->transfer_key, $event->event_metadata['transferKey']);

        $this->assertSame('TRANSFERRED', $recommendation->status);
        $this->assertSame($intake->id, $recommendation->cms_recommendation_id);
        $this->assertSame($intake->transfer_key, $recommendation->cms_transfer_key);
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
    }

    public function test_intake_and_initial_event_are_immutable_and_non_deletable(): void
    {
        $intake = $this->transfer($this->context('IMMUTABLE'));
        $event = $intake->case->events()->firstOrFail();

        $this->assertLogicException(
            fn () => $intake->update(['recommendation_code' => 'ALTERED']),
            'CMS intake records are immutable.',
        );
        $this->assertLogicException(
            fn () => $intake->update(['source_snapshot' => ['altered' => true]]),
            'CMS intake records are immutable.',
        );
        $this->assertLogicException(
            fn () => $intake->delete(),
            'CMS intake records cannot be deleted.',
        );
        $this->assertLogicException(
            fn () => $event->update(['new_status' => 'ALTERED']),
            'CMS recommendation events are append-only.',
        );
        $this->assertLogicException(
            fn () => $event->delete(),
            'CMS recommendation events cannot be deleted.',
        );
        $this->assertLogicException(
            fn () => $intake->case->delete(),
            'CMS recommendation cases cannot be deleted.',
        );
    }

    public function test_retry_rejects_an_existing_intake_with_conflicting_source_identity(): void
    {
        $context = $this->context('IDENTITY');
        $intake = $this->transfer($context);
        $other = $this->context('IDENTITY-OTHER');
        DB::table('cms_recommendations')
            ->where('id', $intake->id)
            ->update(['audit_report_id' => $other['report']->id]);

        $this->assertRejected($context, 'immutable source identity');
        $this->assertDatabaseCount('cms_recommendations', 1);
        $this->assertDatabaseCount('cms_recommendation_cases', 1);
        $this->assertDatabaseCount('cms_recommendation_events', 1);
    }

    public function test_intake_rejects_ineligible_or_mismatched_source_combinations(): void
    {
        $notFinal = $this->context('NOT-FINAL');
        DB::table('audit_reports')->where('id', $notFinal['report']->id)->update([
            'report_stage' => 'DRAFT_REPORT',
        ]);
        $notFinal['report'] = $notFinal['report']->fresh();
        $this->assertRejected($notFinal, 'issued Final Report');

        $notIssued = $this->context('NOT-ISSUED');
        DB::table('audit_reports')->where('id', $notIssued['report']->id)->update([
            'status' => 'APPROVED',
        ]);
        $notIssued['report'] = $notIssued['report']->fresh();
        $this->assertRejected($notIssued, 'issued Final Report');

        $unlocked = $this->context('UNLOCKED');
        DB::table('audit_report_versions')->where('id', $unlocked['version']->id)->update([
            'is_locked' => false,
            'locked_at' => null,
            'locked_by' => null,
        ]);
        $unlocked['version'] = $unlocked['version']->fresh();
        $this->assertRejected($unlocked, 'must be locked');

        $notIncluded = $this->context('NOT-INCLUDED');
        DB::table('audit_report_findings')
            ->where('audit_report_version_id', $notIncluded['version']->id)
            ->where('audit_finding_id', $notIncluded['finding']->id)
            ->delete();
        $this->assertRejected($notIncluded, 'not included');

        $draftFinding = $this->context('DRAFT-FINDING');
        DB::table('audit_findings')->where('id', $draftFinding['finding']->id)->update([
            'status' => 'DRAFT',
        ]);
        $this->assertRejected($draftFinding, 'current, finalized');

        $archived = $this->context('ARCHIVED');
        DB::table('audit_recommendations')->where('id', $archived['recommendation']->id)->update([
            'deleted_at' => now(),
        ]);
        $archived['recommendation'] = AuditRecommendation::query()
            ->withTrashed()
            ->findOrFail($archived['recommendation']->id);
        $this->assertRejected($archived, 'Archived recommendations');

        $excluded = $this->context('EXCLUDED');
        DB::table('audit_recommendations')->where('id', $excluded['recommendation']->id)->update([
            'status' => 'EXCLUDED',
            'cms_exclusion_reason' => 'Superseded by law.',
            'cms_exclusion_authority' => 'City Internal Auditor',
            'cms_excluded_by' => $excluded['actor']->id,
            'cms_excluded_at' => now(),
        ]);
        $excluded['recommendation'] = $excluded['recommendation']->fresh();
        $this->assertRejected($excluded, 'do not create CMS records');
        $this->assertDatabaseMissing('cms_recommendations', [
            'source_audit_recommendation_id' => $excluded['recommendation']->id,
        ]);

        $wrongEngagement = $this->context('WRONG-ENGAGEMENT');
        $other = $this->context('OTHER-ENGAGEMENT');
        $wrongEngagement['engagement'] = $other['engagement'];
        $this->assertRejected($wrongEngagement, 'does not belong');

        $wrongRecommendation = $this->context('WRONG-REC');
        $other = $this->context('OTHER-REC');
        $wrongRecommendation['recommendation'] = $other['recommendation'];
        $this->assertRejected($wrongRecommendation, 'not included');

        $snapshotConflict = $this->context('SNAPSHOT-CONFLICT');
        $otherOffice = Office::query()
            ->whereKeyNot($snapshotConflict['office']->id)
            ->firstOrFail();
        DB::table('audit_recommendations')
            ->where('id', $snapshotConflict['recommendation']->id)
            ->update(['responsible_office_id' => $otherOffice->id]);
        $snapshotConflict['recommendation'] = $snapshotConflict['recommendation']->fresh();
        $this->assertRejected($snapshotConflict, 'finalized recommendation snapshot');
    }

    public function test_multi_recommendation_intake_failure_rolls_back_issuance_and_history(): void
    {
        $context = $this->context('ROLLBACK', false);
        $invalidRecommendation = AuditRecommendation::query()->create([
            'audit_finding_id' => $context['finding']->id,
            'recommendation_code' => 'REC-ROLLBACK-INVALID',
            'recommendation' => 'This draft recommendation must fail the issue transaction.',
            'responsible_office_id' => $context['office']->id,
            'target_implementation_date' => now()->addMonths(5)->toDateString(),
            'status' => 'DRAFT',
            'created_by' => $context['actor']->id,
            'updated_by' => $context['actor']->id,
        ]);

        try {
            DB::transaction(function () use ($context, $invalidRecommendation): void {
                $version = AuditReportVersion::query()
                    ->lockForUpdate()
                    ->findOrFail($context['version']->id);
                $report = AuditReport::query()
                    ->lockForUpdate()
                    ->findOrFail($context['report']->id);
                $version->forceFill([
                    'is_locked' => true,
                    'locked_at' => now(),
                    'locked_by' => $context['actor']->id,
                ])->save();
                $report->forceFill([
                    'status' => 'ISSUED',
                    'issued_at' => now(),
                    'issued_by' => $context['actor']->id,
                ])->save();

                $service = app(CmsIntakeService::class);
                $service->intake(
                    $context['recommendation'],
                    $context['engagement'],
                    $report,
                    $version,
                    $context['request'],
                );
                $service->intake(
                    $invalidRecommendation,
                    $context['engagement'],
                    $report,
                    $version,
                    $context['request'],
                );
            });
            $this->fail('The invalid recommendation should roll back the issue transaction.');
        } catch (ValidationException) {
            // Expected domain rejection.
        }

        $this->assertSame('APPROVED', $context['report']->fresh()->status);
        $this->assertFalse($context['version']->fresh()->is_locked);
        $this->assertSame('FINALIZED', $context['recommendation']->fresh()->status);
        $this->assertNull($context['recommendation']->fresh()->cms_recommendation_id);
        $this->assertDatabaseCount('cms_recommendations', 0);
        $this->assertDatabaseCount('cms_recommendation_cases', 0);
        $this->assertDatabaseCount('cms_recommendation_events', 0);
        $this->assertSame(
            0,
            ActivityLog::query()->where('action', 'cms.recommendation.intake_created')->count(),
        );
        $this->assertSame(
            0,
            AuditLog::query()->where('action', 'cms.recommendation.intake_created')->count(),
        );
        $this->assertDatabaseMissing('notifications', ['type' => 'AEMS_REPORT_ISSUED']);
    }

    public function test_formal_aems_exclusion_remains_terminal_without_creating_cms_records(): void
    {
        $context = $this->context('FORMAL-EXCLUSION');
        Sanctum::actingAs($context['actor']);

        $this->postJson(
            "/api/aems/engagements/{$context['engagement']->id}"
            ."/recommendations/{$context['recommendation']->id}/cms-exclusion",
            [
                'reason' => 'The governing requirement was formally repealed before issuance.',
                'authority' => 'City Internal Auditor',
            ],
        )->assertOk()
            ->assertJsonPath('data.recommendation.status', 'EXCLUDED');

        $recommendation = $context['recommendation']->fresh();
        $this->assertSame('EXCLUDED', $recommendation->status);
        $this->assertNotNull($recommendation->cms_excluded_at);
        $this->assertDatabaseCount('cms_recommendations', 0);
        $this->assertDatabaseCount('cms_recommendation_cases', 0);
        $this->assertDatabaseCount('cms_recommendation_events', 0);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'aems.closure.cms_exclusion_authorized',
        ]);
    }

    public function test_foreign_keys_uniqueness_and_orphan_preflight_protect_lineage(): void
    {
        $context = $this->context('INTEGRITY');
        $intake = $this->transfer($context);

        try {
            DB::table('audit_recommendations')
                ->where('id', $context['recommendation']->id)
                ->update(['cms_recommendation_id' => $intake->id + 9999]);
            $this->fail('The AEMS CMS foreign key should reject an arbitrary intake ID.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        try {
            CmsRecommendationCase::query()->create([
                'cms_recommendation_id' => $intake->id,
                'status_code' => 'TRANSFERRED',
                'effective_target_implementation_date' => now()->toDateString(),
                'lead_responsible_office_id' => $context['office']->id,
                'opened_at' => now(),
                'created_by' => $context['actor']->id,
                'lock_version' => 1,
            ]);
            $this->fail('The one-case-per-intake constraint should reject duplicates.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        config(['database.connections.cms_preflight' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        $preflight = DB::connection('cms_preflight');
        $preflight->statement(
            'CREATE TABLE cms_recommendations (id INTEGER PRIMARY KEY)',
        );
        $preflight->statement(
            'CREATE TABLE audit_recommendations (
                id INTEGER PRIMARY KEY,
                recommendation_code VARCHAR(80) NOT NULL,
                cms_recommendation_id INTEGER NULL
            )',
        );
        $preflight->table('audit_recommendations')->insert([
            'id' => $context['recommendation']->id,
            'recommendation_code' => $context['recommendation']->recommendation_code,
            'cms_recommendation_id' => $intake->id + 9999,
        ]);
        try {
            CmsIntakeReferentialPreflight::assertNoOrphanedRecommendationPointers($preflight);
            $this->fail('The preflight should detect an orphaned AEMS CMS pointer.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                $context['recommendation']->recommendation_code,
                $exception->getMessage(),
            );
            $this->assertStringContainsString('approved migration', $exception->getMessage());
        } finally {
            DB::purge('cms_preflight');
        }

        CmsIntakeReferentialPreflight::assertNoOrphanedRecommendationPointers();
        $this->assertSame($intake->id, $context['recommendation']->fresh()->cms_recommendation_id);
    }

    public function test_cms_one_preserves_the_six_legacy_compatibility_permissions(): void
    {
        $legacy = [
            'cms.approve_extension',
            'cms.close',
            'cms.submit_evidence',
            'cms.update',
            'cms.validate',
            'cms.view',
        ];
        $actual = Permission::query()
            ->whereIn('code', $legacy)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        $this->assertSame($legacy, $actual);
    }

    /**
     * @return array{
     *   actor: User,
     *   office: Office,
     *   engagement: AuditEngagement,
     *   finding: AuditFinding,
     *   recommendation: AuditRecommendation,
     *   report: AuditReport,
     *   version: AuditReportVersion,
     *   request: Request
     * }
     */
    private function context(string $suffix, bool $issued = true): array
    {
        $suffix = Str::upper(Str::slug($suffix, '-'));
        $actor = User::query()->where('username', 'departmenthead')->firstOrFail();
        $auditor = User::query()->where('username', 'auditor')->firstOrFail();
        $office = Office::query()->findOrFail(
            User::query()->where('username', 'auditee')->firstOrFail()->office_id,
        );
        $confidentiality = MasterList::query()
            ->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->firstOrFail()->items()->where('code', 'CONFIDENTIAL')->firstOrFail();
        $risk = MasterList::query()
            ->where('code', 'RISK_LEVEL')
            ->firstOrFail()->items()->where('code', 'HIGH')->firstOrFail();
        $documentType = MasterList::query()
            ->where('code', 'DOCUMENT_TYPE')
            ->firstOrFail()->items()->where('code', 'OTHER')->firstOrFail();
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => "CMS1-{$suffix}",
            'title' => "CMS-1 Intake {$suffix}",
            'source_type' => 'SPECIAL',
            'special_authority_reference' => "AUTH-{$suffix}",
            'special_authority_date' => now()->subMonth()->toDateString(),
            'special_authority_approved_by' => $actor->id,
            'objectives' => 'Verify hardened CMS intake.',
            'scope' => 'Final report recommendation transfer.',
            'status' => 'REPORTING',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $engagement->offices()->attach($office->id, ['is_primary' => true]);
        $finding = AuditFinding::query()->create([
            'finding_family_uuid' => (string) Str::uuid(),
            'revision_number' => 1,
            'is_current_revision' => true,
            'audit_engagement_id' => $engagement->id,
            'finding_code' => "FND-{$suffix}",
            'title' => "CMS-1 finding {$suffix}",
            'criteria' => 'A controlled recommendation must be followed up.',
            'condition' => 'The required control was not consistently performed.',
            'cause' => 'The process lacks documented ownership.',
            'effect' => 'The control objective may not be achieved.',
            'risk_rating_id' => $risk->id,
            'responsible_office_id' => $office->id,
            'status' => 'FINALIZED',
            'authored_by' => $auditor->id,
            'validated_at' => now()->subDays(3),
            'validated_by' => $actor->id,
            'finalized_at' => now()->subDays(2),
            'finalized_by' => $actor->id,
            'finalized_snapshot' => ['finding' => ['title' => "CMS-1 finding {$suffix}"]],
        ]);
        $recommendation = AuditRecommendation::query()->create([
            'audit_finding_id' => $finding->id,
            'recommendation_code' => "REC-{$suffix}",
            'recommendation' => "Implement the controlled corrective action for {$suffix}.",
            'responsible_office_id' => $office->id,
            'target_implementation_date' => now()->addMonths(4)->toDateString(),
            'status' => 'DRAFT',
            'created_by' => $auditor->id,
            'updated_by' => $auditor->id,
        ]);
        $recommendation->update([
            'status' => 'FINALIZED',
            'finalized_at' => now()->subDays(2),
            'finalized_by' => $actor->id,
            'finalized_snapshot' => [
                'id' => $recommendation->id,
                'recommendationCode' => "REC-{$suffix}",
                'recommendation' => "Implement the controlled corrective action for {$suffix}.",
                'responsibleOfficeId' => $office->id,
                'targetImplementationDate' => $recommendation->target_implementation_date->toDateString(),
            ],
        ]);

        $checksum = hash('sha256', "cms-intake-{$suffix}");
        $document = Document::query()->create([
            'document_code' => "DOC-CMS1-{$suffix}",
            'document_type_id' => $documentType->id,
            'confidentiality_level_id' => $confidentiality->id,
            'title' => "CMS-1 Final Report {$suffix}",
            'version' => '1',
            'description' => 'CMS-1 test report.',
            'owner_module' => 'AEMS',
            'library_visible' => false,
            'original_file_name' => "cms1-{$suffix}.pdf",
            'storage_path' => "tests/cms1-{$suffix}.pdf",
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 100,
            'checksum_sha256' => $checksum,
            'uploaded_by' => $auditor->id,
            'updated_by' => $auditor->id,
            'is_active' => true,
        ]);
        $documentVersion = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'version_label' => '1',
            'change_summary' => 'Issued final report.',
            'original_file_name' => "cms1-{$suffix}.pdf",
            'storage_path' => "tests/cms1-{$suffix}-v1.pdf",
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 100,
            'checksum_sha256' => $checksum,
            'uploaded_by' => $auditor->id,
        ]);
        $document->forceFill(['current_version_id' => $documentVersion->id])->save();
        $report = AuditReport::query()->create([
            'audit_engagement_id' => $engagement->id,
            'report_code' => "AR-{$suffix}",
            'title' => "Final Audit Report {$suffix}",
            'report_stage' => 'FINAL_REPORT',
            'status' => 'APPROVED',
            'current_version_number' => 1,
            'confidentiality_level_id' => $confidentiality->id,
            'document_id' => $document->id,
            'prepared_by' => $auditor->id,
            'approved_at' => now()->subDay(),
            'approved_by' => $actor->id,
            'approving_authority' => 'City Internal Auditor',
            'lock_version' => 1,
            'is_active' => true,
        ]);
        $version = AuditReportVersion::query()->create([
            'audit_report_id' => $report->id,
            'version_number' => 1,
            'report_stage' => 'FINAL_REPORT',
            'content_snapshot' => [
                'findingIds' => [$finding->id],
                'recommendationIds' => [$recommendation->id],
            ],
            'document_version_id' => $documentVersion->id,
            'checksum_sha256' => $checksum,
            'pdf_file_name' => "cms1-{$suffix}.pdf",
            'file_size' => 100,
            'is_locked' => false,
            'change_reason' => 'Initial final report.',
            'created_by' => $auditor->id,
        ]);
        $version->findings()->attach($finding->id, [
            'sequence_number' => 1,
            'is_included' => true,
        ]);
        $report->forceFill(['current_version_id' => $version->id])->save();
        if ($issued) {
            $version->forceFill([
                'is_locked' => true,
                'locked_at' => now(),
                'locked_by' => $actor->id,
            ])->save();
            $report->forceFill([
                'status' => 'ISSUED',
                'issued_at' => now(),
                'issued_by' => $actor->id,
            ])->save();
        }

        $request = Request::create('/api/aems/cms-intake-test', 'POST');
        $request->setUserResolver(fn (): User => $actor);

        return [
            'actor' => $actor,
            'office' => $office,
            'engagement' => $engagement->fresh(),
            'finding' => $finding->fresh(),
            'recommendation' => $recommendation->fresh(),
            'report' => $report->fresh(),
            'version' => $version->fresh(),
            'request' => $request,
        ];
    }

    /** @param array<string, mixed> $context */
    private function transfer(array $context): CmsRecommendation
    {
        return DB::transaction(
            fn (): CmsRecommendation => app(CmsIntakeService::class)->intake(
                $context['recommendation'],
                $context['engagement'],
                $context['report'],
                $context['version'],
                $context['request'],
            ),
        );
    }

    /** @param array<string, mixed> $context */
    private function assertRejected(array $context, string $messageFragment): void
    {
        try {
            $this->transfer($context);
            $this->fail('The CMS intake source should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                $messageFragment,
                implode(' ', $exception->errors()['cmsTransfer'] ?? []),
            );
        }
    }

    private function assertLogicException(
        callable $action,
        string $message,
    ): void {
        try {
            $action();
            $this->fail('The immutable record operation should have failed.');
        } catch (LogicException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }
}
