<?php

namespace Tests\Feature;

use App\Models\AuditEngagement;
use App\Models\IapPlanEngagement;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AemsFoundationContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_foundation_columns_and_lifecycle_projection_are_available(): void
    {
        $this->assertTrue(Schema::hasColumn('audit_engagements', 'engagement_office_id'));
        $this->assertTrue(Schema::hasColumn('audit_engagements', 'phase'));
        $this->assertTrue(Schema::hasColumn('audit_engagements', 'administrative_status'));

        $projection = AuditEngagement::lifecycleProjectionForStatus('FIELDWORK');
        $this->assertSame('EXECUTION', $projection['phase']);
        $this->assertSame('ACTIVE', $projection['administrative_status']);

        $suspended = AuditEngagement::lifecycleProjectionForStatus('SUSPENDED', 'REPORTING');
        $this->assertSame('REPORTING', $suspended['phase']);
        $this->assertSame('SUSPENDED', $suspended['administrative_status']);

        $permissions = collect(['aems.foundation.view', 'aems.foundation.manage_scope', 'aems.foundation.reconcile']);
        $this->assertTrue($permissions->every(
            fn (string $permission): bool => \App\Models\Permission::query()
                ->where('code', $permission)
                ->exists(),
        ));
    }

    public function test_special_engagement_scope_requires_exactly_one_office(): void
    {
        $management = $this->user('departmenthead');
        $mayor = $this->user('mayor');
        $source = IapPlanEngagement::query()->with('auditAreas')->firstOrFail();
        $officeIds = Office::query()->limit(2)->pluck('id')->all();

        Sanctum::actingAs($management);
        $this->postJson('/api/aems/engagements', [
            'title' => 'Foundation Scope Invariant Audit',
            'specialAuthorityReference' => 'OCM-MEMO-FOUNDATION-001',
            'specialAuthorityTypeCode' => 'MAYOR_DIRECTIVE',
            'specialAuthorityDate' => '2026-08-19',
            'specialAuthorityApprovedBy' => $mayor->id,
            'objectives' => 'Validate the AEMS foundation scope invariant.',
            'scope' => 'AEMS foundation contract coverage.',
            'plannedStartDate' => '2026-09-01',
            'plannedEndDate' => '2026-09-15',
            'plannedPersonDays' => 10,
            'officeIds' => $officeIds,
            'auditAreaIds' => [$source->auditAreas->firstOrFail()->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('officeIds');

        $this->assertDatabaseCount('audit_engagements', 0);
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
