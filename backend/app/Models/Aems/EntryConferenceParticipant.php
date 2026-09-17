<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class EntryConferenceParticipant extends Model
{
    protected $fillable = [
        'entry_conference_id',
        'user_id',
        'office_id',
        'participant_type',
        'participant_role',
        'external_name',
        'external_email',
        'attendance_status',
        'attended_at',
        'attendance_notes',
    ];

    protected function casts(): array
    {
        return ['attended_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $guard = function (self $participant): void {
            if (in_array($participant->conference?->status, EntryConference::TERMINAL_STATUSES, true)) {
                throw new LogicException('Completed or waived Entry Conference participants are immutable.');
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(EntryConference::class, 'entry_conference_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class)->withTrashed();
    }
}
