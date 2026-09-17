<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Pins one received request item to an exact Audit Evidence/Core version. */
class AemsEvidenceRequestEvidence extends Model
{
    use HasFactory;
    protected $table = 'aems_evidence_request_evidence';
    protected $fillable = ['evidence_request_id', 'audit_evidence_id', 'document_version_id', 'received_by', 'received_at', 'receipt_notes', 'receipt_status', 'receipt_outcome', 'received_form', 'acquisition_method'];
    protected function casts(): array { return ['received_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Evidence receipt links are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Evidence receipt links cannot be deleted.'));
    }
    public function request(): BelongsTo { return $this->belongsTo(AemsEvidenceRequest::class, 'evidence_request_id'); }
    public function evidence(): BelongsTo { return $this->belongsTo(AuditEvidence::class, 'audit_evidence_id')->withTrashed(); }
    public function documentVersion(): BelongsTo { return $this->belongsTo(DocumentVersion::class); }
    public function receiver(): BelongsTo { return $this->belongsTo(User::class, 'received_by')->withTrashed(); }
}
