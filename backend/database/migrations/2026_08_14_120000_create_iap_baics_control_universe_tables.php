<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** BAICS-3 control universe, interim analysis, traceability, and immutable snapshots. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iap_baics_controls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained('iap_baics_assessments')->cascadeOnDelete();
            $table->foreignId('scope_item_id')->constrained('iap_baics_scope_items')->restrictOnDelete();
            $table->foreignId('component_id')->nullable()->constrained('iap_baics_components')->restrictOnDelete();
            $table->string('control_code', 100);
            $table->string('process_step', 255);
            $table->string('responsible_unit', 255)->nullable();
            $table->foreignId('control_owner_office_id')->constrained('offices')->restrictOnDelete();
            $table->foreignId('control_owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('objective');
            $table->text('related_risk');
            $table->text('control_description');
            $table->text('expected_result');
            $table->string('control_type', 30)->default('PREVENTIVE');
            $table->string('execution_mode', 30)->default('MANUAL');
            $table->string('frequency', 100)->nullable();
            $table->text('evidence_produced')->nullable();
            $table->boolean('approval_required')->default(false);
            $table->boolean('segregation_of_duties_required')->default(false);
            $table->text('design_assessment')->nullable();
            $table->text('operating_assessment')->nullable();
            $table->string('control_status', 40)->default('Existing')->index();
            $table->string('deficiency_classification', 40)->nullable()->index();
            $table->text('limitation_details')->nullable();
            $table->text('gap_details')->nullable();
            $table->text('breakdown_details')->nullable();
            $table->text('contradiction_details')->nullable();
            $table->text('recommendation_action')->nullable();
            $table->string('status', 40)->default('DRAFT')->index();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('immutable_at')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('iap_baics_controls')->nullOnDelete();
            $table->boolean('is_current_revision')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['assessment_id', 'control_code'], 'iap_baics_control_code_unique');
            $table->index(['assessment_id', 'control_status']);
            $table->index(['scope_item_id', 'component_id']);
        });

        Schema::create('iap_baics_control_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('control_id')->constrained('iap_baics_controls')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 40);
            $table->json('snapshot');
            $table->string('snapshot_hash', 64);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['control_id', 'version_number'], 'iap_baics_control_version_lookup');
        });

        Schema::create('iap_baics_control_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('control_id')->constrained('iap_baics_controls')->cascadeOnDelete();
            $table->foreignId('method_id')->constrained('iap_baics_methods')->restrictOnDelete();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['control_id', 'method_id'], 'iap_baics_control_method_unique');
        });

        Schema::create('iap_baics_control_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('control_id')->constrained('iap_baics_controls')->cascadeOnDelete();
            $table->foreignId('evidence_link_id')->constrained('iap_baics_evidence_links')->restrictOnDelete();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['control_id', 'evidence_link_id'], 'iap_baics_control_evidence_unique');
        });

        Schema::create('iap_baics_interim_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained('iap_baics_assessments')->cascadeOnDelete();
            $table->string('analysis_code', 100);
            $table->string('title', 255);
            $table->date('analysis_period_start')->nullable();
            $table->date('analysis_period_end')->nullable();
            $table->text('analysis_narrative');
            $table->text('findings_summary')->nullable();
            $table->text('recommendations_summary')->nullable();
            $table->text('limitations')->nullable();
            $table->json('source_manifest')->nullable();
            $table->string('status', 40)->default('DRAFT')->index();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('immutable_at')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['assessment_id', 'analysis_code'], 'iap_baics_interim_code_unique');
        });

        Schema::create('iap_baics_interim_analysis_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interim_analysis_id')->constrained('iap_baics_interim_analyses')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 40);
            $table->json('snapshot');
            $table->string('snapshot_hash', 64);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['interim_analysis_id', 'version_number'], 'iap_baics_interim_version_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iap_baics_interim_analysis_versions');
        Schema::dropIfExists('iap_baics_interim_analyses');
        Schema::dropIfExists('iap_baics_control_evidence');
        Schema::dropIfExists('iap_baics_control_methods');
        Schema::dropIfExists('iap_baics_control_versions');
        Schema::dropIfExists('iap_baics_controls');
    }
};
