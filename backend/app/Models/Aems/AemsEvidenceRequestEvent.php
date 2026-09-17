<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only request lifecycle/control event for Evidence Requests. */
class AemsEvidenceRequestEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;
    protected $table = 'aems_evidence_request_events';
    protected $fillable = ['evidence_request_id', 'audit_engagement_id', 'event_type', 'from_status', 'to_status', 'actor_id', 'reason', 'metadata', 'version_number', 'created_at'];
    protected function casts(): array { return ['metadata' => 'array', 'created_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Evidence Request lifecycle events are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Evidence Request lifecycle events cannot be deleted.'));
    }
    public function request(): BelongsTo { return $this->belongsTo(AemsEvidenceRequest::class, 'evidence_request_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id')->withTrashed(); }
}
