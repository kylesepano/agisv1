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
            DB::statement('ALTER TABLE cms_recommendation_cases DROP CONSTRAINT IF EXISTS cms_case_status_check');
            DB::statement("ALTER TABLE cms_recommendation_cases ADD CONSTRAINT cms_case_status_check CHECK (status_code IN ('TRANSFERRED','FOR_ACTION_PLAN','MONITORING','FOR_VALIDATION','PARTIALLY_IMPLEMENTED','IMPLEMENTED','FOR_CLOSURE','CLOSED','FOR_DISPOSITION','ACCEPTED_RISK','NO_LONGER_APPLICABLE'))");
        }

        Schema::create('cms_disposition_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_recommendation_case_id')->constrained('cms_recommendation_cases')->restrictOnDelete();
            $table->unsignedInteger('request_sequence');
            $table->string('disposition_code', 40);
            $table->string('initiator_type_code', 40);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedBigInteger('resolved_version_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['cms_recommendation_case_id', 'request_sequence'], 'cms_disposition_case_sequence_unique');
            $table->index(['cms_recommendation_case_id', 'disposition_code', 'resolved_at'], 'cms_disposition_case_status_idx');
        });

        Schema::create('cms_disposition_request_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_disposition_request_id')->constrained('cms_disposition_requests')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('previous_version_id')->nullable()->constrained('cms_disposition_request_versions')->restrictOnDelete();
            $table->string('status_code', 30)->default('DRAFT');
            $table->string('active_slot', 20)->nullable()->default('ACTIVE');
            $table->string('previous_case_status', 40);
            $table->unsignedInteger('case_lock_version')->default(1);
            $table->date('requested_effective_date')->nullable();
            foreach ([
                'disposition_summary', 'basis_and_criteria', 'risk_impact_assessment',
                'management_position', 'responsible_office_confirmation',
                'accepted_risk_rationale', 'risk_treatment_and_monitoring',
                'no_longer_applicable_basis', 'transition_or_records_impact',
                'residual_risk_statement', 'no_additional_evidence_explanation',
            ] as $field) {
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
            $table->unique(['cms_disposition_request_id', 'version_number'], 'cms_disposition_version_unique');
            $table->unique(['cms_disposition_request_id', 'active_slot'], 'cms_disposition_active_unique');
            $table->index(['status_code', 'active_slot'], 'cms_disposition_version_status_idx');
        });

        Schema::create('cms_disposition_review_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_disposition_request_version_id')->constrained('cms_disposition_request_versions')->restrictOnDelete();
            $table->unique('cms_disposition_request_version_id', 'cms_disposition_review_assessment_version_unique');
            $table->foreignId('reviewer_user_id')->constrained('users')->restrictOnDelete();
            $table->string('recommendation_code', 40);
            foreach (['readiness_assessment', 'basis_assessment', 'evidence_assessment', 'risk_assessment', 'conditions_or_observations'] as $field) {
                $table->text($field);
            }
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });

        Schema::create('cms_disposition_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_disposition_request_version_id')->constrained('cms_disposition_request_versions')->restrictOnDelete();
            $table->unique('cms_disposition_request_version_id', 'cms_disposition_decision_version_unique');
            $table->string('decision_code', 20);
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->text('decision_comment');
            $table->text('override_reason')->nullable();
            $table->string('previous_case_status', 40);
            $table->string('new_case_status', 40);
            $table->date('effective_date')->nullable();
            $table->jsonb('final_snapshot');
            $table->timestamps();
        });

        Schema::create('cms_disposition_evidence_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_disposition_request_version_id')->constrained('cms_disposition_request_versions')->restrictOnDelete();
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
            $table->unique(['cms_disposition_request_version_id', 'document_version_id'], 'cms_disposition_evidence_exact_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_disposition_evidence_links');
        Schema::dropIfExists('cms_disposition_decisions');
        Schema::dropIfExists('cms_disposition_review_assessments');
        Schema::dropIfExists('cms_disposition_request_versions');
        Schema::dropIfExists('cms_disposition_requests');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE cms_recommendation_cases DROP CONSTRAINT IF EXISTS cms_case_status_check');
            DB::statement("ALTER TABLE cms_recommendation_cases ADD CONSTRAINT cms_case_status_check CHECK (status_code IN ('TRANSFERRED','FOR_ACTION_PLAN','MONITORING','FOR_VALIDATION','PARTIALLY_IMPLEMENTED','IMPLEMENTED','FOR_CLOSURE','CLOSED'))");
        }
    }
};
