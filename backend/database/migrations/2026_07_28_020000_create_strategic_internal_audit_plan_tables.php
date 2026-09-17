<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Creates versioned SIAP plans, objectives, priorities, links, and workflow events. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategic_internal_audit_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('plan_code', 60)->unique();
            $table->unsignedSmallInteger('start_year')->index();
            $table->unsignedSmallInteger('end_year')->index();
            $table->string('title');
            $table->text('strategic_context')->nullable();
            $table->text('vision')->nullable();
            $table->text('mission_alignment')->nullable();
            $table->text('planning_methodology')->nullable();
            $table->text('expected_outcomes');
            $table->string('status', 40)->default('DRAFT')->index();
            $table->unsignedSmallInteger('revision_number')->default(0);
            $table->foreignId('supersedes_plan_id')->nullable()
                ->constrained('strategic_internal_audit_plans')->nullOnDelete();
            $table->boolean('is_current_revision')->default(true)->index();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['start_year', 'end_year', 'revision_number'],
                'siap_period_revision_unique',
            );
            $table->index(['status', 'start_year', 'end_year'], 'siap_status_period_index');
        });

        DB::statement(
            'CREATE UNIQUE INDEX siap_one_current_revision_per_period '.
            'ON strategic_internal_audit_plans (start_year, end_year) '.
            'WHERE is_current_revision = true AND deleted_at IS NULL',
        );

        Schema::create('siap_objectives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('strategic_plan_id')
                ->constrained('strategic_internal_audit_plans')->cascadeOnDelete();
            $table->string('objective_code', 40);
            $table->string('title');
            $table->text('description');
            $table->text('expected_outcome');
            $table->unsignedSmallInteger('display_order')->default(1);
            $table->timestamps();

            $table->unique(['strategic_plan_id', 'objective_code'], 'siap_objective_code_unique');
        });

        Schema::create('siap_objective_audit_area', function (Blueprint $table): void {
            $table->foreignId('objective_id')->constrained('siap_objectives')->cascadeOnDelete();
            $table->foreignId('audit_area_id')->constrained('audit_areas')->restrictOnDelete();
            $table->primary(['objective_id', 'audit_area_id'], 'siap_objective_area_primary');
        });

        Schema::create('siap_priorities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('strategic_plan_id')
                ->constrained('strategic_internal_audit_plans')->cascadeOnDelete();
            $table->string('priority_code', 40);
            $table->string('title');
            $table->string('theme');
            $table->text('description');
            $table->text('expected_outcome');
            $table->unsignedSmallInteger('display_order')->default(1);
            $table->timestamps();

            $table->unique(['strategic_plan_id', 'priority_code'], 'siap_priority_code_unique');
        });

        Schema::create('siap_workflow_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('strategic_plan_id')
                ->constrained('strategic_internal_audit_plans')->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_role_code', 60);
            $table->text('comment')->nullable();
            $table->unsignedInteger('plan_lock_version');
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['strategic_plan_id', 'created_at'], 'siap_event_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siap_workflow_events');
        Schema::dropIfExists('siap_priorities');
        Schema::dropIfExists('siap_objective_audit_area');
        Schema::dropIfExists('siap_objectives');
        DB::statement('DROP INDEX IF EXISTS siap_one_current_revision_per_period');
        Schema::dropIfExists('strategic_internal_audit_plans');
    }
};
