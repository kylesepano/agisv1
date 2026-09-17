<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preserves append-only assignment, reassignment, and removal history for an audit team.
 */
class EngagementTeamHistory extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'engagement_team_history';

    protected $fillable = [
        'audit_engagement_id',
        'engagement_team_id',
        'action',
        'actor_id',
        'reason',
        'old_values',
        'new_values',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(EngagementTeam::class, 'engagement_team_id')->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
