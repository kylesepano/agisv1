<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/** Immutable CIAS Management decision for one extension version. */
class CmsTargetDateExtensionDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_target_date_extension_version_id', 'decision_code', 'decided_by',
        'decided_at', 'decision_comment', 'override_reason',
        'previous_effective_target_date', 'approved_target_date',
        'new_effective_target_date',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'previous_effective_target_date' => 'date',
            'approved_target_date' => 'date',
            'new_effective_target_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Extension decisions are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Extension decisions cannot be deleted.'));
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CmsTargetDateExtensionVersion::class, 'cms_target_date_extension_version_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function history(): HasOne
    {
        return $this->hasOne(CmsRecommendationTargetDateHistory::class, 'cms_target_date_extension_decision_id');
    }
}
