<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Management's report against one immutable accepted Action Plan milestone. */
class CmsMilestoneProgress extends Model
{
    use HasFactory;

    protected $table = 'cms_milestone_progress';

    public const STATUSES = [
        'NOT_STARTED',
        'IN_PROGRESS',
        'REPORTED_COMPLETED',
        'DELAYED',
        'ON_HOLD',
    ];

    protected $fillable = [
        'cms_progress_update_version_id',
        'cms_action_plan_milestone_id',
        'milestone_sequence',
        'milestone_snapshot',
        'management_reported_status_code',
        'management_reported_percentage',
        'accomplishment_description',
        'issues_and_constraints',
        'next_step',
        'forecast_completion_date',
        'no_evidence_explanation',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'milestone_sequence' => 'integer',
            'milestone_snapshot' => 'array',
            'management_reported_percentage' => 'decimal:2',
            'forecast_completion_date' => 'date',
            'display_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $assertDraft = function (self $progress): void {
            if ($progress->version()->value('status_code') !== CmsProgressUpdateVersion::STATUS_DRAFT) {
                throw new LogicException(
                    'Submitted milestone progress is immutable.',
                );
            }
        };
        static::updating($assertDraft);
        static::deleting($assertDraft);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(
            CmsProgressUpdateVersion::class,
            'cms_progress_update_version_id',
        );
    }

    public function actionPlanMilestone(): BelongsTo
    {
        return $this->belongsTo(
            CmsActionPlanMilestone::class,
            'cms_action_plan_milestone_id',
        );
    }

    public function evidenceLinks(): HasMany
    {
        return $this->hasMany(CmsProgressEvidenceLink::class, 'cms_milestone_progress_id')
            ->whereNull('removed_at');
    }
}
