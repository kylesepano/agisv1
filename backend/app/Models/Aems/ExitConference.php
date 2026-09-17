<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Represents the scheduled exit conference, minutes, agreements, and acknowledgement.
 */
class ExitConference extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['SCHEDULED', 'RESCHEDULED', 'COMPLETED', 'CANCELLED', 'WAIVED'];

    protected $fillable = [
        'audit_engagement_id',
        'conference_code',
        'scheduled_start_at',
        'scheduled_end_at',
        'venue',
        'meeting_link',
        'online_meeting_details',
        'agenda',
        'discussion_summary',
        'minutes',
        'agreements',
        'disagreements',
        'completion_snapshot',
        'minutes_document_version_id',
        'status',
        'waiver_reason',
        'cancellation_reason',
        'created_by',
        'updated_by',
        'completed_at',
        'completed_by',
        'acknowledged_at',
        'acknowledged_by',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'completed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'completion_snapshot' => 'array',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $conference): void {
            if (! in_array(
                $conference->getOriginal('status'),
                ['COMPLETED', 'CANCELLED', 'WAIVED'],
                true,
            )) {
                return;
            }
            $acknowledgementFields = [
                'acknowledged_at',
                'acknowledged_by',
                'lock_version',
                'updated_at',
            ];
            if (array_diff(array_keys($conference->getDirty()), $acknowledgementFields)) {
                throw new LogicException('Completed, cancelled, and waived conferences are immutable.');
            }
        });
        static::deleting(function (self $conference): void {
            if (in_array($conference->status, ['COMPLETED', 'CANCELLED', 'WAIVED'], true)) {
                throw new LogicException('Terminal Exit Conference records cannot be deleted.');
            }
        });
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ExitConferenceParticipant::class);
    }

    public function findings(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditFinding::class,
            'exit_conference_findings',
            'exit_conference_id',
            'audit_finding_id',
        )->withPivot([
            'sequence_number',
            'discussion_status',
            'agreement_status',
            'discussion_notes',
            'agreement_details',
            'disagreement_details',
            'revised_target_date',
        ])->withTimestamps()->orderByPivot('sequence_number');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExitConferenceAttachment::class);
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(ExitConferenceAcknowledgement::class);
    }

    public function minutesDocumentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'minutes_document_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by')->withTrashed();
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by')->withTrashed();
    }
}
