<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CmsAutomationRuleVersion extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    protected $fillable = [
        'cms_automation_rule_id', 'version_number', 'status_code',
        'configuration', 'created_by', 'effective_from', 'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'effective_from' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('CMS automation rule versions are immutable.'));
        static::deleting(fn (): never => throw new LogicException('CMS automation rule versions cannot be deleted.'));
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CmsAutomationRule::class, 'cms_automation_rule_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function runs(): HasMany
    {
        return $this->hasMany(CmsAutomationRun::class, 'cms_automation_rule_version_id');
    }
}
