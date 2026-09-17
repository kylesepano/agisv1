<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CmsReopeningEvidenceLink extends Model
{
    protected $fillable = ['cms_reopening_request_version_id', 'document_id', 'document_version_id', 'evidence_category', 'title', 'description', 'source_or_custodian', 'linked_by', 'linked_at', 'checksum_sha256', 'confidentiality_code_snapshot', 'removed_by', 'removed_at', 'removal_reason'];

    protected function casts(): array
    {
        return ['linked_at' => 'datetime', 'removed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $link): void {
            if ($link->getOriginal('removed_at') === null && $link->getDirty() !== ['removed_by', 'removed_at', 'removal_reason', 'updated_at']) {
                throw new LogicException('Reopening evidence is immutable.');
            }
        });
        static::deleting(fn (): never => throw new LogicException('Reopening evidence cannot be deleted.'));
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CmsReopeningRequestVersion::class, 'cms_reopening_request_version_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function linker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by')->withTrashed();
    }
}
