<?php

namespace Tests\Feature\Api;

use App\Models\AuditEngagement;
use App\Models\AuditFocus;
use App\Models\IapPlanEngagement;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AemsFoundationG2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_g2_schema_and_completed_projection_are_declared(): void
    {
        foreach ([
            'iap_risk_source_type',
            'iap_legacy_risk_assessment_id',
            'special_authority_class',
            'scope_boundaries',
            'scope_limitations',
            'scope_source_variance',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('audit_engagements', $column));
        }
        $this->assertTrue(Schema::hasColumn('audit_engagement_audit_areas', 'coverage_metadata'));
        $this->assertContains('COMPLETED', AuditEngagement::STATUSES);
        $projection = AuditEngagement::lifecycleProjectionForStatus('COMPLETED');
        $this->assertSame('COMPLETION_TRANSFER', $projection['phase']);
        $this->assertSame('ACTIVE', $projection['administrative_status']);
    }

    public function test_scope_workspace_enforces_one_office_and_persists_structured_coverage(): void
    {
        $management = $this->user('departmenthead');
        $source = $this->approvedSource($management);
        Sanctum::actingAs($management);
        $engagementId = $this->postJson('/api/aems/engagements/import', [
            'iapPlanEngagementId' => $source->id,
        ])->assertCreated()->json('data.engagement.id');
        $area = $source->auditAreas->firstOrFail();
        $focusIds = $source->auditFocuses
            ->where('audit_area_id', $area->id)
            ->pluck('id')
            ->values()
            ->all();

        $this->getJson("/api/aems/engagements/{$engagementId}/scope")
            ->assertOk()
            ->assertJsonPath('data.contract.screenId', 'SCR-212')
            ->assertJsonPath('data.scope.officeRule.requiredCount', 1);

        $updated = $this->putJson("/api/aems/engagements/{$engagementId}/scope", [
            'officeId' => $source->offices->firstOrFail()->id,
            'scopeBoundaries' => 'Approved Area boundary.',
            'scopeLimitations' => 'No limitation identified.',
            'scopeSourceVariance' => ['decision' => 'ALIGNED'],
            'areaCoverage' => [[
                'auditAreaId' => $area->id,
                'focusIds' => $focusIds,
                'boundary' => 'Area-specific boundary.',
                'limitations' => 'Area-specific limitation review.',
                'sourceVariance' => '',
                'objective' => 'Area objective.',
            ]],
            'lockVersion' => 1,
        ])->assertOk()->json('data.scope');

        $this->assertSame('Approved Area boundary.', $updated['scopeBoundaries']);
        $this->assertSame(1, $updated['officeRule']['actualCount']);
        $this->assertSame('Area-specific boundary.', $updated['auditAreas'][0]['coverageMetadata']['boundary']);
        $this->assertSame(
            $focusIds,
            collect($updated['auditFocuses'])->pluck('id')->sort()->values()->all(),
        );
        $this->assertDatabaseHas('engagement_events', [
            'audit_engagement_id' => $engagementId,
            'action' => 'UPDATE_SCOPE',
        ]);
    }

    public function test_scope_can_be_saved_without_selecting_a_focus(): void
    {
        $management = $this->user('departmenthead');
        $source = $this->approvedSource($management);
        $area = $source->auditAreas->firstOrFail();

        Sanctum::actingAs($management);
        $engagementId = $this->postJson('/api/aems/engagements/import', [
            'iapPlanEngagementId' => $source->id,
        ])->assertCreated()->json('data.engagement.id');

        $this->putJson("/api/aems/engagements/{$engagementId}/scope", [
            'officeId' => $source->offices->firstOrFail()->id,
            'areaCoverage' => [[
                'auditAreaId' => $area->id,
                'focusIds' => [],
            ]],
            'lockVersion' => 1,
        ])->assertOk();
    }

    public function test_import_records_iap_risk_source_discriminator(): void
    {
        $management = $this->user('departmenthead');
        $source = $this->approvedSource($management);
        Sanctum::actingAs($management);
        $engagement = $this->postJson('/api/aems/engagements/import', [
            'iapPlanEngagementId' => $source->id,
        ])->assertCreated()->json('data.engagement');

        $this->assertSame(
            $source->universe_risk_assessment_id ? 'UNIVERSE_RISK_ASSESSMENT' : ($source->risk_assessment_id ? 'LEGACY_RISK_ASSESSMENT' : null),
            $engagement['iapRiskSourceType'],
        );
    }

    public function test_database_allows_only_one_office_pivot_per_engagement(): void
    {
        $management = $this->user('departmenthead');
        $source = $this->approvedSource($management);
        Sanctum::actingAs($management);
        $engagement = AuditEngagement::query()->findOrFail(
            $this->postJson('/api/aems/engagements/import', [
                'iapPlanEngagementId' => $source->id,
            ])->assertCreated()->json('data.engagement.id'),
        );
        $office = Office::query()->whereKey($engagement->engagement_office_id)->firstOrFail();
        $otherOffice = Office::query()->where('id', '<>', $office->id)->firstOrFail();

        $this->expectException(QueryException::class);
        $engagement->offices()->attach($otherOffice->id, ['is_primary' => false]);
    }

    public function test_scope_rejects_focus_outside_the_selected_area(): void
    {
        $management = $this->user('departmenthead');
        $source = $this->approvedSource($management);
        $area = $source->auditAreas->firstOrFail();
        $foreignFocus = AuditFocus::query()
            ->where('audit_area_id', '<>', $area->id)
            ->where('is_active', true)
            ->first();
        $this->assertNotNull($foreignFocus, 'The seeded catalog must contain a focus outside the selected area.');

        Sanctum::actingAs($management);
        $engagementId = $this->postJson('/api/aems/engagements/import', [
            'iapPlanEngagementId' => $source->id,
        ])->assertCreated()->json('data.engagement.id');

        $this->putJson("/api/aems/engagements/{$engagementId}/scope", [
            'officeId' => $source->offices->firstOrFail()->id,
            'scopeBoundaries' => 'Area boundary.',
            'scopeLimitations' => 'No limitation identified.',
            'scopeSourceVariance' => ['decision' => 'ALIGNED'],
            'areaCoverage' => [[
                'auditAreaId' => $area->id,
                'focusIds' => [$foreignFocus->id],
                'boundary' => 'Area boundary.',
            ]],
            'lockVersion' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('areaCoverage');
    }

    public function test_scope_rejects_non_positive_focus_ids_before_pivot_write(): void
    {
        $management = $this->user('departmenthead');
        $source = $this->approvedSource($management);
        $area = $source->auditAreas->firstOrFail();

        Sanctum::actingAs($management);
        $engagementId = $this->postJson('/api/aems/engagements/import', [
            'iapPlanEngagementId' => $source->id,
        ])->assertCreated()->json('data.engagement.id');

        $this->putJson("/api/aems/engagements/{$engagementId}/scope", [
            'officeId' => $source->offices->firstOrFail()->id,
            'areaCoverage' => [[
                'auditAreaId' => $area->id,
                'focusIds' => [0],
            ]],
            'lockVersion' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('areaCoverage.0.focusIds.0');
    }

    public function test_scope_rejects_area_not_linked_to_the_selected_office(): void
    {
        $management = $this->user('departmenthead');
        $source = $this->approvedSource($management);
        $office = $source->offices->firstOrFail();
        $unlinkedArea = \App\Models\AuditArea::query()
            ->where('is_active', true)
            ->whereDoesntHave('offices', fn ($query) => $query->whereKey($office->id))
            ->first();

        $this->assertNotNull(
            $unlinkedArea,
            'The seeded catalog must contain an audit area outside the selected office.',
        );

        Sanctum::actingAs($management);
        $engagementId = $this->postJson('/api/aems/engagements/import', [
            'iapPlanEngagementId' => $source->id,
        ])->assertCreated()->json('data.engagement.id');

        $this->putJson("/api/aems/engagements/{$engagementId}/scope", [
            'officeId' => $office->id,
            'areaCoverage' => [[
                'auditAreaId' => $unlinkedArea->id,
                'focusIds' => [],
            ]],
            'lockVersion' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('areaCoverage');
    }

    private function user(string $username): \App\Models\User
    {
        return \App\Models\User::query()->where('username', $username)->firstOrFail();
    }

    private function approvedSource(User $management): IapPlanEngagement
    {
        $source = IapPlanEngagement::query()->with(['plan', 'offices', 'auditAreas'])->firstOrFail();
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
}
