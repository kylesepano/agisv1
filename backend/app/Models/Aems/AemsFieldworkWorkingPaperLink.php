<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AemsFieldworkWorkingPaperLink extends Model
{
    protected $table = 'aems_fieldwork_record_working_papers';

    use HasFactory;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Fieldwork Working Paper links are immutable.'));
        static::deleting(fn (): never => throw new \LogicException('Fieldwork Working Paper links cannot be deleted.'));
    }

    protected $fillable = ['fieldwork_record_version_id', 'working_paper_id', 'working_paper_version_id'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(AemsFieldworkRecordVersion::class, 'fieldwork_record_version_id');
    }

    public function workingPaper(): BelongsTo
    {
        return $this->belongsTo(WorkingPaper::class)->withTrashed();
    }

    public function workingPaperVersion(): BelongsTo
    {
        return $this->belongsTo(WorkingPaperVersion::class);
    }
}
