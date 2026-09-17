<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsAutomationAction extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    protected $fillable = [
        'cms_automation_run_id', 'cms_automation_rule_id', 'cms_recommendation_case_id',
        'action_type', 'status_code', 'dedupe_key', 'target_user_id',
        'candidate_type', 'candidate_id', 'notification_id', 'payload',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(CmsAutomationRun::class, 'cms_automation_run_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CmsAutomationRule::class, 'cms_automation_rule_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CmsRecommendationCase::class, 'cms_recommendation_case_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id')->withTrashed();
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(SystemNotification::class, 'notification_id');
    }
}
