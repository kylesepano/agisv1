<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/** Versioned reviewer note tied to an engagement and optional finding/conference/task. */
class AemsReviewNote extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['DRAFT', 'FINALIZED', 'VOIDED'];

    protected $table = 'aems_review_notes';
    protected $fillable = ['note_family_uuid', 'version_number', 'supersedes_note_id', 'is_current_revision', 'audit_engagement_id', 'audit_finding_id', 'entry_conference_id', 'exit_conference_id', 'aems_engagement_task_id', 'note_code', 'note_type', 'content', 'status', 'revision_reason', 'created_by', 'finalized_by', 'finalized_at', 'lock_version'];
    protected function casts(): array { return ['version_number' => 'integer', 'is_current_revision' => 'boolean', 'finalized_at' => 'datetime', 'lock_version' => 'integer']; }

    protected static function booted(): void
    {
        static::updating(function (self $note): void {
            if ($note->getOriginal('status') === 'FINALIZED' || ! $note->getOriginal('is_current_revision')) throw new LogicException('Finalized or superseded review notes are immutable.');
        });
        static::deleting(function (self $note): void {
            if ($note->status === 'FINALIZED') throw new LogicException('Finalized review notes cannot be deleted.');
        });
    }

    public function engagement(): BelongsTo { return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed(); }
    public function finding(): BelongsTo { return $this->belongsTo(AuditFinding::class, 'audit_finding_id')->withTrashed(); }
    public function entryConference(): BelongsTo { return $this->belongsTo(EntryConference::class, 'entry_conference_id')->withTrashed(); }
    public function exitConference(): BelongsTo { return $this->belongsTo(ExitConference::class, 'exit_conference_id')->withTrashed(); }
    public function task(): BelongsTo { return $this->belongsTo(AemsEngagementTask::class, 'aems_engagement_task_id')->withTrashed(); }
    public function supersedes(): BelongsTo { return $this->belongsTo(self::class, 'supersedes_note_id')->withTrashed(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
    public function finalizer(): BelongsTo { return $this->belongsTo(User::class, 'finalized_by')->withTrashed(); }
    public function attachments(): HasMany { return $this->hasMany(AemsReviewNoteAttachment::class, 'aems_review_note_id')->orderBy('created_at'); }
}
