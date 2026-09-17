<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AemsReportExport extends Model
{
    protected $guarded = [];
    protected $casts = ['generated_at' => 'datetime', 'file_size' => 'integer'];
    protected static function booted(): void { static::updating(fn (): never => throw new LogicException('Report exports are immutable.')); static::deleting(fn (): never => throw new LogicException('Report exports cannot be deleted.')); }
    public function report(): BelongsTo { return $this->belongsTo(AuditReport::class); }
    public function version(): BelongsTo { return $this->belongsTo(AuditReportVersion::class, 'audit_report_version_id'); }
    public function documentVersion(): BelongsTo { return $this->belongsTo(DocumentVersion::class); }
    public function generator(): BelongsTo { return $this->belongsTo(User::class, 'generated_by')->withTrashed(); }
}
