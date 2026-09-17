<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Stores planned workload allocations independently from IAP and AEMS records. */
class ArmisWorkloadAllocation extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'RETURNED', 'APPROVED', 'LOCKED'];

    protected $fillable = [
        'workload_family_uuid', 'resource_profile_id', 'version_number', 'supersedes_id',
        'is_current_revision', 'requirement_id', 'source_module', 'source_type', 'source_id',
        'fiscal_year', 'planned_person_days', 'status', 'notes', 'created_by', 'updated_by',
        'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'approved_by',
        'approved_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer', 'is_current_revision' => 'boolean', 'fiscal_year' => 'integer',
            'planned_person_days' => 'decimal:2',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function resourceProfile(): BelongsTo
    {
        return $this->belongsTo(ArmisResourceProfile::class, 'resource_profile_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ArmisResourceRequirement::class, 'requirement_id');
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
}
