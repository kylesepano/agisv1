<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_program_procedures', function (Blueprint $table): void {
            $table->string('fieldwork_status', 30)->default('NOT_STARTED')->index();
            $table->text('fieldwork_results')->nullable();
            $table->text('fieldwork_conclusion')->nullable();
            $table->string('fieldwork_review_state', 40)->default('NOT_REVIEWED');
            $table->json('related_tasks')->nullable();
            $table->json('related_records')->nullable();
            $table->timestamp('fieldwork_completed_at')->nullable();
            $table->foreignId('fieldwork_completed_by')->nullable()->constrained('users')->restrictOnDelete();
        });

        Schema::create('aems_fieldwork_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('record_family_uuid');
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->foreignId('audit_program_procedure_id')->constrained('audit_program_procedures')->restrictOnDelete();
            $table->foreignId('audit_area_id')->nullable()->constrained('audit_areas')->restrictOnDelete();
            $table->foreignId('audit_focus_id')->nullable()->constrained('audit_focuses')->restrictOnDelete();
            $table->string('record_code', 100);
            $table->string('record_type', 30);
            $table->string('status', 40)->default('DRAFT')->index();
            $table->unsignedInteger('current_version_number')->default(1);
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['audit_engagement_id', 'record_code'], 'aems_fieldwork_record_code_unique');
            $table->index(['audit_engagement_id', 'audit_program_procedure_id', 'status'], 'aems_fieldwork_record_scope_idx');
        });

        Schema::create('aems_fieldwork_record_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fieldwork_record_id')->constrained('aems_fieldwork_records')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('record_type', 30);
            $table->foreignId('audit_program_procedure_id')->constrained('audit_program_procedures')->restrictOnDelete();
            $table->foreignId('audit_area_id')->nullable()->constrained('audit_areas')->restrictOnDelete();
            $table->foreignId('audit_focus_id')->nullable()->constrained('audit_focuses')->restrictOnDelete();
            $table->date('performed_on');
            $table->string('location', 500)->nullable();
            $table->text('objective')->nullable();
            $table->text('procedure_performed');
            $table->text('population_description')->nullable();
            $table->text('sample_description')->nullable();
            $table->text('analysis')->nullable();
            $table->text('result');
            $table->text('conclusion');
            $table->string('execution_status', 30)->default('COMPLETED');
            $table->json('related_tasks')->nullable();
            $table->json('related_records')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->text('change_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['fieldwork_record_id', 'version_number'], 'aems_fieldwork_version_unique');
            $table->index(['audit_program_procedure_id', 'execution_status'], 'aems_fieldwork_procedure_status_idx');
        });

        Schema::create('aems_fieldwork_record_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fieldwork_record_version_id')->constrained('aems_fieldwork_record_versions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->string('participant_name', 255);
            $table->string('participant_role', 120)->nullable();
            $table->timestamps();
            $table->index(['fieldwork_record_version_id', 'user_id'], 'aems_fieldwork_participant_idx');
        });

        Schema::create('aems_fieldwork_record_working_papers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fieldwork_record_version_id')->constrained('aems_fieldwork_record_versions')->cascadeOnDelete();
            $table->foreignId('working_paper_id')->constrained('working_papers')->restrictOnDelete();
            $table->foreignId('working_paper_version_id')->nullable()->constrained('working_paper_versions')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['fieldwork_record_version_id', 'working_paper_id', 'working_paper_version_id'], 'aems_fieldwork_working_paper_unique');
        });

        Schema::create('aems_fieldwork_record_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fieldwork_record_version_id')->constrained('aems_fieldwork_record_versions')->cascadeOnDelete();
            $table->foreignId('audit_evidence_id')->constrained('audit_evidence')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['fieldwork_record_version_id', 'audit_evidence_id'], 'aems_fieldwork_evidence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aems_fieldwork_record_evidence');
        Schema::dropIfExists('aems_fieldwork_record_working_papers');
        Schema::dropIfExists('aems_fieldwork_record_participants');
        Schema::dropIfExists('aems_fieldwork_record_versions');
        Schema::dropIfExists('aems_fieldwork_records');
        Schema::table('audit_program_procedures', function (Blueprint $table): void {
            $table->dropForeign(['fieldwork_completed_by']);
            $table->dropColumn([
                'fieldwork_status', 'fieldwork_results', 'fieldwork_conclusion',
                'fieldwork_review_state', 'related_tasks', 'related_records',
                'fieldwork_completed_at', 'fieldwork_completed_by',
            ]);
        });
    }
};
