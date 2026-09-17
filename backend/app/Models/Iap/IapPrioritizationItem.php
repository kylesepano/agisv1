<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents one ranked assessed subject and its selection or deferral decision.
 */
class IapPrioritizationItem extends Model
{
    use HasFactory;

    public const DECISIONS = ['SELECTED', 'DEFERRED', 'NOT_SELECTED'];

    protected $fillable = [
        'prioritization_run_id',
        'risk_assessment_id',
        'audit_universe_item_id',
        'subject_code',
        'subject_name',
        'office_code',
        'office_name',
        'audit_area_code',
        'audit_area_name',
        'inherent_risk_score',
        'control_effectiveness_percent',
        'residual_risk_score',
        'risk_level_code',
        'risk_level_label',
        'priority_score',
        'system_rank',
        'final_rank',
        'recommended_decision',
        'decision',
        'decision_reason',
        'is_manual_override',
        'override_reason',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'inherent_risk_score' => 'float',
            'control_effectiveness_percent' => 'float',
            'residual_risk_score' => 'float',
            'priority_score' => 'float',
            'system_rank' => 'integer',
            'final_rank' => 'integer',
            'is_manual_override' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(IapPrioritizationRun::class, 'prioritization_run_id');
    }

    public function riskAssessment(): BelongsTo
    {
        return $this->belongsTo(IapUniverseRiskAssessment::class, 'risk_assessment_id')
            ->withTrashed();
    }

    public function auditUniverseItem(): BelongsTo
    {
        return $this->belongsTo(IapAuditUniverseItem::class, 'audit_universe_item_id')
            ->withTrashed();
    }

    public function planEngagements(): HasMany
    {
        return $this->hasMany(IapPlanEngagement::class, 'prioritization_item_id');
    }
}
