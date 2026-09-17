<?php

namespace Tests\Feature\Api;

use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstanceEvent;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class WorkflowManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_workflow_definitions_are_versioned_published_and_immutable(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $payload = $this->definitionPayload();

        $response = $this->postJson('/api/workflows', $payload)
            ->assertCreated()
            ->assertJsonPath('data.workflow.status', 'DRAFT')
            ->assertJsonPath('data.workflow.version', 1)
            ->assertJsonCount(3, 'data.workflow.steps')
            ->assertJsonCount(2, 'data.workflow.transitions');
        $workflowId = $response->json('data.workflow.id');

        $this->postJson("/api/workflows/{$workflowId}/publish")
            ->assertOk()
            ->assertJsonPath('data.workflow.status', 'PUBLISHED')
            ->assertJsonPath('data.workflow.isImmutable', true);
        $this->putJson("/api/workflows/{$workflowId}", $payload)
            ->assertUnprocessable();

        $revision = $this->postJson("/api/workflows/{$workflowId}/revisions")
            ->assertCreated()
            ->assertJsonPath('data.workflow.status', 'DRAFT')
            ->assertJsonPath('data.workflow.version', 2)
            ->json('data.workflow');
        $this->assertNotSame($workflowId, $revision['id']);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'workflow.definition_published',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'workflow.definition_revised',
        ]);
    }

    public function test_engine_enforces_roles_separation_sla_locking_and_immutable_history(): void
    {
        $admin = $this->user('admin');
        Sanctum::actingAs($admin);
        $workflowId = $this->postJson('/api/workflows', $this->definitionPayload())
            ->assertCreated()
            ->json('data.workflow.id');
        $this->postJson("/api/workflows/{$workflowId}/publish")->assertOk();

        $office = Office::query()->where('code', 'CIAS')->firstOrFail();
        $instance = $this->postJson('/api/workflow-instances', [
            'workflowDefinitionId' => $workflowId,
            'subjectId' => 9001,
            'subjectCode' => 'TEST-2026-001',
            'subjectLabel' => 'Reusable workflow test record',
            'officeId' => $office->id,
            'context' => ['source' => 'automated-test'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.instance.currentStep.code', 'DRAFT')
            ->assertJsonPath('data.instance.lockVersion', 0)
            ->json('data.instance');
        $instanceId = $instance['id'];
        $this->assertNotNull($instance['dueAt']);

        $submitted = $this->postJson(
            "/api/workflow-instances/{$instanceId}/transitions/SUBMIT",
            ['lockVersion' => 0],
        )
            ->assertOk()
            ->assertJsonPath('data.instance.currentStep.code', 'REVIEW')
            ->assertJsonPath('data.instance.lockVersion', 1)
            ->json('data.instance');
        $this->assertEmpty($submitted['availableTransitions']);

        $this->postJson(
            "/api/workflow-instances/{$instanceId}/transitions/APPROVE",
            ['lockVersion' => 1, 'comment' => 'Self approval attempt.'],
        )->assertForbidden();

        Sanctum::actingAs($this->user('departmenthead'));
        $this->postJson(
            "/api/workflow-instances/{$instanceId}/transitions/APPROVE",
            ['lockVersion' => 0, 'comment' => 'Stale request.'],
        )->assertUnprocessable();
        $completed = $this->postJson(
            "/api/workflow-instances/{$instanceId}/transitions/APPROVE",
            ['lockVersion' => 1, 'comment' => 'Reviewed and approved.'],
        )
            ->assertOk()
            ->assertJsonPath('data.instance.status', 'COMPLETED')
            ->assertJsonPath('data.instance.currentStep.code', 'APPROVED')
            ->assertJsonCount(3, 'data.instance.events')
            ->json('data.instance');
        $this->assertNotNull($completed['completedAt']);

        $event = WorkflowInstanceEvent::query()->where('action_code', 'SUBMIT')->firstOrFail();
        $this->expectException(LogicException::class);
        $event->update(['comment' => 'History rewrite attempt']);
    }

    public function test_active_instances_block_archive_and_access_is_permission_scoped(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $definition = WorkflowDefinition::query()
            ->where('code', 'CORE_DOCUMENT_REVIEW')
            ->firstOrFail();
        $this->postJson('/api/workflow-instances', [
            'workflowDefinitionId' => $definition->id,
            'subjectCode' => 'DOC-TEST-001',
            'subjectLabel' => 'Document awaiting review',
        ])->assertCreated();

        $this->deleteJson("/api/workflows/{$definition->id}")
            ->assertUnprocessable();
        $this->assertDatabaseHas('workflow_instances', [
            'workflow_definition_id' => $definition->id,
            'status' => 'ACTIVE',
        ]);

        Sanctum::actingAs($this->user('auditee'));
        $this->getJson('/api/workflows')->assertForbidden();
        $this->postJson('/api/workflow-instances', [
            'workflowDefinitionId' => $definition->id,
            'subjectCode' => 'DOC-TEST-002',
            'subjectLabel' => 'Unauthorized start',
        ])->assertForbidden();

        Sanctum::actingAs($this->user('agisadmin'));
        $this->getJson('/api/workflows')
            ->assertOk()
            ->assertJsonPath('data.summary.published', 2);
    }

    public function test_workflow_reports_reviewer_and_approver_users_from_permissions(): void
    {
        $admin = $this->user('admin');
        $management = $this->user('departmenthead');
        Sanctum::actingAs($admin);

        $workflow = $this->getJson('/api/workflows')
            ->assertOk()
            ->json('data.definitions');
        $core = collect($workflow)->firstWhere('code', 'CORE_DOCUMENT_REVIEW');
        $publish = collect($core['transitions'])->firstWhere('code', 'PUBLISH');

        $this->assertSame('documents.approve', $publish['authorization']['permission']['code']);
        $this->assertSame('APPROVER', $publish['authorization']['type']);

        $instanceId = $this->postJson('/api/workflow-instances', [
            'workflowDefinitionId' => $core['id'],
            'subjectCode' => 'DOC-AUTHORITY-001',
            'subjectLabel' => 'Permission authority test document',
        ])
            ->assertCreated()
            ->json('data.instance.id');

        $this->postJson("/api/workflow-instances/{$instanceId}/transitions/SUBMIT", [
            'lockVersion' => 0,
        ])->assertOk();

        Sanctum::actingAs($management);
        $instance = $this->getJson("/api/workflow-instances/{$instanceId}")
            ->assertOk()
            ->json('data.instance');
        $availablePublish = collect($instance['availableTransitions'])->firstWhere('code', 'PUBLISH');

        $this->assertSame('documents.approve', $availablePublish['authorization']['permission']['code']);
        $this->assertContains($management->id, collect($availablePublish['authorization']['users'])->pluck('id')->all());
        $this->assertNotContains($admin->id, collect($availablePublish['authorization']['users'])->pluck('id')->all());
    }

    /** @return array<string, mixed> */
    private function definitionPayload(): array
    {
        $managementRole = Role::query()->where('code', 'cias_management')->firstOrFail();
        $actPermission = Permission::query()->where('code', 'workflows.act')->firstOrFail();

        return [
            'code' => 'TEST_CONTROLLED_APPROVAL',
            'name' => 'Test Controlled Approval',
            'moduleCode' => 'CORE',
            'subjectType' => 'TEST_RECORD',
            'description' => 'A test workflow with SLA, permission, and separation controls.',
            'steps' => [
                [
                    'code' => 'DRAFT',
                    'name' => 'Draft',
                    'stepType' => 'START',
                    'slaHours' => 48,
                    'responsibleRoleId' => null,
                    'instructions' => 'Prepare the record.',
                ],
                [
                    'code' => 'REVIEW',
                    'name' => 'Management Review',
                    'stepType' => 'INTERMEDIATE',
                    'slaHours' => 24,
                    'responsibleRoleId' => $managementRole->id,
                    'instructions' => 'Review the record.',
                ],
                [
                    'code' => 'APPROVED',
                    'name' => 'Approved',
                    'stepType' => 'END',
                    'slaHours' => null,
                    'responsibleRoleId' => null,
                    'instructions' => null,
                ],
            ],
            'transitions' => [
                [
                    'code' => 'SUBMIT',
                    'name' => 'Submit for review',
                    'fromStepCode' => 'DRAFT',
                    'toStepCode' => 'REVIEW',
                    'actorRoleId' => null,
                    'requiredPermissionId' => $actPermission->id,
                    'requiresComment' => false,
                    'enforceSeparationOfDuties' => false,
                    'isActive' => true,
                ],
                [
                    'code' => 'APPROVE',
                    'name' => 'Approve record',
                    'fromStepCode' => 'REVIEW',
                    'toStepCode' => 'APPROVED',
                    'actorRoleId' => $managementRole->id,
                    'requiredPermissionId' => $actPermission->id,
                    'requiresComment' => true,
                    'enforceSeparationOfDuties' => true,
                    'isActive' => true,
                ],
            ],
        ];
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
