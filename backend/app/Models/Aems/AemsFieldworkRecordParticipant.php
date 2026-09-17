<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AemsFieldworkRecordParticipant extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Fieldwork record participants are immutable.'));
        static::deleting(fn (): never => throw new \LogicException('Fieldwork record participants cannot be deleted.'));
    }

    protected $fillable = ['fieldwork_record_version_id', 'user_id', 'office_id', 'participant_name', 'participant_role'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(AemsFieldworkRecordVersion::class, 'fieldwork_record_version_id');
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
