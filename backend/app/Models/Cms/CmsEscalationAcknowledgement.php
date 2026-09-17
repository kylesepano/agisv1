<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CmsEscalationAcknowledgement extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Acknowledgements are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Acknowledgements cannot be deleted.'));
    }

    public function escalation(): BelongsTo
    {
        return $this->belongsTo(CmsEscalation::class, 'cms_escalation_id');
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

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(CmsEscalationRecipient::class);
    }
}
