<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents one reviewable revision of the procedure program for an engagement.
 */
class AuditProgram extends Model
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
        'SUPERSEDED',
    ];

    protected $fillable = [
        'audit_engagement_id',
        'audit_engagement_plan_id',
        'program_code',
        'title',
        'objective',
        'status',
        'revision_number',
        'supersedes_program_id',
        'revision_reason',
        'is_current_revision',
        'prepared_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'activated_at',
        'completed_at',
        'lock_version',
        'is_active',
        'audit_area_id','audit_type_id','audit_period_start','audit_period_end','audit_criteria','risk_statement_set','sampling_approach','planned_working_paper_requirements',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'is_current_revision' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
            'lock_version' => 'integer',
            'is_active' => 'boolean',
            'audit_period_start' => 'date', 'audit_period_end' => 'date', 'risk_statement_set' => 'array', 'planned_working_paper_requirements' => 'array',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function engagementPlan(): BelongsTo
    {
        return $this->belongsTo(AuditEngagementPlan::class, 'audit_engagement_plan_id')->withTrashed();
    }

    public function procedures(): HasMany
    {
        return $this->hasMany(AuditProgramProcedure::class)->orderBy('sequence_number');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_program_id')->withTrashed();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_program_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }
}
