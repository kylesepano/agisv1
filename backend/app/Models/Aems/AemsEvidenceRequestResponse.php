<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only auditee submission against an Evidence Request. */
class AemsEvidenceRequestResponse extends Model
{
    use HasFactory;

    protected $table = 'aems_evidence_request_responses';

    protected $fillable = [
        'evidence_request_id',
        'audit_evidence_id',
        'document_version_id',
        'submitted_by',
        'submitted_at',
        'response_note',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Evidence Request responses are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Evidence Request responses cannot be deleted.'));
    }

    public function request(): BelongsTo { return $this->belongsTo(AemsEvidenceRequest::class, 'evidence_request_id'); }
    public function evidence(): BelongsTo { return $this->belongsTo(AuditEvidence::class, 'audit_evidence_id')->withTrashed(); }
    public function documentVersion(): BelongsTo { return $this->belongsTo(DocumentVersion::class); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by')->withTrashed(); }
}
