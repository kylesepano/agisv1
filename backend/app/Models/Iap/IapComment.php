<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stores management, reviewer, return, or revision comments on an IAP record.
 */
class IapComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'plan_id',
        'plan_engagement_id',
        'author_id',
        'comment_type_id',
        'parent_comment_id',
        'visibility',
        'body',
        'is_immutable',
    ];

    protected function casts(): array
    {
        return ['is_immutable' => 'boolean'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InternalAuditPlan::class, 'plan_id');
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(IapPlanEngagement::class, 'plan_engagement_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    public function commentType(): BelongsTo
    {
        return $this->belongsTo(MasterListItem::class, 'comment_type_id')->withTrashed();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_comment_id')->withTrashed();
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_comment_id');
    }
}
