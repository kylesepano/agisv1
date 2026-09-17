<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class AemsPlanningPackageVersion extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    public const UPDATED_AT = null;
    protected $fillable = ['planning_package_id', 'version_number', 'preliminary_survey', 'planning_attributes', 'iap_lineage_snapshot', 'preliminary_survey_document_version_id', 'checksum_sha256', 'change_reason', 'created_by', 'created_at'];
    protected function casts(): array { return ['version_number' => 'integer', 'preliminary_survey' => 'array', 'planning_attributes' => 'array', 'iap_lineage_snapshot' => 'array', 'created_at' => 'datetime']; }
    protected static function booted(): void { static::updating(fn (): never => throw new LogicException('Planning package versions are immutable.')); static::deleting(fn (): never => throw new LogicException('Planning package versions cannot be deleted.')); }
    public function package(): BelongsTo { return $this->belongsTo(AemsPlanningPackage::class, 'planning_package_id')->withTrashed(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
    public function preliminarySurveyDocumentVersion(): BelongsTo { return $this->belongsTo(DocumentVersion::class, 'preliminary_survey_document_version_id'); }
    public function objectives(): HasMany { return $this->hasMany(AemsPlanningObjective::class, 'planning_package_version_id')->orderBy('sequence'); }
    public function processFlows(): HasMany { return $this->hasMany(AemsProcessFlowDocument::class, 'planning_package_version_id')->orderBy('sequence'); }
    public function riskMatrix(): HasOne { return $this->hasOne(AemsRiskMatrix::class, 'planning_package_version_id')->oldest('id'); }
    public function riskMatrices(): HasMany { return $this->hasMany(AemsRiskMatrix::class, 'planning_package_version_id')->orderBy('id'); }
    public function kpis(): HasMany { return $this->hasMany(AemsPlanningKpi::class, 'planning_package_version_id')->orderBy('sequence'); }
    public function plannedWorkingPaperRequirements(): HasMany { return $this->hasMany(AemsPlannedWorkingPaperRequirement::class, 'planning_package_version_id')->orderBy('sequence'); }
    public function reviews(): HasMany { return $this->hasMany(AemsPlanningPackageReview::class, 'planning_package_version_id'); }
}
