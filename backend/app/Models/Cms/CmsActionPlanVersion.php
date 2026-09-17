<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Immutable-after-submission content revision for one Action Plan family. */
class CmsActionPlanVersion extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_UNDER_REVIEW = 'UNDER_REVIEW';

    public const STATUS_RETURNED = 'RETURNED';

    public const STATUS_ACCEPTED = 'ACCEPTED';

    public const ACTIVE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
    ];

    protected $fillable = [
        'cms_corrective_action_plan_id',
        'version_number',
        'previous_version_id',
        'status_code',
        'active_slot',
        'plan_summary',
        'implementation_strategy',
        'expected_outcome',
        'root_cause_response',
        'resources_required',
        'dependencies',
        'risks_and_constraints',
        'planned_start_date',
        'planned_target_date',
        'owner_office_id',
        'focal_user_id',
        'prepared_by',
        'submitted_by',
        'submitted_at',
        'review_started_by',
        'review_started_at',
        'accepted_by',
        'accepted_at',
        'acceptance_comment',
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
            'planned_start_date' => 'date',
            'planned_target_date' => 'date',
            'submitted_at' => 'datetime',
            'review_started_at' => 'datetime',
            'accepted_at' => 'datetime',
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
                'status_code',
                'active_slot',
                'review_started_by',
                'review_started_at',
                'accepted_by',
                'accepted_at',
                'acceptance_comment',
                'returned_by',
                'returned_at',
                'return_reason',
                'lock_version',
                'updated_at',
            ];
            if (array_diff(array_keys($version->getDirty()), $allowed) !== []) {
                throw new LogicException(
                    'Submitted Action Plan versions are immutable.',
                );
            }
        });
        static::deleting(
            fn (): never => throw new LogicException(
                'Action Plan version history cannot be deleted.',
            ),
        );
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            CmsCorrectiveActionPlan::class,
            'cms_corrective_action_plan_id',
        );
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(CmsActionPlanMilestone::class)
            ->orderBy('display_order')
            ->orderBy('sequence_number');
    }

    public function progressUpdates(): HasMany
    {
        return $this->hasMany(
            CmsProgressUpdate::class,
            'accepted_action_plan_version_id',
        );
    }

    public function ownerOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'owner_office_id')->withTrashed();
    }

    public function focalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'focal_user_id')->withTrashed();
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

    public function accepter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by')->withTrashed();
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by')->withTrashed();
    }
}
