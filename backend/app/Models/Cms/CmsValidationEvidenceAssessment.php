<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Professional assessment of one exact management or validator evidence link. */
class CmsValidationEvidenceAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_validation_version_id',
        'cms_validation_item_id',
        'cms_progress_evidence_link_id',
        'cms_validation_evidence_link_id',
        'evidence_source_code',
        'relevance_code',
        'reliability_code',
        'sufficiency_code',
        'relied_upon',
        'assessment_summary',
        'limitation_summary',
        'assessed_by',
        'assessed_at',
    ];

    protected function casts(): array
    {
        return [
            'relied_upon' => 'boolean',
            'assessed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $assertDraft = function (self $assessment): void {
            if ($assessment->version()->value('status_code') !== CmsValidationVersion::STATUS_DRAFT) {
                throw new LogicException('Submitted Evidence Assessments are immutable.');
            }
        };
        static::updating($assertDraft);
        static::deleting($assertDraft);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CmsValidationVersion::class, 'cms_validation_version_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CmsValidationItem::class, 'cms_validation_item_id');
    }

    public function progressEvidenceLink(): BelongsTo
    {
        return $this->belongsTo(CmsProgressEvidenceLink::class, 'cms_progress_evidence_link_id');
    }

    public function validationEvidenceLink(): BelongsTo
    {
        return $this->belongsTo(CmsValidationEvidenceLink::class, 'cms_validation_evidence_link_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by')->withTrashed();
    }
}
