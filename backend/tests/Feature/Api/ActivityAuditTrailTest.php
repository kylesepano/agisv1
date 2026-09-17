<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\AuditArea;
use App\Models\AuditFocus;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_activity_and_audit_registries_filter_paginate_and_show_changes(): void
    {
        $admin = $this->user('admin');
        $office = Office::query()->firstOrFail();
        AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'office.updated',
            'auditable_type' => Office::class,
            'auditable_id' => $office->id,
            'old_values' => ['name' => 'Old office name'],
            'new_values' => ['name' => $office->name],
            'ip_address' => '127.0.0.1',
            'metadata' => ['module' => 'CORE'],
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/activity-logs?module=CORE&search=notification&perPage=10')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'activityLogs',
                    'pagination' => ['currentPage', 'lastPage', 'perPage', 'total'],
                    'summary' => ['total', 'today', 'actors', 'security'],
                    'options' => ['modules', 'actions', 'users'],
                ],
            ]);

        $this->getJson('/api/audit-logs?module=CORE&action=office.updated&recordType='.urlencode(Office::class))
            ->assertOk()
            ->assertJsonPath('data.auditLogs.0.recordType', 'Office')
            ->assertJsonPath('data.auditLogs.0.recordId', $office->id)
            ->assertJsonPath('data.auditLogs.0.oldValues.name', 'Old office name')
            ->assertJsonPath('data.auditLogs.0.newValues.name', $office->name)
            ->assertJsonPath('data.summary.changedRecords', 1);
    }

    public function test_audit_trail_resolves_reference_ids_to_display_values_without_changing_raw_values(): void
    {
        $admin = $this->user('admin');
        $office = Office::query()->where('code', 'BPLD')->firstOrFail();
        $area = AuditArea::query()->firstOrFail();
        $focus = AuditFocus::query()->where('audit_area_id', $area->id)->firstOrFail();
        AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'aems.engagement.scope_updated',
            'auditable_type' => \App\Models\AuditEngagement::class,
            'auditable_id' => 1,
            'old_values' => [
                'officeIds' => [],
                'engagementOfficeId' => null,
                'auditAreaIds' => [],
                'auditFocusIds' => [],
            ],
            'new_values' => [
                'officeIds' => [$office->id],
                'engagementOfficeId' => $office->id,
                'auditAreaIds' => [$area->id],
                'auditFocusIds' => [$focus->id],
            ],
            'metadata' => ['module' => 'AEMS'],
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/audit-logs?action=aems.engagement.scope_updated')
            ->assertOk()
            ->assertJsonPath('data.auditLogs.0.newValues.officeIds.0', $office->id)
            ->assertJsonPath('data.auditLogs.0.displayNewValues.officeIds.0', $office->code.' — '.$office->name)
            ->assertJsonPath('data.auditLogs.0.displayNewValues.engagementOfficeId', $office->code.' — '.$office->name)
            ->assertJsonPath('data.auditLogs.0.displayNewValues.auditAreaIds.0', $area->code.' — '.$area->name)
            ->assertJsonPath('data.auditLogs.0.displayNewValues.auditFocusIds.0', $focus->code.' — '.$focus->name);
    }

    public function test_log_exports_are_permission_protected_and_available_in_all_formats(): void
    {
        Sanctum::actingAs($this->user('admin'));
        foreach (['csv', 'excel', 'pdf', 'print'] as $format) {
            $this->get("/api/activity-logs/export?format={$format}")->assertOk();
            $this->get("/api/audit-logs/export?format={$format}")->assertOk();
        }
        $this->assertDatabaseHas('activity_logs', ['action' => 'activity_log.exported']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'audit_trail.exported']);

        Sanctum::actingAs($this->user('mayor'));
        $this->getJson('/api/activity-logs')->assertForbidden();
        $this->getJson('/api/audit-logs')->assertForbidden();
        $this->get('/api/audit-logs/export?format=csv')->assertForbidden();
    }

    public function test_seeded_notifications_reference_existing_records_and_deep_links(): void
    {
        foreach (['agisadmin', 'departmenthead', 'auditor', 'mayor'] as $username) {
            $notification = $this->user($username)->systemNotifications()
                ->whereNotNull('subject_id')
                ->firstOrFail();
            $this->assertNotEmpty($notification->action_url);
            $this->assertTrue(class_exists($notification->subject_type));
            $this->assertNotNull(
                $notification->subject_type::query()->find($notification->subject_id),
            );
        }
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
