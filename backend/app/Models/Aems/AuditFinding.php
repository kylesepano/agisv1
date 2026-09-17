<?php

namespace App\Models;

use App\Services\AemsAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Represents one revision of a supported audit finding and its dialogue and recommendations.
 */
class AuditFinding extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'DRAFT',
        'PENDING_REVIEW',
        'VALIDATED',
        'COMMUNICATED',
        'AWAITING_MANAGEMENT_RESPONSE',
        'UNDER_DIALOGUE',
        'FINALIZED',
        'WITHDRAWN',
        'SUPERSEDED',
    ];

    public const REVISION_TYPES = ['ORIGINAL', 'CORRECTION', 'AMENDMENT', 'SUPERSESSION', 'WITHDRAWAL'];

    public const DIRECT_CREATION_REASONS = [
        'URGENT_OR_MATERIAL_RISK',
        'FORMAL_DIRECTIVE_OR_LEGAL_REQUIREMENT',
        'CROSS_CUTTING_OR_SYSTEMIC_MATTER',
    ];

    /** Internal guard used only by the revision service when closing a prior revision. */
    protected static bool $allowRevisionTransition = false;

    protected $fillable = [
        'finding_family_uuid',
        'revision_number',
        'revision_type',
        'revision_reason',
        'revision_snapshot',
        'supersedes_finding_id',
        'is_current_revision',
        'audit_engagement_id',
        'source_issue_id',
        'direct_creation_reason',
        'direct_creation_authority',
        'direct_creation_by',
        'direct_creation_at',
        'finding_code',
        'title',
        'criteria',
        'condition',
        'cause',
        'effect',
        'conclusion',
        'significance_classification',
        'effect_classification',
        'no_recommendation_reason',
        'risk_rating_id',
        'responsible_office_id',
        'status',
        'authored_by',
        'reviewer_id',
        'submitted_at',
        'validated_at',
        'validated_by',
        'communicated_at',
        'communicated_by',
        'management_response_due_date',
        'communicated_snapshot',
        'non_response_reason',
        'non_response_recorded_at',
        'non_response_recorded_by',
        'finalized_at',
        'finalized_by',
        'finalized_snapshot',
        'withdrawn_at',
        'withdrawn_by',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'is_current_revision' => 'boolean',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'communicated_at' => 'datetime',
            'management_response_due_date' => 'date',
            'communicated_snapshot' => 'array',
            'non_response_recorded_at' => 'datetime',
            'finalized_at' => 'datetime',
            'finalized_snapshot' => 'array',
            'revision_snapshot' => 'array',
            'direct_creation_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $finding): void {
            if (! self::$allowRevisionTransition && (in_array($finding->getOriginal('status'), ['FINALIZED', 'WITHDRAWN', 'SUPERSEDED'], true)
                || ! $finding->getOriginal('is_current_revision'))) {
                throw new LogicException('Finalized or terminal findings are immutable.');
            }
        });
        static::deleting(function (self $finding): void {
            if (in_array($finding->status, ['FINALIZED', 'WITHDRAWN', 'SUPERSEDED'], true)) {
                throw new LogicException('Finalized findings cannot be deleted.');
            }
        });
    }

    public static function allowRevisionTransition(callable $callback): mixed
    {
        self::$allowRevisionTransition = true;
        try {
            return $callback();
        } finally {
            self::$allowRevisionTransition = false;
        }
    }

    /**
     * Limits findings to assigned auditors or the responsible auditee office.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return app(AemsAccessService::class)->visibleFindings($query, $user);
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function sourceIssue(): BelongsTo
    {
        return $this->belongsTo(AuditIssue::class, 'source_issue_id')->withTrashed();
    }

    public function directCreator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'direct_creation_by')->withTrashed();
    }

    public function responsibleOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'responsible_office_id')->withTrashed();
    }

    public function riskRating(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'risk_rating_id')->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by')->withTrashed();
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by')->withTrashed();
    }

    public function communicator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'communicated_by')->withTrashed();
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by')->withTrashed();
    }

    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by')->withTrashed();
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(AuditRecommendation::class, 'audit_finding_id');
    }

    public function managementResponses(): HasMany
    {
        return $this->hasMany(ManagementResponse::class, 'audit_finding_id')
            ->orderBy('version_number');
    }

    public function dueProcess(): HasMany
    {
        return $this->hasMany(AemsDialogueDueProcess::class, 'audit_finding_id')->orderBy('recorded_at');
    }

    public function transmittals(): HasMany
    {
        return $this->hasMany(AemsFindingTransmittal::class, 'audit_finding_id')->orderBy('sent_at');
    }

    public function workingPaperVersions(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkingPaperVersion::class,
            'audit_finding_working_paper',
            'audit_finding_id',
            'working_paper_version_id',
        )->withTimestamps();
    }

    public function evidence(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditEvidence::class,
            'audit_finding_evidence',
            'audit_finding_id',
            'audit_evidence_id',
        )->withTimestamps();
    }

    public function exitConferences(): BelongsToMany
    {
        return $this->belongsToMany(
            ExitConference::class,
            'exit_conference_findings',
            'audit_finding_id',
            'exit_conference_id',
        )->withPivot([
            'sequence_number',
            'discussion_status',
            'agreement_status',
            'discussion_notes',
            'agreement_details',
            'disagreement_details',
            'revised_target_date',
        ])->withTimestamps();
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_finding_id')->withTrashed();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'finding_family_uuid', 'finding_family_uuid')
            ->orderBy('revision_number');
    }

    public function fieldworkRecords(): BelongsToMany
    {
        return $this->belongsToMany(
            AemsFieldworkRecord::class,
            'audit_finding_fieldwork_record',
            'audit_finding_id',
            'fieldwork_record_id',
        )->withPivot(['fieldwork_record_version_id'])->withTimestamps();
    }

    public function fieldworkRecordVersions(): BelongsToMany
    {
        return $this->belongsToMany(
            AemsFieldworkRecordVersion::class,
            'audit_finding_fieldwork_record',
            'audit_finding_id',
            'fieldwork_record_version_id',
        )->withPivot(['fieldwork_record_id'])->withTimestamps();
    }

    /**
     * Program procedures whose approved criteria and execution support the
     * finding. This is separate from the fieldwork pivot so the criteria
     * chain remains explicit even when a finding is created from a direct
     * authority or a working-paper reference.
     */
    public function procedures(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditProgramProcedure::class,
            'audit_finding_procedure',
            'audit_finding_id',
            'audit_program_procedure_id',
        )->withPivot(['criteria_reference', 'traceability_note', 'linked_by'])->withTimestamps();
    }
}
