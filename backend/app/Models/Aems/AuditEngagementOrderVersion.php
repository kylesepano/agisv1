<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Represents one immutable content and PDF version of an Audit Engagement Order.
 */
class AuditEngagementOrderVersion extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'audit_engagement_order_id',
        'version_number',
        'authority',
        'objectives',
        'scope',
        'effectivity_date',
        'planned_start_date',
        'planned_end_date',
        'team_snapshot',
        'content_snapshot',
        'document_version_id',
        'checksum_sha256',
        'change_reason',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'effectivity_date' => 'date',
            'planned_start_date' => 'date',
            'planned_end_date' => 'date',
            'team_snapshot' => 'array',
            'content_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('AEO versions are immutable.'));
        static::deleting(fn (): never => throw new LogicException('AEO versions cannot be deleted.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AuditEngagementOrder::class, 'audit_engagement_order_id')->withTrashed();
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
