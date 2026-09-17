<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Immutable IAP-versus-ARMIS provider reconciliation snapshot. */
class ArmisProviderReconciliationRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_uuid',
        'source_query_version',
        'fiscal_year',
        'provider_mode',
        'status',
        'filters',
        'scope_snapshot',
        'result_snapshot',
        'summary',
        'result_checksum_sha256',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'filters' => 'array',
            'scope_snapshot' => 'array',
            'result_snapshot' => 'array',
            'summary' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(
            fn (): never => throw new LogicException('ARMIS reconciliation runs are immutable.'),
        );
        static::deleting(
            fn (): never => throw new LogicException('ARMIS reconciliation runs cannot be deleted.'),
        );
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by')->withTrashed();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ArmisProviderReconciliationReview::class, 'reconciliation_run_id')
            ->orderByDesc('reviewed_at');
    }

    public function authorityDecisions(): HasMany
    {
        return $this->hasMany(ArmisProviderAuthorityDecision::class, 'reconciliation_run_id')
            ->orderByDesc('decided_at');
    }

    public function getDisplayCodeAttribute(): string
    {
        return sprintf('ARMIS-REC-%06d', $this->id);
    }
}
