<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class EntryConference extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'DRAFT',
        'SCHEDULED',
        'RESCHEDULED',
        'HELD',
        'NOTES_FOR_ACKNOWLEDGEMENT',
        'ACKNOWLEDGED',
        'COMPLETED',
        'WAIVED',
        'CANCELLED',
    ];

    public const TERMINAL_STATUSES = ['COMPLETED', 'WAIVED'];

    protected $fillable = [
        'audit_engagement_id',
        'conference_code',
        'status',
        'scheduled_start_at',
        'scheduled_end_at',
        'held_at',
        'venue',
        'meeting_link',
        'online_meeting_details',
        'agenda',
        'briefing_paper',
        'auditee_views',
        'auditee_expectations',
        'conference_notes',
        'material_matters_disposition',
        'notes_circulated_at',
        'notes_circulated_by',
        'reschedule_reason',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'waiver_reason',
        'waiver_authority',
        'waived_at',
        'waived_by',
        'completed_at',
        'completed_by',
        'created_by',
        'updated_by',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'held_at' => 'datetime',
            'briefing_paper' => 'array',
            'notes_circulated_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'waived_at' => 'datetime',
            'completed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $conference): void {
            if (in_array($conference->getOriginal('status'), self::TERMINAL_STATUSES, true)) {
                throw new LogicException('Completed or waived Entry Conferences are immutable.');
            }
        });
        static::deleting(function (self $conference): void {
            if (in_array($conference->status, self::TERMINAL_STATUSES, true)) {
                throw new LogicException('Completed or waived Entry Conferences cannot be deleted.');
            }
        });
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EntryConferenceParticipant::class);
    }

    public function matters(): HasMany
    {
        return $this->hasMany(EntryConferenceMatter::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(EntryConferenceAgreement::class);
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(EntryConferenceAcknowledgement::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EntryConferenceAttachment::class);
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

    public function waiverApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by')->withTrashed();
    }
}
