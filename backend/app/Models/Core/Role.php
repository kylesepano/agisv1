<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Groups permissions and configurable data scopes for assignment to many users.
 */
class Role extends Model
{
    use HasFactory, SoftDeletes;

    public const OFFICE_SCOPES = ['ALL', 'OWN_OFFICE'];

    public const ENGAGEMENT_SCOPES = ['ALL', 'ASSIGNED'];

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_system',
        'is_active',
        'office_access_scope',
        'engagement_access_scope',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission')->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_role_assignments')
            ->withPivot(['is_primary', 'assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function hasPermission(string $permission): bool
    {
        return $this->is_active && $this->permissions->contains('code', $permission);
    }
}
