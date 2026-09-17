<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents governed document metadata; file content lives in immutable versions.
 */
class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_code',
        'document_type_id',
        'confidentiality_level_id',
        'title',
        'reference_number',
        'issuing_authority',
        'publication_date',
        'version',
        'description',
        'owner_module',
        'library_visible',
        'current_version_id',
        'original_file_name',
        'storage_path',
        'mime_type',
        'file_extension',
        'file_size',
        'checksum_sha256',
        'uploaded_by',
        'updated_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'file_size' => 'integer',
            'library_visible' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'document_type_id')->withTrashed();
    }

    public function confidentialityLevel(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'confidentiality_level_id')->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number');
    }

    public function links(): HasMany
    {
        return $this->hasMany(DocumentLink::class)->orderBy('module_code')->orderBy('record_label');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
