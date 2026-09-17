<?php

namespace App\Contracts\Aems;

use App\Models\AuditEngagement;
use App\Models\EngagementClosure;
use App\Models\EngagementRetentionRecord;
use App\Models\User;

/**
 * Replaceable boundary for the interim AEMS retention record.
 *
 * A future Core Records Management provider may replace this implementation
 * without changing preserved AEMS snapshots or the closure service contract.
 */
interface EngagementRetentionProvider
{
    /** @param array<string, mixed> $attributes */
    public function save(
        AuditEngagement $engagement,
        ?EngagementClosure $closure,
        User $actor,
        array $attributes,
    ): EngagementRetentionRecord;

    public function approve(
        EngagementRetentionRecord $record,
        User $actor,
        int $lockVersion,
    ): EngagementRetentionRecord;

    /** @return array{ready: bool, blockers: list<string>} */
    public function readiness(?EngagementRetentionRecord $record): array;
}
