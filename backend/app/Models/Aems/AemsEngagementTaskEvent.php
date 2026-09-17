<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only versioned task exchange/event. */
class AemsEngagementTaskEvent extends Model
{
    public const CREATED_AT = 'recorded_at';
    public const UPDATED_AT = null;
    protected $table = 'aems_engagement_task_events';
    protected $fillable = ['aems_engagement_task_id', 'audit_engagement_id', 'version_number', 'action', 'from_status', 'to_status', 'content', 'actor_id', 'recorded_at', 'snapshot'];
    protected static function booted(): void { static::updating(fn (): never => throw new LogicException('Task events are immutable.')); static::deleting(fn (): never => throw new LogicException('Task events cannot be deleted.')); }
    protected function casts(): array { return ['version_number' => 'integer', 'recorded_at' => 'datetime', 'snapshot' => 'array']; }
    public function task(): BelongsTo { return $this->belongsTo(AemsEngagementTask::class, 'aems_engagement_task_id'); }
    public function engagement(): BelongsTo { return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed(); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id')->withTrashed(); }
}
