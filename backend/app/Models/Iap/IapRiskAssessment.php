<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stores calculated inherent and residual risk for a subject in an assessment cycle.
 */
class IapRiskAssessment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'plan_id',
        'office_id',
        'audit_area_id',
        'assessed_by',
        'assessment_date',
        'last_audit_date',
        'inherent_risk_notes',
        'control_environment_notes',
        'total_weighted_score',
        'calculated_risk_level_id',
        'override_risk_level_id',
        'override_reason',
        'final_risk_level_id',
        'justification',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'last_audit_date' => 'date',
            'total_weighted_score' => 'decimal:2',
            'lock_version' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InternalAuditPlan::class, 'plan_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class)->withTrashed();
    }

    public function auditArea(): BelongsTo
    {
        return $this->belongsTo(AuditArea::class)->withTrashed();
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by')->withTrashed();
    }

    public function calculatedRiskLevel(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'calculated_risk_level_id')->withTrashed();
    }

    public function overrideRiskLevel(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'override_risk_level_id')->withTrashed();
    }

    public function finalRiskLevel(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'final_risk_level_id')->withTrashed();
    }

    public function scores(): HasMany
    {
        return $this->hasMany(IapRiskAssessmentScore::class, 'risk_assessment_id');
    }

    public function engagements(): HasMany
    {
        return $this->hasMany(IapPlanEngagement::class, 'risk_assessment_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(IapAttachment::class, 'risk_assessment_id');
    }
}
