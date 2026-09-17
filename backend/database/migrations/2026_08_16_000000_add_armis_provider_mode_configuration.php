<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_configurations')) {
            return;
        }

        DB::table('system_configurations')->updateOrInsert(
            ['key' => 'armis_provider_mode'],
            [
                'name' => 'ARMIS Provider Mode',
                'value' => json_encode('IAP_INTERIM_FALLBACK', JSON_THROW_ON_ERROR),
                'type' => 'string',
                'group' => 'integrations',
                'description' => 'ARMIS-6A provider mode. Shadow mode preserves IAP as the active AEMS provider until reconciliation and an explicit authority gate are complete.',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('system_configurations')) {
            DB::table('system_configurations')->where('key', 'armis_provider_mode')->delete();
        }
    }
};
