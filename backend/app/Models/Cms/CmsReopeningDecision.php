<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CmsReopeningDecision extends Model
{
    protected $fillable = ['cms_reopening_request_version_id', 'decision_code', 'decided_by', 'decided_at', 'decision_comment', 'override_reason', 'source_terminal_status', 'approved_destination_status', 'previous_active_cycle_number', 'new_active_cycle_number', 'existing_action_plan_retained', 'retained_action_plan_version_id', 'new_action_plan_required', 'assignment_follow_up_required', 'target_date_follow_up_required', 'reopening_effective_date', 'final_snapshot'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'reopening_effective_date' => 'date', 'existing_action_plan_retained' => 'boolean', 'new_action_plan_required' => 'boolean', 'assignment_follow_up_required' => 'boolean', 'target_date_follow_up_required' => 'boolean', 'final_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Reopening decisions are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Reopening decisions cannot be deleted.'));
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CmsReopeningRequestVersion::class, 'cms_reopening_request_version_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function retainedActionPlanVersion(): BelongsTo
    {
        return $this->belongsTo(CmsActionPlanVersion::class, 'retained_action_plan_version_id');
    }
}
