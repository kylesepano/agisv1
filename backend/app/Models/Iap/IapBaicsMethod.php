<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IapBaicsMethod extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = ['ICQ', 'INTERVIEW_INQUIRY_FGD', 'DOCUMENTARY_CRITERIA_REVIEW', 'PROCESS_NARRATIVE_FLOWCHART', 'WALKTHROUGH_OBSERVATION', 'TEST_OF_CONTROLS', 'OVERSIGHT_REPORT_REVIEW', 'INTERIM_ANALYSIS'];
    protected $fillable = ['component_id', 'family_uuid', 'version_number', 'method_type', 'title', 'description', 'performed_by', 'office_id', 'process_reference', 'performed_on', 'procedure', 'result', 'limitations', 'reviewer_id', 'status', 'reviewed_at', 'immutable_at', 'supersedes_id', 'is_current_revision', 'lock_version'];
    protected function casts(): array { return ['performed_on' => 'date', 'reviewed_at' => 'datetime', 'immutable_at' => 'datetime', 'is_current_revision' => 'boolean', 'version_number' => 'integer', 'lock_version' => 'integer']; }
    public function component(): BelongsTo { return $this->belongsTo(IapBaicsComponent::class, 'component_id'); }
    public function performer(): BelongsTo { return $this->belongsTo(User::class, 'performed_by')->withTrashed(); }
    public function office(): BelongsTo { return $this->belongsTo(Office::class, 'office_id')->withTrashed(); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id')->withTrashed(); }
    public function supersedes(): BelongsTo { return $this->belongsTo(self::class, 'supersedes_id')->withTrashed(); }
    public function evidenceLinks(): HasMany { return $this->hasMany(IapBaicsEvidenceLink::class, 'method_id')->orderBy('id'); }
    public function versions(): HasMany { return $this->hasMany(IapBaicsMethodVersion::class, 'method_id')->orderByDesc('version_number'); }
}
