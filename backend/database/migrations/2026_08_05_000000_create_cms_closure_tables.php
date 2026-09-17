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
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('closure_decision_id')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE cms_recommendation_cases DROP CONSTRAINT IF EXISTS cms_case_status_check');
            DB::statement("ALTER TABLE cms_recommendation_cases ADD CONSTRAINT cms_case_status_check CHECK (status_code IN ('TRANSFERRED','FOR_ACTION_PLAN','MONITORING','FOR_VALIDATION','PARTIALLY_IMPLEMENTED','IMPLEMENTED','FOR_CLOSURE','CLOSED'))");
        }

        Schema::create('cms_closure_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_recommendation_case_id')->constrained('cms_recommendation_cases')->restrictOnDelete();
            $table->unsignedInteger('request_sequence');
            $table->string('initiator_type_code', 40);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedBigInteger('resolved_version_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['cms_recommendation_case_id', 'request_sequence'], 'cms_closure_case_sequence_unique');
            $table->index(['cms_recommendation_case_id', 'resolved_at']);
        });

        Schema::create('cms_closure_request_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_closure_request_id')->constrained('cms_closure_requests')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('previous_version_id')->nullable()->constrained('cms_closure_request_versions')->restrictOnDelete();
            $table->string('status_code', 30)->default('DRAFT');
            $table->string('active_slot', 20)->nullable()->default('ACTIVE');
            $table->foreignId('finalized_validation_review_id')->constrained('cms_validation_reviews')->restrictOnDelete();
            $table->foreignId('finalized_validation_version_id')->constrained('cms_validation_versions')->restrictOnDelete();
            $table->foreignId('accepted_action_plan_version_id')->constrained('cms_action_plan_versions')->restrictOnDelete();
            $table->foreignId('recorded_progress_update_version_id')->constrained('cms_progress_update_versions')->restrictOnDelete();
            foreach (['closure_request_summary', 'implementation_basis', 'validated_implementation_summary', 'residual_matters_summary', 'residual_risk_statement', 'ongoing_monitoring_requirements', 'records_and_documentation_summary', 'resolved_escalation_summary', 'management_confirmation', 'compliance_monitor_recommendation_summary', 'no_additional_evidence_explanation'] as $field) {
                $table->text($field)->nullable();
            }
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
            $table->unique(['cms_closure_request_id', 'version_number'], 'cms_closure_request_version_unique');
            $table->unique(['cms_closure_request_id', 'active_slot'], 'cms_closure_request_active_unique');
        });

        Schema::create('cms_closure_review_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_closure_request_version_id')->unique()->constrained('cms_closure_request_versions')->restrictOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->restrictOnDelete();
            $table->string('recommendation_code', 30);
            foreach (['readiness_summary', 'validation_lineage_assessment', 'document_and_evidence_assessment', 'residual_matter_assessment', 'escalation_and_extension_assessment', 'records_completeness_assessment', 'conditions_or_observations'] as $field) {
                $table->text($field)->nullable();
            }
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });

        Schema::create('cms_closure_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_closure_request_version_id')->unique()->constrained('cms_closure_request_versions')->restrictOnDelete();
            $table->string('decision_code', 20);
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->text('decision_comment');
            $table->text('override_reason')->nullable();
            $table->string('previous_case_status', 30);
            $table->string('new_case_status', 30);
            $table->date('closure_effective_date')->nullable();
            $table->jsonb('final_snapshot');
            $table->timestamps();
        });

        Schema::table('cms_recommendation_cases', function (Blueprint $table): void {
            $table->foreign('closure_decision_id')->references('id')->on('cms_closure_decisions')->restrictOnDelete();
        });

        Schema::create('cms_closure_evidence_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_closure_request_version_id')->constrained('cms_closure_request_versions')->restrictOnDelete();
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
            $table->unique(['cms_closure_request_version_id', 'document_version_id'], 'cms_closure_evidence_exact_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_closure_evidence_links');
        Schema::table('cms_recommendation_cases', function (Blueprint $table): void {
            $table->dropForeign(['closure_decision_id']);
        });
        Schema::dropIfExists('cms_closure_decisions');
        Schema::dropIfExists('cms_closure_review_assessments');
        Schema::dropIfExists('cms_closure_request_versions');
        Schema::dropIfExists('cms_closure_requests');
        Schema::table('cms_recommendation_cases', function (Blueprint $table): void {
            $table->dropForeign(['closed_by']);
            $table->dropColumn(['closed_at', 'closed_by', 'closure_decision_id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE cms_recommendation_cases DROP CONSTRAINT IF EXISTS cms_case_status_check');
            DB::statement("ALTER TABLE cms_recommendation_cases ADD CONSTRAINT cms_case_status_check CHECK (status_code IN ('TRANSFERRED','FOR_ACTION_PLAN','MONITORING','FOR_VALIDATION','PARTIALLY_IMPLEMENTED','IMPLEMENTED'))");
        }
    }
};
