<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Associates supporting evidence with a risk assessment without overwriting history.
 */
class IapRiskEvidence extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'assessment_id',
        'original_file_name',
        'storage_path',
        'mime_type',
        'file_extension',
        'file_size',
        'checksum_sha256',
        'uploaded_by',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(IapUniverseRiskAssessment::class, 'assessment_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}
