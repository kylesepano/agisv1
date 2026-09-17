<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngagementLessonLearned extends Model
{
    protected $table = 'engagement_lessons_learned';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['target_date' => 'date'];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id');
    }

    public function closure(): BelongsTo
    {
        return $this->belongsTo(EngagementClosure::class, 'engagement_closure_id');
    }
}
