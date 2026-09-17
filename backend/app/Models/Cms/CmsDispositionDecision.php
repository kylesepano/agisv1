<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CmsDispositionDecision extends Model
{
    protected $fillable = ['cms_disposition_request_version_id', 'decision_code', 'decided_by', 'decided_at', 'decision_comment', 'override_reason', 'previous_case_status', 'new_case_status', 'effective_date', 'final_snapshot'];
    protected function casts(): array { return ['decided_at' => 'datetime', 'effective_date' => 'date', 'final_snapshot' => 'array']; }
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Disposition decisions are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Disposition decisions cannot be deleted.'));
    }
    public function version(): BelongsTo { return $this->belongsTo(CmsDispositionRequestVersion::class, 'cms_disposition_request_version_id'); }
    public function decider(): BelongsTo { return $this->belongsTo(User::class, 'decided_by')->withTrashed(); }
}
