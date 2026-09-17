<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Historical Primary Validator assignment for one Validation Review. */
class CmsValidationAssignment extends Model
{
    use HasFactory;

    public const ROLE_PRIMARY_VALIDATOR = 'PRIMARY_VALIDATOR';

    protected $fillable = [
        'cms_validation_review_id',
        'user_id',
        'assignment_role_code',
        'assigned_by',
        'assigned_at',
        'assignment_reason',
        'effective_from',
        'effective_until',
        'ended_by',
        'ended_at',
        'end_reason',
        'is_current',
        'current_slot',
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
            if (! $assignment->getOriginal('is_current')) {
                throw new LogicException('Ended validator assignments are immutable.');
            }
            $allowed = [
                'effective_until',
                'ended_by',
                'ended_at',
                'end_reason',
                'is_current',
                'current_slot',
                'updated_at',
            ];
            if (array_diff(array_keys($assignment->getDirty()), $allowed) !== []) {
                throw new LogicException('Validator assignment history is immutable.');
            }
        });
        static::deleting(
            fn (): never => throw new LogicException('Validator assignments cannot be deleted.'),
        );
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(CmsValidationReview::class, 'cms_validation_review_id');
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
