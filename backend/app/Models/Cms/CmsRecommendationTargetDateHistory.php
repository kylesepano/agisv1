<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only authoritative history of effective target-date changes. */
class CmsRecommendationTargetDateHistory extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'cms_recommendation_target_date_history';

    protected $fillable = [
        'cms_recommendation_case_id', 'history_code', 'previous_target_date',
        'new_target_date', 'cms_target_date_extension_decision_id', 'actor_id',
        'occurred_at', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_target_date' => 'date',
            'new_target_date' => 'date',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Target-date history is append-only.'));
        static::deleting(fn (): never => throw new LogicException('Target-date history cannot be deleted.'));
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CmsRecommendationCase::class, 'cms_recommendation_case_id');
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(CmsTargetDateExtensionDecision::class, 'cms_target_date_extension_decision_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
