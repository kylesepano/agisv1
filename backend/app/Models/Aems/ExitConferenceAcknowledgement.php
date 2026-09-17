<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Preserves one auditee representative acknowledgement of completed minutes. */
class ExitConferenceAcknowledgement extends Model
{
    use HasFactory;

    public const STATUSES = ['ACKNOWLEDGED', 'WITH_RESERVATIONS'];

    protected $fillable = [
        'exit_conference_id',
        'exit_conference_participant_id',
        'user_id',
        'office_id',
        'version_number',
        'acknowledgement_status',
        'comment',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'acknowledged_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Exit conference acknowledgements are immutable.');
        });
        static::deleting(function (): never {
            throw new LogicException('Exit conference acknowledgements cannot be deleted.');
        });
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(ExitConference::class, 'exit_conference_id')->withTrashed();
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(
            ExitConferenceParticipant::class,
            'exit_conference_participant_id',
        );
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
