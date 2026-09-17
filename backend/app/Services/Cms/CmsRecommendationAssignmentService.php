<?php

namespace App\Services\Cms;

use App\Models\AuditLog;
use App\Models\CmsRecommendationAssignment;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\ActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Controls assignment, replacement, and ending of Compliance Monitors. */
class CmsRecommendationAssignmentService
{
    public function __construct(
        private readonly CmsRecommendationScopeService $scope,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{assignment: CmsRecommendationAssignment, replaced: CmsRecommendationAssignment|null}
     */
    public function assign(
        Request $request,
        int $caseId,
        array $attributes,
    ): array {
        $actor = $request->user();
        $this->scope->authorizeAssignmentAuthority($actor);
        $result = DB::transaction(function () use ($request, $actor, $caseId, $attributes): array {
            $case = $this->scope->resolveVisibleCase(
                $actor,
                $caseId,
                'cms.recommendation.view',
                true,
            );
            $this->assertLockVersion($case, (int) $attributes['lockVersion']);

            $target = User::withTrashed()
                ->with(['role.permissions', 'roles.permissions'])
                ->lockForUpdate()
                ->find((int) $attributes['userId']);
            $this->validateMonitor($case, $target);

            $current = CmsRecommendationAssignment::query()
                ->where('cms_recommendation_case_id', $case->id)
                ->current()
                ->lockForUpdate()
                ->first();
            if ($current && (int) $current->user_id === (int) $target->id) {
                throw ValidationException::withMessages([
                    'userId' => ['This user is already the current Compliance Monitor.'],
                ]);
            }
            if ($current && blank($attributes['reason'] ?? null)) {
                throw ValidationException::withMessages([
                    'reason' => ['A reason is required when replacing the current monitor.'],
                ]);
            }

            $now = now();
            if ($current) {
                $current->forceFill([
                    'ended_by' => $actor->id,
                    'ended_at' => $now,
                    'effective_until' => $now,
                    'end_reason' => $attributes['reason'],
                    'is_current' => false,
                ])->save();
            }

            $assignment = CmsRecommendationAssignment::query()->create([
                'cms_recommendation_case_id' => $case->id,
                'user_id' => $target->id,
                'assignment_role_code' => CmsRecommendationAssignment::ROLE_COMPLIANCE_MONITOR,
                'assignment_reason' => $attributes['reason'] ?? null,
                'assigned_by' => $actor->id,
                'assigned_at' => $now,
                'effective_from' => $attributes['effectiveFrom'] ?? $now,
                'effective_until' => $attributes['effectiveUntil'] ?? null,
                'is_current' => true,
            ]);

            $case->forceFill(['lock_version' => $case->lock_version + 1])->save();
            $eventCode = $current
                ? CmsRecommendationEvent::EVENT_COMPLIANCE_MONITOR_REPLACED
                : CmsRecommendationEvent::EVENT_COMPLIANCE_MONITOR_ASSIGNED;
            $this->event($request, $case, $eventCode, $assignment, $current, $attributes['reason'] ?? null);
            $this->log(
                $request,
                $case,
                $current ? 'cms.recommendation.monitor_replaced' : 'cms.recommendation.monitor_assigned',
                $current,
                $assignment,
                $attributes['reason'] ?? null,
            );

            return [
                'assignment' => $assignment,
                'replaced' => $current,
                'caseLockVersion' => $case->lock_version,
            ];
        });

        DB::afterCommit(function () use ($request, $caseId, $result): void {
            $this->notifyAssigned(
                $request->user()->id,
                $caseId,
                $result['assignment']->id,
                $result['assignment']->user_id,
            );
            if ($result['replaced']) {
                $this->notifyEnded(
                    $request->user()->id,
                    $caseId,
                    $result['replaced']->id,
                    $result['replaced']->user_id,
                    true,
                );
            }
        });

        return $result;
    }

    /**
     * @return array{assignment: CmsRecommendationAssignment, caseLockVersion: int}
     */
    public function end(
        Request $request,
        int $caseId,
        int $assignmentId,
        int $lockVersion,
        string $reason,
    ): array {
        $actor = $request->user();
        $this->scope->authorizeAssignmentAuthority($actor);
        $result = DB::transaction(function () use (
            $request,
            $actor,
            $caseId,
            $assignmentId,
            $lockVersion,
            $reason,
        ): array {
            $case = $this->scope->resolveVisibleCase(
                $actor,
                $caseId,
                'cms.recommendation.view',
                true,
            );
            $this->assertLockVersion($case, $lockVersion);
            $assignment = CmsRecommendationAssignment::query()
                ->whereKey($assignmentId)
                ->where('cms_recommendation_case_id', $case->id)
                ->current()
                ->lockForUpdate()
                ->first();
            if (! $assignment) {
                throw ValidationException::withMessages([
                    'assignment' => ['The current assignment is unavailable or already ended.'],
                ]);
            }

            $now = now();
            $assignment->forceFill([
                'ended_by' => $actor->id,
                'ended_at' => $now,
                'effective_until' => $now,
                'end_reason' => $reason,
                'is_current' => false,
            ])->save();
            $case->forceFill(['lock_version' => $case->lock_version + 1])->save();
            $this->event(
                $request,
                $case,
                CmsRecommendationEvent::EVENT_COMPLIANCE_MONITOR_ASSIGNMENT_ENDED,
                null,
                $assignment,
                $reason,
            );
            $this->log(
                $request,
                $case,
                'cms.recommendation.monitor_assignment_ended',
                $assignment,
                null,
                $reason,
            );

            return [
                'assignment' => $assignment,
                'caseLockVersion' => $case->lock_version,
            ];
        });

        DB::afterCommit(fn () => $this->notifyEnded(
            $request->user()->id,
            $caseId,
            $result['assignment']->id,
            $result['assignment']->user_id,
            false,
        ));

        return $result;
    }

    /** @return Collection<int, User> */
    public function eligibleMonitors(User $actor, CmsRecommendationCase $case): Collection
    {
        $this->scope->authorizeAssignmentAuthority($actor);
        $this->scope->resolveVisibleCase($actor, $case->id);
        $case->loadMissing('recommendation');

        return User::query()
            ->where('is_active', true)
            ->where('is_manually_locked', false)
            ->where(function ($query): void {
                $query->whereNull('locked_until')->orWhere('locked_until', '<=', now());
            })
            ->with(['role.permissions', 'roles.permissions'])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $this->isValidMonitor($case, $user))
            ->values();
    }

