<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class EngagementClosureChecklistItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'required_flag' => 'boolean',
            'blocking_flag' => 'boolean',
            'source_snapshot_json' => 'array',
            'verified_at' => 'datetime',
            'display_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $guard = function (self $item): void {
            if ($item->closure()->whereIn('status_code', ['APPROVED', 'CLOSED'])->exists()) {
                throw new LogicException('The approved closure checklist is immutable.');
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    public function closure(): BelongsTo
    {
        return $this->belongsTo(EngagementClosure::class, 'engagement_closure_id');
    }
}
