<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CmsDispositionReviewAssessment extends Model
{
    protected $fillable = ['cms_disposition_request_version_id', 'reviewer_user_id', 'recommendation_code', 'readiness_assessment', 'basis_assessment', 'evidence_assessment', 'risk_assessment', 'conditions_or_observations', 'reviewed_at'];
    protected function casts(): array { return ['reviewed_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Disposition review assessments are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Disposition review assessments cannot be deleted.'));
    }
    public function version(): BelongsTo { return $this->belongsTo(CmsDispositionRequestVersion::class, 'cms_disposition_request_version_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_user_id')->withTrashed(); }
}
