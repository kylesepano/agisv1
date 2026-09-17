<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IapBaicsControl extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, SoftDeletes;

    public const CONTROL_STATUSES = [
        'Existing', 'Partially Designed', 'Not Designed', 'Operating Effectively',
        'Partially Effective', 'Not Operating', 'Control Gap', 'Deficiency', 'Breakdown',
    ];
    public const TYPES = ['PREVENTIVE', 'DETECTIVE', 'HYBRID'];
    public const EXECUTION_MODES = ['MANUAL', 'AUTOMATED', 'HYBRID'];
    public const WORKFLOW_STATUSES = ['DRAFT', 'PENDING_REVIEW', 'RETURNED', 'APPROVED'];
    public const CLASSIFICATIONS = ['GAP', 'DEFICIENCY', 'BREAKDOWN', 'CONTRADICTION'];

    protected $table = 'iap_baics_controls';
    protected $fillable = [
        'assessment_id', 'scope_item_id', 'component_id', 'control_code', 'process_step',
        'responsible_unit', 'control_owner_office_id', 'control_owner_user_id', 'objective',
        'related_risk', 'control_description', 'expected_result', 'control_type', 'execution_mode',
        'frequency', 'evidence_produced', 'approval_required', 'segregation_of_duties_required',
        'design_assessment', 'operating_assessment', 'control_status', 'deficiency_classification',
        'limitation_details', 'gap_details', 'breakdown_details', 'contradiction_details',
        'recommendation_action', 'status', 'prepared_by', 'reviewer_id', 'approved_by',
        'reviewed_at', 'approved_at', 'immutable_at', 'version_number', 'lock_version',
        'supersedes_id', 'is_current_revision',
    ];

    protected function casts(): array
    {
        return [
            'approval_required' => 'boolean', 'segregation_of_duties_required' => 'boolean',
            'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'immutable_at' => 'datetime',
            'version_number' => 'integer', 'lock_version' => 'integer', 'is_current_revision' => 'boolean',
        ];
    }

    public function assessment(): BelongsTo { return $this->belongsTo(IapBaicsAssessment::class, 'assessment_id'); }
    public function scopeItem(): BelongsTo { return $this->belongsTo(IapBaicsScopeItem::class, 'scope_item_id'); }
    public function component(): BelongsTo { return $this->belongsTo(IapBaicsComponent::class, 'component_id'); }
    public function controlOwnerOffice(): BelongsTo { return $this->belongsTo(Office::class, 'control_owner_office_id')->withTrashed(); }
    public function controlOwner(): BelongsTo { return $this->belongsTo(User::class, 'control_owner_user_id')->withTrashed(); }
    public function preparer(): BelongsTo { return $this->belongsTo(User::class, 'prepared_by')->withTrashed(); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id')->withTrashed(); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by')->withTrashed(); }
    public function supersedes(): BelongsTo { return $this->belongsTo(self::class, 'supersedes_id')->withTrashed(); }
    public function methods(): BelongsToMany { return $this->belongsToMany(IapBaicsMethod::class, 'iap_baics_control_methods', 'control_id', 'method_id')->withTimestamps(); }
    public function evidenceLinks(): BelongsToMany { return $this->belongsToMany(IapBaicsEvidenceLink::class, 'iap_baics_control_evidence', 'control_id', 'evidence_link_id')->withTimestamps(); }
    public function versions(): HasMany { return $this->hasMany(IapBaicsControlVersion::class, 'control_id')->orderByDesc('version_number'); }
}
