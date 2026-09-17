<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/** Stable independent-validation cycle for one exact recorded Progress Update. */
class CmsValidationReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_recommendation_case_id',
        'cms_corrective_action_plan_id',
        'accepted_action_plan_version_id',
        'cms_progress_update_id',
        'recorded_progress_update_version_id',
        'validation_sequence',
        'created_by',
        'current_version_id',
        'finalized_version_id',
        'active_slot',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'validation_sequence' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $review): void {
            if ($review->getOriginal('finalized_version_id') !== null) {
                throw new LogicException('Finalized Validation Review pointers are immutable.');
            }
        });
        static::deleting(
            fn (): never => throw new LogicException('Validation Review history cannot be deleted.'),
        );
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CmsRecommendationCase::class, 'cms_recommendation_case_id');
    }

    public function actionPlan(): BelongsTo
    {
        return $this->belongsTo(CmsCorrectiveActionPlan::class, 'cms_corrective_action_plan_id');
    }

    public function acceptedActionPlanVersion(): BelongsTo
    {
        return $this->belongsTo(CmsActionPlanVersion::class, 'accepted_action_plan_version_id');
    }

    public function progressUpdate(): BelongsTo
    {
        return $this->belongsTo(CmsProgressUpdate::class, 'cms_progress_update_id');
    }

    public function recordedProgressUpdateVersion(): BelongsTo
    {
        return $this->belongsTo(
            CmsProgressUpdateVersion::class,
            'recorded_progress_update_version_id',
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CmsValidationVersion::class)->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(CmsValidationVersion::class, 'current_version_id');
    }

    public function finalizedVersion(): BelongsTo
    {
        return $this->belongsTo(CmsValidationVersion::class, 'finalized_version_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CmsValidationAssignment::class)->orderByDesc('assigned_at');
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(CmsValidationAssignment::class)
            ->where('assignment_role_code', CmsValidationAssignment::ROLE_PRIMARY_VALIDATOR)
            ->where('is_current', true);
    }
}
