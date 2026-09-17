<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Reviewable AEMS escalation signal; it cannot issue a notice or make a final decision. */
class AemsEscalationCandidate extends Model
{
    use HasFactory;

    public const STATUSES = ['OPEN', 'ACKNOWLEDGED', 'RESOLVED', 'DISMISSED'];
    protected $table = 'aems_escalation_candidates';
    protected $fillable = ['audit_engagement_id', 'candidate_code', 'detection_key', 'candidate_type', 'source_type', 'source_id', 'audit_finding_id', 'aems_engagement_task_id', 'entry_conference_id', 'exit_conference_id', 'status', 'reason', 'detected_at', 'due_at', 'trigger_snapshot', 'reviewed_by', 'reviewed_at', 'review_comment', 'lock_version'];
    protected static function booted(): void { static::updating(function (self $candidate): void { if (in_array($candidate->getOriginal('status'), ['RESOLVED', 'DISMISSED'], true)) throw new LogicException('Resolved or dismissed escalation candidates are immutable.'); }); static::deleting(function (self $candidate): void { if (in_array($candidate->status, ['RESOLVED', 'DISMISSED'], true)) throw new LogicException('Resolved or dismissed escalation candidates cannot be deleted.'); }); }
    protected function casts(): array { return ['source_id' => 'integer', 'detected_at' => 'datetime', 'due_at' => 'datetime', 'trigger_snapshot' => 'array', 'reviewed_at' => 'datetime', 'lock_version' => 'integer']; }
    public function engagement(): BelongsTo { return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed(); }
    public function finding(): BelongsTo { return $this->belongsTo(AuditFinding::class, 'audit_finding_id')->withTrashed(); }
    public function task(): BelongsTo { return $this->belongsTo(AemsEngagementTask::class, 'aems_engagement_task_id')->withTrashed(); }
    public function entryConference(): BelongsTo { return $this->belongsTo(EntryConference::class, 'entry_conference_id')->withTrashed(); }
    public function exitConference(): BelongsTo { return $this->belongsTo(ExitConference::class, 'exit_conference_id')->withTrashed(); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by')->withTrashed(); }
}
