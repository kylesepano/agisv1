<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds versioned management-reported progress without changing the accepted
 * Action Plan baseline or recommendation implementation status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_progress_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_recommendation_case_id')
                ->constrained('cms_recommendation_cases')
                ->restrictOnDelete();
            $table->foreignId('cms_corrective_action_plan_id')
                ->constrained('cms_corrective_action_plans')
                ->restrictOnDelete();
            $table->foreignId('accepted_action_plan_version_id')
                ->constrained('cms_action_plan_versions')
                ->restrictOnDelete();
            $table->unsignedInteger('reporting_sequence');
            $table->date('reporting_period_start');
            $table->date('reporting_period_end');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedBigInteger('recorded_version_id')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(
                ['cms_recommendation_case_id', 'reporting_sequence'],
                'cms_progress_case_sequence_unique',
            );
            $table->unique(
                ['cms_recommendation_case_id', 'reporting_period_end'],
                'cms_progress_case_period_end_unique',
            );
            $table->index(
                ['cms_recommendation_case_id', 'reporting_period_start', 'reporting_period_end'],
                'cms_progress_case_period_idx',
            );
        });

        Schema::create('cms_progress_update_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_progress_update_id')
                ->constrained('cms_progress_updates')
                ->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('previous_version_id')
                ->nullable()
                ->constrained('cms_progress_update_versions')
                ->restrictOnDelete();
            $table->string('status_code', 30)->default('DRAFT');
            $table->string('active_slot', 20)->nullable()->default('ACTIVE');
            $table->text('accomplishment_summary')->nullable();
            $table->decimal('management_reported_overall_percentage', 5, 2)->nullable();
            $table->decimal('system_calculated_weighted_percentage', 5, 2)->nullable();
            $table->boolean('baseline_weighted')->default(false);
            $table->text('issues_and_constraints')->nullable();
            $table->text('corrective_actions_for_delays')->nullable();
            $table->text('next_steps')->nullable();
            $table->date('forecast_completion_date')->nullable();
            $table->text('management_declaration')->nullable();
            $table->text('general_evidence_explanation')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('review_started_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('review_started_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->text('recording_comment')->nullable();
            $table->text('revision_reason')->nullable();
            $table->jsonb('submission_snapshot')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(
                ['cms_progress_update_id', 'version_number'],
                'cms_progress_family_version_unique',
            );
            $table->unique(
                ['cms_progress_update_id', 'active_slot'],
                'cms_progress_family_active_slot_unique',
            );
            $table->index(
                ['cms_progress_update_id', 'status_code'],
                'cms_progress_version_status_idx',
            );
        });

        Schema::create('cms_milestone_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_progress_update_version_id')
                ->constrained('cms_progress_update_versions')
                ->restrictOnDelete();
            $table->foreignId('cms_action_plan_milestone_id')
                ->constrained('cms_action_plan_milestones')
                ->restrictOnDelete();
            $table->unsignedInteger('milestone_sequence');
            $table->jsonb('milestone_snapshot');
            $table->string('management_reported_status_code', 30)->default('NOT_STARTED');
            $table->decimal('management_reported_percentage', 5, 2)->default(0);
            $table->text('accomplishment_description')->nullable();
            $table->text('issues_and_constraints')->nullable();
            $table->text('next_step')->nullable();
            $table->date('forecast_completion_date')->nullable();
            $table->text('no_evidence_explanation')->nullable();
            $table->unsignedInteger('display_order');
            $table->timestamps();

            $table->unique(
                ['cms_progress_update_version_id', 'cms_action_plan_milestone_id'],
                'cms_milestone_progress_version_milestone_unique',
            );
            $table->index(
                ['cms_progress_update_version_id', 'display_order'],
                'cms_milestone_progress_order_idx',
            );
        });

        Schema::create('cms_progress_evidence_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_progress_update_version_id')
                ->constrained('cms_progress_update_versions')
                ->restrictOnDelete();
            $table->foreignId('cms_milestone_progress_id')
                ->nullable()
                ->constrained('cms_milestone_progress')
                ->restrictOnDelete();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignId('document_version_id')
                ->constrained('document_versions')
                ->restrictOnDelete();
            $table->string('evidence_category', 80);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('source_or_custodian')->nullable();
            $table->foreignId('linked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('linked_at');
            $table->string('checksum_sha256', 64);
            $table->foreignId('confidentiality_level_id')
                ->nullable()
                ->constrained('master_list_items')
                ->restrictOnDelete();
            $table->string('confidentiality_code_snapshot', 80)->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->text('removal_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['cms_progress_update_version_id', 'document_version_id', 'cms_milestone_progress_id'],
                'cms_progress_evidence_exact_link_unique',
            );
            $table->index(
                ['cms_progress_update_version_id', 'removed_at'],
                'cms_progress_evidence_active_idx',
            );
        });

        Schema::table('cms_progress_updates', function (Blueprint $table): void {
            $table->foreign('current_version_id', 'cms_progress_current_version_fk')
                ->references('id')
                ->on('cms_progress_update_versions')
                ->restrictOnDelete();
            $table->foreign('recorded_version_id', 'cms_progress_recorded_version_fk')
                ->references('id')
                ->on('cms_progress_update_versions')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE cms_progress_updates
                 ADD CONSTRAINT cms_progress_period_check
                 CHECK (reporting_period_end >= reporting_period_start)',
            );
            DB::statement(
                "ALTER TABLE cms_progress_update_versions
                 ADD CONSTRAINT cms_progress_version_status_check
                 CHECK (status_code IN ('DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED', 'RECORDED'))",
            );
            DB::statement(
                "ALTER TABLE cms_progress_update_versions
                 ADD CONSTRAINT cms_progress_active_slot_check
                 CHECK (
                    (status_code IN ('DRAFT', 'SUBMITTED', 'UNDER_REVIEW') AND active_slot = 'ACTIVE')
                    OR
                    (status_code IN ('RETURNED', 'RECORDED') AND active_slot IS NULL)
                 )",
            );
            DB::statement(
                'ALTER TABLE cms_progress_update_versions
                 ADD CONSTRAINT cms_progress_overall_percentage_check
                 CHECK (
                    management_reported_overall_percentage IS NULL
                    OR management_reported_overall_percentage BETWEEN 0 AND 100
                 )',
            );
            DB::statement(
                'ALTER TABLE cms_milestone_progress
                 ADD CONSTRAINT cms_milestone_progress_percentage_check
                 CHECK (management_reported_percentage BETWEEN 0 AND 100)',
            );
            DB::statement(
                "ALTER TABLE cms_milestone_progress
                 ADD CONSTRAINT cms_milestone_progress_status_check
                 CHECK (management_reported_status_code IN (
                    'NOT_STARTED', 'IN_PROGRESS', 'REPORTED_COMPLETED', 'DELAYED', 'ON_HOLD'
                 ))",
            );
        }
    }

    public function down(): void
    {
        Schema::table('cms_progress_updates', function (Blueprint $table): void {
            $table->dropForeign('cms_progress_current_version_fk');
            $table->dropForeign('cms_progress_recorded_version_fk');
        });
        Schema::dropIfExists('cms_progress_evidence_links');
        Schema::dropIfExists('cms_milestone_progress');
        Schema::dropIfExists('cms_progress_update_versions');
        Schema::dropIfExists('cms_progress_updates');
    }
};
