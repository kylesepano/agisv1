<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Exact immutable Core Document Version obtained by an independent validator. */
class CmsValidationEvidenceLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_validation_version_id',
        'cms_validation_item_id',
        'document_id',
        'document_version_id',
        'evidence_category',
        'title',
        'description',
        'source_or_custodian',
        'linked_by',
        'linked_at',
        'checksum_sha256',
        'confidentiality_level_id',
        'confidentiality_code_snapshot',
        'removed_by',
        'removed_at',
        'removal_reason',
    ];

    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $link): void {
            if ($link->version()->value('status_code') !== CmsValidationVersion::STATUS_DRAFT
                || array_diff(array_keys($link->getDirty()), [
                    'removed_by',
                    'removed_at',
                    'removal_reason',
                    'updated_at',
                ]) !== []) {
                throw new LogicException(
                    'Validation evidence links are immutable outside controlled draft removal.',
                );
            }
        });
        static::deleting(
            fn (): never => throw new LogicException('Validation evidence history cannot be deleted.'),
        );
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CmsValidationVersion::class, 'cms_validation_version_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CmsValidationItem::class, 'cms_validation_item_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function confidentialityLevel(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'confidentiality_level_id')->withTrashed();
    }

    public function linker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by')->withTrashed();
    }

    public function remover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by')->withTrashed();
    }
}
