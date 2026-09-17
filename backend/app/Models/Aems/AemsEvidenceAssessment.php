<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Immutable professional assessment snapshot for one exact evidence version. */
class AemsEvidenceAssessment extends Model
{
    use HasFactory;

    public const STATUSES = ['ASSESSED'];

    protected $table = 'aems_evidence_assessments';
    protected $fillable = [
        'assessment_family_uuid', 'audit_engagement_id', 'audit_evidence_id',
        'evidence_request_id', 'document_version_id', 'version_number',
        'supersedes_assessment_id', 'is_current_revision', 'status',
        'sufficiency', 'appropriateness', 'relevance', 'reliability',
        'competence', 'accuracy', 'completeness', 'corroboration',
        'contradiction', 'authenticity', 'integrity', 'confidentiality',
        'is_restricted', 'access_restrictions', 'limitations', 'evidence_gaps',
        'exception_required', 'exception_reason', 'exception_approved_by',
        'exception_approved_at', 'exception_approval_comment', 'assessed_by',
        'assessed_at', 'change_reason', 'lock_version',
    ];
    protected function casts(): array
    {
        return [
            'version_number' => 'integer', 'is_current_revision' => 'boolean',
            'is_restricted' => 'boolean', 'exception_required' => 'boolean',
            'exception_approved_at' => 'datetime', 'assessed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Evidence assessment versions are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Evidence assessment versions cannot be deleted.'));
    }
    public function engagement(): BelongsTo { return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed(); }
    public function evidence(): BelongsTo { return $this->belongsTo(AuditEvidence::class, 'audit_evidence_id')->withTrashed(); }
    public function request(): BelongsTo { return $this->belongsTo(AemsEvidenceRequest::class, 'evidence_request_id')->withTrashed(); }
    public function documentVersion(): BelongsTo { return $this->belongsTo(DocumentVersion::class); }
    public function assessor(): BelongsTo { return $this->belongsTo(User::class, 'assessed_by')->withTrashed(); }
    public function exceptionApprover(): BelongsTo { return $this->belongsTo(User::class, 'exception_approved_by')->withTrashed(); }
    public function supersedes(): BelongsTo { return $this->belongsTo(self::class, 'supersedes_assessment_id')->withTrashed(); }
    public function scopeCurrent($query) { return $query->where('is_current_revision', true); }
}
