<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only signatory matrix entry for one immutable AEO version. */
class AemsAeoSignatory extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_engagement_order_id', 'version_number', 'sequence', 'signatory_role',
        'user_id', 'is_required', 'status', 'signature_method', 'signature_reference',
        'signed_at', 'signed_by', 'remarks', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'sequence' => 'integer',
            'is_required' => 'boolean',
            'signed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $entry): void {
            if ($entry->getOriginal('status') === 'SIGNED') {
                throw new LogicException('Signed AEO matrix entries are immutable.');
            }
        });
        static::deleting(fn (): never => throw new LogicException('AEO matrix entries cannot be deleted.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AuditEngagementOrder::class, 'audit_engagement_order_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by')->withTrashed();
    }
}
