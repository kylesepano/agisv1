<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AemsCompletionTransferManifest extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (self $manifest): void {
            if ($manifest->getOriginal('status') === 'APPROVED') {
                throw new LogicException('Approved CMS transfer manifests are immutable.');
            }
        });
        static::deleting(function (self $manifest): void {
            if ($manifest->status === 'APPROVED') {
                throw new LogicException('Approved CMS transfer manifests cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'manifest_snapshot_json' => 'array',
            'generated_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(AuditReport::class, 'audit_report_id');
    }

    public function reportVersion(): BelongsTo
    {
        return $this->belongsTo(AuditReportVersion::class, 'audit_report_version_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(AemsCompletionTransferException::class, 'manifest_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by')->withTrashed();
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }
}
