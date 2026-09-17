<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable snapshot of a BAICS-to-IAP consumption decision. */
class IapBaicsIntegrationVersion extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    protected $table = 'iap_baics_integration_versions';

    protected $fillable = ['integration_id', 'version_number', 'status', 'snapshot', 'snapshot_sha256', 'reason', 'created_by'];

    protected function casts(): array { return ['snapshot' => 'array', 'version_number' => 'integer']; }

    public function integration(): BelongsTo { return $this->belongsTo(IapBaicsIntegration::class, 'integration_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
}
