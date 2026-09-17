<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IapBaicsEvidenceLink extends Model
{
    use HasFactory;
    protected $fillable = ['component_id', 'method_id', 'document_version_id', 'evidence_role', 'description', 'created_by'];
    public function component(): BelongsTo { return $this->belongsTo(IapBaicsComponent::class, 'component_id'); }
    public function method(): BelongsTo { return $this->belongsTo(IapBaicsMethod::class, 'method_id'); }
    public function documentVersion(): BelongsTo { return $this->belongsTo(DocumentVersion::class, 'document_version_id')->with('document'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
}
