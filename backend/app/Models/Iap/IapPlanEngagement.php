<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a proposed audit imported or added to an Annual Internal Audit Plan.
 */
class IapPlanEngagement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'plan_id',
        'engagement_code',
        'title',
        'engagement_type_id',
        'audit_approach_id',
        'priority_id',
        'risk_level_id',
        'risk_assessment_id',
        'prioritization_item_id',
        'audit_universe_item_id',
        'universe_risk_assessment_id',
        'source_inherent_risk_score',
        'source_residual_risk_score',
        'source_priority_score',
        'source_risk_level_code',
        'source_decision',
        'source_final_rank',
        'target_quarter',
        'imported_at',
        'imported_by',
        'background',
        'objectives',
        'scope',
        'exclusions',
        'audit_criteria',
        'proposed_methodology',
        'planned_start_date',
        'planned_end_date',
        'expected_report_date',
        'schedule_status',
        'scheduled_at',
        'scheduled_by',
        'last_rescheduled_at',
        'last_rescheduled_by',
        'last_reschedule_reason',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'estimated_person_days',
        'estimated_cost',
        'sequence_number',
        'planning_notes',
        'aem_engagement_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_date' => 'date',
            'planned_end_date' => 'date',
            'expected_report_date' => 'date',
            'scheduled_at' => 'datetime',
            'last_rescheduled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'estimated_person_days' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'source_inherent_risk_score' => 'decimal:2',
            'source_residual_risk_score' => 'decimal:2',
            'source_priority_score' => 'decimal:2',
            'source_final_rank' => 'integer',
            'target_quarter' => 'integer',
            'imported_at' => 'datetime',
            'sequence_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InternalAuditPlan::class, 'plan_id');
    }

    public function engagementType(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'engagement_type_id')->withTrashed();
    }

    public function auditApproach(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'audit_approach_id')->withTrashed();
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'priority_id')->withTrashed();
    }

    public function riskLevel(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'risk_level_id')->withTrashed();
    }

    public function riskAssessment(): BelongsTo
    {
        return $this->belongsTo(IapRiskAssessment::class, 'risk_assessment_id');
    }

    public function prioritizationItem(): BelongsTo
    {
        return $this->belongsTo(IapPrioritizationItem::class, 'prioritization_item_id');
    }

    public function auditUniverseItem(): BelongsTo
    {
        return $this->belongsTo(IapAuditUniverseItem::class, 'audit_universe_item_id')
            ->withTrashed();
    }

    public function universeRiskAssessment(): BelongsTo
    {
        return $this->belongsTo(IapUniverseRiskAssessment::class, 'universe_risk_assessment_id')
            ->withTrashed();
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by')->withTrashed();
    }

    public function offices(): BelongsToMany
    {
        return $this->belongsToMany(Office::class, 'iap_engagement_offices', 'plan_engagement_id', 'office_id')
            ->withTimestamps();
    }

    public function auditAreas(): BelongsToMany
    {
        return $this->belongsToMany(AuditArea::class, 'iap_engagement_audit_areas', 'plan_engagement_id', 'audit_area_id')
            ->withTimestamps();
    }

    public function auditFocuses(): BelongsToMany
    {
        return $this->belongsToMany(AuditFocus::class, 'iap_engagement_audit_focuses', 'plan_engagement_id', 'audit_focus_id')
            ->withTimestamps();
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(IapEngagementTeamMember::class, 'plan_engagement_id');
    }

    public function scheduleEvents(): HasMany
    {
        return $this->hasMany(IapScheduleEvent::class, 'plan_engagement_id')
            ->orderBy('created_at');
    }

    public function skillRequirements(): HasMany
    {
        return $this->hasMany(
            IapEngagementSkillRequirement::class,
            'plan_engagement_id',
        );
    }

    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by')->withTrashed();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IapComment::class, 'plan_engagement_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(IapAttachment::class, 'plan_engagement_id');
    }

    /**
     * Returns the active AEMS engagement created from this approved planning item.
     */
    public function aemEngagement(): HasOne
    {
        return $this->hasOne(AuditEngagement::class, 'iap_plan_engagement_id');
    }

    /**
     * Compatibility projection for the legacy IAP-to-AEMS link column.
     *
     * AEMS owns the relationship through audit_engagements and must not write
     * to an approved IAP source row.  Existing IAP screens still read
     * aem_engagement_id, so expose the authoritative AEMS relationship when
     * the legacy column is null without persisting or mutating the source.
     */
    public function getAemEngagementIdAttribute(mixed $value): ?int
    {
        if ($value !== null) {
            return (int) $value;
        }

        return $this->aemEngagement()
            ->withTrashed()
            ->value('audit_engagements.id');
    }
}
