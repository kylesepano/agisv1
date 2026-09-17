<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents an ordered, activatable value within a master-list category.
 */
class MasterListItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'master_list_id',
        'code',
        'label',
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

    public function masterList(): BelongsTo
    {
        return $this->belongsTo(MasterList::class);
    }
}
