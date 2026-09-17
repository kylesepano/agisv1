<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Represents one immutable methodology, scope, schedule, and resource version of an AEP.
 */
class AuditEngagementPlanVersion extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'audit_engagement_plan_id',
        'version_number',
        'objectives',
        'scope',
        'exclusions',
        'methodology',
        'audit_criteria',
        'materiality',
        'sampling_approach',
        'planned_start_date',
        'planned_end_date',
        'expected_report_date',
        'planned_person_days',
        'resource_requirements',
        'management_coordination',
        'linked_risk_snapshot',
        'confidentiality_level_id',
        'document_version_id',
        'checksum_sha256',
        'change_reason',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'planned_start_date' => 'date',
            'planned_end_date' => 'date',
            'expected_report_date' => 'date',
            'planned_person_days' => 'decimal:2',
            'resource_requirements' => 'array',
            'management_coordination' => 'array',
            'linked_risk_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('AEP versions are immutable.'));
        static::deleting(fn (): never => throw new LogicException('AEP versions cannot be deleted.'));
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AuditEngagementPlan::class, 'audit_engagement_plan_id')->withTrashed();
    }

    public function confidentialityLevel(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'confidentiality_level_id')->withTrashed();
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
