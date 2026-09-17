<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class EngagementClosureEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshot_json' => 'array',
            'request_metadata_json' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Closure events are immutable.'));
        static::deleting(fn () => throw new LogicException('Closure events cannot be deleted.'));
    }

    public function closure(): BelongsTo
    {
        return $this->belongsTo(EngagementClosure::class, 'engagement_closure_id');
    }
}
