<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\WorkflowInstance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Creates in-app notifications and optionally mirrors them through configured email.
 */
class NotificationService
{
    public function __construct(private readonly RuntimeConfiguration $runtime) {}

    /**
     * @param  iterable<int, User|int>  $recipients
     * @param  array<string, mixed>  $payload
     * @return Collection<int, SystemNotification>
     */
    public function send(iterable $recipients, array $payload): Collection
    {
        $users = User::query()
            ->whereIn('id', collect($recipients)->map(
                fn (User|int $recipient): int => $recipient instanceof User
                    ? $recipient->id
                    : (int) $recipient,
            )->unique())
            ->where('is_active', true)
            ->with(['role.permissions', 'roles.permissions', 'notificationPreference'])
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission('notifications.view'))
            ->filter(fn (User $user): bool => $this->allows($user, $payload['category']));

        return $users->map(function (User $user) use ($payload): SystemNotification {
            $attributes = [
                'actor_id' => $payload['actorId'] ?? null,
                'type' => $payload['type'],
                'category' => $payload['category'],
                'priority' => $payload['priority'] ?? 'NORMAL',
                'module_code' => $payload['moduleCode'] ?? 'CORE',
                'title' => $payload['title'],
                'message' => $payload['message'],
                'action_url' => $payload['actionUrl'] ?? null,
                'action_label' => $payload['actionLabel'] ?? null,
                'subject_type' => $payload['subjectType'] ?? null,
                'subject_id' => $payload['subjectId'] ?? null,
                'subject_code' => $payload['subjectCode'] ?? null,
                'dedupe_key' => $payload['dedupeKey'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
                'expires_at' => $payload['expiresAt'] ?? null,
            ];

            if (! empty($attributes['dedupe_key'])) {
                $notification = SystemNotification::query()->firstOrNew(
                    [
                        'recipient_id' => $user->id,
                        'dedupe_key' => $attributes['dedupe_key'],
                    ],
                );
                $notification->fill($attributes);
                if ($notification->exists && ($payload['renotify'] ?? false)) {
                    $notification->forceFill(['read_at' => null, 'archived_at' => null]);
                }
                $notification->save();

                $this->sendEmailWhenEnabled($user, $payload);

                return $notification;
            }

            $notification = $user->systemNotifications()->create($attributes);
            $this->sendEmailWhenEnabled($user, $payload);

            return $notification;
        })->values();
    }

    /** @param array<string, mixed> $payload */
    public function sendToPermission(string $permissionCode, array $payload, ?int $officeId = null): Collection
    {
        $recipients = User::query()
            ->where('is_active', true)
            ->when($officeId, fn ($query) => $query->where('office_id', $officeId))
            ->where(function ($query) use ($permissionCode): void {
                $query
                    ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', $permissionCode))
                    ->orWhereHas('role.permissions', fn ($permission) => $permission->where('code', $permissionCode));
            })
            ->pluck('id');

        return $this->send($recipients, $payload);
    }

    public function notifyWorkflowStep(
        WorkflowInstance $instance,
        User $actor,
        string $actionName,
    ): Collection {
        $instance->loadMissing([
            'definition:id,name',
            'currentStep.outgoingTransitions:id,from_step_id,required_permission_id',
        ]);
        $recipients = collect([$instance->started_by]);
        $permissionIds = $instance->currentStep->outgoingTransitions
            ->pluck('required_permission_id')
            ->filter()
            ->unique()
            ->values();
        if ($permissionIds->isNotEmpty()) {
            $roleUsers = User::query()
                ->where('is_active', true)
                ->when($instance->office_id, fn ($query) => $query->where('office_id', $instance->office_id))
                ->where(function ($query) use ($permissionIds): void {
                    $query
                        ->whereHas('roles.permissions', fn ($permission) => $permission->whereIn('permissions.id', $permissionIds))
                        ->orWhereHas('role.permissions', fn ($permission) => $permission->whereIn('permissions.id', $permissionIds));
                })
                ->pluck('id');
            $recipients = $recipients->merge($roleUsers);
        }

        return $this->send($recipients->filter()->unique(), [
            'actorId' => $actor->id,
            'type' => 'WORKFLOW_STEP',
            'category' => 'WORKFLOW',
            'priority' => $instance->due_at ? 'HIGH' : 'NORMAL',
            'moduleCode' => $instance->module_code,
            'title' => "{$instance->subject_code}: {$instance->currentStep->name}",
            'message' => "{$actionName} by {$actor->name}. The workflow is now at {$instance->currentStep->name}.",
            'actionUrl' => "/workflow-management?instance={$instance->id}",
            'actionLabel' => 'Open workflow',
            'subjectType' => $instance->subject_type,
            'subjectId' => $instance->subject_id,
            'subjectCode' => $instance->subject_code,
            'dedupeKey' => "workflow:{$instance->id}:lock:{$instance->lock_version}",
            'metadata' => [
                'workflowInstanceId' => $instance->id,
                'workflowDefinition' => $instance->definition->name,
                'stepCode' => $instance->currentStep->code,
                'dueAt' => $instance->due_at?->toISOString(),
            ],
        ]);
    }

    public function preference(User $user): NotificationPreference
    {
        return $user->notificationPreference()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'in_app_enabled' => true,
                'workflow_enabled' => true,
                'assignments_enabled' => true,
                'due_dates_enabled' => true,
                'system_enabled' => true,
                'email_enabled' => false,
                'digest_frequency' => 'IMMEDIATE',
            ],
        );
    }

    private function allows(User $user, string $category): bool
    {
        $preference = $user->notificationPreference
            ?? $this->preference($user);
        if (! $preference->in_app_enabled) {
            return false;
        }

        return match ($category) {
            'WORKFLOW' => $preference->workflow_enabled,
            'ASSIGNMENT' => $preference->assignments_enabled,
            'DUE_DATE', 'OVERDUE' => $preference->due_dates_enabled,
            default => $preference->system_enabled,
        };
    }

    /** @param array<string, mixed> $payload */
    private function sendEmailWhenEnabled(User $user, array $payload): void
    {
        $preference = $user->notificationPreference ?? $this->preference($user);
        if (! $this->runtime->boolean('mail_enabled')
            || ! $preference->email_enabled
            || blank($user->email)) {
            return;
        }

        try {
            // Email is a secondary delivery channel. A transport failure is
            // reported but must not roll back the in-app notification or the
            // business transaction that produced it.
            $this->runtime->apply();
            Mail::raw(
                (string) $payload['message'],
                fn ($message) => $message
                    ->to($user->email, $user->name)
                    ->subject((string) $payload['title']),
            );
        } catch (Throwable $error) {
            report($error);
        }
    }
}
