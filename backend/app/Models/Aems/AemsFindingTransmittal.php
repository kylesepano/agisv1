<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Immutable formal AFR transmittal snapshot. */
class AemsFindingTransmittal extends Model
{
    protected $fillable = [
        'audit_engagement_id', 'audit_finding_id', 'transmittal_code',
        'finding_revision_number', 'transmittal_method', 'transmittal_reference',
        'confidentiality', 'sent_by', 'sent_at', 'response_due_date',
        'content_snapshot', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'finding_revision_number' => 'integer',
            'sent_at' => 'datetime',
            'response_due_date' => 'date',
            'content_snapshot' => 'array',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('AFR transmittal snapshots are immutable.'));
        static::deleting(fn (): never => throw new LogicException('AFR transmittal snapshots cannot be deleted.'));
    }

    public function engagement(): BelongsTo { return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed(); }
    public function finding(): BelongsTo { return $this->belongsTo(AuditFinding::class, 'audit_finding_id')->withTrashed(); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sent_by')->withTrashed(); }
    public function recipients(): HasMany { return $this->hasMany(AemsFindingTransmittalRecipient::class, 'transmittal_id')->orderBy('id'); }
    public function events(): HasMany { return $this->hasMany(AemsFindingTransmittalEvent::class, 'transmittal_id')->orderBy('recorded_at'); }
}
