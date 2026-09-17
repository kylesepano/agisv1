<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/** A scoped operational task with explicit due, overdue, and completion state. */
class AemsEngagementTask extends Model
{
    use HasFactory, SoftDeletes;

    protected static bool $allowControlledTransition = false;

    public const STATUSES = ['OPEN', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'];

    protected $table = 'aems_engagement_tasks';

    protected $fillable = [
        'audit_engagement_id', 'task_code', 'task_type', 'title', 'description',
        'subject_type', 'subject_id', 'audit_finding_id', 'entry_conference_id',
        'exit_conference_id', 'assigned_to', 'assigned_office_id', 'status',
        'due_at', 'started_at', 'completed_at', 'completed_by', 'completion_comment',
        'created_by', 'updated_by', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $task): void {
            if (! self::$allowControlledTransition && in_array($task->getOriginal('status'), ['COMPLETED', 'CANCELLED'], true)) {
                throw new LogicException('Completed or cancelled AEMS tasks are immutable.');
            }
        });
        static::deleting(function (self $task): void {
            if (in_array($task->status, ['COMPLETED', 'CANCELLED'], true)) {
                throw new LogicException('Completed or cancelled AEMS tasks cannot be deleted.');
            }
        });
    }

    public static function allowControlledTransition(callable $callback): mixed
    {
        $previous = self::$allowControlledTransition;
        self::$allowControlledTransition = true;
        try { return $callback(); } finally { self::$allowControlledTransition = $previous; }
    }

    public function engagement(): BelongsTo { return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed(); }
    public function finding(): BelongsTo { return $this->belongsTo(AuditFinding::class, 'audit_finding_id')->withTrashed(); }
    public function entryConference(): BelongsTo { return $this->belongsTo(EntryConference::class, 'entry_conference_id')->withTrashed(); }
    public function exitConference(): BelongsTo { return $this->belongsTo(ExitConference::class, 'exit_conference_id')->withTrashed(); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to')->withTrashed(); }
    public function assignedOffice(): BelongsTo { return $this->belongsTo(Office::class, 'assigned_office_id')->withTrashed(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by')->withTrashed(); }
    public function completer(): BelongsTo { return $this->belongsTo(User::class, 'completed_by')->withTrashed(); }
    public function events(): HasMany { return $this->hasMany(AemsEngagementTaskEvent::class, 'aems_engagement_task_id')->orderBy('version_number'); }

    public function getDueStateAttribute(): string
    {
        if (in_array($this->status, ['COMPLETED', 'CANCELLED'], true)) return $this->status;
        if (! $this->due_at) return 'NO_DUE_DATE';
        return $this->due_at->isPast() ? 'OVERDUE' : ($this->due_at->lte(now()->addHours(48)) ? 'DUE_SOON' : 'ON_TRACK');
    }
}
