<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CompletionAssessmentItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'blocking_flag' => 'boolean',
            'blocker_accepted' => 'boolean',
            'blocker_accepted_at' => 'datetime',
            'variance_value' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        $guard = function (self $item): void {
            if ($item->assessment()->where('status_code', 'APPROVED')->exists()) {
                throw new LogicException('Items of an approved Completion Assessment are immutable.');
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(CompletionAssessment::class, 'completion_assessment_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id')->withTrashed();
    }
}
