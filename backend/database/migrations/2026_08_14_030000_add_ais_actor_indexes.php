<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ais_report_runs', function (Blueprint $table): void {
            $table->index(['generated_by', 'generated_at'], 'ais_report_run_actor_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ais_report_runs', function (Blueprint $table): void {
            $table->dropIndex('ais_report_run_actor_date_idx');
        });
    }
};
