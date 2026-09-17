<?php

namespace App\Models;

use App\Services\AemsAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a controlled working-paper family prepared for one engagement procedure.
 */
class WorkingPaper extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'DRAFT',
        'SUBMITTED',
        'RETURNED_FOR_REVISION',
        'RESUBMITTED',
        'APPROVED',
        'SUPERSEDED',
        'VOIDED',
    ];

    protected $fillable = [
        'audit_engagement_id',
        'audit_program_procedure_id',
        'working_paper_code',
        'title',
        'status',
        'current_version_number',
        'prepared_by',
        'reviewer_id',
        'reviewed_at',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'voided_at',
        'voided_by',
        'void_reason',
        'lock_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'current_version_number' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'voided_at' => 'datetime',
            'lock_version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    /**
     * Restricts discovery to engagements visible to the current AEMS actor.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'engagement',
            fn (Builder $engagement): Builder => app(AemsAccessService::class)
                ->visibleEngagements($engagement, $user),
        );
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(AuditProgramProcedure::class, 'audit_program_procedure_id')->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkingPaperVersion::class)->orderBy('version_number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(WorkingPaperVersion::class)->ofMany('version_number', 'max');
    }

    public function evidence(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditEvidence::class,
            'working_paper_evidence',
            'working_paper_id',
            'audit_evidence_id',
        )->withTimestamps();
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id')->withTrashed();
    }
}
