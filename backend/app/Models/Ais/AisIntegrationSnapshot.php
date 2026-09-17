<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Immutable AIS-owned record of a cross-module integration health check. */
class AisIntegrationSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'snapshot_code',
        'contract_version',
        'integration_contract_version',
        'status',
        'scope_snapshot',
        'source_statuses',
        'reconciliation',
        'diagnostics',
        'source_contract_hash_sha256',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'scope_snapshot' => 'array',
            'source_statuses' => 'array',
            'reconciliation' => 'array',
            'diagnostics' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('AIS integration snapshots are immutable.'));
        static::deleting(fn (): never => throw new LogicException('AIS integration snapshots cannot be deleted.'));
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by')->withTrashed();
    }

    public function getDisplayCodeAttribute(): string
    {
        return $this->snapshot_code ?: sprintf('AIS-INT-%06d', $this->id);
    }
}
