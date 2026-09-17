<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preserves SIAP approval, return, activation, and revision history.
 */
class SiapWorkflowEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'strategic_plan_id',
        'action',
        'from_status',
        'to_status',
        'actor_id',
        'actor_role_code',
        'comment',
        'plan_lock_version',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'plan_lock_version' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function strategicPlan(): BelongsTo
    {
        return $this->belongsTo(StrategicInternalAuditPlan::class, 'strategic_plan_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
