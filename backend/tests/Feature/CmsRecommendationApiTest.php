<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditLog;
use App\Models\AuditRecommendation;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Models\CmsEscalation;
use App\Models\CmsRecommendation;
use App\Models\CmsRecommendationAssignment;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\Cms\CmsRecommendationScopeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class CmsRecommendationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    public function test_permissions_are_granular_without_removing_legacy_codes_or_granting_admin_assignment(): void
    {
        $this->assertEqualsCanonicalizing([
            'cms.approve_extension',
            'cms.close',
            'cms.submit_evidence',
            'cms.update',
            'cms.validate',
            'cms.view',
            'cms.dashboard.view',
            'cms.recommendation.view',
            'cms.recommendation.assign',
            'cms.recommendation.monitor',
            'cms.administration.monitor',
            'cms.action-plan.view',
            'cms.action-plan.create',
            'cms.action-plan.update',
            'cms.action-plan.submit',
            'cms.action-plan.review',
            'cms.action-plan.accept',
            'cms.action-plan.return',
            'cms.action-plan.revise',
            'cms.progress.view',
            'cms.progress.create',
            'cms.progress.update',
            'cms.progress.submit',
            'cms.progress.review',
            'cms.progress.return',
            'cms.progress.record',
            'cms.progress.revise',
            'cms.evidence.view',
            'cms.evidence.upload',
            'cms.evidence.download',
            'cms.evidence.remove_draft',
            'cms.validation.view',
            'cms.validation.create',
            'cms.validation.assign',
            'cms.validation.update',
            'cms.validation.submit',
            'cms.validation.review',
            'cms.validation.return',
            'cms.validation.finalize',
            'cms.validation.revise',
            'cms.validation-evidence.view',
            'cms.validation-evidence.upload',
            'cms.validation-evidence.download',
            'cms.validation-evidence.remove_draft',
            'cms.extension.view',
            'cms.extension.create',
            'cms.extension.update',
            'cms.extension.submit',
            'cms.extension.review',
            'cms.extension.return',
            'cms.extension.recommend',
            'cms.extension.approve',
            'cms.extension.reject',
            'cms.extension.revise',
            'cms.extension-evidence.view',
            'cms.extension-evidence.upload',
            'cms.extension-evidence.download',
            'cms.extension-evidence.remove_draft',
            'cms.escalation.view',
            'cms.escalation.create',
            'cms.escalation.update',
            'cms.escalation.submit',
            'cms.escalation.review',
            'cms.escalation.return',
            'cms.escalation.issue',
            'cms.escalation.acknowledge',
            'cms.escalation.respond',
            'cms.escalation.response-review',
            'cms.escalation.response-return',
            'cms.escalation.response-accept',
            'cms.escalation.resolve',
            'cms.escalation.revise',
            'cms.escalation-evidence.view',
            'cms.escalation-evidence.upload',
            'cms.escalation-evidence.download',
            'cms.escalation-evidence.remove_draft',
            'cms.closure.view',
            'cms.closure.request',
            'cms.closure.update',
            'cms.closure.submit',
            'cms.closure.review',
            'cms.closure.return',
            'cms.closure.recommend',
            'cms.closure.approve',
            'cms.closure.reject',
            'cms.closure.revise',
            'cms.closure-evidence.view',
            'cms.closure-evidence.upload',
            'cms.closure-evidence.download',
            'cms.closure-evidence.remove_draft',
            'cms.reopening.view',
            'cms.reopening.request',
            'cms.reopening.update',
            'cms.reopening.submit',
            'cms.reopening.review',
            'cms.reopening.return',
            'cms.reopening.recommend',
            'cms.reopening.approve',
            'cms.reopening.reject',
            'cms.reopening.revise',
            'cms.reopening-evidence.view',
            'cms.reopening-evidence.upload',
            'cms.reopening-evidence.download',
            'cms.reopening-evidence.remove_draft',
            'cms.report.view',
            'cms.report.export',
            'cms.disposition.view',
            'cms.disposition.request',
            'cms.disposition.update',
            'cms.disposition.submit',
            'cms.disposition.review',
            'cms.disposition.return',
            'cms.disposition.recommend',
            'cms.disposition.approve',
            'cms.disposition.reject',
            'cms.disposition.revise',
            'cms.disposition-evidence.view',
            'cms.disposition-evidence.upload',
            'cms.disposition-evidence.download',
            'cms.disposition-evidence.remove_draft',
            'cms.automation.view',
            'cms.automation.manage',
            'cms.automation.run',
            'cms.automation.review',
            'cms.automation.dismiss',
        ], Permission::query()->where('code', 'like', 'cms.%')->pluck('code')->all());

        $management = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $platform = $this->user('admin');
        $administrator = $this->user('agisadmin');

        $this->assertTrue($management->hasPermission('cms.recommendation.assign'));
        $this->assertTrue($management->hasPermission('cms.recommendation.monitor'));
        $this->assertTrue($auditor->hasPermission('cms.recommendation.monitor'));
        $this->assertFalse($auditor->hasPermission('cms.recommendation.assign'));
        $this->assertTrue($platform->hasPermission('cms.administration.monitor'));
        $this->assertFalse($platform->hasPermission('cms.recommendation.assign'));
        $this->assertFalse($platform->hasPermission('cms.recommendation.view'));
        $this->assertFalse($administrator->hasPermission('cms.recommendation.assign'));
        $this->assertTrue($administrator->hasPermission('cms.administration.monitor'));

        Sanctum::actingAs($platform);
        $this->getJson('/api/cms/dashboard')->assertForbidden();
    }

    public function test_scope_list_and_dashboard_hide_other_office_and_confidential_cases(): void
    {
        $auditee = $this->user('auditee');
        $otherOffice = Office::query()->whereKeyNot($auditee->office_id)->firstOrFail();
        $visible = $this->case('VISIBLE', $auditee->office, 'INTERNAL', 'HIGH', now()->subDay());
        $this->case('CONFIDENTIAL', $auditee->office, 'CONFIDENTIAL', 'HIGH', now()->subDay());
        $this->case('OTHER-OFFICE', $otherOffice, 'INTERNAL', 'LOW', now()->addMonth());

        Sanctum::actingAs($auditee);
        $this->getJson('/api/cms/recommendations')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.recommendations.0.id', $visible->id)
            ->assertJsonCount(1, 'data.filters.responsibleOffices');
        $this->getJson('/api/cms/dashboard')
            ->assertOk()
            ->assertJsonPath('data.cards.totalVisibleCases', 1)
            ->assertJsonPath('data.cards.overdueCases', 1)
            ->assertJsonPath('data.cards.highRiskOverdueCases', 1)
            ->assertJsonPath('data.groups.byResponsibleOffice.0.id', $auditee->office_id)
            ->assertJsonPath('data.groups.byResponsibleOffice.0.count', 1)
            ->assertJsonPath('data.groups.byRiskLevel.0.code', 'HIGH')
            ->assertJsonPath('data.groups.byRiskLevel.0.count', 1);

        Sanctum::actingAs($this->user('departmenthead'));
        $this->getJson('/api/cms/recommendations')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 3);
        $this->getJson('/api/cms/dashboard')
            ->assertOk()
            ->assertJsonPath('data.cards.totalVisibleCases', 3)
            ->assertJsonPath('data.cards.assignedCases', 0)
            ->assertJsonPath('data.cards.unassignedCases', 3)
            ->assertJsonPath('data.groups.byAssignedMonitor.0.code', 'UNASSIGNED')
            ->assertJsonPath('data.groups.byAssignedMonitor.0.count', 3);

        $readOnly = $this->user('mayor');
        $readOnlyCase = $this->case(
            'READ-ONLY',
            $readOnly->office,
            'INTERNAL',
            'LOW',
            now()->addMonth(),
        );
        Sanctum::actingAs($readOnly);
        $this->getJson('/api/cms/recommendations')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.recommendations.0.id', $readOnlyCase->id);
    }

    public function test_assigned_monitor_scope_registry_filters_detail_and_view_deduplication(): void
    {
        $auditee = $this->user('auditee');
        $case = $this->case(
            'SEARCHABLE',
            $auditee->office,
            'CONFIDENTIAL',
            'HIGH',
            now()->subDay(),
        );
        $auditor = $this->user('auditor');
        $this->directAssignment($case, $auditor);

        Sanctum::actingAs($auditor);
        $this->getJson('/api/cms/recommendations?search=SEARCHABLE&overdue=true&assigned=true&sortBy=targetDate&sortDirection=asc')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.recommendations.0.isOverdue', true)
            ->assertJsonPath('data.recommendations.0.currentMonitor.user.id', $auditor->id);
        $this->getJson('/api/cms/recommendations?sortBy=not-a-column')
            ->assertUnprocessable();

        $url = "/api/cms/recommendations/{$case->id}";
        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.recommendation.sourceLineage.recommendation.wording', 'Correct SEARCHABLE.')
            ->assertJsonPath('data.recommendation.sourceLineage.report.checksumSha256', hash('sha256', 'SEARCHABLE'))
            ->assertJsonMissingPath('data.recommendation.sourceLineage.report.storagePath');
        $this->getJson($url)->assertOk();
        $this->assertSame(
            1,
            ActivityLog::query()
                ->where('action', 'cms.recommendation.viewed')
                ->where('metadata->recordId', $case->id)
                ->count(),
        );

        $unrelated = $this->user('cias.employee');
        Sanctum::actingAs($unrelated);
        $this->getJson($url)->assertNotFound();
        $this->assertSame(
            1,
            ActivityLog::query()->where('action', 'cms.recommendation.viewed')->count(),
        );
    }

    public function test_assignment_replacement_and_end_preserve_history_events_logs_and_notifications(): void
    {
        $case = $this->case(
            'ASSIGN',
            $this->user('auditee')->office,
            'CONFIDENTIAL',
            'HIGH',
            now()->addMonth(),
        );
        $management = $this->user('departmenthead');
        $first = $this->user('auditor');
        $second = $this->user('cias.employee');
        Sanctum::actingAs($management);

        $response = $this->postJson(
            "/api/cms/recommendations/{$case->id}/assignments",
            [
                'userId' => $first->id,
                'lockVersion' => 1,
            ],
        )->assertCreated()
            ->assertJsonPath('data.assignment.user.id', $first->id)
            ->assertJsonPath('data.caseLockVersion', 2);
        $firstAssignmentId = $response->json('data.assignment.id');

        $this->postJson(
            "/api/cms/recommendations/{$case->id}/assignments",
            [
                'userId' => $first->id,
                'lockVersion' => 2,
            ],
        )->assertUnprocessable();
        $this->assertDatabaseCount('cms_recommendation_assignments', 1);

        $replacement = $this->postJson(
            "/api/cms/recommendations/{$case->id}/assignments",
            [
                'userId' => $second->id,
                'reason' => 'Workload rebalancing.',
                'lockVersion' => 2,
            ],
        )->assertCreated()
            ->assertJsonPath('data.caseLockVersion', 3);
        $secondAssignmentId = $replacement->json('data.assignment.id');

        $this->assertDatabaseHas('cms_recommendation_assignments', [
            'id' => $firstAssignmentId,
            'is_current' => false,
            'end_reason' => 'Workload rebalancing.',
        ]);
        $this->assertDatabaseHas('cms_recommendation_assignments', [
            'id' => $secondAssignmentId,
            'is_current' => true,
        ]);

        $this->postJson(
            "/api/cms/recommendations/{$case->id}/assignments/{$secondAssignmentId}/end",
            [
                'reason' => 'Monitoring completed for reassignment queue.',
                'lockVersion' => 3,
            ],
        )->assertOk()
            ->assertJsonPath('data.caseLockVersion', 4);

        $this->assertSame(0, CmsRecommendationAssignment::query()->current()->count());
        $this->assertDatabaseHas('cms_recommendation_events', [
            'event_code' => 'COMPLIANCE_MONITOR_ASSIGNED',
        ]);
        $this->assertDatabaseHas('cms_recommendation_events', [
            'event_code' => 'COMPLIANCE_MONITOR_REPLACED',
        ]);
        $this->assertDatabaseHas('cms_recommendation_events', [
            'event_code' => 'COMPLIANCE_MONITOR_ASSIGNMENT_ENDED',
        ]);
        $this->assertSame(
            3,
            ActivityLog::query()->where('action', 'like', 'cms.recommendation.monitor%')->count(),
        );
        $this->assertSame(
            3,
            AuditLog::query()->where('action', 'like', 'cms.recommendation.monitor%')->count(),
        );
        $this->assertGreaterThanOrEqual(
            3,
            SystemNotification::query()->where('module_code', 'CMS')->count(),
        );
        $this->assertSame('TRANSFERRED', $case->fresh()->status_code);
        $this->assertDatabaseCount('cms_recommendation_assignments', 2);
    }

    public function test_assignment_authority_target_state_independence_and_stale_lock_are_enforced(): void
    {
        $auditee = $this->user('auditee');
        $case = $this->case('RULES', $auditee->office, 'INTERNAL', 'LOW', now()->addMonth());

        Sanctum::actingAs($auditee);
        $this->postJson(
            "/api/cms/recommendations/{$case->id}/assignments",
            ['userId' => $this->user('auditor')->id, 'lockVersion' => 1],
        )->assertForbidden();

        $management = $this->user('departmenthead');
        Sanctum::actingAs($management);
        $ownOfficeMonitor = $this->user('auditor');
        $auditeeRole = Role::query()
            ->where('code', 'auditee_representative')
            ->firstOrFail();
        $ownOfficeMonitor->forceFill(['office_id' => $auditee->office_id])->save();
        $ownOfficeMonitor->syncRoleAssignments(
            [$ownOfficeMonitor->role_id, $auditeeRole->id],
            $ownOfficeMonitor->role_id,
        );
        $this->postJson(
            "/api/cms/recommendations/{$case->id}/assignments",
            ['userId' => $ownOfficeMonitor->id, 'lockVersion' => 1],
        )->assertUnprocessable();

        $locked = $this->user('cias.employee');
        $locked->forceFill(['is_manually_locked' => true])->save();
        $this->postJson(
            "/api/cms/recommendations/{$case->id}/assignments",
            ['userId' => $locked->id, 'lockVersion' => 1],
        )->assertUnprocessable();
        $this->assertDatabaseCount('cms_recommendation_assignments', 0);
        $this->assertDatabaseMissing('notifications', ['module_code' => 'CMS']);

        $locked->forceFill(['is_manually_locked' => false])->save();
        $this->postJson(
            "/api/cms/recommendations/{$case->id}/assignments",
            ['userId' => $locked->id, 'lockVersion' => 99],
        )->assertUnprocessable();
        $this->assertDatabaseCount('cms_recommendation_assignments', 0);
    }

    public function test_assignment_database_uniqueness_and_model_history_guards_are_enforced(): void
    {
        $case = $this->case(
            'INTEGRITY',
            $this->user('auditee')->office,
            'INTERNAL',
            'LOW',
            now()->addMonth(),
        );
        $assignment = $this->directAssignment($case, $this->user('auditor'));

        try {
            $this->directAssignment($case, $this->user('cias.employee'));
            $this->fail('A second current Compliance Monitor should be rejected.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        try {
            $assignment->forceFill(['assignment_reason' => 'Rewrite history.'])->save();
            $this->fail('Current assignment history should not be rewritten.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('cannot be rewritten', $exception->getMessage());
        }

        try {
            $assignment->delete();
            $this->fail('Assignment history should not be deleted.');
        } catch (LogicException $exception) {
            $this->assertSame('CMS assignments cannot be deleted.', $exception->getMessage());
        }
    }

    public function test_scope_service_rejects_inactive_and_compatibility_only_unrelated_users(): void
    {
        $case = $this->case(
            'SCOPE',
            $this->user('auditee')->office,
            'INTERNAL',
            'LOW',
            now()->addMonth(),
        );
        $auditor = $this->user('auditor');
        $scope = app(CmsRecommendationScopeService::class);
        $this->assertFalse(
            $scope->visibleCases(CmsRecommendationCase::query(), $auditor)->whereKey($case->id)->exists(),
        );

        $auditor->forceFill(['is_active' => false])->save();
        $this->assertFalse(
            $scope->visibleCases(CmsRecommendationCase::query(), $auditor->fresh())
                ->whereKey($case->id)
                ->exists(),
        );
    }

    public function test_cms_escalation_notice_submission_review_and_issue_preserve_case_status(): void
    {
        $management = $this->user('departmenthead');
        $monitor = $this->user('auditor');
        $issuer = User::factory()->create([
            'role_id' => $management->role_id,
            'office_id' => $management->office_id,
            'is_active' => true,
            'is_manually_locked' => false,
        ]);
        $issuer->syncRoleAssignments([$management->role_id], $management->role_id);
        $case = $this->case('ESCALATION', $this->user('auditee')->office, 'INTERNAL', 'HIGH', now()->subDay());
        $case->forceFill(['status_code' => CmsRecommendationCase::STATUS_MONITORING])->save();
        $this->directAssignment($case, $monitor);

        Sanctum::actingAs($monitor);
        $create = $this->postJson("/api/cms/recommendations/{$case->id}/escalations", [
            'primaryTriggerCode' => 'OVERDUE_TARGET',
            'subject' => 'Escalation notice',
            'escalationSummary' => 'Management attention is required.',
            'basisAndContext' => 'The effective target date has passed.',
            'requiredManagementActions' => 'Provide a documented response.',
            'requiredResponseContents' => 'Include actions, owner, and date.',
            'responseDueDate' => now()->addDays(10)->toDateString(),
            'lockVersion' => $case->lock_version,
        ])->assertCreated();
        $escalationId = $create->json('data.escalation.id');
        $versionId = $create->json('data.escalation.currentNotice.id');
        $lock = $create->json('data.escalation.lockVersion');

        $submit = $this->postJson("/api/cms/escalations/{$escalationId}/notice-versions/{$versionId}/transitions/submit", ['lockVersion' => $lock, 'confirmation' => true])->assertOk();
        $lock = $submit->json('data.escalation.lockVersion');

        Sanctum::actingAs($management);
        $review = $this->postJson("/api/cms/escalations/{$escalationId}/notice-versions/{$versionId}/transitions/start-review", ['lockVersion' => $lock])->assertOk();
        $lock = $review->json('data.escalation.lockVersion');
        $issuer = User::factory()->create(['role_id' => $management->role_id, 'office_id' => $management->office_id, 'is_active' => true, 'is_manually_locked' => false]);
        $issuer->syncRoleAssignments([$management->role_id], $management->role_id);
        Sanctum::actingAs($issuer);
        Sanctum::actingAs($issuer);
        $this->postJson("/api/cms/escalations/{$escalationId}/notice-versions/{$versionId}/transitions/issue", ['lockVersion' => $review->json('data.escalation.currentNotice.lockVersion'), 'issuanceComment' => 'Issued after independent review.', 'confirmation' => true])->assertOk();

        $this->assertDatabaseHas('cms_escalations', ['id' => $escalationId, 'operational_status_code' => CmsEscalation::STATUS_ISSUED]);
        $this->assertDatabaseHas('cms_recommendation_cases', ['id' => $case->id, 'status_code' => CmsRecommendationCase::STATUS_MONITORING]);

        $auditee = $this->user('auditee');
        Sanctum::actingAs($auditee);
        $response = $this->postJson("/api/cms/escalations/{$escalationId}/response", [
            'managementResponseSummary' => 'Management has responded.',
            'rootCauseOrExplanation' => 'The process owner changed.',
            'actionsCompleted' => 'The control was redesigned.',
            'remainingActions' => 'Evidence will be retained.',
            'committedActions' => 'Complete the remaining action.',
            'responsiblePersonOrOffice' => 'Responsible office head',
            'commitmentTargetDate' => now()->addDays(30)->toDateString(),
            'noEvidenceExplanation' => 'Supporting evidence will be provided during follow-up.',
            'lockVersion' => 1,
        ])->assertCreated();
        $responseId = $response->json('data.response.id');
        $responseVersionId = $response->json('data.response.currentVersion.id');
        $responseLock = $response->json('data.response.currentVersion.lockVersion');
        $submitted = $this->postJson("/api/cms/escalation-responses/{$responseId}/versions/{$responseVersionId}/transitions/submit", ['lockVersion' => $responseLock, 'confirmation' => true])->assertOk();

        Sanctum::actingAs($management);
        $reviewed = $this->postJson("/api/cms/escalation-responses/{$responseId}/versions/{$responseVersionId}/transitions/start-review", ['lockVersion' => $submitted->json('data.response.currentVersion.lockVersion')])->assertOk();
        $accepted = $this->postJson("/api/cms/escalation-responses/{$responseId}/versions/{$responseVersionId}/transitions/accept", ['lockVersion' => $reviewed->json('data.response.currentVersion.lockVersion'), 'acceptanceComment' => 'Accepted as the follow-up baseline.', 'confirmation' => true])->assertOk();
        $this->postJson("/api/cms/escalations/{$escalationId}/resolve", ['lockVersion' => $accepted->json('data.response.escalation.lockVersion') ?: $this->getJson("/api/cms/escalations/{$escalationId}")->json('data.escalation.lockVersion'), 'resolutionSummary' => 'Formal escalation addressed.', 'basisForResolution' => 'The management response is sufficient for follow-up.', 'followUpRequirements' => 'Continue normal CMS monitoring.', 'confirmation' => true])->assertOk();
        $this->assertDatabaseHas('cms_escalations', ['id' => $escalationId, 'operational_status_code' => CmsEscalation::STATUS_RESOLVED]);
    }

    private function user(string $username): User
    {
        return User::query()
            ->with(['office', 'role.permissions', 'roles.permissions'])
            ->where('username', $username)
            ->firstOrFail();
    }

    private function case(
        string $suffix,
        Office $office,
        string $confidentialityCode,
        string $riskCode,
        mixed $targetDate,
    ): CmsRecommendationCase {
        $actor = $this->user('departmenthead');
        $auditor = $this->user('auditor');
        $confidentiality = MasterList::query()
            ->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->firstOrFail()->items()->where('code', $confidentialityCode)->firstOrFail();
        $risk = MasterList::query()
            ->where('code', 'RISK_LEVEL')
            ->firstOrFail()->items()->where('code', $riskCode)->firstOrFail();
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => "CMS2A-{$suffix}",
            'title' => "CMS-2A {$suffix}",
            'source_type' => 'SPECIAL',
            'special_authority_reference' => "AUTH-{$suffix}",
            'special_authority_date' => now()->subMonth(),
            'special_authority_approved_by' => $actor->id,
            'objectives' => 'Test CMS-2A.',
            'scope' => 'Recommendation monitoring.',
            'status' => 'REPORTING',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
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
            'target_implementation_date' => $targetDate,
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
            'issued_by' => $actor->id,
        ]);
        $version = AuditReportVersion::query()->create([
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
            'report' => [
                'id' => $report->id,
                'code' => $report->report_code,
                'versionId' => $version->id,
                'versionNumber' => 1,
                'checksumSha256' => $version->checksum_sha256,
            ],
        ];
        $intake = CmsRecommendation::query()->create([
            'source_audit_recommendation_id' => $recommendation->id,
            'transfer_key' => (string) Str::uuid(),
            'audit_engagement_id' => $engagement->id,
            'audit_report_id' => $report->id,
            'audit_report_version_id' => $version->id,
            'report_code_snapshot' => $report->report_code,
            'report_version_number_snapshot' => 1,
            'report_issued_at' => $report->issued_at,
            'report_issued_by' => $actor->id,
            'report_checksum_sha256' => $version->checksum_sha256,
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
            'target_implementation_date' => $targetDate,
            'original_target_implementation_date' => $targetDate,
            'source_schema_version' => 1,
            'status' => 'TRANSFERRED',
            'transferred_at' => now()->subDays(2),
            'transferred_by' => $actor->id,
        ]);
        $case = CmsRecommendationCase::query()->create([
            'cms_recommendation_id' => $intake->id,
            'status_code' => 'TRANSFERRED',
            'effective_target_implementation_date' => $targetDate,
            'lead_responsible_office_id' => $office->id,
            'opened_at' => $intake->transferred_at,
            'created_by' => $actor->id,
            'lock_version' => 1,
        ]);
        CmsRecommendationEvent::query()->create([
            'cms_recommendation_case_id' => $case->id,
            'cms_recommendation_id' => $intake->id,
            'idempotency_key' => "cms-intake:{$intake->id}",
            'event_code' => 'INTAKE_CREATED',
            'source_module' => 'AEMS',
            'actor_id' => $actor->id,
            'new_status' => 'TRANSFERRED',
            'event_metadata' => ['transferKey' => $intake->transfer_key],
            'created_at' => $intake->transferred_at,
        ]);

        return $case->fresh();
    }

    private function directAssignment(
        CmsRecommendationCase $case,
        User $user,
    ): CmsRecommendationAssignment {
        return CmsRecommendationAssignment::query()->create([
            'cms_recommendation_case_id' => $case->id,
            'user_id' => $user->id,
            'assignment_role_code' => 'COMPLIANCE_MONITOR',
            'assigned_by' => $this->user('departmenthead')->id,
            'assigned_at' => now(),
            'effective_from' => now(),
            'is_current' => true,
        ]);
    }
}
