<?php

namespace App\Models;

use App\Services\AemsAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A versioned, traceable execution record for an Audit Program procedure. */
class AemsFieldworkRecord extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = [
        'INTERVIEW', 'OBSERVATION', 'WALKTHROUGH', 'INSPECTION',
        'TESTING', 'SAMPLING', 'ANALYSIS', 'INQUIRY', 'MEETING',
        'FIELD_NOTE', 'OTHER',
    ];

    public const STATUSES = [
        'DRAFT', 'SUBMITTED', 'RETURNED_FOR_REVISION', 'RESUBMITTED',
        'FINALIZED', 'SUPERSEDED',
    ];

    public const EXECUTION_STATUSES = ['PLANNED', 'IN_PROGRESS', 'COMPLETED'];

    protected $fillable = [
        'record_family_uuid', 'audit_engagement_id', 'audit_program_procedure_id',
        'audit_area_id', 'audit_focus_id', 'record_code', 'record_type', 'status',
        'current_version_number', 'prepared_by', 'submitted_at', 'submitted_by',
        'reviewer_id', 'reviewed_at', 'reviewer_notes', 'finalized_by', 'finalized_at',
        'lock_version', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'current_version_number' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'finalized_at' => 'datetime',
            'lock_version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('engagement', fn (Builder $engagement): Builder =>
            app(AemsAccessService::class)->visibleEngagements($engagement, $user));
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(AuditProgramProcedure::class, 'audit_program_procedure_id')->withTrashed();
    }

    public function auditArea(): BelongsTo
    {
        return $this->belongsTo(AuditArea::class)->withTrashed();
    }

    public function auditFocus(): BelongsTo
    {
        return $this->belongsTo(AuditFocus::class)->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AemsFieldworkRecordVersion::class, 'fieldwork_record_id')->orderBy('version_number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(AemsFieldworkRecordVersion::class, 'fieldwork_record_id')->ofMany('version_number', 'max');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by')->withTrashed();
    }
}
