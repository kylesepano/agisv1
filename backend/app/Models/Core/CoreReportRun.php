<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Immutable, scope-pinned Core administrative report snapshot. */
class CoreReportRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_code', 'report_title', 'source_query_version', 'filters',
        'scope_snapshot', 'result_snapshot', 'row_count', 'result_checksum_sha256',
        'generated_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'scope_snapshot' => 'array',
            'result_snapshot' => 'array',
            'row_count' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Core report runs are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Core report runs cannot be deleted.'));
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by')->withTrashed();
    }

    public function exports(): HasMany
    {
        return $this->hasMany(CoreReportExport::class)->orderBy('format')->orderByDesc('version_number');
    }

    public function getDisplayCodeAttribute(): string
    {
        return sprintf('CORE-RPT-%06d', $this->id);
    }
}
