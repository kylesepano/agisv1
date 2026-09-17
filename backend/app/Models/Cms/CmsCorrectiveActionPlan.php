<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Stable family that points to current and officially accepted plan versions. */
class CmsCorrectiveActionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_recommendation_case_id',
        'owner_office_id',
        'created_by',
        'current_version_id',
        'accepted_version_id',
        'lock_version',
    ];

    protected function casts(): array
    {
        return ['lock_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $plan): void {
            if ($plan->versions()->where('status_code', '!=', CmsActionPlanVersion::STATUS_DRAFT)->exists()) {
                throw new LogicException(
                    'An Action Plan with submitted history cannot be deleted.',
                );
            }
        });
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(
            CmsRecommendationCase::class,
            'cms_recommendation_case_id',
        );
    }

    public function ownerOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'owner_office_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CmsActionPlanVersion::class)
            ->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(CmsActionPlanVersion::class, 'current_version_id');
    }

    public function acceptedVersion(): BelongsTo
    {
        return $this->belongsTo(CmsActionPlanVersion::class, 'accepted_version_id');
    }
}
