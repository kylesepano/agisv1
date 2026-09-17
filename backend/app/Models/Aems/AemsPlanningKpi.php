<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AemsPlanningKpi extends Model
{
    use HasFactory;

    protected $fillable = ['planning_package_version_id','kpi_code','name','target','measurement_method','source_reference','responsible_office_id','status','sequence'];
    protected function casts(): array { return ['sequence' => 'integer']; }
    protected static function booted(): void { static::updating(fn (): never => throw new LogicException('Planning KPI records are immutable within a planning version.')); static::deleting(fn (): never => throw new LogicException('Planning KPI records cannot be deleted.')); }
    public function version(): BelongsTo { return $this->belongsTo(AemsPlanningPackageVersion::class, 'planning_package_version_id'); }
    public function responsibleOffice(): BelongsTo { return $this->belongsTo(Office::class, 'responsible_office_id')->withTrashed(); }
}
