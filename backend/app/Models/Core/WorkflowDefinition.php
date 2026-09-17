<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Defines a reusable, versioned workflow for a module and record type.
 */
class WorkflowDefinition extends Model
{
    use HasFactory, SoftDeletes;

    public const MODULES = ['CORE', 'IAP', 'AEM', 'AFR', 'CMS', 'ARMIS', 'AIS'];

    public const STATUSES = ['DRAFT', 'PUBLISHED', 'RETIRED'];

    protected $fillable = [
        'code',
        'name',
        'module_code',
        'subject_type',
        'version',
        'description',
        'status',
        'is_active',
        'created_by',
        'updated_by',
        'published_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('sequence');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class)->orderBy('sequence');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class);
    }

    public function activeInstances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class)->where('status', 'ACTIVE');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by')->withTrashed();
    }
}
