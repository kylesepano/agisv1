<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsEscalationCandidate extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    public const OPEN = 'OPEN';
    public const ACKNOWLEDGED = 'ACKNOWLEDGED';
    public const DISMISSED = 'DISMISSED';
    public const DRAFT_CREATED = 'DRAFT_CREATED';

    protected $fillable = [
        'cms_recommendation_case_id', 'cms_automation_run_id', 'detection_key',
        'status_code', 'trigger_code', 'severity_code', 'reason', 'detected_at',
        'trigger_snapshot', 'reviewed_by', 'reviewed_at', 'review_note', 'escalation_id',
    ];

    protected function casts(): array
    {
        return ['detected_at' => 'datetime', 'reviewed_at' => 'datetime', 'trigger_snapshot' => 'array'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CmsRecommendationCase::class, 'cms_recommendation_case_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(CmsAutomationRun::class, 'cms_automation_run_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function escalation(): BelongsTo
    {
        return $this->belongsTo(CmsEscalation::class, 'escalation_id');
    }
}
