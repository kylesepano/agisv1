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
 * Represents one auditable subject, its owner, stakeholders, and audit classification.
 */
class IapAuditUniverseItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'subject_code',
        'name',
        'subject_type_id',
        'responsible_office_id',
        'primary_audit_area_id',
        'materiality_level_id',
        'description',
        'audit_scope',
        'materiality_exposure',
        'last_audit_date',
        'historical_audit_summary',
        'created_by',
        'updated_by',
        'lock_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_audit_date' => 'date',
            'lock_version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subjectType(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'subject_type_id')->withTrashed();
    }

    public function responsibleOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'responsible_office_id')->withTrashed();
    }

    public function primaryAuditArea(): BelongsTo
    {
        return $this->belongsTo(AuditArea::class, 'primary_audit_area_id')->withTrashed();
    }

    public function materialityLevel(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'materiality_level_id')->withTrashed();
    }

    public function stakeholderOffices(): BelongsToMany
    {
        return $this->belongsToMany(
            Office::class,
            'iap_audit_universe_stakeholders',
            'audit_universe_item_id',
            'office_id',
        )->withTimestamps();
    }

    public function auditHistory(): HasMany
    {
        return $this->hasMany(
            IapAuditUniverseHistory::class,
            'audit_universe_item_id',
        )->orderByDesc('audited_on');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
