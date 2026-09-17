<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Immutable decision to activate ARMIS authority or roll back to IAP. */
class ArmisProviderAuthorityDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'reconciliation_run_id',
        'decision_code',
        'from_mode',
        'to_mode',
        'reason',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(
            fn (): never => throw new LogicException('ARMIS authority decisions are immutable.'),
        );
        static::deleting(
            fn (): never => throw new LogicException('ARMIS authority decisions cannot be deleted.'),
        );
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ArmisProviderReconciliationRun::class, 'reconciliation_run_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }
}
