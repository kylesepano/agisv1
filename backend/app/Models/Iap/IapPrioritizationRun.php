<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Groups a repeatable ranking exercise sourced from a validated risk period.
 */
class IapPrioritizationRun extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'DRAFT',
        'PENDING_REVIEW',
        'RETURNED_FOR_REVISION',
        'RESUBMITTED',
        'FINALIZED',
    ];

    protected $fillable = [
        'run_code',
        'name',
        'risk_period_id',
        'methodology',
        'status',
        'created_by',
        'submitted_at',
        'submitted_by',
        'finalized_at',
        'finalized_by',
        'lock_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'finalized_at' => 'datetime',
            'lock_version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function riskPeriod(): BelongsTo
    {
        return $this->belongsTo(IapRiskPeriod::class, 'risk_period_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(IapPrioritizationItem::class, 'prioritization_run_id')
            ->orderBy('final_rank');
    }

    public function annualPlans(): HasMany
    {
        return $this->hasMany(InternalAuditPlan::class, 'prioritization_run_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(IapPrioritizationEvent::class, 'prioritization_run_id')
            ->orderBy('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by')->withTrashed();
    }
}
