<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ais_aggregation_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('snapshot_code', 40)->unique();
            $table->string('contract_version', 30)->default('AIS-1.0');
            $table->string('source_query_version', 40)->default('AIS-1-v1');
            $table->jsonb('scope_snapshot');
            $table->jsonb('source_versions');
            $table->jsonb('metrics');
            $table->string('metrics_checksum_sha256', 64);
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at')->useCurrent()->index();
            $table->timestamps();
            $table->index(['generated_by', 'generated_at'], 'ais_snapshot_actor_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ais_aggregation_snapshots');
    }
};
