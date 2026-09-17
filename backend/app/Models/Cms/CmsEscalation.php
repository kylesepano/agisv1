<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class CmsEscalation extends Model
{
    use HasFactory;

    public const STATUS_PREPARATION = 'PREPARATION';

    public const STATUS_AWAITING_ISSUANCE = 'AWAITING_ISSUANCE';

    public const STATUS_ISSUED = 'ISSUED';

    public const STATUS_ACKNOWLEDGED = 'ACKNOWLEDGED';

    public const STATUS_AWAITING_RESPONSE = 'AWAITING_RESPONSE';

    public const STATUS_RESPONSE_UNDER_REVIEW = 'RESPONSE_UNDER_REVIEW';

    public const STATUS_FOLLOW_UP = 'FOLLOW_UP';

    public const STATUS_RESOLVED = 'RESOLVED';

    public const TRIGGERS = [
        'OVERDUE_TARGET', 'MISSING_PROGRESS_UPDATE', 'REPEATED_PROGRESS_RETURN',
        'INADEQUATE_MANAGEMENT_RESPONSE', 'VALIDATION_NOT_IMPLEMENTED',
        'VALIDATION_INADEQUATE_BASIS', 'REPEATED_EXTENSION_REQUEST',
        'FAILURE_TO_COMPLETE_REQUIRED_ACTION', 'MANAGEMENT_NON_RESPONSE', 'OTHER',
    ];

    protected $fillable = [
        'cms_recommendation_case_id', 'escalation_sequence', 'primary_trigger_code',
        'trigger_snapshot', 'source_effective_target_date', 'source_case_status',
        'operational_status_code', 'created_by', 'current_notice_version_id',
        'issued_notice_version_id', 'current_response_id', 'resolved_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return ['trigger_snapshot' => 'array', 'source_effective_target_date' => 'date', 'resolved_at' => 'datetime', 'lock_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new LogicException('CMS escalations cannot be deleted.'));
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CmsRecommendationCase::class, 'cms_recommendation_case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function noticeVersions(): HasMany
    {
        return $this->hasMany(CmsEscalationNoticeVersion::class)->orderByDesc('version_number');
    }

    public function currentNotice(): BelongsTo
    {
        return $this->belongsTo(CmsEscalationNoticeVersion::class, 'current_notice_version_id');
    }

    public function issuedNotice(): BelongsTo
    {
        return $this->belongsTo(CmsEscalationNoticeVersion::class, 'issued_notice_version_id');
    }

    public function response(): HasOne
    {
        return $this->hasOne(CmsEscalationResponse::class);
    }

    public function currentResponse(): BelongsTo
    {
        return $this->belongsTo(CmsEscalationResponse::class, 'current_response_id');
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(CmsEscalationAcknowledgement::class);
    }

    public function resolution(): HasOne
    {
        return $this->hasOne(CmsEscalationResolution::class);
    }

    public function getDisplayCodeAttribute(): string
    {
        return sprintf('ESC-CMS-REC-%06d-%03d', $this->cms_recommendation_case_id, $this->escalation_sequence);
    }
}
