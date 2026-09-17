<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditLog;
use App\Models\AuditRecommendation;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Models\CmsActionPlanMilestone;
use App\Models\CmsActionPlanVersion;
use App\Models\CmsCorrectiveActionPlan;
use App\Models\CmsRecommendation;
use App\Models\CmsRecommendationAssignment;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\Permission;
use App\Models\SystemNotification;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class CmsActionPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    public function test_schema_constraints_permissions_and_role_separation_are_enforced(): void
    {
        $this->assertTrue(Schema::hasTable('cms_corrective_action_plans'));
        $this->assertTrue(Schema::hasTable('cms_action_plan_versions'));
        $this->assertTrue(Schema::hasTable('cms_action_plan_milestones'));
        $this->assertSame(
            8,
            Permission::query()->where('code', 'like', 'cms.action-plan.%')->count(),
        );

        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $auditee = $this->user('auditee');
        $platform = $this->user('admin');
        $administrator = $this->user('agisadmin');
        $readOnly = $this->user('mayor');

        $this->assertTrue($management->hasPermission('cms.action-plan.accept'));
        $this->assertTrue($auditor->hasPermission('cms.action-plan.review'));
        $this->assertTrue($auditor->hasPermission('cms.action-plan.accept'));
        $this->assertTrue($auditee->hasPermission('cms.action-plan.create'));
        $this->assertTrue($auditee->hasPermission('cms.action-plan.submit'));
        $this->assertTrue($readOnly->hasPermission('cms.action-plan.view'));
        $this->assertFalse($platform->hasPermission('cms.action-plan.accept'));
        $this->assertFalse($administrator->hasPermission('cms.action-plan.accept'));

        $case = $this->case('SCHEMA', $auditee->office);
        $plan = $this->createPlan($case, $auditee);

        try {
            CmsCorrectiveActionPlan::query()->create([
                'cms_recommendation_case_id' => $case->id,
                'owner_office_id' => $auditee->office_id,
                'created_by' => $auditee->id,
            ]);
            $this->fail('Only one Action Plan family should exist per case.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        try {
            CmsActionPlanVersion::query()->create([
                'cms_corrective_action_plan_id' => $plan->id,
                'version_number' => 1,
                'status_code' => 'DRAFT',
                'owner_office_id' => $auditee->office_id,
                'prepared_by' => $auditee->id,
            ]);
            $this->fail('Family version numbers must be unique.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        try {
            CmsActionPlanVersion::query()->create([
                'cms_corrective_action_plan_id' => $plan->id,
                'version_number' => 2,
                'status_code' => 'DRAFT',
                'owner_office_id' => $auditee->office_id,
                'prepared_by' => $auditee->id,
            ]);
            $this->fail('Only one active mutable or in-review version should exist.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    public function test_responsible_office_creates_and_updates_only_a_valid_current_draft(): void
    {
        $auditee = $this->user('auditee');
        $case = $this->case('DRAFT', $auditee->office);
        $plan = $this->createPlan($case, $auditee);
        $version = $plan->currentVersion;

        $this->assertSame('FOR_ACTION_PLAN', $case->fresh()->status_code);
        $this->assertSame($auditee->office_id, $plan->owner_office_id);
        $this->assertSame(1, $version->version_number);
        $this->assertSame('DRAFT', $version->status_code);
        $this->assertCount(2, $version->milestones);

        Sanctum::actingAs($auditee);
        $payload = $this->payload($auditee, $case);
        $payload['lockVersion'] = $version->lock_version;
        $payload['planSummary'] = 'Updated management commitment.';
        $payload['milestones'] = array_reverse($payload['milestones']);
        $payload['milestones'][0]['displayOrder'] = 1;
        $payload['milestones'][1]['displayOrder'] = 2;
        $this->putJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$version->id}",
            $payload,
        )->assertOk()
            ->assertJsonPath(
                'data.actionPlan.currentVersion.planSummary',
                'Updated management commitment.',
            );

        $fresh = $version->fresh();
        $this->putJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$version->id}",
            ['lockVersion' => $version->lock_version, 'planSummary' => 'Stale overwrite.'],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('lockVersion');

        $invalidWeights = $this->payload($auditee, $case);
        $invalidWeights['lockVersion'] = $fresh->lock_version;
        $invalidWeights['milestones'][0]['weightPercentage'] = 50;
        $invalidWeights['milestones'][1]['weightPercentage'] = null;
        $this->putJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$version->id}",
            $invalidWeights,
        )->assertUnprocessable()
            ->assertJsonValidationErrors('milestones');

        $otherOfficeUser = User::query()
            ->where('office_id', '!=', $auditee->office_id)
            ->whereHas('role', fn ($role) => $role->where('code', 'auditee_representative'))
            ->firstOrFail();
        Sanctum::actingAs($otherOfficeUser);
        $this->postJson(
            "/api/cms/recommendations/{$case->id}/action-plans",
            $this->payload($otherOfficeUser, $case),
        )->assertNotFound();

        Sanctum::actingAs($this->user('auditor'));
        $this->postJson(
            "/api/cms/recommendations/{$case->id}/action-plans",
            $this->payload($auditee, $case),
        )->assertForbidden();
    }

    public function test_submission_review_return_and_revision_preserve_immutable_history(): void
    {
        $auditee = $this->user('auditee');
        $reviewer = $this->user('auditor');
        $case = $this->case('RETURN', $auditee->office);
        $this->assignMonitor($case, $reviewer);
        $plan = $this->createPlan($case, $auditee);
        $version = $plan->currentVersion;

        Sanctum::actingAs($auditee);
        $this->postJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$version->id}/transitions/submit",
            ['lockVersion' => 1, 'confirmation' => true],
        )->assertOk()
            ->assertJsonPath('data.actionPlan.currentVersion.status', 'SUBMITTED')
            ->assertJsonPath(
                'data.actionPlan.currentVersion.hasSubmissionSnapshot',
                true,
            );

        $submitted = $version->fresh();
        $this->assertNotNull($submitted->submitted_at);
        $this->assertNotNull($submitted->submission_snapshot);
        $this->putJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$version->id}",
            ['lockVersion' => $submitted->lock_version, 'planSummary' => 'Rewrite.'],
        )->assertUnprocessable();

        Sanctum::actingAs($reviewer);
        $this->postJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$version->id}/transitions/start-review",
            ['lockVersion' => $submitted->lock_version],
        )->assertOk()
            ->assertJsonPath('data.actionPlan.currentVersion.status', 'UNDER_REVIEW');
        $reviewing = $version->fresh();
        $this->postJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$version->id}/transitions/return",
            ['lockVersion' => $reviewing->lock_version],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('returnReason');
        $this->postJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$version->id}/transitions/return",
            [
                'lockVersion' => $reviewing->lock_version,
                'returnReason' => 'Define a clearer verification method.',
            ],
        )->assertOk()
            ->assertJsonPath('data.actionPlan.currentVersion.status', 'RETURNED');

        Sanctum::actingAs($auditee);
        $returned = $version->fresh();
        $this->putJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$version->id}",
            ['lockVersion' => $returned->lock_version, 'planSummary' => 'Rewrite.'],
        )->assertUnprocessable();
        $revisionResponse = $this->postJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$version->id}/revisions",
            [
                'lockVersion' => $returned->lock_version,
                'revisionReason' => 'Address compliance review instructions.',
            ],
        )->assertCreated()
            ->assertJsonPath('data.actionPlan.currentVersion.versionNumber', 2)
            ->assertJsonPath('data.actionPlan.currentVersion.status', 'DRAFT');

        $revisionId = $revisionResponse->json('data.actionPlan.currentVersion.id');
        $this->assertSame(
            $version->milestones()->count(),
            CmsActionPlanMilestone::query()
                ->where('cms_action_plan_version_id', $revisionId)
                ->count(),
        );
        $this->assertSame('RETURNED', $version->fresh()->status_code);
        $this->assertDatabaseHas('cms_recommendation_events', [
            'event_code' => 'ACTION_PLAN_REVISION_CREATED',
        ]);
        $this->assertGreaterThanOrEqual(
            5,
            ActivityLog::query()->where('action', 'like', 'cms.action-plan.%')->count(),
        );
        $this->assertGreaterThanOrEqual(
            5,
            AuditLog::query()->where('action', 'like', 'cms.action-plan.%')->count(),
        );
        $this->assertGreaterThanOrEqual(
            3,
            SystemNotification::query()->where('module_code', 'CMS')->count(),
        );
    }

    public function test_completeness_weight_dates_user_and_separation_guards_are_enforced(): void
    {
        $auditee = $this->user('auditee');
        $management = $this->user('departmenthead');
        $case = $this->case('VALIDATE', $auditee->office);

        Sanctum::actingAs($auditee);
        $create = $this->postJson(
            "/api/cms/recommendations/{$case->id}/action-plans",
            ['lockVersion' => 1],
        )->assertCreated();
        $planId = $create->json('data.actionPlan.id');
        $versionId = $create->json('data.actionPlan.currentVersion.id');

        $this->postJson(
            "/api/cms/action-plans/{$planId}/versions/{$versionId}/transitions/submit",
            ['lockVersion' => 1, 'confirmation' => true],
        )->assertUnprocessable()
            ->assertJsonValidationErrors([
                'planSummary',
                'implementationStrategy',
                'expectedOutcome',
                'focalUserId',
                'milestones',
            ]);
        $this->assertDatabaseMissing('notifications', [
            'type' => 'CMS_ACTION_PLAN_SUBMITTED',
        ]);

        $payload = $this->payload($auditee, $case);
        $payload['lockVersion'] = 1;
        $payload['milestones'][1]['sequenceNumber'] = 1;
        $this->putJson(
            "/api/cms/action-plans/{$planId}/versions/{$versionId}",
            $payload,
        )->assertUnprocessable()
            ->assertJsonValidationErrors('milestones.1.sequenceNumber');

        $payload = $this->payload($auditee, $case);
        $payload['lockVersion'] = 1;
        $payload['milestones'][0]['plannedStartDate'] = now()->addDays(12)->toDateString();
        $payload['milestones'][0]['plannedTargetDate'] = now()->addDays(10)->toDateString();
        $this->putJson(
            "/api/cms/action-plans/{$planId}/versions/{$versionId}",
            $payload,
        )->assertUnprocessable()
            ->assertJsonValidationErrors('milestones.0.plannedTargetDate');

        $payload = $this->payload($auditee, $case);
        $payload['lockVersion'] = 1;
        $payload['focalUserId'] = $management->id;
        $this->putJson(
            "/api/cms/action-plans/{$planId}/versions/{$versionId}",
            $payload,
        )->assertUnprocessable()
            ->assertJsonValidationErrors('focalUserId');

        $payload = $this->payload($auditee, $case);
        $payload['lockVersion'] = 1;
        $payload['plannedTargetDate'] = now()->addMonths(2)->toDateString();
        $this->putJson(
            "/api/cms/action-plans/{$planId}/versions/{$versionId}",
            $payload,
        )->assertUnprocessable()
            ->assertJsonValidationErrors('plannedTargetDate');

        $payload = $this->payload($auditee, $case);
        $payload['lockVersion'] = 1;
        foreach ($payload['milestones'] as &$milestone) {
            $milestone['weightPercentage'] = null;
        }
        unset($milestone);
        $this->putJson(
            "/api/cms/action-plans/{$planId}/versions/{$versionId}",
            $payload,
        )->assertOk()
            ->assertJsonPath(
                'data.actionPlan.currentVersion.completeness.weightingUsed',
                false,
            );

        $version = CmsActionPlanVersion::query()->findOrFail($versionId);
        $this->postJson(
            "/api/cms/action-plans/{$planId}/versions/{$versionId}/transitions/submit",
            ['lockVersion' => $version->lock_version, 'confirmation' => true],
        )->assertOk();

        Sanctum::actingAs($auditee);
        $this->postJson(
            "/api/cms/action-plans/{$planId}/versions/{$versionId}/transitions/start-review",
            ['lockVersion' => $version->fresh()->lock_version],
        )->assertForbidden();

        Sanctum::actingAs($management);
        $this->postJson(
            "/api/cms/action-plans/{$planId}/versions/{$versionId}/transitions/start-review",
            ['lockVersion' => $version->fresh()->lock_version],
        )->assertOk();
        $this->postJson(
            "/api/cms/action-plans/{$planId}/versions/{$versionId}/transitions/accept",
            [
                'lockVersion' => $version->fresh()->lock_version,
                'confirmation' => true,
            ],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('acceptanceComment');
    }

    public function test_independent_acceptance_establishes_and_replaces_the_official_baseline(): void
    {
        $auditee = $this->user('auditee');
        $reviewer = $this->user('auditor');
        $case = $this->case('ACCEPT', $auditee->office);
        $this->assignMonitor($case, $reviewer);
        $plan = $this->createPlan($case, $auditee);
        $first = $plan->currentVersion;
        $this->submitAndReview($plan, $first, $auditee, $reviewer);

        $auditee->role->permissions()->syncWithoutDetaching(
            Permission::query()
                ->where('code', 'cms.action-plan.accept')
                ->value('id'),
        );
        Sanctum::actingAs($auditee);
        $this->postJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$first->id}/transitions/accept",
            [
                'lockVersion' => $first->fresh()->lock_version,
                'acceptanceComment' => 'Attempted self-acceptance.',
                'confirmation' => true,
            ],
        )->assertForbidden();

        Sanctum::actingAs($reviewer);
        $this->postJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$first->id}/transitions/accept",
            [
                'lockVersion' => $first->fresh()->lock_version,
                'acceptanceComment' => 'Complete and responsive to the recommendation.',
                'confirmation' => true,
            ],
        )->assertOk()
            ->assertJsonPath('data.actionPlan.currentVersion.status', 'ACCEPTED')
            ->assertJsonPath('data.actionPlan.caseContext.status', 'MONITORING');

        $this->assertSame('MONITORING', $case->fresh()->status_code);
        $this->assertSame($first->id, $plan->fresh()->accepted_version_id);
        $acceptedEventCount = CmsRecommendationEvent::query()
            ->where('event_code', 'ACTION_PLAN_ACCEPTED')
            ->count();
        $this->postJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$first->id}/transitions/accept",
            [
                'lockVersion' => $first->fresh()->lock_version,
                'acceptanceComment' => 'Duplicate acceptance.',
                'confirmation' => true,
            ],
        )->assertUnprocessable();
        $this->assertSame(
            $acceptedEventCount,
            CmsRecommendationEvent::query()
                ->where('event_code', 'ACTION_PLAN_ACCEPTED')
                ->count(),
        );
        try {
            $first->fresh()->forceFill(['plan_summary' => 'Rewrite accepted content.'])->save();
            $this->fail('Accepted plan content must remain immutable.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        Sanctum::actingAs($auditee);
        $revisionResponse = $this->postJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$first->id}/revisions",
            [
                'lockVersion' => $first->fresh()->lock_version,
                'revisionReason' => 'Update the implementation approach.',
            ],
        )->assertCreated();
        $secondId = $revisionResponse->json('data.actionPlan.currentVersion.id');
        $second = CmsActionPlanVersion::query()->findOrFail($secondId);
        $this->assertSame('MONITORING', $case->fresh()->status_code);
        $this->assertSame($first->id, $plan->fresh()->accepted_version_id);

        $this->submitAndReview($plan->fresh(), $second, $auditee, $reviewer);
        Sanctum::actingAs($reviewer);
        $this->postJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$second->id}/transitions/accept",
            [
                'lockVersion' => $second->fresh()->lock_version,
                'acceptanceComment' => 'Revised baseline accepted.',
                'confirmation' => true,
            ],
        )->assertOk()
            ->assertJsonPath('data.actionPlan.acceptedVersionId', $second->id);

        $this->getJson("/api/cms/action-plans/{$plan->id}")
            ->assertOk()
            ->assertJsonFragment([
                'id' => $first->id,
                'status' => 'ACCEPTED',
                'isSuperseded' => true,
            ]);
        $this->assertSame('ACCEPTED', $first->fresh()->status_code);
        $this->assertSame($second->id, $plan->fresh()->accepted_version_id);
    }

    public function test_acceptance_failure_rolls_back_the_entire_transition(): void
    {
        $auditee = $this->user('auditee');
        $reviewer = $this->user('auditor');
        $case = $this->case('ROLLBACK', $auditee->office);
        $this->assignMonitor($case, $reviewer);
        $plan = $this->createPlan($case, $auditee);
        $version = $plan->currentVersion;
        $this->submitAndReview($plan, $version, $auditee, $reviewer);

        $eventCount = CmsRecommendationEvent::query()->count();
        $activityCount = ActivityLog::query()->count();
        $auditCount = AuditLog::query()->count();
        $notificationCount = SystemNotification::query()->count();

        CmsRecommendationEvent::creating(
            function (CmsRecommendationEvent $event): void {
                if ($event->event_code === CmsRecommendationEvent::EVENT_ACTION_PLAN_ACCEPTED) {
                    throw new \RuntimeException('Simulated event persistence failure.');
                }
            },
        );

        Sanctum::actingAs($reviewer);
        $this->withoutExceptionHandling();

        try {
            $this->postJson(
                "/api/cms/action-plans/{$plan->id}/versions/{$version->id}/transitions/accept",
                [
                    'lockVersion' => $version->fresh()->lock_version,
                    'acceptanceComment' => 'This must be rolled back.',
                    'confirmation' => true,
                ],
            );
            $this->fail('The simulated persistence failure should escape the request.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Simulated event persistence failure.',
                $exception->getMessage(),
            );
        } finally {
            CmsRecommendationEvent::flushEventListeners();
            CmsRecommendationEvent::clearBootedModels();
        }

        $this->assertSame('UNDER_REVIEW', $version->fresh()->status_code);
        $this->assertNull($plan->fresh()->accepted_version_id);
        $this->assertSame('FOR_ACTION_PLAN', $case->fresh()->status_code);
        $this->assertSame($eventCount, CmsRecommendationEvent::query()->count());
        $this->assertSame($activityCount, ActivityLog::query()->count());
        $this->assertSame($auditCount, AuditLog::query()->count());
        $this->assertSame(
            $notificationCount,
            SystemNotification::query()->count(),
        );
    }

    public function test_scope_and_recommendation_detail_integration_remain_safe_and_backward_compatible(): void
    {
        $auditee = $this->user('auditee');
        $case = $this->case('DETAIL', $auditee->office);

        Sanctum::actingAs($auditee);
        $this->getJson("/api/cms/recommendations/{$case->id}/action-plan")
            ->assertOk()
            ->assertJsonPath('data.actionPlan', null)
            ->assertJsonPath('data.permittedActions.0', 'create');
        $plan = $this->createPlan($case, $auditee);
        $this->getJson("/api/cms/recommendations/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.recommendation.actionPlanSummary.hasActionPlan', true)
            ->assertJsonPath(
                'data.recommendation.actionPlanSummary.currentVersionNumber',
                1,
            )
            ->assertJsonPath('data.recommendation.recommendation', 'Correct DETAIL.');

        $unrelated = User::query()
            ->where('office_id', '!=', $auditee->office_id)
            ->whereHas('role', fn ($role) => $role->where('code', 'auditee_representative'))
            ->firstOrFail();
        Sanctum::actingAs($unrelated);
        $this->getJson("/api/cms/action-plans/{$plan->id}")->assertNotFound();

        Sanctum::actingAs($this->user('admin'));
        $this->getJson("/api/cms/action-plans/{$plan->id}")->assertForbidden();
        $this->getJson('/api/cms/dashboard')->assertForbidden();
    }

    private function createPlan(
        CmsRecommendationCase $case,
        User $auditee,
    ): CmsCorrectiveActionPlan {
        Sanctum::actingAs($auditee);
        $response = $this->postJson(
            "/api/cms/recommendations/{$case->id}/action-plans",
            $this->payload($auditee, $case),
        )->assertCreated()
            ->assertJsonPath('data.actionPlan.currentVersion.versionNumber', 1);

        return CmsCorrectiveActionPlan::query()
            ->with(['currentVersion.milestones'])
            ->findOrFail($response->json('data.actionPlan.id'));
    }

    private function submitAndReview(
        CmsCorrectiveActionPlan $plan,
        CmsActionPlanVersion $version,
        User $auditee,
        User $reviewer,
    ): void {
        Sanctum::actingAs($auditee);
        $this->postJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$version->id}/transitions/submit",
            ['lockVersion' => $version->fresh()->lock_version, 'confirmation' => true],
        )->assertOk();
        Sanctum::actingAs($reviewer);
        $this->postJson(
            "/api/cms/action-plans/{$plan->id}/versions/{$version->id}/transitions/start-review",
            ['lockVersion' => $version->fresh()->lock_version],
        )->assertOk();
    }

    /** @return array<string, mixed> */
    private function payload(User $auditee, CmsRecommendationCase $case): array
    {
        $start = now()->addDay()->toDateString();
        $target = now()->addDays(20)->toDateString();

        return [
            'lockVersion' => $case->lock_version,
            'planSummary' => 'Management will strengthen the affected control.',
            'implementationStrategy' => 'Issue procedures, train staff, and verify adoption.',
            'expectedOutcome' => 'The control operates consistently with retained evidence.',
            'rootCauseResponse' => 'The plan addresses the documented process weakness.',
            'resourcesRequired' => 'Existing personnel and operating resources.',
            'dependencies' => 'Management approval of the procedure.',
            'risksAndConstraints' => 'Scheduling and staff availability.',
            'plannedStartDate' => $start,
            'plannedTargetDate' => $target,
            'ownerOfficeId' => $auditee->office_id,
            'focalUserId' => $auditee->id,
            'milestones' => [
                [
                    'sequenceNumber' => 1,
                    'title' => 'Approve revised procedure',
                    'description' => 'Prepare and approve the revised control procedure.',
                    'expectedOutput' => 'Approved procedure',
                    'successIndicator' => 'Signed approval is available.',
                    'verificationMethod' => 'Inspect the approved procedure.',
                    'responsibleOfficeId' => $auditee->office_id,
                    'responsibleUserId' => $auditee->id,
                    'plannedStartDate' => $start,
                    'plannedTargetDate' => now()->addDays(10)->toDateString(),
                    'weightPercentage' => 40,
                    'displayOrder' => 1,
                ],
                [
                    'sequenceNumber' => 2,
                    'title' => 'Complete staff orientation',
                    'description' => 'Orient personnel on the approved procedure.',
                    'expectedOutput' => 'Completed orientation',
                    'successIndicator' => 'Attendance and materials are retained.',
                    'verificationMethod' => 'Inspect orientation records.',
                    'responsibleOfficeId' => $auditee->office_id,
                    'responsibleUserId' => $auditee->id,
                    'plannedStartDate' => now()->addDays(11)->toDateString(),
                    'plannedTargetDate' => $target,
                    'weightPercentage' => 60,
                    'displayOrder' => 2,
                ],
            ],
        ];
    }

    private function user(string $username): User
    {
        return User::query()
            ->with(['office', 'role.permissions', 'roles.permissions'])
            ->where('username', $username)
            ->firstOrFail();
    }

    private function assignMonitor(CmsRecommendationCase $case, User $reviewer): void
    {
        CmsRecommendationAssignment::query()->create([
            'cms_recommendation_case_id' => $case->id,
            'user_id' => $reviewer->id,
            'assignment_role_code' => 'COMPLIANCE_MONITOR',
            'assigned_by' => $this->user('departmenthead')->id,
            'assigned_at' => now(),
            'effective_from' => now(),
            'is_current' => true,
        ]);
    }

    private function case(string $suffix, Office $office): CmsRecommendationCase
    {
        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $risk = MasterList::query()
            ->where('code', 'RISK_LEVEL')
            ->firstOrFail()->items()->where('code', 'HIGH')->firstOrFail();
        $confidentiality = MasterList::query()
            ->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->firstOrFail()->items()->where('code', 'INTERNAL')->firstOrFail();
        $target = now()->addMonth()->toDateString();
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => "CMS3A-{$suffix}",
            'title' => "CMS-3A {$suffix}",
            'source_type' => 'SPECIAL',
            'special_authority_reference' => "AUTH-{$suffix}",
            'special_authority_date' => now()->subMonth(),
            'special_authority_approved_by' => $management->id,
            'objectives' => 'Test CMS-3A.',
            'scope' => 'Corrective Action Plan.',
            'status' => 'REPORTING',
            'created_by' => $management->id,
            'updated_by' => $management->id,
        ]);
        $engagement->offices()->attach($office->id, ['is_primary' => true]);
        $finding = AuditFinding::query()->create([
            'finding_family_uuid' => (string) Str::uuid(),
            'revision_number' => 1,
            'is_current_revision' => true,
            'audit_engagement_id' => $engagement->id,
            'finding_code' => "FND-{$suffix}",
            'title' => "Finding {$suffix}",
            'criteria' => 'Expected control.',
            'condition' => 'Control gap.',
            'cause' => 'Process weakness.',
            'effect' => 'Risk exposure.',
            'risk_rating_id' => $risk->id,
            'responsible_office_id' => $office->id,
            'status' => 'FINALIZED',
            'authored_by' => $auditor->id,
        ]);
        $recommendation = AuditRecommendation::query()->create([
            'audit_finding_id' => $finding->id,
            'recommendation_code' => "REC-{$suffix}",
            'recommendation' => "Correct {$suffix}.",
            'responsible_office_id' => $office->id,
            'target_implementation_date' => $target,
            'status' => 'FINALIZED',
            'created_by' => $auditor->id,
        ]);
        $report = AuditReport::query()->create([
            'audit_engagement_id' => $engagement->id,
            'report_code' => "AR-{$suffix}",
            'title' => "Final report {$suffix}",
            'report_stage' => 'FINAL_REPORT',
            'status' => 'ISSUED',
            'current_version_number' => 1,
            'confidentiality_level_id' => $confidentiality->id,
            'prepared_by' => $auditor->id,
            'issued_at' => now()->subDays(2),
            'issued_by' => $management->id,
        ]);
        $reportVersion = AuditReportVersion::query()->create([
            'audit_report_id' => $report->id,
            'version_number' => 1,
            'report_stage' => 'FINAL_REPORT',
            'content_snapshot' => [],
            'checksum_sha256' => hash('sha256', $suffix),
            'change_reason' => 'Issued report.',
            'created_by' => $auditor->id,
        ]);
        $snapshot = [
            'engagement' => [
                'id' => $engagement->id,
                'code' => $engagement->engagement_code,
                'title' => $engagement->title,
            ],
            'finding' => [
                'id' => $finding->id,
                'code' => $finding->finding_code,
                'title' => $finding->title,
            ],
            'recommendation' => [
                'id' => $recommendation->id,
                'code' => $recommendation->recommendation_code,
                'wording' => $recommendation->recommendation,
            ],
        ];
        $intake = CmsRecommendation::query()->create([
            'source_audit_recommendation_id' => $recommendation->id,
            'transfer_key' => (string) Str::uuid(),
            'audit_engagement_id' => $engagement->id,
            'audit_report_id' => $report->id,
            'audit_report_version_id' => $reportVersion->id,
            'report_code_snapshot' => $report->report_code,
            'report_version_number_snapshot' => 1,
            'report_issued_at' => $report->issued_at,
            'report_issued_by' => $management->id,
            'report_checksum_sha256' => $reportVersion->checksum_sha256,
            'confidentiality_level_id' => $confidentiality->id,
            'confidentiality_code_snapshot' => $confidentiality->code,
            'confidentiality_label_snapshot' => $confidentiality->label,
            'audit_finding_id' => $finding->id,
            'risk_rating_id' => $risk->id,
            'risk_code_snapshot' => $risk->code,
            'risk_label_snapshot' => $risk->label,
            'recommendation_code' => $recommendation->recommendation_code,
            'source_snapshot' => $snapshot,
            'responsible_office_id' => $office->id,
            'responsible_office_snapshot' => [[
                'id' => $office->id,
                'code' => $office->code,
                'name' => $office->name,
                'isLead' => true,
            ]],
            'lead_responsible_office_id' => $office->id,
            'target_implementation_date' => $target,
            'original_target_implementation_date' => $target,
            'source_schema_version' => 1,
            'status' => 'TRANSFERRED',
            'transferred_at' => now()->subDays(2),
            'transferred_by' => $management->id,
        ]);
        $case = CmsRecommendationCase::query()->create([
            'cms_recommendation_id' => $intake->id,
            'status_code' => 'TRANSFERRED',
            'effective_target_implementation_date' => $target,
            'lead_responsible_office_id' => $office->id,
            'opened_at' => $intake->transferred_at,
            'created_by' => $management->id,
            'lock_version' => 1,
        ]);
        CmsRecommendationEvent::query()->create([
            'cms_recommendation_case_id' => $case->id,
            'cms_recommendation_id' => $intake->id,
            'idempotency_key' => "cms-intake:{$intake->id}",
            'event_code' => 'INTAKE_CREATED',
            'source_module' => 'AEMS',
            'actor_id' => $management->id,
            'new_status' => 'TRANSFERRED',
            'event_metadata' => ['transferKey' => $intake->transfer_key],
            'created_at' => $intake->transferred_at,
        ]);

        return $case->fresh();
    }
}
