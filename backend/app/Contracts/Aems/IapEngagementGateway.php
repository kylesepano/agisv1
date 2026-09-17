<?php

namespace App\Contracts\Aems;

use App\Models\IapPlanEngagement;
use Illuminate\Database\Eloquent\Collection;

/** Stable boundary through which AEMS consumes approved IAP engagement sources. */
interface IapEngagementGateway
{
    /** @return Collection<int, IapPlanEngagement> */
    public function eligibleForImport(): Collection;

    public function lockForImport(int $sourceId): IapPlanEngagement;

    public function markImported(IapPlanEngagement $source, int $engagementId): void;

    public function relink(int $sourceId, int $engagementId): void;

    /** @return array<string, mixed> */
    public function status(): array;
}
