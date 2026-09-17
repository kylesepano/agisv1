<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/** Immutable source envelope created once from an issued AEMS recommendation. */
class CmsRecommendation extends Model
{
    use HasFactory;

    public const STATUS_TRANSFERRED = 'TRANSFERRED';

    public const SOURCE_SCHEMA_VERSION = 1;

    protected $fillable = [
        'source_audit_recommendation_id',
        'transfer_key',
        'audit_engagement_id',
        'audit_report_id',
        'audit_report_version_id',
        'report_code_snapshot',
        'report_version_number_snapshot',
        'report_issued_at',
        'report_issued_by',
        'report_checksum_sha256',
        'confidentiality_level_id',
        'confidentiality_code_snapshot',
        'confidentiality_label_snapshot',
        'audit_finding_id',
        'risk_rating_id',
        'risk_code_snapshot',
        'risk_label_snapshot',
        'recommendation_code',
        'source_snapshot',
        'responsible_office_id',
        'responsible_office_snapshot',
        'lead_responsible_office_id',
        'target_implementation_date',
        'original_target_implementation_date',
        'source_schema_version',
        'status',
        'transferred_at',
        'transferred_by',
    ];

    protected function casts(): array
    {
        return [
            'source_snapshot' => 'array',
            'responsible_office_snapshot' => 'array',
            'report_version_number_snapshot' => 'integer',
            'report_issued_at' => 'datetime',
            'target_implementation_date' => 'date',
            'original_target_implementation_date' => 'date',
            'source_schema_version' => 'integer',
            'transferred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('CMS intake records are immutable.'));
        static::deleting(fn (): never => throw new LogicException('CMS intake records cannot be deleted.'));
    }

    public function sourceRecommendation(): BelongsTo
    {
        return $this->belongsTo(
            AuditRecommendation::class,
            'source_audit_recommendation_id',
        )->withTrashed();
    }

    public function reportVersion(): BelongsTo
    {
        return $this->belongsTo(AuditReportVersion::class, 'audit_report_version_id');
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id')->withTrashed();
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(AuditReport::class, 'audit_report_id')->withTrashed();
    }

    public function reportIssuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'report_issued_by')->withTrashed();
    }

    public function confidentialityLevel(): BelongsTo
    {
        return $this->belongsTo(
            MasterListItem::class,
            'confidentiality_level_id',
        )->withTrashed();
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(AuditFinding::class, 'audit_finding_id')->withTrashed();
    }

    public function riskRating(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'risk_rating_id')->withTrashed();
    }

    public function responsibleOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'responsible_office_id')->withTrashed();
    }

    public function leadResponsibleOffice(): BelongsTo
    {
        return $this->belongsTo(
            Office::class,
            'lead_responsible_office_id',
        )->withTrashed();
    }

    public function transferActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by')->withTrashed();
    }

    public function case(): HasOne
    {
        return $this->hasOne(CmsRecommendationCase::class);
    }
}
