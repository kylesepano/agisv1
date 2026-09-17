<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Represents a finding recommendation and its idempotent future transfer lineage to CMS.
 */
class AuditRecommendation extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['DRAFT', 'FINALIZED', 'TRANSFERRED', 'EXCLUDED'];

    protected $fillable = [
        'audit_finding_id',
        'recommendation_code',
        'recommendation',
        'responsible_office_id',
        'target_implementation_date',
        'status',
        'created_by',
        'updated_by',
        'finalized_at',
        'finalized_by',
        'finalized_snapshot',
        'cms_transfer_key',
        'cms_recommendation_id',
        'transferred_to_cms_at',
        'transferred_to_cms_by',
        'cms_exclusion_reason',
        'cms_exclusion_authority',
        'cms_excluded_by',
        'cms_excluded_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'target_implementation_date' => 'date',
            'finalized_at' => 'datetime',
            'finalized_snapshot' => 'array',
            'cms_recommendation_id' => 'integer',
            'transferred_to_cms_at' => 'datetime',
            'cms_excluded_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $recommendation): void {
            if (! in_array($recommendation->getOriginal('status'), ['FINALIZED', 'TRANSFERRED'], true)) {
                return;
            }
            $transferFields = [
                'status',
                'cms_transfer_key',
                'cms_recommendation_id',
                'transferred_to_cms_at',
                'transferred_to_cms_by',
                'cms_exclusion_reason',
                'cms_exclusion_authority',
                'cms_excluded_by',
                'cms_excluded_at',
                'lock_version',
                'updated_at',
            ];
            if (array_diff(array_keys($recommendation->getDirty()), $transferFields) !== []) {
                throw new LogicException('Finalized recommendation content is immutable.');
            }
        });
        static::deleting(function (self $recommendation): void {
            if (in_array($recommendation->status, ['FINALIZED', 'TRANSFERRED'], true)) {
                throw new LogicException('Finalized recommendations cannot be deleted.');
            }
        });
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(AuditFinding::class, 'audit_finding_id')->withTrashed();
    }

    public function responsibleOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'responsible_office_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function cmsRecommendation(): HasOne
    {
        return $this->hasOne(CmsRecommendation::class, 'source_audit_recommendation_id');
    }
}
