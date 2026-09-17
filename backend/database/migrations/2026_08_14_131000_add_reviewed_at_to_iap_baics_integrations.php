<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('iap_baics_integrations', 'reviewed_at')) {
            Schema::table('iap_baics_integrations', function (Blueprint $table): void {
                $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('iap_baics_integrations', 'reviewed_at')) {
            Schema::table('iap_baics_integrations', function (Blueprint $table): void {
                $table->dropColumn('reviewed_at');
            });
        }
    }
};
