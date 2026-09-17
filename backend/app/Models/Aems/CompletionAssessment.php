<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CompletionAssessment extends Model
{
    public const STATUSES = [
        'DRAFT',
        'PENDING_REVIEW',
        'RETURNED_FOR_REVISION',
        'RESUBMITTED',
        'APPROVED',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_current_revision' => 'boolean',
            'period_from' => 'date',
            'period_to' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'revision_number' => 'integer',
            'version_no' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $assessment): void {
            if ($assessment->getOriginal('status_code') === 'APPROVED') {
                throw new LogicException('Approved Completion Assessments are immutable.');
            }
        });
        static::deleting(function (self $assessment): void {
            if ($assessment->status_code === 'APPROVED') {
                throw new LogicException('Approved Completion Assessments cannot be deleted.');
            }
        });
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CompletionAssessmentItem::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CompletionAssessmentVersion::class)->orderBy('version_no');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_assessment_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by')->withTrashed();
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
