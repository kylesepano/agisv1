<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preserves initial scheduling, rescheduling, cancellation, and conflict history.
 */
class IapScheduleEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'plan_engagement_id',
        'action',
        'from_status',
        'to_status',
        'old_start_date',
        'old_end_date',
        'old_expected_report_date',
        'new_start_date',
        'new_end_date',
        'new_expected_report_date',
        'old_team',
        'new_team',
        'conflicts',
        'reason',
        'actor_id',
    ];

    protected function casts(): array
    {
        return [
            'old_start_date' => 'date',
            'old_end_date' => 'date',
            'old_expected_report_date' => 'date',
            'new_start_date' => 'date',
            'new_end_date' => 'date',
            'new_expected_report_date' => 'date',
            'old_team' => 'array',
            'new_team' => 'array',
            'conflicts' => 'array',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(IapPlanEngagement::class, 'plan_engagement_id')
            ->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
