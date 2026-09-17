<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Pins an exchange to one immutable private Core document version. */
class AemsDialogueAttachment extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'audit_engagement_id',
        'audit_finding_id',
        'management_response_id',
        'auditor_rejoinder_id',
        'attachment_code',
        'caption',
        'document_version_id',
        'uploaded_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Dialogue attachments are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Dialogue attachments cannot be deleted.'));
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(AuditFinding::class, 'audit_finding_id')->withTrashed();
    }

    public function managementResponse(): BelongsTo
    {
        return $this->belongsTo(ManagementResponse::class)->withTrashed();
    }

    public function auditorRejoinder(): BelongsTo
    {
        return $this->belongsTo(AuditorRejoinder::class)->withTrashed();
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}
