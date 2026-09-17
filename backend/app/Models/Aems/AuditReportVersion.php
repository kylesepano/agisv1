<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Represents one immutable generated draft or final audit-report file and content snapshot.
 */
class AuditReportVersion extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'audit_report_id',
        'version_number',
        'report_stage',
        'content_snapshot',
        'document_version_id',
        'checksum_sha256',
        'pdf_file_name',
        'file_size',
        'is_locked',
        'locked_at',
        'locked_by',
        'change_reason',
        'created_by',
        'created_at',
        'source_interim_report_version_id',
        'interim_treatment',
        'source_manifest',
        'source_manifest_sha256',
        'reproducibility_key',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'content_snapshot' => 'array',
            'file_size' => 'integer',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
            'created_at' => 'datetime',
            'source_manifest' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            $lockFields = ['is_locked', 'locked_at', 'locked_by'];
            if ($version->getOriginal('is_locked')
                || array_diff(array_keys($version->getDirty()), $lockFields)) {
                throw new LogicException('Audit-report version content is immutable.');
            }
        });
        static::deleting(fn (): never => throw new LogicException('Audit-report versions cannot be deleted.'));
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(AuditReport::class, 'audit_report_id')->withTrashed();
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function findings(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditFinding::class,
            'audit_report_findings',
            'audit_report_version_id',
            'audit_finding_id',
        )->withPivot(['sequence_number', 'is_included'])->withTimestamps();
    }

    public function evidence(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditEvidence::class,
            'aems_evidence_report_links',
            'audit_report_version_id',
            'audit_evidence_id',
        )->withPivot(['sequence_number', 'treatment', 'link_reason', 'linked_by'])->withTimestamps();
    }

    public function sourceInterimVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_interim_report_version_id');
    }

    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(AuditIssue::class, 'aems_report_issue_links', 'audit_report_version_id', 'audit_issue_id')
            ->withPivot(['sequence_number', 'treatment', 'link_reason', 'linked_by'])->withTimestamps();
    }

    public function workingPaperVersions(): BelongsToMany
    {
        return $this->belongsToMany(WorkingPaperVersion::class, 'aems_report_working_paper_links', 'audit_report_version_id', 'working_paper_version_id')
            ->withPivot(['sequence_number', 'treatment', 'link_reason', 'linked_by'])->withTimestamps();
    }

    public function authorityDecisions(): HasMany
    {
        return $this->hasMany(AemsReportAuthorityDecision::class, 'audit_report_version_id');
    }

    public function signatories(): HasMany
    {
        return $this->hasMany(AemsReportSignatory::class, 'audit_report_version_id');
    }

    public function transmittals(): HasMany
    {
        return $this->hasMany(AemsReportTransmittal::class, 'audit_report_version_id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(AemsReportExport::class, 'audit_report_version_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ReportRecipient::class);
    }

    public function reviewComments(): HasMany
    {
        return $this->hasMany(AuditReportReviewComment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by')->withTrashed();
    }
}
