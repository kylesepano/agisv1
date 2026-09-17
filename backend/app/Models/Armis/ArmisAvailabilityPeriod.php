<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Stores a submitted or approved ARMIS availability period. */
class ArmisAvailabilityPeriod extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'RETURNED', 'APPROVED', 'LOCKED'];

    public const TYPES = ['AVAILABLE', 'UNAVAILABLE', 'LEAVE', 'TRAINING', 'OTHER'];

    protected $fillable = [
        'availability_family_uuid', 'resource_profile_id', 'version_number', 'supersedes_id',
        'is_current_revision', 'availability_type', 'start_date', 'end_date', 'person_days',
        'status', 'notes', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at',
        'approved_by', 'approved_at', 'created_by', 'updated_by', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer', 'is_current_revision' => 'boolean',
            'start_date' => 'date', 'end_date' => 'date', 'person_days' => 'decimal:2',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function resourceProfile(): BelongsTo
    {
        return $this->belongsTo(ArmisResourceProfile::class, 'resource_profile_id');
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
}
