<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Stores immutable ARMIS provider health and cutover-verification snapshots. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('armis_provider_monitoring_checks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('check_uuid')->unique();
            $table->string('source_query_version', 40)->default('ARMIS-6D-v1');
            $table->string('provider_mode', 40);
            $table->string('configured_mode', 40);
            $table->string('overall_status', 20)->index();
            $table->jsonb('scope_snapshot');
            $table->jsonb('checks');
            $table->jsonb('provider_snapshot');
            $table->string('result_checksum_sha256', 64);
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('performed_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(
                ['provider_mode', 'overall_status', 'performed_at'],
                'armis_provider_monitoring_mode_status_date_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('armis_provider_monitoring_checks');
    }
};
