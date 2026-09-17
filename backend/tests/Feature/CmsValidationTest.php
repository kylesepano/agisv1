<?php

namespace Tests\Feature;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditRecommendation;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Models\CmsActionPlanVersion;
use App\Models\CmsCorrectiveActionPlan;
use App\Models\CmsDispositionDecision;
use App\Models\CmsMilestoneProgress;
use App\Models\CmsProgressUpdate;
use App\Models\CmsProgressUpdateVersion;
use App\Models\CmsRecommendation;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\CmsValidationAssignment;
use App\Models\CmsValidationEvidenceLink;
use App\Models\CmsValidationReview;
use App\Models\CmsValidationVersion;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\MasterList;
use App\Models\Permission;
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
use LogicException;
use Tests\TestCase;

class CmsValidationTest extends TestCase
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

    public function test_schema_permissions_constraints_and_professional_role_separation(): void
    {
        foreach ([
            'cms_validation_reviews',
            'cms_validation_versions',
            'cms_validation_items',
            'cms_validation_evidence_assessments',
            'cms_validation_assignments',
            'cms_validation_evidence_links',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertSame(
            9,
            Permission::query()->where('code', 'like', 'cms.validation.%')->count(),
        );
        $this->assertSame(
            4,
            Permission::query()->where('code', 'like', 'cms.validation-evidence.%')->count(),
        );
        $this->assertSame(125, Permission::query()->where('code', 'like', 'cms.%')->count());

        $management = $this->user('departmenthead');
        $validator = $this->user('cias.employee');
        $auditee = $this->user('auditee');
        $platform = $this->user('admin');
        $administrator = $this->user('agisadmin');
        $readOnly = $this->user('mayor');

        $this->assertTrue($management->hasPermission('cms.validation.finalize'));
        $this->assertTrue($validator->hasPermission('cms.validation.submit'));
        $this->assertTrue($validator->hasPermission('cms.validation-evidence.upload'));
        $this->assertTrue($auditee->hasPermission('cms.validation.view'));
        $this->assertFalse($auditee->hasPermission('cms.validation.update'));
        $this->assertTrue($readOnly->hasPermission('cms.validation.view'));
        $this->assertFalse($readOnly->hasPermission('cms.validation-evidence.download'));
        $this->assertFalse($platform->hasPermission('cms.validation.view'));
        $this->assertFalse($administrator->hasPermission('cms.validation.finalize'));

        $fixture = $this->fixture('CONSTRAINT');
        $review = $this->createReview($fixture);
        try {
            CmsValidationVersion::query()->create([
                'cms_validation_review_id' => $review->id,
                'version_number' => 2,
                'status_code' => 'DRAFT',
                'active_slot' => 'ACTIVE',
                'validator_user_id' => $fixture['validator']->id,
                'prepared_by' => $fixture['validator']->id,
            ]);
            $this->fail('Only one active Validation Version should be allowed.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        try {
            CmsValidationAssignment::query()->create([
                'cms_validation_review_id' => $review->id,
                'user_id' => $this->user('auditor')->id,
                'assignment_role_code' => 'PRIMARY_VALIDATOR',
                'assigned_by' => $fixture['supervisor']->id,
                'assigned_at' => now(),
                'assignment_reason' => 'Duplicate current assignment.',
                'effective_from' => now(),
                'is_current' => true,
                'current_slot' => 'CURRENT',
            ]);
            $this->fail('Only one current Primary Validator should be allowed.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    public function test_accepted_risk_disposition_requires_independent_review_and_decision(): void
    {
        $fixture = $this->fixture('DISPOSITION-ACCEPTED-RISK');
        $auditee = $fixture['auditee'];
        $management = $fixture['supervisor'];
        $reviewer = User::factory()->create([
            'role_id' => $management->role_id,
            'office_id' => $management->office_id,
            'username' => 'cias.disposition.decisioner',
            'employee_id' => 'CIAS-DISP-001',
        ]);
        $decisionMaker = User::factory()->create([
            'role_id' => $management->role_id,
            'office_id' => $management->office_id,
            'username' => 'cias.disposition.approver',
            'employee_id' => 'CIAS-DISP-003',
        ]);
        $case = $fixture['case']->fresh();

        Sanctum::actingAs($auditee);
        $draft = $this->postJson("/api/cms/recommendations/{$case->id}/dispositions", [
            'dispositionCode' => 'ACCEPTED_RISK',
        ])->assertCreated()->json('data.request');
        $version = $draft['currentVersion'];

        $document = Document::query()->create([
            'document_type_id' => MasterList::query()->firstOrFail()->items()->firstOrFail()->id,
            'title' => 'Disposition support evidence',
            'original_file_name' => 'disposition-support.txt',
            'storage_path' => 'cms/disposition-support.txt',
            'mime_type' => 'text/plain',
            'file_extension' => 'txt',
            'file_size' => 12,
            'checksum_sha256' => hash('sha256', 'support'),
            'uploaded_by' => $auditee->id,
            'updated_by' => $auditee->id,
            'is_active' => true,
        ]);
        $documentVersion = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'change_summary' => 'Initial evidence version.',
            'original_file_name' => 'disposition-support.txt',
            'storage_path' => 'cms/disposition-support.txt',
            'mime_type' => 'text/plain',
            'file_size' => 12,
            'checksum_sha256' => hash('sha256', 'support'),
            'uploaded_by' => $auditee->id,
        ]);
        $document->forceFill(['current_version_id' => $documentVersion->id])->save();
        $linked = $this->postJson("/api/cms/disposition-requests/{$draft['id']}/versions/{$version['id']}/evidence", [
            'documentVersionId' => $documentVersion->id,
            'evidenceCategory' => 'DISPOSITION_SUPPORT',
            'title' => 'Exact Core version support',
            'sourceOrCustodian' => 'Responsible office',
        ])->assertOk()->json('data.request');
        $version = $linked['currentVersion'];
        $this->assertDatabaseHas('cms_disposition_evidence_links', [
            'document_id' => $document->id,
            'document_version_id' => $documentVersion->id,
            'checksum_sha256' => $documentVersion->checksum_sha256,
        ]);

        $payload = [
            'lockVersion' => $version['lockVersion'],
            'dispositionSummary' => 'Management requests controlled accepted-risk disposition.',
            'basisAndCriteria' => 'The residual risk is within the approved risk tolerance.',
            'riskImpactAssessment' => 'Residual risk remains monitored under the approved control plan.',
            'managementPosition' => 'Management accepts the documented residual risk.',
            'responsibleOfficeConfirmation' => 'The responsible office confirms ownership of monitoring.',
            'acceptedRiskRationale' => 'Further implementation is disproportionate to the residual exposure.',
            'riskTreatmentAndMonitoring' => 'Quarterly monitoring and management reporting will continue.',
            'noAdditionalEvidenceExplanation' => 'The existing evidence set is complete for this decision.',
        ];
        $submittedResponse = $this->postJson("/api/cms/disposition-requests/{$draft['id']}/versions/{$version['id']}/transitions/submit", $payload)
            ->assertOk();
        $submitted = $submittedResponse->json('data.request');
        $this->assertSame('FOR_DISPOSITION', $submittedResponse->json('data.caseContext.status'));

        Sanctum::actingAs($management);
        $underReview = $this->postJson("/api/cms/disposition-requests/{$draft['id']}/versions/{$version['id']}/transitions/start-review", [])
            ->assertOk()->json('data.request');
        $this->assertSame('UNDER_REVIEW', $underReview['currentVersion']['statusCode']);
        Sanctum::actingAs($reviewer);
        $reviewed = $this->postJson("/api/cms/disposition-requests/{$draft['id']}/versions/{$version['id']}/transitions/recommend", [
            'readinessAssessment' => 'Ready.',
            'basisAssessment' => 'Basis is adequately documented.',
            'evidenceAssessment' => 'Evidence is sufficient and traceable.',
            'riskAssessment' => 'Residual risk is accepted subject to monitoring.',
        ])->assertOk()->json('data.request');
        $this->assertSame('FOR_DECISION', $reviewed['currentVersion']['statusCode']);

        Sanctum::actingAs($decisionMaker);
        $approvedResponse = $this->postJson("/api/cms/disposition-requests/{$draft['id']}/versions/{$version['id']}/transitions/approve", [
            'decisionComment' => 'Approved by an independent CIAS Management decision-maker.',
            'effectiveDate' => today()->toDateString(),
        ])->assertOk();
        $approved = $approvedResponse->json('data.request');

        $this->assertSame('APPROVED', $approved['currentVersion']['statusCode']);
        $this->assertSame('ACCEPTED_RISK', $approvedResponse->json('data.caseContext.status'));
        $this->assertDatabaseHas('cms_disposition_decisions', [
            'cms_disposition_request_version_id' => $version['id'],
            'decision_code' => 'APPROVED',
            'decided_by' => $decisionMaker->id,
            'new_case_status' => 'ACCEPTED_RISK',
        ]);
        $this->assertDatabaseHas('cms_recommendation_events', [
            'cms_recommendation_case_id' => $case->id,
            'event_code' => 'DISPOSITION_APPROVED',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'cms.disposition.approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cms.disposition.approved']);
        $this->assertDatabaseHas('notifications', ['type' => 'CMS_DISPOSITION_APPROVED']);
        $decision = CmsDispositionDecision::query()->where('cms_disposition_request_version_id', $version['id'])->firstOrFail();
        try {
            $decision->update(['decision_comment' => 'tamper']);
            $this->fail('Disposition decisions must remain immutable.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
        Sanctum::actingAs($management);
        $this->getJson('/api/cms/dashboard')
            ->assertOk()
            ->assertJsonPath('data.cards.acceptedRiskRecommendations', 1)
            ->assertJsonPath('data.cards.totalVisibleCases', 0);
        $this->getJson("/api/cms/recommendations/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.recommendation.dispositionSummary.acceptedRisk', true);
    }

    public function test_no_longer_applicable_is_a_separate_disposition_and_rejection_restores_prior_status(): void
    {
        $fixture = $this->fixture('DISPOSITION-NLA');
        $case = $fixture['case']->fresh();
        $auditee = $fixture['auditee'];
        $management = $fixture['supervisor'];
        $reviewer = User::factory()->create([
            'role_id' => $management->role_id,
            'office_id' => $management->office_id,
            'username' => 'cias.disposition.rejecter',
            'employee_id' => 'CIAS-DISP-002',
        ]);
        $decisionMaker = User::factory()->create([
            'role_id' => $management->role_id,
            'office_id' => $management->office_id,
            'username' => 'cias.disposition.nla-approver',
            'employee_id' => 'CIAS-DISP-004',
        ]);

        Sanctum::actingAs($auditee);
        $draft = $this->postJson("/api/cms/recommendations/{$case->id}/dispositions", ['dispositionCode' => 'NO_LONGER_APPLICABLE'])
            ->assertCreated()->json('data.request');
        $version = $draft['currentVersion'];
        $payload = [
            'lockVersion' => $version['lockVersion'],
            'dispositionSummary' => 'The recommendation is no longer applicable.',
            'basisAndCriteria' => 'The underlying process has been retired.',
            'riskImpactAssessment' => 'The original risk no longer exists in the current operating model.',
            'managementPosition' => 'Management confirms the changed operating context.',
            'responsibleOfficeConfirmation' => 'The responsible office confirms the transition.',
            'noLongerApplicableBasis' => 'The audited activity was formally discontinued.',
            'transitionOrRecordsImpact' => 'Records are retained under the replacement process.',
            'noAdditionalEvidenceExplanation' => 'No additional evidence is required beyond the transition record.',
        ];
        $this->postJson("/api/cms/disposition-requests/{$draft['id']}/versions/{$version['id']}/transitions/submit", $payload)->assertOk();
        Sanctum::actingAs($management);
        $this->postJson("/api/cms/disposition-requests/{$draft['id']}/versions/{$version['id']}/transitions/start-review", [])->assertOk();
        Sanctum::actingAs($reviewer);
        $this->postJson("/api/cms/disposition-requests/{$draft['id']}/versions/{$version['id']}/transitions/recommend", [
            'readinessAssessment' => 'Ready.', 'basisAssessment' => 'Adequate.', 'evidenceAssessment' => 'Sufficient.', 'riskAssessment' => 'No residual risk from the retired activity.',
        ])->assertOk();
        Sanctum::actingAs($decisionMaker);
        $rejectedResponse = $this->postJson("/api/cms/disposition-requests/{$draft['id']}/versions/{$version['id']}/transitions/reject", [
            'decisionComment' => 'The transition evidence does not yet establish that the recommendation is no longer applicable.',
        ])->assertOk();
        $rejected = $rejectedResponse->json('data.request');
        $this->assertSame('REJECTED', $rejected['currentVersion']['statusCode']);
        $this->assertSame('MONITORING', $rejectedResponse->json('data.caseContext.status'));
        $this->assertDatabaseHas('cms_disposition_decisions', ['decision_code' => 'REJECTED', 'new_case_status' => 'MONITORING']);
    }

    public function test_returned_disposition_creates_a_new_immutable_revision(): void
    {
        $fixture = $this->fixture('DISPOSITION-REVISION');
        $case = $fixture['case']->fresh();
        $auditee = $fixture['auditee'];
        $management = $fixture['supervisor'];
        $reviewer = User::factory()->create([
            'role_id' => $management->role_id,
            'office_id' => $management->office_id,
            'username' => 'cias.disposition.reviser',
            'employee_id' => 'CIAS-DISP-005',
        ]);

        Sanctum::actingAs($auditee);
        $draft = $this->postJson("/api/cms/recommendations/{$case->id}/dispositions", ['dispositionCode' => 'ACCEPTED_RISK'])
            ->assertCreated()->json('data.request');
        $version = $draft['currentVersion'];
        $payload = [
            'lockVersion' => $version['lockVersion'],
            'dispositionSummary' => 'Initial disposition draft.',
            'basisAndCriteria' => 'Initial basis.',
            'riskImpactAssessment' => 'Initial risk assessment.',
            'managementPosition' => 'Initial management position.',
            'responsibleOfficeConfirmation' => 'Initial office confirmation.',
            'acceptedRiskRationale' => 'Initial accepted-risk rationale.',
            'noAdditionalEvidenceExplanation' => 'Initial evidence explanation.',
        ];
        $this->postJson("/api/cms/disposition-requests/{$draft['id']}/versions/{$version['id']}/transitions/submit", $payload)->assertOk();
        Sanctum::actingAs($management);
        $this->postJson("/api/cms/disposition-requests/{$draft['id']}/versions/{$version['id']}/transitions/start-review", [])->assertOk();
        Sanctum::actingAs($reviewer);
        $returned = $this->postJson("/api/cms/disposition-requests/{$draft['id']}/versions/{$version['id']}/transitions/return", ['returnReason' => 'Provide a clearer risk treatment narrative.'])
            ->assertOk()->json('data.request');
        $this->assertSame('RETURNED', $returned['currentVersion']['statusCode']);

        Sanctum::actingAs($auditee);
        $revised = $this->postJson("/api/cms/disposition-requests/{$draft['id']}/versions/{$version['id']}/revisions", [
            'revisionReason' => 'Expanded the risk treatment narrative.',
        ])->assertCreated()->json('data.request');
        $this->assertSame(2, $revised['currentVersion']['versionNumber']);
        $this->assertSame('DRAFT', $revised['currentVersion']['statusCode']);
        $this->assertDatabaseHas('cms_disposition_request_versions', [
            'id' => $version['id'],
            'status_code' => 'RETURNED',
            'active_slot' => null,
        ]);
    }

    public function test_review_creation_pins_latest_sources_assigns_validator_and_enforces_independence(): void
    {
        $fixture = $this->fixture('CREATE');
        $review = $this->createReview($fixture);

        $this->assertSame('FOR_VALIDATION', $fixture['case']->fresh()->status_code);
        $this->assertSame($fixture['accepted']->id, $review->accepted_action_plan_version_id);
        $this->assertSame($fixture['recorded']->id, $review->recorded_progress_update_version_id);
        $this->assertSame($fixture['validator']->id, $review->currentAssignment->user_id);
        $this->assertSame(
            $fixture['accepted']->milestones()->count(),
            $review->currentVersion->items()->count(),
        );
        $this->assertDatabaseHas('cms_recommendation_events', [
            'cms_recommendation_case_id' => $fixture['case']->id,
            'event_code' => 'VALIDATION_REVIEW_CREATED',
        ]);
        $this->assertDatabaseHas('cms_recommendation_events', [
            'cms_recommendation_case_id' => $fixture['case']->id,
            'event_code' => 'VALIDATOR_ASSIGNED',
        ]);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $fixture['validator']->id,
            'type' => 'CMS_VALIDATOR_ASSIGNED',
        ]);

        Sanctum::actingAs($fixture['supervisor']);
        $this->postJson(
            "/api/cms/recommendations/{$fixture['case']->id}/validations",
            [
                'recordedProgressUpdateVersionId' => $fixture['recorded']->id,
                'validatorUserId' => $fixture['validator']->id,
                'assignmentReason' => 'Duplicate cycle.',
                'lockVersion' => $fixture['case']->fresh()->lock_version,
            ],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('recommendation');

        foreach ([
            $fixture['auditee'],
            $fixture['recorder'],
        ] as $conflicted) {
            $other = $this->fixture('CONFLICT-'.$conflicted->id);
            Sanctum::actingAs($other['supervisor']);
            $this->postJson(
                "/api/cms/recommendations/{$other['case']->id}/validations",
                [
                    'recordedProgressUpdateVersionId' => $other['recorded']->id,
                    'validatorUserId' => $conflicted->id,
                    'assignmentReason' => 'Invalid self-review assignment.',
                    'lockVersion' => $other['case']->lock_version,
                ],
            )->assertUnprocessable()
                ->assertJsonValidationErrors('validatorUserId');
        }
    }

    public function test_validation_options_are_scoped_and_reuse_independence_filters(): void
    {
        $fixture = $this->fixture('OPTIONS');
        $inactive = User::factory()->inactive()->create([
            'role_id' => $fixture['validator']->role_id,
            'office_id' => $fixture['validator']->office_id,
        ]);
        $locked = User::factory()->locked()->create([
            'role_id' => $fixture['validator']->role_id,
            'office_id' => $fixture['validator']->office_id,
        ]);
        $archived = User::factory()->create([
            'role_id' => $fixture['validator']->role_id,
            'office_id' => $fixture['validator']->office_id,
        ]);
        $archived->delete();
        Sanctum::actingAs($fixture['supervisor']);

        $response = $this->getJson(
            "/api/cms/recommendations/{$fixture['case']->id}/validation-options",
        )->assertOk()
            ->assertJsonPath('data.caseContext.lockVersion', 1)
            ->assertJsonPath('data.eligibleRecordedProgressUpdates.0.recordedVersionId', $fixture['recorded']->id)
            ->assertJsonPath('data.eligibleRecordedProgressUpdates.0.acceptedActionPlanVersion.id', $fixture['accepted']->id);

        $validators = collect($response->json('data.eligibleValidators'));
        $this->assertTrue($validators->contains('id', $fixture['validator']->id));
        $this->assertFalse($validators->contains('id', $fixture['auditee']->id));
        $this->assertFalse($validators->contains('id', $fixture['recorder']->id));
        $this->assertFalse($validators->contains('id', $fixture['supervisor']->id));
        $this->assertFalse($validators->contains('id', $this->user('admin')->id));
        $this->assertFalse($validators->contains('id', $inactive->id));
        $this->assertFalse($validators->contains('id', $locked->id));
        $this->assertFalse($validators->contains('id', $archived->id));

        Sanctum::actingAs($fixture['auditee']);
        $this->getJson(
            "/api/cms/recommendations/{$fixture['case']->id}/validation-options",
        )->assertForbidden();

        Sanctum::actingAs($fixture['supervisor']);
        $this->getJson('/api/cms/recommendations/999999/validation-options')
            ->assertNotFound();
    }

    public function test_complete_validation_submission_review_and_implemented_finalization_are_atomic_and_immutable(): void
    {
        $fixture = $this->fixture('IMPLEMENTED', 100);
        $review = $this->createReview($fixture);
        $version = $this->completeDraft($fixture, $review, 'IMPLEMENTED');

        Sanctum::actingAs($fixture['validator']);
        $this->postJson(
            "/api/cms/validations/{$review->id}/versions/{$version->id}/transitions/submit",
            ['lockVersion' => $version->lock_version, 'confirmation' => true],
        )->assertOk()
            ->assertJsonPath('data.validation.currentVersion.status', 'SUBMITTED')
            ->assertJsonPath(
                'data.validation.currentVersion.proposedConclusionCode',
                'IMPLEMENTED',
            );
        $submitted = $version->fresh();
        $this->assertNotNull($submitted->submission_snapshot);
        $this->assertSame(
            $fixture['recorded']->id,
            data_get($submitted->submission_snapshot, 'recordedProgressUpdateVersionId'),
        );
        $this->assertSame('FOR_VALIDATION', $fixture['case']->fresh()->status_code);

        try {
            $submitted->forceFill(['validation_scope' => 'Illegal submitted edit.'])->save();
            $this->fail('Submitted Validation Versions must be immutable.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        Sanctum::actingAs($fixture['validator']);
        $this->postJson(
            "/api/cms/validations/{$review->id}/versions/{$submitted->id}/transitions/start-review",
            ['lockVersion' => $submitted->lock_version],
        )->assertForbidden();

        Sanctum::actingAs($fixture['supervisor']);
        $this->postJson(
            "/api/cms/validations/{$review->id}/versions/{$submitted->id}/transitions/start-review",
            ['lockVersion' => $submitted->lock_version, 'reviewComment' => 'Review started.'],
        )->assertOk()
            ->assertJsonPath('data.validation.currentVersion.status', 'UNDER_REVIEW');
        $underReview = $submitted->fresh();
        $this->postJson(
            "/api/cms/validations/{$review->id}/versions/{$underReview->id}/transitions/finalize",
            [
                'lockVersion' => $underReview->lock_version,
                'finalConclusionCode' => 'IMPLEMENTED',
                'finalizationComment' => 'Sufficient appropriate evidence supports implementation.',
                'confirmation' => true,
            ],
        )->assertOk()
            ->assertJsonPath('data.validation.currentVersion.status', 'FINALIZED')
            ->assertJsonPath('data.validation.currentVersion.finalConclusionCode', 'IMPLEMENTED')
            ->assertJsonPath('data.validation.sourceContext.caseStatus', 'IMPLEMENTED');

        $this->assertSame('IMPLEMENTED', $fixture['case']->fresh()->status_code);
        $this->assertNull($review->fresh()->active_slot);
        $this->assertSame(
            $underReview->id,
            $review->fresh()->finalized_version_id,
        );
        $this->assertDatabaseHas('activity_logs', ['action' => 'cms.validation.finalized']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cms.validation.finalized']);
        $this->assertDatabaseHas('cms_recommendation_events', [
            'event_code' => 'VALIDATION_FINALIZED',
            'new_status' => 'IMPLEMENTED',
        ]);
        $this->assertDatabaseMissing('cms_recommendation_cases', [
            'id' => $fixture['case']->id,
            'status_code' => 'CLOSED',
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => 'CMS_VALIDATION_FINALIZED',
        ]);

        $finalized = $underReview->fresh();
        try {
            $finalized->forceFill(['finalization_comment' => 'Illegal finalized edit.'])->save();
            $this->fail('Finalized Validation Versions must be immutable.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
        $this->postJson(
            "/api/cms/validations/{$review->id}/versions/{$finalized->id}/transitions/finalize",
            [
                'lockVersion' => $finalized->lock_version,
                'finalConclusionCode' => 'IMPLEMENTED',
                'finalizationComment' => 'Duplicate.',
                'confirmation' => true,
            ],
        )->assertUnprocessable();
    }

    public function test_all_professional_conclusions_drive_only_the_authorized_case_states(): void
    {
        foreach ([
            'NOT_IMPLEMENTED' => 'MONITORING',
            'INADEQUATE_BASIS' => 'MONITORING',
            'PARTIALLY_IMPLEMENTED' => 'PARTIALLY_IMPLEMENTED',
        ] as $conclusion => $expectedStatus) {
            $fixture = $this->fixture('STATE-'.$conclusion);
            $review = $this->createReview($fixture);
            $version = $this->completeDraft($fixture, $review, $conclusion);
            $this->submitAndStartReview($fixture, $review, $version);
            $underReview = $version->fresh();

            Sanctum::actingAs($fixture['supervisor']);
            $this->postJson(
                "/api/cms/validations/{$review->id}/versions/{$underReview->id}/transitions/finalize",
                [
                    'lockVersion' => $underReview->lock_version,
                    'finalConclusionCode' => $conclusion,
                    'finalizationComment' => "Supervisory confirmation of {$conclusion}.",
                    'confirmation' => true,
                ],
            )->assertOk();
            $this->assertSame($expectedStatus, $fixture['case']->fresh()->status_code);
        }
    }

    public function test_conclusion_contradictions_and_supervisory_override_are_rejected(): void
    {
        $fixture = $this->fixture('CONTRADICTION');
        $review = $this->createReview($fixture);
        $version = $this->completeDraft($fixture, $review, 'NOT_IMPLEMENTED', 'SATISFIED');

        Sanctum::actingAs($fixture['validator']);
        $this->postJson(
            "/api/cms/validations/{$review->id}/versions/{$version->id}/transitions/submit",
            ['lockVersion' => $version->lock_version, 'confirmation' => true],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('proposedConclusionCode');

        $fixture = $this->fixture('OVERRIDE');
        $review = $this->createReview($fixture);
        $version = $this->completeDraft($fixture, $review, 'PARTIALLY_IMPLEMENTED');
        $this->submitAndStartReview($fixture, $review, $version);
        $underReview = $version->fresh();
        Sanctum::actingAs($fixture['supervisor']);
        $this->postJson(
            "/api/cms/validations/{$review->id}/versions/{$underReview->id}/transitions/finalize",
            [
                'lockVersion' => $underReview->lock_version,
                'finalConclusionCode' => 'NOT_IMPLEMENTED',
                'finalizationComment' => 'Changed conclusion.',
                'confirmation' => true,
            ],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('overrideReason');
        $this->assertSame('FOR_VALIDATION', $fixture['case']->fresh()->status_code);
    }

    public function test_return_revision_and_validator_replacement_preserve_history(): void
    {
        $fixture = $this->fixture('REVISION');
        $review = $this->createReview($fixture);
        $version = $this->completeDraft($fixture, $review, 'PARTIALLY_IMPLEMENTED');
        $this->submitAndStartReview($fixture, $review, $version);
        $underReview = $version->fresh();
        Sanctum::actingAs($fixture['supervisor']);
        $this->postJson(
            "/api/cms/validations/{$review->id}/versions/{$underReview->id}/transitions/return",
            [
                'lockVersion' => $underReview->lock_version,
                'returnReason' => 'Clarify the remaining control gap.',
            ],
        )->assertOk()
            ->assertJsonPath('data.validation.currentVersion.status', 'RETURNED');
        $returned = $underReview->fresh();

        Sanctum::actingAs($fixture['validator']);
        $this->postJson(
            "/api/cms/validations/{$review->id}/versions/{$returned->id}/revisions",
            [
                'lockVersion' => $returned->lock_version,
                'revisionReason' => 'Address supervisory return instructions.',
            ],
        )->assertCreated()
            ->assertJsonPath('data.validation.currentVersion.status', 'DRAFT')
            ->assertJsonPath('data.validation.currentVersion.versionNumber', 2);
        $revision = $review->fresh('currentVersion')->currentVersion;
        $this->assertSame($returned->id, $revision->previous_version_id);
        $this->assertSame(
            $returned->items()->count(),
            $revision->items()->count(),
        );
        $this->assertSame('FOR_VALIDATION', $fixture['case']->fresh()->status_code);

        $replacement = $fixture['validator']->replicate();
        $replacement->forceFill([
            'username' => 'replacement.validator',
            'employee_id' => 'CIAS-VAL-REPLACEMENT',
            'email' => 'replacement.validator@agis.local',
            'name' => 'Replacement Independent Validator',
            'first_name' => 'Replacement',
            'last_name' => 'Validator',
            'initials' => 'RV',
        ])->save();
        Sanctum::actingAs($fixture['supervisor']);
        $this->postJson(
            "/api/cms/validations/{$review->id}/assignments",
            [
                'validatorUserId' => $replacement->id,
                'assignmentReason' => 'Workload-based independent reassignment.',
                'lockVersion' => $review->fresh()->lock_version,
            ],
        )->assertOk()
            ->assertJsonPath('data.validation.currentPrimaryValidator.id', $replacement->id);
        $this->assertDatabaseHas('cms_validation_assignments', [
            'cms_validation_review_id' => $review->id,
            'user_id' => $fixture['validator']->id,
            'is_current' => false,
        ]);
        $this->assertDatabaseHas('cms_validation_assignments', [
            'cms_validation_review_id' => $review->id,
            'user_id' => $replacement->id,
            'is_current' => true,
        ]);
        $this->assertSame($fixture['validator']->id, $revision->fresh()->prepared_by);
        $this->assertDatabaseHas('cms_recommendation_events', [
            'event_code' => 'VALIDATOR_REPLACED',
        ]);
    }

    public function test_validator_evidence_uses_core_versions_and_draft_removal_retains_file(): void
    {
        $fixture = $this->fixture('EVIDENCE');
        $review = $this->createReview($fixture);
        $version = $review->currentVersion;
        $classification = MasterList::query()
            ->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->firstOrFail()->items()->where('code', 'INTERNAL')->firstOrFail();
        Sanctum::actingAs($fixture['validator']);
        $response = $this->post(
            "/api/cms/validations/{$review->id}/versions/{$version->id}/evidence",
            [
                'lockVersion' => $version->lock_version,
                'validationItemId' => $version->items()->firstOrFail()->id,
                'evidenceCategory' => 'INSPECTION',
                'title' => 'Validator inspection record',
                'sourceOrCustodian' => 'Independent validator',
                'confidentialityLevelId' => $classification->id,
                'file' => UploadedFile::fake()->create('validation.pdf', 25, 'application/pdf'),
            ],
            ['Accept' => 'application/json'],
        )->assertCreated()
            ->assertJsonMissingPath('data.evidence.file.storagePath');
        $evidenceId = $response->json('data.evidence.id');
        $this->assertDatabaseHas('cms_validation_evidence_links', ['id' => $evidenceId]);
        $this->assertDatabaseHas('cms_validation_evidence_assessments', [
            'cms_validation_evidence_link_id' => $evidenceId,
            'evidence_source_code' => 'VALIDATOR_OBTAINED',
        ]);
        $evidence = CmsValidationEvidenceLink::query()->findOrFail($evidenceId);
        $this->get("/api/cms/validation-evidence/{$evidence->id}/download")
            ->assertOk();

        $version = $version->fresh();
        $this->deleteJson(
            "/api/cms/validation-evidence/{$evidence->id}",
            [
                'lockVersion' => $version->lock_version,
                'removalReason' => 'Replace with a more complete record.',
            ],
        )->assertOk();
        $this->assertNotNull($evidence->fresh()->removed_at);
        $this->assertDatabaseHas('documents', ['id' => $evidence->document_id]);
        $this->assertDatabaseHas('document_versions', ['id' => $evidence->document_version_id]);
        $this->assertDatabaseMissing('cms_validation_evidence_assessments', [
            'cms_validation_evidence_link_id' => $evidence->id,
        ]);
    }

    public function test_partial_status_allows_new_progress_but_validation_and_implemented_states_block_it(): void
    {
        $fixture = $this->fixture('PROGRESS-AFTER');
        Sanctum::actingAs($fixture['auditee']);
        $fixture['case']->forceFill(['status_code' => 'PARTIALLY_IMPLEMENTED'])->save();
        $this->postJson(
            "/api/cms/recommendations/{$fixture['case']->id}/progress-updates",
            $this->progressPayload($fixture, 2),
        )->assertCreated();

        foreach (['FOR_VALIDATION', 'IMPLEMENTED'] as $status) {
            $fixture['case']->forceFill([
                'status_code' => $status,
                'lock_version' => $fixture['case']->fresh()->lock_version + 1,
            ])->save();
            $payload = $this->progressPayload($fixture, 3);
            $payload['lockVersion'] = $fixture['case']->fresh()->lock_version;
            $this->postJson(
                "/api/cms/recommendations/{$fixture['case']->id}/progress-updates",
                $payload,
            )->assertUnprocessable()
                ->assertJsonValidationErrors('recommendation');
        }
    }

    /** @param array<string, mixed> $fixture */
    private function createReview(array $fixture): CmsValidationReview
    {
        Sanctum::actingAs($fixture['supervisor']);
        $response = $this->postJson(
            "/api/cms/recommendations/{$fixture['case']->id}/validations",
            [
                'recordedProgressUpdateVersionId' => $fixture['recorded']->id,
                'validatorUserId' => $fixture['validator']->id,
                'assignmentReason' => 'Independent professional validation assignment.',
                'lockVersion' => $fixture['case']->lock_version,
            ],
        )->assertCreated()
            ->assertJsonPath('data.validation.currentVersion.status', 'DRAFT');

        return CmsValidationReview::query()
            ->with(['currentVersion.items', 'currentAssignment'])
            ->findOrFail($response->json('data.validation.id'));
    }

    /** @param array<string, mixed> $fixture */
    private function completeDraft(
        array $fixture,
        CmsValidationReview $review,
        string $conclusion,
        ?string $forcedItemConclusion = null,
    ): CmsValidationVersion {
        $version = $review->currentVersion()->with('items')->firstOrFail();
        $codes = match ($conclusion) {
            'IMPLEMENTED' => ['SATISFIED', 'SATISFIED'],
            'PARTIALLY_IMPLEMENTED' => ['SATISFIED', 'NOT_SATISFIED'],
            'INADEQUATE_BASIS' => ['INADEQUATE_BASIS', 'NOT_SATISFIED'],
            default => ['NOT_SATISFIED', 'NOT_SATISFIED'],
        };
        $items = $version->items->values()->map(
            fn ($item, int $index): array => [
                'id' => $item->id,
                'scopeCode' => 'MILESTONE',
                'actionPlanMilestoneId' => $item->cms_action_plan_milestone_id,
                'milestoneProgressId' => $item->cms_milestone_progress_id,
                'sequenceNumber' => $item->sequence_number,
                'criterion' => $item->criterion ?: 'Accepted milestone criterion.',
                'procedurePerformed' => 'Inspected implementation records and corroborated the control.',
                'populationOrSource' => 'Recorded management evidence and source records.',
                'sampleDescription' => 'Judgmental sample of relevant transactions.',
                'resultSummary' => 'The procedure produced a documented professional result.',
                'exceptionSummary' => $conclusion === 'IMPLEMENTED'
                    ? null
                    : 'Material implementation work remains.',
                'itemConclusionCode' => $forcedItemConclusion ?? $codes[$index],
                'validatedMilestonePercentage' => $conclusion === 'IMPLEMENTED' ? 100 : 50,
                'followUpRequired' => $conclusion !== 'IMPLEMENTED',
                'displayOrder' => $index + 1,
            ],
        )->all();
        Sanctum::actingAs($fixture['validator']);
        $this->putJson(
            "/api/cms/validations/{$review->id}/versions/{$version->id}",
            [
                'lockVersion' => $version->lock_version,
                'validationScope' => 'Validate implementation of the accepted corrective actions.',
                'validationObjectives' => 'Reach an independent evidence-based conclusion.',
                'methodologySummary' => 'Inspection, inquiry, reperformance, and corroboration.',
                'overallWorkPerformed' => 'Completed all milestone-level validation procedures.',
                'overallEvidenceSummary' => 'Evidence was evaluated independently of management reporting.',
                'limitations' => $conclusion === 'INADEQUATE_BASIS'
                    ? 'Critical source documentation was unavailable; obtain the missing source records.'
                    : null,
                'professionalJudgmentRationale' => 'The conclusion reflects the procedures, results, exceptions, and evidence assessments, including the remaining work.',
                'proposedConclusionCode' => $conclusion,
                'validatedCompletionPercentage' => $conclusion === 'IMPLEMENTED' ? 100 : 50,
                'validationItems' => $items,
            ],
        )->assertOk();

        return $version->fresh('items');
    }

    /** @param array<string, mixed> $fixture */
    private function submitAndStartReview(
        array $fixture,
        CmsValidationReview $review,
        CmsValidationVersion $version,
    ): void {
        Sanctum::actingAs($fixture['validator']);
        $this->postJson(
            "/api/cms/validations/{$review->id}/versions/{$version->id}/transitions/submit",
            ['lockVersion' => $version->lock_version, 'confirmation' => true],
        )->assertOk();
        $submitted = $version->fresh();
        Sanctum::actingAs($fixture['supervisor']);
        $this->postJson(
            "/api/cms/validations/{$review->id}/versions/{$submitted->id}/transitions/start-review",
            ['lockVersion' => $submitted->lock_version, 'reviewComment' => 'Review started.'],
        )->assertOk();
    }

    /** @return array<string, mixed> */
    private function fixture(string $suffix, float $reportedPercentage = 75): array
    {
        $suffix = Str::upper(Str::slug($suffix, '-'));
        $supervisor = $this->user('departmenthead');
        $validator = $this->user('cias.employee');
        $recorder = $this->user('auditor');
        $auditee = $this->user('auditee');
        $office = $auditee->office;
        $risk = MasterList::query()
            ->where('code', 'RISK_LEVEL')
            ->firstOrFail()->items()->where('code', 'HIGH')->firstOrFail();
        $confidentiality = MasterList::query()
            ->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->firstOrFail()->items()->where('code', 'INTERNAL')->firstOrFail();
        $target = today()->addMonths(2);
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => "CMS5A-{$suffix}",
            'title' => "CMS-5A {$suffix}",
            'source_type' => 'SPECIAL',
            'special_authority_reference' => "AUTH-{$suffix}",
            'special_authority_date' => today()->subMonth(),
            'special_authority_approved_by' => $supervisor->id,
            'objectives' => 'Test independent validation.',
            'scope' => 'CMS professional validation.',
            'status' => 'REPORTING',
            'created_by' => $supervisor->id,
            'updated_by' => $supervisor->id,
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
            'authored_by' => $recorder->id,
        ]);
        $recommendation = AuditRecommendation::query()->create([
            'audit_finding_id' => $finding->id,
            'recommendation_code' => "REC-{$suffix}",
            'recommendation' => "Correct {$suffix}.",
            'responsible_office_id' => $office->id,
            'target_implementation_date' => $target,
            'status' => 'FINALIZED',
            'created_by' => $recorder->id,
        ]);
        $report = AuditReport::query()->create([
            'audit_engagement_id' => $engagement->id,
            'report_code' => "AR-{$suffix}",
            'title' => "Final report {$suffix}",
            'report_stage' => 'FINAL_REPORT',
            'status' => 'ISSUED',
            'current_version_number' => 1,
            'confidentiality_level_id' => $confidentiality->id,
            'prepared_by' => $recorder->id,
            'issued_at' => now()->subDays(10),
            'issued_by' => $supervisor->id,
        ]);
        $reportVersion = AuditReportVersion::query()->create([
            'audit_report_id' => $report->id,
            'version_number' => 1,
            'report_stage' => 'FINAL_REPORT',
            'content_snapshot' => [],
            'checksum_sha256' => hash('sha256', $suffix),
            'change_reason' => 'Issued report.',
            'created_by' => $recorder->id,
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
            'report_issued_by' => $supervisor->id,
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
            'transferred_at' => now()->subDays(10),
            'transferred_by' => $supervisor->id,
        ]);
        $case = CmsRecommendationCase::query()->create([
            'cms_recommendation_id' => $intake->id,
            'status_code' => 'MONITORING',
            'effective_target_implementation_date' => $target,
            'lead_responsible_office_id' => $office->id,
            'opened_at' => $intake->transferred_at,
            'created_by' => $supervisor->id,
            'lock_version' => 1,
        ]);
        CmsRecommendationEvent::query()->create([
            'cms_recommendation_case_id' => $case->id,
            'cms_recommendation_id' => $intake->id,
            'idempotency_key' => "cms-intake:{$intake->id}",
            'event_code' => 'INTAKE_CREATED',
            'source_module' => 'AEMS',
            'actor_id' => $supervisor->id,
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
            'planned_start_date' => today()->subMonth(),
            'planned_target_date' => $target,
            'owner_office_id' => $office->id,
            'focal_user_id' => $auditee->id,
            'prepared_by' => $auditee->id,
            'submitted_by' => $auditee->id,
            'submitted_at' => now()->subDays(9),
            'review_started_by' => $recorder->id,
            'review_started_at' => now()->subDays(8),
            'accepted_by' => $supervisor->id,
            'accepted_at' => now()->subDays(7),
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
                'success_indicator' => 'Output is documented and operating.',
                'verification_method' => 'Inspect and reperform the control.',
                'responsible_office_id' => $office->id,
                'responsible_user_id' => $auditee->id,
                'planned_start_date' => today()->subMonth(),
                'planned_target_date' => today()->addDays(30 + $index),
                'weight_percentage' => $weight,
                'display_order' => $index + 1,
            ]);
        }
        $plan->forceFill([
            'current_version_id' => $accepted->id,
            'accepted_version_id' => $accepted->id,
        ])->save();
        $update = CmsProgressUpdate::query()->create([
            'cms_recommendation_case_id' => $case->id,
            'cms_corrective_action_plan_id' => $plan->id,
            'accepted_action_plan_version_id' => $accepted->id,
            'reporting_sequence' => 1,
            'reporting_period_start' => today()->subDays(6),
            'reporting_period_end' => today()->subDay(),
            'created_by' => $auditee->id,
            'lock_version' => 1,
        ]);
        $recorded = CmsProgressUpdateVersion::query()->create([
            'cms_progress_update_id' => $update->id,
            'version_number' => 1,
            'status_code' => 'RECORDED',
            'active_slot' => null,
            'accomplishment_summary' => 'Management reports implementation progress.',
            'management_reported_overall_percentage' => $reportedPercentage,
            'system_calculated_weighted_percentage' => $reportedPercentage,
            'baseline_weighted' => true,
            'management_declaration' => 'Management reporting only.',
            'prepared_by' => $auditee->id,
            'submitted_by' => $auditee->id,
            'submitted_at' => now()->subDays(3),
            'review_started_by' => $recorder->id,
            'review_started_at' => now()->subDays(2),
            'recorded_by' => $recorder->id,
            'recorded_at' => now()->subDay(),
            'recording_comment' => 'Completeness reviewed; not validated.',
            'submission_snapshot' => [],
            'lock_version' => 4,
        ]);
        foreach ($accepted->milestones()->orderBy('display_order')->get() as $index => $milestone) {
            CmsMilestoneProgress::query()->create([
                'cms_progress_update_version_id' => $recorded->id,
                'cms_action_plan_milestone_id' => $milestone->id,
                'milestone_sequence' => $milestone->sequence_number,
                'milestone_snapshot' => ['title' => $milestone->title],
                'management_reported_status_code' => $reportedPercentage >= 100
                    ? 'REPORTED_COMPLETED'
                    : 'IN_PROGRESS',
                'management_reported_percentage' => $reportedPercentage,
                'accomplishment_description' => 'Management-reported accomplishment.',
                'display_order' => $index + 1,
            ]);
        }
        $update->forceFill([
            'current_version_id' => $recorded->id,
            'recorded_version_id' => $recorded->id,
        ])->save();

        return [
            'supervisor' => $supervisor,
            'validator' => $validator,
            'recorder' => $recorder,
            'auditee' => $auditee,
            'case' => $case->fresh(['recommendation', 'actionPlan.acceptedVersion.milestones']),
            'plan' => $plan->fresh(),
            'accepted' => $accepted->fresh('milestones'),
            'update' => $update->fresh(),
            'recorded' => $recorded->fresh(['progressUpdate', 'milestoneProgress']),
        ];
    }

    /** @param array<string, mixed> $fixture */
    private function progressPayload(array $fixture, int $sequence): array
    {
        $start = today()->addDays(($sequence - 1) * 10);
        $end = $start->addDays(5);

        return [
            'lockVersion' => $fixture['case']->fresh()->lock_version,
            'reportingPeriodStart' => $start->toDateString(),
            'reportingPeriodEnd' => $end->toDateString(),
            'accomplishmentSummary' => 'Continued management-reported progress.',
            'managementDeclaration' => 'Management reporting only.',
            'milestoneProgress' => $fixture['accepted']->milestones->values()->map(
                fn ($milestone, int $index): array => [
                    'actionPlanMilestoneId' => $milestone->id,
                    'managementReportedStatusCode' => 'IN_PROGRESS',
                    'managementReportedPercentage' => 50,
                    'accomplishmentDescription' => 'Continued work.',
                    'noEvidenceExplanation' => 'Evidence will follow.',
                    'displayOrder' => $index + 1,
                ],
            )->all(),
        ];
    }

    private function user(string $username): User
    {
        return User::query()
            ->with(['office', 'role.permissions', 'roles.permissions'])
            ->where('username', $username)
            ->firstOrFail();
    }
}
