<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Preserves one immutable reviewer comment against an exact report version. */
class AuditReportReviewComment extends Model
{
    use HasFactory;

    public const ACTIONS = ['REVIEWED', 'RETURNED', 'APPROVED'];

    protected $fillable = [
        'audit_report_id',
        'audit_report_version_id',
        'review_action',
        'comment',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Report review comments are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Report review comments cannot be deleted.'));
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(AuditReport::class, 'audit_report_id')->withTrashed();
    }

    public function reportVersion(): BelongsTo
    {
        return $this->belongsTo(AuditReportVersion::class, 'audit_report_version_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }
}
