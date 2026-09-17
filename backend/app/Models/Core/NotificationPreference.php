<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores a user's in-app and email delivery preferences by notification type.
 */
class NotificationPreference extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'in_app_enabled',
        'workflow_enabled',
        'assignments_enabled',
        'due_dates_enabled',
        'system_enabled',
        'email_enabled',
        'digest_frequency',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    protected function casts(): array
    {
        return [
            'in_app_enabled' => 'boolean',
            'workflow_enabled' => 'boolean',
            'assignments_enabled' => 'boolean',
            'due_dates_enabled' => 'boolean',
            'system_enabled' => 'boolean',
            'email_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
