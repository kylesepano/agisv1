<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AemsEngagementMilestone extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'planned_start_date' => 'date',
            'due_date' => 'date',
            'completed_date' => 'date',
            'required_flag' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $milestone): void {
            if ($milestone->getOriginal('status_code') === 'COMPLETED') {
                throw new LogicException('Completed audit milestones are immutable.');
            }
        });
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id');
    }

    public function responsibleOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'responsible_office_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
