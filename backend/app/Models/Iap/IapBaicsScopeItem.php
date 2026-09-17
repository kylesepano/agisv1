<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IapBaicsScopeItem extends Model
{
    use HasFactory;
    protected $fillable = ['assessment_id', 'audit_universe_item_id', 'office_id', 'audit_area_id', 'audit_focus_id', 'source_snapshot', 'scope_notes', 'boundaries', 'exclusions', 'limitations', 'created_by'];
    protected function casts(): array { return ['source_snapshot' => 'array']; }
    public function assessment(): BelongsTo { return $this->belongsTo(IapBaicsAssessment::class, 'assessment_id'); }
    public function auditUniverseItem(): BelongsTo { return $this->belongsTo(IapAuditUniverseItem::class, 'audit_universe_item_id')->withTrashed(); }
    public function office(): BelongsTo { return $this->belongsTo(Office::class)->withTrashed(); }
    public function auditArea(): BelongsTo { return $this->belongsTo(AuditArea::class)->withTrashed(); }
    public function auditFocus(): BelongsTo { return $this->belongsTo(AuditFocus::class)->withTrashed(); }
}
