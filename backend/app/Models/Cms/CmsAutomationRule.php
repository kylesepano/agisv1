<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CmsAutomationRule extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    public const TYPE_REMINDER = 'REMINDER';

    public const TYPE_CLOSURE_READINESS = 'CLOSURE_READINESS';

    public const TYPE_ESCALATION_CANDIDATE = 'ESCALATION_CANDIDATE';

    public const ACTIVE = 'ACTIVE';

    public const INACTIVE = 'INACTIVE';

    protected $fillable = [
        'rule_code', 'name', 'description', 'rule_type', 'status_code',
        'schedule_code', 'configuration', 'created_by', 'updated_by',
        'current_version_id', 'lock_version',
    ];

    protected function casts(): array
    {
        return ['configuration' => 'array', 'lock_version' => 'integer'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CmsAutomationRuleVersion::class, 'cms_automation_rule_id')
            ->orderByDesc('version_number');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(CmsAutomationRuleVersion::class, 'id', 'current_version_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(CmsAutomationRun::class, 'cms_automation_rule_id')->latest('started_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
