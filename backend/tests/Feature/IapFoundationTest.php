<?php

namespace Tests\Feature;

use App\Models\AuditArea;
use App\Models\Document;
use App\Models\IapAttachment;
use App\Models\IapComment;
use App\Models\IapEngagementTeamMember;
use App\Models\IapPlanEngagement;
use App\Models\IapRiskAssessment;
use App\Models\IapRiskAssessmentScore;
use App\Models\IapWorkflowEvent;
use App\Models\InternalAuditPlan;
use App\Models\MasterList;
use App\Models\MasterListItem;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IapFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_iap_schema_reference_lists_and_permissions_are_seeded(): void
    {
        foreach ([
            'internal_audit_plans',
            'iap_risk_assessments',
            'iap_risk_assessment_scores',
            'iap_plan_engagements',
            'iap_engagement_offices',
            'iap_engagement_audit_areas',
            'iap_engagement_audit_focuses',
            'iap_engagement_team_members',
            'iap_workflow_events',
            'iap_comments',
            'iap_attachments',
            'iap_audit_universe_items',
            'iap_audit_universe_stakeholders',
            'iap_audit_universe_history',
            'strategic_internal_audit_plans',
            'siap_objectives',
            'siap_objective_audit_area',
            'siap_priorities',
            'siap_workflow_events',
            'iap_risk_periods',
            'iap_risk_period_criteria',
            'iap_universe_risk_assessments',
            'iap_universe_risk_scores',
            'iap_risk_evidence',
            'iap_risk_period_events',
            'iap_prioritization_runs',
            'iap_prioritization_items',
            'iap_prioritization_events',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} must exist.");
        }

        $this->assertSame(
            11,
            MasterList::query()->where('code', 'like', 'IAP_%')->count(),
        );
        $this->assertDatabaseMissing('master_lists', ['code' => 'IAP_PLAN_STATUS']);
        $this->assertDatabaseMissing('master_lists', ['code' => 'IAP_APPROVAL_ACTION']);
        $this->assertSame(
            10,
            $this->list('IAP_RISK_CRITERION')->items()->count(),
        );
        $this->assertDatabaseHas('master_list_items', [
            'code' => 'LEAD_AUDITOR',
            'label' => 'Lead Auditor',
        ]);
        $this->assertDatabaseHas('permissions', ['code' => 'iap.assess_risk']);
        $this->assertDatabaseHas('permissions', ['code' => 'iap.create_revision']);
        $this->assertDatabaseHas('permissions', ['code' => 'iap.restore']);
        $this->assertDatabaseHas('permissions', ['code' => 'iap.manage_universe']);

        $management = $this->user('departmenthead')->load('role.permissions');
        $auditor = $this->user('auditor')->load('role.permissions');
        $administrator = $this->user('agisadmin')->load('role.permissions');
        $readOnly = $this->user('mayor')->load('role.permissions');
        $auditee = $this->user('auditee')->load('role.permissions');

        foreach ([
            'iap.create',
            'iap.manage_universe',
            'iap.assess_risk',
            'iap.approve',
            'iap.activate',
            'iap.complete',
            'iap.create_revision',
            'iap.archive',
            'iap.restore',
            'iap.export',
        ] as $permission) {
            $this->assertTrue($management->hasPermission($permission));
        }

        $this->assertTrue($auditor->hasPermission('iap.assess_risk'));
        $this->assertTrue($auditor->hasPermission('iap.manage_engagements'));
        $this->assertFalse($auditor->hasPermission('iap.approve'));
        $this->assertTrue($administrator->hasPermission('iap.view'));
        $this->assertFalse($administrator->hasPermission('iap.update'));
        $this->assertTrue($readOnly->hasPermission('iap.view'));
        $this->assertFalse($readOnly->hasPermission('iap.update'));
        $this->assertFalse($auditee->hasPermission('iap.view'));
    }

    public function test_iap_models_preserve_the_complete_planning_record_graph(): void
    {
        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $office = Office::query()->where('code', 'CBO')->firstOrFail();
        $area = AuditArea::query()->where('code', 'PROCUREMENT')->firstOrFail();
        $focus = $area->focuses()->firstOrFail();
        $riskLevel = $this->item('RISK_LEVEL', 'HIGH');

        $plan = InternalAuditPlan::query()->create([
            'plan_code' => 'IAP-2027-R00',
            'fiscal_year' => 2027,
            'planning_period_type_id' => $this->item('IAP_PLANNING_PERIOD_TYPE', 'ANNUAL')->id,
            'planning_period_start' => '2027-01-01',
            'planning_period_end' => '2027-12-31',
            'title' => '2027 Annual Internal Audit Plan',
            'executive_summary' => 'Risk-based annual internal audit coverage.',
            'planning_methodology' => 'Weighted risk assessment and available capacity.',
            'overall_objective' => 'Provide risk-based assurance over priority city operations.',
            'overall_scope' => 'Selected city offices, audit areas, and audit focuses.',
            'status' => 'DRAFT',
            'revision_number' => 0,
            'is_current_revision' => true,
            'prepared_by' => $auditor->id,
            'coordinator_id' => $management->id,
            'lock_version' => 1,
            'is_active' => true,
        ]);

        $assessment = IapRiskAssessment::query()->create([
            'plan_id' => $plan->id,
            'office_id' => $office->id,
            'audit_area_id' => $area->id,
            'assessed_by' => $auditor->id,
            'assessment_date' => '2026-11-15',
            'total_weighted_score' => 3.75,
            'calculated_risk_level_id' => $riskLevel->id,
            'final_risk_level_id' => $riskLevel->id,
            'justification' => 'Material procurement activity and unresolved prior findings.',
        ]);

        IapRiskAssessmentScore::query()->create([
            'risk_assessment_id' => $assessment->id,
            'risk_criterion_id' => $this->item('IAP_RISK_CRITERION', 'FINANCIAL_MATERIALITY')->id,
            'criterion_weight' => 15,
            'rating' => 4,
            'weighted_score' => 0.6,
            'comment' => 'High annual procurement value.',
        ]);

        $engagement = IapPlanEngagement::query()->create([
            'plan_id' => $plan->id,
            'engagement_code' => 'IAP-2027-001',
            'title' => 'Procurement and Supply Management Audit',
            'engagement_type_id' => $this->item('IAP_ENGAGEMENT_TYPE', 'COMPLIANCE')->id,
            'audit_approach_id' => $this->item('IAP_AUDIT_APPROACH', 'RISK_BASED')->id,
            'priority_id' => $this->item('IAP_PLANNING_PRIORITY', 'HIGH')->id,
            'risk_level_id' => $riskLevel->id,
            'risk_assessment_id' => $assessment->id,
            'background' => 'Selected through the annual risk assessment.',
            'objectives' => 'Assess procurement compliance and related internal controls.',
            'scope' => 'Procurement planning through payment and inventory recording.',
            'planned_start_date' => '2027-02-01',
            'planned_end_date' => '2027-03-15',
            'estimated_person_days' => 20,
            'estimated_cost' => 25000,
            'sequence_number' => 1,
            'is_active' => true,
        ]);
        $engagement->offices()->attach($office->id);
        $engagement->auditAreas()->attach($area->id);
        $engagement->auditFocuses()->attach($focus->id);

        IapEngagementTeamMember::query()->create([
            'plan_engagement_id' => $engagement->id,
            'user_id' => $auditor->id,
            'team_role_id' => $this->item('IAP_TEAM_ROLE', 'LEAD_AUDITOR')->id,
            'planned_person_days' => 12,
        ]);
        IapEngagementTeamMember::query()->create([
            'plan_engagement_id' => $engagement->id,
            'user_id' => $management->id,
            'team_role_id' => $this->item('IAP_TEAM_ROLE', 'REVIEWER')->id,
            'planned_person_days' => 8,
        ]);

        IapWorkflowEvent::query()->create([
            'plan_id' => $plan->id,
            'action' => 'CREATE',
            'from_status' => null,
            'to_status' => 'DRAFT',
            'actor_id' => $auditor->id,
            'actor_role_code' => 'agis_user',
            'comment' => 'Initial annual plan revision created.',
            'plan_lock_version' => 1,
        ]);

        IapComment::query()->create([
            'plan_id' => $plan->id,
            'plan_engagement_id' => $engagement->id,
            'author_id' => $management->id,
            'comment_type_id' => $this->item('IAP_COMMENT_TYPE', 'MANAGEMENT')->id,
            'visibility' => 'INTERNAL',
            'body' => 'Coordinate the engagement schedule with the auditee office.',
            'is_immutable' => false,
        ]);

        $document = Document::query()->create([
            'document_type_id' => $this->item('DOCUMENT_TYPE', 'TEMPLATE_FORM')->id,
            'title' => 'IAP Risk Assessment Working Paper',
            'owner_module' => 'iap',
            'library_visible' => false,
            'original_file_name' => 'iap-risk-assessment.pdf',
            'storage_path' => 'documents/iap-risk-assessment.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size' => 1024,
            'checksum_sha256' => str_repeat('a', 64),
            'uploaded_by' => $auditor->id,
            'updated_by' => $auditor->id,
            'is_active' => true,
        ]);
        IapAttachment::query()->create([
            'plan_id' => $plan->id,
            'risk_assessment_id' => $assessment->id,
            'document_id' => $document->id,
            'attachment_type_id' => $this->item('IAP_ATTACHMENT_TYPE', 'RISK_SUPPORT')->id,
            'display_name' => 'Risk Assessment Support',
            'visibility' => 'INTERNAL',
            'uploaded_by' => $auditor->id,
        ]);

        $loaded = $plan->fresh([
            'preparer',
            'coordinator',
            'riskAssessments.scores.criterion',
            'engagements.offices',
            'engagements.auditAreas',
            'engagements.auditFocuses',
            'engagements.teamMembers.teamRole',
            'workflowEvents',
            'comments',
            'attachments.document',
        ]);

        $this->assertSame('CIAS-AUD-004', $loaded->preparer->employee_id);
        $this->assertCount(1, $loaded->riskAssessments);
        $this->assertCount(1, $loaded->riskAssessments->first()->scores);
        $this->assertCount(1, $loaded->engagements);
        $this->assertSame('CBO', $loaded->engagements->first()->offices->first()->code);
        $this->assertSame('PROCUREMENT', $loaded->engagements->first()->auditAreas->first()->code);
        $this->assertCount(1, $loaded->engagements->first()->auditFocuses);
        $this->assertCount(2, $loaded->engagements->first()->teamMembers);
        $this->assertSame(20.0, $loaded->engagements->first()->teamMembers->sum('planned_person_days'));
        $this->assertCount(1, $loaded->workflowEvents);
        $this->assertCount(1, $loaded->comments);
        $this->assertCount(1, $loaded->attachments);
        $this->assertSame(
            'iap-risk-assessment.pdf',
            $loaded->attachments->first()->document->original_file_name,
        );
    }

    public function test_only_one_current_non_archived_plan_revision_is_allowed_per_fiscal_year(): void
    {
        $auditor = $this->user('auditor');
        $periodType = $this->item('IAP_PLANNING_PERIOD_TYPE', 'ANNUAL');

        InternalAuditPlan::query()->create($this->planAttributes(
            'IAP-2028-R00',
            2028,
            0,
            $periodType->id,
            $auditor->id,
        ));

        $this->expectException(QueryException::class);
        InternalAuditPlan::query()->create($this->planAttributes(
            'IAP-2028-R01',
            2028,
            1,
            $periodType->id,
            $auditor->id,
        ));
    }

    /** @return array<string, mixed> */
    private function planAttributes(
        string $code,
        int $year,
        int $revision,
        int $periodTypeId,
        int $preparerId,
    ): array {
        return [
            'plan_code' => $code,
            'fiscal_year' => $year,
            'planning_period_type_id' => $periodTypeId,
            'planning_period_start' => "{$year}-01-01",
            'planning_period_end' => "{$year}-12-31",
            'title' => "{$year} Annual Internal Audit Plan",
            'overall_objective' => 'Provide risk-based internal audit coverage.',
            'overall_scope' => 'Authorized city offices and audit areas.',
            'status' => 'DRAFT',
            'revision_number' => $revision,
            'is_current_revision' => true,
            'prepared_by' => $preparerId,
            'lock_version' => 1,
            'is_active' => true,
        ];
    }

    private function list(string $code): MasterList
    {
        return MasterList::query()->where('code', $code)->firstOrFail();
    }

    private function item(string $listCode, string $itemCode): MasterListItem
    {
        return $this->list($listCode)
            ->items()
            ->where('code', $itemCode)
            ->firstOrFail();
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
