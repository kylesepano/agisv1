<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Immutable authority and consequence assessment for a team amendment. */
class AemsTeamAmendment extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'audit_engagement_id', 'engagement_team_id', 'action', 'authority_code',
        'reason', 'consequence_assessment', 'old_values', 'new_values',
        'actor_id', 'authority_document_version_id', 'created_at',
    ];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Team amendment controls are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Team amendment controls cannot be deleted.'));
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(EngagementTeam::class, 'engagement_team_id')->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
