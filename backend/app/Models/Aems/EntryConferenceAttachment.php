<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class EntryConferenceAttachment extends Model
{
    public const CATEGORIES = [
        'BRIEFING_PAPER',
        'AGENDA',
        'CONFERENCE_NOTES',
        'WAIVER_SUPPORT',
        'OTHER',
    ];

    protected $fillable = [
        'entry_conference_id',
        'attachment_code',
        'category',
        'caption',
        'document_version_id',
        'uploaded_by',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Entry Conference attachments are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Entry Conference attachments cannot be deleted.'));
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(EntryConference::class, 'entry_conference_id');
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}
