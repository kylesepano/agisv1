<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CmsReopeningReviewAssessment extends Model
{
    protected $fillable = ['cms_reopening_request_version_id', 'reviewer_user_id', 'recommendation_code', 'source_decision_integrity_assessment', 'new_evidence_or_changed_condition_assessment', 'materiality_assessment', 'risk_assessment', 'destination_status_assessment', 'action_plan_requirement_assessment', 'assignment_and_monitoring_assessment', 'evidence_sufficiency_assessment', 'recommendation_rationale', 'conditions_or_observations', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Reopening assessments are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Reopening assessments cannot be deleted.'));
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CmsReopeningRequestVersion::class, 'cms_reopening_request_version_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id')->withTrashed();
    }
}
