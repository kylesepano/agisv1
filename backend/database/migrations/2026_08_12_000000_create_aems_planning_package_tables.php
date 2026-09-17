<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aems_planning_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->unique()->constrained('audit_engagements')->restrictOnDelete();
            $table->string('package_code', 100)->unique();
            $table->string('status', 40)->default('DRAFT')->index();
            $table->unsignedInteger('current_version_number')->default(0);
            $table->unsignedInteger('approved_version_number')->nullable();
            $table->string('source_type', 30)->nullable();
            $table->foreignId('iap_plan_engagement_id')->nullable()->constrained('iap_plan_engagements')->restrictOnDelete();
            $table->foreignId('iap_plan_id')->nullable()->constrained('internal_audit_plans')->restrictOnDelete();
            $table->foreignId('iap_prioritization_item_id')->nullable()->constrained('iap_prioritization_items')->restrictOnDelete();
            $table->foreignId('iap_risk_assessment_id')->nullable()->constrained('iap_universe_risk_assessments')->restrictOnDelete();
            $table->foreignId('iap_audit_universe_item_id')->nullable()->constrained('iap_audit_universe_items')->restrictOnDelete();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('aems_planning_package_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planning_package_id')->constrained('aems_planning_packages')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('preliminary_survey')->nullable();
            $table->json('planning_attributes')->nullable();
            $table->json('iap_lineage_snapshot')->nullable();
            $table->foreignId('preliminary_survey_document_version_id')->nullable()->constrained('document_versions')->restrictOnDelete();
            $table->string('checksum_sha256', 64)->nullable();
            $table->text('change_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['planning_package_id', 'version_number'], 'aems_planning_package_version_unique');
        });

        Schema::create('aems_planning_objectives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planning_package_version_id')->constrained('aems_planning_package_versions')->cascadeOnDelete();
            $table->string('objective_code', 80);
            $table->text('objective_statement');
            $table->string('source_type', 30)->default('AEMS');
            $table->string('source_reference', 160)->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();
            $table->unique(['planning_package_version_id', 'objective_code'], 'aems_planning_objective_unique');
        });

        Schema::create('aems_process_flow_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planning_package_version_id')->constrained('aems_planning_package_versions')->cascadeOnDelete();
            $table->string('flow_code', 80);
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('process_owner_office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained('document_versions')->restrictOnDelete();
            $table->string('source_type', 30)->default('AEMS');
            $table->string('source_reference', 160)->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();
            $table->unique(['planning_package_version_id', 'flow_code'], 'aems_process_flow_unique');
        });

        Schema::create('aems_risk_matrices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planning_package_version_id')->unique()->constrained('aems_planning_package_versions')->cascadeOnDelete();
            $table->string('matrix_code', 80);
            $table->string('title');
            $table->text('methodology')->nullable();
            $table->string('risk_appetite', 100)->nullable();
            $table->text('overall_conclusion')->nullable();
            $table->timestamps();
        });

        Schema::create('aems_risk_matrix_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('risk_matrix_id')->constrained('aems_risk_matrices')->cascadeOnDelete();
            $table->string('risk_code', 80);
            $table->text('risk_statement');
            $table->string('risk_category', 100)->nullable();
            $table->decimal('inherent_likelihood', 8, 2)->nullable();
            $table->decimal('inherent_impact', 8, 2)->nullable();
            $table->decimal('inherent_score', 8, 2)->nullable();
            $table->text('control_description')->nullable();
            $table->string('control_effectiveness', 50)->nullable();
            $table->decimal('residual_likelihood', 8, 2)->nullable();
            $table->decimal('residual_impact', 8, 2)->nullable();
            $table->decimal('residual_score', 8, 2)->nullable();
            $table->string('residual_rating', 50)->nullable();
            $table->string('risk_response', 100)->nullable();
            $table->foreignId('responsible_office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->string('status', 30)->default('OPEN');
            $table->timestamps();
            $table->unique(['risk_matrix_id', 'risk_code'], 'aems_risk_matrix_item_unique');
        });

        Schema::create('aems_planning_package_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planning_package_id')->constrained('aems_planning_packages')->cascadeOnDelete();
            $table->foreignId('planning_package_version_id')->constrained('aems_planning_package_versions')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('result', 30);
            $table->text('comment')->nullable();
            $table->timestamp('reviewed_at')->useCurrent();
            $table->unique(['planning_package_id', 'planning_package_version_id', 'reviewer_id'], 'aems_planning_review_unique');
        });

        Schema::create('aems_risk_objective_links', function (Blueprint $table): void {
            $table->foreignId('risk_matrix_item_id')->constrained('aems_risk_matrix_items')->cascadeOnDelete();
            $table->foreignId('planning_objective_id')->constrained('aems_planning_objectives')->cascadeOnDelete();
            $table->string('relationship_basis', 500)->nullable();
            $table->timestamps();
            $table->primary(['risk_matrix_item_id', 'planning_objective_id'], 'aems_risk_objective_pk');
        });

        Schema::create('aems_risk_procedure_links', function (Blueprint $table): void {
            $table->foreignId('risk_matrix_item_id')->constrained('aems_risk_matrix_items')->cascadeOnDelete();
            $table->foreignId('audit_program_procedure_id')->constrained('audit_program_procedures')->restrictOnDelete();
            $table->string('relationship_basis', 500)->nullable();
            $table->timestamps();
            $table->primary(['risk_matrix_item_id', 'audit_program_procedure_id'], 'aems_risk_procedure_pk');
        });

        Schema::create('aems_risk_working_paper_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('risk_matrix_item_id')->constrained('aems_risk_matrix_items')->cascadeOnDelete();
            $table->foreignId('working_paper_id')->nullable()->constrained('working_papers')->restrictOnDelete();
            $table->string('working_paper_reference', 160);
            $table->string('relationship_basis', 500)->nullable();
            $table->timestamps();
            $table->unique(['risk_matrix_item_id', 'working_paper_reference'], 'aems_risk_working_paper_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aems_risk_working_paper_links');
        Schema::dropIfExists('aems_risk_procedure_links');
        Schema::dropIfExists('aems_risk_objective_links');
        Schema::dropIfExists('aems_planning_package_reviews');
        Schema::dropIfExists('aems_risk_matrix_items');
        Schema::dropIfExists('aems_risk_matrices');
        Schema::dropIfExists('aems_process_flow_documents');
        Schema::dropIfExists('aems_planning_objectives');
        Schema::dropIfExists('aems_planning_package_versions');
        Schema::dropIfExists('aems_planning_packages');
    }
};
