<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsClosureReviewAssessment extends Model
{
    protected $fillable = ['cms_closure_request_version_id', 'reviewer_user_id', 'recommendation_code', 'readiness_summary', 'validation_lineage_assessment', 'document_and_evidence_assessment', 'residual_matter_assessment', 'escalation_and_extension_assessment', 'records_completeness_assessment', 'conditions_or_observations', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CmsClosureRequestVersion::class, 'cms_closure_request_version_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id')->withTrashed();
    }
}
