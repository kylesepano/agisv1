<?php

namespace Tests\Feature\Api;

use App\Models\SystemNotification;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Services\NotificationReminderService;
use App\Services\NotificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_users_can_manage_only_their_notification_inbox(): void
    {
        $auditor = $this->user('auditor');
        $otherNotification = $this->user('departmenthead')
            ->systemNotifications()
            ->firstOrFail();
        Sanctum::actingAs($auditor);

        $response = $this->getJson('/api/notifications?perPage=10')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.unread', 2)
            ->assertJsonCount(2, 'data.notifications')
            ->assertJsonPath('data.preferences.inAppEnabled', true);
        $notificationId = $response->json('data.notifications.0.id');

        $this->postJson("/api/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('data.notification.isRead', true);
        $this->postJson("/api/notifications/{$notificationId}/unread")
            ->assertOk()
            ->assertJsonPath('data.notification.isRead', false);
        $this->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 2);

        $this->deleteJson("/api/notifications/{$notificationId}")
            ->assertOk();
        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'recipient_id' => $auditor->id,
        ]);
        $this->getJson('/api/notifications?includeArchived=1')
            ->assertOk()
            ->assertJsonPath('data.summary.archived', 1);
        $this->postJson("/api/notifications/{$notificationId}/restore")
            ->assertOk()
            ->assertJsonPath('data.notification.isArchived', false);

        $this->postJson("/api/notifications/{$otherNotification->id}/read")
            ->assertForbidden();
    }

    public function test_preferences_control_delivery_and_administrator_can_target_users(): void
    {
        $auditor = $this->user('auditor');
        Sanctum::actingAs($auditor);
        $this->putJson('/api/notifications/preferences', [
            'inAppEnabled' => true,
            'workflowEnabled' => true,
            'assignmentsEnabled' => false,
            'dueDatesEnabled' => true,
            'systemEnabled' => true,
            'emailEnabled' => false,
            'digestFrequency' => 'DAILY',
            'quietHoursStart' => '18:00',
            'quietHoursEnd' => '07:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.preferences.assignmentsEnabled', false)
            ->assertJsonPath('data.preferences.digestFrequency', 'DAILY');

        $service = app(NotificationService::class);
        $this->assertCount(0, $service->send([$auditor], [
            'type' => 'TEST_ASSIGNMENT',
            'category' => 'ASSIGNMENT',
            'moduleCode' => 'IAP',
            'title' => 'Suppressed assignment',
            'message' => 'This notification should be suppressed by preferences.',
        ]));
        $this->assertCount(1, $service->send([$auditor], [
            'type' => 'TEST_DUE',
            'category' => 'DUE_DATE',
            'moduleCode' => 'IAP',
            'title' => 'Allowed deadline',
            'message' => 'This deadline remains enabled.',
            'dedupeKey' => 'test:allowed-deadline',
        ]));

        Sanctum::actingAs($this->user('admin'));
        $this->postJson('/api/notifications', [
            'targetType' => 'USER',
            'userIds' => [$auditor->id],
            'category' => 'SYSTEM',
            'priority' => 'HIGH',
            'moduleCode' => 'CORE',
            'title' => 'Policy advisory',
            'message' => 'A new internal audit policy reference is available.',
            'actionUrl' => '/document-management',
            'actionLabel' => 'View document',
        ])
            ->assertCreated()
            ->assertJsonPath('data.delivered', 1);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $auditor->id,
            'title' => 'Policy advisory',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'notification.delivered',
        ]);

        Sanctum::actingAs($this->user('auditee'));
        $this->postJson('/api/notifications', [
            'targetType' => 'ALL',
            'category' => 'SYSTEM',
            'priority' => 'NORMAL',
            'moduleCode' => 'CORE',
            'title' => 'Unauthorized message',
            'message' => 'This must not be delivered.',
        ])->assertForbidden();
    }

    public function test_due_reminders_are_idempotent_and_header_endpoint_is_live(): void
    {
        $admin = $this->user('admin');
        $definition = WorkflowDefinition::query()
            ->where('code', 'CORE_DOCUMENT_REVIEW')
            ->firstOrFail();
        Sanctum::actingAs($admin);
        $instanceId = $this->postJson('/api/workflow-instances', [
            'workflowDefinitionId' => $definition->id,
            'subjectCode' => 'DOC-DUE-001',
            'subjectLabel' => 'Document with an overdue workflow step',
        ])
            ->assertCreated()
            ->json('data.instance.id');
        WorkflowInstance::query()->whereKey($instanceId)->update([
            'due_at' => now()->subDay(),
        ]);

        $reminders = app(NotificationReminderService::class);
        $reminders->dispatch();
        $firstCount = SystemNotification::query()
            ->where('dedupe_key', "workflow:{$instanceId}:due:".now()->subDay()->toDateString().':overdue')
            ->count();
        $reminders->dispatch();
        $secondCount = SystemNotification::query()
            ->where('dedupe_key', "workflow:{$instanceId}:due:".now()->subDay()->toDateString().':overdue')
            ->count();
        $this->assertGreaterThanOrEqual(1, $firstCount);
        $this->assertSame($firstCount, $secondCount);

        $this->getJson('/api/notifications/recent')
            ->assertOk()
            ->assertJsonPath('data.unreadCount', fn ($value): bool => $value >= 1)
            ->assertJsonPath(
                'data.notifications.0.id',
                fn ($value): bool => is_int($value),
            );
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
