<?php

namespace Tests\Feature;

use App\Models\AuditEngagement;
use App\Models\IapPlanEngagement;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AemsCrossModuleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_iap_import_keeps_approved_source_row_unchanged_and_owns_lineage_in_aems(): void
    {
        $actor = $this->user('departmenthead');
        $source = IapPlanEngagement::query()
            ->with(['plan', 'offices', 'auditAreas', 'auditFocuses'])
            ->firstOrFail();
        $source->plan->update([
            'status' => 'ACTIVE',
            'approved_at' => now()->subDay(),
            'approved_by' => $actor->id,
            'activated_at' => now(),
            'activated_by' => $actor->id,
        ]);
        $before = DB::table('iap_plan_engagements')->whereKey($source->id)->first();

        Sanctum::actingAs($actor);
        $engagementId = $this->postJson('/api/aems/engagements/import', [
            'iapPlanEngagementId' => $source->id,
        ])->assertCreated()->json('data.engagement.id');

        $after = DB::table('iap_plan_engagements')->whereKey($source->id)->first();
        $this->assertSame((array) $before, (array) $after);
        $this->assertSame(
            $engagementId,
            IapPlanEngagement::query()->findOrFail($source->id)->aem_engagement_id,
        );
        $this->assertDatabaseHas('audit_engagements', [
            'id' => $engagementId,
            'iap_plan_engagement_id' => $source->id,
        ]);
    }

    public function test_integration_status_is_protected_and_declares_cross_module_ownership(): void
    {
        Sanctum::actingAs($this->user('admin'));

        $this->getJson('/api/aems/integrations/status')
            ->assertOk()
            ->assertJsonPath('data.integrations.security.scopeAware', true)
            ->assertJsonPath('data.integrations.security.ais.integrated', false)
            ->assertJsonPath('data.integrations.iap.sourceMutation', false)
            ->assertJsonPath('data.integrations.iap.lineageOwner', 'AEMS_AUDIT_ENGAGEMENT')
            ->assertJsonPath('data.integrations.cms.immutableSourceEnvelope', true)
            ->assertJsonPath('data.integrations.cms.idempotency', 'TRANSFER_KEY_AND_SOURCE_RECOMMENDATION_UNIQUE')
            ->assertJsonPath('data.integrations.integrity.healthy', true);
    }

    public function test_integration_status_requires_the_existing_aems_scope_permission(): void
    {
        Sanctum::actingAs($this->user('auditee'));

        $this->getJson('/api/aems/integrations/status')->assertForbidden();
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
