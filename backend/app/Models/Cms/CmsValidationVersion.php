<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Immutable-after-submission professional validation revision. */
class CmsValidationVersion extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_UNDER_REVIEW = 'UNDER_REVIEW';

    public const STATUS_RETURNED = 'RETURNED';

    public const STATUS_FINALIZED = 'FINALIZED';

    public const ACTIVE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
    ];

    public const CONCLUSIONS = [
        'NOT_IMPLEMENTED',
        'PARTIALLY_IMPLEMENTED',
        'IMPLEMENTED',
        'INADEQUATE_BASIS',
    ];

    protected $fillable = [
        'cms_validation_review_id',
        'version_number',
        'previous_version_id',
        'status_code',
        'active_slot',
        'validation_scope',
        'validation_objectives',
        'methodology_summary',
        'overall_work_performed',
        'overall_evidence_summary',
        'limitations',
        'professional_judgment_rationale',
        'proposed_conclusion_code',
        'final_conclusion_code',
        'validated_completion_percentage',
        'validator_user_id',
        'prepared_by',
        'submitted_by',
        'submitted_at',
        'supervisory_review_started_by',
        'supervisory_review_started_at',
        'supervisory_review_comment',
        'returned_by',
        'returned_at',
        'return_reason',
        'finalized_by',
        'finalized_at',
        'finalization_comment',
        'supervisory_override_reason',
        'revision_reason',
        'submission_snapshot',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'validated_completion_percentage' => 'decimal:2',
            'submitted_at' => 'datetime',
            'supervisory_review_started_at' => 'datetime',
            'returned_at' => 'datetime',
            'finalized_at' => 'datetime',
            'submission_snapshot' => 'array',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            $originalStatus = $version->getOriginal('status_code');
            if ($originalStatus === self::STATUS_DRAFT) {
                return;
            }

            $workflowFields = match ($originalStatus) {
                self::STATUS_SUBMITTED => [
                    'status_code',
                    'supervisory_review_started_by',
                    'supervisory_review_started_at',
                    'supervisory_review_comment',
                    'lock_version',
                    'updated_at',
                ],
                self::STATUS_UNDER_REVIEW => [
                    'status_code',
                    'active_slot',
                    'returned_by',
                    'returned_at',
                    'return_reason',
                    'final_conclusion_code',
                    'finalized_by',
                    'finalized_at',
                    'finalization_comment',
                    'supervisory_override_reason',
                    'lock_version',
                    'updated_at',
                ],
                default => [],
            };
            if (array_diff(array_keys($version->getDirty()), $workflowFields) !== []) {
                throw new LogicException('Submitted Validation Versions are immutable.');
            }
        });
        static::deleting(
            fn (): never => throw new LogicException('Validation Version history cannot be deleted.'),
        );
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(CmsValidationReview::class, 'cms_validation_review_id');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CmsValidationItem::class)->orderBy('display_order');
    }

    public function evidenceAssessments(): HasMany
    {
        return $this->hasMany(CmsValidationEvidenceAssessment::class)->orderBy('id');
    }

    public function evidenceLinks(): HasMany
    {
        return $this->hasMany(CmsValidationEvidenceLink::class)->orderBy('linked_at');
    }

    public function activeEvidenceLinks(): HasMany
    {
        return $this->evidenceLinks()->whereNull('removed_at');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_user_id')->withTrashed();
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function supervisoryReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisory_review_started_by')->withTrashed();
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by')->withTrashed();
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by')->withTrashed();
    }
}
