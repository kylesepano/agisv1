<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Immutable ARMIS provider health and cutover-verification snapshot. */
class ArmisProviderMonitoringCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'check_uuid',
        'source_query_version',
        'provider_mode',
        'configured_mode',
        'overall_status',
        'scope_snapshot',
        'checks',
        'provider_snapshot',
        'result_checksum_sha256',
        'performed_by',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'scope_snapshot' => 'array',
            'checks' => 'array',
            'provider_snapshot' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(
            fn (): never => throw new LogicException('ARMIS provider monitoring checks are immutable.'),
        );
        static::deleting(
            fn (): never => throw new LogicException('ARMIS provider monitoring checks cannot be deleted.'),
        );
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by')->withTrashed();
    }

    public function getDisplayCodeAttribute(): string
    {
        return sprintf('ARMIS-MON-%06d', $this->id);
    }
}
