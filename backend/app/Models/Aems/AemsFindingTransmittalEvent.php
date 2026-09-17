<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only AFR delivery/acknowledgement event. */
class AemsFindingTransmittalEvent extends Model
{
    public const CREATED_AT = 'recorded_at';
    public const UPDATED_AT = null;
    protected $fillable = ['transmittal_id', 'recipient_id', 'event_type', 'content', 'actor_id', 'metadata', 'recorded_at'];
    protected $casts = ['metadata' => 'array', 'recorded_at' => 'datetime'];
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Transmittal events are immutable.'));
        static::deleting(fn (): never => throw new \LogicException('Transmittal events cannot be deleted.'));
    }
    public function transmittal(): BelongsTo { return $this->belongsTo(AemsFindingTransmittal::class, 'transmittal_id'); }
    public function recipient(): BelongsTo { return $this->belongsTo(AemsFindingTransmittalRecipient::class, 'recipient_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id')->withTrashed(); }
}
