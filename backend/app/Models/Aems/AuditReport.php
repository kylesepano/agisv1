<?php

namespace App\Models;

use App\Services\AemsAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Represents the controlled report family that progresses from draft to final issuance.
 */
class AuditReport extends Model
{
    use HasFactory, SoftDeletes;

    public const REPORT_STAGES = ['INTERIM_REPORT', 'DRAFT_REPORT', 'FINAL_REPORT'];

    public const STATUSES = [
        'DRAFT',
        'PENDING_REVIEW',
        'RETURNED_FOR_REVISION',
        'RESUBMITTED',
        'APPROVED',
        'ISSUED',
        'SUPERSEDED',
        'WITHDRAWN',
        'ADMINISTRATIVELY_CLOSED',
    ];

    protected $fillable = [
        'audit_engagement_id',
        'report_code',
        'title',
        'report_stage',
        'status',
        'current_version_number',
        'confidentiality_level_id',
        'document_id',
        'current_version_id',
        'supersedes_report_id',
        'prepared_by',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'approving_authority',
        'issued_at',
        'issued_by',
        'withdrawn_at',
        'withdrawn_by',
        'withdrawal_reason',
        'administratively_closed_at',
        'administratively_closed_by',
        'administrative_closure_reason',
        'administrative_closure_reference',
        'lock_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'current_version_number' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'administratively_closed_at' => 'datetime',
            'lock_version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $report): void {
            if (in_array($report->getOriginal('status'), ['ISSUED', 'ADMINISTRATIVELY_CLOSED'], true)) {
                throw new LogicException('Issued audit reports are immutable.');
            }
        });
        static::deleting(function (self $report): void {
            if (in_array($report->status, ['ISSUED', 'ADMINISTRATIVELY_CLOSED'], true)) {
                throw new LogicException('Issued audit reports cannot be deleted.');
            }
        });
    }

    /**
     * Limits internal reports to the audit team and issued reports to authorized recipients.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return app(AemsAccessService::class)->visibleReports($query, $user);
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AuditReportVersion::class)->orderBy('version_number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(AuditReportVersion::class)->ofMany('version_number', 'max');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(AuditReportVersion::class, 'current_version_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function confidentialityLevel(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'confidentiality_level_id')->withTrashed();
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

    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by')->withTrashed();
    }

    public function administrativelyClosedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administratively_closed_by')->withTrashed();
    }

    public function supersededReport(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_report_id')->withTrashed();
    }

    public function successors(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_report_id');
    }

    public function distributionDecisions(): HasMany
    {
        return $this->hasMany(AuditReportDistributionDecision::class);
    }

    public function reviewComments(): HasMany
    {
        return $this->hasMany(AuditReportReviewComment::class);
    }

    public function authorityDecisions(): HasMany
    {
        return $this->hasMany(AemsReportAuthorityDecision::class);
    }

    public function signatories(): HasMany
    {
        return $this->hasMany(AemsReportSignatory::class);
    }

    public function transmittals(): HasMany
    {
        return $this->hasMany(AemsReportTransmittal::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(AemsReportExport::class);
    }
}
