<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IapBaicsInterimAnalysis extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory, SoftDeletes;
    public const STATUSES = ['DRAFT', 'PENDING_REVIEW', 'RETURNED', 'APPROVED'];
    protected $table = 'iap_baics_interim_analyses';
    protected $fillable = ['assessment_id', 'analysis_code', 'title', 'analysis_period_start', 'analysis_period_end', 'analysis_narrative', 'findings_summary', 'recommendations_summary', 'limitations', 'source_manifest', 'status', 'prepared_by', 'reviewer_id', 'approved_by', 'reviewed_at', 'approved_at', 'immutable_at', 'version_number', 'lock_version'];
    protected function casts(): array { return ['analysis_period_start' => 'date', 'analysis_period_end' => 'date', 'source_manifest' => 'array', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'immutable_at' => 'datetime', 'version_number' => 'integer', 'lock_version' => 'integer']; }
    public function assessment(): BelongsTo { return $this->belongsTo(IapBaicsAssessment::class, 'assessment_id'); }
    public function preparer(): BelongsTo { return $this->belongsTo(User::class, 'prepared_by')->withTrashed(); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id')->withTrashed(); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by')->withTrashed(); }
    public function versions(): HasMany { return $this->hasMany(IapBaicsInterimAnalysisVersion::class, 'interim_analysis_id')->orderByDesc('version_number'); }
}
