<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Stores a versioned competency claim and its exact Core document evidence. */
class ArmisCompetency extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['DRAFT', 'RETURNED', 'PENDING_VERIFICATION', 'VERIFIED', 'EXPIRED', 'REVOKED'];

    public const PROFICIENCY_LEVELS = ['BASIC', 'INTERMEDIATE', 'ADVANCED', 'EXPERT'];

    protected $fillable = [
        'competency_family_uuid', 'resource_profile_id', 'competency_id', 'version_number',
        'supersedes_id', 'is_current_revision', 'proficiency_level', 'credential_type',
        'credential_reference', 'issuer', 'issued_at', 'status', 'evidence_document_version_id',
        'verified_by', 'verified_at', 'expires_at', 'submitted_by', 'submitted_at',
        'reviewed_by', 'reviewed_at', 'notes', 'verification_notes', 'created_by',
        'updated_by', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'is_current_revision' => 'boolean',
            'issued_at' => 'date',
            'verified_at' => 'datetime',
            'expires_at' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function resourceProfile(): BelongsTo
    {
        return $this->belongsTo(ArmisResourceProfile::class, 'resource_profile_id');
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'competency_id')->withTrashed();
    }

    public function evidenceDocumentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'evidence_document_version_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id')->withTrashed();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'competency_family_uuid', 'competency_family_uuid')
            ->orderByDesc('version_number');
    }
}
