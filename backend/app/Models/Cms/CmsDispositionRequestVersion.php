<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class CmsDispositionRequestVersion extends Model
{
    public const DRAFT = 'DRAFT';
    public const SUBMITTED = 'SUBMITTED';
    public const UNDER_REVIEW = 'UNDER_REVIEW';
    public const RETURNED = 'RETURNED';
    public const FOR_DECISION = 'FOR_DECISION';
    public const APPROVED = 'APPROVED';
    public const REJECTED = 'REJECTED';
    public const ACTIVE_STATUSES = [self::DRAFT, self::SUBMITTED, self::UNDER_REVIEW, self::RETURNED, self::FOR_DECISION];

    protected $fillable = [
        'cms_disposition_request_id', 'version_number', 'previous_version_id',
        'status_code', 'active_slot', 'previous_case_status', 'case_lock_version',
        'requested_effective_date', 'disposition_summary', 'basis_and_criteria',
        'risk_impact_assessment', 'management_position', 'responsible_office_confirmation',
        'accepted_risk_rationale', 'risk_treatment_and_monitoring',
        'no_longer_applicable_basis', 'transition_or_records_impact',
        'residual_risk_statement', 'no_additional_evidence_explanation',
        'prepared_by', 'submitted_by', 'submitted_at', 'review_started_by',
        'review_started_at', 'returned_by', 'returned_at', 'return_reason',
        'revision_reason', 'submission_snapshot', 'lock_version',
    ];

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'case_lock_version' => 'integer', 'requested_effective_date' => 'date', 'submitted_at' => 'datetime', 'review_started_at' => 'datetime', 'returned_at' => 'datetime', 'submission_snapshot' => 'array', 'lock_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('status_code') === self::DRAFT) return;
            $allowed = ['status_code', 'active_slot', 'submitted_by', 'submitted_at', 'review_started_by', 'review_started_at', 'returned_by', 'returned_at', 'return_reason', 'lock_version', 'updated_at'];
            if (array_diff(array_keys($version->getDirty()), $allowed) !== []) throw new LogicException('Submitted disposition versions are immutable.');
        });
        static::deleting(fn (): never => throw new LogicException('Disposition version history cannot be deleted.'));
    }

    public function request(): BelongsTo { return $this->belongsTo(CmsDispositionRequest::class, 'cms_disposition_request_id'); }
    public function previousVersion(): BelongsTo { return $this->belongsTo(self::class, 'previous_version_id'); }
    public function preparer(): BelongsTo { return $this->belongsTo(User::class, 'prepared_by')->withTrashed(); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by')->withTrashed(); }
    public function reviewStarter(): BelongsTo { return $this->belongsTo(User::class, 'review_started_by')->withTrashed(); }
    public function returner(): BelongsTo { return $this->belongsTo(User::class, 'returned_by')->withTrashed(); }
    public function assessment(): HasOne { return $this->hasOne(CmsDispositionReviewAssessment::class); }
    public function decision(): HasOne { return $this->hasOne(CmsDispositionDecision::class); }
    public function evidenceLinks(): HasMany { return $this->hasMany(CmsDispositionEvidenceLink::class)->orderBy('linked_at'); }
    public function activeEvidenceLinks(): HasMany { return $this->evidenceLinks()->whereNull('removed_at'); }
    public function getDisplayCodeAttribute(): string { return sprintf('%s-V%d', $this->request?->display_code ?? 'DISPOSITION', $this->version_number); }
}
