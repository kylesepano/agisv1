<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Versioned objectivity, conflict-of-interest, or independence declaration. */
class AemsTeamSafeguardDeclaration extends Model
{
    use HasFactory;

    public const TYPES = ['OBJECTIVITY', 'CONFLICT_OF_INTEREST', 'INDEPENDENCE'];

    public const OUTCOMES = ['CLEAR', 'DISCLOSED', 'CONFLICT'];

    public const STATUSES = ['SUBMITTED', 'ACCEPTED', 'RETURNED'];

    protected $fillable = [
        'declaration_family_uuid', 'audit_engagement_id', 'engagement_team_id', 'user_id',
        'declaration_type', 'version_number', 'supersedes_id', 'is_current_revision',
        'outcome', 'statement', 'mitigation_plan', 'evidence_document_version_id', 'status',
        'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_notes',
        'created_by', 'updated_by', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'is_current_revision' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $declaration): void {
            if ($declaration->getOriginal('status') === 'ACCEPTED'
                && $declaration->isDirty(array_diff(array_keys($declaration->getDirty()), ['is_current_revision', 'updated_by', 'updated_at']))) {
                throw new LogicException('Accepted safeguard declarations are immutable; create a revision.');
            }
        });
        static::deleting(
            fn (): never => throw new LogicException('Safeguard declarations are immutable and cannot be deleted.'),
        );
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

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id')->withTrashed();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'declaration_family_uuid', 'declaration_family_uuid')
            ->orderByDesc('version_number');
    }

    public function evidenceDocumentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'evidence_document_version_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }
}
