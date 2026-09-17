<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IapBaicsInterimAnalysisVersion extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    protected $table = 'iap_baics_interim_analysis_versions';
    protected $fillable = ['interim_analysis_id', 'version_number', 'status', 'snapshot', 'snapshot_hash', 'reason', 'created_by'];
    protected function casts(): array { return ['snapshot' => 'array']; }
    public function interimAnalysis(): BelongsTo { return $this->belongsTo(IapBaicsInterimAnalysis::class, 'interim_analysis_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
}
