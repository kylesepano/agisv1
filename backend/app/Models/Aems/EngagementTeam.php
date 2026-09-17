<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a current or historical auditor assignment within an AEMS engagement.
 */
class EngagementTeam extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'engagement_teams';

    protected $fillable = [
        'audit_engagement_id',
        'user_id',
        'assignment_role_code',
        'planned_person_days',
        'actual_person_days',
        'assigned_from',
        'assigned_until',
        'ended_at',
        'assigned_by',
        'ended_by',
        'assignment_notes',
        'end_reason',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'planned_person_days' => 'decimal:2',
            'actual_person_days' => 'decimal:2',
            'assigned_from' => 'date',
            'assigned_until' => 'date',
            'ended_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by')->withTrashed();
    }

    public function ender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by')->withTrashed();
    }

    public function history(): HasMany
    {
        return $this->hasMany(EngagementTeamHistory::class, 'engagement_team_id')
            ->orderBy('created_at');
    }
}
