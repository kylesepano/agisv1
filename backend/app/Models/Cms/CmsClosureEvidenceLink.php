<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsClosureEvidenceLink extends Model
{
    protected $fillable = ['cms_closure_request_version_id', 'document_id', 'document_version_id', 'evidence_category', 'title', 'description', 'source_or_custodian', 'linked_by', 'linked_at', 'checksum_sha256', 'confidentiality_code_snapshot', 'removed_by', 'removed_at', 'removal_reason'];

    protected function casts(): array
    {
        return ['linked_at' => 'datetime', 'removed_at' => 'datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CmsClosureRequestVersion::class, 'cms_closure_request_version_id');
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }
}
