<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Associates an auditor with a planning skill or specialization.
 */
class IapAuditorSkill extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialization_id',
        'proficiency_level',
        'notes',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'specialization_id')
            ->withTrashed();
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by')->withTrashed();
    }
}
