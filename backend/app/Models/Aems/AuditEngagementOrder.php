<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents the controlled Audit Engagement Order family for an engagement.
 */
class AuditEngagementOrder extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'DRAFT',
        'PENDING_REVIEW',
        'RETURNED_FOR_REVISION',
        'RESUBMITTED',
        'APPROVED',
        'ISSUED',
        'CANCELLED',
        'VOIDED',
        'SUPERSEDED',
    ];

    protected $fillable = [
        'audit_engagement_id',
        'order_code',
        'status',
        'current_version_number',
        'prepared_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'issued_by',
        'issued_at',
        'lock_version',
        'is_active',
        'cancelled_by',
        'cancelled_at',
        'voided_by',
        'voided_at',
        'amended_from_version_number',
        'superseded_by_order_id',
    ];

    protected function casts(): array
    {
        return [
            'current_version_number' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'voided_at' => 'datetime',
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
        return $this->hasMany(AuditEngagementOrderVersion::class, 'audit_engagement_order_id')
            ->orderBy('version_number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(AuditEngagementOrderVersion::class, 'audit_engagement_order_id')
            ->ofMany('version_number', 'max');
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

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by')->withTrashed();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    public function signatories(): HasMany
    {
        return $this->hasMany(AemsAeoSignatory::class, 'audit_engagement_order_id')
            ->orderBy('version_number')->orderBy('sequence');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(AemsAeoDistribution::class, 'audit_engagement_order_id')
            ->orderByDesc('created_at');
    }
}
