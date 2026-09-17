<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates repeatable prioritization runs, ranked items, and decision history. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iap_prioritization_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('run_code', 60)->unique();
            $table->string('name');
            $table->foreignId('risk_period_id')->constrained('iap_risk_periods')->restrictOnDelete();
            $table->text('methodology');
            $table->string('status', 40)->default('DRAFT')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['risk_period_id', 'status']);
        });

        Schema::create('iap_prioritization_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prioritization_run_id')
                ->constrained('iap_prioritization_runs')->cascadeOnDelete();
            $table->foreignId('risk_assessment_id')
                ->constrained('iap_universe_risk_assessments')->restrictOnDelete();
            $table->foreignId('audit_universe_item_id')
                ->constrained('iap_audit_universe_items')->restrictOnDelete();
            $table->string('subject_code', 80);
            $table->string('subject_name');
            $table->string('office_code', 60)->nullable();
            $table->string('office_name')->nullable();
            $table->string('audit_area_code', 60)->nullable();
            $table->string('audit_area_name')->nullable();
            $table->decimal('inherent_risk_score', 5, 2);
            $table->decimal('control_effectiveness_percent', 5, 2);
            $table->decimal('residual_risk_score', 5, 2);
            $table->string('risk_level_code', 30);
            $table->string('risk_level_label', 80);
            $table->decimal('priority_score', 6, 2);
            $table->unsignedInteger('system_rank');
            $table->unsignedInteger('final_rank');
            $table->string('recommended_decision', 30);
            $table->string('decision', 30);
            $table->text('decision_reason')->nullable();
            $table->boolean('is_manual_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(
                ['prioritization_run_id', 'risk_assessment_id'],
                'iap_prioritization_assessment_unique',
            );
            $table->index(['prioritization_run_id', 'final_rank']);
            $table->index(['prioritization_run_id', 'decision']);
        });

        Schema::create('iap_prioritization_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prioritization_run_id')
                ->constrained('iap_prioritization_runs')->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->unsignedInteger('run_lock_version');
            $table->timestamp('created_at')->useCurrent();
            $table->index(
                ['prioritization_run_id', 'created_at'],
                'iap_prioritization_event_timeline',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iap_prioritization_events');
        Schema::dropIfExists('iap_prioritization_items');
        Schema::dropIfExists('iap_prioritization_runs');
    }
};
