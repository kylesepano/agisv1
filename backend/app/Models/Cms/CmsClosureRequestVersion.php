<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CmsClosureRequestVersion extends Model
{
    public const DRAFT = 'DRAFT';

    public const SUBMITTED = 'SUBMITTED';

    public const UNDER_REVIEW = 'UNDER_REVIEW';

    public const RETURNED = 'RETURNED';

    public const FOR_DECISION = 'FOR_DECISION';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    public const ACTIVE_STATUSES = [self::DRAFT, self::SUBMITTED, self::UNDER_REVIEW, self::RETURNED, self::FOR_DECISION];

    protected $fillable = ['cms_closure_request_id', 'version_number', 'previous_version_id', 'status_code', 'active_slot', 'finalized_validation_review_id', 'finalized_validation_version_id', 'accepted_action_plan_version_id', 'recorded_progress_update_version_id', 'closure_request_summary', 'implementation_basis', 'validated_implementation_summary', 'residual_matters_summary', 'residual_risk_statement', 'ongoing_monitoring_requirements', 'records_and_documentation_summary', 'resolved_escalation_summary', 'management_confirmation', 'compliance_monitor_recommendation_summary', 'no_additional_evidence_explanation', 'prepared_by', 'submitted_by', 'submitted_at', 'review_started_by', 'review_started_at', 'returned_by', 'returned_at', 'return_reason', 'revision_reason', 'submission_snapshot', 'lock_version'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'review_started_at' => 'datetime', 'returned_at' => 'datetime', 'submission_snapshot' => 'array', 'lock_version' => 'integer'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CmsClosureRequest::class, 'cms_closure_request_id');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    public function validationReview(): BelongsTo
    {
        return $this->belongsTo(CmsValidationReview::class, 'finalized_validation_review_id');
    }

    public function validationVersion(): BelongsTo
    {
        return $this->belongsTo(CmsValidationVersion::class, 'finalized_validation_version_id');
    }

    public function acceptedActionPlanVersion(): BelongsTo
    {
        return $this->belongsTo(CmsActionPlanVersion::class, 'accepted_action_plan_version_id');
    }

    public function recordedProgressUpdateVersion(): BelongsTo
    {
        return $this->belongsTo(CmsProgressUpdateVersion::class, 'recorded_progress_update_version_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'review_started_by')->withTrashed();
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by')->withTrashed();
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(CmsClosureReviewAssessment::class);
    }

    public function decision(): HasOne
    {
        return $this->hasOne(CmsClosureDecision::class);
    }

    public function evidenceLinks(): HasMany
    {
        return $this->hasMany(CmsClosureEvidenceLink::class);
    }

    public function activeEvidenceLinks(): HasMany
    {
        return $this->hasMany(CmsClosureEvidenceLink::class)->whereNull('removed_at');
    }
}
