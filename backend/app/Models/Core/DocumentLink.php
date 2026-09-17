<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Associates a document with an authorized record in a Core or audit module.
 */
class DocumentLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'module_code',
        'record_type',
        'record_id',
        'record_code',
        'record_label',
        'linked_by',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by')->withTrashed();
    }
}
