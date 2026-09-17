<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Immutable content snapshot for an Evidence Request. */
class AemsEvidenceRequestVersion extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'aems_evidence_request_versions';
    protected $fillable = ['evidence_request_id', 'version_number', 'title', 'purpose', 'requested_from_office_id', 'requested_from_user_id', 'due_date', 'requested_items', 'change_reason', 'created_by', 'created_at'];
    protected function casts(): array { return ['version_number' => 'integer', 'due_date' => 'date', 'requested_items' => 'array', 'created_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Evidence Request versions are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Evidence Request versions cannot be deleted.'));
    }
    public function request(): BelongsTo { return $this->belongsTo(AemsEvidenceRequest::class, 'evidence_request_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
    public function requestedFromOffice(): BelongsTo { return $this->belongsTo(Office::class, 'requested_from_office_id')->withTrashed(); }
}
