<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/** Stable request family for one unresolved or resolved target-date extension. */
class CmsTargetDateExtensionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_recommendation_case_id',
        'request_sequence',
        'baseline_effective_target_date',
        'created_by',
        'current_version_id',
        'resolved_version_id',
        'resolved_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'request_sequence' => 'integer',
            'baseline_effective_target_date' => 'date',
            'resolved_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(
            fn (): never => throw new LogicException('Target-date extension history cannot be deleted.'),
        );
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CmsRecommendationCase::class, 'cms_recommendation_case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CmsTargetDateExtensionVersion::class)
            ->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(CmsTargetDateExtensionVersion::class, 'current_version_id');
    }

    public function resolvedVersion(): BelongsTo
    {
        return $this->belongsTo(CmsTargetDateExtensionVersion::class, 'resolved_version_id');
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(CmsTargetDateExtensionVersion::class)
            ->whereIn('status_code', CmsTargetDateExtensionVersion::ACTIVE_STATUSES);
    }

    public function getDisplayCodeAttribute(): string
    {
        return sprintf(
            'EXT-CMS-REC-%06d-%03d',
            $this->cms_recommendation_case_id,
            $this->request_sequence,
        );
    }
}
