<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class EntryConferenceAgreement extends Model
{
    protected $fillable = [
        'entry_conference_id',
        'agreement',
        'responsible_user_id',
        'responsible_office_id',
        'due_date',
        'status',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    protected static function booted(): void
    {
        $guard = function (self $agreement): void {
            if (in_array($agreement->conference?->status, EntryConference::TERMINAL_STATUSES, true)) {
                throw new LogicException('Completed or waived Entry Conference agreements are immutable.');
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(EntryConference::class, 'entry_conference_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id')->withTrashed();
    }

    public function responsibleOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'responsible_office_id')->withTrashed();
    }
}
