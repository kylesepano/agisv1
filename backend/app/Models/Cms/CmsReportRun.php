<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Immutable, scope-pinned CMS report result. */
class CmsReportRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_code',
        'report_title',
        'source_query_version',
        'filters',
        'scope_snapshot',
        'result_snapshot',
        'row_count',
        'result_checksum_sha256',
        'generated_by',
        'generated_at',
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
        static::updating(
            fn (): never => throw new LogicException('CMS report runs are immutable.'),
        );
        static::deleting(
            fn (): never => throw new LogicException('CMS report runs cannot be deleted.'),
        );
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by')->withTrashed();
    }

    public function exports(): HasMany
    {
        return $this->hasMany(CmsReportExport::class, 'cms_report_run_id')
            ->orderBy('format')
            ->orderByDesc('version_number');
    }

    public function getDisplayCodeAttribute(): string
    {
        return sprintf('CMS-RPT-%06d', $this->id);
    }
}
