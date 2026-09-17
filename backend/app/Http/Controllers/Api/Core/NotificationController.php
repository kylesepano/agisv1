<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\NotificationDeliveryRequest;
use App\Models\Office;
use App\Models\Role;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\ActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Serves notifications, delivery preferences, reminders, and read-state actions.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(SystemNotification::CATEGORIES)],
            'priority' => ['nullable', Rule::in(SystemNotification::PRIORITIES)],
            'module' => ['nullable', 'string', 'max:20'],
            'readStatus' => ['nullable', Rule::in(['UNREAD', 'READ'])],
            'includeArchived' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);
        $base = SystemNotification::query()->where('recipient_id', $request->user()->id);
        $summary = [
            'total' => (clone $base)->whereNull('archived_at')->count(),
            'unread' => (clone $base)->whereNull('archived_at')->whereNull('read_at')->count(),
            'actionRequired' => (clone $base)
                ->whereNull('archived_at')
                ->whereIn('category', ['WORKFLOW', 'ASSIGNMENT'])
                ->whereNull('read_at')
                ->count(),
            'overdue' => (clone $base)
                ->whereNull('archived_at')
                ->where('category', 'OVERDUE')
                ->count(),
            'archived' => (clone $base)->whereNotNull('archived_at')->count(),
        ];

        $query = (clone $base)
            ->with('actor:id,employee_id,name,initials')
            ->when(
                ! ($validated['includeArchived'] ?? false),
                fn ($query) => $query->whereNull('archived_at'),
            )
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($scope) use ($search): void {
                    $scope
                        ->where('title', 'ilike', "%{$search}%")
                        ->orWhere('message', 'ilike', "%{$search}%")
                        ->orWhere('subject_code', 'ilike', "%{$search}%");
                });
            })
            ->when($validated['category'] ?? null, fn ($query, $value) => $query->where('category', $value))
            ->when($validated['priority'] ?? null, fn ($query, $value) => $query->where('priority', $value))
            ->when($validated['module'] ?? null, fn ($query, $value) => $query->where('module_code', $value))
            ->when(
                ($validated['readStatus'] ?? null) === 'UNREAD',
                fn ($query) => $query->whereNull('read_at'),
            )
            ->when(
                ($validated['readStatus'] ?? null) === 'READ',
                fn ($query) => $query->whereNotNull('read_at'),
            )
            ->latest();
        $paginator = $query->paginate(
            $validated['perPage'] ?? app(\App\Services\RuntimeConfiguration::class)->paginationSize(),
        );

        return $this->success([
            'notifications' => collect($paginator->items())
                ->map(fn (SystemNotification $notification): array => $this->data($notification))
                ->values(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'summary' => $summary,
            'preferences' => $this->preferenceData($this->notifications->preference($request->user())),
            'options' => [
                'categories' => SystemNotification::CATEGORIES,
                'priorities' => SystemNotification::PRIORITIES,
                'modules' => ['CORE', 'IAP', 'AEM', 'AFR', 'CMS', 'ARMIS', 'AIS'],
                'users' => $request->user()->hasPermission('notifications.manage')
                    ? User::query()->where('is_active', true)->orderBy('name')->get(['id', 'employee_id', 'name'])
                    : [],
                'roles' => $request->user()->hasPermission('notifications.manage')
                    ? Role::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])
                    : [],
                'offices' => $request->user()->hasPermission('notifications.manage')
                    ? Office::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])
                    : [],
            ],
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $notifications = SystemNotification::query()
            ->where('recipient_id', $request->user()->id)
            ->whereNull('archived_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('actor:id,employee_id,name,initials')
            ->latest()
            ->limit(8)
            ->get();

        return $this->success([
            'notifications' => $notifications->map(fn ($item): array => $this->data($item))->values(),
            'unreadCount' => SystemNotification::query()
                ->where('recipient_id', $request->user()->id)
                ->whereNull('archived_at')
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function read(Request $request, SystemNotification $notification): JsonResponse
    {
        $this->owned($request, $notification);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return $this->success(['notification' => $this->data($notification->load('actor'))]);
    }

    public function unread(Request $request, SystemNotification $notification): JsonResponse
    {
        $this->owned($request, $notification);
        $notification->update(['read_at' => null]);

        return $this->success(['notification' => $this->data($notification->load('actor'))]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $count = SystemNotification::query()
            ->where('recipient_id', $request->user()->id)
            ->whereNull('archived_at')
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return $this->success(['updated' => $count], 'All notifications marked as read.');
    }

    public function archive(Request $request, SystemNotification $notification): JsonResponse
    {
        $this->owned($request, $notification);
        $notification->update([
            'archived_at' => now(),
            'read_at' => $notification->read_at ?? now(),
        ]);
        ActivityRecorder::record(
            $request,
            'notification.archived',
            "Archived notification: {$notification->title}",
            metadata: ['notificationId' => $notification->id],
        );

        return $this->success(message: 'Notification archived without deleting it.');
    }

    public function restore(Request $request, SystemNotification $notification): JsonResponse
    {
        $this->owned($request, $notification);
        if (! $notification->archived_at) {
            throw ValidationException::withMessages([
                'notification' => ['This notification is not archived.'],
            ]);
        }
        $notification->update(['archived_at' => null]);

        return $this->success([
            'notification' => $this->data($notification->load('actor')),
        ], 'Notification restored.');
    }

    public function preferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'inAppEnabled' => ['required', 'boolean'],
            'workflowEnabled' => ['required', 'boolean'],
            'assignmentsEnabled' => ['required', 'boolean'],
            'dueDatesEnabled' => ['required', 'boolean'],
            'systemEnabled' => ['required', 'boolean'],
            'emailEnabled' => ['required', 'boolean'],
            'digestFrequency' => ['required', Rule::in(['IMMEDIATE', 'DAILY', 'WEEKLY'])],
            'quietHoursStart' => ['nullable', 'date_format:H:i'],
            'quietHoursEnd' => ['nullable', 'date_format:H:i'],
        ]);
        $preference = $this->notifications->preference($request->user());
        $old = $this->preferenceData($preference);
        $preference->update([
            'in_app_enabled' => $validated['inAppEnabled'],
            'workflow_enabled' => $validated['workflowEnabled'],
            'assignments_enabled' => $validated['assignmentsEnabled'],
            'due_dates_enabled' => $validated['dueDatesEnabled'],
            'system_enabled' => $validated['systemEnabled'],
            'email_enabled' => $validated['emailEnabled'],
            'digest_frequency' => $validated['digestFrequency'],
            'quiet_hours_start' => $validated['quietHoursStart'],
            'quiet_hours_end' => $validated['quietHoursEnd'],
        ]);
        ActivityRecorder::record(
            $request,
            'notification.preferences_updated',
            "{$request->user()->name} updated notification preferences.",
            $request->user(),
            $old,
            $this->preferenceData($preference),
        );

        return $this->success([
            'preferences' => $this->preferenceData($preference),
        ], 'Notification preferences updated.');
    }

    public function deliver(NotificationDeliveryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $recipientIds = match ($validated['targetType']) {
            'USER' => collect($validated['userIds']),
            'ROLE' => User::query()
                ->where('is_active', true)
                ->where(function ($query) use ($validated): void {
                    $query
                        ->whereHas('roles', fn ($role) => $role->whereKey($validated['roleId']))
                        ->orWhere('role_id', $validated['roleId']);
                })
                ->pluck('id'),
            'OFFICE' => User::query()
                ->where('is_active', true)
                ->where('office_id', $validated['officeId'])
                ->pluck('id'),
            'ALL' => User::query()->where('is_active', true)->pluck('id'),
        };
        $delivered = DB::transaction(fn () => $this->notifications->send($recipientIds, [
            'actorId' => $request->user()->id,
            'type' => 'ADMIN_MESSAGE',
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'moduleCode' => strtoupper($validated['moduleCode']),
            'title' => $validated['title'],
            'message' => $validated['message'],
            'actionUrl' => $validated['actionUrl'] ?? null,
            'actionLabel' => $validated['actionLabel'] ?? null,
            'expiresAt' => $validated['expiresAt'] ?? null,
            'metadata' => ['targetType' => $validated['targetType']],
        ]));
        ActivityRecorder::record(
            $request,
            'notification.delivered',
            "Delivered “{$validated['title']}” to {$delivered->count()} users.",
            metadata: [
                'targetType' => $validated['targetType'],
                'recipientCount' => $delivered->count(),
            ],
        );

        return $this->success(
            ['delivered' => $delivered->count()],
            "Notification delivered to {$delivered->count()} users.",
            201,
        );
    }

    private function owned(Request $request, SystemNotification $notification): void
    {
        if ((int) $notification->recipient_id !== (int) $request->user()->id) {
            abort(403, 'You cannot access another user’s notification.');
        }
    }

    /** @return array<string, mixed> */
    private function data(SystemNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'category' => $notification->category,
            'priority' => $notification->priority,
            'moduleCode' => $notification->module_code,
            'title' => $notification->title,
            'message' => $notification->message,
            'actionUrl' => $notification->action_url,
            'actionLabel' => $notification->action_label,
            'subjectType' => $notification->subject_type,
            'subjectId' => $notification->subject_id,
            'subjectCode' => $notification->subject_code,
            'metadata' => $notification->metadata,
            'actor' => $notification->actor ? [
                'id' => $notification->actor->id,
                'employeeId' => $notification->actor->employee_id,
                'name' => $notification->actor->name,
                'initials' => $notification->actor->initials,
            ] : null,
            'isRead' => $notification->read_at !== null,
            'readAt' => $notification->read_at?->toISOString(),
            'isArchived' => $notification->archived_at !== null,
            'archivedAt' => $notification->archived_at?->toISOString(),
            'expiresAt' => $notification->expires_at?->toISOString(),
            'createdAt' => $notification->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function preferenceData($preference): array
    {
        return [
            'inAppEnabled' => $preference->in_app_enabled,
            'workflowEnabled' => $preference->workflow_enabled,
            'assignmentsEnabled' => $preference->assignments_enabled,
            'dueDatesEnabled' => $preference->due_dates_enabled,
            'systemEnabled' => $preference->system_enabled,
            'emailEnabled' => $preference->email_enabled,
            'digestFrequency' => $preference->digest_frequency,
            'quietHoursStart' => $preference->quiet_hours_start,
            'quietHoursEnd' => $preference->quiet_hours_end,
        ];
    }

    private function success(
        array $data = [],
        ?string $message = null,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data ?: null,
        ], $status);
    }
}
