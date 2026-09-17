<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Immutable private export generated from an AIS report snapshot. */
class AisReportExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'ais_report_run_id', 'format', 'version_number', 'file_name', 'storage_path',
        'mime_type', 'file_size', 'checksum_sha256', 'generated_by', 'generated_at',
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
        static::updating(fn (): never => throw new LogicException('AIS report exports are immutable.'));
        static::deleting(fn (): never => throw new LogicException('AIS report exports cannot be deleted.'));
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AisReportRun::class, 'ais_report_run_id');
    }
}
