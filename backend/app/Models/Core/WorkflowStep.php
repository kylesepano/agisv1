<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Defines one ordered workflow state and its responsible role and SLA.
 */
class WorkflowStep extends Model
{
    use HasFactory;

    public const TYPES = ['START', 'INTERMEDIATE', 'END'];

    protected $fillable = [
        'workflow_definition_id',
        'code',
        'name',
        'sequence',
        'step_type',
        'responsible_role_id',
        'sla_hours',
        'instructions',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'sla_hours' => 'integer',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function responsibleRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'responsible_role_id')->withTrashed();
    }

    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'from_step_id')->orderBy('sequence');
    }
}
