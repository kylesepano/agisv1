<?php

namespace Tests\Feature\Api;

use App\Models\InternalAuditPlan;
use App\Models\MasterListItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\IapSchedulingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IapSupportingRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_supporting_files_are_linked_downloadable_archivable_and_recoverable(): void
    {
        $plan = $this->plan();
        $engagement = $plan->engagements()->firstOrFail();
        Sanctum::actingAs($this->user('departmenthead'));

        $attachment = $this->post(
            "/api/iap/plans/{$plan->id}/attachments",
            [
                'file' => UploadedFile::fake()->create(
                    'capacity-calculation.xlsx',
                    80,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ),
                'attachmentTypeId' => $this->item(
                    'IAP_ATTACHMENT_TYPE',
                    'BUDGET_RESOURCE_SUPPORT',
                )->id,
                'displayName' => '2026 auditor capacity calculation',
                'description' => 'Required versus available person-days for the approved planning period.',
                'visibility' => 'INTERNAL',
                'planEngagementId' => $engagement->id,
            ],
            ['Accept' => 'application/json'],
        )
            ->assertCreated()
            ->assertJsonPath('data.attachment.attachmentType.code', 'BUDGET_RESOURCE_SUPPORT')
            ->assertJsonPath('data.attachment.engagement.id', $engagement->id)
            ->json('data.attachment');

        $this->assertDatabaseHas('documents', [
            'title' => '2026 auditor capacity calculation',
            'owner_module' => 'IAP',
            'library_visible' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'iap.supporting_attachment.created',
        ]);

        $this->getJson("/api/iap/plans/{$plan->id}/supporting-records")
            ->assertOk()
            ->assertJsonCount(1, 'data.attachments')
            ->assertJsonPath('data.attachments.0.displayName', '2026 auditor capacity calculation')
            ->assertJsonPath('data.capabilities.canUpload', true);

        $this->get("/api/iap/plans/{$plan->id}/attachments/{$attachment['id']}/download")
            ->assertOk();

        $this->deleteJson("/api/iap/plans/{$plan->id}/attachments/{$attachment['id']}")
            ->assertOk();
        $this->assertSoftDeleted('iap_attachments', ['id' => $attachment['id']]);

        $this->getJson("/api/iap/plans/{$plan->id}/supporting-records?includeArchived=1")
            ->assertOk()
            ->assertJsonPath('data.attachments.0.isArchived', true);

        $this->postJson("/api/iap/plans/{$plan->id}/attachments/{$attachment['id']}/restore")
            ->assertOk();
        $this->assertDatabaseHas('iap_attachments', [
            'id' => $attachment['id'],
            'deleted_at' => null,
        ]);
    }

    public function test_management_visibility_and_review_comments_are_enforced(): void
    {
        $plan = $this->plan();
        Sanctum::actingAs($this->user('departmenthead'));

        $this->post(
            "/api/iap/plans/{$plan->id}/attachments",
            [
                'file' => UploadedFile::fake()->create('management-directive.pdf', 32, 'application/pdf'),
                'attachmentTypeId' => $this->item(
                    'IAP_ATTACHMENT_TYPE',
                    'MANAGEMENT_DIRECTIVE',
                )->id,
                'displayName' => 'Confidential management directive',
                'visibility' => 'MANAGEMENT',
            ],
            ['Accept' => 'application/json'],
        )->assertCreated();

        Sanctum::actingAs($this->user('auditor'));
        $this->getJson("/api/iap/plans/{$plan->id}/supporting-records")
            ->assertOk()
            ->assertJsonCount(0, 'data.attachments')
            ->assertJsonPath('data.capabilities.canViewArchived', false);

        $plan->forceFill(['status' => 'PENDING_REVIEW'])->save();
        Sanctum::actingAs($this->user('departmenthead'));
        $comment = $this->postJson("/api/iap/plans/{$plan->id}/comments", [
            'body' => 'Confirm that the final person-day allocation agrees with ARMIS capacity.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.comment.commentType.code', 'REVIEW')
            ->assertJsonPath('data.comment.isImmutable', true)
            ->json('data.comment');

        $this->assertDatabaseHas('iap_comments', [
            'id' => $comment['id'],
            'is_immutable' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'iap.reviewer_comment.created',
        ]);

        Sanctum::actingAs($this->user('auditor'));
        $this->postJson("/api/iap/plans/{$plan->id}/comments", [
            'body' => 'An auditor must not create formal reviewer comments.',
        ])->assertForbidden();
    }

    public function test_approved_plan_supporting_records_are_frozen(): void
    {
        $plan = $this->plan();
        $plan->forceFill(['status' => 'APPROVED'])->save();
        Sanctum::actingAs($this->user('departmenthead'));

        $this->post(
            "/api/iap/plans/{$plan->id}/attachments",
            [
                'file' => UploadedFile::fake()->create('late-file.pdf', 16, 'application/pdf'),
                'attachmentTypeId' => $this->item('IAP_ATTACHMENT_TYPE', 'APPROVAL_SUPPORT')->id,
                'displayName' => 'Late approval file',
                'visibility' => 'INTERNAL',
            ],
            ['Accept' => 'application/json'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->getJson("/api/iap/plans/{$plan->id}/supporting-records")
            ->assertOk()
            ->assertJsonPath('data.capabilities.isFrozen', true)
            ->assertJsonPath('data.capabilities.canUpload', false)
            ->assertJsonPath('data.capabilities.canComment', false);
    }

    private function plan(): InternalAuditPlan
    {
        return InternalAuditPlan::query()
            ->where('plan_code', IapSchedulingSeeder::DEMO_PLAN_CODE)
            ->firstOrFail();
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
