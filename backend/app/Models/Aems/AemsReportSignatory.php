<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AemsReportSignatory extends Model
{
    protected $guarded = [];
    protected $casts = ['signed_at' => 'datetime'];
    protected static function booted(): void { static::updating(fn (): never => throw new LogicException('Report signatories are immutable.')); static::deleting(fn (): never => throw new LogicException('Report signatories cannot be deleted.')); }
    public function report(): BelongsTo { return $this->belongsTo(AuditReport::class); }
    public function version(): BelongsTo { return $this->belongsTo(AuditReportVersion::class, 'audit_report_version_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class)->withTrashed(); }
}
