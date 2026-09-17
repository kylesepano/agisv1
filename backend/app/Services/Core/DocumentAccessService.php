<?php

namespace App\Services;

use App\Models\Document;
use App\Models\MasterListItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves document visibility and mutation rights from confidentiality and permissions.
 */
class DocumentAccessService
{
    /**
     * PUBLIC and INTERNAL are baseline classifications for authenticated users.
     * Higher classifications require explicit permissions seeded on the role.
     */
    public const PUBLIC_CODES = ['PUBLIC', 'INTERNAL'];

    /**
     * Apply the same discovery policy used by downloads and mutations.
     *
     * Uploaders retain access to their own record so a later classification
     * change cannot leave the responsible uploader unable to locate it.
     */
    public function visibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('documents.view_restricted')) {
            return $query;
        }

        return $query->where(function (Builder $visibility) use ($user): void {
            $visibility
                ->where('uploaded_by', $user->id)
                ->orWhereNull('confidentiality_level_id')
                ->orWhereHas(
                    'confidentialityLevel',
                    fn (Builder $level) => $level->whereIn(
                        'code',
                        $user->hasPermission('documents.view_confidential')
                            ? [...self::PUBLIC_CODES, 'CONFIDENTIAL']
                            : self::PUBLIC_CODES,
                    ),
                );
        });
    }

    public function authorizeView(User $user, Document $document): void
    {
        $visible = $this->visibleTo(
            Document::query()->withTrashed()->whereKey($document->id),
            $user,
        )->exists();

        throw_unless($visible, new HttpException(403, 'You are not authorized to access this document classification.'));
    }

    /**
     * Prevent a user from assigning a classification they cannot subsequently
     * administer. The request also validates that the item belongs to the
     * DOCUMENT_CONFIDENTIALITY master list.
     */
    public function authorizeClassification(User $user, MasterListItem $level): void
    {
        $allowed = match ($level->code) {
            'RESTRICTED' => $user->hasPermission('documents.view_restricted'),
            'CONFIDENTIAL' => $user->hasPermission('documents.view_confidential'),
            default => true,
        };

        throw_unless($allowed, new HttpException(403, 'You cannot assign this confidentiality level.'));
    }
}
