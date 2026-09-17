<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Immutable exact Core document version linked to due-process evidence. */
class AemsDueProcessAttachment extends Model
{
    public const UPDATED_AT = null;
    protected $table = 'aems_due_process_attachments';
    protected $fillable = ['aems_dialogue_due_process_id', 'attachment_code', 'caption', 'document_version_id', 'uploaded_by', 'created_at'];
    protected function casts(): array { return ['created_at' => 'datetime']; }
    protected static function booted(): void { static::updating(fn (): never => throw new LogicException('Due-process attachments are immutable.')); static::deleting(fn (): never => throw new LogicException('Due-process attachments cannot be deleted.')); }
    public function dueProcess(): BelongsTo { return $this->belongsTo(AemsDialogueDueProcess::class, 'aems_dialogue_due_process_id'); }
    public function documentVersion(): BelongsTo { return $this->belongsTo(DocumentVersion::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by')->withTrashed(); }
}
