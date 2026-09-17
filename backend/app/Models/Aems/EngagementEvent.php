<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preserves append-only AEMS workflow actions with actor, state, version, and value snapshots.
 */
class EngagementEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'audit_engagement_id',
        'subject_type',
        'subject_id',
        'subject_family_uuid',
        'subject_version',
        'subject_code',
        'action',
        'from_status',
        'to_status',
        'actor_id',
        'actor_role_code',
        'actor_assignment_code',
        'office_id',
        'comment',
        'reason_category_code',
        'old_values',
        'new_values',
        'record_lock_version',
        'document_version_ids',
        'notification_ids',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'subject_version' => 'integer',
            'old_values' => 'array',
            'new_values' => 'array',
            'record_lock_version' => 'integer',
            'document_version_ids' => 'array',
            'notification_ids' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class)->withTrashed();
    }
}
