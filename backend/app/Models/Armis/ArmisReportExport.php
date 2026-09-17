<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Immutable private CSV/PDF artifact generated from one ARMIS report run. */
class ArmisReportExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'armis_report_run_id',
        'format',
        'version_number',
        'file_name',
        'storage_path',
        'mime_type',
        'file_size',
        'checksum_sha256',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'file_size' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(
            fn (): never => throw new LogicException('ARMIS report exports are immutable.'),
        );
        static::deleting(
            fn (): never => throw new LogicException('ARMIS report exports cannot be deleted.'),
        );
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ArmisReportRun::class, 'armis_report_run_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by')->withTrashed();
    }
}
