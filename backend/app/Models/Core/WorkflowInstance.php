<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tracks the current step, state, deadline, and version of one workflow execution.
 */
class WorkflowInstance extends Model
{
    use HasFactory;

    public const STATUSES = ['ACTIVE', 'COMPLETED', 'CANCELLED'];

    protected $fillable = [
        'workflow_definition_id',
        'current_step_id',
        'module_code',
        'subject_type',
        'subject_id',
        'subject_code',
        'subject_label',
        'office_id',
        'status',
        'context',
        'started_by',
        'completed_by',
        'started_at',
        'step_entered_at',
        'due_at',
        'completed_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'context' => 'array',
            'started_at' => 'datetime',
            'step_entered_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'current_step_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class)->withTrashed();
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by')->withTrashed();
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by')->withTrashed();
    }

    public function events(): HasMany
    {
        return $this->hasMany(WorkflowInstanceEvent::class)->orderBy('created_at');
    }
}
