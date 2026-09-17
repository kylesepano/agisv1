<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents an actionable notification with optional record-navigation metadata.
 */
class SystemNotification extends Model
{
    use HasFactory;

    public const CATEGORIES = ['WORKFLOW', 'ASSIGNMENT', 'DUE_DATE', 'OVERDUE', 'SYSTEM'];

    public const PRIORITIES = ['LOW', 'NORMAL', 'HIGH', 'URGENT'];

    protected $table = 'notifications';

    protected $fillable = [
        'recipient_id',
        'actor_id',
        'type',
        'category',
        'priority',
        'module_code',
        'title',
        'message',
        'action_url',
        'action_label',
        'subject_type',
        'subject_id',
        'subject_code',
        'dedupe_key',
        'metadata',
        'read_at',
        'archived_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'read_at' => 'datetime',
            'archived_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id')->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
