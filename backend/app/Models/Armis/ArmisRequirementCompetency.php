<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Links a resource requirement to a Core competency catalogue item. */
class ArmisRequirementCompetency extends Model
{
    use HasFactory;

    protected $fillable = ['requirement_id', 'competency_id', 'minimum_resources', 'minimum_proficiency', 'notes'];

    protected function casts(): array
    {
        return ['minimum_resources' => 'integer'];
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ArmisResourceRequirement::class, 'requirement_id');
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'competency_id')->withTrashed();
    }
}
