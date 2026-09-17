<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CmsEscalationNoticeEvidenceLink extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['linked_at' => 'datetime', 'removed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $link): void {
            if ($link->getOriginal('removed_at') === null && $link->isDirty(array_diff(array_keys($link->getDirty()), ['removed_by', 'removed_at', 'removal_reason']))) {
                throw new LogicException('Submitted evidence links are immutable.');
            }
        });
    }

    public function noticeVersion(): BelongsTo
    {
        return $this->belongsTo(CmsEscalationNoticeVersion::class, 'cms_escalation_notice_version_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function confidentialityLevel(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'confidentiality_level_id')->withTrashed();
    }
}
