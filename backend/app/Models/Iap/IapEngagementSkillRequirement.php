<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Defines a skill and proficiency requirement for a proposed audit engagement.
 */
class IapEngagementSkillRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_engagement_id',
        'specialization_id',
        'minimum_auditors',
        'minimum_proficiency',
        'notes',
    ];

    protected function casts(): array
    {
        return ['minimum_auditors' => 'integer'];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(IapPlanEngagement::class, 'plan_engagement_id');
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'specialization_id')
            ->withTrashed();
    }
}
