<?php

namespace Tests\Feature\Api;

use App\Models\AuditEngagement;
use App\Models\EngagementTeam;
use App\Models\IapPlanEngagement;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AemsEngagementRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_approved_iap_item_import_preserves_lineage_snapshot_and_blocks_duplicates(): void
    {
        $management = $this->user('departmenthead');
        $source = $this->approvedSource($management);
        $originalObjective = $source->objectives;
        $originalResidualRisk = (float) $source->source_residual_risk_score;
        Sanctum::actingAs($management);

        $this->getJson('/api/aems/engagements/import-options')
            ->assertOk()
            ->assertJsonPath('data.iapEngagements.0.id', $source->id);

        $created = $this->postJson('/api/aems/engagements/import', [
            'iapPlanEngagementId' => $source->id,
        ])->assertCreated()
            ->assertJsonPath('data.engagement.sourceType', 'PLANNED')
            ->assertJsonPath('data.engagement.status', 'DRAFT')
            ->assertJsonPath('data.engagement.iapPlanEngagementId', $source->id)
            ->json('data.engagement');

        $engagement = AuditEngagement::query()->findOrFail($created['id']);
        $this->assertSame($source->plan_id, $engagement->iap_plan_id);
        $this->assertSame($source->prioritization_item_id, $engagement->iap_prioritization_item_id);
        $this->assertSame($source->universe_risk_assessment_id, $engagement->iap_risk_assessment_id);
        $this->assertSame($source->audit_universe_item_id, $engagement->iap_audit_universe_item_id);
        $this->assertSame($originalObjective, $engagement->source_snapshot['planEngagement']['objectives']);
        $this->assertSame(
            $originalResidualRisk,
            $engagement->source_snapshot['riskAssessment']['residualRiskScore'],
        );
        $this->assertSame(
            $engagement->id,
            $source->fresh()->aem_engagement_id,
        );

        $source->update([
            'objectives' => 'Changed after AEMS import.',
            'source_residual_risk_score' => 1.00,
        ]);
        $this->getJson("/api/aems/engagements/{$engagement->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.engagement.sourceSnapshot.planEngagement.objectives',
                $originalObjective,
            )
            ->assertJsonPath(
                'data.engagement.sourceSnapshot.riskAssessment.residualRiskScore',
                $originalResidualRisk,
            );

        $this->postJson('/api/aems/engagements/import', [
            'iapPlanEngagementId' => $source->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('iapPlanEngagementId');
    }

    public function test_special_engagement_can_be_created_updated_filtered_archived_and_restored(): void
    {
        $management = $this->user('departmenthead');
        $mayor = $this->user('mayor');
        $source = IapPlanEngagement::query()
            ->with(['offices', 'auditAreas', 'auditFocuses'])
            ->firstOrFail();
        $office = $source->offices->firstOrFail();
        $area = $source->auditAreas->firstOrFail();
        Sanctum::actingAs($management);

        $payload = [
            'title' => 'Special Cash Accountability Audit',
            'specialAuthorityReference' => 'OCM-MEMO-2026-014',
            'specialAuthorityTypeCode' => 'MAYOR_DIRECTIVE',
            'specialAuthorityDate' => '2026-07-15',
            'specialAuthorityApprovedBy' => $mayor->id,
            'background' => 'A separately authorized unplanned audit.',
            'objectives' => 'Assess cash accountability controls.',
            'scope' => 'Cash collections and deposits for the selected period.',
            'exclusions' => 'Payroll disbursements are outside this engagement.',
            'plannedStartDate' => '2026-08-03',
            'plannedEndDate' => '2026-08-21',
            'expectedReportDate' => '2026-09-04',
            'plannedPersonDays' => 15,
            'officeIds' => [$office->id],
            'auditAreaIds' => [$area->id],
            'auditFocusIds' => $source->auditFocuses->pluck('id')->all(),
        ];
        $created = $this->postJson('/api/aems/engagements', $payload)
            ->assertCreated()
            ->assertJsonPath('data.engagement.sourceType', 'SPECIAL')
            ->assertJsonPath('data.engagement.status', 'DRAFT')
            ->assertJsonPath(
                'data.engagement.specialAuthorityReference',
                'OCM-MEMO-2026-014',
            )
            ->assertJsonPath(
                'data.engagement.objectives',
                'Assess cash accountability controls.',
            )
            ->assertJsonPath(
                'data.engagement.scope',
                'Cash collections and deposits for the selected period.',
            )
            ->assertJsonPath(
                'data.engagement.exclusions',
                'Payroll disbursements are outside this engagement.',
            )
            ->json('data.engagement');

        $this->getJson('/api/aems/engagements?search=Cash%20Accountability&sourceType=SPECIAL')
            ->assertOk()
            ->assertJsonCount(1, 'data.engagements')
            ->assertJsonPath('data.summary.special', 1);

        $updated = $this->putJson("/api/aems/engagements/{$created['id']}", [
            ...$payload,
            'engagementCode' => $created['engagementCode'],
            'title' => 'Updated Special Cash Accountability Audit',
            'lockVersion' => $created['lockVersion'],
        ])->assertOk()
            ->assertJsonPath(
                'data.engagement.title',
                'Updated Special Cash Accountability Audit',
            )->json('data.engagement');

        // Registry edits no longer carry SCR-212 values. They must not clear
        // the separately maintained scope or coverage.
        $this->putJson("/api/aems/engagements/{$created['id']}", [
            'engagementCode' => $updated['engagementCode'],
            'title' => 'Updated Registry Metadata Only',
            'plannedStartDate' => '2026-08-03',
            'plannedEndDate' => '2026-08-21',
            'plannedPersonDays' => 15,
            'lockVersion' => $updated['lockVersion'],
        ])->assertOk()->assertJsonPath('data.engagement.title', 'Updated Registry Metadata Only');
        $this->assertDatabaseHas('audit_engagements', [
            'id' => $created['id'],
            'objectives' => 'Assess cash accountability controls.',
            'scope' => 'Cash collections and deposits for the selected period.',
            'exclusions' => 'Payroll disbursements are outside this engagement.',
        ]);
        $this->assertDatabaseHas('audit_engagement_offices', [
            'audit_engagement_id' => $created['id'],
            'office_id' => $office->id,
        ]);

        $this->deleteJson("/api/aems/engagements/{$created['id']}")
            ->assertOk();
        $this->assertSoftDeleted('audit_engagements', ['id' => $created['id']]);
        $this->getJson('/api/aems/engagements?includeArchived=1')
            ->assertOk()
            ->assertJsonPath('data.summary.archived', 1);

        $this->postJson("/api/aems/engagements/{$created['id']}/restore")
            ->assertOk()
            ->assertJsonPath('data.engagement.isArchived', false);
        $this->assertDatabaseHas('audit_engagements', [
            'id' => $created['id'],
            'deleted_at' => null,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('engagement_events', [
            'audit_engagement_id' => $created['id'],
            'action' => 'ARCHIVE',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => AuditEngagement::class,
            'auditable_id' => $created['id'],
            'action' => 'aems.engagement.restored',
        ]);
    }

    public function test_special_engagement_draft_can_be_created_before_scope_is_completed(): void
    {
        $management = $this->user('departmenthead');
        $mayor = $this->user('mayor');
        Sanctum::actingAs($management);

        $created = $this->postJson('/api/aems/engagements', [
            'title' => 'Unplanned BPLD Scope Draft',
            'specialAuthorityReference' => 'OCM-MEMO-2026-015',
            'specialAuthorityTypeCode' => 'MAYOR_DIRECTIVE',
            'specialAuthorityDate' => '2026-08-20',
            'specialAuthorityApprovedBy' => $mayor->id,
            'plannedStartDate' => '2026-09-01',
            'plannedEndDate' => '2026-09-30',
            'plannedPersonDays' => 10,
        ])->assertCreated()
            ->assertJsonPath('data.engagement.status', 'DRAFT')
            ->json('data.engagement');

        $this->assertDatabaseHas('audit_engagements', [
            'id' => $created['id'],
            'objectives' => '',
            'scope' => '',
            'status' => 'DRAFT',
        ]);
    }

    public function test_registry_enforces_role_and_current_team_assignment_scope(): void
    {
        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $platform = $this->user('admin');
        $auditee = $this->user('auditee');
        $source = $this->approvedSource($management);
        Sanctum::actingAs($management);
        $engagementId = $this->postJson('/api/aems/engagements/import', [
            'iapPlanEngagementId' => $source->id,
        ])->assertCreated()->json('data.engagement.id');

        Sanctum::actingAs($auditor);
        $this->getJson('/api/aems/engagements')
            ->assertOk()
            ->assertJsonCount(0, 'data.engagements');

        EngagementTeam::query()->create([
            'audit_engagement_id' => $engagementId,
            'user_id' => $auditor->id,
            'assignment_role_code' => 'AUDITOR',
            'assigned_by' => $management->id,
            'is_active' => true,
        ]);
        $this->getJson('/api/aems/engagements')
            ->assertOk()
            ->assertJsonCount(1, 'data.engagements');
        $this->getJson("/api/aems/engagements/{$engagementId}")->assertOk();

        Sanctum::actingAs($platform);
        $this->getJson('/api/aems/engagements')
            ->assertOk()
            ->assertJsonCount(1, 'data.engagements');
        $this->postJson('/api/aems/engagements', [])->assertForbidden();

        Sanctum::actingAs($auditee);
        $this->getJson('/api/aems/engagements')->assertForbidden();
        $this->getJson("/api/aems/engagements/{$engagementId}")->assertForbidden();
    }

    private function approvedSource(User $management): IapPlanEngagement
    {
        $source = IapPlanEngagement::query()
            ->with(['plan', 'offices', 'auditAreas'])
            ->firstOrFail();
        $source->plan->update([
            'status' => 'ACTIVE',
            'approved_at' => now()->subDay(),
            'approved_by' => $management->id,
            'activated_at' => now(),
            'activated_by' => $management->id,
        ]);

        return $source->fresh([
            'plan',
            'offices',
            'auditAreas',
            'auditFocuses',
            'prioritizationItem.riskAssessment',
            'universeRiskAssessment',
        ]);
    }

    private function user(string $username): User
    {
        return User::query()
            ->with(['role.permissions', 'roles.permissions', 'office'])
            ->where('username', $username)
            ->firstOrFail();
    }
}
