<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IapBaicsControlVersion extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    protected $table = 'iap_baics_control_versions';
    protected $fillable = ['control_id', 'version_number', 'status', 'snapshot', 'snapshot_hash', 'reason', 'created_by'];
    protected function casts(): array { return ['snapshot' => 'array']; }
    public function control(): BelongsTo { return $this->belongsTo(IapBaicsControl::class, 'control_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
}
