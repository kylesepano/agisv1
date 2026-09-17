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
 * Represents the complete AEMS audit aggregate and its preserved IAP or special-authority source.
 */
class AuditEngagement extends Model
{
    use HasFactory, SoftDeletes;

    public const SOURCE_TYPES = ['PLANNED', 'SPECIAL'];

    public const PHASES = [
        'FOUNDATION',
        'PLANNING',
        'EXECUTION',
        'ISSUES_AFR',
        'CONFERENCES',
        'REPORTING',
        'COMPLETION_TRANSFER',
        'CLOSURE',
    ];

    public const ADMINISTRATIVE_STATUSES = [
        'DRAFT',
        'ACTIVE',
        'RETURNED',
        'ISSUED',
        'SUSPENDED',
        'CANCELLED',
        'CLOSED',
        'ARCHIVED',
    ];

    public const STATUSES = [
        'DRAFT',
        'AUTHORIZATION_PREPARATION',
        'RETURNED_FOR_REVISION',
        'AUTHORIZED',
        'ENGAGEMENT_PLANNING',
        'ENTRY_CONFERENCE',
        'FIELDWORK',
        'FINDINGS_COMMUNICATION',
        'EXIT_CONFERENCE',
        'REPORTING',
        'ISSUED',
        'CLOSURE_REVIEW',
        'COMPLETED',
        'CLOSED',
        'SUSPENDED',
        'CANCELLED',
    ];

    protected $fillable = [
        'engagement_code',
        'title',
        'source_type',
        'iap_plan_engagement_id',
        'iap_plan_id',
        'iap_prioritization_item_id',
        'iap_risk_assessment_id',
        'iap_risk_source_type',
        'iap_legacy_risk_assessment_id',
        'iap_audit_universe_item_id',
        'source_snapshot',
        'engagement_office_id',
        'special_authority_reference',
        'special_authority_type_code',
        'special_authority_class',
        'special_authority_date',
        'special_authority_approved_by',
        'special_authority_document_version_id',
        'audit_type_id',
        'engagement_approach_id',
        'background',
        'objectives',
        'scope',
        'scope_boundaries',
        'scope_limitations',
        'scope_source_variance',
        'exclusions',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'expected_report_date',
        'planned_person_days',
        'actual_person_days',
        'status',
        'phase',
        'administrative_status',
        'returned_from_status',
        'return_to_status',
        'suspended_from_status',
        'status_reason',
        'suspension_metadata',
        'cancellation_metadata',
        'transitioned_by',
        'transitioned_at',
        'created_by',
        'updated_by',
        'cancelled_by',
        'cancelled_at',
        'closed_by',
        'closed_at',
        'reopen_revision_number',
        'current_reopen_request_id',
        'reopened_by',
        'reopened_at',
        'lock_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'source_snapshot' => 'array',
            'scope_source_variance' => 'array',
            'suspension_metadata' => 'array',
            'cancellation_metadata' => 'array',
            'special_authority_date' => 'date',
            'planned_start_date' => 'date',
            'planned_end_date' => 'date',
            'actual_start_date' => 'date',
            'actual_end_date' => 'date',
            'expected_report_date' => 'date',
            'planned_person_days' => 'decimal:2',
            'actual_person_days' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'closed_at' => 'datetime',
            'transitioned_at' => 'datetime',
            'lock_version' => 'integer',
            'reopen_revision_number' => 'integer',
            'reopened_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Applies the central AEMS assignment/global-scope rule to engagement queries.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return app(AemsAccessService::class)->visibleEngagements($query, $user);
    }

    /**
     * Restrict engagement queries used for resource allocation conflicts to
     * engagements that can still consume team capacity. Soft-deleted rows are
     * excluded by the model's default scope; lifecycle flags are included here
     * because cancellation and administrative archiving preserve the row for
     * audit/history purposes.
     */
    public function scopeActiveForResourceConflicts(Builder $query): Builder
    {
        return $query
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereNotIn('status', ['CANCELLED', 'CLOSED'])
            ->whereNotIn('administrative_status', ['CANCELLED', 'CLOSED', 'ARCHIVED']);
    }

