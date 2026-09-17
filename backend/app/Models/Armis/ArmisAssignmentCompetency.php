<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable required-competency snapshot for an assignment revision. */
class ArmisAssignmentCompetency extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id', 'competency_id', 'minimum_proficiency', 'notes',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ArmisEngagementAssignment::class, 'assignment_id');
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'competency_id')->withTrashed();
    }
}
