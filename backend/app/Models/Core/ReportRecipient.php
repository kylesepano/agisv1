<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Represents an internal or external recipient and delivery acknowledgement for a report version.
 */
class ReportRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_report_version_id',
        'user_id',
        'office_id',
        'external_name',
        'external_email',
        'recipient_type',
        'delivery_method',
        'delivery_status',
        'sent_at',
        'delivered_at',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $recipient): void {
            if ($recipient->reportVersion()->where('is_locked', true)->exists()) {
                throw new LogicException('Recipients of an issued report version are immutable.');
            }
        });
        static::deleting(function (self $recipient): void {
            if ($recipient->reportVersion()->where('is_locked', true)->exists()) {
                throw new LogicException('Recipients of an issued report version cannot be deleted.');
            }
        });
    }

    public function reportVersion(): BelongsTo
    {
        return $this->belongsTo(AuditReportVersion::class, 'audit_report_version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class)->withTrashed();
    }
}
