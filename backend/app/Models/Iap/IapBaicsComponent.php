<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IapBaicsComponent extends Model
{
    use HasFactory, SoftDeletes;

    public const CODES = [
        'CONTROL_ENVIRONMENT', 'RISK_ASSESSMENT', 'CONTROL_ACTIVITIES',
        'INFORMATION_COMMUNICATION', 'MONITORING_EVALUATION',
    ];

    protected $fillable = ['assessment_id', 'component_code', 'status', 'conclusion', 'supporting_summary', 'limitations', 'assessor_id', 'reviewer_id', 'approved_by', 'reviewed_at', 'approved_at', 'immutable_at', 'version_number', 'lock_version'];
    protected function casts(): array { return ['reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'immutable_at' => 'datetime', 'version_number' => 'integer', 'lock_version' => 'integer']; }
    public function assessment(): BelongsTo { return $this->belongsTo(IapBaicsAssessment::class, 'assessment_id'); }
    public function assessor(): BelongsTo { return $this->belongsTo(User::class, 'assessor_id')->withTrashed(); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id')->withTrashed(); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by')->withTrashed(); }
    public function methods(): HasMany { return $this->hasMany(IapBaicsMethod::class, 'component_id')->where('is_current_revision', true)->orderBy('id'); }
    public function allMethods(): HasMany { return $this->hasMany(IapBaicsMethod::class, 'component_id')->orderBy('id'); }
    public function evidenceLinks(): HasMany { return $this->hasMany(IapBaicsEvidenceLink::class, 'component_id')->orderBy('id'); }
    public function exceptions(): HasMany { return $this->hasMany(IapBaicsException::class, 'component_id')->orderByDesc('id'); }
    public function versions(): HasMany { return $this->hasMany(IapBaicsComponentVersion::class, 'component_id')->orderByDesc('version_number'); }
}
