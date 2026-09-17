<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Defines a weighted risk factor used consistently across an assessment period.
 */
class IapRiskPeriodCriterion extends Model
{
    use HasFactory;

    protected $fillable = ['period_id', 'criterion_id', 'weight', 'display_order'];

    protected function casts(): array
    {
        return ['weight' => 'float', 'display_order' => 'integer'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(IapRiskPeriod::class, 'period_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'criterion_id')->withTrashed();
    }
}
