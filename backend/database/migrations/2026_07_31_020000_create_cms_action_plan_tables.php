<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the versioned management-owned Corrective Action Plan aggregate without
 * changing the immutable AEMS transfer envelope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_corrective_action_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_recommendation_case_id')
                ->unique()
                ->constrained('cms_recommendation_cases')
                ->restrictOnDelete();
            $table->foreignId('owner_office_id')
                ->constrained('offices')
                ->restrictOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedBigInteger('accepted_version_id')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(
                ['owner_office_id', 'created_at'],
                'cms_action_plan_owner_created_idx',
            );
        });

        Schema::create('cms_action_plan_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_corrective_action_plan_id')
                ->constrained('cms_corrective_action_plans')
                ->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('previous_version_id')
                ->nullable()
                ->constrained('cms_action_plan_versions')
                ->restrictOnDelete();
            $table->string('status_code', 30)->default('DRAFT');
            $table->text('plan_summary')->nullable();
            $table->text('implementation_strategy')->nullable();
            $table->text('expected_outcome')->nullable();
            $table->text('root_cause_response')->nullable();
            $table->text('resources_required')->nullable();
            $table->text('dependencies')->nullable();
            $table->text('risks_and_constraints')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_target_date')->nullable();
            $table->foreignId('owner_office_id')
                ->constrained('offices')
                ->restrictOnDelete();
            $table->foreignId('focal_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('prepared_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('submitted_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('review_started_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('review_started_at')->nullable();
            $table->foreignId('accepted_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->text('acceptance_comment')->nullable();
            $table->foreignId('returned_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->text('revision_reason')->nullable();
            $table->jsonb('submission_snapshot')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(
                ['cms_corrective_action_plan_id', 'version_number'],
                'cms_action_plan_family_version_unique',
            );
            $table->index(
                ['cms_corrective_action_plan_id', 'status_code'],
                'cms_action_plan_version_status_idx',
            );
        });

        Schema::create('cms_action_plan_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_action_plan_version_id')
                ->constrained('cms_action_plan_versions')
                ->restrictOnDelete();
            $table->unsignedInteger('sequence_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('expected_output');
            $table->text('success_indicator')->nullable();
            $table->text('verification_method')->nullable();
            $table->foreignId('responsible_office_id')
                ->constrained('offices')
                ->restrictOnDelete();
            $table->foreignId('responsible_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_target_date');
            $table->decimal('weight_percentage', 5, 2)->nullable();
            $table->unsignedInteger('display_order');
            $table->timestamps();

            $table->unique(
                ['cms_action_plan_version_id', 'sequence_number'],
                'cms_action_plan_milestone_sequence_unique',
            );
            $table->index(
                ['responsible_office_id', 'planned_target_date'],
                'cms_action_plan_milestone_owner_target_idx',
            );
        });

        Schema::table('cms_corrective_action_plans', function (Blueprint $table): void {
            $table->foreign('current_version_id', 'cms_action_plan_current_version_fk')
                ->references('id')
                ->on('cms_action_plan_versions')
                ->restrictOnDelete();
            $table->foreign('accepted_version_id', 'cms_action_plan_accepted_version_fk')
                ->references('id')
                ->on('cms_action_plan_versions')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE cms_recommendation_cases
                 DROP CONSTRAINT IF EXISTS cms_case_status_check',
            );
            DB::statement(
                "ALTER TABLE cms_recommendation_cases
                 ADD CONSTRAINT cms_case_status_check
                 CHECK (status_code IN ('TRANSFERRED', 'FOR_ACTION_PLAN', 'MONITORING'))",
            );
            DB::statement(
                "ALTER TABLE cms_action_plan_versions
                 ADD CONSTRAINT cms_action_plan_version_status_check
                 CHECK (status_code IN ('DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED', 'ACCEPTED'))",
            );
            DB::statement(
                'ALTER TABLE cms_action_plan_milestones
                 ADD CONSTRAINT cms_action_plan_milestone_weight_check
                 CHECK (weight_percentage IS NULL OR (weight_percentage > 0 AND weight_percentage <= 100))',
            );
            DB::statement(
                "CREATE UNIQUE INDEX cms_action_plan_one_active_version_unique
                 ON cms_action_plan_versions (cms_corrective_action_plan_id)
                 WHERE status_code IN ('DRAFT', 'SUBMITTED', 'UNDER_REVIEW')",
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'DROP INDEX IF EXISTS cms_action_plan_one_active_version_unique',
            );
            DB::statement(
                'ALTER TABLE cms_action_plan_milestones
                 DROP CONSTRAINT IF EXISTS cms_action_plan_milestone_weight_check',
            );
            DB::statement(
                'ALTER TABLE cms_action_plan_versions
                 DROP CONSTRAINT IF EXISTS cms_action_plan_version_status_check',
            );
            DB::statement(
                'ALTER TABLE cms_recommendation_cases
                 DROP CONSTRAINT IF EXISTS cms_case_status_check',
            );
            DB::statement(
                "ALTER TABLE cms_recommendation_cases
                 ADD CONSTRAINT cms_case_status_check
                 CHECK (status_code = 'TRANSFERRED')",
            );
        }

        Schema::table('cms_corrective_action_plans', function (Blueprint $table): void {
            $table->dropForeign('cms_action_plan_current_version_fk');
            $table->dropForeign('cms_action_plan_accepted_version_fk');
        });
        Schema::dropIfExists('cms_action_plan_milestones');
        Schema::dropIfExists('cms_action_plan_versions');
        Schema::dropIfExists('cms_corrective_action_plans');
    }
};
