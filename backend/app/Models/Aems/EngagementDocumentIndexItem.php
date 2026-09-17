<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class EngagementDocumentIndexItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'included_flag' => 'boolean',
            'document_date' => 'date',
            'indexed_at' => 'datetime',
            'sequence_no' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $guard = function (self $item): void {
            if ($item->closure?->document_index_locked_at) {
                throw new LogicException('The final document index is locked.');
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id');
    }

    public function closure(): BelongsTo
    {
        return $this->belongsTo(EngagementClosure::class, 'engagement_closure_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }
}
