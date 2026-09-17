<?php

namespace Database\Seeders;

use App\Models\IapPlanEngagement;
use App\Models\InternalAuditPlan;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\NotificationService;
use Illuminate\Database\Seeder;

/**
 * Seeds actionable notifications that reference records in the demonstration data.
 */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $notifications = app(NotificationService::class);
        $plan = InternalAuditPlan::query()
            ->where('is_current_revision', true)
            ->latest('fiscal_year')
            ->first();
        $engagement = IapPlanEngagement::query()
            ->with(['plan:id,plan_code,title', 'offices:id,code,name'])
            ->whereNotNull('planned_start_date')
            ->orderBy('planned_start_date')
            ->first();
        $workflow = WorkflowDefinition::query()
            ->where('status', 'PUBLISHED')
            ->where('code', 'IAP_ANNUAL_PLAN_APPROVAL')
            ->first();

        $records = [
            'admin' => [[
                'type' => 'SYSTEM_READY',
                'category' => 'SYSTEM',
                'priority' => 'NORMAL',
                'moduleCode' => 'CORE',
                'title' => 'Notification Center is ready',
                'message' => 'Your live AGIS notification inbox, delivery preferences, workflow alerts, and due-date reminders are available.',
                'actionUrl' => '/notifications',
                'actionLabel' => 'Open Notification Center',
                'dedupeKey' => 'demo:notification-center-ready',
            ]],
            'agisadmin' => $workflow ? [[
                'type' => 'WORKFLOW_CONFIGURATION',
                'category' => 'WORKFLOW',
                'priority' => 'HIGH',
                'moduleCode' => 'CORE',
                'title' => "{$workflow->name} is published",
                'message' => "{$workflow->code} version {$workflow->version} is the current published workflow definition.",
                'actionUrl' => "/workflow-management?workflow={$workflow->id}",
                'actionLabel' => 'Open workflow definition',
                'subjectType' => WorkflowDefinition::class,
                'subjectId' => $workflow->id,
                'subjectCode' => $workflow->code,
                'metadata' => ['workflowDefinitionId' => $workflow->id],
                'dedupeKey' => 'demo:published-workflow',
            ]] : [],
            'departmenthead' => $plan ? [[
                'type' => 'PLAN_REVIEW',
                'category' => 'WORKFLOW',
                'priority' => 'URGENT',
                'moduleCode' => 'IAP',
                'title' => "{$plan->plan_code} requires management attention",
                'message' => "{$plan->title} is currently {$this->words($plan->status)} and contains the seeded 2026 planning records.",
                'actionUrl' => "/internal-audit-planning/{$plan->id}",
                'actionLabel' => 'Open annual plan',
                'subjectType' => InternalAuditPlan::class,
                'subjectId' => $plan->id,
                'subjectCode' => $plan->plan_code,
                'metadata' => ['planId' => $plan->id, 'status' => $plan->status],
                'dedupeKey' => 'demo:iap-plan-review',
            ]] : [],
            'auditor' => $engagement ? [
                [
                    'type' => 'IAP_TEAM_ASSIGNMENT',
                    'category' => 'ASSIGNMENT',
                    'priority' => 'HIGH',
                    'moduleCode' => 'IAP',
                    'title' => "Assigned to {$engagement->engagement_code}",
                    'message' => "{$engagement->title} is scheduled for {$engagement->planned_start_date?->format('M j')}–{$engagement->planned_end_date?->format('M j, Y')}.",
                    'actionUrl' => "/internal-audit-planning/scheduling?engagement={$engagement->id}",
                    'actionLabel' => 'Open scheduled engagement',
                    'subjectType' => IapPlanEngagement::class,
                    'subjectId' => $engagement->id,
                    'subjectCode' => $engagement->engagement_code,
                    'metadata' => ['engagementId' => $engagement->id, 'planId' => $engagement->plan_id],
                    'dedupeKey' => 'demo:auditor-assignment',
                ],
                [
                    'type' => 'AUDIT_START_REMINDER',
                    'category' => 'DUE_DATE',
                    'priority' => 'NORMAL',
                    'moduleCode' => 'IAP',
                    'title' => "{$engagement->engagement_code} schedule reminder",
                    'message' => "Review the approved planning record for {$engagement->title} and prepare the entrance-conference materials.",
                    'actionUrl' => "/internal-audit-planning/{$engagement->plan_id}",
                    'actionLabel' => 'Open plan workspace',
                    'subjectType' => IapPlanEngagement::class,
                    'subjectId' => $engagement->id,
                    'subjectCode' => $engagement->engagement_code,
                    'metadata' => ['engagementId' => $engagement->id, 'planId' => $engagement->plan_id],
                    'dedupeKey' => 'demo:audit-due',
                ],
            ] : [],
            'auditee' => [[
                'type' => 'PROFILE_CONFIRMATION',
                'category' => 'SYSTEM',
                'priority' => 'NORMAL',
                'moduleCode' => 'CORE',
                'title' => 'Confirm your seeded employee profile',
                'message' => 'Review your employee ID, office, position, employment type, contact information, and account details.',
                'actionUrl' => '/profile',
                'actionLabel' => 'Review my profile',
                'subjectType' => User::class,
                'dedupeKey' => 'demo:auditee-profile',
            ]],
            'mayor' => $plan ? [[
                'type' => 'PLAN_REPORT_AVAILABLE',
                'category' => 'SYSTEM',
                'priority' => 'LOW',
                'moduleCode' => 'IAP',
                'title' => "{$plan->plan_code} planning reports available",
                'message' => "Read-only planning reports for {$plan->title} can be reviewed and exported from IAP Reports.",
                'actionUrl' => "/internal-audit-planning/reports?report=annual-audit-schedule&plan={$plan->id}",
                'actionLabel' => 'View IAP reports',
                'subjectType' => InternalAuditPlan::class,
                'subjectId' => $plan->id,
                'subjectCode' => $plan->plan_code,
                'metadata' => ['planId' => $plan->id],
                'dedupeKey' => 'demo:mayor-report',
            ]] : [],
        ];

        foreach ($records as $username => $payloads) {
            $user = User::query()->where('username', $username)->first();
            if (! $user) {
                continue;
            }
            foreach ($payloads as $payload) {
                if (($payload['subjectType'] ?? null) === User::class && empty($payload['subjectId'])) {
                    $payload['subjectId'] = $user->id;
                    $payload['subjectCode'] = $user->employee_id;
                }
                $notifications->send([$user], $payload);
            }
        }
    }

    private function words(string $value): string
    {
        return strtolower(str_replace('_', ' ', $value));
    }
}
