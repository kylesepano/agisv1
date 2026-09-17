<?php

namespace App\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Refuses the CMS lineage foreign key when legacy AEMS pointers are orphaned.
 */
class CmsIntakeReferentialPreflight
{
    public static function assertNoOrphanedRecommendationPointers(
        ?ConnectionInterface $connection = null,
    ): void {
        $connection ??= DB::connection();
        $orphans = $connection->table('audit_recommendations as recommendation')
            ->leftJoin(
                'cms_recommendations as cms',
                'cms.id',
                '=',
                'recommendation.cms_recommendation_id',
            )
            ->whereNotNull('recommendation.cms_recommendation_id')
            ->whereNull('cms.id')
            ->orderBy('recommendation.id')
            ->get([
                'recommendation.id as recommendation_id',
                'recommendation.recommendation_code',
                'recommendation.cms_recommendation_id',
            ]);

        if ($orphans->isEmpty()) {
            return;
        }

        $details = $orphans
            ->map(
                fn (object $row): string => sprintf(
                    'recommendation %d (%s) -> CMS %d',
                    $row->recommendation_id,
                    $row->recommendation_code,
                    $row->cms_recommendation_id,
                ),
            )
            ->implode('; ');

        throw new RuntimeException(
            'Cannot add the AEMS-to-CMS foreign key because orphaned '
            ."cms_recommendation_id values exist: {$details}. "
            .'Create the missing immutable CMS intake records or formally correct '
            .'the source data under an approved migration before retrying.',
        );
    }
}
