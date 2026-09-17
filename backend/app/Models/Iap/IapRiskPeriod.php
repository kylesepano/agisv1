<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a controlled scoring cycle and its validation or locking status.
 */
class IapRiskPeriod extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'DRAFT',
        'OPEN',
        'PENDING_VALIDATION',
        'RETURNED_FOR_REVISION',
        'RESUBMITTED',
        'VALIDATED',
        'LOCKED',
    ];

    protected $fillable = [
        'period_code',
        'name',
        'assessment_year',
        'start_date',
        'end_date',
        'instructions',
        'status',
        'created_by',
        'opened_at',
        'opened_by',
        'submitted_at',
        'submitted_by',
        'validated_at',
        'validated_by',
        'locked_at',
        'locked_by',
        'lock_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'assessment_year' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'opened_at' => 'datetime',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'locked_at' => 'datetime',
            'lock_version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(IapRiskPeriodCriterion::class, 'period_id')
            ->orderBy('display_order');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(IapUniverseRiskAssessment::class, 'period_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(IapRiskPeriodEvent::class, 'period_id')->orderBy('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by')->withTrashed();
    }
}
