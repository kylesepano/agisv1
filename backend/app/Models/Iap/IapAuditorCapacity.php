<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores an auditor's temporary annual person-day capacity for IAP allocation.
 */
class IapAuditorCapacity extends Model
{
    use HasFactory;

    protected $fillable = [
        'fiscal_year',
        'user_id',
        'available_person_days',
        'notes',
        'set_by',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'available_person_days' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by')->withTrashed();
    }
}
