<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aems_process_flow_documents', function (Blueprint $table): void {
            $table->foreignId('audit_area_id')->nullable()->after('planning_package_version_id')->constrained('audit_areas')->restrictOnDelete();
            $table->foreignId('audit_focus_id')->nullable()->after('audit_area_id')->constrained('audit_focuses')->restrictOnDelete();
            $table->text('scope_statement')->nullable();
            $table->json('steps')->nullable();
            $table->json('inputs')->nullable();
            $table->json('outputs')->nullable();
            $table->json('records_systems')->nullable();
            $table->json('controls')->nullable();
            $table->json('decision_points')->nullable();
            $table->json('risk_points')->nullable();
            $table->text('limitations')->nullable();
        });

        Schema::table('aems_risk_matrices', function (Blueprint $table): void {
            $table->dropUnique('aems_risk_matrices_planning_package_version_id_unique');
            $table->foreignId('audit_area_id')->nullable()->constrained('audit_areas')->restrictOnDelete();
            $table->foreignId('audit_focus_id')->nullable()->constrained('audit_focuses')->restrictOnDelete();
            $table->string('matrix_type', 50)->nullable();
            $table->string('status', 30)->default('DRAFT')->index();
        });
        Schema::table('aems_risk_matrices', function (Blueprint $table): void {
            $table->index(['planning_package_version_id', 'audit_area_id'], 'aems_risk_matrix_scope_idx');
        });

        Schema::table('aems_risk_matrix_items', function (Blueprint $table): void {
            $table->foreignId('audit_area_id')->nullable()->constrained('audit_areas')->restrictOnDelete();
            $table->foreignId('audit_focus_id')->nullable()->constrained('audit_focuses')->restrictOnDelete();
            $table->foreignId('process_flow_id')->nullable()->constrained('aems_process_flow_documents')->restrictOnDelete();
            $table->string('process_name', 255)->nullable();
            $table->string('risk_area', 255)->nullable();
            $table->text('planned_audit_approach')->nullable();
            $table->text('criteria')->nullable();
            $table->text('response_rationale')->nullable();
            $table->string('source_reference', 160)->nullable();
        });

        Schema::table('audit_programs', function (Blueprint $table): void {
            $table->foreignId('audit_area_id')->nullable()->constrained('audit_areas')->restrictOnDelete();
            $table->foreignId('audit_type_id')->nullable()->constrained('master_list_items')->restrictOnDelete();
            $table->date('audit_period_start')->nullable();
            $table->date('audit_period_end')->nullable();
            $table->text('audit_criteria')->nullable();
            $table->json('risk_statement_set')->nullable();
            $table->text('sampling_approach')->nullable();
            $table->json('planned_working_paper_requirements')->nullable();
        });

        Schema::table('audit_program_procedures', function (Blueprint $table): void {
            $table->foreignId('audit_area_id')->nullable()->constrained('audit_areas')->restrictOnDelete();
            $table->foreignId('audit_focus_id')->nullable()->constrained('audit_focuses')->restrictOnDelete();
            $table->foreignId('process_flow_id')->nullable()->constrained('aems_process_flow_documents')->restrictOnDelete();
            $table->string('process_name', 255)->nullable();
            $table->string('audit_method', 100)->nullable();
            $table->text('audit_criteria')->nullable();
            $table->decimal('planned_person_days', 8, 2)->nullable();
            $table->json('sampling_requirement')->nullable();
            $table->json('planned_working_paper_requirement')->nullable();
            $table->json('risk_statement_ids')->nullable();
        });

        Schema::create('aems_planning_kpis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planning_package_version_id')->constrained('aems_planning_package_versions')->cascadeOnDelete();
            $table->string('kpi_code', 80);
            $table->string('name');
            $table->string('target', 255);
            $table->text('measurement_method');
            $table->string('source_reference', 160)->nullable();
            $table->foreignId('responsible_office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->string('status', 30)->default('DEFINED');
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();
            $table->unique(['planning_package_version_id', 'kpi_code'], 'aems_planning_kpi_unique');
        });

        Schema::create('aems_planned_working_paper_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planning_package_version_id')->constrained('aems_planning_package_versions')->cascadeOnDelete();
            $table->foreignId('audit_program_procedure_id')->nullable()->constrained('audit_program_procedures')->restrictOnDelete();
            $table->foreignId('risk_matrix_item_id')->nullable()->constrained('aems_risk_matrix_items')->restrictOnDelete();
            $table->string('working_paper_reference', 120);
            $table->string('title');
            $table->text('objective')->nullable();
            $table->text('required_evidence')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();
            $table->unique(['planning_package_version_id', 'working_paper_reference'], 'aems_planned_wp_requirement_unique');
        });

        // This must run after the audit_programs Schema::table operation above;
        // SQLite rebuilds that table and otherwise drops the partial predicate.
        DB::statement('DROP INDEX IF EXISTS aem_current_program_revision_unique');
        $predicate = DB::connection()->getDriverName() === 'pgsql' ? 'true' : '1';
        DB::statement("CREATE UNIQUE INDEX aem_current_program_revision_unique ON audit_programs (audit_engagement_id, program_code) WHERE is_current_revision = {$predicate} AND deleted_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS aem_current_program_revision_unique');
        $predicate = DB::connection()->getDriverName() === 'pgsql' ? 'true' : '1';
        DB::statement("CREATE UNIQUE INDEX aem_current_program_revision_unique ON audit_programs (audit_engagement_id, program_code) WHERE is_current_revision = {$predicate} AND deleted_at IS NULL");
        Schema::dropIfExists('aems_planned_working_paper_requirements');
        Schema::dropIfExists('aems_planning_kpis');
        Schema::table('audit_program_procedures', function (Blueprint $table): void {
            $table->dropForeign(['audit_area_id']); $table->dropForeign(['audit_focus_id']); $table->dropForeign(['process_flow_id']);
            $table->dropColumn(['audit_area_id','audit_focus_id','process_flow_id','process_name','audit_method','audit_criteria','planned_person_days','sampling_requirement','planned_working_paper_requirement','risk_statement_ids']);
        });
        Schema::table('audit_programs', function (Blueprint $table): void {
            $table->dropForeign(['audit_area_id']); $table->dropForeign(['audit_type_id']);
            $table->dropColumn(['audit_area_id','audit_type_id','audit_period_start','audit_period_end','audit_criteria','risk_statement_set','sampling_approach','planned_working_paper_requirements']);
        });
        Schema::table('aems_risk_matrix_items', function (Blueprint $table): void {
            $table->dropForeign(['audit_area_id']); $table->dropForeign(['audit_focus_id']); $table->dropForeign(['process_flow_id']);
            $table->dropColumn(['audit_area_id','audit_focus_id','process_flow_id','process_name','risk_area','planned_audit_approach','criteria','response_rationale','source_reference']);
        });
        Schema::table('aems_risk_matrices', function (Blueprint $table): void {
            $table->dropForeign(['audit_area_id']); $table->dropForeign(['audit_focus_id']);
            $table->dropIndex('aems_risk_matrix_scope_idx');
            $table->dropColumn(['audit_area_id','audit_focus_id','matrix_type','status']);
            $table->unique('planning_package_version_id');
        });
        Schema::table('aems_process_flow_documents', function (Blueprint $table): void {
            $table->dropForeign(['audit_area_id']); $table->dropForeign(['audit_focus_id']);
            $table->dropColumn(['audit_area_id','audit_focus_id','scope_statement','steps','inputs','outputs','records_systems','controls','decision_points','risk_points','limitations']);
        });
    }
};
