<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a focused audit subject that belongs to exactly one audit area.
 */
class AuditFocus extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'audit_focuses';

    protected $fillable = [
        'audit_area_id',
        'code',
        'name',
        'description',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function auditArea(): BelongsTo
    {
        return $this->belongsTo(AuditArea::class);
    }
}
