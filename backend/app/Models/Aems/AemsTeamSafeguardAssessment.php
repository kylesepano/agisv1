<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Immutable provider/readiness assessment for an AEMS engagement team. */
class AemsTeamSafeguardAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_uuid', 'audit_engagement_id', 'version_number', 'is_current_revision', 'status',
        'provider_mode', 'provider_status', 'reconciliation', 'checks', 'blockers', 'warnings',
        'supersedes_id', 'assessed_by', 'assessed_at', 'approved_by', 'approved_at',
        'decision_comment', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'is_current_revision' => 'boolean',
            'provider_status' => 'array',
            'reconciliation' => 'array',
            'checks' => 'array',
            'blockers' => 'array',
            'warnings' => 'array',
            'assessed_at' => 'datetime',
            'approved_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(
            fn (): never => throw new LogicException('Safeguard assessments are immutable; create a new assessment.'),
        );
        static::deleting(
            fn (): never => throw new LogicException('Safeguard assessments cannot be deleted.'),
        );
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id')->withTrashed();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'audit_engagement_id', 'audit_engagement_id')
            ->orderByDesc('version_number');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }
}
