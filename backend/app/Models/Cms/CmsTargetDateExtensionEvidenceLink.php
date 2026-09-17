<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Exact Core Document Version pinned to an extension request version. */
class CmsTargetDateExtensionEvidenceLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_target_date_extension_version_id', 'document_id', 'document_version_id',
        'evidence_category', 'title', 'description', 'source_or_custodian',
        'linked_by', 'linked_at', 'checksum_sha256', 'confidentiality_level_id',
        'confidentiality_code_snapshot', 'removed_by', 'removed_at',
        'removal_reason',
    ];

    protected function casts(): array
    {
        return ['linked_at' => 'datetime', 'removed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $link): void {
            if ($link->getOriginal('removed_at') === null) {
                $allowed = ['removed_by', 'removed_at', 'removal_reason', 'updated_at'];
                if (array_diff(array_keys($link->getDirty()), $allowed) !== []) {
                    throw new LogicException('Submitted extension evidence links are immutable.');
                }
            }
        });
        static::deleting(fn (): never => throw new LogicException('Extension evidence links cannot be deleted.'));
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CmsTargetDateExtensionVersion::class, 'cms_target_date_extension_version_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class)->withTrashed();
    }

    public function confidentialityLevel(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'confidentiality_level_id')->withTrashed();
    }

    public function linker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by')->withTrashed();
    }
}
