<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsAutomationRun extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    protected $fillable = [
        'cms_automation_rule_id', 'cms_automation_rule_version_id', 'run_key',
        'status_code', 'started_at', 'finished_at', 'scanned_count',
        'created_count', 'skipped_count', 'error_count', 'error_summary', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'finished_at' => 'datetime',
            'metadata' => 'array',
            'scanned_count' => 'integer', 'created_count' => 'integer',
            'skipped_count' => 'integer', 'error_count' => 'integer',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CmsAutomationRule::class, 'cms_automation_rule_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CmsAutomationRuleVersion::class, 'cms_automation_rule_version_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CmsAutomationAction::class, 'cms_automation_run_id');
    }
}
