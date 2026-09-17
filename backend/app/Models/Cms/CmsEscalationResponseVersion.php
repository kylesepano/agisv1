<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CmsEscalationResponseVersion extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_UNDER_REVIEW = 'UNDER_REVIEW';

    public const STATUS_RETURNED = 'RETURNED';

    public const STATUS_ACCEPTED_FOR_FOLLOW_UP = 'ACCEPTED_FOR_FOLLOW_UP';

    public const ACTIVE_STATUSES = [self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_UNDER_REVIEW];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['commitment_start_date' => 'date', 'commitment_target_date' => 'date', 'submitted_at' => 'datetime', 'review_started_at' => 'datetime', 'returned_at' => 'datetime', 'accepted_at' => 'datetime', 'submission_snapshot' => 'array', 'lock_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('status_code') !== self::STATUS_DRAFT) {
                $allowed = ['status_code', 'active_slot', 'submitted_by', 'submitted_at', 'review_started_by', 'review_started_at', 'returned_by', 'returned_at', 'return_reason', 'accepted_by', 'accepted_at', 'acceptance_comment', 'lock_version', 'updated_at'];
                if (array_diff(array_keys($version->getDirty()), $allowed) !== []) {
                    throw new LogicException('Submitted escalation responses are immutable.');
                }
            }
        });
        static::deleting(fn (): never => throw new LogicException('Escalation response versions cannot be deleted.'));
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(CmsEscalationResponse::class, 'cms_escalation_response_id');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'review_started_by')->withTrashed();
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by')->withTrashed();
    }

    public function accepter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by')->withTrashed();
    }

    public function evidenceLinks(): HasMany
    {
        return $this->hasMany(CmsEscalationResponseEvidenceLink::class);
    }

    public function activeEvidenceLinks(): HasMany
    {
        return $this->hasMany(CmsEscalationResponseEvidenceLink::class)->whereNull('removed_at');
    }
}
