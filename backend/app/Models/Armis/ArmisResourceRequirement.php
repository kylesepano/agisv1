<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Represents a normalized demand record that can be linked to IAP or AEMS. */
class ArmisResourceRequirement extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'RETURNED', 'APPROVED', 'LOCKED'];

    protected $fillable = [
        'source_module', 'source_type', 'source_id', 'office_id', 'fiscal_year', 'title',
        'required_person_days', 'status', 'notes', 'created_by', 'submitted_by',
        'submitted_at', 'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer', 'required_person_days' => 'decimal:2',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function competencies(): HasMany
    {
        return $this->hasMany(ArmisRequirementCompetency::class, 'requirement_id');
    }

    public function workloadAllocations(): HasMany
    {
        return $this->hasMany(ArmisWorkloadAllocation::class, 'requirement_id');
    }
}
