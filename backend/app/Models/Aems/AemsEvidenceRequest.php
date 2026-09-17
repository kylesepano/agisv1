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

/** A scoped, separately versioned request for audit evidence. */
class AemsEvidenceRequest extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'DRAFT', 'SUBMITTED', 'SENT', 'ACKNOWLEDGED', 'PARTIALLY_RECEIVED',
        'RECEIVED', 'FOR_REVIEW', 'ASSESSED', 'OVERDUE', 'EXTENSION_REQUESTED',
        'EXTENDED', 'ESCALATED', 'CANCELLED', 'CLOSED_WITHOUT_SUBMISSION', 'CLOSED',
    ];

    protected $table = 'aems_evidence_requests';

    protected $fillable = [
        'request_family_uuid', 'audit_engagement_id', 'request_code', 'title',
        'purpose', 'requested_from_office_id', 'requested_from_user_id',
        'due_date', 'status', 'current_version_number', 'prepared_by',
        'submitted_by', 'submitted_at', 'sent_by', 'sent_at',
        'partially_received_at', 'received_at', 'assessed_at', 'closed_at',
        'closed_by', 'closure_reason', 'lock_version', 'is_active',
        'acknowledged_by', 'acknowledged_at', 'acknowledgement_note',
        'extension_requested_due_date', 'extension_requested_by', 'extension_requested_at',
        'extension_due_date', 'extension_approved_by', 'extension_approved_at', 'extension_reason',
        'overdue_at', 'escalated_by', 'escalated_at', 'escalation_reason',
        'cancelled_by', 'cancelled_at', 'cancellation_reason', 'closure_type',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'submitted_at' => 'datetime',
            'sent_at' => 'datetime',
            'partially_received_at' => 'datetime',
            'received_at' => 'datetime',
            'assessed_at' => 'datetime',
            'closed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'extension_requested_due_date' => 'date',
            'extension_requested_at' => 'datetime',
            'extension_due_date' => 'date',
            'extension_approved_at' => 'datetime',
            'overdue_at' => 'datetime',
            'escalated_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'lock_version' => 'integer',
            'current_version_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('engagement', fn (Builder $engagement): Builder =>
            app(AemsAccessService::class)->visibleEngagements($engagement, $user));
    }

    public function engagement(): BelongsTo { return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed(); }
    public function requestedFromOffice(): BelongsTo { return $this->belongsTo(Office::class, 'requested_from_office_id')->withTrashed(); }
    public function requestedFromUser(): BelongsTo { return $this->belongsTo(User::class, 'requested_from_user_id')->withTrashed(); }
    public function preparer(): BelongsTo { return $this->belongsTo(User::class, 'prepared_by')->withTrashed(); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by')->withTrashed(); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sent_by')->withTrashed(); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by')->withTrashed(); }
    public function acknowledger(): BelongsTo { return $this->belongsTo(User::class, 'acknowledged_by')->withTrashed(); }
    public function extensionRequester(): BelongsTo { return $this->belongsTo(User::class, 'extension_requested_by')->withTrashed(); }
    public function extensionApprover(): BelongsTo { return $this->belongsTo(User::class, 'extension_approved_by')->withTrashed(); }
    public function escalator(): BelongsTo { return $this->belongsTo(User::class, 'escalated_by')->withTrashed(); }
    public function canceller(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by')->withTrashed(); }
    public function versions(): HasMany { return $this->hasMany(AemsEvidenceRequestVersion::class, 'evidence_request_id')->orderByDesc('version_number'); }
    public function latestVersion(): HasOne { return $this->hasOne(AemsEvidenceRequestVersion::class, 'evidence_request_id')->latestOfMany('version_number'); }
    public function evidenceLinks(): HasMany { return $this->hasMany(AemsEvidenceRequestEvidence::class, 'evidence_request_id'); }
    public function responses(): HasMany { return $this->hasMany(AemsEvidenceRequestResponse::class, 'evidence_request_id'); }
    public function events(): HasMany { return $this->hasMany(AemsEvidenceRequestEvent::class, 'evidence_request_id')->orderByDesc('created_at'); }
}
