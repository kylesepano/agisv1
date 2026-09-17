<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTransition;
use Illuminate\Support\Collection;

/**
 * Resolves workflow authority from permissions rather than role-name checks.
 *
 * A role may still be shown as descriptive metadata on a workflow step, but
 * the permission attached to a transition is the source of truth for who may
 * review, approve, return, or otherwise advance a workflow.
 */
class WorkflowAuthorityService
{
    /**
     * @return array<string, mixed>
     */
    public function forTransition(
        WorkflowTransition $transition,
        ?WorkflowInstance $instance = null,
    ): array {
        $permission = $transition->requiredPermission;
        $users = $this->usersForPermission($permission, $instance, $transition->enforce_separation_of_duties);

        return [
            'source' => $permission ? 'PERMISSION' : 'UNCONFIGURED',
            'type' => $this->authorityType($transition),
            'permission' => $permission ? [
                'id' => $permission->id,
                'code' => $permission->code,
                'name' => $permission->name,
                'module' => $permission->module,
                'action' => $permission->action,
                'description' => $permission->description,
            ] : null,
            'users' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'employeeId' => $user->employee_id,
                'name' => $user->name,
                'position' => $user->position,
                'office' => $user->office ? [
                    'id' => $user->office->id,
                    'code' => $user->office->code,
                    'name' => $user->office->name,
                ] : null,
            ])->values()->all(),
            'userCount' => $users->count(),
            'message' => $permission
                ? ($users->isEmpty()
                    ? 'No active user currently has this permission within the workflow scope.'
                    : 'Eligible users are determined from this permission and workflow scope.')
                : 'No required permission is configured. This action cannot be performed.',
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function usersForPermission(
        ?Permission $permission,
        ?WorkflowInstance $instance,
        bool $excludeInitiator,
    ): Collection {
        if (! $permission) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($permission): void {
                $query
                    ->whereHas('roles.permissions', fn ($permissions) => $permissions->whereKey($permission->id))
                    ->orWhereHas('role.permissions', fn ($permissions) => $permissions->whereKey($permission->id));
            })
            ->with([
                'role.permissions',
                'roles.permissions',
                'office:id,code,name',
            ])
            ->get()
            ->filter(function (User $user) use ($permission, $instance, $excludeInitiator): bool {
                if (! $user->hasPermission($permission->code) || $user->isLocked()) {
                    return false;
                }

                if ($excludeInitiator && $instance?->started_by === $user->id) {
                    return false;
                }

                if (! $instance?->office_id) {
                    return true;
                }

                return $user->id === $instance->started_by
                    || (int) $user->office_id === (int) $instance->office_id
                    || $user->hasGlobalOfficeAccess();
            })
            ->sortBy(fn (User $user): string => mb_strtolower((string) $user->name))
            ->values();
    }

    private function authorityType(WorkflowTransition $transition): string
    {
        $text = mb_strtolower($transition->code.' '.$transition->name.' '.($transition->requiredPermission?->action ?? ''));

        if (str_contains($text, 'approve')
            || str_contains($text, 'publish')
            || str_contains($text, 'accept')) {
            return 'APPROVER';
        }

        if (str_contains($text, 'review')
            || str_contains($text, 'return')
            || str_contains($text, 'recommend')
            || str_contains($text, 'validate')) {
            return 'REVIEWER';
        }

        if (str_contains($text, 'submit') || str_contains($text, 'resubmit')) {
            return 'SUBMITTER';
        }

        return 'AUTHORIZED ACTOR';
    }
}
