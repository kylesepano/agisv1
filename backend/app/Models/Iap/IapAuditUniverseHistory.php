<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Preserves significant changes and historical audit context for a universe item.
 */
class IapAuditUniverseHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'iap_audit_universe_history';

    protected $fillable = [
        'audit_universe_item_id',
        'audited_on',
        'engagement_reference',
        'title',
        'outcome',
        'report_reference',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return ['audited_on' => 'date'];
    }

    public function auditUniverseItem(): BelongsTo
    {
        return $this->belongsTo(
            IapAuditUniverseItem::class,
            'audit_universe_item_id',
        );
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }
}
