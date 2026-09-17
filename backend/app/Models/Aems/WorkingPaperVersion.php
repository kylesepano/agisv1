<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

/**
 * Represents one immutable objective, procedure, result, and conclusion version of a working paper.
 */
class WorkingPaperVersion extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'working_paper_id',
        'version_number',
        'objective',
        'procedure_performed',
        'population_description',
        'sample_description',
        'result',
        'conclusion',
        'no_evidence_reason',
        'cross_references',
        'document_version_id',
        'checksum_sha256',
        'change_reason',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'cross_references' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Working-paper versions are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Working-paper versions cannot be deleted.'));
    }

    public function workingPaper(): BelongsTo
    {
        return $this->belongsTo(WorkingPaper::class)->withTrashed();
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function evidence(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditEvidence::class,
            'working_paper_version_evidence',
            'working_paper_version_id',
            'audit_evidence_id',
        )->withTimestamps();
    }

    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditIssue::class,
            'audit_issue_working_paper',
            'working_paper_version_id',
            'audit_issue_id',
        )->withTimestamps();
    }

    public function findings(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditFinding::class,
            'audit_finding_working_paper',
            'working_paper_version_id',
            'audit_finding_id',
        )->withTimestamps();
    }
}
