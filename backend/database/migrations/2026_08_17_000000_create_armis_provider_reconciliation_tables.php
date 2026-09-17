<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Stores immutable ARMIS/IAP reconciliation snapshots and authority decisions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('armis_provider_reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_uuid')->unique();
            $table->string('source_query_version', 40)->default('ARMIS-6B-v1');
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('provider_mode', 40);
            $table->string('status', 30)->default('GENERATED')->index();
            $table->jsonb('filters');
            $table->jsonb('scope_snapshot');
            $table->jsonb('result_snapshot');
            $table->jsonb('summary');
            $table->string('result_checksum_sha256', 64);
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(
                ['fiscal_year', 'provider_mode', 'generated_at'],
                'armis_reconciliation_fiscal_mode_date_idx',
            );
        });

        Schema::create('armis_provider_reconciliation_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reconciliation_run_id')
                ->constrained('armis_provider_reconciliation_runs')
                ->restrictOnDelete();
            $table->string('decision', 20);
            $table->jsonb('discrepancy_decisions');
            $table->text('comment');
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->useCurrent();
            $table->timestamps();

            $table->unique(
                'reconciliation_run_id',
                'armis_reconciliation_review_run_unique',
            );
        });

        Schema::create('armis_provider_authority_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reconciliation_run_id')->nullable()
                ->constrained('armis_provider_reconciliation_runs')
                ->restrictOnDelete();
            $table->string('decision_code', 30);
            $table->string('from_mode', 40);
            $table->string('to_mode', 40);
            $table->text('reason');
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(
                ['decision_code', 'decided_at'],
                'armis_authority_decision_code_date_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('armis_provider_authority_decisions');
        Schema::dropIfExists('armis_provider_reconciliation_reviews');
        Schema::dropIfExists('armis_provider_reconciliation_runs');
    }
};
