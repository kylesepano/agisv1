<?php

namespace App\Models;

use App\Services\AemsAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Represents a reviewable fieldwork exception that may be dismissed or converted to a finding.
 */
class AuditIssue extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'DRAFT',
        'SUBMITTED',
        'VALIDATED',
        'DISMISSED',
        'CONVERTED_TO_FINDING',
        'WITHDRAWN',
    ];

    public const DISPOSITIONS = [
        'CONVERTED_TO_FINDING',
        'MERGED',
        'RESOLVED_DURING_AUDIT',
        'OBSERVATION',
        'REFERRED',
        'CLOSED_WITHOUT_FINDING',
        'DISMISSED',
        'WITHDRAWN',
    ];

    /** Compatibility labels preserve legacy DISMISSED rows while exposing the
     * professional terminal disposition represented by the row. */
    public const STATUS_COMPATIBILITY = [
        'DRAFT' => ['canonical' => 'DRAFT', 'label' => 'Draft', 'terminal' => false],
        'SUBMITTED' => ['canonical' => 'FOR_REVIEW', 'label' => 'For Review', 'terminal' => false],
        'VALIDATED' => ['canonical' => 'UNDER_EVALUATION', 'label' => 'Under Evaluation', 'terminal' => false],
        'DISMISSED' => ['canonical' => 'DISPOSED', 'label' => 'Disposed', 'terminal' => true],
        'CONVERTED_TO_FINDING' => ['canonical' => 'DISPOSED', 'label' => 'Disposed — Converted to Finding', 'terminal' => true],
        'WITHDRAWN' => ['canonical' => 'WITHDRAWN', 'label' => 'Withdrawn', 'terminal' => true],
    ];

    protected $fillable = [
        'audit_engagement_id',
        'issue_code',
        'title',
        'exception_description',
        'responsible_office_id',
        'risk_rating_id',
        'status',
        'disposition',
        'disposition_reason',
        'disposition_recorded_by',
        'disposition_recorded_at',
        'merged_into_issue_id',
        'referred_to',
        'resolution_details',
        'raised_by',
        'reviewer_id',
        'submitted_at',
        'validated_at',
        'validated_by',
        'dismissed_at',
        'dismissed_by',
        'dismissal_reason',
        'converted_at',
        'converted_by',
        'withdrawn_at',
        'withdrawn_by',
        'withdrawal_reason',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'converted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'disposition_recorded_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $issue): void {
            if (in_array($issue->getOriginal('status'), ['DISMISSED', 'CONVERTED_TO_FINDING', 'WITHDRAWN'], true)) {
                throw new LogicException('Dismissed or converted issues are immutable.');
            }
        });
        static::deleting(function (self $issue): void {
            if (in_array($issue->status, ['DISMISSED', 'CONVERTED_TO_FINDING', 'WITHDRAWN'], true)) {
                throw new LogicException('Dismissed or converted issues cannot be deleted.');
            }
        });
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'engagement',
            fn (Builder $engagement): Builder => app(AemsAccessService::class)
                ->visibleEngagements($engagement, $user),
        );
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function responsibleOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'responsible_office_id')->withTrashed();
    }

    public function riskRating(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'risk_rating_id')->withTrashed();
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id')->withTrashed();
    }

    public function dispositionRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposition_recorded_by')->withTrashed();
    }

    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by')->withTrashed();
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_issue_id')->withTrashed();
    }

    public function workingPaperVersions(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkingPaperVersion::class,
            'audit_issue_working_paper',
            'audit_issue_id',
            'working_paper_version_id',
        )->withTimestamps();
    }

    public function evidence(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditEvidence::class,
            'audit_issue_evidence',
            'audit_issue_id',
            'audit_evidence_id',
        )->withTimestamps();
    }

    public function finding(): HasOne
    {
        return $this->hasOne(AuditFinding::class, 'source_issue_id');
    }
}
