<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AemsReportAuthorityDecision extends Model
{
    protected $guarded = [];
    protected $casts = ['decided_at' => 'datetime'];
    protected static function booted(): void { static::updating(fn (): never => throw new LogicException('Report authority decisions are immutable.')); static::deleting(fn (): never => throw new LogicException('Report authority decisions cannot be deleted.')); }
    public function report(): BelongsTo { return $this->belongsTo(AuditReport::class); }
    public function version(): BelongsTo { return $this->belongsTo(AuditReportVersion::class, 'audit_report_version_id'); }
    public function decider(): BelongsTo { return $this->belongsTo(User::class, 'decided_by')->withTrashed(); }
}
