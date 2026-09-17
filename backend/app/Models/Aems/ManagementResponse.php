<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Represents a versioned auditee response to one formally communicated finding.
 */
class ManagementResponse extends Model
{
    use HasFactory, SoftDeletes;

    public const AGREEMENT_POSITIONS = ['AGREE', 'PARTIALLY_AGREE', 'DISAGREE'];

    public const RESPONSE_KINDS = ['ORIGINAL', 'LATE', 'SUPPLEMENTAL', 'REPLACEMENT'];

    public const STATUSES = [
        'DRAFT',
        'SUBMITTED',
        'UNDER_AUDITOR_REVIEW',
        'CLARIFICATION_REQUESTED',
        'RESUBMITTED',
        'EXTENSION_REQUESTED',
        'EXTENSION_APPROVED',
        'DIALOGUE_FINALIZED',
    ];

    protected $fillable = [
        'response_family_uuid',
        'version_number',
        'supersedes_response_id',
        'is_current_revision',
        'audit_finding_id',
        'response_code',
        'response_kind',
        'agreement_position',
        'management_comment',
        'proposed_action',
        'responsible_office_id',
        'responsible_user_id',
        'proposed_target_date',
        'status',
        'authored_by',
        'submitted_at',
        'clarification_requested_at',
        'clarification_requested_by',
        'clarification_request',
        'extension_requested_at',
        'extension_requested_by',
        'extension_requested_due_date',
        'extension_approved_at',
        'extension_approved_by',
        'extension_approved_due_date',
        'extension_reason',
        'submitted_late',
        'late_reason',
        'supplemental_reason',
        'finalized_at',
        'finalized_by',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'submitted_late' => 'boolean',
            'is_current_revision' => 'boolean',
            'proposed_target_date' => 'date',
            'submitted_at' => 'datetime',
            'clarification_requested_at' => 'datetime',
            'extension_requested_at' => 'datetime',
            'extension_approved_at' => 'datetime',
            'extension_requested_due_date' => 'date',
            'extension_approved_due_date' => 'date',
            'finalized_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $response): void {
            if ($response->getOriginal('status') === 'DIALOGUE_FINALIZED'
                || ! $response->getOriginal('is_current_revision')) {
                throw new LogicException('Finalized management responses are immutable.');
            }
        });
        static::deleting(function (self $response): void {
            if ($response->status === 'DIALOGUE_FINALIZED') {
                throw new LogicException('Finalized management responses cannot be deleted.');
            }
        });
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(AuditFinding::class, 'audit_finding_id')->withTrashed();
    }

    public function responsibleOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'responsible_office_id')->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by')->withTrashed();
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by')->withTrashed();
    }

    public function extensionRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'extension_requested_by')->withTrashed();
    }

    public function extensionApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'extension_approved_by')->withTrashed();
    }

    public function rejoinders(): HasMany
    {
        return $this->hasMany(AuditorRejoinder::class)->orderBy('version_number');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AemsDialogueAttachment::class)
            ->orderBy('created_at');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_response_id')->withTrashed();
    }
}
