<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates reusable workflow definitions, steps, transitions, instances, and events. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80);
            $table->string('name');
            $table->string('module_code', 20);
            $table->string('subject_type', 100);
            $table->unsignedInteger('version')->default(1);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['code', 'version']);
            $table->index(['module_code', 'status', 'is_active']);
        });

        Schema::create('workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_definition_id')
                ->constrained('workflow_definitions')
                ->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->unsignedInteger('sequence');
            $table->string('step_type', 20)->default('INTERMEDIATE');
            $table->foreignId('responsible_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->unsignedInteger('sla_hours')->nullable();
            $table->text('instructions')->nullable();
            $table->timestampsTz();

            $table->unique(['workflow_definition_id', 'code']);
            $table->unique(['workflow_definition_id', 'sequence']);
        });

        Schema::create('workflow_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_definition_id')
                ->constrained('workflow_definitions')
                ->cascadeOnDelete();
            $table->foreignId('from_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->foreignId('to_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->unsignedInteger('sequence')->default(1);
            $table->foreignId('actor_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('required_permission_id')->nullable()->constrained('permissions')->nullOnDelete();
            $table->boolean('requires_comment')->default(false);
            $table->boolean('enforce_separation_of_duties')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['workflow_definition_id', 'code']);
            $table->index(['from_step_id', 'is_active']);
        });

        Schema::create('workflow_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions')->restrictOnDelete();
            $table->foreignId('current_step_id')->constrained('workflow_steps')->restrictOnDelete();
            $table->string('module_code', 20);
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_code', 150);
            $table->string('subject_label');
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->string('status', 20)->default('ACTIVE');
            $table->json('context')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('step_entered_at');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->index(['module_code', 'subject_type', 'subject_id']);
            $table->index(['status', 'due_at']);
            $table->index(['office_id', 'status']);
        });

        Schema::create('workflow_instance_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained('workflow_instances')->cascadeOnDelete();
            $table->foreignId('workflow_transition_id')->nullable()->constrained('workflow_transitions')->nullOnDelete();
            $table->foreignId('from_step_id')->nullable()->constrained('workflow_steps')->nullOnDelete();
            $table->foreignId('to_step_id')->constrained('workflow_steps')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_code', 80);
            $table->text('comment')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at');

            $table->index(['workflow_instance_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instance_events');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflow_definitions');
    }
};
