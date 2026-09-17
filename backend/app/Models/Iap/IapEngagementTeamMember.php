<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assigns an auditor, team role, and person-day allocation to an engagement.
 */
class IapEngagementTeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_engagement_id',
        'user_id',
        'team_role_id',
        'planned_person_days',
        'assignment_notes',
    ];

    protected function casts(): array
    {
        return ['planned_person_days' => 'decimal:2'];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(IapPlanEngagement::class, 'plan_engagement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function teamRole(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'team_role_id')->withTrashed();
    }
}
