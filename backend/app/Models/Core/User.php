<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

/**
 * Represents an employee identity, account state, office assignment, and active roles.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'office_id',
        'employee_id',
        'username',
        'email',
        'first_name',
        'middle_name',
        'last_name',
        'name_extension',
        'name',
        'initials',
        'position',
        'employment_type',
        'contact_number',
        'birth_date',
        'is_office_head',
        'password',
        'is_active',
        'failed_login_attempts',
        'locked_until',
        'is_manually_locked',
        'manually_locked_at',
        'manually_locked_by',
        'last_login_at',
        'lock_version',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'is_manually_locked' => 'boolean',
            'manually_locked_at' => 'datetime',
            'last_login_at' => 'datetime',
            'birth_date' => 'date',
            'is_office_head' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role_assignments')
            ->withPivot(['is_primary', 'assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function manualLocker(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manually_locked_by')->withTrashed();
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function systemNotifications(): HasMany
    {
        return $this->hasMany(SystemNotification::class, 'recipient_id')->latest();
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function subjectActivityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'subject_user_id')->latest('created_at');
    }

    public function iapTeamAssignments(): HasMany
    {
        return $this->hasMany(IapEngagementTeamMember::class);
    }

    public function iapCapacities(): HasMany
    {
        return $this->hasMany(IapAuditorCapacity::class);
    }

    public function iapUnavailability(): HasMany
    {
        return $this->hasMany(IapAuditorUnavailability::class);
    }

    public function iapSkills(): HasMany
    {
        return $this->hasMany(IapAuditorSkill::class);
    }

    public function armisResourceProfiles(): HasMany
    {
        return $this->hasMany(ArmisResourceProfile::class);
    }

    public function cmsRecommendationAssignments(): HasMany
    {
        return $this->hasMany(CmsRecommendationAssignment::class);
    }

    public function cmsValidationAssignments(): HasMany
    {
        return $this->hasMany(CmsValidationAssignment::class);
    }

    public function changes(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;

        return $this->effectiveRoles()->contains(
            fn (Role $role): bool => in_array($role->code, $roles, true)
                || in_array($role->name, $roles, true),
        );
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active || $this->trashed()) {
            return false;
        }

        return $this->effectiveRoles()
            ->contains(fn (Role $role): bool => $role->permissions->contains('code', $permission));
    }

    public function hasGlobalOfficeAccess(): bool
    {
        return $this->effectiveRoles()
            ->contains(fn (Role $role): bool => $role->office_access_scope === 'ALL');
    }

    public function hasGlobalEngagementAccess(): bool
    {
        return $this->effectiveRoles()
            ->contains(fn (Role $role): bool => $role->engagement_access_scope === 'ALL');
    }

    public function isReadOnlyOnly(): bool
    {
        return $this->hasPermission('access.read_only_scope');
    }

    /**
     * @param  list<string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return collect($permissions)->contains(fn (string $permission) => $this->hasPermission($permission));
    }

    /**
     * @param  list<string>  $permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        return collect($permissions)->every(fn (string $permission) => $this->hasPermission($permission));
    }

    public function isLocked(): bool
    {
        return $this->is_manually_locked
            || ($this->locked_until !== null && $this->locked_until->isFuture());
    }

    /** @return Collection<int, Role> */
    public function effectiveRoles()
    {
        $assigned = $this->relationLoaded('roles')
            ? $this->roles
            : $this->roles()->with('permissions')->get();

        if ($this->role && ! $assigned->contains('id', $this->role->id)) {
            $assigned = $assigned->push($this->role);
        }

        return $assigned
            ->filter(fn (Role $role): bool => $role->is_active && ! $role->trashed())
            ->unique('id')
            ->values();
    }

    /** @param list<int> $roleIds */
    public function syncRoleAssignments(array $roleIds, int $primaryRoleId, ?int $assignedBy = null): void
    {
        $now = now();
        $assignments = collect($roleIds)
            ->mapWithKeys(fn (int $roleId): array => [
                $roleId => [
                    'is_primary' => $roleId === $primaryRoleId,
                    'assigned_by' => $assignedBy,
                    'assigned_at' => $now,
                ],
            ])
            ->all();

        $this->roles()->sync($assignments);
        if ((int) $this->role_id !== $primaryRoleId) {
            $this->forceFill(['role_id' => $primaryRoleId])->save();
        }
        $this->unsetRelation('role');
        $this->unsetRelation('roles');
    }
}
