<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Defines a strategic audit theme or priority within a SIAP revision.
 */
class SiapPriority extends Model
{
    use HasFactory;

    protected $fillable = [
        'strategic_plan_id',
        'priority_code',
        'title',
        'theme',
        'description',
        'expected_outcome',
        'display_order',
    ];

    protected function casts(): array
    {
        return ['display_order' => 'integer'];
    }

    public function strategicPlan(): BelongsTo
    {
        return $this->belongsTo(StrategicInternalAuditPlan::class, 'strategic_plan_id');
    }
}
