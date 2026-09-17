<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class CmsClosureRequest extends Model
{
    public const INITIATOR_RESPONSIBLE_OFFICE = 'RESPONSIBLE_OFFICE';

    public const INITIATOR_COMPLIANCE_MONITOR = 'COMPLIANCE_MONITOR';

    protected $fillable = ['cms_recommendation_case_id', 'request_sequence', 'initiator_type_code', 'created_by', 'current_version_id', 'resolved_version_id', 'resolved_at', 'lock_version'];

    protected function casts(): array
    {
        return ['request_sequence' => 'integer', 'resolved_at' => 'datetime', 'lock_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new LogicException('Closure history cannot be deleted.'));
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CmsRecommendationCase::class, 'cms_recommendation_case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CmsClosureRequestVersion::class)->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(CmsClosureRequestVersion::class, 'current_version_id');
    }

    public function resolvedVersion(): BelongsTo
    {
        return $this->belongsTo(CmsClosureRequestVersion::class, 'resolved_version_id');
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(CmsClosureRequestVersion::class)->whereIn('status_code', ['DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED', 'FOR_DECISION']);
    }

    public function getDisplayCodeAttribute(): string
    {
        return sprintf('CLS-CMS-REC-%06d-%03d', $this->cms_recommendation_case_id, $this->request_sequence);
    }
}
