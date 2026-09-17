<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents the controlled Audit Engagement Plan family for an engagement.
 */
class AuditEngagementPlan extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'DRAFT',
        'PENDING_REVIEW',
        'RETURNED_FOR_REVISION',
        'RESUBMITTED',
        'APPROVED',
        'SUPERSEDED',
    ];

    protected $fillable = [
        'audit_engagement_id',
        'plan_code',
        'status',
        'current_version_number',
        'prepared_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'lock_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'current_version_number' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'lock_version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AuditEngagementPlanVersion::class, 'audit_engagement_plan_id')
            ->orderBy('version_number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(AuditEngagementPlanVersion::class, 'audit_engagement_plan_id')
            ->ofMany('version_number', 'max');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(AuditProgram::class, 'audit_engagement_plan_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }
}
