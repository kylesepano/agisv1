<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Immutable private export generated from a Core report snapshot. */
class CoreReportExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'core_report_run_id', 'format', 'version_number', 'file_name', 'storage_path',
        'mime_type', 'file_size', 'checksum_sha256', 'generated_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'file_size' => 'integer', 'generated_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Core report exports are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Core report exports cannot be deleted.'));
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(CoreReportRun::class, 'core_report_run_id');
    }
}
