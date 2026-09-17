<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class EngagementReopenRequest extends Model
{
    public const STATUSES = [
        'DRAFT',
        'PENDING_APPROVAL',
        'APPROVED',
        'IMPLEMENTED',
        'REJECTED',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'implemented_at' => 'datetime',
            'original_closed_snapshot_json' => 'array',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $request): void {
            if (in_array($request->getOriginal('status_code'), ['IMPLEMENTED', 'REJECTED'], true)) {
                throw new LogicException('Completed reopening requests are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Reopening history cannot be deleted.'));
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id');
    }

    public function authorityDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'authority_document_id')->withTrashed();
    }

    public function authorityDocumentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'authority_document_version_id');
    }
}
