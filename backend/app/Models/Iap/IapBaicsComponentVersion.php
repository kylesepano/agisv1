<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IapBaicsComponentVersion extends Model
{
    use HasFactory;
    protected $fillable = ['component_id', 'component_code', 'version_number', 'status', 'snapshot', 'snapshot_hash', 'reason', 'created_by'];
    protected function casts(): array { return ['snapshot' => 'array', 'version_number' => 'integer']; }
    public function component(): BelongsTo { return $this->belongsTo(IapBaicsComponent::class, 'component_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
}
