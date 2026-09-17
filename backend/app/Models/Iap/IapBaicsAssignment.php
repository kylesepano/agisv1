<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IapBaicsAssignment extends Model
{
    use HasFactory;
    protected $fillable = ['assessment_id', 'user_id', 'role_code', 'authority_level', 'assignment_reason', 'status', 'assigned_at', 'ended_at', 'assigned_by', 'lock_version'];
    protected function casts(): array { return ['assigned_at' => 'datetime', 'ended_at' => 'datetime', 'lock_version' => 'integer']; }
    public function assessment(): BelongsTo { return $this->belongsTo(IapBaicsAssessment::class, 'assessment_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class)->withTrashed(); }
    public function assigner(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by')->withTrashed(); }
}
