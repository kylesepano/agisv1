<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class CmsReopeningRequest extends Model
{
    public const INITIATOR_RESPONSIBLE_OFFICE = 'RESPONSIBLE_OFFICE';

    public const INITIATOR_COMPLIANCE_MONITOR = 'COMPLIANCE_MONITOR';

    protected $fillable = [
        'cms_recommendation_case_id', 'request_sequence', 'initiator_type_code',
        'created_by', 'source_terminal_status', 'source_closure_decision_id',
        'source_disposition_decision_id', 'current_version_id', 'resolved_version_id',
        'resolved_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return ['request_sequence' => 'integer', 'resolved_at' => 'datetime', 'lock_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new LogicException('Reopening history cannot be deleted.'));
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CmsRecommendationCase::class, 'cms_recommendation_case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function sourceClosureDecision(): BelongsTo
    {
        return $this->belongsTo(CmsClosureDecision::class, 'source_closure_decision_id');
    }

    public function sourceDispositionDecision(): BelongsTo
    {
        return $this->belongsTo(CmsDispositionDecision::class, 'source_disposition_decision_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CmsReopeningRequestVersion::class)->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(CmsReopeningRequestVersion::class, 'current_version_id');
    }

    public function resolvedVersion(): BelongsTo
    {
        return $this->belongsTo(CmsReopeningRequestVersion::class, 'resolved_version_id');
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(CmsReopeningRequestVersion::class)->whereIn('status_code', CmsReopeningRequestVersion::ACTIVE_STATUSES);
    }

    public function getDisplayCodeAttribute(): string
    {
        return sprintf('ROP-CMS-REC-%06d-%03d', $this->cms_recommendation_case_id, $this->request_sequence);
    }
}
