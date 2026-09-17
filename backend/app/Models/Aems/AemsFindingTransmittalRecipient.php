<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Controlled delivery and acknowledgement state for one AFR recipient. */
class AemsFindingTransmittalRecipient extends Model
{
    public const STATUSES = ['PENDING', 'SENT', 'DELIVERED', 'FAILED', 'ACKNOWLEDGED'];

    protected $fillable = [
        'transmittal_id', 'recipient_type', 'recipient_user_id', 'recipient_office_id',
        'recipient_name', 'delivery_status', 'delivered_at', 'acknowledged_at',
        'acknowledged_by', 'acknowledgement_comment', 'delivery_reference', 'lock_version',
    ];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime', 'acknowledged_at' => 'datetime', 'lock_version' => 'integer'];
    }

    public function transmittal(): BelongsTo { return $this->belongsTo(AemsFindingTransmittal::class, 'transmittal_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'recipient_user_id')->withTrashed(); }
    public function office(): BelongsTo { return $this->belongsTo(Office::class, 'recipient_office_id')->withTrashed(); }
    public function acknowledger(): BelongsTo { return $this->belongsTo(User::class, 'acknowledged_by')->withTrashed(); }
    public function events(): HasMany { return $this->hasMany(AemsFindingTransmittalEvent::class, 'recipient_id')->orderBy('recorded_at'); }
}
