<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/** Immutable-after-submission management-reported Progress Update version. */
class CmsProgressUpdateVersion extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_UNDER_REVIEW = 'UNDER_REVIEW';

    public const STATUS_RETURNED = 'RETURNED';

    public const STATUS_RECORDED = 'RECORDED';

    public const ACTIVE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
    ];

    protected $fillable = [
        'cms_progress_update_id',
        'version_number',
        'previous_version_id',
        'status_code',
        'active_slot',
        'accomplishment_summary',
        'management_reported_overall_percentage',
        'system_calculated_weighted_percentage',
        'baseline_weighted',
        'issues_and_constraints',
        'corrective_actions_for_delays',
        'next_steps',
        'forecast_completion_date',
        'management_declaration',
        'general_evidence_explanation',
        'prepared_by',
        'submitted_by',
        'submitted_at',
        'review_started_by',
        'review_started_at',
        'review_comment',
        'returned_by',
        'returned_at',
        'return_reason',
        'recorded_by',
        'recorded_at',
        'recording_comment',
        'revision_reason',
        'submission_snapshot',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'management_reported_overall_percentage' => 'decimal:2',
            'system_calculated_weighted_percentage' => 'decimal:2',
            'baseline_weighted' => 'boolean',
            'forecast_completion_date' => 'date',
            'submitted_at' => 'datetime',
            'review_started_at' => 'datetime',
            'returned_at' => 'datetime',
            'recorded_at' => 'datetime',
            'submission_snapshot' => 'array',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('status_code') === self::STATUS_DRAFT) {
                return;
            }

            $allowed = [
                'status_code',
                'active_slot',
                'review_started_by',
                'review_started_at',
                'review_comment',
                'returned_by',
                'returned_at',
                'return_reason',
                'recorded_by',
                'recorded_at',
                'recording_comment',
                'lock_version',
                'updated_at',
            ];
            if (array_diff(array_keys($version->getDirty()), $allowed) !== []) {
                throw new LogicException(
                    'Submitted Progress Update versions are immutable.',
                );
            }
        });
        static::deleting(
            fn (): never => throw new LogicException(
                'Progress Update version history cannot be deleted.',
            ),
        );
    }

    public function progressUpdate(): BelongsTo
    {
        return $this->belongsTo(CmsProgressUpdate::class, 'cms_progress_update_id');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    public function milestoneProgress(): HasMany
    {
        return $this->hasMany(CmsMilestoneProgress::class, 'cms_progress_update_version_id')
            ->orderBy('display_order');
    }

    public function evidenceLinks(): HasMany
    {
        return $this->hasMany(CmsProgressEvidenceLink::class, 'cms_progress_update_version_id')
            ->orderBy('linked_at');
    }

    public function activeEvidenceLinks(): HasMany
    {
        return $this->evidenceLinks()->whereNull('removed_at');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function reviewStarter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'review_started_by')->withTrashed();
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by')->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    public function validationReview(): HasOne
    {
        return $this->hasOne(
            CmsValidationReview::class,
            'recorded_progress_update_version_id',
        );
    }
}
