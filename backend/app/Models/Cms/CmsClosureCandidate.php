<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsClosureCandidate extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    public const OPEN = 'OPEN';
    public const ACKNOWLEDGED = 'ACKNOWLEDGED';
    public const DISMISSED = 'DISMISSED';
    public const CONVERTED = 'CONVERTED';

    protected $fillable = [
        'cms_recommendation_case_id', 'cms_automation_run_id', 'detection_key',
        'status_code', 'detected_at', 'readiness_snapshot', 'reviewed_by',
        'reviewed_at', 'review_note', 'closure_request_id',
    ];

    protected function casts(): array
    {
        return ['detected_at' => 'datetime', 'reviewed_at' => 'datetime', 'readiness_snapshot' => 'array'];
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

    public function closureRequest(): BelongsTo
    {
        return $this->belongsTo(CmsClosureRequest::class, 'closure_request_id');
    }
}
