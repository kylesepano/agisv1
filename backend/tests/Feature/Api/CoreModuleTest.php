<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\AuditArea;
use App\Models\AuditFocus;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoreModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_realistic_core_records_are_seeded_with_relationships(): void
    {
        $this->assertDatabaseCount('offices', 43);
        $this->assertDatabaseCount('roles', 6);
        $this->assertDatabaseCount('users', 97);
        $this->assertDatabaseCount('audit_areas', 10);
        $this->assertDatabaseCount('master_lists', 23);
        $this->assertDatabaseCount('system_configurations', 43);
        $this->assertGreaterThanOrEqual(50, $this->getConnection()->table('audit_focuses')->count());
        $this->assertGreaterThan(80, $this->getConnection()->table('audit_area_office')->count());
        $positionListId = $this->getConnection()
            ->table('master_lists')
            ->where('code', 'POSITION')
            ->value('id');
        $this->assertGreaterThan(
            100,
            $this->getConnection()->table('master_list_items')->where('master_list_id', $positionListId)->count(),
        );
        $this->assertDatabaseHas('master_lists', ['code' => 'OFFICE_SECTOR']);
        $this->assertDatabaseHas('master_lists', ['code' => 'GOVERNMENT_EMPLOYMENT_TYPE']);
        $this->assertDatabaseHas('master_lists', ['code' => 'DOCUMENT_TYPE']);
        $this->assertDatabaseHas('master_lists', ['code' => 'OFFICE_TYPE']);
        $this->assertDatabaseHas('master_lists', ['code' => 'AUDIT_AREA_TYPE']);
        $this->assertFalse(Schema::hasColumn('offices', 'parent_office_id'));
        $this->assertTrue(Schema::hasColumn('audit_areas', 'parent_audit_area_id'));
        foreach ([
            'ENGAGEMENT_STATUS',
            'RECOMMENDATION_STATUS',
            'IAP_PLAN_STATUS',
            'IAP_APPROVAL_ACTION',
        ] as $removedList) {
            $this->assertDatabaseMissing('master_lists', ['code' => $removedList]);
        }
        $this->assertDatabaseHas('master_list_items', [
            'code' => 'INTERNAL_AUDIT_MANUAL',
            'label' => 'Internal Audit Manual / PGIAM',
        ]);
        $this->assertDatabaseHas('master_list_items', [
            'code' => 'CONTRACT_OF_SERVICE',
            'label' => 'Contract of Service',
        ]);
        $this->assertDatabaseHas('users', [
            'employee_id' => 'CASS-HEAD',
            'first_name' => 'Chit Leonelle Isaiah',
            'middle_name' => 'R.',
            'last_name' => 'Bañas',
            'name' => 'Chit Leonelle Isaiah R. Bañas',
        ]);
        $this->assertSame(
            0,
            User::query()
                ->where(function ($query): void {
                    $query
                        ->where('first_name', 'like', 'Atty.%')
                        ->orWhere('first_name', 'like', 'Engr.%')
                        ->orWhere('first_name', 'like', 'Dr.%')
                        ->orWhere('first_name', 'like', 'Judge.%');
                })
                ->count(),
        );
        User::query()
            ->whereNotNull('middle_name')
            ->pluck('middle_name')
            ->each(fn (string $middleName) => $this->assertMatchesRegularExpression(
                '/^\pL\.$/u',
                $middleName,
            ));

        Office::query()
            ->where('code', '!=', 'AGIS-SYS')
            ->each(function (Office $office): void {
                $this->assertTrue(
                    $office->users()->where('is_office_head', true)->exists(),
                    "{$office->name} must have an office head.",
                );
                $this->assertTrue(
                    $office->users()->where('is_office_head', false)->exists(),
                    "{$office->name} must have at least one employee.",
                );
            });

        $procurement = AuditArea::query()
            ->with(['offices', 'focuses'])
            ->where('code', 'PROCUREMENT')
            ->firstOrFail();

        $this->assertSame(42, $procurement->offices->count());
        $this->assertSame(8, $procurement->focuses->count());
        $this->assertNotNull($procurement->audit_area_type_id);
        $this->assertNotNull($procurement->responsible_office_id);
    }

    public function test_core_registry_access_is_enforced_by_role(): void
    {
        Sanctum::actingAs($this->user('agisadmin'));

        $this->getJson('/api/audit-areas')->assertOk()->assertJsonCount(10, 'data.auditAreas');
        $this->getJson('/api/audit-focuses')->assertOk();
        $this->getJson('/api/users')->assertOk()->assertJsonCount(97, 'data.users');
        $this->getJson('/api/roles')->assertOk()->assertJsonCount(6, 'data.roles');
        $this->getJson('/api/permissions')->assertOk()->assertJsonCount(483, 'data.permissions');
        $this->getJson('/api/master-lists')->assertOk()->assertJsonCount(23, 'data.masterLists');
        $this->getJson('/api/master-lists?configurableOnly=1')
            ->assertOk()
            ->assertJsonCount(21, 'data.masterLists');
        $this->getJson('/api/system-configurations')->assertOk()->assertJsonCount(43, 'data.configurations');

        Sanctum::actingAs($this->user('mayor'));
        $this->getJson('/api/audit-areas')->assertOk();
        $this->getJson('/api/master-lists')->assertOk();
        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/audit-areas', [])->assertForbidden();
        $this->putJson('/api/system-configurations', [])->assertForbidden();

        $auditee = $this->user('auditee')->load('role.permissions');
        $this->assertTrue($auditee->hasPermission('cms.view'));
        $this->assertTrue($auditee->hasPermission('documents.view'));
        $this->assertTrue($auditee->hasPermission('notifications.view'));
        $this->assertFalse($auditee->hasPermission('offices.view'));
        $this->assertFalse($auditee->hasPermission('audit_areas.view'));
        $this->assertFalse($auditee->hasPermission('master_lists.view'));
    }

    public function test_internal_reference_lists_are_hidden_and_cannot_be_edited(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $list = MasterList::query()
            ->with('items')
            ->where('code', 'RISK_LEVEL')
            ->firstOrFail();

        $items = $list->items
            ->map(fn ($item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'label' => $item->label,
                'description' => $item->description,
                'isActive' => true,
            ])
            ->values()
            ->all();

        $this->putJson("/api/master-lists/{$list->id}", [
            'name' => $list->name,
            'description' => $list->description,
            'isActive' => true,
            'items' => $items,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('masterList');

        $this->getJson('/api/master-lists?configurableOnly=1')
            ->assertOk()
            ->assertJsonMissing(['code' => 'RISK_LEVEL'])
            ->assertJsonMissing(['code' => 'IAP_COMMENT_TYPE'])
            ->assertJsonFragment(['code' => 'DOCUMENT_TYPE']);
    }

    public function test_audit_area_hierarchy_prevents_cycles_and_preserves_responsibility_and_history(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $office = Office::query()->where('code', 'CFO')->firstOrFail();
        $areaTypeId = $this->getConnection()
            ->table('master_list_items')
            ->join('master_lists', 'master_lists.id', '=', 'master_list_items.master_list_id')
            ->where('master_lists.code', 'AUDIT_AREA_TYPE')
            ->where('master_list_items.code', 'PROCESS')
            ->value('master_list_items.id');

        $parent = $this->postJson('/api/audit-areas', [
            'code' => 'TEST-PARENT',
            'name' => 'Test Parent Area',
            'description' => 'Parent area used to verify hierarchy controls.',
            'scope' => 'Citywide parent scope.',
            'auditAreaTypeId' => $areaTypeId,
            'responsibleOfficeId' => $office->id,
            'officeIds' => [$office->id],
            'isActive' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.auditArea.auditAreaType.code', 'PROCESS')
            ->assertJsonPath('data.auditArea.responsibleOffice.code', 'CFO')
            ->assertJsonPath('data.auditArea.history.0.action', 'audit_area.created')
            ->json('data.auditArea');

        $child = $this->postJson('/api/audit-areas', [
            'code' => 'TEST-CHILD',
            'name' => 'Test Child Area',
            'description' => 'Child area used to verify hierarchy controls.',
            'scope' => 'A narrower subarea scope.',
            'parentAuditAreaId' => $parent['id'],
            'auditAreaTypeId' => $areaTypeId,
            'responsibleOfficeId' => $office->id,
            'officeIds' => [$office->id],
            'isActive' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.auditArea.parentAuditArea.id', $parent['id'])
            ->json('data.auditArea');

        $this->getJson('/api/audit-areas')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $child['id'],
                'parentAuditAreaId' => $parent['id'],
            ]);

        $this->putJson("/api/audit-areas/{$parent['id']}", [
            'code' => $parent['code'],
            'name' => $parent['name'],
            'parentAuditAreaId' => $child['id'],
            'auditAreaTypeId' => $areaTypeId,
            'responsibleOfficeId' => $office->id,
            'officeIds' => [$office->id],
            'isActive' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parentAuditAreaId');

        $this->deleteJson("/api/audit-areas/{$parent['id']}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('auditArea');
        $this->assertNotSoftDeleted('audit_areas', ['id' => $parent['id']]);
    }

    public function test_profile_and_password_changes_are_logged_with_old_and_new_values(): void
    {
        $user = $this->user('auditor');
        Sanctum::actingAs($user);

        $this->putJson('/api/profile', [
            'employeeId' => 'CIAS-AUD-009',
            'firstName' => 'Marissa',
            'middleName' => 'B.',
            'lastName' => 'Barcelona',
            'extension' => null,
            'email' => 'marissa.barcelona@agis.local',
            'position' => 'Internal Auditor II',
            'employmentType' => 'Permanent',
            'contactNumber' => '0917-000-0001',
            'birthDate' => '1990-05-15',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Marissa B. Barcelona')
            ->assertJsonPath('data.profile.employeeId', 'CIAS-AUD-009')
            ->assertJsonPath('data.profile.position', 'Internal Auditor II')
            ->assertJsonPath('data.profile.employmentType', 'Permanent');

        $this->putJson('/api/profile/password', [
            'currentPassword' => 'lala',
            'password' => 'new-lala',
            'password_confirmation' => 'new-lala',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-lala', $user->fresh()->password));

        $profileLog = ActivityLog::query()->where('action', 'profile.updated')->firstOrFail();
        $this->assertSame('Marissa Barcellona', $profileLog->old_values['name']);
        $this->assertSame('Marissa B. Barcelona', $profileLog->new_values['name']);
        $this->assertDatabaseHas('activity_logs', [
            'subject_user_id' => $user->id,
            'action' => 'profile.password_changed',
        ]);
    }

    public function test_user_updates_are_logged_and_agis_admin_cannot_assign_platform_admin(): void
    {
        $administrator = $this->user('agisadmin');
        $target = $this->user('auditee');
        $platformRoleId = $this->user('admin')->role_id;
        Sanctum::actingAs($administrator);

        $this->putJson("/api/users/{$target->id}", [
            'employeeId' => 'AUDITEE-001',
            'email' => 'auditee.updated@agis.local',
            'firstName' => 'Maria',
            'middleName' => 'U.',
            'lastName' => 'Santos',
            'extension' => null,
            'position' => 'Office Representative',
            'employmentType' => 'Contract of Service',
            'contactNumber' => '0917-111-1111',
            'birthDate' => '1992-06-10',
            'isOfficeHead' => false,
            'isActive' => true,
            'officeId' => $target->office_id,
            'roleId' => $target->role_id,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Maria U. Santos')
            ->assertJsonPath('data.user.employmentType', 'Contract of Service');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $administrator->id,
            'subject_user_id' => $target->id,
            'action' => 'user.updated',
        ]);
        $this->assertDatabaseHas('master_list_items', [
            'label' => 'Office Representative',
        ]);

        $duplicateTarget = $this->user('auditor');
        $this->putJson("/api/users/{$duplicateTarget->id}", [
            'employeeId' => 'AUDITEE-001',
            'firstName' => $duplicateTarget->first_name,
            'middleName' => $duplicateTarget->middle_name,
            'lastName' => $duplicateTarget->last_name,
            'extension' => $duplicateTarget->name_extension,
            'officeId' => $duplicateTarget->office_id,
            'roleId' => $duplicateTarget->role_id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employeeId');

        $this->putJson("/api/users/{$target->id}", [
            'employeeId' => $target->employee_id,
            'firstName' => 'Maria',
            'middleName' => 'U.',
            'lastName' => 'Santos',
            'officeId' => $target->office_id,
            'roleId' => $platformRoleId,
        ])->assertForbidden();
    }

    public function test_platform_administrator_can_reset_archive_and_restore_users(): void
    {
        $platformAdministrator = $this->user('admin');
        $target = $this->user('auditee');
        Sanctum::actingAs($platformAdministrator);

        $this->putJson("/api/users/{$target->id}/password", [
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('replacement-password', $target->fresh()->password));
        $this->assertDatabaseHas('activity_logs', [
            'subject_user_id' => $target->id,
            'action' => 'user.password_reset',
        ]);

        $this->deleteJson("/api/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('message', 'User archived successfully.');
        $this->assertSoftDeleted('users', ['id' => $target->id]);

        $this->getJson('/api/users?include_archived=1')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $target->id,
                'isArchived' => true,
            ]);

        $this->postJson("/api/users/{$target->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.user.isArchived', false);
        $this->assertNotSoftDeleted('users', ['id' => $target->id]);

        Sanctum::actingAs($this->user('agisadmin'));
        $this->putJson("/api/users/{$target->id}/password", [
            'password' => 'not-allowed',
            'password_confirmation' => 'not-allowed',
        ])->assertForbidden();
    }

    public function test_audit_areas_and_focuses_are_archived_and_restorable(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $area = AuditArea::query()->where('code', 'PROCUREMENT')->firstOrFail();
        $focus = $area->focuses()->firstOrFail();

        $this->deleteJson("/api/audit-focuses/{$focus->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Audit focus archived successfully.');
        $this->assertSoftDeleted('audit_focuses', ['id' => $focus->id]);

        $this->postJson("/api/audit-focuses/{$focus->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.auditFocus.isArchived', false);

        $this->deleteJson("/api/audit-areas/{$area->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Audit area archived successfully.');
        $this->assertSoftDeleted('audit_areas', ['id' => $area->id]);
        $this->assertSame(
            0,
            AuditFocus::query()->where('audit_area_id', $area->id)->count(),
        );

        $this->postJson("/api/audit-areas/{$area->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.auditArea.isArchived', false);
        $this->assertGreaterThan(
            0,
            AuditFocus::query()->where('audit_area_id', $area->id)->count(),
        );
    }

    public function test_administrator_can_create_a_master_list(): void
    {
        Sanctum::actingAs($this->user('agisadmin'));

        $this->postJson('/api/master-lists', [
            'code' => 'document category',
            'name' => 'Document Category',
            'description' => 'Categories used to organize shared documents.',
            'isActive' => true,
            'items' => [
                [
                    'code' => 'policy',
                    'label' => 'Policy',
                    'description' => 'Approved policy documents.',
                    'isActive' => true,
                ],
                [
                    'code' => 'working paper',
                    'label' => 'Working Paper',
                    'description' => 'Audit working papers.',
                    'isActive' => true,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.masterList.code', 'DOCUMENT_CATEGORY')
            ->assertJsonCount(2, 'data.masterList.items');

        $this->assertDatabaseHas('master_lists', [
            'code' => 'DOCUMENT_CATEGORY',
            'name' => 'Document Category',
        ]);
        $this->assertDatabaseHas('master_list_items', [
            'code' => 'WORKING_PAPER',
            'label' => 'Working Paper',
        ]);

        Sanctum::actingAs($this->user('auditor'));
        $this->postJson('/api/master-lists', [])->assertForbidden();
    }

    public function test_access_roles_can_be_created_archived_and_restored_only_without_assigned_users(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $permissionIds = Permission::query()
            ->whereIn('code', ['dashboard.view', 'documents.view'])
            ->pluck('id')
            ->all();

        $this->postJson('/api/roles', [
            'code' => 'regional reviewer',
            'name' => 'Regional Reviewer',
            'description' => 'Reviews authorized records for a designated region.',
            'isActive' => true,
            'permissionIds' => $permissionIds,
        ])
            ->assertCreated()
            ->assertJsonPath('data.role.code', 'regional_reviewer')
            ->assertJsonPath('data.role.usersCount', 0)
            ->assertJsonPath('data.role.isArchived', false)
            ->assertJsonCount(2, 'data.role.permissionIds');

        $role = Role::query()->where('code', 'regional_reviewer')->firstOrFail();
        $assignedRole = Role::query()->where('code', 'auditee_representative')->firstOrFail();

        $this->deleteJson("/api/roles/{$assignedRole->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
        $this->assertNotSoftDeleted('roles', ['id' => $assignedRole->id]);

        $this->deleteJson("/api/roles/{$role->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Access role archived successfully.');
        $this->assertSoftDeleted('roles', ['id' => $role->id]);

        $this->getJson('/api/roles?include_archived=1')
            ->assertOk()
            ->assertJsonFragment([
                'code' => 'regional_reviewer',
                'isArchived' => true,
            ]);

        $this->postJson("/api/roles/{$role->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.role.isActive', true)
            ->assertJsonPath('data.role.isArchived', false);
        $this->assertNotSoftDeleted('roles', ['id' => $role->id]);

        $this->assertDatabaseHas('activity_logs', ['action' => 'role.created']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'role.archived']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'role.restored']);
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
