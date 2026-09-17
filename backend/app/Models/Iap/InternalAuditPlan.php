<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents one versioned Annual Internal Audit Plan and its approval state.
 */
class InternalAuditPlan extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'DRAFT',
        'PENDING_REVIEW',
        'RETURNED_FOR_REVISION',
        'RESUBMITTED',
        'APPROVED',
        'ACTIVE',
        'COMPLETED',
        'REJECTED',
    ];

    protected $fillable = [
        'plan_code',
        'fiscal_year',
        'planning_period_type_id',
        'prioritization_run_id',
        'planning_period_start',
        'planning_period_end',
        'title',
        'executive_summary',
        'planning_methodology',
        'overall_objective',
        'overall_scope',
        'limitations',
        'status',
        'revision_number',
        'supersedes_plan_id',
        'is_current_revision',
        'prepared_by',
        'coordinator_id',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'activated_at',
        'activated_by',
        'completed_at',
        'completed_by',
        'lock_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'planning_period_start' => 'date',
            'planning_period_end' => 'date',
            'revision_number' => 'integer',
            'is_current_revision' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
            'lock_version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function planningPeriodType(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'planning_period_type_id');
    }

    public function prioritizationRun(): BelongsTo
    {
        return $this->belongsTo(IapPrioritizationRun::class, 'prioritization_run_id')
            ->withTrashed();
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by')->withTrashed();
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by')->withTrashed();
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by')->withTrashed();
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_plan_id')->withTrashed();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_plan_id');
    }

    public function riskAssessments(): HasMany
    {
        return $this->hasMany(IapRiskAssessment::class, 'plan_id');
    }

    public function engagements(): HasMany
    {
        return $this->hasMany(IapPlanEngagement::class, 'plan_id')->orderBy('sequence_number');
    }

    public function workflowEvents(): HasMany
    {
        return $this->hasMany(IapWorkflowEvent::class, 'plan_id')->orderBy('created_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IapComment::class, 'plan_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(IapAttachment::class, 'plan_id');
    }
}
