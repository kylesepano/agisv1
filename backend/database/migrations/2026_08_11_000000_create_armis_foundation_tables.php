<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates the ARMIS-1A resource, capacity, workload, and actuals foundation. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('armis_resource_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('resource_code', 40)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->string('category', 40)->default('AUDIT_RESOURCE');
            $table->string('status', 30)->default('DRAFT')->index();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['office_id', 'status'], 'armis_resource_office_status_idx');
            $table->index(['user_id', 'status'], 'armis_resource_user_status_idx');
        });

        Schema::create('armis_competencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resource_profile_id')
                ->constrained('armis_resource_profiles')
                ->cascadeOnDelete();
            $table->foreignId('competency_id')
                ->constrained('master_list_items')
                ->restrictOnDelete();
            $table->string('proficiency_level', 20)->default('INTERMEDIATE');
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('evidence_document_version_id')
                ->nullable()
                ->constrained('document_versions')
                ->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(
                ['resource_profile_id', 'competency_id'],
                'armis_resource_competency_unique',
            );
            $table->index(['resource_profile_id', 'status'], 'armis_competency_profile_status_idx');
        });

        Schema::create('armis_availability_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resource_profile_id')
                ->constrained('armis_resource_profiles')
                ->cascadeOnDelete();
            $table->string('availability_type', 30)->default('AVAILABLE');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('person_days', 8, 2)->nullable();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->softDeletes();
            $table->timestamps();
            $table->index(
                ['resource_profile_id', 'start_date', 'end_date'],
                'armis_availability_profile_dates_idx',
            );
        });

        Schema::create('armis_capacity_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resource_profile_id')
                ->constrained('armis_resource_profiles')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedInteger('version_number')->default(1);
            $table->decimal('available_person_days', 8, 2);
            $table->string('status', 30)->default('DRAFT')->index();
            $table->text('notes')->nullable();
            $table->foreignId('supersedes_id')->nullable()
                ->constrained('armis_capacity_submissions')
                ->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(
                ['resource_profile_id', 'fiscal_year', 'version_number'],
                'armis_capacity_profile_year_version_unique',
            );
            $table->index(['fiscal_year', 'status'], 'armis_capacity_year_status_idx');
        });

        Schema::create('armis_resource_requirements', function (Blueprint $table): void {
            $table->id();
            $table->string('source_module', 30)->default('ARMIS');
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->string('title', 255);
            $table->decimal('required_person_days', 8, 2)->default(0);
            $table->string('status', 30)->default('DRAFT')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['source_module', 'source_type', 'source_id'], 'armis_requirement_source_idx');
            $table->index(['office_id', 'fiscal_year'], 'armis_requirement_office_year_idx');
        });

        Schema::create('armis_requirement_competencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requirement_id')
                ->constrained('armis_resource_requirements')
                ->cascadeOnDelete();
            $table->foreignId('competency_id')
                ->constrained('master_list_items')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('minimum_resources')->default(1);
            $table->string('minimum_proficiency', 20)->default('INTERMEDIATE');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(
                ['requirement_id', 'competency_id'],
                'armis_requirement_competency_unique',
            );
        });

        Schema::create('armis_workload_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resource_profile_id')
                ->constrained('armis_resource_profiles')
                ->cascadeOnDelete();
            $table->foreignId('requirement_id')
                ->nullable()
                ->constrained('armis_resource_requirements')
                ->nullOnDelete();
            $table->string('source_module', 30)->default('ARMIS');
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->decimal('planned_person_days', 8, 2)->default(0);
            $table->string('status', 30)->default('DRAFT')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['resource_profile_id', 'fiscal_year'], 'armis_workload_profile_year_idx');
            $table->index(['source_module', 'source_type', 'source_id'], 'armis_workload_source_idx');
        });

        Schema::create('armis_actual_person_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resource_profile_id')
                ->constrained('armis_resource_profiles')
                ->cascadeOnDelete();
            $table->string('source_module', 30)->default('ARMIS');
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('version_number')->default(1);
            $table->decimal('actual_person_days', 8, 2)->default(0);
            $table->string('status', 30)->default('DRAFT')->index();
            $table->text('notes')->nullable();
            $table->foreignId('supersedes_id')->nullable()
                ->constrained('armis_actual_person_days')
                ->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(
                ['resource_profile_id', 'source_module', 'source_type', 'source_id', 'period_start', 'period_end', 'version_number'],
                'armis_actual_source_period_version_unique',
            );
            $table->index(['resource_profile_id', 'period_start', 'period_end'], 'armis_actual_profile_period_idx');
        });

        Schema::create('armis_workflow_events', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->string('event_code', 80);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id', 'created_at'], 'armis_workflow_subject_timeline_idx');
            $table->index(['event_code', 'created_at'], 'armis_workflow_event_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('armis_workflow_events');
        Schema::dropIfExists('armis_actual_person_days');
        Schema::dropIfExists('armis_workload_allocations');
        Schema::dropIfExists('armis_requirement_competencies');
        Schema::dropIfExists('armis_resource_requirements');
        Schema::dropIfExists('armis_capacity_submissions');
        Schema::dropIfExists('armis_availability_periods');
        Schema::dropIfExists('armis_competencies');
        Schema::dropIfExists('armis_resource_profiles');
    }
};
