<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AemsReportTransmittal extends Model
{
    protected $guarded = [];
    protected $casts = ['sent_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    protected static function booted(): void { static::updating(fn (): never => throw new LogicException('Report transmittals are immutable; append a new record.')); static::deleting(fn (): never => throw new LogicException('Report transmittals cannot be deleted.')); }
    public function report(): BelongsTo { return $this->belongsTo(AuditReport::class); }
    public function version(): BelongsTo { return $this->belongsTo(AuditReportVersion::class, 'audit_report_version_id'); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sent_by')->withTrashed(); }
}
