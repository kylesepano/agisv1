<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores a criterion result for the subject-centered risk assessment representation.
 */
class IapUniverseRiskScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'criterion_id',
        'criterion_weight',
        'rating',
        'weighted_score',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'criterion_weight' => 'float',
            'rating' => 'float',
            'weighted_score' => 'float',
        ];
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'criterion_id')->withTrashed();
    }
}