    private function validateMonitor(
        CmsRecommendationCase $case,
        ?User $target,
    ): void {
        if (! $target || ! $this->isValidMonitor($case, $target)) {
            throw ValidationException::withMessages([
                'userId' => [
                    'Select an active, unlocked, independent user with CMS monitoring authority.',
                ],
            ]);
        }
    }

    private function isValidMonitor(CmsRecommendationCase $case, User $target): bool
    {
        if (! $this->scope->isUsableAccount($target)
            || ! $target->hasPermission('cms.recommendation.monitor')
            || ! $target->hasPermission('cms.recommendation.monitor')) {
            return false;
        }

        $case->loadMissing('recommendation');
        if (! $this->scope->canViewClassification(
            $target,
            $case->recommendation->confidentiality_code_snapshot,
        )) {
            return false;
        }

        return ! ($target->hasPermission('access.auditee_scope')
            && $target->office_id
            && in_array((int) $target->office_id, [
                (int) $case->lead_responsible_office_id,
                (int) $case->recommendation->responsible_office_id,
                (int) $case->recommendation->lead_responsible_office_id,
            ], true));
    }

    private function assertLockVersion(
        CmsRecommendationCase $case,
        int $lockVersion,
    ): void {
        if ((int) $case->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['The CMS recommendation changed. Refresh before retrying.'],
            ]);
        }
    }

    private function event(
        Request $request,
        CmsRecommendationCase $case,
        string $eventCode,
        ?CmsRecommendationAssignment $assignment,
        ?CmsRecommendationAssignment $previous,
        ?string $reason,
    ): void {
        $identity = $assignment?->id ?? $previous?->id;
        CmsRecommendationEvent::query()->create([
            'cms_recommendation_case_id' => $case->id,
            'cms_recommendation_id' => $case->cms_recommendation_id,
            'idempotency_key' => "cms-assignment:{$identity}:{$eventCode}",
            'event_code' => $eventCode,
            'source_module' => 'CMS',
            'actor_id' => $request->user()->id,
            'previous_status' => $case->status_code,
            'new_status' => $case->status_code,
            'event_metadata' => [
                'assignmentId' => $assignment?->id,
                'assignedUserId' => $assignment?->user_id,
                'previousAssignmentId' => $previous?->id,
                'previousUserId' => $previous?->user_id,
                'reason' => $reason,
                'caseLockVersion' => $case->lock_version,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }

    private function log(
        Request $request,
        CmsRecommendationCase $case,
        string $action,
        ?CmsRecommendationAssignment $oldAssignment,
        ?CmsRecommendationAssignment $newAssignment,
        ?string $reason,
    ): void {
        $old = $oldAssignment ? [
            'assignmentId' => $oldAssignment->id,
            'userId' => $oldAssignment->user_id,
            'isCurrent' => true,
        ] : null;
        $new = $newAssignment ? [
            'assignmentId' => $newAssignment->id,
            'userId' => $newAssignment->user_id,
            'isCurrent' => true,
        ] : [
            'assignmentId' => $oldAssignment?->id,
            'userId' => $oldAssignment?->user_id,
            'isCurrent' => false,
        ];
        $metadata = [
            'module' => 'CMS',
            'caseId' => $case->id,
            'cmsRecommendationId' => $case->cms_recommendation_id,
            'reason' => $reason,
        ];
        ActivityRecorder::record(
            $request,
            $action,
            'Updated the Compliance Monitor assignment for a CMS recommendation.',
            oldValues: $old,
            newValues: $new,
            metadata: $metadata,
        );
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => CmsRecommendationCase::class,
            'auditable_id' => $case->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ]);
    }

    private function notifyAssigned(
        int $actorId,
        int $caseId,
        int $assignmentId,
        int $recipientId,
    ): void {
        $this->notifications->send([$recipientId], [
            'actorId' => $actorId,
            'type' => 'CMS_COMPLIANCE_MONITOR_ASSIGNED',
            'category' => 'ASSIGNMENT',
            'priority' => 'NORMAL',
            'moduleCode' => 'CMS',
            'title' => 'CMS monitoring assignment',
            'message' => 'You were assigned as Compliance Monitor for an authorized CMS case.',
            'actionUrl' => "/compliance-management/recommendations/{$caseId}",
            'actionLabel' => 'Open recommendation',
            'subjectType' => CmsRecommendationCase::class,
            'subjectId' => $caseId,
            'subjectCode' => sprintf('CMS-REC-%06d', $caseId),
            'dedupeKey' => "cms-monitor-assigned:{$assignmentId}",
            'metadata' => ['caseId' => $caseId, 'assignmentId' => $assignmentId],
        ]);
    }

    private function notifyEnded(
        int $actorId,
        int $caseId,
        int $assignmentId,
        int $recipientId,
        bool $replaced,
    ): void {
        $this->notifications->send([$recipientId], [
            'actorId' => $actorId,
            'type' => $replaced
                ? 'CMS_COMPLIANCE_MONITOR_REPLACED'
                : 'CMS_COMPLIANCE_MONITOR_ASSIGNMENT_ENDED',
            'category' => 'ASSIGNMENT',
            'priority' => 'NORMAL',
            'moduleCode' => 'CMS',
            'title' => 'CMS monitoring assignment updated',
            'message' => 'Your Compliance Monitor assignment was ended.',
            'subjectType' => CmsRecommendationCase::class,
            'subjectId' => $caseId,
            'subjectCode' => sprintf('CMS-REC-%06d', $caseId),
            'dedupeKey' => "cms-monitor-ended:{$assignmentId}",
            'metadata' => ['caseId' => $caseId, 'assignmentId' => $assignmentId],
        ]);
    }
}
