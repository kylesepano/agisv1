<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents an internal or external invitee and attendance record for an exit conference.
 */
class ExitConferenceParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'exit_conference_id',
        'user_id',
        'office_id',
        'external_name',
        'external_email',
        'participant_role',
        'attendance_status',
        'attendance_notes',
        'attendance_recorded_at',
        'attendance_recorded_by',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_recorded_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(ExitConference::class, 'exit_conference_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class)->withTrashed();
    }

    public function attendanceRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendance_recorded_by')->withTrashed();
    }

    public function acknowledgement(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(
            ExitConferenceAcknowledgement::class,
            'exit_conference_participant_id',
        );
    }
}
