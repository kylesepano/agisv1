<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Records leave, training, and other dates unavailable for audit scheduling.
 */
class IapAuditorUnavailability extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'iap_auditor_unavailability';

    protected $fillable = [
        'user_id',
        'unavailability_type_id',
        'title',
        'start_date',
        'end_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'unavailability_type_id')
            ->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
