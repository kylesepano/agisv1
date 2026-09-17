<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IapBaicsException extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['assessment_id', 'component_id', 'reason', 'authority_user_id', 'compensating_evidence', 'expiry_date', 'status', 'created_by', 'reviewed_by', 'approved_by', 'reviewed_at', 'approved_at', 'immutable_at', 'version_number', 'lock_version'];
    protected function casts(): array { return ['expiry_date' => 'date', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'immutable_at' => 'datetime', 'version_number' => 'integer', 'lock_version' => 'integer']; }
    public function assessment(): BelongsTo { return $this->belongsTo(IapBaicsAssessment::class, 'assessment_id'); }
    public function component(): BelongsTo { return $this->belongsTo(IapBaicsComponent::class, 'component_id'); }
    public function authority(): BelongsTo { return $this->belongsTo(User::class, 'authority_user_id')->withTrashed(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by')->withTrashed(); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by')->withTrashed(); }
    public function versions(): HasMany { return $this->hasMany(IapBaicsExceptionVersion::class, 'exception_id')->orderByDesc('version_number'); }
}
