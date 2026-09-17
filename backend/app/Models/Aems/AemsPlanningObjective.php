<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AemsPlanningObjective extends Model { use HasFactory; protected $fillable = ['planning_package_version_id','objective_code','objective_statement','source_type','source_reference','sequence']; public function version(): BelongsTo { return $this->belongsTo(AemsPlanningPackageVersion::class,'planning_package_version_id'); } }
