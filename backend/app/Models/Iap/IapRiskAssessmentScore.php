<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores one criterion score and justification within a risk assessment.
 */
class IapRiskAssessmentScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'risk_assessment_id',
        'risk_criterion_id',
        'criterion_weight',
        'rating',
        'weighted_score',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'criterion_weight' => 'decimal:2',
            'rating' => 'decimal:2',
            'weighted_score' => 'decimal:4',
        ];
    }

    public function riskAssessment(): BelongsTo
    {
        return $this->belongsTo(IapRiskAssessment::class, 'risk_assessment_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'risk_criterion_id')->withTrashed();
    }
}
