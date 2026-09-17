<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class CmsReopeningRequestVersion extends Model
{
    public const DRAFT = 'DRAFT';

    public const SUBMITTED = 'SUBMITTED';

    public const UNDER_REVIEW = 'UNDER_REVIEW';

    public const RETURNED = 'RETURNED';

    public const FOR_DECISION = 'FOR_DECISION';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    public const ACTIVE_STATUSES = [self::DRAFT, self::SUBMITTED, self::UNDER_REVIEW, self::RETURNED, self::FOR_DECISION];

    protected $fillable = [
        'cms_reopening_request_id', 'version_number', 'previous_version_id', 'status_code', 'active_slot',
        'reopening_reason_code', 'request_summary', 'changed_condition_or_new_fact', 'materiality_assessment',
        'source_terminal_decision_assessment', 'implementation_or_control_failure_assessment', 'risk_impact',
        'responsible_office_impact', 'proposed_follow_up_approach', 'proposed_destination_code',
        'new_action_plan_requirement_explanation', 'existing_action_plan_suitability_assessment',
        'compliance_monitor_requirement', 'target_date_implications', 'related_recurrence_summary',
        'related_escalation_summary', 'management_position', 'cias_initiator_position',
        'no_additional_evidence_explanation', 'prepared_by', 'submitted_by', 'submitted_at',
        'review_started_by', 'review_started_at', 'returned_by', 'returned_at', 'return_reason',
        'revision_reason', 'submission_snapshot', 'lock_version',
    ];

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'submitted_at' => 'datetime', 'review_started_at' => 'datetime', 'returned_at' => 'datetime', 'submission_snapshot' => 'array', 'lock_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('status_code') === self::DRAFT) {
                return;
            }
            $allowed = ['status_code', 'active_slot', 'review_started_by', 'review_started_at', 'returned_by', 'returned_at', 'return_reason', 'lock_version', 'updated_at'];
            if (array_diff(array_keys($version->getDirty()), $allowed) !== []) {
                throw new LogicException('Submitted reopening versions are immutable.');
            }
        });
        static::deleting(fn (): never => throw new LogicException('Reopening version history cannot be deleted.'));
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CmsReopeningRequest::class, 'cms_reopening_request_id');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function reviewStarter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'review_started_by')->withTrashed();
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by')->withTrashed();
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(CmsReopeningReviewAssessment::class);
    }

    public function decision(): HasOne
    {
        return $this->hasOne(CmsReopeningDecision::class);
    }

    public function evidenceLinks(): HasMany
    {
        return $this->hasMany(CmsReopeningEvidenceLink::class)->orderBy('linked_at');
    }

    public function activeEvidenceLinks(): HasMany
    {
        return $this->hasMany(CmsReopeningEvidenceLink::class)->whereNull('removed_at');
    }

    public function getDisplayCodeAttribute(): string
    {
        return sprintf('%s-V%d', $this->request?->display_code ?? 'REOPENING', $this->version_number);
    }
}
