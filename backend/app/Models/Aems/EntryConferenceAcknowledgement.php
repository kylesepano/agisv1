<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class EntryConferenceAcknowledgement extends Model
{
    public const STATUSES = ['ACKNOWLEDGED', 'ACKNOWLEDGED_WITH_RESERVATION'];

    protected $fillable = [
        'entry_conference_id',
        'user_id',
        'office_id',
        'conference_version',
        'acknowledgement_status',
        'reservation',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return ['conference_version' => 'integer', 'acknowledged_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Entry Conference acknowledgements are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Entry Conference acknowledgements cannot be deleted.'));
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
