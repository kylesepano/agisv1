<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ais_integration_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('snapshot_code', 40)->unique();
            $table->string('contract_version', 30)->default('AIS-5B.0');
            $table->string('integration_contract_version', 30)->default('AIS-5A.0');
            $table->string('status', 40);
            $table->jsonb('scope_snapshot');
            $table->jsonb('source_statuses');
            $table->jsonb('reconciliation');
            $table->jsonb('diagnostics');
            $table->string('source_contract_hash_sha256', 64);
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at')->useCurrent()->index();
            $table->timestamps();
            $table->index(['generated_by', 'generated_at'], 'ais_integration_snapshot_actor_date_idx');
            $table->index(['status', 'generated_at'], 'ais_integration_snapshot_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ais_integration_snapshots');
    }
};
