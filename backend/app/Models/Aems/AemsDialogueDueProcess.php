<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Immutable no-response/clarification due-process exchange. */
class AemsDialogueDueProcess extends Model
{
    public const CREATED_AT = 'recorded_at';
    public const UPDATED_AT = null;
    public const TYPES = [
        'REMINDER', 'NOTICE_SENT', 'CLARIFICATION_REQUESTED', 'FINAL_NON_RESPONSE',
        'ESCALATION_RECOMMENDED', 'EXTENSION_REQUESTED', 'EXTENSION_APPROVED',
        'EXTENSION_REJECTED', 'LATE_RESPONSE', 'SUPPLEMENTAL_RESPONSE',
    ];
    protected $table = 'aems_dialogue_due_process';
    protected $fillable = ['audit_engagement_id', 'audit_finding_id', 'management_response_id', 'event_code', 'version_number', 'event_type', 'content', 'due_date', 'actor_id', 'metadata', 'recorded_at'];
    protected static function booted(): void { static::updating(fn (): never => throw new LogicException('Due-process exchanges are immutable.')); static::deleting(fn (): never => throw new LogicException('Due-process exchanges cannot be deleted.')); }
    protected function casts(): array { return ['version_number' => 'integer', 'due_date' => 'date', 'metadata' => 'array', 'recorded_at' => 'datetime']; }
    public function engagement(): BelongsTo { return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed(); }
    public function finding(): BelongsTo { return $this->belongsTo(AuditFinding::class, 'audit_finding_id')->withTrashed(); }
    public function response(): BelongsTo { return $this->belongsTo(ManagementResponse::class, 'management_response_id')->withTrashed(); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id')->withTrashed(); }
    public function attachments(): HasMany { return $this->hasMany(AemsDueProcessAttachment::class, 'aems_dialogue_due_process_id')->orderBy('created_at'); }
}
