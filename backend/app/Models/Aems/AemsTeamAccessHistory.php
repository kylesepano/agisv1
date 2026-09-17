<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Immutable assignment access grant/revocation history. */
class AemsTeamAccessHistory extends Model
{
    use HasFactory;

    protected $table = 'aems_team_access_history';

    public const UPDATED_AT = null;

    protected $fillable = [
        'audit_engagement_id', 'engagement_team_id', 'user_id', 'action',
        'assignment_role_code', 'access_from', 'access_until', 'actor_id',
        'reason', 'snapshot', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'access_from' => 'date',
            'access_until' => 'date',
            'snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Assignment access history is immutable.'));
        static::deleting(fn (): never => throw new LogicException('Assignment access history cannot be deleted.'));
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(EngagementTeam::class, 'engagement_team_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
