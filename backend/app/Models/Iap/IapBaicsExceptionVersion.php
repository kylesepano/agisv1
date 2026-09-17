<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IapBaicsExceptionVersion extends Model
{
    use HasFactory;
    protected $fillable = ['exception_id', 'version_number', 'status', 'snapshot', 'snapshot_hash', 'reason', 'created_by'];
    protected function casts(): array { return ['snapshot' => 'array', 'version_number' => 'integer']; }
    public function exception(): BelongsTo { return $this->belongsTo(IapBaicsException::class, 'exception_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
}
