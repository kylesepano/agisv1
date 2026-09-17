<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/** Immutable-after-submission management request version. */
class CmsTargetDateExtensionVersion extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_UNDER_REVIEW = 'UNDER_REVIEW';

    public const STATUS_RETURNED = 'RETURNED';

    public const STATUS_FOR_APPROVAL = 'FOR_APPROVAL';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const ACTIVE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_FOR_APPROVAL,
    ];

    protected $fillable = [
        'cms_target_date_extension_request_id',
        'version_number',
        'previous_version_id',
        'status_code',
        'active_slot',
        'accepted_action_plan_version_id',
        'recorded_progress_update_version_id',
        'case_lock_version',
        'requested_target_date',
        'extension_justification',
        'cause_of_delay',
        'actions_already_taken',
        'remaining_actions',
        'recovery_plan',
        'impact_if_not_approved',
        'revised_schedule_summary',
        'management_progress_summary',
        'no_evidence_explanation',
        'prepared_by',
        'submitted_by',
        'submitted_at',
        'review_started_by',
        'review_started_at',
        'returned_by',
        'returned_at',
        'return_reason',
        'revision_reason',
        'submission_snapshot',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'case_lock_version' => 'integer',
            'requested_target_date' => 'date',
            'submitted_at' => 'datetime',
            'review_started_at' => 'datetime',
            'returned_at' => 'datetime',
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
                'status_code', 'active_slot', 'submitted_by', 'submitted_at',
                'review_started_by', 'review_started_at', 'returned_by',
                'returned_at', 'return_reason', 'lock_version', 'updated_at',
            ];
            if (array_diff(array_keys($version->getDirty()), $allowed) !== []) {
                throw new LogicException('Submitted extension versions are immutable.');
            }
        });
        static::deleting(
            fn (): never => throw new LogicException('Target-date extension version history cannot be deleted.'),
        );
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CmsTargetDateExtensionRequest::class, 'cms_target_date_extension_request_id');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    public function acceptedActionPlanVersion(): BelongsTo
    {
        return $this->belongsTo(CmsActionPlanVersion::class, 'accepted_action_plan_version_id');
    }

    public function recordedProgressUpdateVersion(): BelongsTo
    {
        return $this->belongsTo(CmsProgressUpdateVersion::class, 'recorded_progress_update_version_id');
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

    public function assessment(): HasOne
    {
        return $this->hasOne(CmsTargetDateExtensionAssessment::class);
    }

    public function decision(): HasOne
    {
        return $this->hasOne(CmsTargetDateExtensionDecision::class);
    }

    public function evidenceLinks(): HasMany
    {
        return $this->hasMany(CmsTargetDateExtensionEvidenceLink::class)
            ->orderBy('linked_at');
    }

    public function activeEvidenceLinks(): HasMany
    {
        return $this->evidenceLinks()->whereNull('removed_at');
    }

    public function getDisplayCodeAttribute(): string
    {
        return sprintf(
            '%s-V%d',
            $this->request?->display_code ?? 'EXTENSION',
            $this->version_number,
        );
    }

    public function getExtensionDaysAttribute(): ?int
    {
        $baseline = $this->request?->baseline_effective_target_date;

        return $baseline && $this->requested_target_date
            ? $baseline->diffInDays($this->requested_target_date)
            : null;
    }
}
