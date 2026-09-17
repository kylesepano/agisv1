<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only recipient delivery or acknowledgement decision for an issued report. */
class AuditReportDistributionDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_report_id', 'audit_report_version_id', 'report_recipient_id',
        'decision_code', 'comment', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Report distribution decisions are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Report distribution decisions cannot be deleted.'));
    }

    public function report(): BelongsTo { return $this->belongsTo(AuditReport::class, 'audit_report_id'); }
    public function version(): BelongsTo { return $this->belongsTo(AuditReportVersion::class, 'audit_report_version_id'); }
    public function recipient(): BelongsTo { return $this->belongsTo(ReportRecipient::class, 'report_recipient_id'); }
    public function decider(): BelongsTo { return $this->belongsTo(User::class, 'decided_by')->withTrashed(); }
}
