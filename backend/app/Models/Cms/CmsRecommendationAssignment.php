<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Historical, non-deletable Compliance Monitor assignment. */
class CmsRecommendationAssignment extends Model
{
    use HasFactory;

    public const ROLE_COMPLIANCE_MONITOR = 'COMPLIANCE_MONITOR';

    protected $fillable = [
        'cms_recommendation_case_id',
        'user_id',
        'assignment_role_code',
        'assignment_reason',
        'assigned_by',
        'assigned_at',
        'effective_from',
        'effective_until',
        'ended_by',
        'ended_at',
        'end_reason',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'ended_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $assignment): void {
            $allowed = [
                'ended_by',
                'ended_at',
                'end_reason',
                'is_current',
                'effective_until',
                'updated_at',
            ];

            if (! $assignment->getOriginal('is_current')
                || $assignment->is_current
                || array_diff(array_keys($assignment->getDirty()), $allowed) !== []) {
                throw new LogicException(
                    'CMS assignment history may only be ended; it cannot be rewritten.',
                );
            }
        });
        static::deleting(
            fn (): never => throw new LogicException('CMS assignments cannot be deleted.'),
        );
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(
            CmsRecommendationCase::class,
            'cms_recommendation_case_id',
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by')->withTrashed();
    }

    public function ender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by')->withTrashed();
    }
}
