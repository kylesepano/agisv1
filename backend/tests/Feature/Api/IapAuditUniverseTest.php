<?php

namespace Tests\Feature\Api;

use App\Models\AuditArea;
use App\Models\AuditLog;
use App\Models\IapAuditUniverseItem;
use App\Models\MasterListItem;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IapAuditUniverseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeded_audit_universe_is_searchable_and_visible_by_iap_role(): void
    {
        $this->assertDatabaseCount('iap_audit_universe_items', 12);
        $this->assertGreaterThan(0, DB::table('iap_audit_universe_history')->count());

        foreach (['admin', 'agisadmin', 'departmenthead', 'auditor', 'mayor'] as $username) {
            Sanctum::actingAs($this->user($username));
            $this->getJson('/api/iap/audit-universe?perPage=100')
                ->assertOk()
                ->assertJsonPath('data.pagination.total', 12);
        }

        Sanctum::actingAs($this->user('departmenthead'));
        $this->getJson('/api/iap/audit-universe?search=Business%20Tax')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.auditUniverse.0.subjectCode', 'AU-REV-001')
            ->assertJsonCount(1, 'data.auditUniverse.0.auditHistory');

        Sanctum::actingAs($this->user('auditee'));
        $this->getJson('/api/iap/audit-universe')->assertForbidden();
    }

    public function test_management_can_create_update_archive_and_restore_a_subject(): void
    {
        Sanctum::actingAs($this->user('departmenthead'));
        [$office, $area] = $this->coverage();
        $stakeholder = Office::query()->where('id', '<>', $office->id)->firstOrFail();

        $created = $this->postJson('/api/iap/audit-universe', [
            'subjectCode' => 'AU-TEST-001',
            'name' => 'Test Auditable Process',
            'subjectTypeId' => $this->item('IAP_AUDIT_UNIVERSE_SUBJECT_TYPE', 'PROCESS')->id,
            'responsibleOfficeId' => $office->id,
            'primaryAuditAreaId' => $area->id,
            'materialityLevelId' => $this->item('RISK_LEVEL', 'HIGH')->id,
            'description' => 'A controlled test subject for Audit Universe API behavior.',
            'auditScope' => 'The complete process and its key controls.',
            'materialityExposure' => 'Material financial and service-delivery exposure.',
            'stakeholderOfficeIds' => [$stakeholder->id],
            'isActive' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.auditUniverseItem.subjectCode', 'AU-TEST-001')
            ->assertJsonCount(1, 'data.auditUniverseItem.stakeholderOffices')
            ->json('data.auditUniverseItem');

        $updated = $this->putJson("/api/iap/audit-universe/{$created['id']}", [
            'subjectCode' => 'AU-TEST-001',
            'name' => 'Updated Test Auditable Process',
            'subjectTypeId' => $this->item('IAP_AUDIT_UNIVERSE_SUBJECT_TYPE', 'PROCESS')->id,
            'responsibleOfficeId' => $office->id,
            'primaryAuditAreaId' => $area->id,
            'materialityLevelId' => $this->item('RISK_LEVEL', 'CRITICAL')->id,
            'description' => 'Updated subject description.',
            'auditScope' => 'Updated complete-process scope.',
            'materialityExposure' => 'Critical exposure after reassessment.',
            'lastAuditDate' => '2025-06-30',
            'stakeholderOfficeIds' => [],
            'isActive' => true,
            'lockVersion' => $created['lockVersion'],
        ])
            ->assertOk()
            ->assertJsonPath('data.auditUniverseItem.name', 'Updated Test Auditable Process')
            ->assertJsonPath('data.auditUniverseItem.materialityLevel.code', 'CRITICAL')
            ->json('data.auditUniverseItem');

        $this->putJson("/api/iap/audit-universe/{$created['id']}", [
            'subjectCode' => 'AU-TEST-001',
            'name' => 'Stale Change',
            'subjectTypeId' => $this->item('IAP_AUDIT_UNIVERSE_SUBJECT_TYPE', 'PROCESS')->id,
            'responsibleOfficeId' => $office->id,
            'primaryAuditAreaId' => $area->id,
            'description' => 'This change must be rejected.',
            'lockVersion' => $created['lockVersion'],
        ])->assertUnprocessable()->assertJsonValidationErrors('lockVersion');

        $this->deleteJson("/api/iap/audit-universe/{$created['id']}")
            ->assertOk();
        $this->assertSoftDeleted('iap_audit_universe_items', ['id' => $created['id']]);

        $this->getJson('/api/iap/audit-universe?includeArchived=1&status=ARCHIVED&perPage=100')
            ->assertOk()
            ->assertJsonFragment(['subjectCode' => 'AU-TEST-001']);

        foreach (['auditor', 'mayor'] as $username) {
            Sanctum::actingAs($this->user($username));
            $this->getJson('/api/iap/audit-universe?includeArchived=1&status=ARCHIVED&perPage=100')
                ->assertOk()
                ->assertJsonPath('data.pagination.total', 0);
        }

        Sanctum::actingAs($this->user('departmenthead'));
        $this->postJson("/api/iap/audit-universe/{$created['id']}/restore")
            ->assertOk()
            ->assertJsonPath('data.auditUniverseItem.isArchived', false);

        $this->assertTrue(
            AuditLog::query()->where('action', 'iap.audit_universe.created')->exists(),
        );
        $this->assertTrue(
            AuditLog::query()->where('action', 'iap.audit_universe.updated')->exists(),
        );
        $this->assertSame($updated['id'], $created['id']);
    }

    public function test_non_management_iap_roles_cannot_mutate_audit_universe(): void
    {
        $item = IapAuditUniverseItem::query()->firstOrFail();

        foreach (['agisadmin', 'auditor', 'mayor', 'auditee'] as $username) {
            Sanctum::actingAs($this->user($username));
            $this->postJson('/api/iap/audit-universe', [])
                ->assertForbidden();
            $this->deleteJson("/api/iap/audit-universe/{$item->id}")
                ->assertForbidden();
        }
    }

    /** @return array{Office, AuditArea} */
    private function coverage(): array
    {
        $office = Office::query()->whereHas('auditAreas')->firstOrFail();
        $area = $office->auditAreas()->firstOrFail();

        return [$office, $area];
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }

    private function item(string $listCode, string $itemCode): MasterListItem
    {
        return MasterListItem::query()
            ->where('code', $itemCode)
            ->whereHas('masterList', fn ($query) => $query->where('code', $listCode))
            ->firstOrFail();
    }
}
