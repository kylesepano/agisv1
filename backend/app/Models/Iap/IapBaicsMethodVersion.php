<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IapBaicsMethodVersion extends Model
{
    use HasFactory;
    protected $fillable = ['method_id', 'family_uuid', 'version_number', 'status', 'snapshot', 'snapshot_hash', 'reason', 'created_by'];
    protected function casts(): array { return ['snapshot' => 'array', 'version_number' => 'integer']; }
    public function method(): BelongsTo { return $this->belongsTo(IapBaicsMethod::class, 'method_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
}
