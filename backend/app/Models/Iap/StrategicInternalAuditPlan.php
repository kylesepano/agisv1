<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a versioned multi-year strategic plan and its immutable approvals.
 */
class StrategicInternalAuditPlan extends Model
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
    ];

    protected $fillable = [
        'plan_code',
        'start_year',
        'end_year',
        'title',
        'strategic_context',
        'vision',
        'mission_alignment',
        'planning_methodology',
        'expected_outcomes',
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
            'start_year' => 'integer',
            'end_year' => 'integer',
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

    public function objectives(): HasMany
    {
        return $this->hasMany(SiapObjective::class, 'strategic_plan_id')
            ->orderBy('display_order');
    }

    public function priorities(): HasMany
    {
        return $this->hasMany(SiapPriority::class, 'strategic_plan_id')
            ->orderBy('display_order');
    }

    public function workflowEvents(): HasMany
    {
        return $this->hasMany(SiapWorkflowEvent::class, 'strategic_plan_id')
            ->orderBy('created_at');
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
}
