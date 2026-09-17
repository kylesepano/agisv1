<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Represents the ARMIS resource registry profile linked to a Core identity. */
class ArmisResourceProfile extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['DRAFT', 'ACTIVE', 'SUSPENDED', 'INACTIVE', 'ARCHIVED'];

    public const CATEGORIES = ['AUDIT_RESOURCE', 'SPECIALIST', 'REVIEWER', 'SUPPORT'];

    protected $fillable = [
        'resource_code', 'user_id', 'office_id', 'category', 'status',
        'effective_from', 'effective_to', 'notes', 'created_by', 'updated_by',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'lock_version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function competencies(): HasMany
    {
        return $this->hasMany(ArmisCompetency::class, 'resource_profile_id');
    }

    public function availabilityPeriods(): HasMany
    {
        return $this->hasMany(ArmisAvailabilityPeriod::class, 'resource_profile_id');
    }

    public function capacitySubmissions(): HasMany
    {
        return $this->hasMany(ArmisCapacitySubmission::class, 'resource_profile_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ArmisResourceRequirement::class, 'resource_profile_id');
    }

    public function workloadAllocations(): HasMany
    {
        return $this->hasMany(ArmisWorkloadAllocation::class, 'resource_profile_id');
    }

    public function actualPersonDays(): HasMany
    {
        return $this->hasMany(ArmisActualPersonDay::class, 'resource_profile_id');
    }

    public function engagementAssignments(): HasMany
    {
        return $this->hasMany(ArmisEngagementAssignment::class, 'resource_profile_id');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