    public function sourcePlanEngagement(): BelongsTo
    {
        return $this->belongsTo(IapPlanEngagement::class, 'iap_plan_engagement_id')->withTrashed();
    }

    public function sourcePlan(): BelongsTo
    {
        return $this->belongsTo(InternalAuditPlan::class, 'iap_plan_id')->withTrashed();
    }

    public function sourcePrioritizationItem(): BelongsTo
    {
        return $this->belongsTo(IapPrioritizationItem::class, 'iap_prioritization_item_id');
    }

    public function sourceRiskAssessment(): BelongsTo
    {
        return $this->belongsTo(IapUniverseRiskAssessment::class, 'iap_risk_assessment_id')
            ->withTrashed();
    }

    public function sourceLegacyRiskAssessment(): BelongsTo
    {
        return $this->belongsTo(IapRiskAssessment::class, 'iap_legacy_risk_assessment_id')
            ->withTrashed();
    }

    public function sourceAuditUniverseItem(): BelongsTo
    {
        return $this->belongsTo(IapAuditUniverseItem::class, 'iap_audit_universe_item_id')
            ->withTrashed();
    }

    public function auditType(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'audit_type_id')->withTrashed();
    }

    public function engagementApproach(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'engagement_approach_id')->withTrashed();
    }

    public function specialAuthorityApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'special_authority_approved_by')->withTrashed();
    }

    public function specialAuthorityDocumentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'special_authority_document_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function offices(): BelongsToMany
    {
        return $this->belongsToMany(
            Office::class,
            'audit_engagement_offices',
            'audit_engagement_id',
            'office_id',
        )->withPivot('is_primary')->withTimestamps();
    }

    public function engagementOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'engagement_office_id')->withTrashed();
    }

    public function scopeBackfillReview(): HasOne
    {
        return $this->hasOne(AemsEngagementScopeBackfillReview::class, 'audit_engagement_id');
    }

    /** @return array{phase: string, administrative_status: string} */
    public static function lifecycleProjectionForStatus(
        string $status,
        ?string $suspendedFromStatus = null,
    ): array {
        $phaseStatus = $status === 'SUSPENDED'
            ? ($suspendedFromStatus ?: 'DRAFT')
            : $status;
        $phase = match ($phaseStatus) {
            'ENGAGEMENT_PLANNING' => 'PLANNING',
            'FIELDWORK' => 'EXECUTION',
            'FINDINGS_COMMUNICATION' => 'ISSUES_AFR',
            'ENTRY_CONFERENCE' => 'CONFERENCES',
            'EXIT_CONFERENCE' => 'CONFERENCES',
            'REPORTING', 'ISSUED' => 'REPORTING',
            'CLOSURE_REVIEW' => 'COMPLETION_TRANSFER',
            'COMPLETED' => 'COMPLETION_TRANSFER',
            'CLOSED', 'CANCELLED' => 'CLOSURE',
            default => 'FOUNDATION',
        };
        $administrativeStatus = match ($status) {
            'DRAFT' => 'DRAFT',
            'RETURNED_FOR_REVISION' => 'RETURNED',
            'ISSUED' => 'ISSUED',
            'COMPLETED' => 'ACTIVE',
            'SUSPENDED' => 'SUSPENDED',
            'CANCELLED' => 'CANCELLED',
            'CLOSED' => 'CLOSED',
            default => 'ACTIVE',
        };

        return [
            'phase' => $phase,
            'administrative_status' => $administrativeStatus,
        ];
    }

    public function auditAreas(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditArea::class,
            'audit_engagement_audit_areas',
            'audit_engagement_id',
            'audit_area_id',
        )->withPivot('coverage_metadata')->withTimestamps();
    }

    public function auditFocuses(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditFocus::class,
            'audit_engagement_audit_focuses',
            'audit_engagement_id',
            'audit_focus_id',
        )->withPivot('coverage_metadata')->withTimestamps();
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(EngagementTeam::class, 'audit_engagement_id');
    }

    public function teamHistory(): HasMany
    {
        return $this->hasMany(EngagementTeamHistory::class, 'audit_engagement_id')
            ->orderBy('created_at');
    }

    public function teamAmendments(): HasMany
    {
        return $this->hasMany(AemsTeamAmendment::class, 'audit_engagement_id')->orderByDesc('created_at');
    }

    public function teamAccessHistory(): HasMany
    {
        return $this->hasMany(AemsTeamAccessHistory::class, 'audit_engagement_id')->orderByDesc('created_at');
    }

    public function engagementOrder(): HasOne
    {
        return $this->hasOne(AuditEngagementOrder::class, 'audit_engagement_id')
            ->where('is_active', true);
    }

    public function engagementPlan(): HasOne
    {
        return $this->hasOne(AuditEngagementPlan::class, 'audit_engagement_id');
    }

    public function planningPackage(): HasOne
    {
        return $this->hasOne(AemsPlanningPackage::class, 'audit_engagement_id');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(AuditProgram::class, 'audit_engagement_id');
    }

    public function workingPapers(): HasMany
    {
        return $this->hasMany(WorkingPaper::class, 'audit_engagement_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(AuditEvidence::class, 'audit_engagement_id');
    }

    public function evidenceRequests(): HasMany
    {
        return $this->hasMany(AemsEvidenceRequest::class, 'audit_engagement_id');
    }

    public function evidenceAssessments(): HasMany
    {
        return $this->hasMany(AemsEvidenceAssessment::class, 'audit_engagement_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(AuditIssue::class, 'audit_engagement_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class, 'audit_engagement_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(AemsEngagementTask::class, 'audit_engagement_id');
    }

    public function reviewNotes(): HasMany
    {
        return $this->hasMany(AemsReviewNote::class, 'audit_engagement_id');
    }

    public function dueProcessExchanges(): HasMany
    {
        return $this->hasMany(AemsDialogueDueProcess::class, 'audit_engagement_id');
    }

    public function escalationCandidates(): HasMany
    {
        return $this->hasMany(AemsEscalationCandidate::class, 'audit_engagement_id');
    }

    public function exitConferences(): HasMany
    {
        return $this->hasMany(ExitConference::class, 'audit_engagement_id');
    }

    public function entryConference(): HasOne
    {
        return $this->hasOne(EntryConference::class, 'audit_engagement_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(AuditReport::class, 'audit_engagement_id');
    }

    public function completionAssessments(): HasMany
    {
        return $this->hasMany(CompletionAssessment::class, 'audit_engagement_id');
    }

    public function currentCompletionAssessment(): HasOne
    {
        return $this->hasOne(CompletionAssessment::class, 'audit_engagement_id')
            ->where('is_current_revision', true);
    }

    public function closure(): HasOne
    {
        return $this->hasOne(EngagementClosure::class, 'audit_engagement_id')
            ->where('is_current_revision', true);
    }

    public function closures(): HasMany
    {
        return $this->hasMany(EngagementClosure::class, 'audit_engagement_id')
            ->orderBy('revision_number');
    }

    public function documentIndexItems(): HasMany
    {
        return $this->hasMany(EngagementDocumentIndexItem::class, 'audit_engagement_id');
    }

    public function retentionRecord(): HasOne
    {
        return $this->hasOne(EngagementRetentionRecord::class, 'audit_engagement_id');
    }

    public function lessonsLearned(): HasMany
    {
        return $this->hasMany(EngagementLessonLearned::class, 'audit_engagement_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(AemsEngagementMilestone::class, 'audit_engagement_id')
            ->orderBy('due_date')->orderBy('id');
    }

    public function recordDispositionActions(): HasMany
    {
        return $this->hasMany(AemsRecordDispositionAction::class, 'audit_engagement_id')
            ->orderByDesc('occurred_at');
    }

    public function reopenRequests(): HasMany
    {
        return $this->hasMany(EngagementReopenRequest::class, 'audit_engagement_id');
    }

    public function completionTransferManifests(): HasMany
    {
        return $this->hasMany(AemsCompletionTransferManifest::class, 'audit_engagement_id');
    }

    public function effortReconciliations(): HasMany
    {
        return $this->hasMany(AemsEffortReconciliation::class, 'audit_engagement_id');
    }

    public function currentReopenRequest(): BelongsTo
    {
        return $this->belongsTo(EngagementReopenRequest::class, 'current_reopen_request_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EngagementEvent::class, 'audit_engagement_id')
            ->orderBy('created_at');
    }
}
