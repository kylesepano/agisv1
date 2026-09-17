<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IapBaicsReport extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, SoftDeletes;
    public const STATUSES = ['DRAFT', 'PENDING_REVIEW', 'RETURNED', 'APPROVED', 'ISSUED', 'SUPERSEDED'];
    protected $table = 'iap_baics_reports';
    protected $fillable = ['assessment_id', 'report_code', 'title', 'status', 'executive_summary', 'objectives_scope_methodology', 'overall_findings', 'control_gap_summary', 'recommendations_summary', 'limitations_exceptions', 'source_manifest', 'prepared_by', 'reviewer_id', 'approved_by', 'issued_by', 'submitted_at', 'reviewed_at', 'approved_at', 'issued_at', 'superseded_at', 'supersedes_id', 'version_number', 'lock_version', 'is_current_revision'];
    protected function casts(): array { return ['source_manifest' => 'array', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'issued_at' => 'datetime', 'superseded_at' => 'datetime', 'version_number' => 'integer', 'lock_version' => 'integer', 'is_current_revision' => 'boolean']; }
    public function assessment(): BelongsTo { return $this->belongsTo(IapBaicsAssessment::class, 'assessment_id'); }
    public function preparer(): BelongsTo { return $this->belongsTo(User::class, 'prepared_by')->withTrashed(); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id')->withTrashed(); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by')->withTrashed(); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class, 'issued_by')->withTrashed(); }
    public function supersedes(): BelongsTo { return $this->belongsTo(self::class, 'supersedes_id')->withTrashed(); }
    public function controls(): BelongsToMany { return $this->belongsToMany(IapBaicsControl::class, 'iap_baics_report_controls', 'report_id', 'control_id')->withTimestamps(); }
    public function interimAnalyses(): BelongsToMany { return $this->belongsToMany(IapBaicsInterimAnalysis::class, 'iap_baics_report_interim_analyses', 'report_id', 'interim_analysis_id')->withTimestamps(); }
    public function versions(): HasMany { return $this->hasMany(IapBaicsReportVersion::class, 'report_id')->orderByDesc('version_number'); }
}
