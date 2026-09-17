<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CmsEscalationResponse extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['lock_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new LogicException('Escalation responses cannot be deleted.'));
    }

    public function escalation(): BelongsTo
    {
        return $this->belongsTo(CmsEscalation::class, 'cms_escalation_id');
    }

    public function issuedNoticeVersion(): BelongsTo
    {
        return $this->belongsTo(CmsEscalationNoticeVersion::class, 'issued_notice_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CmsEscalationResponseVersion::class)->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(CmsEscalationResponseVersion::class, 'current_version_id');
    }

    public function acceptedVersion(): BelongsTo
    {
        return $this->belongsTo(CmsEscalationResponseVersion::class, 'accepted_version_id');
    }
}
