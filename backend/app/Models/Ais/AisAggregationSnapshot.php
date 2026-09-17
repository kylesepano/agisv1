<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Immutable AIS-owned aggregation snapshot; source module records are never mutated. */
class AisAggregationSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'snapshot_code', 'contract_version', 'source_query_version', 'scope_snapshot',
        'source_versions', 'metrics', 'metrics_checksum_sha256', 'generated_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'scope_snapshot' => 'array',
            'source_versions' => 'array',
            'metrics' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('AIS aggregation snapshots are immutable.'));
        static::deleting(fn (): never => throw new LogicException('AIS aggregation snapshots cannot be deleted.'));
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by')->withTrashed();
    }

    public function getDisplayCodeAttribute(): string
    {
        return $this->snapshot_code ?: sprintf('AIS-SNP-%06d', $this->id);
    }
}
