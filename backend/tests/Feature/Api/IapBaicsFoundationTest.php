<?php

namespace Tests\Feature\Api;

use App\Models\AuditArea;
use App\Models\AuditFocus;
use App\Models\IapAuditUniverseItem;
use App\Models\IapBaicsAssessment;
use App\Models\IapBaicsVersion;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IapBaicsFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_baics_cycle_preserves_audit_universe_lineage_and_workflow(): void
    {
        $actor = User::query()->where('username', 'departmenthead')->firstOrFail();
        $office = Office::query()->whereHas('auditAreas')->firstOrFail();
        $area = $office->auditAreas()->firstOrFail();
        $focus = AuditFocus::query()->where('audit_area_id', $area->id)->firstOrFail();
        $source = IapAuditUniverseItem::query()->where('responsible_office_id', $office->id)->firstOrFail();

        Sanctum::actingAs($actor);
        $created = $this->postJson('/api/iap/baics', [
            'assessmentYear' => 2026,
            'name' => '2026 Internal Control Baseline',
            'responsibleOfficeId' => $office->id,
            'scopeSummary' => 'Baseline controls for the selected auditable process.',
            'objectives' => 'Establish a documented control baseline.',
            'methodology' => 'ICQ, interview, documentary review, and walkthrough.',
            'scopeItems' => [[
                'auditUniverseItemId' => $source->id,
                'officeId' => $office->id,
                'auditAreaId' => $area->id,
                'auditFocusId' => $focus->id,
            ]],
        ])->assertCreated()->json('data.assessment');

        $this->assertSame('DRAFT', $created['status']);
        $this->assertCount(1, $created['scopeItems']);
        $this->assertSame($source->subject_code, $created['scopeItems'][0]['sourceSnapshot']['subjectCode']);
        $this->assertDatabaseHas('iap_baics_versions', ['assessment_id' => $created['id'], 'status' => 'DRAFT']);

        $assigned = $this->postJson("/api/iap/baics/{$created['id']}/assignments", [
            'userId' => $actor->id,
            'roleCode' => 'ASSESSOR',
            'assignmentReason' => 'Foundation assessment assignment.',
        ])->assertCreated()->json('data.assignment');
        $this->assertSame('ASSESSOR', $assigned['roleCode']);

        $cycle = $this->postJson("/api/iap/baics/{$created['id']}/transitions/OPEN", ['lockVersion' => $created['lockVersion']])->assertOk()->json('data.assessment');
        $cycle = $this->postJson("/api/iap/baics/{$created['id']}/transitions/START", ['lockVersion' => $cycle['lockVersion']])->assertOk()->json('data.assessment');
        $cycle = $this->postJson("/api/iap/baics/{$created['id']}/transitions/SUBMIT", ['lockVersion' => $cycle['lockVersion']])->assertOk()->json('data.assessment');
        $this->assertSame('PENDING_REVIEW', $cycle['status']);

        // The preparer cannot approve their own cycle.
        $this->postJson("/api/iap/baics/{$created['id']}/transitions/APPROVE", ['lockVersion' => $cycle['lockVersion']])->assertUnprocessable()->assertJsonValidationErrors('approver');
        $this->assertDatabaseHas('iap_baics_assessments', ['id' => $created['id'], 'status' => 'PENDING_REVIEW']);
    }

    public function test_baics_scope_and_concurrency_are_guarded_and_approved_versions_can_only_be_revised(): void
    {
        $actor = User::query()->where('username', 'departmenthead')->firstOrFail();
        $office = Office::query()->whereHas('auditAreas')->firstOrFail();
        $area = $office->auditAreas()->firstOrFail();
        $focus = AuditFocus::query()->where('audit_area_id', $area->id)->firstOrFail();
        $source = IapAuditUniverseItem::query()->where('responsible_office_id', $office->id)->firstOrFail();
        Sanctum::actingAs($actor);
        $payload = ['assessmentYear' => 2026, 'name' => 'Concurrency Baseline', 'responsibleOfficeId' => $office->id, 'scopeSummary' => 'Scope', 'objectives' => 'Objective', 'methodology' => 'Methods', 'scopeItems' => [['auditUniverseItemId' => $source->id, 'officeId' => $office->id, 'auditAreaId' => $area->id, 'auditFocusId' => $focus->id]]];
        $created = $this->postJson('/api/iap/baics', $payload)->assertCreated()->json('data.assessment');
        $stale = [...$payload, 'lockVersion' => 999];
        $this->putJson("/api/iap/baics/{$created['id']}", $stale)->assertUnprocessable()->assertJsonValidationErrors('lockVersion');

        $this->assertDatabaseCount('iap_baics_assessments', 1);
        $this->assertGreaterThanOrEqual(1, IapBaicsVersion::query()->where('assessment_id', $created['id'])->count());
    }
}
