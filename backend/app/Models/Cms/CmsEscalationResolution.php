<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CmsEscalationResolution extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Escalation resolutions are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Escalation resolutions cannot be deleted.'));
    }

    public function escalation(): BelongsTo
    {
        return $this->belongsTo(CmsEscalation::class, 'cms_escalation_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by')->withTrashed();
    }
}
