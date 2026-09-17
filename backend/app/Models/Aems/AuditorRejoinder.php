<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Represents an auditor disposition and rejoinder to a versioned management response.
 */
class AuditorRejoinder extends Model
{
    use HasFactory, SoftDeletes;

    public const DISPOSITIONS = ['ACCEPT', 'PARTIALLY_ACCEPT', 'REJECT'];

    public const STATUSES = ['DRAFT', 'DIALOGUE_FINALIZED'];

    protected $fillable = [
        'management_response_id',
        'version_number',
        'disposition',
        'rejoinder',
        'status',
        'authored_by',
        'finalized_at',
        'finalized_by',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'finalized_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $rejoinder): void {
            if ($rejoinder->getOriginal('status') === 'DIALOGUE_FINALIZED') {
                throw new LogicException('Finalized auditor rejoinders are immutable.');
            }
        });
        static::deleting(function (self $rejoinder): void {
            if ($rejoinder->status === 'DIALOGUE_FINALIZED') {
                throw new LogicException('Finalized auditor rejoinders cannot be deleted.');
            }
        });
    }

    public function managementResponse(): BelongsTo
    {
        return $this->belongsTo(ManagementResponse::class)->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by')->withTrashed();
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by')->withTrashed();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AemsDialogueAttachment::class)
            ->orderBy('created_at');
    }
}
