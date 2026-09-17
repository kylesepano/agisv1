<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Links an uploaded planning support file to a supported IAP record.
 */
class IapAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'plan_id',
        'plan_engagement_id',
        'risk_assessment_id',
        'document_id',
        'attachment_type_id',
        'display_name',
        'visibility',
        'uploaded_by',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InternalAuditPlan::class, 'plan_id');
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(IapPlanEngagement::class, 'plan_engagement_id');
    }

    public function riskAssessment(): BelongsTo
    {
        return $this->belongsTo(IapRiskAssessment::class, 'risk_assessment_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function attachmentType(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'attachment_type_id')->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}
