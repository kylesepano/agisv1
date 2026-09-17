<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents an independent city office with no parent-office relationship.
 */
class Office extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'acronym',
        'office_type_id',
        'sector',
        'contact_number',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function officeType(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'office_type_id');
    }

    public function head(): HasOne
    {
        return $this->hasOne(User::class)->where('is_office_head', true);
    }

    public function auditAreas(): BelongsToMany
    {
        return $this->belongsToMany(AuditArea::class, 'audit_area_office')->withTimestamps();
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
