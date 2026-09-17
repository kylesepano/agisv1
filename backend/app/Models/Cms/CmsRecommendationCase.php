<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Operational root initialized from one immutable CMS recommendation intake.
 */
class CmsRecommendationCase extends Model
{
    use HasFactory;

    public const STATUS_TRANSFERRED = 'TRANSFERRED';

    public const STATUS_FOR_ACTION_PLAN = 'FOR_ACTION_PLAN';

    public const STATUS_MONITORING = 'MONITORING';

    public const STATUS_FOR_VALIDATION = 'FOR_VALIDATION';

    public const STATUS_PARTIALLY_IMPLEMENTED = 'PARTIALLY_IMPLEMENTED';

    public const STATUS_IMPLEMENTED = 'IMPLEMENTED';

    public const STATUS_FOR_CLOSURE = 'FOR_CLOSURE';

    public const STATUS_CLOSED = 'CLOSED';

    public const STATUS_FOR_DISPOSITION = 'FOR_DISPOSITION';

    public const STATUS_ACCEPTED_RISK = 'ACCEPTED_RISK';

    public const STATUS_NO_LONGER_APPLICABLE = 'NO_LONGER_APPLICABLE';

    protected $fillable = [
        'cms_recommendation_id',
        'status_code',
        'effective_target_implementation_date',
        'lead_responsible_office_id',
        'opened_at',
        'created_by',
        'lock_version',
        'closed_at', 'closed_by', 'closure_decision_id',
        'active_cycle_number', 'reopening_count', 'last_reopened_at',
        'last_reopened_by', 'last_reopening_decision_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_target_implementation_date' => 'date',
            'opened_at' => 'datetime',
            'lock_version' => 'integer',
            'closed_at' => 'datetime',
            'active_cycle_number' => 'integer',
            'reopening_count' => 'integer',
            'last_reopened_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(
            fn (): never => throw new LogicException('CMS recommendation cases cannot be deleted.'),
        );
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(CmsRecommendation::class, 'cms_recommendation_id');
    }

    public function leadResponsibleOffice(): BelongsTo
    {
        return $this->belongsTo(
            Office::class,
            'lead_responsible_office_id',
        )->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function events(): HasMany
    {
        return $this->hasMany(CmsRecommendationEvent::class)
            ->orderBy('created_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CmsRecommendationAssignment::class)
            ->orderByDesc('assigned_at');
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(CmsRecommendationAssignment::class)
            ->where('is_current', true)
            ->where(function ($query): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('effective_until')->orWhere('effective_until', '>', now());
            });
    }

    public function actionPlan(): HasOne
    {
        return $this->hasOne(
            CmsCorrectiveActionPlan::class,
            'cms_recommendation_case_id',
        );
    }

    public function progressUpdates(): HasMany
    {
        return $this->hasMany(
            CmsProgressUpdate::class,
            'cms_recommendation_case_id',
        )->orderByDesc('reporting_sequence');
    }

    public function validationReviews(): HasMany
    {
        return $this->hasMany(
            CmsValidationReview::class,
            'cms_recommendation_case_id',
        )->orderByDesc('validation_sequence');
    }

    public function targetDateExtensionRequests(): HasMany
    {
        return $this->hasMany(
            CmsTargetDateExtensionRequest::class,
            'cms_recommendation_case_id',
        )->orderByDesc('request_sequence');
    }

    public function unresolvedTargetDateExtensionRequest(): HasOne
    {
        return $this->hasOne(
            CmsTargetDateExtensionRequest::class,
            'cms_recommendation_case_id',
        )->whereNull('resolved_at');
    }

    public function targetDateHistory(): HasMany
    {
        return $this->hasMany(
            CmsRecommendationTargetDateHistory::class,
            'cms_recommendation_case_id',
        )->orderBy('occurred_at')->orderBy('id');
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(CmsEscalation::class, 'cms_recommendation_case_id')
            ->orderByDesc('escalation_sequence');
    }

    public function activeEscalation(): HasOne
    {
        return $this->hasOne(CmsEscalation::class, 'cms_recommendation_case_id')
            ->whereNull('resolved_at');
    }

    public function activeValidationReview(): HasOne
    {
        return $this->hasOne(
            CmsValidationReview::class,
            'cms_recommendation_case_id',
        )->where('active_slot', 'ACTIVE');
    }

    public function closureRequests(): HasMany
    {
        return $this->hasMany(CmsClosureRequest::class, 'cms_recommendation_case_id')->orderByDesc('request_sequence');
    }

    public function unresolvedClosureRequest(): HasOne
    {
        return $this->hasOne(CmsClosureRequest::class, 'cms_recommendation_case_id')->whereNull('resolved_at');
    }

    public function closureDecision(): BelongsTo
    {
        return $this->belongsTo(CmsClosureDecision::class, 'closure_decision_id');
    }

    public function dispositionRequests(): HasMany
    {
        return $this->hasMany(CmsDispositionRequest::class, 'cms_recommendation_case_id')
            ->orderByDesc('request_sequence');
    }

    public function unresolvedDispositionRequest(): HasOne
    {
        return $this->hasOne(CmsDispositionRequest::class, 'cms_recommendation_case_id')
            ->whereNull('resolved_at');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    public function lastReopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_reopened_by')->withTrashed();
    }

    public function lastReopeningDecision(): BelongsTo
    {
        return $this->belongsTo(CmsReopeningDecision::class, 'last_reopening_decision_id');
    }

    public function reopeningRequests(): HasMany
    {
        return $this->hasMany(CmsReopeningRequest::class, 'cms_recommendation_case_id')
            ->orderByDesc('request_sequence');
    }

    public function unresolvedReopeningRequest(): HasOne
    {
        return $this->hasOne(CmsReopeningRequest::class, 'cms_recommendation_case_id')
            ->whereNull('resolved_at');
    }

    public function closureCandidates(): HasMany
    {
        return $this->hasMany(CmsClosureCandidate::class, 'cms_recommendation_case_id')
            ->orderByDesc('detected_at');
    }

    public function openClosureCandidate(): HasOne
    {
        return $this->hasOne(CmsClosureCandidate::class, 'cms_recommendation_case_id')
            ->whereIn('status_code', [CmsClosureCandidate::OPEN, CmsClosureCandidate::ACKNOWLEDGED]);
    }

    public function escalationCandidates(): HasMany
    {
        return $this->hasMany(CmsEscalationCandidate::class, 'cms_recommendation_case_id')
            ->orderByDesc('detected_at');
    }

    public function openEscalationCandidate(): HasOne
    {
        return $this->hasOne(CmsEscalationCandidate::class, 'cms_recommendation_case_id')
            ->whereIn('status_code', [CmsEscalationCandidate::OPEN, CmsEscalationCandidate::ACKNOWLEDGED]);
    }
}
