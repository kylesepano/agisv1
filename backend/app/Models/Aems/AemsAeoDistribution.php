<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Controlled AEO transmittal and acknowledgement record. */
class AemsAeoDistribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_engagement_order_id', 'version_number', 'recipient_type',
        'recipient_user_id', 'recipient_office_id', 'recipient_name',
        'transmittal_method', 'transmittal_reference', 'status', 'sent_at',
        'acknowledged_at', 'acknowledged_by', 'acknowledgement_note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AuditEngagementOrder::class, 'audit_engagement_order_id')->withTrashed();
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id')->withTrashed();
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'recipient_office_id')->withTrashed();
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by')->withTrashed();
    }
}
