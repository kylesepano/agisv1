<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AemsEffortReconciliation extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (self $reconciliation): void {
            if ($reconciliation->getOriginal('status') === 'APPROVED') {
                throw new LogicException('Approved effort reconciliations are immutable.');
            }
        });
        static::deleting(function (self $reconciliation): void {
            if ($reconciliation->status === 'APPROVED') {
                throw new LogicException('Approved effort reconciliations cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'planned_person_days' => 'decimal:2',
            'aems_actual_person_days' => 'decimal:2',
            'provider_actual_person_days' => 'decimal:2',
            'variance_person_days' => 'decimal:2',
            'source_snapshot_json' => 'array',
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }
}
