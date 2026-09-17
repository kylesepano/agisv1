<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_recommendation_cases', function (Blueprint $table): void {
            $table->unsignedInteger('active_cycle_number')->default(1);
            $table->unsignedInteger('reopening_count')->default(0);
            $table->timestamp('last_reopened_at')->nullable();
            $table->foreignId('last_reopened_by')->nullable()->constrained('users')->restrictOnDelete();
        });

        Schema::create('cms_reopening_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_recommendation_case_id')->constrained('cms_recommendation_cases')->restrictOnDelete();
            $table->unsignedInteger('request_sequence');
            $table->string('initiator_type_code', 40);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('source_terminal_status', 40);
            $table->foreignId('source_closure_decision_id')->nullable()->constrained('cms_closure_decisions')->restrictOnDelete();
            $table->foreignId('source_disposition_decision_id')->nullable()->constrained('cms_disposition_decisions')->restrictOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedBigInteger('resolved_version_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['cms_recommendation_case_id', 'request_sequence'], 'cms_reopening_case_sequence_unique');
            $table->index(['cms_recommendation_case_id', 'resolved_at'], 'cms_reopening_case_resolution_idx');
        });

        Schema::create('cms_reopening_request_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_reopening_request_id')->constrained('cms_reopening_requests')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('previous_version_id')->nullable()->constrained('cms_reopening_request_versions')->restrictOnDelete();
            $table->string('status_code', 30)->default('DRAFT');
            $table->string('active_slot', 20)->nullable()->default('ACTIVE');
            $table->string('reopening_reason_code', 80);
            $table->text('request_summary')->nullable();
            $table->text('changed_condition_or_new_fact')->nullable();
            $table->text('materiality_assessment')->nullable();
            $table->text('source_terminal_decision_assessment')->nullable();
            $table->text('implementation_or_control_failure_assessment')->nullable();
            $table->text('risk_impact')->nullable();
            $table->text('responsible_office_impact')->nullable();
            $table->text('proposed_follow_up_approach')->nullable();
            $table->string('proposed_destination_code', 40)->nullable();
            $table->text('new_action_plan_requirement_explanation')->nullable();
            $table->text('existing_action_plan_suitability_assessment')->nullable();
            $table->text('compliance_monitor_requirement')->nullable();
            $table->text('target_date_implications')->nullable();
            $table->text('related_recurrence_summary')->nullable();
            $table->text('related_escalation_summary')->nullable();
            $table->text('management_position')->nullable();
            $table->text('cias_initiator_position')->nullable();
            $table->text('no_additional_evidence_explanation')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('review_started_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('review_started_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->text('revision_reason')->nullable();
            $table->jsonb('submission_snapshot')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['cms_reopening_request_id', 'version_number'], 'cms_reopening_version_unique');
            $table->unique(['cms_reopening_request_id', 'active_slot'], 'cms_reopening_active_unique');
            $table->index(['status_code', 'active_slot'], 'cms_reopening_version_status_idx');
        });

        Schema::table('cms_reopening_requests', function (Blueprint $table): void {
            $table->foreign('current_version_id')->references('id')->on('cms_reopening_request_versions')->restrictOnDelete();
            $table->foreign('resolved_version_id')->references('id')->on('cms_reopening_request_versions')->restrictOnDelete();
        });

        Schema::create('cms_reopening_review_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_reopening_request_version_id')->constrained('cms_reopening_request_versions')->restrictOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->restrictOnDelete();
            $table->string('recommendation_code', 40);
            $table->text('source_decision_integrity_assessment');
            $table->text('new_evidence_or_changed_condition_assessment');
            $table->text('materiality_assessment');
            $table->text('risk_assessment');
            $table->text('destination_status_assessment');
            $table->text('action_plan_requirement_assessment');
            $table->text('assignment_and_monitoring_assessment');
            $table->text('evidence_sufficiency_assessment');
            $table->text('recommendation_rationale');
            $table->text('conditions_or_observations')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();
            $table->unique('cms_reopening_request_version_id', 'cms_reopening_review_assessment_version_unique');
        });

        Schema::create('cms_reopening_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_reopening_request_version_id')->constrained('cms_reopening_request_versions')->restrictOnDelete();
            $table->string('decision_code', 20);
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->text('decision_comment');
            $table->text('override_reason')->nullable();
            $table->string('source_terminal_status', 40);
            $table->string('approved_destination_status', 40)->nullable();
            $table->unsignedInteger('previous_active_cycle_number');
            $table->unsignedInteger('new_active_cycle_number')->nullable();
            $table->boolean('existing_action_plan_retained')->default(false);
            $table->foreignId('retained_action_plan_version_id')->nullable()->constrained('cms_action_plan_versions')->restrictOnDelete();
            $table->boolean('new_action_plan_required')->default(false);
            $table->boolean('assignment_follow_up_required')->default(false);
            $table->boolean('target_date_follow_up_required')->default(false);
            $table->date('reopening_effective_date');
            $table->jsonb('final_snapshot');
            $table->timestamps();
            $table->unique('cms_reopening_request_version_id', 'cms_reopening_decision_version_unique');
        });

        Schema::table('cms_recommendation_cases', function (Blueprint $table): void {
            $table->foreignId('last_reopening_decision_id')->nullable()->constrained('cms_reopening_decisions')->restrictOnDelete();
        });

        Schema::create('cms_reopening_evidence_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_reopening_request_version_id')->constrained('cms_reopening_request_versions')->restrictOnDelete();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignId('document_version_id')->constrained('document_versions')->restrictOnDelete();
            $table->string('evidence_category', 80);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('source_or_custodian')->nullable();
            $table->foreignId('linked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('linked_at');
            $table->string('checksum_sha256', 64);
            $table->string('confidentiality_code_snapshot', 80)->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->text('removal_reason')->nullable();
            $table->timestamps();
            $table->unique(['cms_reopening_request_version_id', 'document_version_id'], 'cms_reopening_evidence_exact_unique');
        });

        DB::statement('CREATE UNIQUE INDEX cms_reopening_one_unresolved_idx ON cms_reopening_requests (cms_recommendation_case_id) WHERE resolved_at IS NULL');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE cms_reopening_requests ADD CONSTRAINT cms_reopening_source_status_check CHECK (source_terminal_status IN ('CLOSED','ACCEPTED_RISK','NO_LONGER_APPLICABLE'))");
            DB::statement("ALTER TABLE cms_reopening_requests ADD CONSTRAINT cms_reopening_source_decision_check CHECK ((source_terminal_status = 'CLOSED' AND source_closure_decision_id IS NOT NULL AND source_disposition_decision_id IS NULL) OR (source_terminal_status IN ('ACCEPTED_RISK','NO_LONGER_APPLICABLE') AND source_closure_decision_id IS NULL AND source_disposition_decision_id IS NOT NULL))");
            DB::statement("ALTER TABLE cms_reopening_request_versions ADD CONSTRAINT cms_reopening_version_status_check CHECK (status_code IN ('DRAFT','SUBMITTED','UNDER_REVIEW','RETURNED','FOR_DECISION','APPROVED','REJECTED'))");
            DB::statement("ALTER TABLE cms_reopening_request_versions ADD CONSTRAINT cms_reopening_destination_check CHECK (proposed_destination_code IS NULL OR proposed_destination_code IN ('FOR_ACTION_PLAN','MONITORING'))");
            DB::statement("ALTER TABLE cms_reopening_review_assessments ADD CONSTRAINT cms_reopening_review_code_check CHECK (recommendation_code IN ('RECOMMEND_APPROVAL','RECOMMEND_REJECTION'))");
            DB::statement("ALTER TABLE cms_reopening_decisions ADD CONSTRAINT cms_reopening_decision_code_check CHECK (decision_code IN ('APPROVED','REJECTED'))");
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS cms_reopening_one_unresolved_idx');
        Schema::table('cms_recommendation_cases', function (Blueprint $table): void {
            $table->dropForeign(['last_reopening_decision_id']);
        });
        Schema::dropIfExists('cms_reopening_evidence_links');
        Schema::dropIfExists('cms_reopening_decisions');
        Schema::dropIfExists('cms_reopening_review_assessments');
        Schema::table('cms_reopening_requests', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
            $table->dropForeign(['resolved_version_id']);
        });
        Schema::dropIfExists('cms_reopening_request_versions');
        Schema::dropIfExists('cms_reopening_requests');
        Schema::table('cms_recommendation_cases', function (Blueprint $table): void {
            $table->dropForeign(['last_reopened_by']);
            $table->dropColumn(['active_cycle_number', 'reopening_count', 'last_reopened_at', 'last_reopened_by']);
        });
    }
};
