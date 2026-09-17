<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class EngagementRetentionRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'retention_start_date' => 'date',
            'scheduled_disposition_date' => 'date',
            'permanent_flag' => 'boolean',
            'legal_hold_flag' => 'boolean',
            'approved_at' => 'datetime',
            'archived_at' => 'datetime',
            'legal_hold_released_at' => 'datetime',
            'destruction_reviewed_at' => 'datetime',
            'disposition_recorded_at' => 'datetime',
            'approved_snapshot_json' => 'array',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            if ($record->getOriginal('approved_at') !== null) {
                throw new LogicException('Approved retention metadata is immutable.');
            }
        });
        static::deleting(function (self $record): void {
            if ($record->approved_at) {
                throw new LogicException('Approved retention metadata cannot be deleted.');
            }
        });
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id');
    }

    public function closure(): BelongsTo
    {
        return $this->belongsTo(EngagementClosure::class, 'engagement_closure_id');
    }

    public function dispositionActions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AemsRecordDispositionAction::class, 'engagement_retention_record_id')
            ->orderByDesc('occurred_at');
    }
}
