<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Measurable implementation commitment copied with each controlled revision. */
class CmsActionPlanMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_action_plan_version_id',
        'sequence_number',
        'title',
        'description',
        'expected_output',
        'success_indicator',
        'verification_method',
        'responsible_office_id',
        'responsible_user_id',
        'planned_start_date',
        'planned_target_date',
        'weight_percentage',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'planned_start_date' => 'date',
            'planned_target_date' => 'date',
            'weight_percentage' => 'decimal:2',
            'display_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $assertDraft = function (self $milestone): void {
            $status = $milestone->version()->value('status_code');
            if ($status !== CmsActionPlanVersion::STATUS_DRAFT) {
                throw new LogicException(
                    'Submitted Action Plan milestones are immutable.',
                );
            }
        };
        static::updating($assertDraft);
        static::deleting($assertDraft);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(
            CmsActionPlanVersion::class,
            'cms_action_plan_version_id',
        );
    }

    public function responsibleOffice(): BelongsTo
    {
        return $this->belongsTo(
            Office::class,
            'responsible_office_id',
        )->withTrashed();
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id')->withTrashed();
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(
            CmsMilestoneProgress::class,
            'cms_action_plan_milestone_id',
        );
    }
}
