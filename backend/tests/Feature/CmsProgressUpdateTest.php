<?php

namespace Tests\Feature;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditRecommendation;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Models\CmsActionPlanVersion;
use App\Models\CmsCorrectiveActionPlan;
use App\Models\CmsProgressEvidenceLink;
use App\Models\CmsProgressUpdate;
use App\Models\CmsProgressUpdateVersion;
use App\Models\CmsRecommendation;
use App\Models\CmsRecommendationAssignment;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\Permission;
use App\Models\SystemNotification;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CmsProgressUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
        Storage::fake('local');
    }

    public function test_schema_permissions_and_role_separation_are_governed(): void
    {
        foreach ([
            'cms_progress_updates',
            'cms_progress_update_versions',
            'cms_milestone_progress',
            'cms_progress_evidence_links',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertSame(
            8,
            Permission::query()->where('code', 'like', 'cms.progress.%')->count(),
        );
        $this->assertSame(
            4,
            Permission::query()->where('code', 'like', 'cms.evidence.%')->count(),
        );

        $management = $this->user('departmenthead');
        $monitor = $this->user('auditor');
        $auditee = $this->user('auditee');
        $platform = $this->user('admin');
        $administrator = $this->user('agisadmin');
        $readOnly = $this->user('mayor');

        $this->assertTrue($management->hasPermission('cms.progress.record'));
        $this->assertTrue($monitor->hasPermission('cms.progress.review'));
        $this->assertTrue($auditee->hasPermission('cms.progress.create'));
        $this->assertTrue($auditee->hasPermission('cms.evidence.upload'));
        $this->assertTrue($readOnly->hasPermission('cms.progress.view'));
        $this->assertFalse($platform->hasPermission('cms.progress.record'));
        $this->assertFalse($administrator->hasPermission('cms.progress.record'));

        [$case, $plan, $accepted] = $this->monitoringCase('SCHEMA', $auditee->office);
        $first = CmsProgressUpdate::query()->create([
            'cms_recommendation_case_id' => $case->id,
            'cms_corrective_action_plan_id' => $plan->id,
            'accepted_action_plan_version_id' => $accepted->id,
            'reporting_sequence' => 1,
            'reporting_period_start' => today(),
            'reporting_period_end' => today()->addDays(5),
            'created_by' => $auditee->id,
        ]);
        CmsProgressUpdateVersion::query()->create([
            'cms_progress_update_id' => $first->id,
            'version_number' => 1,
            'status_code' => 'DRAFT',
            'active_slot' => 'ACTIVE',
            'prepared_by' => $auditee->id,
        ]);

        $this->expectException(QueryException::class);
        CmsProgressUpdateVersion::query()->create([
            'cms_progress_update_id' => $first->id,
            'version_number' => 2,
            'status_code' => 'DRAFT',
            'active_slot' => 'ACTIVE',
            'prepared_by' => $auditee->id,
        ]);
    }

    public function test_responsible_office_creates_exact_baseline_and_period_rules_apply(): void
    {
        $auditee = $this->user('auditee');
        [$case, , $accepted] = $this->monitoringCase('CREATE', $auditee->office);
        Sanctum::actingAs($auditee);

        $response = $this->postJson(
            "/api/cms/recommendations/{$case->id}/progress-updates",
            $this->payload($case, $accepted),
        )->assertCreated()
            ->assertJsonPath(
                'data.progressUpdate.acceptedActionPlanVersionId',
                $accepted->id,
            )
            ->assertJsonPath('data.progressUpdate.currentVersion.status', 'DRAFT')
            ->assertJsonPath(
                'data.progressUpdate.currentVersion.baselineWeighted',
                true,
            )
            ->assertJsonPath(
                'data.progressUpdate.currentVersion.systemCalculatedWeightedReportedPercentage',
                '50.00',
            )
            ->assertJsonPath('data.progressUpdate.notIndependentlyValidated', true);

        $this->assertSame(
            $accepted->milestones()->count(),
            count($response->json('data.progressUpdate.currentVersion.milestoneProgress')),
        );
        $this->assertSame('MONITORING', $case->fresh()->status_code);

        $duplicate = $this->payload($case->fresh(), $accepted);
        $duplicate['lockVersion'] = $case->fresh()->lock_version;
        $this->postJson(
            "/api/cms/recommendations/{$case->id}/progress-updates",
            $duplicate,
        )->assertUnprocessable()
            ->assertJsonValidationErrors('reportingPeriodEnd');

        $notMonitoring = $this->monitoringCase('NOTMON', $auditee->office)[0];
        $notMonitoring->forceFill(['status_code' => 'FOR_ACTION_PLAN'])->save();
        $payload = $this->payload(
            $notMonitoring,
            $notMonitoring->actionPlan->acceptedVersion,
        );
        $this->postJson(
            "/api/cms/recommendations/{$notMonitoring->id}/progress-updates",
            $payload,
        )->assertUnprocessable()
            ->assertJsonValidationErrors('recommendation');

        Sanctum::actingAs($this->user('auditor'));
        $this->postJson(
            "/api/cms/recommendations/{$case->id}/progress-updates",
            $duplicate,
        )->assertForbidden();
    }

    public function test_draft_calculation_status_consistency_and_stale_lock_are_enforced(): void
    {
        $auditee = $this->user('auditee');
        [$case, , $accepted] = $this->monitoringCase('EDIT', $auditee->office);
        $update = $this->createUpdate($case, $accepted, $auditee);
        $version = $update->currentVersion;
        Sanctum::actingAs($auditee);

        $payload = $this->payload($case, $accepted);
        unset($payload['reportingPeriodStart'], $payload['reportingPeriodEnd']);
        $payload['lockVersion'] = $version->lock_version;
        $payload['milestoneProgress'][0]['managementReportedPercentage'] = 25;
        $payload['milestoneProgress'][1]['managementReportedPercentage'] = 75;
        $this->putJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}",
            $payload,
        )->assertOk()
            ->assertJsonPath(
                'data.progressUpdate.currentVersion.systemCalculatedWeightedReportedPercentage',
                '55.00',
            );

        $fresh = $version->fresh();
        $invalid = $payload;
        $invalid['lockVersion'] = $fresh->lock_version;
        $invalid['managementReportedOverallPercentage'] = 99;
        $this->putJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}",
            $invalid,
        )->assertUnprocessable()
            ->assertJsonValidationErrors('managementReportedOverallPercentage');

        $invalid = $payload;
        $invalid['lockVersion'] = $fresh->lock_version;
        $invalid['milestoneProgress'][0]['managementReportedStatusCode'] = 'NOT_STARTED';
        $invalid['milestoneProgress'][0]['managementReportedPercentage'] = 10;
        $this->putJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}",
            $invalid,
        )->assertUnprocessable()
            ->assertJsonValidationErrors(
                'milestoneProgress.0.managementReportedPercentage',
            );

        $this->putJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}",
            ['lockVersion' => 1, 'accomplishmentSummary' => 'Stale overwrite.'],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('lockVersion');
    }

    public function test_evidence_pins_core_version_downloads_safely_and_draft_removal_retains_file(): void
    {
        $auditee = $this->user('auditee');
        [$case, , $accepted] = $this->monitoringCase('EVIDENCE', $auditee->office);
        $update = $this->createUpdate($case, $accepted, $auditee);
        $version = $update->currentVersion;
        $milestoneProgress = $version->milestoneProgress()->firstOrFail();
        $internal = MasterList::query()
            ->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->firstOrFail()
            ->items()
            ->where('code', 'INTERNAL')
            ->firstOrFail();
        Sanctum::actingAs($auditee);

        $upload = $this->post(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}/evidence",
            [
                'lockVersion' => $version->lock_version,
                'milestoneProgressId' => $milestoneProgress->id,
                'evidenceCategory' => 'ACCOMPLISHMENT',
                'title' => 'Approved corrective procedure',
                'description' => 'Management supporting record.',
                'sourceOrCustodian' => 'Responsible Office Records Unit',
                'confidentialityLevelId' => $internal->id,
                'file' => UploadedFile::fake()->createWithContent(
                    'procedure.pdf',
                    "%PDF-1.7\nCMS progress evidence.",
                ),
            ],
            ['Accept' => 'application/json'],
        )->assertCreated()
            ->assertJsonMissingPath('data.evidence.storagePath')
            ->assertJsonPath('data.evidence.documentVersionId', fn ($id): bool => $id > 0);

        $evidence = CmsProgressEvidenceLink::query()->firstOrFail();
        $document = Document::query()->findOrFail($evidence->document_id);
        $documentVersion = DocumentVersion::query()->findOrFail(
            $evidence->document_version_id,
        );
        $this->assertSame($documentVersion->checksum_sha256, $evidence->checksum_sha256);
        $this->assertSame($documentVersion->id, $document->current_version_id);
        Storage::disk('local')->assertExists($documentVersion->storage_path);

        $this->get("/api/cms/progress-evidence/{$evidence->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->deleteJson("/api/cms/progress-evidence/{$evidence->id}", [
            'lockVersion' => $version->fresh()->lock_version,
            'removalReason' => 'Incorrect supporting record selected.',
        ])->assertOk();

        $this->assertNotNull($evidence->fresh()->removed_at);
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('document_versions', ['id' => $documentVersion->id]);
        Storage::disk('local')->assertExists($documentVersion->storage_path);
        $this->get("/api/cms/progress-evidence/{$evidence->id}/download")
            ->assertNotFound();
    }

    public function test_submission_snapshots_exact_baseline_and_makes_content_immutable(): void
    {
        $auditee = $this->user('auditee');
        [$case, , $accepted] = $this->monitoringCase('SUBMIT', $auditee->office);
        $update = $this->createUpdate($case, $accepted, $auditee);
        $version = $update->currentVersion;
        Sanctum::actingAs($auditee);

        $this->postJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}/transitions/submit",
            ['lockVersion' => $version->lock_version, 'confirmation' => true],
        )->assertOk()
            ->assertJsonPath('data.progressUpdate.currentVersion.status', 'SUBMITTED')
            ->assertJsonPath(
                'data.progressUpdate.currentVersion.hasSubmissionSnapshot',
                true,
            );

        $submitted = $version->fresh();
        $this->assertSame(
            $accepted->id,
            data_get($submitted->submission_snapshot, 'acceptedActionPlanVersionId'),
        );
        $this->assertCount(
            2,
            data_get($submitted->submission_snapshot, 'milestoneProgress'),
        );
        $this->putJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}",
            [
                'lockVersion' => $submitted->lock_version,
                'accomplishmentSummary' => 'Attempted rewrite.',
            ],
        )->assertUnprocessable();
        $this->assertDatabaseHas('cms_recommendation_events', [
            'event_code' => 'PROGRESS_UPDATE_SUBMITTED',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'cms.progress.submitted']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cms.progress.submitted']);
        $this->assertGreaterThan(
            0,
            SystemNotification::query()->where('module_code', 'CMS')->count(),
        );
    }

    public function test_independent_review_return_and_revision_preserve_history(): void
    {
        $auditee = $this->user('auditee');
        $reviewer = $this->user('auditor');
        [$case, , $accepted] = $this->monitoringCase('RETURN', $auditee->office);
        $this->assignMonitor($case, $reviewer);
        $update = $this->createUpdate($case, $accepted, $auditee);
        $version = $this->submit($update, $auditee);

        Sanctum::actingAs($reviewer);
        $this->postJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}/transitions/start-review",
            ['lockVersion' => $version->lock_version, 'reviewComment' => 'Review started.'],
        )->assertOk()
            ->assertJsonPath('data.progressUpdate.currentVersion.status', 'UNDER_REVIEW');
        $reviewing = $version->fresh();
        $this->postJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}/transitions/return",
            ['lockVersion' => $reviewing->lock_version],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('returnReason');
        $this->postJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}/transitions/return",
            [
                'lockVersion' => $reviewing->lock_version,
                'returnReason' => 'Clarify the reported accomplishment.',
            ],
        )->assertOk();

        Sanctum::actingAs($auditee);
        $returned = $version->fresh();
        $response = $this->postJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}/revisions",
            [
                'lockVersion' => $returned->lock_version,
                'revisionReason' => 'Address completeness review instructions.',
            ],
        )->assertCreated()
            ->assertJsonPath('data.progressUpdate.currentVersion.versionNumber', 2)
            ->assertJsonPath('data.progressUpdate.currentVersion.status', 'DRAFT');

        $this->assertSame('RETURNED', $returned->fresh()->status_code);
        $this->assertSame(
            $version->milestoneProgress()->count(),
            count($response->json('data.progressUpdate.currentVersion.milestoneProgress')),
        );
    }

    public function test_recording_is_unvalidated_keeps_monitoring_and_revision_preserves_baseline(): void
    {
        $auditee = $this->user('auditee');
        $reviewer = $this->user('auditor');
        [$case, , $accepted] = $this->monitoringCase('RECORD', $auditee->office);
        $this->assignMonitor($case, $reviewer);
        $update = $this->createUpdate($case, $accepted, $auditee);
        $version = $this->submit($update, $auditee);
        Sanctum::actingAs($reviewer);
        $this->postJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}/transitions/start-review",
            ['lockVersion' => $version->lock_version],
        )->assertOk();
        $reviewing = $version->fresh();
        $this->postJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}/transitions/record",
            [
                'lockVersion' => $reviewing->lock_version,
                'recordingComment' => 'Complete enough for follow-up monitoring.',
                'confirmation' => true,
            ],
        )->assertOk()
            ->assertJsonPath('data.progressUpdate.currentVersion.status', 'RECORDED')
            ->assertJsonPath(
                'data.progressUpdate.currentVersion.notIndependentlyValidated',
                true,
            );

        $recorded = $version->fresh();
        $this->assertSame('MONITORING', $case->fresh()->status_code);
        $this->assertSame($recorded->id, $update->fresh()->recorded_version_id);
        $this->assertDatabaseMissing('cms_recommendation_cases', [
            'id' => $case->id,
            'status_code' => 'IMPLEMENTED',
        ]);

        Sanctum::actingAs($auditee);
        $revision = $this->postJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}/revisions",
            [
                'lockVersion' => $recorded->lock_version,
                'revisionReason' => 'Correct management-reported narrative.',
            ],
        )->assertCreated();
        $this->assertSame($recorded->id, $update->fresh()->recorded_version_id);
        $this->assertTrue(
            $revision->json('data.progressUpdate.recordedVersion.isRecordedCurrent'),
        );
        $this->assertSame('MONITORING', $case->fresh()->status_code);
    }

    public function test_scope_safe_404_and_backward_compatible_summaries_do_not_validate_progress(): void
    {
        $auditee = $this->user('auditee');
        [$case, , $accepted] = $this->monitoringCase('SCOPE', $auditee->office);
        $update = $this->createUpdate($case, $accepted, $auditee);

        $other = User::query()
            ->where('office_id', '!=', $auditee->office_id)
            ->whereHas(
                'role',
                fn ($role) => $role->where('code', 'auditee_representative'),
            )
            ->firstOrFail();
        Sanctum::actingAs($other);
        $this->getJson("/api/cms/progress-updates/{$update->id}")->assertNotFound();

        Sanctum::actingAs($auditee);
        $this->getJson("/api/cms/recommendations/{$case->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.recommendation.progressUpdateSummary.hasProgressUpdates',
                true,
            )
            ->assertJsonPath(
                'data.recommendation.progressUpdateSummary.notIndependentlyValidated',
                true,
            )
            ->assertJsonPath(
                'data.recommendation.actionPlanSummary.hasActionPlan',
                true,
            );
        Sanctum::actingAs($this->user('departmenthead'));
        $this->getJson('/api/cms/dashboard')
            ->assertOk()
            ->assertJsonPath(
                'data.cards.progressUpdatesAwaitingReview',
                0,
            )
            ->assertJsonPath(
                'data.dataLimitations.2',
                'Automation identifies readiness, sends reminders, and prepares candidates; professional decisions and formal notices remain manual.',
            );
    }

    public function test_unweighted_one_hundred_percent_remains_management_reported_only(): void
    {
        $auditee = $this->user('auditee');
        [$case, , $accepted] = $this->monitoringCase(
            'UNWEIGHTED',
            $auditee->office,
            false,
        );
        $payload = $this->payload($case, $accepted);
        $payload['managementReportedOverallPercentage'] = 100;
        Sanctum::actingAs($auditee);
        $this->postJson(
            "/api/cms/recommendations/{$case->id}/progress-updates",
            $payload,
        )->assertCreated()
            ->assertJsonPath(
                'data.progressUpdate.currentVersion.baselineWeighted',
                false,
            )
            ->assertJsonPath(
                'data.progressUpdate.currentVersion.systemCalculatedWeightedReportedPercentage',
                null,
            )
            ->assertJsonPath(
                'data.progressUpdate.currentVersion.managementReportsComplete',
                true,
            )
            ->assertJsonPath(
                'data.progressUpdate.currentVersion.reportedCompleteAwaitingValidation',
                true,
            )
            ->assertJsonPath(
                'data.progressUpdate.currentVersion.notIndependentlyValidated',
                true,
            );

        $this->assertSame('MONITORING', $case->fresh()->status_code);
        $this->assertFalse(Schema::hasColumn(
            'cms_recommendation_cases',
            'implementation_status',
        ));
    }

    public function test_baseline_change_blocks_old_submission_and_new_family_pins_new_acceptance(): void
    {
        $auditee = $this->user('auditee');
        [$case, $plan, $accepted] = $this->monitoringCase('REBASE', $auditee->office);
        $update = $this->createUpdate($case, $accepted, $auditee);
        $replacement = CmsActionPlanVersion::query()->create([
            ...collect($accepted->getAttributes())->except([
                'id',
                'version_number',
                'previous_version_id',
                'created_at',
                'updated_at',
            ])->all(),
            'version_number' => 2,
            'previous_version_id' => $accepted->id,
            'accepted_at' => now(),
            'lock_version' => 1,
        ]);
        foreach ($accepted->milestones as $milestone) {
            $replacement->milestones()->create(
                collect($milestone->getAttributes())
                    ->except([
                        'id',
                        'cms_action_plan_version_id',
                        'created_at',
                        'updated_at',
                    ])->all(),
            );
        }
        $plan->forceFill([
            'current_version_id' => $replacement->id,
            'accepted_version_id' => $replacement->id,
            'lock_version' => $plan->lock_version + 1,
        ])->save();

        Sanctum::actingAs($auditee);
        $version = $update->currentVersion;
        $this->postJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}/transitions/submit",
            ['lockVersion' => $version->lock_version, 'confirmation' => true],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('actionPlan');

        $newPayload = $this->payload($case->fresh(), $replacement->fresh('milestones'));
        $newPayload['reportingPeriodStart'] = today()->addDays(7)->toDateString();
        $newPayload['reportingPeriodEnd'] = today()->addDays(13)->toDateString();
        $this->postJson(
            "/api/cms/recommendations/{$case->id}/progress-updates",
            $newPayload,
        )->assertCreated()
            ->assertJsonPath(
                'data.progressUpdate.acceptedActionPlanVersionId',
                $replacement->id,
            );
        $this->assertSame(
            $accepted->id,
            $update->fresh()->accepted_action_plan_version_id,
        );
    }

    public function test_submitted_evidence_is_immutable_and_recorded_revision_copies_exact_document_version(): void
    {
        $auditee = $this->user('auditee');
        $reviewer = $this->user('auditor');
        [$case, , $accepted] = $this->monitoringCase('EVCOPY', $auditee->office);
        $this->assignMonitor($case, $reviewer);
        $update = $this->createUpdate($case, $accepted, $auditee);
        $version = $update->currentVersion;
        $internal = MasterList::query()
            ->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->firstOrFail()
            ->items()
            ->where('code', 'INTERNAL')
            ->firstOrFail();
        Sanctum::actingAs($auditee);
        $this->post(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}/evidence",
            [
                'lockVersion' => $version->lock_version,
                'evidenceCategory' => 'GENERAL',
                'title' => 'Management progress record',
                'confidentialityLevelId' => $internal->id,
                'file' => UploadedFile::fake()->createWithContent(
                    'progress.pdf',
                    "%PDF-1.7\nPinned CMS evidence.",
                ),
            ],
            ['Accept' => 'application/json'],
        )->assertCreated();
        $evidence = CmsProgressEvidenceLink::query()->firstOrFail();
        $originalDocumentVersion = $evidence->documentVersion;
        $version = $this->submit($update->fresh(), $auditee);
        $this->deleteJson("/api/cms/progress-evidence/{$evidence->id}", [
            'lockVersion' => $version->lock_version,
            'removalReason' => 'Attempt after submission.',
        ])->assertUnprocessable();

        Sanctum::actingAs($reviewer);
        $this->postJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}/transitions/start-review",
            ['lockVersion' => $version->lock_version],
        )->assertOk();
        $reviewing = $version->fresh();
        $this->postJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}/transitions/record",
            [
                'lockVersion' => $reviewing->lock_version,
                'recordingComment' => 'Recorded for monitoring only.',
                'confirmation' => true,
            ],
        )->assertOk();
        $recorded = $version->fresh();

        $document = $originalDocumentVersion->document;
        $newDocumentVersion = $document->versions()->create([
            'version_number' => 2,
            'version_label' => 'Unrelated later version',
            'change_summary' => 'Created after the recorded update.',
            'original_file_name' => 'later.pdf',
            'storage_path' => "tests/later-{$document->id}.pdf",
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 10,
            'checksum_sha256' => hash('sha256', 'later'),
            'uploaded_by' => $auditee->id,
        ]);
        $document->forceFill(['current_version_id' => $newDocumentVersion->id])->save();

        Sanctum::actingAs($auditee);
        $revision = $this->postJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}/revisions",
            [
                'lockVersion' => $recorded->lock_version,
                'revisionReason' => 'Correct the recorded management narrative.',
            ],
        )->assertCreated();
        $revisionId = $revision->json('data.progressUpdate.currentVersion.id');
        $this->assertDatabaseHas('cms_progress_evidence_links', [
            'cms_progress_update_version_id' => $revisionId,
            'document_version_id' => $originalDocumentVersion->id,
            'checksum_sha256' => $originalDocumentVersion->checksum_sha256,
        ]);
        $this->assertDatabaseMissing('cms_progress_evidence_links', [
            'cms_progress_update_version_id' => $revisionId,
            'document_version_id' => $newDocumentVersion->id,
        ]);
    }

    private function createUpdate(
        CmsRecommendationCase $case,
        CmsActionPlanVersion $accepted,
        User $auditee,
    ): CmsProgressUpdate {
        Sanctum::actingAs($auditee);
        $this->postJson(
            "/api/cms/recommendations/{$case->id}/progress-updates",
            $this->payload($case, $accepted),
        )->assertCreated();

        return CmsProgressUpdate::query()
            ->with(['currentVersion.milestoneProgress'])
            ->where('cms_recommendation_case_id', $case->id)
            ->firstOrFail();
    }

    private function submit(CmsProgressUpdate $update, User $auditee): CmsProgressUpdateVersion
    {
        Sanctum::actingAs($auditee);
        $version = $update->currentVersion()->firstOrFail();
        $this->postJson(
            "/api/cms/progress-updates/{$update->id}/versions/{$version->id}/transitions/submit",
            ['lockVersion' => $version->lock_version, 'confirmation' => true],
        )->assertOk();

        return $version->fresh();
    }

    /** @return array<string, mixed> */
    private function payload(
        CmsRecommendationCase $case,
        CmsActionPlanVersion $accepted,
    ): array {
        $accepted->loadMissing('milestones');

        return [
            'lockVersion' => $case->lock_version,
            'reportingPeriodStart' => today()->toDateString(),
            'reportingPeriodEnd' => today()->addDays(6)->toDateString(),
            'accomplishmentSummary' => 'Management reports measurable corrective-action progress.',
            'issuesAndConstraints' => 'Procurement timing remains a constraint.',
            'correctiveActionsForDelays' => 'Expedite the pending coordination.',
            'nextSteps' => 'Complete the remaining accepted milestones.',
            'forecastCompletionDate' => today()->addDays(20)->toDateString(),
            'managementDeclaration' => 'This is management-reported information.',
            'generalEvidenceExplanation' => 'Supporting records are being finalized and will follow.',
            'milestoneProgress' => $accepted->milestones->values()->map(
                fn ($milestone, int $index): array => [
                    'actionPlanMilestoneId' => $milestone->id,
                    'managementReportedStatusCode' => 'IN_PROGRESS',
                    'managementReportedPercentage' => 50,
                    'accomplishmentDescription' => "Reported progress for milestone {$milestone->sequence_number}.",
                    'issuesAndConstraints' => 'Minor coordination delay.',
                    'nextStep' => 'Complete and document the output.',
                    'forecastCompletionDate' => today()->addDays(15 + $index)->toDateString(),
                    'noEvidenceExplanation' => 'The supporting record is undergoing management finalization.',
                    'displayOrder' => $index + 1,
                ],
            )->all(),
        ];
    }

    /**
     * @return array{CmsRecommendationCase, CmsCorrectiveActionPlan, CmsActionPlanVersion}
     */
    private function monitoringCase(
        string $suffix,
        Office $office,
        bool $weighted = true,
    ): array {
        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $auditee = $this->user('auditee');
        $risk = MasterList::query()
            ->where('code', 'RISK_LEVEL')
            ->firstOrFail()->items()->where('code', 'HIGH')->firstOrFail();
        $confidentiality = MasterList::query()
            ->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->firstOrFail()->items()->where('code', 'INTERNAL')->firstOrFail();
        $target = today()->addMonths(2)->toDateString();
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => "CMS4A-{$suffix}",
            'title' => "CMS-4A {$suffix}",
            'source_type' => 'SPECIAL',
            'special_authority_reference' => "AUTH-{$suffix}",
            'special_authority_date' => today()->subMonth(),
            'special_authority_approved_by' => $management->id,
            'objectives' => 'Test CMS-4A.',
            'scope' => 'Management-reported progress.',
            'status' => 'REPORTING',
            'created_by' => $management->id,
            'updated_by' => $management->id,
        ]);
        $engagement->offices()->attach($office->id, ['is_primary' => true]);
        $finding = AuditFinding::query()->create([
            'finding_family_uuid' => (string) Str::uuid(),
            'revision_number' => 1,
            'is_current_revision' => true,
            'audit_engagement_id' => $engagement->id,
            'finding_code' => "FND-{$suffix}",
            'title' => "Finding {$suffix}",
            'criteria' => 'Expected control.',
            'condition' => 'Control gap.',
            'cause' => 'Process weakness.',
            'effect' => 'Risk exposure.',
            'risk_rating_id' => $risk->id,
            'responsible_office_id' => $office->id,
            'status' => 'FINALIZED',
            'authored_by' => $auditor->id,
        ]);
        $recommendation = AuditRecommendation::query()->create([
            'audit_finding_id' => $finding->id,
            'recommendation_code' => "REC-{$suffix}",
            'recommendation' => "Correct {$suffix}.",
            'responsible_office_id' => $office->id,
            'target_implementation_date' => $target,
            'status' => 'FINALIZED',
            'created_by' => $auditor->id,
        ]);
        $report = AuditReport::query()->create([
            'audit_engagement_id' => $engagement->id,
            'report_code' => "AR-{$suffix}",
            'title' => "Final report {$suffix}",
            'report_stage' => 'FINAL_REPORT',
            'status' => 'ISSUED',
            'current_version_number' => 1,
            'confidentiality_level_id' => $confidentiality->id,
            'prepared_by' => $auditor->id,
            'issued_at' => now()->subDays(5),
            'issued_by' => $management->id,
        ]);
        $reportVersion = AuditReportVersion::query()->create([
            'audit_report_id' => $report->id,
            'version_number' => 1,
            'report_stage' => 'FINAL_REPORT',
            'content_snapshot' => [],
            'checksum_sha256' => hash('sha256', $suffix),
            'change_reason' => 'Issued report.',
            'created_by' => $auditor->id,
        ]);
        $intake = CmsRecommendation::query()->create([
            'source_audit_recommendation_id' => $recommendation->id,
            'transfer_key' => (string) Str::uuid(),
            'audit_engagement_id' => $engagement->id,
            'audit_report_id' => $report->id,
            'audit_report_version_id' => $reportVersion->id,
            'report_code_snapshot' => $report->report_code,
            'report_version_number_snapshot' => 1,
            'report_issued_at' => $report->issued_at,
            'report_issued_by' => $management->id,
            'report_checksum_sha256' => $reportVersion->checksum_sha256,
            'confidentiality_level_id' => $confidentiality->id,
            'confidentiality_code_snapshot' => $confidentiality->code,
            'confidentiality_label_snapshot' => $confidentiality->label,
            'audit_finding_id' => $finding->id,
            'risk_rating_id' => $risk->id,
            'risk_code_snapshot' => $risk->code,
            'risk_label_snapshot' => $risk->label,
            'recommendation_code' => $recommendation->recommendation_code,
            'source_snapshot' => [
                'engagement' => [
                    'id' => $engagement->id,
                    'code' => $engagement->engagement_code,
                    'title' => $engagement->title,
                ],
                'finding' => [
                    'id' => $finding->id,
                    'code' => $finding->finding_code,
                    'title' => $finding->title,
                ],
                'recommendation' => [
                    'id' => $recommendation->id,
                    'code' => $recommendation->recommendation_code,
                    'wording' => $recommendation->recommendation,
                ],
            ],
            'responsible_office_id' => $office->id,
            'responsible_office_snapshot' => [[
                'id' => $office->id,
                'code' => $office->code,
                'name' => $office->name,
                'isLead' => true,
            ]],
            'lead_responsible_office_id' => $office->id,
            'target_implementation_date' => $target,
            'original_target_implementation_date' => $target,
            'source_schema_version' => 1,
            'status' => 'TRANSFERRED',
            'transferred_at' => now()->subDays(5),
            'transferred_by' => $management->id,
        ]);
        $case = CmsRecommendationCase::query()->create([
            'cms_recommendation_id' => $intake->id,
            'status_code' => 'MONITORING',
            'effective_target_implementation_date' => $target,
            'lead_responsible_office_id' => $office->id,
            'opened_at' => $intake->transferred_at,
            'created_by' => $management->id,
            'lock_version' => 1,
        ]);
        CmsRecommendationEvent::query()->create([
            'cms_recommendation_case_id' => $case->id,
            'cms_recommendation_id' => $intake->id,
            'idempotency_key' => "cms-intake:{$intake->id}",
            'event_code' => 'INTAKE_CREATED',
            'source_module' => 'AEMS',
            'actor_id' => $management->id,
            'new_status' => 'TRANSFERRED',
            'event_metadata' => ['transferKey' => $intake->transfer_key],
            'created_at' => $intake->transferred_at,
        ]);
        $plan = CmsCorrectiveActionPlan::query()->create([
            'cms_recommendation_case_id' => $case->id,
            'owner_office_id' => $office->id,
            'created_by' => $auditee->id,
            'lock_version' => 1,
        ]);
        $accepted = CmsActionPlanVersion::query()->create([
            'cms_corrective_action_plan_id' => $plan->id,
            'version_number' => 1,
            'status_code' => 'ACCEPTED',
            'active_slot' => null,
            'plan_summary' => 'Accepted management plan.',
            'implementation_strategy' => 'Implement the corrective control.',
            'expected_outcome' => 'Control gap corrected.',
            'planned_start_date' => today()->subDay(),
            'planned_target_date' => $target,
            'owner_office_id' => $office->id,
            'focal_user_id' => $auditee->id,
            'prepared_by' => $auditee->id,
            'submitted_by' => $auditee->id,
            'submitted_at' => now()->subDays(3),
            'review_started_by' => $auditor->id,
            'review_started_at' => now()->subDays(2),
            'accepted_by' => $management->id,
            'accepted_at' => now()->subDay(),
            'acceptance_comment' => 'Accepted monitoring baseline.',
            'submission_snapshot' => [],
            'lock_version' => 4,
        ]);
        foreach ([40, 60] as $index => $weight) {
            $accepted->milestones()->create([
                'sequence_number' => $index + 1,
                'title' => 'Accepted milestone '.($index + 1),
                'description' => 'Controlled milestone wording.',
                'expected_output' => 'Measurable output '.($index + 1),
                'success_indicator' => 'Output is documented.',
                'verification_method' => 'Inspect management evidence.',
                'responsible_office_id' => $office->id,
                'responsible_user_id' => $auditee->id,
                'planned_start_date' => today(),
                'planned_target_date' => today()->addDays(30 + $index),
                'weight_percentage' => $weighted ? $weight : null,
                'display_order' => $index + 1,
            ]);
        }
        $plan->forceFill([
            'current_version_id' => $accepted->id,
            'accepted_version_id' => $accepted->id,
        ])->save();

        return [
            $case->fresh(['actionPlan.acceptedVersion.milestones']),
            $plan->fresh(),
            $accepted->fresh('milestones'),
        ];
    }

    private function assignMonitor(CmsRecommendationCase $case, User $reviewer): void
    {
        CmsRecommendationAssignment::query()->create([
            'cms_recommendation_case_id' => $case->id,
            'user_id' => $reviewer->id,
            'assignment_role_code' => 'COMPLIANCE_MONITOR',
            'assigned_by' => $this->user('departmenthead')->id,
            'assigned_at' => now(),
            'effective_from' => now(),
            'is_current' => true,
        ]);
    }

    private function user(string $username): User
    {
        return User::query()
            ->with(['office', 'role.permissions', 'roles.permissions'])
            ->where('username', $username)
            ->firstOrFail();
    }
}
