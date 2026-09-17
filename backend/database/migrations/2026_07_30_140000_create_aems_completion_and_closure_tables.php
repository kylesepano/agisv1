<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_recommendations', function (Blueprint $table): void {
            $table->text('cms_exclusion_reason')->nullable();
            $table->string('cms_exclusion_authority')->nullable();
            $table->foreignId('cms_excluded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cms_excluded_at')->nullable();
        });

        Schema::create('completion_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->string('assessment_code', 100);
            $table->unsignedInteger('revision_number')->default(1);
            $table->foreignId('supersedes_assessment_id')->nullable()
                ->constrained('completion_assessments')->restrictOnDelete();
            $table->boolean('is_current_revision')->default(true)->index();
            $table->unsignedInteger('version_no')->default(1);
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('overall_result_code', 40);
            $table->text('objectives_achievement_summary');
            $table->text('scope_completion_summary');
            $table->text('methodology_assessment');
            $table->text('standards_compliance_assessment');
            $table->text('evidence_sufficiency_assessment');
            $table->text('supervision_assessment');
            $table->text('report_timeliness_assessment');
            $table->text('management_response_assessment');
            $table->text('recommendation_transfer_assessment');
            $table->text('resource_utilization_assessment');
            $table->text('limitations_summary')->nullable();
            $table->text('lessons_summary')->nullable();
            $table->text('recommendation_for_closure');
            $table->string('status_code', 40)->default('DRAFT')->index();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('return_comment')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(
                ['audit_engagement_id', 'assessment_code', 'revision_number'],
                'aems_completion_assessment_revision_unique',
            );
        });

        Schema::create('completion_assessment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('completion_assessment_id')
                ->constrained('completion_assessments')->cascadeOnDelete();
            $table->string('criterion_code', 80);
            $table->text('planned_value')->nullable();
            $table->text('actual_value')->nullable();
            $table->string('result_code', 30);
            $table->decimal('variance_value', 14, 2)->nullable();
            $table->text('explanation');
            $table->string('related_record_type', 120)->nullable();
            $table->unsignedBigInteger('related_record_id')->nullable();
            $table->boolean('blocking_flag')->default(false);
            $table->boolean('blocker_accepted')->default(false);
            $table->foreignId('blocker_accepted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('blocker_accepted_at')->nullable();
            $table->text('blocker_acceptance_reason')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['completion_assessment_id', 'criterion_code'],
                'aems_completion_assessment_criterion_unique',
            );
        });

        Schema::create('completion_assessment_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('completion_assessment_id')
                ->constrained('completion_assessments')->restrictOnDelete();
            $table->unsignedInteger('version_no');
            $table->jsonb('snapshot_json');
            $table->foreignId('document_version_id')->nullable()
                ->constrained('document_versions')->restrictOnDelete();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(
                ['completion_assessment_id', 'version_no'],
                'aems_completion_assessment_version_unique',
            );
        });

        Schema::create('engagement_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')
                ->constrained('audit_engagements')->restrictOnDelete();
            $table->string('closure_code', 100)->unique();
            $table->unsignedInteger('revision_number')->default(1);
            $table->foreignId('supersedes_closure_id')->nullable()
                ->constrained('engagement_closures')->restrictOnDelete();
            $table->boolean('is_current_revision')->default(true)->index();
            $table->foreignId('completion_assessment_id')->nullable()
                ->constrained('completion_assessments')->restrictOnDelete();
            $table->text('closure_summary');
            $table->text('unresolved_matters_summary')->nullable();
            $table->text('lessons_learned_summary')->nullable();
            $table->boolean('final_document_index_complete')->default(false);
            $table->boolean('retention_metadata_complete')->default(false);
            $table->boolean('cms_transfer_complete')->default(false);
            $table->boolean('actual_person_days_complete')->default(false);
            $table->string('status_code', 40)->default('DRAFT')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('return_comment')->nullable();
            $table->timestamp('document_index_locked_at')->nullable();
            $table->foreignId('document_index_locked_by')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->jsonb('approved_snapshot_json')->nullable();
            $table->jsonb('closed_snapshot_json')->nullable();
            $table->foreignId('closure_document_version_id')->nullable()
                ->constrained('document_versions')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(
                ['audit_engagement_id', 'revision_number'],
                'aems_engagement_closure_revision_unique',
            );
        });

        Schema::create('engagement_closure_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('engagement_closure_id')
                ->constrained('engagement_closures')->cascadeOnDelete();
            $table->string('checklist_code', 100);
            $table->string('checklist_category_code', 60);
            $table->text('description');
            $table->boolean('required_flag')->default(true);
            $table->string('result_code', 30);
            $table->text('explanation')->nullable();
            $table->string('related_record_type', 120)->nullable();
            $table->unsignedBigInteger('related_record_id')->nullable();
            $table->string('source_path')->nullable();
            $table->jsonb('source_snapshot_json')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('blocking_flag')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(
                ['engagement_closure_id', 'checklist_code'],
                'aems_closure_checklist_code_unique',
            );
        });

        Schema::create('engagement_closure_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('engagement_closure_id')
                ->constrained('engagement_closures')->restrictOnDelete();
            $table->foreignId('audit_engagement_id')
                ->constrained('audit_engagements')->restrictOnDelete();
            $table->string('action_code', 60);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->jsonb('snapshot_json');
            $table->timestamp('occurred_at');
            $table->jsonb('request_metadata_json')->nullable();
            $table->index(['audit_engagement_id', 'occurred_at'], 'aems_closure_event_engagement_idx');
        });

        Schema::create('engagement_document_index_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')
                ->constrained('audit_engagements')->restrictOnDelete();
            $table->foreignId('engagement_closure_id')->nullable()
                ->constrained('engagement_closures')->restrictOnDelete();
            $table->unsignedInteger('sequence_no');
            $table->string('record_category_code', 60);
            $table->string('record_type', 120);
            $table->unsignedBigInteger('record_id')->nullable();
            $table->foreignId('document_id')->nullable()
                ->constrained('documents')->restrictOnDelete();
            $table->foreignId('document_version_id')->nullable()
                ->constrained('document_versions')->restrictOnDelete();
            $table->string('reference_code', 120);
            $table->string('title');
            $table->string('version_label')->nullable();
            $table->date('document_date')->nullable();
            $table->string('confidentiality_code', 60)->default('INTERNAL');
            $table->string('retention_rule_code', 100)->nullable();
            $table->boolean('included_flag')->default(true);
            $table->text('exclusion_reason')->nullable();
            $table->foreignId('exclusion_authorized_by')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->foreignId('indexed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('indexed_at');
            $table->timestamps();
            $table->unique(
                ['audit_engagement_id', 'record_type', 'record_id', 'document_version_id'],
                'aems_document_index_source_unique',
            );
        });

        Schema::create('engagement_retention_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->unique()
                ->constrained('audit_engagements')->restrictOnDelete();
            $table->foreignId('engagement_closure_id')->nullable()
                ->constrained('engagement_closures')->restrictOnDelete();
            $table->string('retention_classification_code', 80);
            $table->string('retention_trigger_code', 80);
            $table->date('retention_start_date');
            $table->unsignedInteger('retention_period_value')->nullable();
            $table->string('retention_period_unit', 20)->nullable();
            $table->boolean('permanent_flag')->default(false);
            $table->date('scheduled_disposition_date')->nullable();
            $table->foreignId('custodian_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('custodian_office_id')->constrained('offices')->restrictOnDelete();
            $table->text('storage_location_description')->nullable();
            $table->boolean('legal_hold_flag')->default(false);
            $table->string('legal_hold_reference')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->jsonb('approved_snapshot_json')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
        });

        Schema::create('engagement_lessons_learned', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')
                ->constrained('audit_engagements')->restrictOnDelete();
            $table->foreignId('engagement_closure_id')->nullable()
                ->constrained('engagement_closures')->restrictOnDelete();
            $table->string('category_code', 60);
            $table->text('observation');
            $table->text('impact');
            $table->text('recommended_improvement');
            $table->foreignId('responsible_office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('target_date')->nullable();
            $table->string('status_code', 30)->default('OPEN');
            $table->string('confidentiality_code', 60)->default('INTERNAL');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('engagement_reopen_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')
                ->constrained('audit_engagements')->restrictOnDelete();
            $table->string('request_code', 100)->unique();
            $table->string('reason_code', 80);
            $table->text('reason_text');
            $table->foreignId('authority_document_id')
                ->constrained('documents')->restrictOnDelete();
            $table->foreignId('authority_document_version_id')
                ->constrained('document_versions')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status_code', 40)->default('DRAFT')->index();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('implemented_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->jsonb('original_closed_snapshot_json')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
        });

        Schema::table('audit_engagements', function (Blueprint $table): void {
            $table->unsignedInteger('reopen_revision_number')->default(0);
            $table->foreignId('current_reopen_request_id')->nullable()
                ->constrained('engagement_reopen_requests')->restrictOnDelete();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reopened_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audit_engagements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_reopen_request_id');
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn('reopened_at');
            $table->dropColumn('reopen_revision_number');
        });
        Schema::dropIfExists('engagement_reopen_requests');
        Schema::dropIfExists('engagement_lessons_learned');
        Schema::dropIfExists('engagement_retention_records');
        Schema::dropIfExists('engagement_document_index_items');
        Schema::dropIfExists('engagement_closure_events');
        Schema::dropIfExists('engagement_closure_checklist_items');
        Schema::dropIfExists('engagement_closures');
        Schema::dropIfExists('completion_assessment_versions');
        Schema::dropIfExists('completion_assessment_items');
        Schema::dropIfExists('completion_assessments');
        Schema::table('audit_recommendations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cms_excluded_by');
            $table->dropColumn([
                'cms_exclusion_reason',
                'cms_exclusion_authority',
                'cms_excluded_at',
            ]);
        });
    }
};
