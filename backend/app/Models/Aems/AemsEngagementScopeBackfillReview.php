<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable-at-purpose audit record for the G2 legacy office reconciliation. */
class AemsEngagementScopeBackfillReview extends Model
{
    protected $table = 'aems_engagement_scope_backfill_reviews';

    protected $fillable = [
        'audit_engagement_id',
        'office_count',
        'legacy_office_ids',
        'canonical_office_id',
        'resolution_status',
        'resolution_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'legacy_office_ids' => 'array',
            'office_count' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id');
    }

    public function canonicalOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'canonical_office_id')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }
}
