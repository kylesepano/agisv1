<?php

namespace Tests\Feature;

use App\Models\AuditEngagement;
use App\Models\AuditEngagementOrder;
use App\Models\AuditEngagementOrderVersion;
use App\Models\AuditEngagementPlan;
use App\Models\AuditEngagementPlanVersion;
use App\Models\AuditEvidence;
use App\Models\AuditFinding;
use App\Models\AuditIssue;
use App\Models\AuditorRejoinder;
use App\Models\AuditProgram;
use App\Models\AuditProgramProcedure;
use App\Models\AuditRecommendation;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\EngagementEvent;
use App\Models\EngagementTeam;
use App\Models\EngagementTeamHistory;
use App\Models\ExitConference;
use App\Models\ExitConferenceParticipant;
use App\Models\IapPlanEngagement;
use App\Models\ManagementResponse;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\ReportRecipient;
use App\Models\User;
use App\Models\WorkingPaper;
use App\Models\WorkingPaperVersion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AemsFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_aems_foundation_tables_exist(): void
    {
        foreach ([
            'audit_engagements',
            'audit_engagement_offices',
            'audit_engagement_audit_areas',
            'audit_engagement_audit_focuses',
            'engagement_teams',
            'engagement_team_history',
            'audit_engagement_orders',
            'audit_engagement_order_versions',
            'audit_engagement_plans',
            'audit_engagement_plan_versions',
            'audit_programs',
            'audit_program_procedures',
            'working_papers',
            'working_paper_versions',
            'audit_evidence',
            'working_paper_evidence',
            'working_paper_version_evidence',
            'audit_issues',
            'audit_issue_working_paper',
            'audit_issue_evidence',
            'audit_findings',
            'audit_finding_working_paper',
            'audit_finding_evidence',
            'audit_recommendations',
            'management_responses',
            'auditor_rejoinders',
            'aems_dialogue_attachments',
            'entry_conferences',
            'entry_conference_participants',
            'entry_conference_matters',
            'entry_conference_agreements',
            'entry_conference_acknowledgements',
            'entry_conference_attachments',
            'exit_conferences',
            'exit_conference_participants',
            'exit_conference_findings',
            'exit_conference_attachments',
            'exit_conference_acknowledgements',
            'audit_reports',
            'audit_report_versions',
            'audit_report_findings',
            'audit_report_review_comments',
            'cms_recommendations',
            'cms_recommendation_cases',
            'cms_recommendation_assignments',
            'cms_recommendation_events',
            'report_recipients',
            'engagement_events',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} must exist.");
        }
    }

    public function test_aems_models_preserve_the_complete_engagement_record_graph(): void
    {
        $auditor = $this->user('auditor');
        $management = $this->user('departmenthead');
        $auditee = $this->user('auditee');
        $source = IapPlanEngagement::query()
            ->with(['offices', 'auditAreas', 'auditFocuses'])
            ->firstOrFail();
        $office = $source->offices->first() ?? Office::query()->firstOrFail();
        $area = $source->auditAreas->first();
        $focus = $source->auditFocuses->first();

        $engagement = AuditEngagement::query()->create([
            'engagement_code' => 'AEMS-2027-001',
            'title' => 'Procurement and Supply Management Audit',
            'source_type' => 'PLANNED',
            'iap_plan_engagement_id' => $source->id,
            'source_snapshot' => [
                'iapEngagementCode' => $source->engagement_code,
                'riskLevelCode' => $source->source_risk_level_code,
            ],
            'background' => 'Approved risk-based engagement imported from IAP.',
            'objectives' => 'Assess procurement compliance and internal controls.',
            'scope' => 'Procurement planning through receipt and payment.',
            'planned_start_date' => '2027-02-01',
            'planned_end_date' => '2027-03-15',
            'expected_report_date' => '2027-04-15',
            'planned_person_days' => 20,
            'status' => 'DRAFT',
            'created_by' => $management->id,
            'updated_by' => $management->id,
        ]);
        $engagement->offices()->attach($office->id, ['is_primary' => true]);
        if ($area) {
            $engagement->auditAreas()->attach($area->id);
        }
        if ($focus) {
            $engagement->auditFocuses()->attach($focus->id);
        }

        $teamMember = EngagementTeam::query()->create([
            'audit_engagement_id' => $engagement->id,
            'user_id' => $auditor->id,
            'assignment_role_code' => 'TEAM_LEADER',
            'planned_person_days' => 20,
            'assigned_from' => '2027-02-01',
            'assigned_until' => '2027-03-15',
            'assigned_by' => $management->id,
            'is_active' => true,
        ]);
        EngagementTeamHistory::query()->create([
            'audit_engagement_id' => $engagement->id,
            'engagement_team_id' => $teamMember->id,
            'action' => 'ASSIGN',
            'actor_id' => $management->id,
            'reason' => 'Initial engagement team assignment.',
            'new_values' => ['userId' => $auditor->id, 'role' => 'TEAM_LEADER'],
        ]);

        $order = AuditEngagementOrder::query()->create([
            'audit_engagement_id' => $engagement->id,
            'order_code' => 'AEO-2027-001',
            'status' => 'DRAFT',
            'current_version_number' => 1,
            'prepared_by' => $auditor->id,
        ]);
        AuditEngagementOrderVersion::query()->create([
            'audit_engagement_order_id' => $order->id,
            'version_number' => 1,
            'authority' => 'Authority of the City Internal Audit Services.',
            'objectives' => $engagement->objectives,
            'scope' => $engagement->scope,
            'effectivity_date' => '2027-02-01',
            'planned_start_date' => '2027-02-01',
            'planned_end_date' => '2027-03-15',
            'team_snapshot' => [['userId' => $auditor->id, 'role' => 'TEAM_LEADER']],
            'created_by' => $auditor->id,
        ]);

        $engagementPlan = AuditEngagementPlan::query()->create([
            'audit_engagement_id' => $engagement->id,
            'plan_code' => 'AEP-2027-001',
            'status' => 'DRAFT',
            'current_version_number' => 1,
            'prepared_by' => $auditor->id,
        ]);
        AuditEngagementPlanVersion::query()->create([
            'audit_engagement_plan_id' => $engagementPlan->id,
            'version_number' => 1,
            'objectives' => $engagement->objectives,
            'scope' => $engagement->scope,
            'methodology' => 'Interviews, walkthroughs, sampling, and document review.',
            'audit_criteria' => 'Applicable procurement laws and internal procedures.',
            'planned_start_date' => '2027-02-01',
            'planned_end_date' => '2027-03-15',
            'expected_report_date' => '2027-04-15',
            'planned_person_days' => 20,
            'resource_requirements' => ['auditors' => 1, 'personDays' => 20],
            'created_by' => $auditor->id,
        ]);

        $program = AuditProgram::query()->create([
            'audit_engagement_id' => $engagement->id,
            'audit_engagement_plan_id' => $engagementPlan->id,
            'program_code' => 'AP-PROC-001',
            'title' => 'Procurement Compliance Audit Program',
            'objective' => 'Determine whether procurement transactions comply with requirements.',
            'status' => 'DRAFT',
            'prepared_by' => $auditor->id,
        ]);
        $procedure = AuditProgramProcedure::query()->create([
            'audit_program_id' => $program->id,
            'procedure_code' => 'PROC-01',
            'sequence_number' => 1,
            'objective' => 'Test purchase transactions.',
            'procedure_description' => 'Select and inspect a representative transaction sample.',
            'expected_evidence' => 'APP, purchase requests, quotations, PO, inspection, and vouchers.',
            'assigned_to' => $auditor->id,
            'target_date' => '2027-02-28',
            'status' => 'NOT_STARTED',
        ]);

        $workingPaper = WorkingPaper::query()->create([
            'audit_engagement_id' => $engagement->id,
            'audit_program_procedure_id' => $procedure->id,
            'working_paper_code' => 'WP-PROC-01',
            'title' => 'Procurement Transaction Sample',
            'status' => 'DRAFT',
            'current_version_number' => 1,
            'prepared_by' => $auditor->id,
            'reviewer_id' => $management->id,
        ]);
        $workingPaperVersion = WorkingPaperVersion::query()->create([
            'working_paper_id' => $workingPaper->id,
            'version_number' => 1,
            'objective' => 'Determine compliance of sampled transactions.',
            'procedure_performed' => 'Inspected the complete supporting-document trail.',
            'population_description' => 'All procurement transactions for the selected period.',
            'sample_description' => 'Twenty risk-weighted transactions.',
            'result' => 'One transaction lacked timely inspection documentation.',
            'conclusion' => 'The exception requires review as a potential issue.',
            'created_by' => $auditor->id,
        ]);

        [$document, $documentVersion] = $this->evidenceDocument($auditor);
        $evidence = AuditEvidence::query()->create([
            'evidence_family_uuid' => (string) Str::uuid(),
            'version_number' => 1,
            'is_current_revision' => true,
            'audit_engagement_id' => $engagement->id,
            'evidence_code' => 'EVD-PROC-001',
            'title' => 'Sampled procurement transaction documents',
            'source_description' => 'Records provided by the auditee office.',
            'date_obtained' => '2027-02-15',
            'custodian_name' => 'Procurement Records Custodian',
            'custodian_office_id' => $office->id,
            'document_version_id' => $documentVersion->id,
            'checksum_sha256' => $documentVersion->checksum_sha256,
            'status' => 'VERIFIED',
            'uploaded_by' => $auditor->id,
            'verified_by' => $management->id,
            'verified_at' => now(),
        ]);
        $workingPaper->evidence()->attach($evidence->id);
        $workingPaperVersion->evidence()->attach($evidence->id);

        $issue = AuditIssue::query()->create([
            'audit_engagement_id' => $engagement->id,
            'issue_code' => 'ISS-PROC-001',
            'title' => 'Delayed inspection documentation',
            'exception_description' => 'Inspection evidence was prepared after payment processing.',
            'responsible_office_id' => $office->id,
            'status' => 'VALIDATED',
            'raised_by' => $auditor->id,
            'reviewer_id' => $management->id,
            'validated_by' => $management->id,
            'validated_at' => now(),
        ]);
        $issue->workingPaperVersions()->attach($workingPaperVersion->id);
        $issue->evidence()->attach($evidence->id);

        $finding = AuditFinding::query()->create([
            'finding_family_uuid' => (string) Str::uuid(),
            'revision_number' => 0,
            'is_current_revision' => true,
            'audit_engagement_id' => $engagement->id,
            'source_issue_id' => $issue->id,
            'finding_code' => 'FND-PROC-001',
            'title' => 'Untimely inspection documentation',
            'criteria' => 'Inspection and acceptance must precede payment processing.',
            'condition' => 'One sampled transaction was documented after payment.',
            'cause' => 'The inspection checklist was not routed on time.',
            'effect' => 'Payment may occur without timely confirmation of delivery.',
            'responsible_office_id' => $office->id,
            'status' => 'DRAFT',
            'authored_by' => $auditor->id,
            'reviewer_id' => $management->id,
        ]);
        $finding->workingPaperVersions()->attach($workingPaperVersion->id);
        $finding->evidence()->attach($evidence->id);

        $recommendation = AuditRecommendation::query()->create([
            'audit_finding_id' => $finding->id,
            'recommendation_code' => 'REC-PROC-001',
            'recommendation' => 'Require inspection documents before voucher approval.',
            'responsible_office_id' => $office->id,
            'target_implementation_date' => '2027-06-30',
            'status' => 'TRANSFERRED',
            'created_by' => $auditor->id,
            'finalized_at' => now(),
            'finalized_by' => $management->id,
            'cms_transfer_key' => (string) Str::uuid(),
            'transferred_to_cms_at' => now(),
            'transferred_to_cms_by' => $management->id,
        ]);

        $response = ManagementResponse::query()->create([
            'response_family_uuid' => (string) Str::uuid(),
            'version_number' => 1,
            'is_current_revision' => true,
            'audit_finding_id' => $finding->id,
            'response_code' => 'MR-PROC-001',
            'agreement_position' => 'AGREE',
            'management_comment' => 'The office agrees with the finding.',
            'proposed_action' => 'Implement a voucher checklist control.',
            'responsible_office_id' => $office->id,
            'responsible_user_id' => $auditee->id,
            'proposed_target_date' => '2027-06-30',
            'status' => 'SUBMITTED',
            'authored_by' => $auditee->id,
            'submitted_at' => now(),
        ]);
        AuditorRejoinder::query()->create([
            'management_response_id' => $response->id,
            'version_number' => 1,
            'disposition' => 'ACCEPT',
            'rejoinder' => 'The proposed control and target date are acceptable.',
            'status' => 'DIALOGUE_FINALIZED',
            'authored_by' => $auditor->id,
            'finalized_at' => now(),
            'finalized_by' => $management->id,
        ]);

        $conference = ExitConference::query()->create([
            'audit_engagement_id' => $engagement->id,
            'conference_code' => 'EXIT-2027-001',
            'scheduled_start_at' => '2027-04-01 09:00:00',
            'scheduled_end_at' => '2027-04-01 11:00:00',
            'venue' => 'CIAS Conference Room',
            'agenda' => 'Discuss validated findings and agreed corrective actions.',
            'status' => 'SCHEDULED',
            'created_by' => $management->id,
        ]);
        ExitConferenceParticipant::query()->create([
            'exit_conference_id' => $conference->id,
            'user_id' => $auditee->id,
            'office_id' => $office->id,
            'participant_role' => 'AUDITEE_REPRESENTATIVE',
            'attendance_status' => 'INVITED',
        ]);

        $report = AuditReport::query()->create([
            'audit_engagement_id' => $engagement->id,
            'report_code' => 'AR-2027-001',
            'title' => 'Procurement and Supply Management Audit Report',
            'report_stage' => 'DRAFT_REPORT',
            'status' => 'DRAFT',
            'current_version_number' => 1,
            'prepared_by' => $auditor->id,
        ]);
        $reportVersion = AuditReportVersion::query()->create([
            'audit_report_id' => $report->id,
            'version_number' => 1,
            'report_stage' => 'DRAFT_REPORT',
            'content_snapshot' => [
                'executiveSummary' => 'One procurement control exception was identified.',
                'findingCodes' => [$finding->finding_code],
            ],
            'created_by' => $auditor->id,
        ]);
        $reportVersion->findings()->attach($finding->id, [
            'sequence_number' => 1,
            'is_included' => true,
        ]);
        ReportRecipient::query()->create([
            'audit_report_version_id' => $reportVersion->id,
            'user_id' => $auditee->id,
            'office_id' => $office->id,
            'recipient_type' => 'AUDITEE',
            'delivery_status' => 'PENDING',
        ]);

        EngagementEvent::query()->create([
            'audit_engagement_id' => $engagement->id,
            'subject_type' => 'ENGAGEMENT',
            'subject_id' => $engagement->id,
            'subject_code' => $engagement->engagement_code,
            'action' => 'CREATE',
            'from_status' => null,
            'to_status' => 'DRAFT',
            'actor_id' => $management->id,
            'actor_role_code' => 'cias_management',
            'new_values' => ['status' => 'DRAFT'],
            'record_lock_version' => 1,
        ]);

        $loaded = $engagement->fresh([
            'sourcePlanEngagement',
            'offices',
            'auditAreas',
            'auditFocuses',
            'teamMembers.history',
            'engagementOrder.versions',
            'engagementPlan.versions',
            'programs.procedures.workingPapers.versions',
            'workingPapers.evidence.documentVersion',
            'workingPapers.versions.evidence.documentVersion',
            'issues.workingPaperVersions',
            'issues.evidence',
            'findings.recommendations',
            'findings.managementResponses.rejoinders',
            'findings.workingPaperVersions',
            'findings.evidence',
            'exitConferences.participants',
            'reports.versions.findings',
            'reports.versions.recipients',
            'events',
        ]);

        $this->assertSame($source->id, $loaded->sourcePlanEngagement->id);
        $this->assertSame($office->id, $loaded->offices->first()->id);
        $this->assertCount(1, $loaded->teamMembers);
        $this->assertCount(1, $loaded->engagementOrder->versions);
        $this->assertCount(1, $loaded->engagementPlan->versions);
        $this->assertCount(1, $loaded->programs->first()->procedures);
        $this->assertCount(1, $loaded->workingPapers->first()->evidence);
        $this->assertCount(
            1,
            $loaded->workingPapers->first()->versions->first()->evidence,
        );
        $this->assertCount(1, $loaded->issues->first()->workingPaperVersions);
        $this->assertSame($issue->id, $loaded->findings->first()->source_issue_id);
        $this->assertSame($recommendation->cms_transfer_key, $loaded->findings->first()
            ->recommendations->first()->cms_transfer_key);
        $this->assertCount(1, $loaded->findings->first()->managementResponses->first()->rejoinders);
        $this->assertCount(1, $loaded->exitConferences->first()->participants);
        $this->assertCount(1, $loaded->reports->first()->versions->first()->findings);
        $this->assertCount(1, $loaded->reports->first()->versions->first()->recipients);
        $this->assertCount(1, $loaded->events);
        $this->assertSame($document->id, $documentVersion->document_id);
    }

    public function test_one_iap_item_cannot_create_duplicate_active_aems_engagements(): void
    {
        $management = $this->user('departmenthead');
        $source = IapPlanEngagement::query()->firstOrFail();

        AuditEngagement::query()->create($this->engagementAttributes(
            'AEMS-2027-010',
            $source->id,
            $management->id,
        ));

        $this->expectException(QueryException::class);
        AuditEngagement::query()->create($this->engagementAttributes(
            'AEMS-2027-011',
            $source->id,
            $management->id,
        ));
    }

    /** @return array<string, mixed> */
    private function engagementAttributes(string $code, int $sourceId, int $creatorId): array
    {
        return [
            'engagement_code' => $code,
            'title' => 'Planned audit engagement',
            'source_type' => 'PLANNED',
            'iap_plan_engagement_id' => $sourceId,
            'source_snapshot' => ['sourceId' => $sourceId],
            'objectives' => 'Perform the approved audit.',
            'scope' => 'Approved IAP scope.',
            'status' => 'DRAFT',
            'created_by' => $creatorId,
            'updated_by' => $creatorId,
        ];
    }

    /** @return array{Document, DocumentVersion} */
    private function evidenceDocument(User $uploader): array
    {
        $document = Document::query()->create([
            'document_code' => 'DOC-AEM-EVD-001',
            'document_type_id' => $this->list('DOCUMENT_TYPE')->items()->firstOrFail()->id,
            'title' => 'AEMS Evidence File',
            'description' => 'Evidence used by the AEMS foundation test.',
            'owner_module' => 'aem',
            'library_visible' => false,
            'original_file_name' => 'aems-evidence.pdf',
            'storage_path' => 'documents/aems-evidence.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 2048,
            'checksum_sha256' => str_repeat('e', 64),
            'uploaded_by' => $uploader->id,
            'updated_by' => $uploader->id,
            'is_active' => true,
        ]);
        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'version_label' => '1.0',
            'change_summary' => 'Initial evidence version.',
            'original_file_name' => 'aems-evidence.pdf',
            'storage_path' => 'documents/versions/aems-evidence-v1.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 2048,
            'checksum_sha256' => str_repeat('e', 64),
            'uploaded_by' => $uploader->id,
        ]);
        $document->update(['current_version_id' => $version->id]);

        return [$document, $version];
    }

    private function list(string $code): MasterList
    {
        return MasterList::query()->where('code', $code)->firstOrFail();
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
