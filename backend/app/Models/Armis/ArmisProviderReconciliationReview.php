<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Immutable independent assessment of a reconciliation snapshot. */
class ArmisProviderReconciliationReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'reconciliation_run_id',
        'decision',
        'discrepancy_decisions',
        'comment',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'discrepancy_decisions' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(
            fn (): never => throw new LogicException('ARMIS reconciliation reviews are immutable.'),
        );
        static::deleting(
            fn (): never => throw new LogicException('ARMIS reconciliation reviews cannot be deleted.'),
        );
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ArmisProviderReconciliationRun::class, 'reconciliation_run_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }
}
