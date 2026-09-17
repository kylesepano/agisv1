<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Stores a versioned ARMIS resource assignment to an AEMS engagement. */
class ArmisEngagementAssignment extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'RETURNED', 'APPROVED', 'LOCKED'];

    public const ROLES = [
        'SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER',
        'SPECIALIST', 'AUTHORIZED_PARTICIPANT',
    ];

    protected $fillable = [
        'assignment_family_uuid', 'audit_engagement_id', 'resource_profile_id', 'requirement_id',
        'version_number', 'supersedes_id', 'is_current_revision', 'assignment_role_code',
        'assigned_from', 'assigned_until', 'planned_person_days', 'status', 'notes',
        'created_by', 'updated_by', 'submitted_by', 'submitted_at', 'reviewed_by',
        'reviewed_at', 'approved_by', 'approved_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'is_current_revision' => 'boolean',
            'assigned_from' => 'date',
            'assigned_until' => 'date',
            'planned_person_days' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function resourceProfile(): BelongsTo
    {
        return $this->belongsTo(ArmisResourceProfile::class, 'resource_profile_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ArmisResourceRequirement::class, 'requirement_id');
    }

    public function competencies(): HasMany
    {
        return $this->hasMany(ArmisAssignmentCompetency::class, 'assignment_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function actualPersonDays(): HasMany
    {
        return $this->hasMany(ArmisActualPersonDay::class, 'assignment_id');
    }
}
