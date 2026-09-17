<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AemsPlannedWorkingPaperRequirement extends Model
{
    use HasFactory;

    protected $fillable = ['planning_package_version_id','audit_program_procedure_id','risk_matrix_item_id','working_paper_reference','title','objective','required_evidence','is_required','sequence'];
    protected function casts(): array { return ['is_required' => 'boolean', 'sequence' => 'integer']; }
    protected static function booted(): void { static::updating(fn (): never => throw new LogicException('Planned Working Paper requirements are immutable within a planning version.')); static::deleting(fn (): never => throw new LogicException('Planned Working Paper requirements cannot be deleted.')); }
    public function version(): BelongsTo { return $this->belongsTo(AemsPlanningPackageVersion::class, 'planning_package_version_id'); }
    public function procedure(): BelongsTo { return $this->belongsTo(AuditProgramProcedure::class, 'audit_program_procedure_id'); }
    public function riskItem(): BelongsTo { return $this->belongsTo(AemsRiskMatrixItem::class, 'risk_matrix_item_id'); }
}
