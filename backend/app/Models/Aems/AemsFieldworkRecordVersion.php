<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Immutable execution content and traceability snapshot. */
class AemsFieldworkRecordVersion extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'fieldwork_record_id', 'version_number', 'record_type',
        'audit_program_procedure_id', 'audit_area_id', 'audit_focus_id',
        'performed_on', 'location', 'objective', 'procedure_performed',
        'population_description', 'sample_description', 'analysis', 'result',
        'conclusion', 'execution_status', 'related_tasks', 'related_records',
        'reviewer_notes', 'change_reason', 'created_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'performed_on' => 'date',
            'related_tasks' => 'array',
            'related_records' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Fieldwork record versions are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Fieldwork record versions cannot be deleted.'));
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(AemsFieldworkRecord::class, 'fieldwork_record_id')->withTrashed();
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(AuditProgramProcedure::class, 'audit_program_procedure_id')->withTrashed();
    }

    public function auditArea(): BelongsTo
    {
        return $this->belongsTo(AuditArea::class)->withTrashed();
    }

    public function auditFocus(): BelongsTo
    {
        return $this->belongsTo(AuditFocus::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(AemsFieldworkRecordParticipant::class, 'fieldwork_record_version_id');
    }

    public function workingPaperLinks(): HasMany
    {
        return $this->hasMany(AemsFieldworkWorkingPaperLink::class, 'fieldwork_record_version_id');
    }

    public function evidenceLinks(): HasMany
    {
        return $this->hasMany(AemsFieldworkEvidenceLink::class, 'fieldwork_record_version_id');
    }
}
