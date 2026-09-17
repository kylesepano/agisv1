<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Immutable, reviewable BAICS baseline decision consumed by an IAP record. */
class IapBaicsIntegration extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    public const CONSUMER_TYPES = [
        'RISK_PERIOD',
        'UNIVERSE_RISK_ASSESSMENT',
        'PRIORITIZATION_RUN',
        'STRATEGIC_PLAN',
        'ANNUAL_PLAN',
        'ANNUAL_PLAN_ENGAGEMENT',
    ];

    public const DECISION_TYPES = ['BAICS_BACKED', 'LEGACY_EXCEPTION'];

    public const STATUSES = ['DRAFT', 'PENDING_REVIEW', 'RETURNED', 'APPROVED', 'RETIRED'];

    protected $table = 'iap_baics_integrations';

    protected $fillable = [
        'integration_code', 'assessment_id', 'report_id', 'report_version_id',
        'consumer_type', 'consumer_id', 'decision_type', 'status', 'decision_reason',
        'legacy_reason', 'compensating_source', 'reviewer_id', 'authority_user_id',
        'approved_by', 'created_by', 'submitted_at', 'reviewed_at', 'approved_at', 'retired_at',
        'expires_at', 'consumer_snapshot', 'source_snapshot', 'provider_snapshot',
        'source_manifest_sha256', 'version_number', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'consumer_snapshot' => 'array',
            'source_snapshot' => 'array',
            'provider_snapshot' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'retired_at' => 'datetime',
            'expires_at' => 'date',
            'version_number' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function assessment(): BelongsTo { return $this->belongsTo(IapBaicsAssessment::class, 'assessment_id')->withTrashed(); }
    public function report(): BelongsTo { return $this->belongsTo(IapBaicsReport::class, 'report_id')->withTrashed(); }
    public function reportVersion(): BelongsTo { return $this->belongsTo(IapBaicsReportVersion::class, 'report_version_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id')->withTrashed(); }
    public function authority(): BelongsTo { return $this->belongsTo(User::class, 'authority_user_id')->withTrashed(); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by')->withTrashed(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
    public function versions(): HasMany { return $this->hasMany(IapBaicsIntegrationVersion::class, 'integration_id')->orderByDesc('version_number'); }

    public function isUsable(): bool
    {
        return $this->status === 'APPROVED'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
