<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Scheduled automation has no human actor; the append-only event
            // may therefore carry a null actor while retaining system metadata.
            DB::statement('ALTER TABLE cms_recommendation_events ALTER COLUMN actor_id DROP NOT NULL');
        }

        Schema::create('cms_automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('rule_code', 80)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('rule_type', 40);
            $table->string('status_code', 20)->default('ACTIVE');
            $table->string('schedule_code', 30)->default('DAILY');
            $table->jsonb('configuration')->default('{}');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['rule_type', 'status_code']);
        });

        Schema::create('cms_automation_rule_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_automation_rule_id')->constrained('cms_automation_rules')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status_code', 20)->default('ACTIVE');
            $table->jsonb('configuration')->default('{}');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_from');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->unique(['cms_automation_rule_id', 'version_number'], 'cms_automation_rule_version_unique');
            $table->index(['status_code', 'effective_from']);
        });

        Schema::table('cms_automation_rules', function (Blueprint $table): void {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('cms_automation_rule_versions')
                ->nullOnDelete();
        });

        Schema::create('cms_automation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_automation_rule_id')->nullable()->constrained('cms_automation_rules')->nullOnDelete();
            $table->foreignId('cms_automation_rule_version_id')->nullable()->constrained('cms_automation_rule_versions')->nullOnDelete();
            $table->string('run_key', 180)->unique();
            $table->string('status_code', 20)->default('RUNNING');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('scanned_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->text('error_summary')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['status_code', 'started_at']);
        });

        Schema::create('cms_automation_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_automation_run_id')->constrained('cms_automation_runs')->restrictOnDelete();
            $table->foreignId('cms_automation_rule_id')->nullable()->constrained('cms_automation_rules')->nullOnDelete();
            $table->foreignId('cms_recommendation_case_id')->nullable()->constrained('cms_recommendation_cases')->restrictOnDelete();
            $table->string('action_type', 40);
            $table->string('status_code', 30)->default('CREATED');
            $table->string('dedupe_key', 220)->unique();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('candidate_type', 50)->nullable();
            $table->unsignedBigInteger('candidate_id')->nullable();
            $table->foreignId('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
            $table->jsonb('payload')->nullable();
            $table->timestamps();
            $table->index(['action_type', 'status_code']);
            $table->index(['cms_recommendation_case_id', 'action_type']);
        });

        Schema::create('cms_closure_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_recommendation_case_id')->constrained('cms_recommendation_cases')->restrictOnDelete();
            $table->foreignId('cms_automation_run_id')->nullable()->constrained('cms_automation_runs')->nullOnDelete();
            $table->string('detection_key', 220)->unique();
            $table->string('status_code', 30)->default('OPEN');
            $table->timestamp('detected_at');
            $table->jsonb('readiness_snapshot');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('closure_request_id')->nullable()->constrained('cms_closure_requests')->nullOnDelete();
            $table->timestamps();
            $table->index(['status_code', 'detected_at']);
            $table->index(['cms_recommendation_case_id', 'status_code']);
        });

        Schema::create('cms_escalation_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_recommendation_case_id')->constrained('cms_recommendation_cases')->restrictOnDelete();
            $table->foreignId('cms_automation_run_id')->nullable()->constrained('cms_automation_runs')->nullOnDelete();
            $table->string('detection_key', 220)->unique();
            $table->string('status_code', 30)->default('OPEN');
            $table->string('trigger_code', 60);
            $table->string('severity_code', 30)->default('MEDIUM');
            $table->text('reason');
            $table->timestamp('detected_at');
            $table->jsonb('trigger_snapshot');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('escalation_id')->nullable()->constrained('cms_escalations')->nullOnDelete();
            $table->timestamps();
            $table->index(['status_code', 'detected_at']);
            $table->index(['cms_recommendation_case_id', 'status_code']);
            $table->index(['trigger_code', 'severity_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_escalation_candidates');
        Schema::dropIfExists('cms_closure_candidates');
        Schema::dropIfExists('cms_automation_actions');
        Schema::dropIfExists('cms_automation_runs');
        Schema::table('cms_automation_rules', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('cms_automation_rule_versions');
        Schema::dropIfExists('cms_automation_rules');
    }
};
