<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents one assigned audit procedure and its completion or waiver disposition.
 */
class AuditProgramProcedure extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['NOT_STARTED', 'IN_PROGRESS', 'COMPLETED', 'WAIVED'];

    public const REVIEWER_RESULTS = [
        'SATISFACTORY',
        'NEEDS_REVISION',
        'NOT_APPLICABLE',
    ];

    protected $fillable = [
        'audit_program_id',
        'procedure_code',
        'sequence_number',
        'objective',
        'procedure_description',
        'expected_evidence',
        'working_paper_reference',
        'assigned_to',
        'target_date',
        'status',
        'reviewer_result',
        'reviewer_comments',
        'reviewed_by',
        'reviewed_at',
        'completed_at',
        'completed_by',
        'waived_at',
        'waived_by',
        'waiver_reason',
        'fieldwork_status',
        'fieldwork_results',
        'fieldwork_conclusion',
        'fieldwork_review_state',
        'related_tasks',
        'related_records',
        'fieldwork_completed_at',
        'fieldwork_completed_by',
        'lock_version',
        'audit_area_id','audit_focus_id','process_flow_id','process_name','audit_method','audit_criteria','planned_person_days','sampling_requirement','planned_working_paper_requirement','risk_statement_ids',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'target_date' => 'date',
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'waived_at' => 'datetime',
            'related_tasks' => 'array',
            'related_records' => 'array',
            'fieldwork_completed_at' => 'datetime',
            'lock_version' => 'integer',
            'planned_person_days' => 'decimal:2', 'sampling_requirement' => 'array', 'planned_working_paper_requirement' => 'array', 'risk_statement_ids' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(AuditProgram::class, 'audit_program_id')->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function workingPapers(): HasMany
    {
        return $this->hasMany(WorkingPaper::class, 'audit_program_procedure_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by')->withTrashed();
    }

    public function waiverApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by')->withTrashed();
    }

    public function fieldworkCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fieldwork_completed_by')->withTrashed();
    }

    public function fieldworkRecords(): HasMany
    {
        return $this->hasMany(AemsFieldworkRecord::class, 'audit_program_procedure_id');
    }

    public function auditArea(): BelongsTo { return $this->belongsTo(AuditArea::class, 'audit_area_id')->withTrashed(); }
    public function auditFocus(): BelongsTo { return $this->belongsTo(AuditFocus::class, 'audit_focus_id')->withTrashed(); }
    public function processFlow(): BelongsTo { return $this->belongsTo(AemsProcessFlowDocument::class, 'process_flow_id'); }

    public function findings(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditFinding::class,
            'audit_finding_procedure',
            'audit_program_procedure_id',
            'audit_finding_id',
        )->withPivot(['criteria_reference', 'traceability_note', 'linked_by'])->withTimestamps();
    }
}
