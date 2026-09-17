<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Models\MasterList;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Services\RuntimeConfiguration;
use App\Support\ActivityRecorder;
use App\Support\PersonName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manages employee identities, account controls, role scopes, and password resets.
 */
class UserController extends Controller
{
    public function __construct(private readonly RuntimeConfiguration $configuration) {}

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->boolean('include_archived'), fn ($query) => $query->withTrashed())
            ->when(
                ! $request->user()->hasGlobalOfficeAccess(),
                fn ($query) => $query->where('office_id', $request->user()->office_id),
            )
            ->with([
                'office:id,code,name',
                'role:id,code,name,is_active,office_access_scope,engagement_access_scope,deleted_at',
                'role.permissions:id,code,name,module,action',
                'roles:id,code,name,is_active,office_access_scope,engagement_access_scope,deleted_at',
                'roles.permissions:id,code,name,module,action',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => $this->data($user));

        return response()->json(['success' => true, 'data' => ['users' => $users]]);
    }

    public function show(User $user): JsonResponse
    {
        $this->ensureUserScope(request(), $user);

        return response()->json([
            'success' => true,
            'data' => ['user' => $this->data($user, true)],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateUser($request);
        [$roles, $primaryRoleId] = $this->resolveRoleSelection($validated);
        $this->ensureAssignable($request, $roles);
        $this->ensureOfficeSelection($request, (int) $validated['officeId']);

        $user = DB::transaction(function () use ($request, $roles, $primaryRoleId, $validated): User {
            $user = User::query()->create([
                ...$this->attributes($validated, $primaryRoleId),
                'password' => Hash::make($validated['password']),
                'is_active' => $validated['isActive'] ?? true,
                'failed_login_attempts' => 0,
            ]);
            $user->syncRoleAssignments($roles->pluck('id')->all(), $primaryRoleId, $request->user()->id);
            $this->syncOfficeHead($user);

            return $user;
        }, 3);
        $this->syncPosition($user->position);

        ActivityRecorder::record(
            $request,
            'user.created',
            "{$request->user()->name} created the user account {$user->name}.",
            $user,
            null,
            $this->activityValues($user),
        );

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => ['user' => $this->data($user)],
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureManageable($request, $user);
        $validated = $this->validateUser($request, $user);
        $this->ensureOfficeSelection($request, (int) $validated['officeId']);
        if (
            array_key_exists('isActive', $validated)
            && (bool) $validated['isActive'] !== (bool) $user->is_active
        ) {
            throw ValidationException::withMessages([
                'isActive' => ['Use the dedicated activate or disable account action.'],
            ]);
        }
        [$roles, $primaryRoleId] = $this->resolveRoleSelection($validated);
        $this->ensureAssignable($request, $roles);
        $this->preventSelfPrivilegeLoss($request, $user, $roles, $validated);
        $oldValues = $this->activityValues($user);

        DB::transaction(function () use ($request, $roles, $primaryRoleId, $validated, $user): void {
            $user->update([
                ...$this->attributes($validated, $primaryRoleId),
            ]);
            $user->syncRoleAssignments($roles->pluck('id')->all(), $primaryRoleId, $request->user()->id);
            $this->syncOfficeHead($user);
        }, 3);
        $this->syncPosition($user->position);

        ActivityRecorder::record(
            $request,
            'user.updated',
            "{$request->user()->name} updated {$user->name}.",
            $user,
            $oldValues,
            $this->activityValues($user->fresh()),
        );

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => ['user' => $this->data($user)],
        ]);
    }

    public function archive(Request $request, User $user): JsonResponse
    {
        $this->ensureManageable($request, $user);

        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot archive your own account.'],
            ]);
        }

        $oldValues = $this->accountState($user);
        $user->forceFill([
            'is_active' => false,
            'is_office_head' => false,
            'is_manually_locked' => false,
            'manually_locked_at' => null,
            'manually_locked_by' => null,
        ])->save();
        $user->tokens()->delete();
        $user->delete();

        ActivityRecorder::record(
            $request,
            'user.archived',
            "{$request->user()->name} archived {$user->name}.",
            $user,
            $oldValues,
            [...$this->accountState($user), 'isArchived' => true],
        );

        return response()->json(['success' => true, 'message' => 'User archived successfully.']);
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        $this->ensureManageable($request, $user);
        $oldValues = $this->accountState($user);
        $user->forceFill(['is_active' => true])->save();

        ActivityRecorder::record(
            $request,
            'user.activated',
            "{$request->user()->name} activated {$user->name}.",
            $user,
            $oldValues,
            $this->accountState($user),
        );

        return response()->json([
            'success' => true,
            'message' => 'User account activated successfully.',
            'data' => ['user' => $this->data($user)],
        ]);
    }

    public function disable(Request $request, User $user): JsonResponse
    {
        $this->ensureManageable($request, $user);
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot disable your own account.'],
            ]);
        }

        $oldValues = $this->accountState($user);
        $user->forceFill([
            'is_active' => false,
            'is_office_head' => false,
        ])->save();
        $user->tokens()->delete();

        ActivityRecorder::record(
            $request,
            'user.disabled',
            "{$request->user()->name} disabled {$user->name}.",
            $user,
            $oldValues,
            $this->accountState($user),
        );

        return response()->json([
            'success' => true,
            'message' => 'User account disabled successfully.',
            'data' => ['user' => $this->data($user)],
        ]);
    }

    public function lock(Request $request, User $user): JsonResponse
    {
        $this->ensureManageable($request, $user);
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user' => ['Activate this account before applying a manual lock.'],
            ]);
        }
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot lock your own account.'],
            ]);
        }

        $oldValues = $this->accountState($user);
        $user->forceFill([
            'is_manually_locked' => true,
            'manually_locked_at' => now(),
            'manually_locked_by' => $request->user()->id,
            'lock_version' => $user->lock_version + 1,
        ])->save();
        $user->tokens()->delete();

        ActivityRecorder::record(
            $request,
            'user.locked',
            "{$request->user()->name} manually locked {$user->name}.",
            $user,
            $oldValues,
            $this->accountState($user),
        );

        return response()->json([
            'success' => true,
            'message' => 'User account locked successfully.',
            'data' => ['user' => $this->data($user)],
        ]);
    }

    public function unlock(Request $request, User $user): JsonResponse
    {
        $this->ensureManageable($request, $user);
        $oldValues = $this->accountState($user);
        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'is_manually_locked' => false,
            'manually_locked_at' => null,
            'manually_locked_by' => null,
            'lock_version' => $user->lock_version + 1,
        ])->save();

        ActivityRecorder::record(
            $request,
            'user.unlocked',
            "{$request->user()->name} unlocked {$user->name}.",
            $user,
            $oldValues,
            $this->accountState($user),
        );

        return response()->json([
            'success' => true,
            'message' => 'User account unlocked successfully.',
            'data' => ['user' => $this->data($user)],
        ]);
    }

    public function restore(Request $request, int $user): JsonResponse
    {
        $record = User::onlyTrashed()->with('role')->findOrFail($user);
        $this->ensureManageable($request, $record);

        if ($record->office_id && ! Office::query()->whereKey($record->office_id)->exists()) {
            throw ValidationException::withMessages([
                'office' => ['Restore the user’s assigned office before restoring this account.'],
            ]);
        }

        $record->restore();
        $record->forceFill([
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'is_manually_locked' => false,
            'manually_locked_at' => null,
            'manually_locked_by' => null,
        ])->save();

        ActivityRecorder::record(
            $request,
            'user.restored',
            "{$request->user()->name} restored {$record->name}.",
            $record,
            ['isActive' => false, 'isArchived' => true],
            ['isActive' => true, 'isArchived' => false],
        );

        return response()->json([
            'success' => true,
            'message' => 'User restored successfully.',
            'data' => ['user' => $this->data($record)],
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        if (! $request->user()->hasPermission('access.manage_system_roles')) {
            abort(403, 'Only a Platform Administrator can reset user passwords.');
        }

        $validated = $request->validate([
              'password' => ['required', 'string', 'min:'.$this->configuration->passwordMinLength(), 'max:255', 'confirmed'],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'is_manually_locked' => false,
            'manually_locked_at' => null,
            'manually_locked_by' => null,
            'remember_token' => null,
            'lock_version' => $user->lock_version + 1,
        ])->save();
        $user->tokens()->delete();

        ActivityRecorder::record(
            $request,
            'user.password_reset',
            "{$request->user()->name} reset the password of {$user->name}.",
            $user,
            null,
            ['passwordReset' => true],
        );

        return response()->json([
            'success' => true,
            'message' => 'User password reset successfully.',
        ]);
    }

    /** @return array<string, mixed> */
    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'employeeId' => ['required', 'string', 'max:40', Rule::unique('users', 'employee_id')->ignore($user?->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'firstName' => ['required', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'extension' => ['nullable', 'string', 'max:20'],
            'position' => ['nullable', 'string', 'max:255'],
            'employmentType' => ['nullable', 'string', 'max:80'],
            'contactNumber' => ['nullable', 'string', 'max:100'],
            'birthDate' => ['nullable', 'date', 'before:today'],
            'isOfficeHead' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
            'officeId' => ['required', 'integer', Rule::exists(Office::class, 'id')->whereNull('deleted_at')],
            'roleId' => [
                'nullable',
                'required_without:roleIds',
                'integer',
                Rule::exists(Role::class, 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'roleIds' => ['nullable', 'required_without:roleId', 'array', 'min:1'],
            'roleIds.*' => [
                'integer',
                'distinct',
                Rule::exists(Role::class, 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'primaryRoleId' => [
                'nullable',
                'required_with:roleIds',
                'integer',
                Rule::exists(Role::class, 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
              'password' => [$user ? 'prohibited' : 'required', 'string', 'min:'.$this->configuration->passwordMinLength(), 'max:255'],
        ]);
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated, int $primaryRoleId): array
    {
        $name = PersonName::fromParts(
            $validated['firstName'],
            $validated['middleName'] ?? null,
            $validated['lastName'],
            $validated['extension'] ?? null,
        );

        return [
            'username' => 'employee:'.strtolower(trim($validated['employeeId'])),
            'employee_id' => strtoupper(trim($validated['employeeId'])),
            'email' => $validated['email'] ?? null,
            ...$name,
            'position' => $validated['position'] ?? null,
            'employment_type' => $validated['employmentType'] ?? null,
            'contact_number' => $validated['contactNumber'] ?? null,
            'birth_date' => $validated['birthDate'] ?? null,
            'is_office_head' => $validated['isOfficeHead'] ?? false,
            'office_id' => $validated['officeId'],
            'role_id' => $primaryRoleId,
        ];
    }

    /** @return array{0: Collection<int, Role>, 1: int} */
    private function resolveRoleSelection(array $validated): array
    {
        $roleIds = collect($validated['roleIds'] ?? [$validated['roleId']])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $primaryRoleId = (int) ($validated['primaryRoleId'] ?? $validated['roleId'] ?? $roleIds->first());

        if (! $roleIds->contains($primaryRoleId)) {
            throw ValidationException::withMessages([
                'primaryRoleId' => ['The primary role must also be included in the assigned roles.'],
            ]);
        }

        $roles = Role::query()
            ->with('permissions')
            ->whereIn('id', $roleIds)
            ->where('is_active', true)
            ->get();

        if ($roles->count() !== $roleIds->count()) {
            throw ValidationException::withMessages([
                'roleIds' => ['One or more selected roles are unavailable.'],
            ]);
        }

        return [$roles, $primaryRoleId];
    }

    /** @param Collection<int, Role> $roles */
    private function ensureAssignable(Request $request, Collection $roles): void
    {
        if ($roles->contains(fn (Role $role): bool => $role->permissions->contains('code', 'access.manage_system_roles'))
            && ! $request->user()->hasPermission('access.manage_system_roles')) {
            abort(403, 'Only a Platform Administrator can assign that role.');
        }
    }

    /** @param Collection<int, Role> $roles
     * @param  array<string, mixed>  $validated
     */
    private function preventSelfPrivilegeLoss(
        Request $request,
        User $user,
        Collection $roles,
        array $validated,
    ): void {
        if (! $request->user()->is($user)) {
            return;
        }

        if (($validated['isActive'] ?? true) === false) {
            throw ValidationException::withMessages([
                'isActive' => ['You cannot disable your own account.'],
            ]);
        }

        if ($user->hasPermission('access.manage_system_roles')
            && ! $roles->contains(fn (Role $role): bool => $role->permissions->contains('code', 'access.manage_system_roles'))) {
            throw ValidationException::withMessages([
                'roleIds' => ['You cannot remove your own Platform Administrator role.'],
            ]);
        }
    }

    private function syncOfficeHead(User $user): void
    {
        if (! $user->is_office_head || ! $user->office_id) {
            return;
        }

        User::query()
            ->where('office_id', $user->office_id)
            ->whereKeyNot($user->id)
            ->update(['is_office_head' => false]);
    }

    private function syncPosition(?string $position): void
    {
        $position = trim((string) $position);

        if ($position === '') {
            return;
        }

        $list = MasterList::query()->where('code', 'POSITION')->first();

        if (! $list) {
            return;
        }

        $code = Str::of($position)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->limit(60, '')
            ->toString();

        $item = $list->items()->withTrashed()->firstOrNew(['code' => $code]);
        $item->fill([
            'label' => $position,
            'description' => $item->exists
                ? $item->description
                : 'Custom position title added from the User Registry.',
            'display_order' => $item->exists
                ? $item->display_order
                : ($list->items()->max('display_order') ?? 0) + 1,
            'is_active' => true,
        ])->save();

        if ($item->trashed()) {
            $item->restore();
        }
    }

    private function ensureManageable(Request $request, User $user): void
    {
        $this->ensureUserScope($request, $user);
        $user->loadMissing(['role.permissions', 'roles.permissions']);
        if ($user->hasPermission('access.manage_system_roles')
            && ! $request->user()->hasPermission('access.manage_system_roles')) {
            abort(403, 'Only a Platform Administrator can manage that account.');
        }
    }

    private function ensureOfficeSelection(Request $request, int $officeId): void
    {
        abort_if(
            ! $request->user()->hasGlobalOfficeAccess()
            && (int) $request->user()->office_id !== $officeId,
            403,
            'Your role is limited to users in your assigned office.',
        );
    }

    private function ensureUserScope(Request $request, User $user): void
    {
        abort_if(
            ! $request->user()->hasGlobalOfficeAccess()
            && (int) $request->user()->office_id !== (int) $user->office_id,
            403,
            'Your role is limited to users in your assigned office.',
        );
    }

    /** @return array<string, mixed> */
    private function activityValues(User $user): array
    {
        $user->loadMissing([
            'office:id,name',
            'role:id,code,name,is_active,office_access_scope,engagement_access_scope,deleted_at',
            'roles:id,code,name,is_active,office_access_scope,engagement_access_scope,deleted_at',
        ]);

        return [
            'employeeId' => $user->employee_id,
            'firstName' => $user->first_name,
            'middleName' => $user->middle_name,
            'lastName' => $user->last_name,
            'extension' => $user->name_extension,
            'name' => $user->name,
            'email' => $user->email,
            'position' => $user->position,
            'employmentType' => $user->employment_type,
            'contactNumber' => $user->contact_number,
            'birthDate' => $user->birth_date?->toDateString(),
            'office' => $user->office?->name,
            'primaryRole' => $user->role?->name,
            'roles' => $user->effectiveRoles()->pluck('name')->sort()->values()->all(),
            'isOfficeHead' => $user->is_office_head,
            'isActive' => $user->is_active,
            ...$this->accountState($user),
        ];
    }

    /** @return array<string, mixed> */
    private function data(User $user, bool $includeDetails = false): array
    {
        $relations = [
            'office:id,code,name',
            'role:id,code,name,is_active,office_access_scope,engagement_access_scope,deleted_at',
            'role.permissions:id,code',
            'roles:id,code,name,description,is_active,office_access_scope,engagement_access_scope,deleted_at',
            'roles.permissions:id,code,name,module,action',
            'manualLocker:id,name',
        ];
        if ($includeDetails) {
            $relations[] = 'subjectActivityLogs.user:id,name';
            $relations[] = 'iapTeamAssignments.engagement.plan:id,plan_code,title,status';
            $relations[] = 'iapTeamAssignments.engagement:id,plan_id,engagement_code,title,schedule_status,is_active';
            $relations[] = 'iapTeamAssignments.teamRole:id,code,label';
        }
        $user->loadMissing($relations);
        $roles = $user->effectiveRoles();
        $permissions = $roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->unique('id')
            ->sortBy('code')
            ->values();

        $data = [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'email' => $user->email,
            'firstName' => $user->first_name,
            'middleName' => $user->middle_name,
            'lastName' => $user->last_name,
            'extension' => $user->name_extension,
            'name' => $user->name,
            'initials' => $user->initials,
            'position' => $user->position,
            'employmentType' => $user->employment_type,
            'contactNumber' => $user->contact_number,
            'birthDate' => $user->birth_date?->toDateString(),
            'isOfficeHead' => $user->is_office_head,
            'isActive' => $user->is_active,
            'isArchived' => $user->trashed(),
            'officeId' => $user->office_id,
            'officeCode' => $user->office?->code,
            'office' => $user->office?->name,
            'roleId' => $user->role_id,
            'roleCode' => $user->role?->code,
            'role' => $user->role?->name,
            'primaryRoleId' => $user->role_id,
            'roleIds' => $roles->pluck('id')->values()->all(),
            'roles' => $roles->map(fn (Role $role): array => [
                'id' => $role->id,
                'code' => $role->code,
                'name' => $role->name,
                'description' => $role->description,
                'isPrimary' => (int) $role->id === (int) $user->role_id,
                'officeAccessScope' => $role->office_access_scope,
                'engagementAccessScope' => $role->engagement_access_scope,
            ])->values(),
            'accessScopes' => [
                'office' => $user->hasGlobalOfficeAccess() ? 'ALL' : 'OWN_OFFICE',
                'engagement' => $user->hasGlobalEngagementAccess() ? 'ALL' : 'ASSIGNED',
            ],
            'permissions' => $permissions->pluck('code')->values()->all(),
            'permissionsCount' => $permissions->count(),
            'lastLoginAt' => $user->last_login_at?->toIso8601String(),
            'failedLoginAttempts' => $user->failed_login_attempts,
            'lockedUntil' => $user->locked_until?->toIso8601String(),
            'isManuallyLocked' => $user->is_manually_locked,
            'manuallyLockedAt' => $user->manually_locked_at?->toIso8601String(),
            'manuallyLockedBy' => $user->manualLocker?->name,
            'isLocked' => $user->isLocked(),
        ];

        if (! $includeDetails) {
            return $data;
        }

        return [
            ...$data,
            'permissionDetails' => $permissions->map(fn ($permission): array => [
                'id' => $permission->id,
                'code' => $permission->code,
                'name' => $permission->name,
                'module' => $permission->module,
                'action' => $permission->action,
            ])->values(),
            'activeAssignments' => $user->iapTeamAssignments
                ->filter(fn ($assignment): bool => $assignment->engagement?->is_active
                    && $assignment->engagement?->schedule_status !== 'CANCELLED')
                ->map(fn ($assignment): array => [
                    'id' => $assignment->id,
                    'engagementCode' => $assignment->engagement?->engagement_code,
                    'engagementTitle' => $assignment->engagement?->title,
                    'planCode' => $assignment->engagement?->plan?->plan_code,
                    'planTitle' => $assignment->engagement?->plan?->title,
                    'planStatus' => $assignment->engagement?->plan?->status,
                    'scheduleStatus' => $assignment->engagement?->schedule_status,
                    'teamRole' => $assignment->teamRole?->label,
                    'plannedPersonDays' => $assignment->planned_person_days,
                ])
                ->values(),
            'activityHistory' => $user->subjectActivityLogs
                ->take(50)
                ->map(fn ($log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'actor' => $log->user?->name ?? 'System',
                    'oldValues' => $log->old_values,
                    'newValues' => $log->new_values,
                    'createdAt' => $log->created_at?->toIso8601String(),
                ])
                ->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function accountState(User $user): array
    {
        return [
            'isActive' => $user->is_active,
            'isLocked' => $user->isLocked(),
            'isManuallyLocked' => $user->is_manually_locked,
            'lockedUntil' => $user->locked_until?->toIso8601String(),
            'failedLoginAttempts' => $user->failed_login_attempts,
        ];
    }
}
