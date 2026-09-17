<?php

namespace App\Models;

use App\Services\AemsAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a checksum-verified evidence record pinned to an immutable document version.
 */
class AuditEvidence extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['DRAFT', 'VERIFIED', 'LOCKED', 'VOIDED'];
    public const OUTCOMES = ['REGISTERED', 'FOR_ASSESSMENT', 'ACCEPTED', 'LIMITED', 'ADDITIONAL_REQUIRED', 'REJECTED', 'SUPERSEDED', 'DUPLICATE', 'VOIDED'];

    protected $table = 'audit_evidence';

    protected $fillable = [
        'evidence_family_uuid',
        'version_number',
        'supersedes_evidence_id',
        'is_current_revision',
        'audit_engagement_id',
        'evidence_code',
        'title',
        'evidence_category_id',
        'evidence_source_type_id',
        'source_description',
        'date_obtained',
        'custodian_name',
        'custodian_office_id',
        'confidentiality_level_id',
        'document_version_id',
        'checksum_sha256',
        'status',
        'assessment_required',
        'uploaded_by',
        'verified_by',
        'verified_at',
        'locked_at',
        'voided_by',
        'voided_at',
        'void_reason',
        'lock_version', 'outcome', 'acquisition_method', 'acquisition_form',
        'planning_objective_id', 'risk_matrix_item_id', 'control_reference',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'is_current_revision' => 'boolean',
            'date_obtained' => 'date',
            'verified_at' => 'datetime',
            'locked_at' => 'datetime',
            'voided_at' => 'datetime',
            'lock_version' => 'integer',
            'assessment_required' => 'boolean',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    /**
     * Restricts evidence discovery to engagements visible to the current actor.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'engagement',
            fn (Builder $engagement): Builder => app(AemsAccessService::class)
                ->visibleEngagements($engagement, $user),
        );
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by')->withTrashed();
    }

    public function confidentialityLevel(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'confidentiality_level_id')->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'evidence_category_id')->withTrashed();
    }

    public function sourceType(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'evidence_source_type_id')->withTrashed();
    }

    public function workingPapers(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkingPaper::class,
            'working_paper_evidence',
            'audit_evidence_id',
            'working_paper_id',
        )->withTimestamps();
    }

    public function workingPaperVersions(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkingPaperVersion::class,
            'working_paper_version_evidence',
            'audit_evidence_id',
            'working_paper_version_id',
        )->withTimestamps();
    }

    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditIssue::class,
            'audit_issue_evidence',
            'audit_evidence_id',
            'audit_issue_id',
        )->withTimestamps();
    }

    public function findings(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditFinding::class,
            'audit_finding_evidence',
            'audit_evidence_id',
            'audit_finding_id',
        )->withTimestamps();
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_evidence_id')->withTrashed();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_evidence_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(AemsEvidenceAssessment::class, 'audit_evidence_id');
    }

    public function currentAssessment(): HasOne
    {
        return $this->hasOne(AemsEvidenceAssessment::class, 'audit_evidence_id')
            ->where('is_current_revision', true)
            ->latestOfMany('version_number');
    }

    public function planningObjective(): BelongsTo { return $this->belongsTo(AemsPlanningObjective::class, 'planning_objective_id'); }
    public function riskMatrixItem(): BelongsTo { return $this->belongsTo(AemsRiskMatrixItem::class, 'risk_matrix_item_id'); }
    public function reportVersions(): BelongsToMany
    {
        return $this->belongsToMany(AuditReportVersion::class, 'aems_evidence_report_links', 'audit_evidence_id', 'audit_report_version_id')
            ->withPivot(['sequence_number', 'link_reason', 'linked_by'])->withTimestamps();
    }
}
