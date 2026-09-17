<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Defines an allowed action between two steps in a workflow definition.
 */
class WorkflowTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_definition_id',
        'from_step_id',
        'to_step_id',
        'code',
        'name',
        'sequence',
        'actor_role_id',
        'required_permission_id',
        'requires_comment',
        'enforce_separation_of_duties',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'requires_comment' => 'boolean',
            'enforce_separation_of_duties' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function fromStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'from_step_id');
    }

    public function toStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'to_step_id');
    }

    public function actorRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'actor_role_id')->withTrashed();
    }

    public function requiredPermission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'required_permission_id');
    }
}
