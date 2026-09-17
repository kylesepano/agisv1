<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class EngagementClosure extends Model
{
    public const STATUSES = [
        'DRAFT',
        'PENDING_REVIEW',
        'RETURNED_FOR_REVISION',
        'RESUBMITTED',
        'APPROVED',
        'CLOSED',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'final_document_index_complete' => 'boolean',
            'retention_metadata_complete' => 'boolean',
            'cms_transfer_complete' => 'boolean',
            'actual_person_days_complete' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
            'document_index_locked_at' => 'datetime',
            'approved_snapshot_json' => 'array',
            'closed_snapshot_json' => 'array',
            'lock_version' => 'integer',
            'revision_number' => 'integer',
            'is_current_revision' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $closure): void {
            if ($closure->getOriginal('status_code') === 'CLOSED') {
                throw new LogicException('Closed engagement records are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Engagement closure history cannot be deleted.'));
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_closure_id');
    }

    public function completionAssessment(): BelongsTo
    {
        return $this->belongsTo(CompletionAssessment::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(EngagementClosureChecklistItem::class)->orderBy('display_order');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EngagementClosureEvent::class)->orderBy('occurred_at');
    }

    public function documentIndexItems(): HasMany
    {
        return $this->hasMany(EngagementDocumentIndexItem::class);
    }

    public function retentionRecord(): HasOne
    {
        return $this->hasOne(EngagementRetentionRecord::class);
    }

    public function closureDocumentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'closure_document_version_id');
    }

    public function lessonsLearned(): HasMany
    {
        return $this->hasMany(EngagementLessonLearned::class);
    }
}
