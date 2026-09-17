<?php

namespace App\Console\Commands;

use App\Models\ArmisProviderAuthorityDecision;
use App\Models\User;
use App\Services\ArmisResourceBackfillService;
use App\Services\RuntimeConfiguration;
use App\Models\SystemConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Migrates historical IAP resource ledgers into ARMIS without deleting IAP history. */
class ArmisResourceBackfillCommand extends Command
{
    protected $signature = 'armis:resource-backfill
        {--actor=agisadmin : Username recorded as the backfill actor}
        {--approve : Mark imported records as approved current ARMIS revisions}
        {--activate : Record the explicit ARMIS authority decision after backfill}';

    protected $description = 'Backfill IAP capacity, skills, availability, requirements, and workloads into ARMIS';

    public function handle(ArmisResourceBackfillService $backfill, RuntimeConfiguration $runtime): int
    {
        $actor = User::query()->where('username', (string) $this->option('actor'))->first();
        if (! $actor) {
            $this->error('The selected backfill actor was not found.');

            return self::FAILURE;
        }

        $approve = (bool) $this->option('approve') || (bool) $this->option('activate');
        $counts = $backfill->backfill($actor, $approve);
        $this->table(['Ledger', 'Imported'], collect($counts)->map(fn ($count, $ledger) => [$ledger, $count])->values()->all());

        if (! $this->option('activate')) {
            $this->info('ARMIS records were backfilled. Run again with --activate after independent review to cut over authority.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('armis_provider_authority_decisions')) {
            $this->error('ARMIS authority tables are not migrated.');

            return self::FAILURE;
        }

        $reconciliation = $backfill->ensureAcceptedCutoverReconciliation($actor);

        if (! ArmisProviderAuthorityDecision::query()
            ->where('decision_code', 'ARMIS_RESOURCE_CUTOVER')
            ->where('to_mode', 'ARMIS_AUTHORITATIVE')
            ->exists()) {
            ArmisProviderAuthorityDecision::query()->create([
                'reconciliation_run_id' => $reconciliation->id,
                'decision_code' => 'ARMIS_RESOURCE_CUTOVER',
                'from_mode' => $runtime->armisProviderMode(),
                'to_mode' => 'ARMIS_AUTHORITATIVE',
                'reason' => 'Explicit resource ownership cutover: ARMIS is now authoritative for resource profiles, competencies, capacity, availability, workload, and person-days. Historical IAP records remain read-only lineage.',
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);
        }

        $configuration = SystemConfiguration::query()->where('key', 'armis_provider_mode')->firstOrFail();
        $configuration->value = 'ARMIS_AUTHORITATIVE';
        $configuration->updated_by = $actor->id;
        $configuration->save();
        $runtime->forget();
        $this->info('ARMIS authority decision recorded. IAP resource ledgers remain preserved as historical lineage.');

        return self::SUCCESS;
    }
}
