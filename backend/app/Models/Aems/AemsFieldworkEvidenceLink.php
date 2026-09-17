<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AemsFieldworkEvidenceLink extends Model
{
    protected $table = 'aems_fieldwork_record_evidence';

    use HasFactory;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Fieldwork Evidence links are immutable.'));
        static::deleting(fn (): never => throw new \LogicException('Fieldwork Evidence links cannot be deleted.'));
    }

    protected $fillable = ['fieldwork_record_version_id', 'audit_evidence_id'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(AemsFieldworkRecordVersion::class, 'fieldwork_record_version_id');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(AuditEvidence::class, 'audit_evidence_id')->withTrashed();
    }
}
