<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Pins one immutable Core document version to an exit conference. */
class ExitConferenceAttachment extends Model
{
    use HasFactory;

    public const CATEGORIES = ['MINUTES', 'SUPPORTING'];

    protected $fillable = [
        'exit_conference_id',
        'attachment_code',
        'category',
        'caption',
        'document_version_id',
        'uploaded_by',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Exit conference attachments are immutable.');
        });
        static::deleting(function (): never {
            throw new LogicException('Exit conference attachments cannot be deleted.');
        });
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(ExitConference::class, 'exit_conference_id')->withTrashed();
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
