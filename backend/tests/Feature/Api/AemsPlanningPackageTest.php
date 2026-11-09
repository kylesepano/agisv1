<?php

namespace Tests\Feature\Api;

use App\Models\AemsPlanningPackageVersion;
use App\Models\AemsRiskMatrix;
use App\Models\AemsRiskMatrixItem;
use App\Models\ArmisResourceProfile;
use App\Models\AuditEngagement;
use App\Models\AuditEngagementPlan;
use App\Models\AuditProgram;
use App\Models\AuditProgramProcedure;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AemsPlanningPackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_package_preserves_lineage_and_duplicate_creation_is_rejected(): void
    {
        [$prepared, , , $engagement] = $this->fixture();
        Sanctum::actingAs($prepared);
        $payload = ['preliminarySurvey' => ['purpose' => 'Understand the process.']];
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package", $payload)
            ->assertCreated()->assertJsonPath('data.package.status', 'DRAFT');
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('package');
        $this->assertDatabaseHas('aems_planning_package_versions', ['version_number' => 1]);
        $this->assertDatabaseHas('aems_planning_packages', ['audit_engagement_id' => $engagement->id, 'source_type' => 'PLANNED']);
    }

    public function test_risk_matrix_numeric_fields_return_actionable_validation_errors(): void
    {
        [$prepared, , , $engagement, $procedure] = $this->fixture();
        Sanctum::actingAs($prepared);
        $payload = $this->completePayload($procedure->id);
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package", $payload)
            ->assertCreated();
        $package = $engagement->planningPackage()->firstOrFail();
        $payload['riskItems'][0]['inherentLikelihood'] = 'test';

        $this->putJson("/api/aems/engagements/{$engagement->id}/planning-package/{$package->id}", [
            ...$payload,
            'lockVersion' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('riskItems.0.inherentLikelihood')
            ->assertSeeText('The riskItems.0.inherentLikelihood field must be a number.');
    }

    public function test_risk_item_control_effectiveness_and_residual_rating_must_use_active_master_list_values(): void
    {
        [$prepared, , , $engagement, $procedure] = $this->fixture();
        Sanctum::actingAs($prepared);
        $payload = $this->completePayload($procedure->id);
        $payload['riskItems'][0] = [
            ...$payload['riskItems'][0],
            'controlEffectiveness' => 'Narrative assessment',
            'residualRating' => 'Unlisted rating',
        ];

        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'riskItems.0.controlEffectiveness',
                'riskItems.0.residualRating',
            ]);
    }

    public function test_risk_item_details_survive_saving_a_new_planning_package_version(): void
    {
        [$prepared, , , $engagement, $procedure] = $this->fixture();
        Sanctum::actingAs($prepared);
        $payload = $this->completePayload($procedure->id);
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package", $payload)
            ->assertCreated();
        $package = $engagement->planningPackage()->firstOrFail();
        $firstVersionFlowId = AemsPlanningPackageVersion::query()
            ->where('planning_package_id', $package->id)
            ->firstOrFail()
            ->processFlows()
            ->value('id');
        $payload['processFlows'][0]['id'] = $firstVersionFlowId;
        $payload['riskItems'][0] = [
            ...$payload['riskItems'][0],
            'riskCategory' => 'Financial reporting',
            'inherentLikelihood' => 3,
            'inherentImpact' => 4,
            'inherentScore' => 12,
            'controlDescription' => 'Supervisory review of assessment calculations.',
            'controlEffectiveness' => 'PARTIALLY_EFFECTIVE',
            'residualLikelihood' => 2,
            'residualImpact' => 3,
            'residualScore' => 6,
            'residualRating' => 'MODERATE',
            'riskResponse' => 'Mitigate',
            'processFlowId' => $firstVersionFlowId,
            'processName' => 'Permit assessment',
            'riskArea' => 'Assessment calculation',
            'plannedAuditApproach' => 'Recalculate a sample of assessments.',
            'criteria' => 'Applicable revenue ordinance and assessment policy.',
            'responseRationale' => 'Residual risk requires additional substantive testing.',
            'sourceReference' => 'BPLD-REV-2026-01',
        ];

        $this->putJson("/api/aems/engagements/{$engagement->id}/planning-package/{$package->id}", [
            ...$payload,
            'lockVersion' => 1,
        ])->assertOk();

        $item = AemsRiskMatrixItem::query()
            ->whereHas('matrix.version', fn ($query) => $query
                ->where('planning_package_id', $package->id)
                ->where('version_number', 2))
            ->firstOrFail();

        $this->assertSame('Financial reporting', $item->risk_category);
        $this->assertSame('Supervisory review of assessment calculations.', $item->control_description);
        $this->assertSame('PARTIALLY_EFFECTIVE', $item->control_effectiveness);
        $this->assertSame('MODERATE', $item->residual_rating);
        $this->assertSame('Mitigate', $item->risk_response);
        $this->assertSame('Permit assessment', $item->process_name);
        $this->assertSame('Assessment calculation', $item->risk_area);
        $this->assertSame('Recalculate a sample of assessments.', $item->planned_audit_approach);
        $this->assertSame('Applicable revenue ordinance and assessment policy.', $item->criteria);
        $this->assertSame('Residual risk requires additional substantive testing.', $item->response_rationale);
        $this->assertSame('BPLD-REV-2026-01', $item->source_reference);
        $this->assertSame(
            AemsPlanningPackageVersion::query()
                ->where('planning_package_id', $package->id)
                ->where('version_number', 2)
                ->firstOrFail()
                ->processFlows()
                ->value('id'),
            $item->process_flow_id,
        );
    }

    public function test_readiness_review_approval_and_immutable_revision_workflow(): void
    {
        [$prepared, $reviewer, $approver, $engagement, $procedure] = $this->fixture();
        Sanctum::actingAs($prepared);
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package", $this->completePayload($procedure->id))
            ->assertCreated();
        $package = $engagement->planningPackage()->firstOrFail();
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package/{$package->id}/transition", ['action' => 'SUBMIT', 'lockVersion' => 1])
            ->assertOk()->assertJsonPath('data.package.status', 'PENDING_REVIEW');
        Sanctum::actingAs($reviewer);
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package/{$package->id}/transition", ['action' => 'REVIEW', 'lockVersion' => 2, 'comment' => 'Planning basis independently assessed.'])
            ->assertOk();
        Sanctum::actingAs($approver);
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package/{$package->id}/transition", ['action' => 'APPROVE', 'lockVersion' => 3])
            ->assertOk()->assertJsonPath('data.package.status', 'APPROVED');
        $package->refresh();
        $this->assertSame(1, $package->approved_version_number);
        Sanctum::actingAs($reviewer);
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package/{$package->id}/revise", ['lockVersion' => 4, 'reason' => 'Update the process walkthrough.'])
            ->assertOk()->assertJsonPath('data.package.status', 'DRAFT');
        $package->refresh();
        $this->assertSame(2, $package->current_version_number);
        $this->assertSame(1, $package->approved_version_number);
        $this->assertCount(2, $package->versions()->get());
        $this->getJson("/api/aems/engagements/{$engagement->id}/planning-package")
            ->assertOk()
            ->assertJsonCount(2, 'data.package.versions')
            ->assertJsonPath('data.package.versions.0.versionNumber', 1)
            ->assertJsonPath('data.package.versions.1.versionNumber', 2);
        $this->expectException(\LogicException::class);
        AemsPlanningPackageVersion::query()->firstOrFail()->update(['change_reason' => 'tamper']);
    }

    public function test_sole_cias_head_may_review_and_approve_own_planning_package(): void
    {
        [$prepared, $reviewer, $approver, $engagement, $procedure] = $this->fixture();
        $prepared->update(['is_office_head' => true]);
        User::query()
            ->whereIn('id', [$reviewer->id, $approver->id])
            ->update(['is_active' => false]);

        Sanctum::actingAs($prepared);
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package", $this->completePayload($procedure->id))
            ->assertCreated();
        $package = $engagement->planningPackage()->firstOrFail();
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package/{$package->id}/transition", [
            'action' => 'SUBMIT',
            'lockVersion' => 1,
        ])->assertOk();
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package/{$package->id}/transition", [
            'action' => 'REVIEW',
            'lockVersion' => 2,
            'comment' => 'Sole CIAS Head planning review recorded.',
        ])->assertOk();
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package/{$package->id}/transition", [
            'action' => 'APPROVE',
            'lockVersion' => 3,
        ])->assertOk()->assertJsonPath('data.package.status', 'APPROVED');
    }

    public function test_g3_strict_fieldwork_conformance_is_reported_and_multiple_matrices_are_supported(): void
    {
        [$prepared, , , $engagement, $procedure] = $this->fixture();
        Sanctum::actingAs($prepared);
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package", $this->completePayload($procedure->id))
            ->assertCreated();
        $workspace = $this->getJson("/api/aems/engagements/{$engagement->id}/planning-package")
            ->assertOk()->json('data');
        $this->assertFalse($workspace['readiness']['fieldworkReady']);
        $this->assertContains('structuredProcessFlows', array_column($workspace['readiness']['conformanceChecks'], 'key'));
        $version = AemsPlanningPackageVersion::query()->firstOrFail();
        AemsRiskMatrix::query()->create([
            'planning_package_version_id' => $version->id,
            'matrix_code' => 'RM-2',
            'title' => 'Second authorized matrix',
            'status' => 'DRAFT',
        ]);
        $this->assertDatabaseCount('aems_risk_matrices', 2);
    }

    public function test_risk_matrix_register_narrative_fields_are_persisted_on_new_versions(): void
    {
        [$prepared, , , $engagement, $procedure] = $this->fixture();
        Sanctum::actingAs($prepared);
        $payload = $this->completePayload($procedure->id);
        $payload['riskMatrices'] = [
            ['code' => 'RM-1', 'title' => 'Planning risk matrix', 'methodology' => 'Initial methodology', 'riskAppetite' => 'Moderate', 'overallConclusion' => 'Initial conclusion', 'riskItems' => $payload['riskItems']],
            ['code' => 'RM-2', 'title' => 'Second matrix', 'methodology' => 'Second methodology', 'riskAppetite' => 'Low', 'overallConclusion' => 'Second conclusion', 'allAuditFocuses' => true, 'riskItems' => []],
        ];
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package", $payload)->assertCreated();
        $package = $engagement->planningPackage()->firstOrFail();

        $payload['riskMatrices'][0]['methodology'] = 'Updated methodology';
        $payload['riskMatrices'][0]['riskAppetite'] = 'High';
        $payload['riskMatrices'][0]['overallConclusion'] = 'Updated conclusion';
        $this->putJson("/api/aems/engagements/{$engagement->id}/planning-package/{$package->id}", [...$payload, 'lockVersion' => 1])->assertOk();

        $this->assertDatabaseHas('aems_risk_matrices', ['planning_package_version_id' => 2, 'matrix_code' => 'RM-1', 'methodology' => 'Updated methodology', 'risk_appetite' => 'High', 'overall_conclusion' => 'Updated conclusion']);
        $this->assertDatabaseHas('aems_risk_matrices', ['planning_package_version_id' => 2, 'matrix_code' => 'RM-2', 'methodology' => 'Second methodology', 'risk_appetite' => 'Low', 'overall_conclusion' => 'Second conclusion', 'all_audit_focuses' => true]);

        unset($payload['riskMatrices'][1]);
        $payload['riskMatrices'] = array_values($payload['riskMatrices']);
        $this->putJson("/api/aems/engagements/{$engagement->id}/planning-package/{$package->id}", [...$payload, 'lockVersion' => 2])->assertOk();

        $this->assertDatabaseCount('aems_risk_matrices', 5);
        $this->assertDatabaseHas('aems_risk_matrices', ['planning_package_version_id' => 3, 'matrix_code' => 'RM-1']);
        $this->assertDatabaseMissing('aems_risk_matrices', ['planning_package_version_id' => 3, 'matrix_code' => 'RM-2']);
    }

    public function test_reference_survey_sections_round_trip_and_preserve_existing_planning_graph(): void
    {
        [$prepared, , , $engagement, $procedure] = $this->fixture();
        Sanctum::actingAs($prepared);
        $this->postJson("/api/aems/engagements/{$engagement->id}/planning-package", $this->completePayload($procedure->id))->assertCreated();
        $url = "/api/aems/engagements/{$engagement->id}/preliminary-survey";
        $this->putJson($url, ['section' => 'meeting', 'lockVersion' => 1, 'data' => ['venue' => 'CIAS Office', 'proposedAt' => '2026-11-10T09:00', 'mattersDiscussed' => 'Discussed records.']])->assertOk()->assertJsonPath('data.lockVersion', 2)->assertJsonPath('data.survey.meeting.venue', 'CIAS Office');
        $this->putJson($url, ['section' => 'auditee', 'lockVersion' => 2, 'data' => ['mandate' => 'Office mandate', 'annualBudget' => 5000, 'programs' => [['title' => 'Service improvement', 'budget' => 1200, 'status' => 'Ongoing']]]])->assertOk()->assertJsonPath('data.survey.meeting.venue', 'CIAS Office')->assertJsonPath('data.survey.auditee.programs.0.title', 'Service improvement');
        $this->putJson($url, ['section' => 'ppsp', 'lockVersion' => 3, 'data' => ['purpose' => 'Deliver services', 'steps' => [['activity' => 'Review applications']], 'sources' => [['documentPersonMethod' => 'Interview']], 'questions' => [['informationGap' => 'Missing log']], 'risks' => [['riskDescription' => 'Late processing', 'likelihood' => 3, 'impact' => 4, 'riskRating' => 100]]]])->assertOk()->assertJsonPath('data.survey.ppsp.risks.0.riskRating', 12);
        $this->putJson($url, ['section' => 'ics', 'lockVersion' => 4, 'data' => ['assessments' => [['principleId' => 16, 'preliminaryView' => 'More Information Needed', 'basisSource' => 'Interview', 'nextAction' => 'Add to DRL']]]])->assertOk()->assertJsonPath('data.survey.ics.assessments.0.principleId', 16);
        $package = $this->getJson("/api/aems/engagements/{$engagement->id}/planning-package")->assertOk()->json('data.package');
        $this->assertSame('Understand the process.', $package['latestVersion']['preliminarySurvey']['purpose']);
        $this->assertSame('OBJ-1', $package['latestVersion']['objectives'][0]['code']);
        $risk = $package['latestVersion']['riskMatrices'][0]['items'][0];
        $this->assertSame('OBJ-1', $risk['objectives'][0]['code']);
        $this->assertSame($procedure->id, $risk['procedures'][0]['id']);
        $this->assertSame('WP-01', $risk['workingPapers'][0]['reference']);
        $this->assertCount(5, $package['versions']);
        $this->assertArrayNotHasKey('workspace', $package['versions'][0]['preliminarySurvey']);
    }

    public function test_reference_survey_rejects_stale_saves_invalid_data_and_locked_stages(): void
    {
        [$prepared, , , $engagement] = $this->fixture();
        Sanctum::actingAs($prepared);
        $url = "/api/aems/engagements/{$engagement->id}/preliminary-survey";
        $payload = ['section' => 'meeting', 'lockVersion' => null, 'data' => ['venue' => 'Conference room']];
        $this->putJson($url, $payload)->assertOk()->assertJsonPath('data.lockVersion', 1);
        $this->putJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('lockVersion');
        $this->putJson($url, ['section' => 'ppsp', 'lockVersion' => 1, 'data' => ['risks' => [['likelihood' => 9]]]])->assertUnprocessable()->assertJsonValidationErrors('data.risks.0.likelihood');
        $this->putJson($url, ['section' => 'ics', 'lockVersion' => 1, 'data' => ['assessments' => [['principleId' => 17]]]])->assertUnprocessable()->assertJsonValidationErrors('data.assessments.0.principleId');
        $engagement->planningPackage()->update(['status' => 'APPROVED']);
        $this->putJson($url, [...$payload, 'lockVersion' => 1])->assertUnprocessable()->assertJsonValidationErrors('status');
        $engagement->planningPackage()->update(['status' => 'DRAFT']);
        $engagement->update(['status' => 'FIELDWORK']);
        $this->putJson($url, [...$payload, 'lockVersion' => 1])->assertUnprocessable();
    }

    public function test_reference_survey_attachments_are_private_and_scoped_to_the_engagement(): void
    {
        Storage::fake('local');
        [$prepared, , , $engagement] = $this->fixture();
        Sanctum::actingAs($prepared);
        $url = "/api/aems/engagements/{$engagement->id}/preliminary-survey";
        $upload = $this->post($url.'/attachments', ['file' => UploadedFile::fake()->create('meeting.pdf', 20, 'application/pdf')])->assertCreated()->json('data');
        $this->putJson($url, ['section' => 'meeting', 'lockVersion' => null, 'data' => ['noticeDocumentVersionId' => $upload['id']]])->assertOk()->assertJsonPath('data.attachments.0.fileName', 'meeting.pdf');
        $this->get($url.'/attachments/'.$upload['id'])->assertOk()->assertDownload('meeting.pdf');
        [, , , $other] = $this->fixture();
        $this->get("/api/aems/engagements/{$other->id}/preliminary-survey/attachments/{$upload['id']}")->assertNotFound();
        $this->putJson("/api/aems/engagements/{$other->id}/preliminary-survey", ['section' => 'meeting', 'lockVersion' => null, 'data' => ['noticeDocumentVersionId' => $upload['id']]])->assertNotFound();
        $this->post($url.'/attachments', ['file' => UploadedFile::fake()->create('script.exe', 20)], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_structured_survey_satisfies_survey_readiness_without_legacy_narratives(): void
    {
        [$prepared, , , $engagement] = $this->fixture();
        Sanctum::actingAs($prepared);
        $sections = [
            'meeting' => ['actualDate' => '2026-11-09', 'mattersDiscussed' => 'Discussed requirements.'],
            'auditee' => ['mandate' => 'Office mandate', 'objectives' => 'Office objectives'],
            'ppsp' => ['purpose' => 'Service delivery', 'scope' => 'Entire office', 'steps' => [['activity' => 'Review']], 'sources' => [['documentPersonMethod' => 'Interview']], 'risks' => [['riskDescription' => 'Delay']]],
            'ics' => ['assessments' => array_map(fn ($id) => ['principleId' => $id, 'preliminaryView' => 'No Apparent Concern', 'basisSource' => 'Interview and records'], range(1, 16))],
        ];
        $lock = null;
        foreach ($sections as $section => $data) {
            $lock = $this->putJson("/api/aems/engagements/{$engagement->id}/preliminary-survey", ['section' => $section, 'lockVersion' => $lock, 'data' => $data])->assertOk()->json('data.lockVersion');
            $checks = $this->getJson("/api/aems/engagements/{$engagement->id}/planning-package")->assertOk()->json('data.readiness.checks');
            $this->assertSame($section === 'ics', collect($checks)->firstWhere('key', 'survey')['met']);
        }
    }

    public function test_preliminary_meeting_supports_active_armis_auditors_and_admin_staff_across_offices(): void
    {
        [$prepared, , , $engagement] = $this->fixture();
        Sanctum::actingAs($prepared);
        $office = Office::query()->where('code', 'CIAS')->firstOrFail();
        $otherOffice = Office::where('code', '!=', 'CIAS')->firstOrFail();
        $first = User::factory()->create(['office_id' => $otherOffice->id, 'is_active' => true]);
        $second = User::factory()->create(['office_id' => $otherOffice->id, 'is_active' => true]);
        $inactive = User::factory()->create(['office_id' => $office->id, 'is_active' => false]);
        $unregistered = User::factory()->create(['office_id' => $office->id, 'is_active' => true]);
        $inactiveProfile = User::factory()->create(['office_id' => $office->id, 'is_active' => true]);
        $deletedProfile = User::factory()->create(['office_id' => $office->id, 'is_active' => true]);
        $deletedUser = User::factory()->create(['office_id' => $office->id, 'is_active' => true]);
        foreach ([[$first, 'AUDIT_RESOURCE', 'ACTIVE'], [$second, 'SUPPORT', 'ACTIVE'], [$inactive, 'SPECIALIST', 'ACTIVE'], [$inactiveProfile, 'REVIEWER', 'INACTIVE'], [$deletedProfile, 'SUPPORT', 'ACTIVE'], [$deletedUser, 'AUDIT_RESOURCE', 'ACTIVE']] as [$person, $category, $status]) {
            ArmisResourceProfile::query()->create(['resource_code' => 'TEST-PARTICIPANT-'.$person->id, 'user_id' => $person->id, 'office_id' => $person->office_id, 'category' => $category, 'status' => $status]);
        }
        ArmisResourceProfile::query()->create(['resource_code' => 'TEST-DUPLICATE-'.$first->id, 'user_id' => $first->id, 'office_id' => $first->office_id, 'category' => 'SPECIALIST', 'status' => 'ACTIVE']);
        ArmisResourceProfile::query()->where('user_id', $deletedProfile->id)->delete();
        $deletedUser->delete();
        $url = "/api/aems/engagements/{$engagement->id}/preliminary-survey";
        $options = $this->getJson($url)->assertOk()->json('data.participantOptions');
        $this->assertContains($first->id, array_column($options, 'id'));
        $this->assertContains($second->id, array_column($options, 'id'));
        $this->assertSame(1, collect($options)->where('id', $first->id)->count());
        foreach ([$inactive, $unregistered, $inactiveProfile, $deletedProfile, $deletedUser] as $invalid) {
            $this->assertNotContains($invalid->id, array_column($options, 'id'));
        }
        $this->putJson($url, ['section' => 'meeting', 'lockVersion' => null, 'data' => ['ciasParticipantIds' => [$first->id, $second->id]]])->assertOk()
            ->assertJsonPath('data.survey.meeting.ciasParticipantIds', [$first->id, $second->id])
            ->assertJsonPath('data.survey.meeting.ciasParticipants', $first->name.', '.$second->name);
        foreach ([$inactive, $unregistered, $inactiveProfile, $deletedProfile, $deletedUser] as $invalid) {
            $this->putJson($url, ['section' => 'meeting', 'lockVersion' => 1, 'data' => ['ciasParticipantIds' => [$invalid->id]]])->assertUnprocessable()->assertJsonValidationErrors('data.ciasParticipantIds.0');
        }
        $this->putJson($url, ['section' => 'meeting', 'lockVersion' => 1, 'data' => ['ciasParticipantIds' => []]])->assertOk()->assertJsonPath('data.survey.meeting.ciasParticipants', '');
    }

    public function test_reference_survey_template_is_an_authenticated_word_document(): void
    {
        [$prepared, , , $engagement] = $this->fixture();
        Sanctum::actingAs($prepared);
        $this->get("/api/aems/engagements/{$engagement->id}/preliminary-survey/attachments/template")->assertOk()->assertDownload('Notice_of_Preliminary_Meeting_Template.docx');
    }

    /** @return array{User,User,User,AuditEngagement,AuditProgramProcedure} */
    private function fixture(): array
    {
        $management = User::query()->whereHas('roles', fn ($q) => $q->where('code', 'cias_management'))->firstOrFail();
        $office = Office::query()->firstOrFail();
        $users = collect([$management]);
        while ($users->count() < 3) {
            $users->push(User::factory()->create(['role_id' => $management->role_id, 'office_id' => $office->id]));
        }
        [$prepared, $reviewer, $approver] = $users->all();
        $engagement = AuditEngagement::query()->create(['engagement_code' => 'AEMS-PP-'.fake()->unique()->numerify('####'), 'title' => 'Planning package test', 'source_type' => 'PLANNED', 'iap_plan_engagement_id' => null, 'source_snapshot' => ['test' => true], 'objectives' => 'Test objective', 'scope' => 'Test scope', 'status' => 'ENGAGEMENT_PLANNING', 'created_by' => $prepared->id, 'updated_by' => $prepared->id, 'is_active' => true]);
        $plan = AuditEngagementPlan::query()->create(['audit_engagement_id' => $engagement->id, 'plan_code' => 'AEP-'.$engagement->engagement_code, 'status' => 'APPROVED', 'current_version_number' => 1, 'prepared_by' => $prepared->id, 'approved_by' => $reviewer->id, 'approved_at' => now(), 'is_active' => true]);
        $program = AuditProgram::query()->create(['audit_engagement_id' => $engagement->id, 'audit_engagement_plan_id' => $plan->id, 'program_code' => 'AP-'.$engagement->engagement_code, 'title' => 'Program', 'objective' => 'Program objective', 'status' => 'APPROVED', 'prepared_by' => $prepared->id, 'approved_by' => $reviewer->id, 'approved_at' => now(), 'is_current_revision' => true, 'is_active' => true]);
        $procedure = AuditProgramProcedure::query()->create(['audit_program_id' => $program->id, 'procedure_code' => 'P-01', 'objective' => 'Test objective', 'procedure_description' => 'Inspect records.', 'status' => 'NOT_STARTED']);

        return [$prepared, $reviewer, $approver, $engagement, $procedure];
    }

    private function completePayload(int $procedureId): array
    {
        return ['preliminarySurvey' => ['purpose' => 'Understand the process.', 'background' => 'Background reviewed.', 'informationSources' => 'Policies and interviews.', 'observations' => 'Walkthrough completed.', 'planningImplications' => 'Test controls and evidence.'], 'planningAttributes' => ['methodology' => 'Risk based'], 'objectives' => [['code' => 'OBJ-1', 'statement' => 'Assess control design.']], 'processFlows' => [['code' => 'FLOW-1', 'title' => 'Procure to pay', 'description' => 'Documented walkthrough.']], 'riskMatrix' => ['code' => 'RM-1', 'title' => 'Planning risk matrix'], 'riskItems' => [['riskCode' => 'R-1', 'riskStatement' => 'A control may fail.', 'objectiveCodes' => ['OBJ-1'], 'procedureIds' => [$procedureId], 'workingPapers' => [['reference' => 'WP-01']]]]];
    }
}
