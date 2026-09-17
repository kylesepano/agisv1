<?php

namespace Database\Seeders;

use App\Models\ArmisProviderAuthorityDecision;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\ArmisResourceBackfillService;
use Illuminate\Database\Seeder;

/** Seeds the local ARMIS authority baseline from the historical IAP demo plan. */
class ArmisResourcePlanningSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('username', 'agisadmin')->first()
            ?? User::query()->where('username', 'admin')->first();
        if (! $actor) {
            return;
        }

        app(ArmisResourceBackfillService::class)->backfill($actor, true);

        $run = app(ArmisResourceBackfillService::class)
            ->ensureAcceptedCutoverReconciliation($actor);

        if (! ArmisProviderAuthorityDecision::query()
            ->where('decision_code', 'ARMIS_RESOURCE_CUTOVER')
            ->where('to_mode', 'ARMIS_AUTHORITATIVE')
            ->exists()) {
            ArmisProviderAuthorityDecision::query()->create([
                'decision_code' => 'ARMIS_RESOURCE_CUTOVER',
                'reconciliation_run_id' => $run->id,
                'from_mode' => 'IAP_INTERIM_FALLBACK',
                'to_mode' => 'ARMIS_AUTHORITATIVE',
                'reason' => 'Local/demo resource ownership cutover. ARMIS is authoritative; IAP resource records are retained as read-only historical lineage.',
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);
        }

        $configuration = SystemConfiguration::query()->where('key', 'armis_provider_mode')->first();
        if ($configuration) {
            $configuration->value = 'ARMIS_AUTHORITATIVE';
            $configuration->updated_by = $actor->id;
            $configuration->save();
        }
    }
}
