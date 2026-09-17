<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A controlled BAICS cycle/version that is owned by IAP. */
class IapBaicsAssessment extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['DRAFT', 'PLANNING', 'IN_PROGRESS', 'PENDING_REVIEW', 'RETURNED', 'RESUBMITTED', 'APPROVED', 'PUBLISHED', 'ARCHIVED'];

    protected $fillable = [
        'family_uuid', 'assessment_code', 'version_number', 'assessment_year', 'name', 'status',
        'responsible_office_id', 'scope_summary', 'objectives', 'boundaries', 'exclusions', 'limitations',
        'methodology', 'planned_start_date', 'planned_end_date', 'review_date', 'report_date',
        'legacy_status', 'legacy_reason', 'legacy_authority_user_id', 'legacy_expires_at', 'prepared_by',
        'submitted_by', 'reviewed_by', 'approved_by', 'published_by', 'submitted_at', 'reviewed_at',
        'approved_at', 'published_at', 'archived_at', 'supersedes_id', 'is_current_revision', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'assessment_year' => 'integer', 'version_number' => 'integer', 'planned_start_date' => 'date',
            'planned_end_date' => 'date', 'review_date' => 'date', 'report_date' => 'date',
            'legacy_expires_at' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime',
            'approved_at' => 'datetime', 'published_at' => 'datetime', 'archived_at' => 'datetime',
            'is_current_revision' => 'boolean', 'lock_version' => 'integer',
        ];
    }

    public function responsibleOffice(): BelongsTo { return $this->belongsTo(Office::class, 'responsible_office_id')->withTrashed(); }
    public function preparer(): BelongsTo { return $this->belongsTo(User::class, 'prepared_by')->withTrashed(); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by')->withTrashed(); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by')->withTrashed(); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by')->withTrashed(); }
    public function publisher(): BelongsTo { return $this->belongsTo(User::class, 'published_by')->withTrashed(); }
    public function supersedes(): BelongsTo { return $this->belongsTo(self::class, 'supersedes_id')->withTrashed(); }
    public function scopeItems(): HasMany { return $this->hasMany(IapBaicsScopeItem::class, 'assessment_id')->orderBy('id'); }
    public function assignments(): HasMany { return $this->hasMany(IapBaicsAssignment::class, 'assessment_id')->orderBy('id'); }
    public function versions(): HasMany { return $this->hasMany(IapBaicsVersion::class, 'assessment_id')->orderByDesc('version_number'); }
    public function latestVersion(): HasOne { return $this->hasOne(IapBaicsVersion::class, 'assessment_id')->latestOfMany('version_number'); }
    public function components(): HasMany { return $this->hasMany(IapBaicsComponent::class, 'assessment_id')->orderBy('id'); }
    public function exceptions(): HasMany { return $this->hasMany(IapBaicsException::class, 'assessment_id')->orderByDesc('id'); }
    public function controls(): HasMany { return $this->hasMany(IapBaicsControl::class, 'assessment_id')->where('is_current_revision', true)->orderBy('control_code'); }
    public function allControls(): HasMany { return $this->hasMany(IapBaicsControl::class, 'assessment_id')->orderBy('control_code'); }
    public function interimAnalyses(): HasMany { return $this->hasMany(IapBaicsInterimAnalysis::class, 'assessment_id')->orderByDesc('id'); }
    public function reports(): HasMany { return $this->hasMany(IapBaicsReport::class, 'assessment_id')->orderByDesc('id'); }
    public function integrations(): HasMany { return $this->hasMany(IapBaicsIntegration::class, 'assessment_id')->orderByDesc('id'); }
}
