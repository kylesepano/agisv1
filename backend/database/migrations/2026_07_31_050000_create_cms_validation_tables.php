<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds independent validation without changing management-reported progress,
 * closing recommendations, or introducing extension/escalation workflows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_validation_reviews', function (Blueprint $table): void {
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
            $table->foreignId('cms_progress_update_id')
                ->constrained('cms_progress_updates')
                ->restrictOnDelete();
            $table->foreignId('recorded_progress_update_version_id')
                ->unique('cms_validation_recorded_version_unique')
                ->constrained('cms_progress_update_versions')
                ->restrictOnDelete();
            $table->unsignedInteger('validation_sequence');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedBigInteger('finalized_version_id')->nullable();
            $table->string('active_slot', 20)->nullable()->default('ACTIVE');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(
                ['cms_recommendation_case_id', 'validation_sequence'],
                'cms_validation_case_sequence_unique',
            );
            $table->unique(
                ['cms_recommendation_case_id', 'active_slot'],
                'cms_validation_case_active_unique',
            );
        });

        Schema::create('cms_validation_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_validation_review_id')
                ->constrained('cms_validation_reviews')
                ->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('previous_version_id')
                ->nullable()
                ->constrained('cms_validation_versions')
                ->restrictOnDelete();
            $table->string('status_code', 30)->default('DRAFT');
            $table->string('active_slot', 20)->nullable()->default('ACTIVE');
            $table->text('validation_scope')->nullable();
            $table->text('validation_objectives')->nullable();
            $table->text('methodology_summary')->nullable();
            $table->text('overall_work_performed')->nullable();
            $table->text('overall_evidence_summary')->nullable();
            $table->text('limitations')->nullable();
            $table->text('professional_judgment_rationale')->nullable();
            $table->string('proposed_conclusion_code', 40)->nullable();
            $table->string('final_conclusion_code', 40)->nullable();
            $table->decimal('validated_completion_percentage', 5, 2)->nullable();
            $table->foreignId('validator_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('supervisory_review_started_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('supervisory_review_started_at')->nullable();
            $table->text('supervisory_review_comment')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->text('finalization_comment')->nullable();
            $table->text('supervisory_override_reason')->nullable();
            $table->text('revision_reason')->nullable();
            $table->jsonb('submission_snapshot')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(
                ['cms_validation_review_id', 'version_number'],
                'cms_validation_review_version_unique',
            );
            $table->unique(
                ['cms_validation_review_id', 'active_slot'],
                'cms_validation_review_active_version_unique',
            );
            $table->index(
                ['cms_validation_review_id', 'status_code'],
                'cms_validation_version_status_idx',
            );
        });

        Schema::create('cms_validation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_validation_version_id')
                ->constrained('cms_validation_versions')
                ->restrictOnDelete();
            $table->string('scope_code', 30);
            $table->foreignId('cms_action_plan_milestone_id')
                ->nullable()
                ->constrained('cms_action_plan_milestones')
                ->restrictOnDelete();
            $table->foreignId('cms_milestone_progress_id')
                ->nullable()
                ->constrained('cms_milestone_progress')
                ->restrictOnDelete();
            $table->unsignedInteger('sequence_number');
            $table->text('criterion')->nullable();
            $table->text('procedure_performed')->nullable();
            $table->text('population_or_source')->nullable();
            $table->text('sample_description')->nullable();
            $table->text('result_summary')->nullable();
            $table->text('exception_summary')->nullable();
            $table->string('item_conclusion_code', 40)->nullable();
            $table->decimal('validated_milestone_percentage', 5, 2)->nullable();
            $table->boolean('follow_up_required')->nullable();
            $table->unsignedInteger('display_order');
            $table->timestamps();

            $table->unique(
                ['cms_validation_version_id', 'cms_action_plan_milestone_id'],
                'cms_validation_item_milestone_unique',
            );
            $table->unique(
                ['cms_validation_version_id', 'sequence_number'],
                'cms_validation_item_sequence_unique',
            );
            $table->index(
                ['cms_validation_version_id', 'display_order'],
                'cms_validation_item_order_idx',
            );
        });

        Schema::create('cms_validation_evidence_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_validation_version_id')
                ->constrained('cms_validation_versions')
                ->restrictOnDelete();
            $table->foreignId('cms_validation_item_id')
                ->nullable()
                ->constrained('cms_validation_items')
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
                ['cms_validation_version_id', 'document_version_id', 'cms_validation_item_id'],
                'cms_validation_evidence_exact_link_unique',
            );
            $table->index(
                ['cms_validation_version_id', 'removed_at'],
                'cms_validation_evidence_active_idx',
            );
        });

        Schema::create('cms_validation_evidence_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_validation_version_id')
                ->constrained('cms_validation_versions')
                ->restrictOnDelete();
            $table->foreignId('cms_validation_item_id')
                ->nullable()
                ->constrained('cms_validation_items')
                ->restrictOnDelete();
            $table->foreignId('cms_progress_evidence_link_id')
                ->nullable()
                ->constrained('cms_progress_evidence_links')
                ->restrictOnDelete();
            $table->foreignId('cms_validation_evidence_link_id')
                ->nullable()
                ->constrained('cms_validation_evidence_links')
                ->restrictOnDelete();
            $table->string('evidence_source_code', 40);
            $table->string('relevance_code', 30)->default('NOT_ASSESSED');
            $table->string('reliability_code', 30)->default('NOT_ASSESSED');
            $table->string('sufficiency_code', 30)->default('NOT_ASSESSED');
            $table->boolean('relied_upon')->default(false);
            $table->text('assessment_summary')->nullable();
            $table->text('limitation_summary')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['cms_validation_version_id', 'cms_progress_evidence_link_id'],
                'cms_validation_management_evidence_unique',
            );
            $table->unique(
                ['cms_validation_version_id', 'cms_validation_evidence_link_id'],
                'cms_validation_obtained_evidence_unique',
            );
        });

        Schema::create('cms_validation_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_validation_review_id')
                ->constrained('cms_validation_reviews')
                ->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('assignment_role_code', 40)->default('PRIMARY_VALIDATOR');
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->text('assignment_reason');
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('ended_at')->nullable();
            $table->text('end_reason')->nullable();
            $table->boolean('is_current')->default(true);
            $table->string('current_slot', 20)->nullable()->default('CURRENT');
            $table->timestamps();

            $table->unique(
                ['cms_validation_review_id', 'current_slot'],
                'cms_validation_current_assignment_unique',
            );
            $table->index(
                ['user_id', 'is_current'],
                'cms_validation_assignment_user_current_idx',
            );
        });

        Schema::table('cms_validation_reviews', function (Blueprint $table): void {
            $table->foreign('current_version_id', 'cms_validation_current_version_fk')
                ->references('id')
                ->on('cms_validation_versions')
                ->restrictOnDelete();
            $table->foreign('finalized_version_id', 'cms_validation_finalized_version_fk')
                ->references('id')
                ->on('cms_validation_versions')
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
                 CHECK (status_code IN (
                    'TRANSFERRED', 'FOR_ACTION_PLAN', 'MONITORING',
                    'FOR_VALIDATION', 'PARTIALLY_IMPLEMENTED', 'IMPLEMENTED'
                 ))",
            );
            DB::statement(
                "ALTER TABLE cms_validation_reviews
                 ADD CONSTRAINT cms_validation_review_active_slot_check
                 CHECK (active_slot IS NULL OR active_slot = 'ACTIVE')",
            );
            DB::statement(
                "ALTER TABLE cms_validation_versions
                 ADD CONSTRAINT cms_validation_version_status_check
                 CHECK (status_code IN ('DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED', 'FINALIZED'))",
            );
            DB::statement(
                "ALTER TABLE cms_validation_versions
                 ADD CONSTRAINT cms_validation_version_active_slot_check
                 CHECK (
                    (status_code IN ('DRAFT', 'SUBMITTED', 'UNDER_REVIEW') AND active_slot = 'ACTIVE')
                    OR
                    (status_code IN ('RETURNED', 'FINALIZED') AND active_slot IS NULL)
                 )",
            );
            DB::statement(
                "ALTER TABLE cms_validation_versions
                 ADD CONSTRAINT cms_validation_proposed_conclusion_check
                 CHECK (proposed_conclusion_code IS NULL OR proposed_conclusion_code IN (
                    'NOT_IMPLEMENTED', 'PARTIALLY_IMPLEMENTED', 'IMPLEMENTED', 'INADEQUATE_BASIS'
                 ))",
            );
            DB::statement(
                "ALTER TABLE cms_validation_versions
                 ADD CONSTRAINT cms_validation_final_conclusion_check
                 CHECK (final_conclusion_code IS NULL OR final_conclusion_code IN (
                    'NOT_IMPLEMENTED', 'PARTIALLY_IMPLEMENTED', 'IMPLEMENTED', 'INADEQUATE_BASIS'
                 ))",
            );
            DB::statement(
                'ALTER TABLE cms_validation_versions
                 ADD CONSTRAINT cms_validation_completion_percentage_check
                 CHECK (
                    validated_completion_percentage IS NULL
                    OR validated_completion_percentage BETWEEN 0 AND 100
                 )',
            );
            DB::statement(
                "ALTER TABLE cms_validation_items
                 ADD CONSTRAINT cms_validation_item_scope_check
                 CHECK (
                    (scope_code = 'MILESTONE' AND cms_action_plan_milestone_id IS NOT NULL)
                    OR
                    (scope_code = 'RECOMMENDATION' AND cms_action_plan_milestone_id IS NULL)
                 )",
            );
            DB::statement(
                "ALTER TABLE cms_validation_items
                 ADD CONSTRAINT cms_validation_item_conclusion_check
                 CHECK (item_conclusion_code IS NULL OR item_conclusion_code IN (
                    'SATISFIED', 'PARTIALLY_SATISFIED', 'NOT_SATISFIED',
                    'INADEQUATE_BASIS', 'NOT_APPLICABLE'
                 ))",
            );
            DB::statement(
                'ALTER TABLE cms_validation_items
                 ADD CONSTRAINT cms_validation_item_percentage_check
                 CHECK (
                    validated_milestone_percentage IS NULL
                    OR validated_milestone_percentage BETWEEN 0 AND 100
                 )',
            );
            DB::statement(
                'ALTER TABLE cms_validation_evidence_assessments
                 ADD CONSTRAINT cms_validation_assessment_reference_check
                 CHECK (
                    (cms_progress_evidence_link_id IS NOT NULL)::int
                    + (cms_validation_evidence_link_id IS NOT NULL)::int = 1
                 )',
            );
            DB::statement(
                "ALTER TABLE cms_validation_evidence_assessments
                 ADD CONSTRAINT cms_validation_assessment_source_check
                 CHECK (
                    (evidence_source_code = 'MANAGEMENT_SUBMITTED' AND cms_progress_evidence_link_id IS NOT NULL)
                    OR
                    (evidence_source_code = 'VALIDATOR_OBTAINED' AND cms_validation_evidence_link_id IS NOT NULL)
                 )",
            );
            DB::statement(
                "ALTER TABLE cms_validation_evidence_assessments
                 ADD CONSTRAINT cms_validation_assessment_relevance_check
                 CHECK (relevance_code IN (
                    'RELEVANT', 'PARTIALLY_RELEVANT', 'NOT_RELEVANT', 'NOT_ASSESSED'
                 ))",
            );
            DB::statement(
                "ALTER TABLE cms_validation_evidence_assessments
                 ADD CONSTRAINT cms_validation_assessment_reliability_check
                 CHECK (reliability_code IN (
                    'RELIABLE', 'LIMITED_RELIABILITY', 'UNRELIABLE', 'NOT_ASSESSED'
                 ))",
            );
            DB::statement(
                "ALTER TABLE cms_validation_evidence_assessments
                 ADD CONSTRAINT cms_validation_assessment_sufficiency_check
                 CHECK (sufficiency_code IN (
                    'SUFFICIENT', 'PARTIALLY_SUFFICIENT', 'INSUFFICIENT', 'NOT_ASSESSED'
                 ))",
            );
            DB::statement(
                "ALTER TABLE cms_validation_assignments
                 ADD CONSTRAINT cms_validation_assignment_role_check
                 CHECK (assignment_role_code = 'PRIMARY_VALIDATOR')",
            );
            DB::statement(
                "ALTER TABLE cms_validation_assignments
                 ADD CONSTRAINT cms_validation_assignment_current_check
                 CHECK (
                    (is_current = TRUE AND current_slot = 'CURRENT' AND ended_at IS NULL)
                    OR
                    (is_current = FALSE AND current_slot IS NULL AND ended_at IS NOT NULL)
                 )",
            );
        }
    }

    public function down(): void
    {
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
        }

        Schema::table('cms_validation_reviews', function (Blueprint $table): void {
            $table->dropForeign('cms_validation_current_version_fk');
            $table->dropForeign('cms_validation_finalized_version_fk');
        });
        Schema::dropIfExists('cms_validation_assignments');
        Schema::dropIfExists('cms_validation_evidence_assessments');
        Schema::dropIfExists('cms_validation_evidence_links');
        Schema::dropIfExists('cms_validation_items');
        Schema::dropIfExists('cms_validation_versions');
        Schema::dropIfExists('cms_validation_reviews');
    }
};
