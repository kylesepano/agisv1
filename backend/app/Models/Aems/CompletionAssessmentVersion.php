<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CompletionAssessmentVersion extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshot_json' => 'array',
            'created_at' => 'datetime',
            'version_no' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Completion Assessment versions are immutable.'));
        static::deleting(fn () => throw new LogicException('Completion Assessment versions cannot be deleted.'));
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(CompletionAssessment::class, 'completion_assessment_id');
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }
}
