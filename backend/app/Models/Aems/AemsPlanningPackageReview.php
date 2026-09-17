<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AemsPlanningPackageReview extends Model
{
    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $fillable = ['planning_package_id', 'planning_package_version_id', 'reviewer_id', 'result', 'comment', 'reviewed_at'];

    protected function casts(): array { return ['reviewed_at' => 'datetime']; }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Planning package reviews are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Planning package reviews cannot be deleted.'));
    }

    public function package(): BelongsTo { return $this->belongsTo(AemsPlanningPackage::class, 'planning_package_id'); }
    public function version(): BelongsTo { return $this->belongsTo(AemsPlanningPackageVersion::class, 'planning_package_version_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id')->withTrashed(); }
}
