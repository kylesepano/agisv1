<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds standard AGIS roles, granular permissions, and default access scopes.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * The permission catalogue uses stable module.action codes so API policies and
     * the frontend can check a specific operation instead of relying on role names.
     *
     * @var array<string, list<string>>
     */
    private array $permissionCatalogue = [
        'dashboard' => ['view'],
        // Scope capabilities are permission-driven so custom roles can reuse
        // the same behavior without relying on a system role code.
        'access' => ['auditee_scope', 'read_only_scope', 'manage_system_roles'],
        'offices' => ['view', 'create', 'update', 'delete', 'restore'],
        'audit_areas' => ['view', 'create', 'update', 'delete', 'restore'],
        'audit_focus' => ['view', 'create', 'update', 'delete', 'restore'],
        'users' => [
            'view',
            'create',
            'update',
            'activate',
            'deactivate',
            'archive',
            'restore',
            'lock',
            'unlock',
            'reset_password',
        ],
        'roles' => ['view', 'create', 'clone', 'update', 'delete', 'restore'],
        'permissions' => ['view', 'update'],
        'master_lists' => ['view', 'manage'],
        'iap' => [
            'view',
            'manage_universe',
            'create',
            'update',
            'assess_risk',
            'manage_engagements',
            'assign_team',
            'submit',
            'review',
            'approve',
            'activate',
            'complete',
            'create_revision',
            'archive',
            'restore',
            'export',
        ],
        'iap.baics' => [
            'view', 'create', 'update', 'assign', 'submit', 'review', 'return',
            'approve', 'publish', 'archive', 'export', 'manage-controls',
        ],
        // A dedicated integration family prevents a user who can maintain
        // BAICS instruments from implicitly receiving IAP-consumer authority.
        // The legacy iap.baics.* permissions remain supported by the API.
        'iap.baics.integration' => [
            'view', 'create', 'update', 'submit', 'review', 'return', 'approve', 'retire',
        ],
        'aem' => ['view', 'create', 'update', 'assign', 'manage_workpapers', 'review', 'close'],
        'aems.engagement' => [
            'view', 'create', 'update', 'transition', 'authorize', 'suspend', 'cancel',
            'archive', 'restore', 'close', 'export', 'reopen_request', 'reopen_approve',
        ],
        // Explicitly grants the controlled self-review/self-acceptance capability.
        // Assignment and workflow gates still apply; this permission only allows
        // the same user to perform an otherwise independent review action.
        'aems.review' => ['own_submission'],
        'aems.foundation' => ['view', 'manage_scope', 'reconcile'],
        'aems.team' => [
            'view', 'assign', 'reassign',
            'amend', 'history',
            'safeguard_view', 'safeguard_declare', 'safeguard_review', 'safeguard_approve',
            'safeguard_submit_on_behalf',
        ],
        'aems.aeo' => [
            'view', 'prepare', 'review', 'approve', 'issue', 'revise', 'amend', 'sign',
            'distribute', 'acknowledge', 'cancel', 'void', 'supersede',
        ],
        'aems.aep' => ['view', 'create', 'review', 'approve', 'revise'],
        'aems.program' => ['view', 'manage', 'review', 'approve'],
        'aems.fieldwork' => ['view', 'create', 'manage', 'review', 'finalize'],
        'aems.planning-package' => ['view', 'create', 'update', 'review', 'approve', 'revise'],
        'aems.working-paper' => ['view', 'create', 'manage', 'review', 'approve'],
        'aems.evidence' => ['view', 'upload', 'verify', 'void', 'assess', 'outcome', 'link_report', 'exception_approve'],
        'aems.evidence-request' => ['view', 'create', 'update', 'submit', 'send', 'acknowledge', 'respond', 'receive', 'assess', 'extend', 'extension_approve', 'overdue', 'escalate', 'cancel', 'close'],
        'aems.issue' => ['view', 'create', 'validate', 'dismiss', 'convert', 'merge', 'resolve', 'observe', 'refer', 'close_without_finding', 'withdraw'],
        'aems.afr' => ['view', 'transmit', 'delivery', 'acknowledge'],
        'aems.finding' => ['view', 'create', 'review', 'validate', 'communicate', 'finalize', 'revise'],
        'aems.management-response' => ['view', 'submit', 'request_clarification', 'request_extension', 'approve_extension', 'reject_extension', 'supplement'],
        'aems.rejoinder' => ['view', 'create', 'finalize'],
        'aems.review-note' => ['view', 'create', 'update', 'finalize', 'revise', 'attach'],
        'aems.task' => ['view', 'create', 'update', 'assign', 'complete', 'cancel', 'reopen', 'escalate'],
        'aems.due-process' => ['view', 'create', 'remind', 'record_non_response', 'attach'],
        'aems.escalation-candidate' => ['view', 'create', 'review', 'resolve', 'dismiss'],
        'aems.conference' => ['view', 'manage', 'acknowledge'],
        'aems.entry-conference' => ['view', 'manage', 'acknowledge', 'waive'],
        'aems.report' => [
            'view', 'create', 'manage', 'review', 'approve', 'issue', 'view_issued',
            'distribute', 'acknowledge', 'amend', 'withdraw', 'supersede',
            'authority', 'signatory', 'transmit', 'export', 'close_admin',
        ],
        'aems.completion-assessment' => ['view', 'create', 'update', 'submit', 'review', 'approve'],
        'aems.completion-transfer' => ['view', 'reconcile', 'approve'],
        'aems.closure' => ['view', 'create', 'update', 'submit', 'review', 'approve', 'close'],
        'aems.document-index' => ['view', 'manage', 'finalize'],
        'aems.retention' => ['view', 'manage', 'approve', 'archive', 'legal_hold_release', 'destruction_review', 'disposition_execute'],
        'aems.records' => ['view', 'search'],
        'aems.calendar' => ['view', 'manage'],
        'afr' => ['view', 'create', 'update', 'submit', 'review', 'approve'],
        'cms' => [
            'view', 'update', 'submit_evidence', 'validate', 'approve_extension', 'close',
            'dashboard.view', 'recommendation.view', 'recommendation.assign',
            'recommendation.monitor', 'administration.monitor',
        ],
        'cms.action-plan' => [
            'view', 'create', 'update', 'submit', 'review', 'accept', 'return', 'revise',
        ],
        'cms.progress' => [
            'view', 'create', 'update', 'submit', 'review', 'return', 'record', 'revise',
        ],
        'cms.evidence' => ['view', 'upload', 'download', 'remove_draft'],
        'cms.validation' => [
            'view', 'create', 'assign', 'update', 'submit', 'review', 'return',
            'finalize', 'revise',
        ],
        'cms.validation-evidence' => ['view', 'upload', 'download', 'remove_draft'],
        'cms.extension' => [
            'view', 'create', 'update', 'submit', 'review', 'return',
            'recommend', 'approve', 'reject', 'revise',
        ],
        'cms.extension-evidence' => ['view', 'upload', 'download', 'remove_draft'],
        'cms.escalation' => [
            'view', 'create', 'update', 'submit', 'review', 'return', 'issue',
            'acknowledge', 'respond', 'response-review', 'response-return',
            'response-accept', 'resolve', 'revise',
        ],
        'cms.escalation-evidence' => ['view', 'upload', 'download', 'remove_draft'],
        'cms.closure' => ['view', 'request', 'update', 'submit', 'review', 'return', 'recommend', 'approve', 'reject', 'revise'],
        'cms.closure-evidence' => ['view', 'upload', 'download', 'remove_draft'],
        'cms.disposition' => ['view', 'request', 'update', 'submit', 'review', 'return', 'recommend', 'approve', 'reject', 'revise'],
        'cms.disposition-evidence' => ['view', 'upload', 'download', 'remove_draft'],
        'cms.reopening' => ['view', 'request', 'update', 'submit', 'review', 'return', 'recommend', 'approve', 'reject', 'revise'],
        'cms.reopening-evidence' => ['view', 'upload', 'download', 'remove_draft'],
        'cms.automation' => ['view', 'manage', 'run', 'review', 'dismiss'],
        'cms.report' => ['view', 'export'],
        'arms' => ['view', 'manage'],
        'armis' => [
            'resource.view', 'resource.create', 'resource.update', 'resource.archive', 'resource.restore',
            'competency.view', 'competency.manage', 'competency.verify',
            'availability.view', 'availability.manage', 'availability.review', 'availability.approve',
            'capacity.view', 'capacity.manage', 'capacity.review', 'capacity.approve',
            'workload.view', 'workload.manage', 'workload.review', 'workload.approve',
            'assignment.view', 'assignment.manage', 'assignment.review', 'assignment.approve',
            'actuals.view', 'actuals.record', 'actuals.review', 'actuals.approve', 'actuals.revise',
            'report.view', 'report.export',
            'provider.view', 'provider.monitor', 'provider.reconcile', 'provider.review', 'provider.switch', 'provider.rollback',
        ],
        'ais' => ['view', 'export'],
        'documents' => [
            'view', 'view_confidential', 'view_restricted',
            'upload', 'update', 'review', 'approve', 'download', 'delete', 'restore',
        ],
        'notifications' => ['view', 'manage'],
        'workflows' => [
            'view',
            'create',
            'update',
            'publish',
            'archive',
            'restore',
            'start',
            'act',
            'monitor',
        ],
        'activity_logs' => ['view', 'export'],
        'audit_logs' => ['view', 'export'],
        'system_configuration' => ['view', 'manage'],
        'administrative_reports' => ['view', 'export'],
        'profile' => ['view', 'update', 'change_password'],
    ];

    public function run(): void
    {
        $permissionIds = [];

        foreach ($this->permissionCatalogue as $module => $actions) {
            foreach ($actions as $action) {
                $code = "{$module}.{$action}";
                $permission = Permission::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => str("{$action} {$module}")->replace('_', ' ')->lower()->headline()->toString(),
                        'module' => $module,
                        'action' => $action,
                        'description' => "Allows the {$action} action in the {$module} module.",
                    ],
                );

                $permissionIds[$code] = $permission->id;
            }
        }

        $roles = [
            'platform_admin' => [
                'name' => 'Platform Administrator',
                'description' => 'Full platform configuration, registry, monitoring, and issued-report access without audit approval authority.',
                'is_system' => true,
                'office_access_scope' => 'ALL',
                'engagement_access_scope' => 'ALL',
                    'permissions' => collect(array_keys($permissionIds))
                    ->reject(fn (string $code): bool => str_starts_with($code, 'aems.')
                         || str_starts_with($code, 'aem.')
                         || str_starts_with($code, 'cms.action-plan.')
                         || str_starts_with($code, 'cms.progress.')
                         || str_starts_with($code, 'cms.evidence.')
                         || str_starts_with($code, 'cms.validation.')
                         || str_starts_with($code, 'cms.validation-evidence.')
                         || str_starts_with($code, 'cms.disposition.')
                         || str_starts_with($code, 'cms.disposition-evidence.')
                         || str_starts_with($code, 'cms.reopening.')
                         || str_starts_with($code, 'cms.reopening-evidence.')
                         || str_starts_with($code, 'cms.automation.')
                         || str_starts_with($code, 'cms.report.')
                         || str_starts_with($code, 'armis.provider.')
                         || in_array($code, ['access.auditee_scope', 'access.read_only_scope'], true)
                        || in_array($code, [
                            'cms.dashboard.view',
                            'cms.recommendation.view',
                            'cms.recommendation.assign',
                            'cms.recommendation.monitor',
                            'cms.administration.monitor',
                        ], true))
                    ->merge([
                        'aem.view',
                        'cms.administration.monitor',
                     'aems.engagement.view',
                        'aems.foundation.view',
                        'aems.planning-package.view',
                        'aems.team.view',
                        'aems.team.safeguard_view',
                        'aems.fieldwork.view',
                        'aems.entry-conference.view',
                        'aems.report.view_issued',
                        'aems.completion-assessment.view',
                        'aems.completion-transfer.view',
                        'aems.closure.view',
                        'aems.document-index.view',
                        'aems.retention.view',
                        'aems.records.view',
                        'aems.calendar.view',
                        'armis.provider.view',
                    ])
                    ->all(),
            ],
            'agis_admin' => [
                'name' => 'AGIS Administrator',
                'description' => 'Administration of registries, master lists, access, configuration, and platform monitoring.',
                'is_system' => true,
                'office_access_scope' => 'ALL',
                'engagement_access_scope' => 'ALL',
                'permissions' => [
                    'dashboard.view',
                    'offices.view', 'offices.create', 'offices.update', 'offices.delete',
                    'offices.restore',
                    'audit_areas.view', 'audit_areas.create', 'audit_areas.update', 'audit_areas.delete',
                    'audit_areas.restore',
                    'audit_focus.view', 'audit_focus.create', 'audit_focus.update', 'audit_focus.delete',
                    'audit_focus.restore',
                    'users.view', 'users.create', 'users.update', 'users.deactivate',
                    'users.activate', 'users.archive', 'users.restore', 'users.lock', 'users.unlock',
                    'roles.view', 'roles.create', 'roles.clone', 'roles.update', 'roles.delete', 'roles.restore',
                    'permissions.view', 'permissions.update',
                    'master_lists.view', 'master_lists.manage',
                    'iap.view', 'iap.baics.view', 'aem.view', 'afr.view', 'cms.view', 'arms.view', 'ais.view',
                    'cms.administration.monitor',
                    'cms.automation.view', 'cms.automation.manage', 'cms.automation.run',
                    'cms.report.view', 'cms.report.export',
                    ...collect(array_keys($permissionIds))
                        ->reject(fn (string $code): bool => str_starts_with($code, 'armis.provider.'))
                        ->filter(fn (string $code): bool => str_starts_with($code, 'armis.'))->all(),
                    'armis.provider.view', 'armis.provider.monitor',
                    'aems.engagement.view', 'aems.team.view', 'aems.report.view_issued',
                    'aems.team.safeguard_view',
                     'aems.foundation.view',
                    'aems.planning-package.view',
                    'aems.completion-assessment.view', 'aems.closure.view',
                    'aems.completion-transfer.view',
                    'aems.document-index.view', 'aems.retention.view',
                    'aems.records.view', 'aems.calendar.view',
                    'documents.view', 'documents.upload', 'documents.update', 'documents.review', 'documents.approve',
                    'documents.view_confidential', 'documents.view_restricted',
                    'documents.download', 'documents.delete', 'documents.restore',
                    'notifications.view', 'notifications.manage',
                    'workflows.view', 'workflows.create', 'workflows.update',
                    'workflows.publish', 'workflows.archive', 'workflows.restore',
                    'workflows.start', 'workflows.act', 'workflows.monitor',
                    'activity_logs.view', 'activity_logs.export', 'audit_logs.view', 'audit_logs.export',
                    'system_configuration.view', 'system_configuration.manage',
                    'administrative_reports.view', 'administrative_reports.export',
                    'profile.view', 'profile.update', 'profile.change_password',
                ],
            ],
            'cias_management' => [
                'name' => 'CIAS Management',
                'description' => 'CIAS leadership oversight, review, approval, resource management, and reporting.',
                'is_system' => true,
                'office_access_scope' => 'ALL',
                'engagement_access_scope' => 'ALL',
                'permissions' => [
                    'dashboard.view', 'offices.view', 'audit_areas.view', 'audit_focus.view',
                    'users.view', 'master_lists.view',
                    'iap.view', 'iap.create', 'iap.update', 'iap.assess_risk', 'iap.baics.view',
                    'iap.manage_universe',
                    'iap.manage_engagements', 'iap.assign_team', 'iap.submit',
                    'iap.review', 'iap.approve', 'iap.activate', 'iap.complete',
                    'iap.create_revision', 'iap.archive', 'iap.restore', 'iap.export',
                    ...collect(array_keys($permissionIds))->filter(fn (string $code): bool => str_starts_with($code, 'iap.baics.'))->all(),
                    'aem.view', 'aem.create', 'aem.update', 'aem.assign', 'aem.review', 'aem.close',
                    ...collect(array_keys($permissionIds))
                        ->filter(fn (string $code): bool => str_starts_with($code, 'aems.'))
                        ->all(),
                    'aems.review.own_submission',
                    'afr.view', 'afr.create', 'afr.update', 'afr.review', 'afr.approve',
                    'cms.view', 'cms.update', 'cms.validate', 'cms.approve_extension', 'cms.close',
                    'cms.dashboard.view', 'cms.recommendation.view',
                    'cms.recommendation.assign', 'cms.recommendation.monitor',
                    'cms.action-plan.view', 'cms.action-plan.review',
                    'cms.action-plan.accept', 'cms.action-plan.return',
                    'cms.action-plan.revise',
                    'cms.progress.view', 'cms.progress.review',
                    'cms.progress.return', 'cms.progress.record',
                    'cms.evidence.view', 'cms.evidence.download',
                    'cms.validation.view', 'cms.validation.create',
                    'cms.validation.assign', 'cms.validation.review',
                    'cms.validation.return', 'cms.validation.finalize',
                    'cms.validation-evidence.view',
                    'cms.validation-evidence.download',
                    'cms.extension.view', 'cms.extension.review', 'cms.extension.return',
                    'cms.extension.recommend', 'cms.extension.approve', 'cms.extension.reject',
                    'cms.extension-evidence.view', 'cms.extension-evidence.download',
                    ...collect(array_keys($permissionIds))->filter(fn (string $code): bool => str_starts_with($code, 'cms.escalation.'))->all(),
                    'cms.escalation-evidence.view', 'cms.escalation-evidence.download',
                    ...collect(array_keys($permissionIds))->filter(fn (string $code): bool => str_starts_with($code, 'cms.closure.'))->all(),
                    'cms.closure-evidence.view', 'cms.closure-evidence.download',
                    ...collect(array_keys($permissionIds))->filter(fn (string $code): bool => str_starts_with($code, 'cms.disposition.'))->all(),
                    'cms.disposition-evidence.view', 'cms.disposition-evidence.download',
                    ...collect(array_keys($permissionIds))->filter(fn (string $code): bool => str_starts_with($code, 'cms.reopening.'))->all(),
                    'cms.reopening-evidence.view', 'cms.reopening-evidence.download',
                    ...collect(array_keys($permissionIds))->filter(fn (string $code): bool => str_starts_with($code, 'cms.automation.'))->all(),
                    ...collect(array_keys($permissionIds))->filter(fn (string $code): bool => str_starts_with($code, 'cms.report.'))->all(),
                    ...collect(array_keys($permissionIds))->filter(fn (string $code): bool => str_starts_with($code, 'armis.'))->all(),
                    'arms.view', 'arms.manage', 'ais.view', 'ais.export',
                    'documents.view', 'documents.upload', 'documents.update', 'documents.review', 'documents.approve',
                    'documents.view_confidential', 'documents.view_restricted',
                    'documents.download', 'documents.delete', 'documents.restore',
                    'notifications.view', 'notifications.manage',
                    'workflows.view', 'workflows.start', 'workflows.act', 'workflows.monitor',
                    'activity_logs.view', 'activity_logs.export', 'audit_logs.view', 'audit_logs.export',
                    'administrative_reports.view', 'administrative_reports.export',
                    'profile.view', 'profile.update', 'profile.change_password',
                ],
            ],
            'agis_user' => [
                'name' => 'AGIS User',
                'description' => 'Operational access to assigned plans, engagements, findings, and evidence.',
                'is_system' => true,
                'office_access_scope' => 'ALL',
                'engagement_access_scope' => 'ASSIGNED',
                'permissions' => [
                    'dashboard.view',
                    'offices.view', 'audit_areas.view', 'audit_focus.view', 'master_lists.view',
                    'iap.view', 'iap.update', 'iap.assess_risk',
                    ...collect(array_keys($permissionIds))->filter(fn (string $code): bool => str_starts_with($code, 'iap.baics.'))->all(),
                    'iap.manage_engagements', 'iap.assign_team',
                    'aem.view', 'aem.update', 'aem.manage_workpapers',
                     'aems.engagement.view', 'aems.engagement.update',
                     'aems.engagement.transition',
                     'aems.foundation.view', 'aems.foundation.manage_scope',
                    'aems.planning-package.view', 'aems.planning-package.create', 'aems.planning-package.update', 'aems.planning-package.review',
                    'aems.team.view',
                    'aems.team.history',
                    'aems.team.safeguard_view', 'aems.team.safeguard_declare', 'aems.team.safeguard_review',
                    'aems.aeo.view', 'aems.aeo.prepare', 'aems.aeo.review',
                    'aems.aeo.sign', 'aems.aeo.acknowledge',
                    'aems.aep.view', 'aems.aep.create', 'aems.aep.review',
                    'aems.program.view', 'aems.program.manage', 'aems.program.review',
                    'aems.fieldwork.view', 'aems.fieldwork.create', 'aems.fieldwork.review',
                    'aems.working-paper.view', 'aems.working-paper.create',
                    'aems.working-paper.review', 'aems.working-paper.approve',
                    'aems.evidence.view', 'aems.evidence.upload', 'aems.evidence.verify', 'aems.evidence.assess',
                    'aems.evidence.outcome', 'aems.evidence.link_report',
                    'aems.evidence-request.view', 'aems.evidence-request.create', 'aems.evidence-request.update',
                    'aems.evidence-request.submit', 'aems.evidence-request.send', 'aems.evidence-request.acknowledge', 'aems.evidence-request.receive',
                    'aems.evidence-request.assess', 'aems.evidence-request.extend', 'aems.evidence-request.extension_approve', 'aems.evidence-request.overdue', 'aems.evidence-request.escalate',
                    'aems.issue.view', 'aems.issue.create', 'aems.issue.validate',
                    'aems.issue.merge', 'aems.issue.resolve', 'aems.issue.observe', 'aems.issue.refer', 'aems.issue.close_without_finding', 'aems.issue.withdraw',
                    'aems.afr.view', 'aems.afr.transmit', 'aems.afr.delivery', 'aems.afr.acknowledge',
                    'aems.finding.view', 'aems.finding.create', 'aems.finding.review',
                    'aems.finding.validate', 'aems.finding.revise',
                    'aems.management-response.view',
                    'aems.management-response.request_clarification', 'aems.management-response.request_extension',
                    'aems.management-response.approve_extension', 'aems.management-response.reject_extension', 'aems.management-response.supplement',
                    'aems.rejoinder.view', 'aems.rejoinder.create', 'aems.rejoinder.finalize',
                    'aems.review-note.view', 'aems.review-note.create', 'aems.review-note.update',
                    'aems.review-note.finalize', 'aems.review-note.revise', 'aems.review-note.attach',
                    'aems.task.view', 'aems.task.create', 'aems.task.update', 'aems.task.assign',
                    'aems.task.complete', 'aems.task.cancel', 'aems.task.reopen', 'aems.task.escalate',
                    'aems.due-process.view', 'aems.due-process.create', 'aems.due-process.remind',
                    'aems.due-process.record_non_response', 'aems.due-process.attach',
                    'aems.escalation-candidate.view', 'aems.escalation-candidate.create',
                    'aems.escalation-candidate.review', 'aems.escalation-candidate.resolve',
                    'aems.escalation-candidate.dismiss',
                    'aems.conference.view', 'aems.conference.manage',
                    'aems.entry-conference.view', 'aems.entry-conference.manage',
                    'aems.entry-conference.acknowledge',
                    'aems.report.view', 'aems.report.create', 'aems.report.review',
                    'aems.report.view_issued', 'aems.report.distribute', 'aems.report.amend',
                    'aems.report.withdraw', 'aems.report.supersede', 'aems.report.authority',
                    'aems.report.signatory', 'aems.report.transmit', 'aems.report.export',
                    'aems.report.close_admin',
                    'aems.completion-assessment.view', 'aems.completion-assessment.create',
                    'aems.completion-assessment.update', 'aems.completion-assessment.submit',
                    'aems.completion-assessment.review',
                    'aems.completion-transfer.view', 'aems.completion-transfer.reconcile',
                    'aems.closure.view', 'aems.closure.create', 'aems.closure.update',
                    'aems.closure.submit', 'aems.closure.review',
                    'aems.document-index.view', 'aems.document-index.manage',
                    'aems.retention.view', 'aems.retention.manage',
                    'aems.records.view', 'aems.records.search', 'aems.calendar.view', 'aems.calendar.manage',
                    'aems.engagement.reopen_request',
                    'afr.view', 'afr.create', 'afr.update', 'afr.submit',
                    'cms.view', 'cms.update', 'cms.submit_evidence',
                    'cms.dashboard.view', 'cms.recommendation.view',
                    'cms.recommendation.monitor',
                    'cms.action-plan.view', 'cms.action-plan.review',
                    'cms.action-plan.accept', 'cms.action-plan.return',
                    'cms.progress.view', 'cms.progress.review',
                    'cms.progress.return', 'cms.progress.record',
                    'cms.evidence.view', 'cms.evidence.download',
                    'cms.validation.view', 'cms.validation.update',
                    'cms.validation.submit', 'cms.validation.revise',
                    'cms.validation-evidence.view',
                    'cms.validation-evidence.upload',
                    'cms.validation-evidence.download',
                    'cms.validation-evidence.remove_draft',
                    'cms.extension.view', 'cms.extension.review', 'cms.extension.return',
                    'cms.extension.recommend',
                    'cms.extension-evidence.view', 'cms.extension-evidence.download',
                    'cms.escalation.view', 'cms.escalation.create', 'cms.escalation.update',
                    'cms.escalation.submit', 'cms.escalation.revise', 'cms.escalation.response-review',
                    'cms.escalation.response-return', 'cms.escalation.response-accept',
                    'cms.escalation-evidence.view', 'cms.escalation-evidence.download',
                    'cms.closure.view', 'cms.closure.request', 'cms.closure.update', 'cms.closure.submit', 'cms.closure.revise',
                    'cms.closure-evidence.view', 'cms.closure-evidence.upload', 'cms.closure-evidence.download', 'cms.closure-evidence.remove_draft',
                    'cms.disposition.view', 'cms.disposition.request', 'cms.disposition.update', 'cms.disposition.submit', 'cms.disposition.revise',
                    'cms.disposition-evidence.view', 'cms.disposition-evidence.upload', 'cms.disposition-evidence.download', 'cms.disposition-evidence.remove_draft',
                    'cms.reopening.view', 'cms.reopening.request', 'cms.reopening.update', 'cms.reopening.submit', 'cms.reopening.revise',
                    'cms.reopening-evidence.view', 'cms.reopening-evidence.upload', 'cms.reopening-evidence.download', 'cms.reopening-evidence.remove_draft',
                    'cms.automation.view',
                    'cms.report.view', 'cms.report.export',
                    'armis.resource.view', 'armis.resource.create', 'armis.resource.update',
                    'armis.competency.view', 'armis.competency.manage',
                    'armis.availability.view', 'armis.availability.manage',
                    'armis.capacity.view', 'armis.capacity.manage',
                    'armis.workload.view', 'armis.assignment.view', 'armis.assignment.manage',
                    'armis.actuals.view', 'armis.actuals.record',
                    'arms.view', 'ais.view',
                    'documents.view', 'documents.upload', 'documents.download',
                    'documents.view_confidential',
                    'notifications.view',
                    'workflows.start', 'workflows.act',
                    'administrative_reports.view',
                    'profile.view', 'profile.update', 'profile.change_password',
                ],
            ],
            'auditee_representative' => [
                'name' => 'Auditee Representative',
                'description' => 'Office representative access to assigned engagements, findings, requests, and evidence.',
                'is_system' => true,
                'office_access_scope' => 'OWN_OFFICE',
                'engagement_access_scope' => 'ASSIGNED',
                'permissions' => [
                    'access.auditee_scope',
                    'dashboard.view',
                    'aems.finding.view',
                    'aems.afr.view', 'aems.afr.acknowledge',
                    'aems.aeo.view', 'aems.aeo.acknowledge',
                    'aems.management-response.view', 'aems.management-response.submit', 'aems.management-response.request_extension', 'aems.management-response.supplement',
                    'aems.rejoinder.view',
                    'aems.review-note.view', 'aems.due-process.view', 'aems.task.view',
                    'aems.escalation-candidate.view',
                    'aems.conference.view', 'aems.conference.acknowledge',
                    'aems.entry-conference.view', 'aems.entry-conference.acknowledge',
                    'aems.evidence-request.view', 'aems.evidence-request.acknowledge', 'aems.evidence-request.respond',
                    'aems.report.view_issued',
                    'aems.report.acknowledge',
                    'cms.view', 'cms.update', 'cms.submit_evidence',
                    'cms.dashboard.view', 'cms.recommendation.view',
                    'cms.action-plan.view', 'cms.action-plan.create',
                    'cms.action-plan.update', 'cms.action-plan.submit',
                    'cms.action-plan.revise',
                    'cms.progress.view', 'cms.progress.create',
                    'cms.progress.update', 'cms.progress.submit',
                    'cms.progress.revise',
                    'cms.evidence.view', 'cms.evidence.upload',
                    'cms.evidence.download', 'cms.evidence.remove_draft',
                    'cms.validation.view',
                    'cms.extension.view', 'cms.extension.create', 'cms.extension.update',
                    'cms.extension.submit', 'cms.extension.revise',
                    'cms.extension-evidence.view', 'cms.extension-evidence.upload',
                    'cms.extension-evidence.download', 'cms.extension-evidence.remove_draft',
                    'cms.escalation.view', 'cms.escalation.acknowledge', 'cms.escalation.respond',
                    'cms.escalation.revise', 'cms.escalation-evidence.view',
                    'cms.escalation-evidence.upload', 'cms.escalation-evidence.download',
                    'cms.escalation-evidence.remove_draft',
                    'cms.closure.view', 'cms.closure.request', 'cms.closure.update', 'cms.closure.submit', 'cms.closure.revise',
                    'cms.closure-evidence.view', 'cms.closure-evidence.upload', 'cms.closure-evidence.download', 'cms.closure-evidence.remove_draft',
                    'cms.disposition.view', 'cms.disposition.request', 'cms.disposition.update', 'cms.disposition.submit', 'cms.disposition.revise',
                    'cms.disposition-evidence.view', 'cms.disposition-evidence.upload', 'cms.disposition-evidence.download', 'cms.disposition-evidence.remove_draft',
                    'cms.reopening.view', 'cms.reopening.request', 'cms.reopening.update', 'cms.reopening.submit', 'cms.reopening.revise',
                    'cms.reopening-evidence.view', 'cms.reopening-evidence.upload', 'cms.reopening-evidence.download', 'cms.reopening-evidence.remove_draft',
                    'cms.report.view',
                    'documents.view', 'documents.upload', 'documents.download',
                    'notifications.view',
                    'workflows.act',
                    'profile.view', 'profile.update', 'profile.change_password',
                ],
            ],
            'read_only' => [
                'name' => 'Read Only User',
                'description' => 'Inquiry-only access to authorized dashboards, registries, audit records, and reports.',
                'is_system' => true,
                'office_access_scope' => 'ALL',
                'engagement_access_scope' => 'ALL',
                'permissions' => [
                    'access.read_only_scope',
                    'dashboard.view', 'offices.view', 'audit_areas.view', 'audit_focus.view',
                    'master_lists.view', 'iap.view', 'iap.baics.view', 'aem.view', 'afr.view', 'cms.view',
                    'cms.dashboard.view', 'cms.recommendation.view',
                    'cms.action-plan.view',
                    'cms.progress.view',
                    'cms.evidence.view', 'cms.evidence.download',
                    'cms.validation.view',
                    'cms.extension.view',
                    'cms.extension-evidence.view', 'cms.extension-evidence.download',
                    'cms.escalation.view', 'cms.escalation-evidence.view', 'cms.escalation-evidence.download',
                    'cms.closure.view', 'cms.closure-evidence.view', 'cms.closure-evidence.download',
                    'cms.disposition.view', 'cms.disposition-evidence.view', 'cms.disposition-evidence.download',
                    'cms.reopening.view', 'cms.reopening-evidence.view', 'cms.reopening-evidence.download',
                    'cms.automation.view',
                    'cms.report.view', 'cms.report.export',
                    'armis.resource.view', 'armis.competency.view', 'armis.availability.view',
                    'armis.capacity.view', 'armis.workload.view', 'armis.assignment.view', 'armis.actuals.view',
                    'armis.provider.view',
                    'aems.report.view_issued',
                    'aems.review-note.view', 'aems.task.view', 'aems.due-process.view',
                    'aems.escalation-candidate.view',
                    'arms.view', 'ais.view', 'documents.view', 'notifications.view',
                    'administrative_reports.view',
                    'profile.view', 'profile.update', 'profile.change_password',
                ],
            ],
        ];

        foreach ($roles as $code => $attributes) {
            $role = Role::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $attributes['name'],
                    'description' => $attributes['description'],
                    'is_system' => $attributes['is_system'],
                    'is_active' => true,
                    'office_access_scope' => $attributes['office_access_scope'],
                    'engagement_access_scope' => $attributes['engagement_access_scope'],
                ],
            );

            $role->permissions()->sync(
                collect($attributes['permissions'])
                    ->map(fn (string $permission) => $permissionIds[$permission])
                    ->all(),
            );
        }

        if (config('demo.full_render_seeders')) {
            return;
        }

        Permission::query()
            ->whereIn('code', ['iap.delete'])
            ->delete();

        $legacyRoles = [
            'system_admin' => 'platform_admin',
            'audit_manager' => 'cias_management',
            'internal_auditor' => 'agis_user',
        ];

        foreach ($legacyRoles as $legacyCode => $replacementCode) {
            $legacyRole = Role::query()->where('code', $legacyCode)->first();
            $replacementRole = Role::query()->where('code', $replacementCode)->firstOrFail();

            if (! $legacyRole) {
                continue;
            }

            $legacyRole->assignedUsers()->withTrashed()->get()->each(
                function (User $user) use ($legacyRole, $replacementRole): void {
                    $roleIds = $user->roles()
                        ->where('roles.id', '<>', $legacyRole->id)
                        ->pluck('roles.id')
                        ->push($replacementRole->id)
                        ->unique()
                        ->values()
                        ->all();
                    $primaryRoleId = (int) $user->role_id === (int) $legacyRole->id
                        ? $replacementRole->id
                        : $user->role_id;
                    $user->syncRoleAssignments($roleIds, $primaryRoleId);
                },
            );
            $legacyRole->users()->update(['role_id' => $replacementRole->id]);
            $legacyRole->delete();
        }
    }
}
