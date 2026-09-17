<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** BAICS-3 Baseline Assessment Report assembly and immutable protected versions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iap_baics_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained('iap_baics_assessments')->restrictOnDelete();
            $table->string('report_code', 100)->unique();
            $table->string('title', 255);
            $table->string('status', 40)->default('DRAFT')->index();
            $table->text('executive_summary')->nullable();
            $table->text('objectives_scope_methodology')->nullable();
            $table->text('overall_findings')->nullable();
            $table->text('control_gap_summary')->nullable();
            $table->text('recommendations_summary')->nullable();
            $table->text('limitations_exceptions')->nullable();
            $table->json('source_manifest')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('supersedes_id')->nullable()->constrained('iap_baics_reports')->nullOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_current_revision')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['assessment_id', 'version_number'], 'iap_baics_report_assessment_version_unique');
        });

        Schema::create('iap_baics_report_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('iap_baics_reports')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 40);
            $table->json('snapshot');
            $table->json('source_manifest')->nullable();
            $table->string('source_manifest_sha256', 64);
            $table->string('content_sha256', 64);
            $table->string('pdf_checksum_sha256', 64)->nullable();
            $table->string('csv_checksum_sha256', 64)->nullable();
            $table->string('file_version', 80);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['report_id', 'version_number'], 'iap_baics_report_version_lookup');
        });

        Schema::create('iap_baics_report_controls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('iap_baics_reports')->cascadeOnDelete();
            $table->foreignId('control_id')->constrained('iap_baics_controls')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['report_id', 'control_id'], 'iap_baics_report_control_unique');
        });

        Schema::create('iap_baics_report_interim_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('iap_baics_reports')->cascadeOnDelete();
            $table->foreignId('interim_analysis_id')->constrained('iap_baics_interim_analyses')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['report_id', 'interim_analysis_id'], 'iap_baics_report_interim_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iap_baics_report_interim_analyses');
        Schema::dropIfExists('iap_baics_report_controls');
        Schema::dropIfExists('iap_baics_report_versions');
        Schema::dropIfExists('iap_baics_reports');
    }
};
