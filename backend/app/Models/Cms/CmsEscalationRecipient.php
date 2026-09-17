<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CmsEscalationRecipient extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['selected_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Escalation recipients are immutable snapshots.'));
        static::deleting(fn (): never => throw new LogicException('Escalation recipients cannot be deleted.'));
    }

    public function noticeVersion(): BelongsTo
    {
        return $this->belongsTo(CmsEscalationNoticeVersion::class, 'cms_escalation_notice_version_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
