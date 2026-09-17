<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Adds the CMS-7A formal escalation, response, evidence, and resolution aggregates. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_escalations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_recommendation_case_id')->constrained('cms_recommendation_cases')->restrictOnDelete();
            $table->unsignedInteger('escalation_sequence');
            $table->string('primary_trigger_code', 80);
            $table->jsonb('trigger_snapshot');
            $table->date('source_effective_target_date')->nullable();
            $table->string('source_case_status', 40);
            $table->string('operational_status_code', 40)->default('PREPARATION');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('current_notice_version_id')->nullable();
            $table->unsignedBigInteger('issued_notice_version_id')->nullable();
            $table->unsignedBigInteger('current_response_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['cms_recommendation_case_id', 'escalation_sequence'], 'cms_escalation_sequence_unique');
            $table->index(['cms_recommendation_case_id', 'resolved_at'], 'cms_escalation_case_resolution_idx');
        });

        Schema::create('cms_escalation_notice_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_escalation_id')->constrained('cms_escalations')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('previous_version_id')->nullable()->constrained('cms_escalation_notice_versions')->restrictOnDelete();
            $table->string('status_code', 30)->default('DRAFT');
            $table->string('active_slot', 20)->nullable();
            $table->string('subject', 255);
            $table->text('escalation_summary');
            $table->text('basis_and_context');
            $table->text('required_management_actions');
            $table->text('required_response_contents');
            $table->date('response_due_date');
            $table->text('consequence_or_follow_up_statement')->nullable();
            $table->boolean('management_attention_requested')->default(true);
            $table->text('additional_trigger_explanation')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('review_started_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('review_started_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->text('issuance_comment')->nullable();
            $table->jsonb('submission_snapshot')->nullable();
            $table->jsonb('issuance_snapshot')->nullable();
            $table->text('revision_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['cms_escalation_id', 'version_number'], 'cms_escalation_notice_version_unique');
            $table->index(['cms_escalation_id', 'status_code'], 'cms_escalation_notice_status_idx');
        });

        Schema::create('cms_escalation_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_escalation_notice_version_id')->constrained('cms_escalation_notice_versions')->restrictOnDelete();
            $table->string('recipient_type', 30);
            $table->foreignId('office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('recipient_name_snapshot', 255);
            $table->string('office_name_snapshot', 255)->nullable();
            $table->string('position_or_role_snapshot', 255)->nullable();
            $table->string('channel_metadata', 255)->nullable();
            $table->foreignId('selected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('selected_at');
            $table->string('delivery_reference', 255)->nullable();
            $table->timestamps();
            $table->index(['cms_escalation_notice_version_id', 'recipient_type'], 'cms_escalation_recipient_type_idx');
        });

        Schema::create('cms_escalation_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_escalation_id')->constrained('cms_escalations')->restrictOnDelete();
            $table->foreignId('cms_escalation_notice_version_id')->constrained('cms_escalation_notice_versions')->restrictOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('cms_escalation_recipients')->restrictOnDelete();
            $table->timestamp('acknowledged_at');
            $table->text('acknowledgement_comment')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['cms_escalation_notice_version_id', 'office_id'], 'cms_escalation_ack_notice_office_unique');
        });

        Schema::create('cms_escalation_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_escalation_id')->constrained('cms_escalations')->restrictOnDelete();
            $table->foreignId('issued_notice_version_id')->constrained('cms_escalation_notice_versions')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedBigInteger('accepted_version_id')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique('cms_escalation_id', 'cms_escalation_response_family_unique');
        });

        Schema::create('cms_escalation_response_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_escalation_response_id')->constrained('cms_escalation_responses')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('previous_version_id')->nullable()->constrained('cms_escalation_response_versions')->restrictOnDelete();
            $table->string('status_code', 30)->default('DRAFT');
            $table->string('active_slot', 20)->nullable();
            $table->text('management_response_summary');
            $table->text('root_cause_or_explanation');
            $table->text('actions_completed');
            $table->text('remaining_actions');
            $table->text('committed_actions');
            $table->string('responsible_person_or_office', 255);
            $table->date('commitment_start_date')->nullable();
            $table->date('commitment_target_date');
            $table->text('resource_or_dependency_needs')->nullable();
            $table->text('request_for_cias_guidance')->nullable();
            $table->text('no_evidence_explanation')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('review_started_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('review_started_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->text('acceptance_comment')->nullable();
            $table->jsonb('submission_snapshot')->nullable();
            $table->text('revision_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['cms_escalation_response_id', 'version_number'], 'cms_escalation_response_version_unique');
            $table->index(['cms_escalation_response_id', 'status_code'], 'cms_escalation_response_status_idx');
        });

        foreach ([
            'cms_escalation_notice_evidence_links' => 'cms_escalation_notice_version_id',
            'cms_escalation_response_evidence_links' => 'cms_escalation_response_version_id',
        ] as $tableName => $parentColumn) {
            Schema::create($tableName, function (Blueprint $table) use ($parentColumn, $tableName): void {
                $table->id();
                $table->unsignedBigInteger($parentColumn);
                $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
                $table->foreignId('document_version_id')->constrained('document_versions')->restrictOnDelete();
                $table->string('evidence_category', 80);
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->string('source_or_custodian', 255)->nullable();
                $table->foreignId('linked_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('linked_at');
                $table->string('checksum_sha256', 64);
                $table->foreignId('confidentiality_level_id')->constrained('master_list_items')->restrictOnDelete();
                $table->string('confidentiality_code_snapshot', 80);
                $table->foreignId('removed_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('removed_at')->nullable();
                $table->text('removal_reason')->nullable();
                $table->timestamps();
                $table->index([$parentColumn, 'removed_at'], $tableName.'_active_idx');
            });
        }

        Schema::create('cms_escalation_resolutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_escalation_id')->constrained('cms_escalations')->restrictOnDelete();
            $table->string('resolution_code', 60);
            $table->text('resolution_summary');
            $table->text('basis_for_resolution');
            $table->text('follow_up_requirements');
            $table->foreignId('resolved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at');
            $table->string('recommendation_case_status_snapshot', 40);
            $table->unsignedBigInteger('accepted_response_version_id')->nullable();
            $table->unsignedBigInteger('latest_progress_update_version_id')->nullable();
            $table->unsignedBigInteger('latest_validation_review_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique('cms_escalation_id', 'cms_escalation_resolution_unique');
        });

        Schema::table('cms_escalations', function (Blueprint $table): void {
            $table->foreign('current_notice_version_id', 'cms_escalation_current_notice_fk')->references('id')->on('cms_escalation_notice_versions')->restrictOnDelete();
            $table->foreign('issued_notice_version_id', 'cms_escalation_issued_notice_fk')->references('id')->on('cms_escalation_notice_versions')->restrictOnDelete();
            $table->foreign('current_response_id', 'cms_escalation_current_response_fk')->references('id')->on('cms_escalation_responses')->restrictOnDelete();
        });
        Schema::table('cms_escalation_responses', function (Blueprint $table): void {
            $table->foreign('current_version_id', 'cms_escalation_response_current_version_fk')->references('id')->on('cms_escalation_response_versions')->restrictOnDelete();
            $table->foreign('accepted_version_id', 'cms_escalation_response_accepted_version_fk')->references('id')->on('cms_escalation_response_versions')->restrictOnDelete();
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX cms_escalation_one_unresolved_case_unique ON cms_escalations (cms_recommendation_case_id) WHERE resolved_at IS NULL');
            DB::statement("CREATE UNIQUE INDEX cms_escalation_one_active_notice_unique ON cms_escalation_notice_versions (cms_escalation_id) WHERE status_code IN ('DRAFT','SUBMITTED','UNDER_REVIEW')");
            DB::statement("CREATE UNIQUE INDEX cms_escalation_one_active_response_unique ON cms_escalation_response_versions (cms_escalation_response_id) WHERE status_code IN ('DRAFT','SUBMITTED','UNDER_REVIEW')");
        }
    }

    public function down(): void
    {
        foreach (['cms_escalation_one_active_response_unique', 'cms_escalation_one_active_notice_unique', 'cms_escalation_one_unresolved_case_unique'] as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
        Schema::table('cms_escalation_responses', function (Blueprint $table): void {
            $table->dropForeign('cms_escalation_response_current_version_fk');
            $table->dropForeign('cms_escalation_response_accepted_version_fk');
        });
        Schema::table('cms_escalations', function (Blueprint $table): void {
            $table->dropForeign('cms_escalation_current_notice_fk');
            $table->dropForeign('cms_escalation_issued_notice_fk');
            $table->dropForeign('cms_escalation_current_response_fk');
        });
        Schema::dropIfExists('cms_escalation_resolutions');
        Schema::dropIfExists('cms_escalation_response_evidence_links');
        Schema::dropIfExists('cms_escalation_notice_evidence_links');
        Schema::dropIfExists('cms_escalation_response_versions');
        Schema::dropIfExists('cms_escalation_responses');
        Schema::dropIfExists('cms_escalation_acknowledgements');
        Schema::dropIfExists('cms_escalation_recipients');
        Schema::dropIfExists('cms_escalation_notice_versions');
        Schema::dropIfExists('cms_escalations');
    }
};
