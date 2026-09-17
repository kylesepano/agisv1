<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsClosureDecision extends Model
{
    protected $fillable = ['cms_closure_request_version_id', 'decision_code', 'decided_by', 'decided_at', 'decision_comment', 'override_reason', 'previous_case_status', 'new_case_status', 'closure_effective_date', 'final_snapshot'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'closure_effective_date' => 'date', 'final_snapshot' => 'array'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CmsClosureRequestVersion::class, 'cms_closure_request_version_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }
}
