<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents the subject-centered assessment record used by prioritization.
 */
class IapUniverseRiskAssessment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'period_id',
        'audit_universe_item_id',
        'assessed_by',
        'assessment_date',
        'control_effectiveness_percent',
        'inherent_risk_score',
        'residual_risk_score',
        'inherent_risk_level_id',
        'residual_risk_level_id',
        'control_effectiveness_notes',
        'justification',
        'evidence_summary',
        'status',
        'validation_comment',
        'validated_by',
        'validated_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'control_effectiveness_percent' => 'float',
            'inherent_risk_score' => 'float',
            'residual_risk_score' => 'float',
            'validated_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(IapRiskPeriod::class, 'period_id');
    }

    public function auditUniverseItem(): BelongsTo
    {
        return $this->belongsTo(IapAuditUniverseItem::class, 'audit_universe_item_id')
            ->withTrashed();
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by')->withTrashed();
    }

    public function scores(): HasMany
    {
        return $this->hasMany(IapUniverseRiskScore::class, 'assessment_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(IapRiskEvidence::class, 'assessment_id');
    }

    public function inherentRiskLevel(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'inherent_risk_level_id')->withTrashed();
    }

    public function residualRiskLevel(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'residual_risk_level_id')->withTrashed();
    }
}
