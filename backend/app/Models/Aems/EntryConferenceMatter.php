<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class EntryConferenceMatter extends Model
{
    protected $fillable = [
        'entry_conference_id',
        'matter_type',
        'description',
        'is_material',
        'disposition_status',
        'disposition',
        'responsible_user_id',
        'responsible_office_id',
        'due_date',
    ];

    protected function casts(): array
    {
        return ['is_material' => 'boolean', 'due_date' => 'date'];
    }

    protected static function booted(): void
    {
        $guard = function (self $matter): void {
            if (in_array($matter->conference?->status, EntryConference::TERMINAL_STATUSES, true)) {
                throw new LogicException('Completed or waived Entry Conference matters are immutable.');
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
