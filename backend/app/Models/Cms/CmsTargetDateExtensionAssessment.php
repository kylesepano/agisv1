<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Immutable Compliance Monitor assessment and recommendation. */
class CmsTargetDateExtensionAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_target_date_extension_version_id', 'assessor_user_id',
        'recommendation_code', 'assessment_summary', 'evidence_review_summary',
        'feasibility_assessment', 'risk_of_delay_summary',
        'conditions_or_observations', 'assessed_at',
    ];

    protected function casts(): array
    {
        return ['assessed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Extension assessments are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Extension assessments cannot be deleted.'));
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CmsTargetDateExtensionVersion::class, 'cms_target_date_extension_version_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_user_id')->withTrashed();
    }
}
