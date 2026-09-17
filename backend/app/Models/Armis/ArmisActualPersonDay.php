<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Stores versioned actual person-day submissions; approved versions are locked. */
class ArmisActualPersonDay extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'RETURNED', 'APPROVED', 'LOCKED'];

    protected $fillable = [
        'actual_family_uuid', 'resource_profile_id', 'assignment_id', 'source_module', 'source_type', 'source_id', 'period_start',
        'period_end', 'version_number', 'actual_person_days', 'status', 'notes',
        'supersedes_id', 'is_current_revision', 'variance_reason', 'submitted_by', 'submitted_at',
        'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at', 'created_by', 'updated_by', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'version_number' => 'integer',
            'is_current_revision' => 'boolean',
            'actual_person_days' => 'decimal:2', 'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'lock_version' => 'integer',
        ];
    }

    public function resourceProfile(): BelongsTo
    {
        return $this->belongsTo(ArmisResourceProfile::class, 'resource_profile_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ArmisEngagementAssignment::class, 'assignment_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
