<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize existing runtime configuration after the ARMIS ownership
     * cutover. Historical IAP/shadow values remain in immutable audit and
     * reconciliation snapshots, but cannot remain an active setting.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('system_configurations')) {
            return;
        }

        DB::table('system_configurations')
            ->where('key', 'armis_provider_mode')
            ->update(['value' => json_encode('ARMIS_AUTHORITATIVE', JSON_THROW_ON_ERROR), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Deliberately irreversible: ARMIS remains the sole operational
        // resource provider. Historical compatibility values are not restored.
    }
};
