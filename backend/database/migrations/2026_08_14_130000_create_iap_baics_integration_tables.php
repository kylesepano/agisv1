<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** BAICS-4 read-only lineage and approval decisions for IAP consumers. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iap_baics_integrations', function (Blueprint $table): void {
            $table->id();
            $table->string('integration_code', 100)->unique();
            $table->foreignId('assessment_id')->nullable()->constrained('iap_baics_assessments')->restrictOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('iap_baics_reports')->restrictOnDelete();
            $table->foreignId('report_version_id')->nullable()->constrained('iap_baics_report_versions')->restrictOnDelete();
            $table->string('consumer_type', 60);
            $table->unsignedBigInteger('consumer_id');
            $table->string('decision_type', 40)->default('BAICS_BACKED');
            $table->string('status', 40)->default('DRAFT')->index();
            $table->text('decision_reason')->nullable();
            $table->text('legacy_reason')->nullable();
            $table->text('compensating_source')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('authority_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->json('consumer_snapshot')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->json('provider_snapshot')->nullable();
            $table->string('source_manifest_sha256', 64)->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['consumer_type', 'consumer_id'], 'iap_baics_integration_consumer_index');
            $table->index(['assessment_id', 'status'], 'iap_baics_integration_assessment_status_index');
        });

        Schema::create('iap_baics_integration_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_id')->constrained('iap_baics_integrations')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 40);
            $table->json('snapshot');
            $table->string('snapshot_sha256', 64);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['integration_id', 'version_number'], 'iap_baics_integration_version_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iap_baics_integration_versions');
        Schema::dropIfExists('iap_baics_integrations');
    }
};
