<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Stable management-reporting family for one recommendation reporting period. */
class CmsProgressUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_recommendation_case_id',
        'cms_corrective_action_plan_id',
        'accepted_action_plan_version_id',
        'reporting_sequence',
        'reporting_period_start',
        'reporting_period_end',
        'created_by',
        'current_version_id',
        'recorded_version_id',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'reporting_sequence' => 'integer',
            'reporting_period_start' => 'date',
            'reporting_period_end' => 'date',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $update): void {
            if ($update->versions()->where('status_code', '!=', CmsProgressUpdateVersion::STATUS_DRAFT)->exists()) {
                throw new LogicException(
                    'A Progress Update with submitted history cannot be deleted.',
                );
            }
        });
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CmsProgressUpdateVersion::class)->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(CmsProgressUpdateVersion::class, 'current_version_id');
    }

    public function recordedVersion(): BelongsTo
    {
        return $this->belongsTo(CmsProgressUpdateVersion::class, 'recorded_version_id');
    }
}
