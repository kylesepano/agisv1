<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a reusable, optionally hierarchical audit classification linked to offices.
 */
class AuditArea extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_audit_area_id',
        'audit_area_type_id',
        'responsible_office_id',
        'code',
        'name',
        'description',
        'scope',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function offices(): BelongsToMany
    {
        return $this->belongsToMany(Office::class, 'audit_area_office')->withTimestamps();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_audit_area_id')->withTrashed();
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_audit_area_id')->orderBy('name');
    }

    public function auditAreaType(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'audit_area_type_id');
    }

    public function responsibleOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'responsible_office_id')->withTrashed();
    }

    public function focuses(): HasMany
    {
        return $this->hasMany(AuditFocus::class)->orderBy('display_order');
    }

    public function engagements(): BelongsToMany
    {
        return $this->belongsToMany(
            IapPlanEngagement::class,
            'iap_engagement_audit_areas',
            'audit_area_id',
            'plan_engagement_id',
        )->withTimestamps();
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
