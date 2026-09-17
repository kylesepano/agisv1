<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Defines a strategic audit objective and its linked audit areas.
 */
class SiapObjective extends Model
{
    use HasFactory;

    protected $fillable = [
        'strategic_plan_id',
        'objective_code',
        'title',
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

    public function auditAreas(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditArea::class,
            'siap_objective_audit_area',
            'objective_id',
            'audit_area_id',
        );
    }
}
