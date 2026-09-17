<?php

namespace App\Services;

use App\Models\AuditFinding;
use App\Models\AuditProgramProcedure;
use App\Models\ExitConference;
use App\Models\IapPlanEngagement;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Services\Cms\CmsAutomationService;
use Illuminate\Support\Collection;

/**
 * Generates deduplicated due-date and workflow reminder notifications.
 */
class NotificationReminderService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly CmsAutomationService $cmsAutomation,
        private readonly AemsWorkQueueService $aemsWorkQueue,
        private readonly RuntimeConfiguration $runtime,
    ) {}

    public function dispatch(): int
    {
        $delivered = 0;

        WorkflowInstance::query()
            ->where('status', 'ACTIVE')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->addHours(48))
            ->with(['definition:id,name', 'currentStep.outgoingTransitions:id,from_step_id,required_permission_id'])
            ->each(function (WorkflowInstance $instance) use (&$delivered): void {
                $overdue = $instance->due_at->isPast();
                $recipients = collect([$instance->started_by]);
                $permissionIds = $instance->currentStep->outgoingTransitions
                    ->pluck('required_permission_id')
                    ->filter()
                    ->unique()
                    ->values();
                if ($permissionIds->isNotEmpty()) {
                    $recipients = $recipients->merge(
                        User::query()
                            ->where('is_active', true)
                            ->when(
                                $instance->office_id,
                                fn ($query) => $query->where('office_id', $instance->office_id),
                            )
                            ->where(function ($query) use ($permissionIds): void {
                                $query
                                    ->whereHas('roles.permissions', fn ($permission) => $permission->whereIn('permissions.id', $permissionIds))
                                    ->orWhereHas('role.permissions', fn ($permission) => $permission->whereIn('permissions.id', $permissionIds));
                            })
                            ->pluck('id'),
                    );
                }
                $delivered += $this->notifications->send($recipients->filter()->unique(), [
                    'type' => $overdue ? 'WORKFLOW_OVERDUE' : 'WORKFLOW_DUE',
                    'category' => $overdue ? 'OVERDUE' : 'DUE_DATE',
                    'priority' => $overdue ? 'URGENT' : 'HIGH',
                    'moduleCode' => $instance->module_code,
                    'title' => $overdue
                        ? "Overdue: {$instance->subject_code}"
                        : "Due soon: {$instance->subject_code}",
                    'message' => "{$instance->currentStep->name} is due {$instance->due_at->diffForHumans()}.",
                    'actionUrl' => "/workflow-management?instance={$instance->id}",
                    'actionLabel' => 'Review workflow',
                    'subjectType' => $instance->subject_type,
                    'subjectId' => $instance->subject_id,
                    'subjectCode' => $instance->subject_code,
                    'dedupeKey' => "workflow:{$instance->id}:due:{$instance->due_at->toDateString()}:".($overdue ? 'overdue' : 'upcoming'),
                ])->count();
            });

        IapPlanEngagement::query()
            ->where('schedule_status', 'SCHEDULED')
            ->whereDate('planned_start_date', '>=', today())
            ->whereDate('planned_start_date', '<=', today()->addDays(7))
            ->with(['teamMembers', 'plan:id,plan_code'])
            ->each(function (IapPlanEngagement $engagement) use (&$delivered): void {
                $delivered += $this->notifications->send(
                    $engagement->teamMembers->pluck('user_id'),
                    [
                        'type' => 'AUDIT_START_REMINDER',
                        'category' => 'DUE_DATE',
                        'priority' => 'HIGH',
                        'moduleCode' => 'IAP',
                        'title' => "Upcoming audit: {$engagement->engagement_code}",
                        'message' => "{$engagement->title} starts {$engagement->planned_start_date->diffForHumans()}.",
                        'actionUrl' => "/internal-audit-planning/{$engagement->plan_id}",
                        'actionLabel' => 'Open annual plan',
                        'subjectType' => 'IAP_PLAN_ENGAGEMENT',
                        'subjectId' => $engagement->id,
                        'subjectCode' => $engagement->engagement_code,
                        'dedupeKey' => "iap-engagement:{$engagement->id}:start:{$engagement->planned_start_date->toDateString()}",
                    ],
                )->count();
            });

        $delivered += $this->dispatchAemsProcedureReminders();
        $delivered += $this->dispatchAemsResponseReminders();
        $delivered += $this->dispatchAemsConferenceReminders();
        $delivered += $this->aemsWorkQueue->dispatchDueReminders();
        // CMS automation creates reminders and reviewable candidates only; it
        // never issues escalation notices or performs closure decisions.
        $delivered += $this->cmsAutomation->run();

        return $delivered;
    }

    private function dispatchAemsProcedureReminders(): int
    {
        $delivered = 0;
        if (! $this->runtime->boolean('aems_reminders_enabled')) {
            return 0;
        }

        AuditProgramProcedure::query()
            ->whereDate('target_date', '<=', today()->addDays(max(1, (int) ceil($this->runtime->integer('aems_reminder_due_hours') / 24))))
            ->whereNotIn('status', ['COMPLETED', 'WAIVED'])
            ->with([
                'program.engagement.teamMembers' => fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('ended_at'),
            ])
            ->each(function (AuditProgramProcedure $procedure) use (&$delivered): void {
                $engagement = $procedure->program?->engagement;
                if (! $engagement || ! $engagement->is_active) {
                    return;
                }
                $overdue = $procedure->target_date?->isBefore(today());
                $recipients = collect([$procedure->assigned_to])
                    ->merge(
                        $engagement->teamMembers
                            ->whereIn('assignment_role_code', ['SUPERVISOR', 'TEAM_LEADER'])
                            ->pluck('user_id'),
                    )
                    ->filter()
                    ->unique();
                $delivered += $this->notifications->send($recipients, [
                    'type' => $overdue ? 'AEMS_PROCEDURE_OVERDUE' : 'AEMS_PROCEDURE_DUE',
                    'category' => $overdue ? 'OVERDUE' : 'DUE_DATE',
                    'priority' => $overdue ? 'URGENT' : 'HIGH',
                    'moduleCode' => 'AEMS',
                    'title' => ($overdue ? 'Overdue procedure: ' : 'Procedure due soon: ').$procedure->procedure_code,
                    'message' => "{$procedure->procedure_description} was due "
                        .$procedure->target_date?->format('M j, Y').'.',
                    'actionUrl' => "/audit-engagement-management/audit-program?engagementId={$engagement->id}",
                    'actionLabel' => 'Open Audit Program',
                    'subjectType' => AuditProgramProcedure::class,
                    'subjectId' => $procedure->id,
                    'subjectCode' => $procedure->procedure_code,
                    'dedupeKey' => "aems:procedure:{$procedure->id}:due:"
                        .$procedure->target_date?->toDateString().':'.($overdue ? 'overdue' : 'upcoming'),
                    'metadata' => [
                        'engagementId' => $engagement->id,
                        'targetDate' => $procedure->target_date?->toDateString(),
                    ],
                ])->count();
            });

        return $delivered;
    }

    private function dispatchAemsResponseReminders(): int
    {
        $delivered = 0;
        if (! $this->runtime->boolean('aems_reminders_enabled')) {
            return 0;
        }

        AuditFinding::query()
            ->where('is_current_revision', true)
            ->whereIn('status', ['COMMUNICATED', 'AWAITING_MANAGEMENT_RESPONSE'])
            ->whereNotNull('management_response_due_date')
            ->whereDate('management_response_due_date', '<=', today()->addDays($this->runtime->integer('aems_response_reminder_days')))
            ->with([
                'engagement.teamMembers' => fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('ended_at'),
            ])
            ->each(function (AuditFinding $finding) use (&$delivered): void {
                $engagement = $finding->engagement;
                if (! $engagement || ! $engagement->is_active) {
                    return;
                }
                $overdue = $finding->management_response_due_date->isPast();
                $recipients = $this->auditeeRepresentatives(
                    collect([$finding->responsible_office_id]),
                )->merge(
                    $engagement->teamMembers
                        ->whereIn('assignment_role_code', ['SUPERVISOR', 'TEAM_LEADER'])
                        ->pluck('user_id'),
                )->filter()->unique();
                $delivered += $this->notifications->send($recipients, [
                    'type' => $overdue
                        ? 'AEMS_MANAGEMENT_RESPONSE_OVERDUE'
                        : 'AEMS_MANAGEMENT_RESPONSE_DUE',
                    'category' => $overdue ? 'OVERDUE' : 'DUE_DATE',
                    'priority' => $overdue ? 'URGENT' : 'HIGH',
                    'moduleCode' => 'AEMS',
                    'title' => ($overdue ? 'Overdue response: ' : 'Response due soon: ')
                        .$finding->finding_code,
                    'message' => "Management's response to {$finding->title} is due "
                        .$finding->management_response_due_date->format('M j, Y').'.',
                    'actionUrl' => "/audit-engagement-management/auditee-responses?engagementId={$engagement->id}&findingId={$finding->id}",
                    'actionLabel' => 'Open Auditee Response',
                    'subjectType' => AuditFinding::class,
                    'subjectId' => $finding->id,
                    'subjectCode' => $finding->finding_code,
                    'dedupeKey' => "aems:finding:{$finding->id}:response-due:"
                        .$finding->management_response_due_date->toDateString().':'
                        .($overdue ? 'overdue' : 'upcoming'),
                    'metadata' => [
                        'engagementId' => $engagement->id,
                        'responseDueDate' => $finding->management_response_due_date->toDateString(),
                    ],
                ])->count();
                if ($overdue) {
                    $this->aemsWorkQueue->createCandidateForSystem(
                        $engagement,
                        'MANAGEMENT_RESPONSE_OVERDUE',
                        null,
                        $finding,
                        null,
                        'Management response is overdue and requires review.',
                        [
                            'findingCode' => $finding->finding_code,
                            'dueDate' => $finding->management_response_due_date->toDateString(),
                        ],
                    );
                }
            });

        return $delivered;
    }

    private function dispatchAemsConferenceReminders(): int
    {
        $delivered = 0;
        if (! $this->runtime->boolean('aems_reminders_enabled')) {
            return 0;
        }

        ExitConference::query()
            ->whereIn('status', ['SCHEDULED', 'RESCHEDULED'])
            ->whereBetween('scheduled_start_at', [
                now()->startOfDay(),
                now()->addDays($this->runtime->integer('aems_conference_reminder_days'))->endOfDay(),
            ])
            ->with(['engagement', 'participants'])
            ->each(function (ExitConference $conference) use (&$delivered): void {
                $engagement = $conference->engagement;
                if (! $engagement || ! $engagement->is_active) {
                    return;
                }
                $recipients = $conference->participants->pluck('user_id')->filter()
                    ->merge($this->auditeeRepresentatives(
                        $conference->participants->pluck('office_id')->filter(),
                    ))
                    ->unique();
                $delivered += $this->notifications->send($recipients, [
                    'type' => 'AEMS_EXIT_CONFERENCE_REMINDER',
                    'category' => 'DUE_DATE',
                    'priority' => 'HIGH',
                    'moduleCode' => 'AEMS',
                    'title' => "Upcoming Exit Conference: {$conference->conference_code}",
                    'message' => "The Exit Conference for {$engagement->title} starts "
                        .$conference->scheduled_start_at?->diffForHumans().'.',
                    'actionUrl' => "/audit-engagement-management/conferences?engagementId={$engagement->id}",
                    'actionLabel' => 'Open Conference Management',
                    'subjectType' => ExitConference::class,
                    'subjectId' => $conference->id,
                    'subjectCode' => $conference->conference_code,
                    'dedupeKey' => "aems:conference:{$conference->id}:reminder:"
                        .$conference->scheduled_start_at?->toDateString(),
                    'metadata' => [
                        'engagementId' => $engagement->id,
                        'scheduledStartAt' => $conference->scheduled_start_at?->toISOString(),
                    ],
                ])->count();
            });

        return $delivered;
    }

    /** @return Collection<int, int> */
    private function auditeeRepresentatives(Collection $officeIds): Collection
    {
        return User::query()
            ->whereIn('office_id', $officeIds->filter()->unique())
            ->where('is_active', true)
            ->where(function ($query): void {
                $query
                    ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'access.auditee_scope'))
                    ->orWhereHas('role.permissions', fn ($permission) => $permission->where('code', 'access.auditee_scope'));
            })
            ->pluck('id');
    }
}
