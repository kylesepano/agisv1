<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** One structured independent-validation procedure and result. */
class CmsValidationItem extends Model
{
    use HasFactory;

    public const SCOPES = ['RECOMMENDATION', 'MILESTONE'];

    public const CONCLUSIONS = [
        'SATISFIED',
        'PARTIALLY_SATISFIED',
        'NOT_SATISFIED',
        'INADEQUATE_BASIS',
        'NOT_APPLICABLE',
    ];

    protected $fillable = [
        'cms_validation_version_id',
        'scope_code',
        'cms_action_plan_milestone_id',
        'cms_milestone_progress_id',
        'sequence_number',
        'criterion',
        'procedure_performed',
        'population_or_source',
        'sample_description',
        'result_summary',
        'exception_summary',
        'item_conclusion_code',
        'validated_milestone_percentage',
        'follow_up_required',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'validated_milestone_percentage' => 'decimal:2',
            'follow_up_required' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $assertDraft = function (self $item): void {
            if ($item->version()->value('status_code') !== CmsValidationVersion::STATUS_DRAFT) {
                throw new LogicException('Submitted Validation Items are immutable.');
            }
        };
        static::updating($assertDraft);
        static::deleting($assertDraft);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CmsValidationVersion::class, 'cms_validation_version_id');
    }

    public function actionPlanMilestone(): BelongsTo
    {
        return $this->belongsTo(CmsActionPlanMilestone::class, 'cms_action_plan_milestone_id');
    }

    public function milestoneProgress(): BelongsTo
    {
        return $this->belongsTo(CmsMilestoneProgress::class, 'cms_milestone_progress_id');
    }

    public function evidenceAssessments(): HasMany
    {
        return $this->hasMany(CmsValidationEvidenceAssessment::class);
    }

    public function evidenceLinks(): HasMany
    {
        return $this->hasMany(CmsValidationEvidenceLink::class);
    }
}
