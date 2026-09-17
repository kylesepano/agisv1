<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Creates the complete AEMS engagement, fieldwork, finding, reporting, and closure record graph. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_engagements', function (Blueprint $table): void {
            $table->id();
            $table->string('engagement_code', 60)->unique();
            $table->string('title');
            $table->string('source_type', 20)->index();
            $table->foreignId('iap_plan_engagement_id')
                ->nullable()
                ->constrained('iap_plan_engagements')
                ->restrictOnDelete();
            $table->json('source_snapshot')->nullable();
            $table->string('special_authority_reference', 100)->nullable();
            $table->string('special_authority_type_code', 60)->nullable();
            $table->date('special_authority_date')->nullable();
            $table->foreignId('special_authority_approved_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('special_authority_document_version_id')
                ->nullable()
                ->constrained('document_versions')
                ->restrictOnDelete();
            $table->foreignId('audit_type_id')
                ->nullable()
                ->constrained('master_list_items')
                ->restrictOnDelete();
            $table->foreignId('engagement_approach_id')
                ->nullable()
                ->constrained('master_list_items')
                ->restrictOnDelete();
            $table->text('background')->nullable();
            $table->text('objectives');
            $table->text('scope');
            $table->text('exclusions')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->date('expected_report_date')->nullable();
            $table->decimal('planned_person_days', 8, 2)->default(0);
            $table->decimal('actual_person_days', 8, 2)->default(0);
            $table->string('status', 50)->default('DRAFT')->index();
            $table->string('returned_from_status', 50)->nullable();
            $table->string('return_to_status', 50)->nullable();
            $table->string('suspended_from_status', 50)->nullable();
            $table->text('status_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['source_type', 'status'], 'aem_engagement_source_status_idx');
            $table->index(['planned_start_date', 'planned_end_date'], 'aem_engagement_plan_dates_idx');
        });

        DB::statement(
            "CREATE UNIQUE INDEX aem_active_iap_source_unique
             ON audit_engagements (iap_plan_engagement_id)
             WHERE iap_plan_engagement_id IS NOT NULL
               AND status <> 'CANCELLED'
               AND deleted_at IS NULL",
        );

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE audit_engagements
                 ADD CONSTRAINT aem_engagement_source_authority_check
                 CHECK (
                    (
                        source_type = 'PLANNED'
                        AND iap_plan_engagement_id IS NOT NULL
                    )
                    OR
                    (
                        source_type = 'SPECIAL'
                        AND iap_plan_engagement_id IS NULL
                        AND special_authority_reference IS NOT NULL
                        AND special_authority_date IS NOT NULL
                        AND special_authority_approved_by IS NOT NULL
                    )
                 )",
            );
        }

        Schema::create('audit_engagement_offices', function (Blueprint $table): void {
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->cascadeOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->primary(['audit_engagement_id', 'office_id'], 'aem_engagement_office_pk');
        });

        Schema::create('audit_engagement_audit_areas', function (Blueprint $table): void {
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->cascadeOnDelete();
            $table->foreignId('audit_area_id')->constrained('audit_areas')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['audit_engagement_id', 'audit_area_id'], 'aem_engagement_area_pk');
        });

        Schema::create('audit_engagement_audit_focuses', function (Blueprint $table): void {
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->cascadeOnDelete();
            $table->foreignId('audit_focus_id')->constrained('audit_focuses')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['audit_engagement_id', 'audit_focus_id'], 'aem_engagement_focus_pk');
        });

        Schema::create('engagement_teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('assignment_role_code', 40)->index();
            $table->decimal('planned_person_days', 8, 2)->default(0);
            $table->decimal('actual_person_days', 8, 2)->default(0);
            $table->date('assigned_from')->nullable();
            $table->date('assigned_until')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('assignment_notes')->nullable();
            $table->text('end_reason')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['audit_engagement_id', 'assignment_role_code'], 'aem_team_role_idx');
        });

        DB::statement(
            'CREATE UNIQUE INDEX aem_current_team_user_unique
             ON engagement_teams (audit_engagement_id, user_id)
             WHERE ended_at IS NULL AND deleted_at IS NULL',
        );

        Schema::create('engagement_team_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->cascadeOnDelete();
            $table->foreignId('engagement_team_id')->nullable()->constrained('engagement_teams')->nullOnDelete();
            $table->string('action', 40)->index();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['audit_engagement_id', 'created_at'], 'aem_team_history_date_idx');
        });

        Schema::create('audit_engagement_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->string('order_code', 80)->unique();
            $table->string('status', 40)->default('DRAFT')->index();
            $table->unsignedInteger('current_version_number')->default(0);
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'CREATE UNIQUE INDEX aem_one_active_order_per_engagement
             ON audit_engagement_orders (audit_engagement_id)
             WHERE is_active = true AND deleted_at IS NULL',
        );

        Schema::create('audit_engagement_order_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_order_id')
                ->constrained('audit_engagement_orders')
                ->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('authority');
            $table->text('objectives');
            $table->text('scope');
            $table->date('effectivity_date')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->json('team_snapshot')->nullable();
            $table->json('content_snapshot')->nullable();
            $table->foreignId('document_version_id')
                ->nullable()
                ->constrained('document_versions')
                ->restrictOnDelete();
            $table->string('checksum_sha256', 64)->nullable();
            $table->text('change_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(
                ['audit_engagement_order_id', 'version_number'],
                'aem_order_version_unique',
            );
        });

        Schema::create('audit_engagement_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->string('plan_code', 80)->unique();
            $table->string('status', 40)->default('DRAFT')->index();
            $table->unsignedInteger('current_version_number')->default(0);
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'CREATE UNIQUE INDEX aem_one_active_plan_per_engagement
             ON audit_engagement_plans (audit_engagement_id)
             WHERE is_active = true AND deleted_at IS NULL',
        );

        Schema::create('audit_engagement_plan_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_plan_id')
                ->constrained('audit_engagement_plans')
                ->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('objectives');
            $table->text('scope');
            $table->text('exclusions')->nullable();
            $table->text('methodology');
            $table->text('audit_criteria');
            $table->text('sampling_approach')->nullable();
            $table->date('planned_start_date');
            $table->date('planned_end_date');
            $table->date('expected_report_date')->nullable();
            $table->decimal('planned_person_days', 8, 2)->default(0);
            $table->json('resource_requirements')->nullable();
            $table->json('linked_risk_snapshot')->nullable();
            $table->foreignId('confidentiality_level_id')
                ->nullable()
                ->constrained('master_list_items')
                ->restrictOnDelete();
            $table->foreignId('document_version_id')
                ->nullable()
                ->constrained('document_versions')
                ->restrictOnDelete();
            $table->string('checksum_sha256', 64)->nullable();
            $table->text('change_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(
                ['audit_engagement_plan_id', 'version_number'],
                'aem_plan_version_unique',
            );
        });

        Schema::create('audit_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->foreignId('audit_engagement_plan_id')
                ->constrained('audit_engagement_plans')
                ->restrictOnDelete();
            $table->string('program_code', 80);
            $table->string('title');
            $table->text('objective');
            $table->string('status', 40)->default('DRAFT')->index();
            $table->unsignedInteger('revision_number')->default(0);
            $table->foreignId('supersedes_program_id')
                ->nullable()
                ->constrained('audit_programs')
                ->nullOnDelete();
            $table->boolean('is_current_revision')->default(true)->index();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(
                ['audit_engagement_id', 'program_code', 'revision_number'],
                'aem_program_revision_unique',
            );
        });

        DB::statement(
            'CREATE UNIQUE INDEX aem_current_program_revision_unique
             ON audit_programs (audit_engagement_id, program_code)
             WHERE is_current_revision = true AND deleted_at IS NULL',
        );

        Schema::create('audit_program_procedures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_program_id')->constrained('audit_programs')->cascadeOnDelete();
            $table->string('procedure_code', 80);
            $table->unsignedInteger('sequence_number')->default(0);
            $table->text('objective');
            $table->text('procedure_description');
            $table->text('expected_evidence')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('target_date')->nullable();
            $table->string('status', 30)->default('NOT_STARTED')->index();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('waived_at')->nullable();
            $table->foreignId('waived_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('waiver_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(
                ['audit_program_id', 'procedure_code'],
                'aem_program_procedure_unique',
            );
            $table->index(['assigned_to', 'target_date', 'status'], 'aem_procedure_assignment_idx');
        });

        Schema::create('working_papers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->foreignId('audit_program_procedure_id')
                ->nullable()
                ->constrained('audit_program_procedures')
                ->restrictOnDelete();
            $table->string('working_paper_code', 80);
            $table->string('title');
            $table->string('status', 40)->default('DRAFT')->index();
            $table->unsignedInteger('current_version_number')->default(0);
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(
                ['audit_engagement_id', 'working_paper_code'],
                'aem_working_paper_code_unique',
            );
        });

        Schema::create('working_paper_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('working_paper_id')->constrained('working_papers')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('objective');
            $table->text('procedure_performed');
            $table->text('population_description')->nullable();
            $table->text('sample_description')->nullable();
            $table->text('result');
            $table->text('conclusion');
            $table->json('cross_references')->nullable();
            $table->foreignId('document_version_id')
                ->nullable()
                ->constrained('document_versions')
                ->restrictOnDelete();
            $table->string('checksum_sha256', 64)->nullable();
            $table->text('change_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['working_paper_id', 'version_number'], 'aem_wp_version_unique');
        });

        Schema::create('audit_evidence', function (Blueprint $table): void {
            $table->id();
            $table->uuid('evidence_family_uuid');
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('supersedes_evidence_id')
                ->nullable()
                ->constrained('audit_evidence')
                ->nullOnDelete();
            $table->boolean('is_current_revision')->default(true)->index();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->string('evidence_code', 80);
            $table->string('title');
            $table->foreignId('evidence_category_id')
                ->nullable()
                ->constrained('master_list_items')
                ->restrictOnDelete();
            $table->text('source_description');
            $table->date('date_obtained');
            $table->string('custodian_name')->nullable();
            $table->foreignId('custodian_office_id')
                ->nullable()
                ->constrained('offices')
                ->restrictOnDelete();
            $table->foreignId('confidentiality_level_id')
                ->nullable()
                ->constrained('master_list_items')
                ->restrictOnDelete();
            $table->foreignId('document_version_id')
                ->constrained('document_versions')
                ->restrictOnDelete();
            $table->string('checksum_sha256', 64);
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(
                ['audit_engagement_id', 'evidence_code', 'version_number'],
                'aem_evidence_version_unique',
            );
        });

        DB::statement(
            'CREATE UNIQUE INDEX aem_current_evidence_unique
             ON audit_evidence (audit_engagement_id, evidence_code)
             WHERE is_current_revision = true AND deleted_at IS NULL',
        );

        Schema::create('working_paper_evidence', function (Blueprint $table): void {
            $table->foreignId('working_paper_id')->constrained('working_papers')->cascadeOnDelete();
            $table->foreignId('audit_evidence_id')->constrained('audit_evidence')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['working_paper_id', 'audit_evidence_id'], 'aem_wp_evidence_pk');
        });

        Schema::create('audit_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->string('issue_code', 80);
            $table->string('title');
            $table->text('exception_description');
            $table->foreignId('responsible_office_id')->constrained('offices')->restrictOnDelete();
            $table->foreignId('risk_rating_id')
                ->nullable()
                ->constrained('master_list_items')
                ->restrictOnDelete();
            $table->string('status', 40)->default('DRAFT')->index();
            $table->foreignId('raised_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('dismissed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('dismissal_reason')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['audit_engagement_id', 'issue_code'], 'aem_issue_code_unique');
        });

        Schema::create('audit_issue_working_paper', function (Blueprint $table): void {
            $table->foreignId('audit_issue_id')->constrained('audit_issues')->cascadeOnDelete();
            $table->foreignId('working_paper_version_id')
                ->constrained('working_paper_versions')
                ->restrictOnDelete();
            $table->timestamps();
            $table->primary(
                ['audit_issue_id', 'working_paper_version_id'],
                'aem_issue_wp_pk',
            );
        });

        Schema::create('audit_issue_evidence', function (Blueprint $table): void {
            $table->foreignId('audit_issue_id')->constrained('audit_issues')->cascadeOnDelete();
            $table->foreignId('audit_evidence_id')->constrained('audit_evidence')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['audit_issue_id', 'audit_evidence_id'], 'aem_issue_evidence_pk');
        });

        Schema::create('audit_findings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('finding_family_uuid');
            $table->unsignedInteger('revision_number')->default(0);
            $table->foreignId('supersedes_finding_id')
                ->nullable()
                ->constrained('audit_findings')
                ->nullOnDelete();
            $table->boolean('is_current_revision')->default(true)->index();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->foreignId('source_issue_id')
                ->nullable()
                ->constrained('audit_issues')
                ->restrictOnDelete();
            $table->string('finding_code', 80);
            $table->string('title');
            $table->text('criteria');
            $table->text('condition');
            $table->text('cause');
            $table->text('effect');
            $table->foreignId('risk_rating_id')
                ->nullable()
                ->constrained('master_list_items')
                ->restrictOnDelete();
            $table->foreignId('responsible_office_id')->constrained('offices')->restrictOnDelete();
            $table->string('status', 50)->default('DRAFT')->index();
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('communicated_at')->nullable();
            $table->foreignId('communicated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('management_response_due_date')->nullable();
            $table->json('communicated_snapshot')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(
                ['audit_engagement_id', 'finding_code', 'revision_number'],
                'aem_finding_revision_unique',
            );
            $table->unique(['source_issue_id'], 'aem_issue_one_finding_unique');
        });

        DB::statement(
            'CREATE UNIQUE INDEX aem_current_finding_unique
             ON audit_findings (audit_engagement_id, finding_code)
             WHERE is_current_revision = true AND deleted_at IS NULL',
        );

        Schema::create('audit_finding_working_paper', function (Blueprint $table): void {
            $table->foreignId('audit_finding_id')->constrained('audit_findings')->cascadeOnDelete();
            $table->foreignId('working_paper_version_id')
                ->constrained('working_paper_versions')
                ->restrictOnDelete();
            $table->timestamps();
            $table->primary(
                ['audit_finding_id', 'working_paper_version_id'],
                'aem_finding_wp_pk',
            );
        });

        Schema::create('audit_finding_evidence', function (Blueprint $table): void {
            $table->foreignId('audit_finding_id')->constrained('audit_findings')->cascadeOnDelete();
            $table->foreignId('audit_evidence_id')->constrained('audit_evidence')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['audit_finding_id', 'audit_evidence_id'], 'aem_finding_evidence_pk');
        });

        Schema::create('audit_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_finding_id')->constrained('audit_findings')->restrictOnDelete();
            $table->string('recommendation_code', 80)->unique();
            $table->text('recommendation');
            $table->foreignId('responsible_office_id')->constrained('offices')->restrictOnDelete();
            $table->date('target_implementation_date')->nullable();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('cms_transfer_key')->nullable()->unique();
            $table->unsignedBigInteger('cms_recommendation_id')->nullable()->unique();
            $table->timestamp('transferred_to_cms_at')->nullable();
            $table->foreignId('transferred_to_cms_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['audit_finding_id', 'status'], 'aem_recommendation_status_idx');
        });

        Schema::create('management_responses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('response_family_uuid');
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('supersedes_response_id')
                ->nullable()
                ->constrained('management_responses')
                ->nullOnDelete();
            $table->boolean('is_current_revision')->default(true)->index();
            $table->foreignId('audit_finding_id')->constrained('audit_findings')->restrictOnDelete();
            $table->string('response_code', 80);
            $table->string('agreement_position', 30);
            $table->text('management_comment');
            $table->text('proposed_action')->nullable();
            $table->foreignId('responsible_office_id')->constrained('offices')->restrictOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('proposed_target_date')->nullable();
            $table->string('status', 40)->default('DRAFT')->index();
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('clarification_requested_at')->nullable();
            $table->foreignId('clarification_requested_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('clarification_request')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(
                ['audit_finding_id', 'response_code', 'version_number'],
                'aem_response_version_unique',
            );
        });

        DB::statement(
            'CREATE UNIQUE INDEX aem_current_response_unique
             ON management_responses (audit_finding_id, response_code)
             WHERE is_current_revision = true AND deleted_at IS NULL',
        );

        Schema::create('auditor_rejoinders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('management_response_id')
                ->constrained('management_responses')
                ->restrictOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('disposition', 30);
            $table->text('rejoinder');
            $table->string('status', 40)->default('DRAFT')->index();
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(
                ['management_response_id', 'version_number'],
                'aem_rejoinder_version_unique',
            );
        });

        Schema::create('exit_conferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->string('conference_code', 80)->unique();
            $table->timestamp('scheduled_start_at');
            $table->timestamp('scheduled_end_at')->nullable();
            $table->string('venue')->nullable();
            $table->text('meeting_link')->nullable();
            $table->text('agenda');
            $table->text('discussion_summary')->nullable();
            $table->text('agreements')->nullable();
            $table->foreignId('minutes_document_version_id')
                ->nullable()
                ->constrained('document_versions')
                ->restrictOnDelete();
            $table->string('status', 30)->default('SCHEDULED')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exit_conference_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exit_conference_id')->constrained('exit_conferences')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->string('external_name')->nullable();
            $table->string('external_email')->nullable();
            $table->string('participant_role', 60);
            $table->string('attendance_status', 30)->default('INVITED');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->index(
                ['exit_conference_id', 'attendance_status'],
                'aem_exit_participant_status_idx',
            );
        });

        Schema::create('audit_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->string('report_code', 80)->unique();
            $table->string('title');
            $table->string('report_stage', 30)->default('DRAFT_REPORT')->index();
            $table->string('status', 40)->default('DRAFT')->index();
            $table->unsignedInteger('current_version_number')->default(0);
            $table->foreignId('confidentiality_level_id')
                ->nullable()
                ->constrained('master_list_items')
                ->restrictOnDelete();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('approving_authority')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'CREATE UNIQUE INDEX aem_one_active_report_per_engagement
             ON audit_reports (audit_engagement_id)
             WHERE is_active = true AND deleted_at IS NULL',
        );

        Schema::create('audit_report_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_report_id')->constrained('audit_reports')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('report_stage', 30);
            $table->json('content_snapshot');
            $table->foreignId('document_version_id')
                ->nullable()
                ->constrained('document_versions')
                ->restrictOnDelete();
            $table->string('checksum_sha256', 64)->nullable();
            $table->text('change_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['audit_report_id', 'version_number'], 'aem_report_version_unique');
        });

        Schema::create('audit_report_findings', function (Blueprint $table): void {
            $table->foreignId('audit_report_version_id')
                ->constrained('audit_report_versions')
                ->cascadeOnDelete();
            $table->foreignId('audit_finding_id')->constrained('audit_findings')->restrictOnDelete();
            $table->unsignedInteger('sequence_number')->default(0);
            $table->boolean('is_included')->default(true);
            $table->timestamps();
            $table->primary(
                ['audit_report_version_id', 'audit_finding_id'],
                'aem_report_finding_pk',
            );
        });

        Schema::create('report_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_report_version_id')
                ->constrained('audit_report_versions')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->string('external_name')->nullable();
            $table->string('external_email')->nullable();
            $table->string('recipient_type', 60);
            $table->string('delivery_method', 30)->nullable();
            $table->string('delivery_status', 30)->default('PENDING')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->index(
                ['audit_report_version_id', 'delivery_status'],
                'aem_report_recipient_status_idx',
            );
        });

        Schema::create('engagement_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->cascadeOnDelete();
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->uuid('subject_family_uuid')->nullable();
            $table->unsignedInteger('subject_version')->nullable();
            $table->string('subject_code', 100)->nullable();
            $table->string('action', 60)->index();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_role_code', 60)->nullable();
            $table->string('actor_assignment_code', 60)->nullable();
            $table->foreignId('office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->string('reason_category_code', 60)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->unsignedInteger('record_lock_version')->nullable();
            $table->json('document_version_ids')->nullable();
            $table->json('notification_ids')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['audit_engagement_id', 'created_at'], 'aem_event_engagement_date_idx');
            $table->index(['subject_type', 'subject_id'], 'aem_event_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_events');
        Schema::dropIfExists('report_recipients');
        Schema::dropIfExists('audit_report_findings');
        Schema::dropIfExists('audit_report_versions');
        Schema::dropIfExists('audit_reports');
        Schema::dropIfExists('exit_conference_participants');
        Schema::dropIfExists('exit_conferences');
        Schema::dropIfExists('auditor_rejoinders');
        Schema::dropIfExists('management_responses');
        Schema::dropIfExists('audit_recommendations');
        Schema::dropIfExists('audit_finding_evidence');
        Schema::dropIfExists('audit_finding_working_paper');
        Schema::dropIfExists('audit_findings');
        Schema::dropIfExists('audit_issue_evidence');
        Schema::dropIfExists('audit_issue_working_paper');
        Schema::dropIfExists('audit_issues');
        Schema::dropIfExists('working_paper_evidence');
        Schema::dropIfExists('audit_evidence');
        Schema::dropIfExists('working_paper_versions');
        Schema::dropIfExists('working_papers');
        Schema::dropIfExists('audit_program_procedures');
        Schema::dropIfExists('audit_programs');
        Schema::dropIfExists('audit_engagement_plan_versions');
        Schema::dropIfExists('audit_engagement_plans');
        Schema::dropIfExists('audit_engagement_order_versions');
        Schema::dropIfExists('audit_engagement_orders');
        Schema::dropIfExists('engagement_team_history');
        Schema::dropIfExists('engagement_teams');
        Schema::dropIfExists('audit_engagement_audit_focuses');
        Schema::dropIfExists('audit_engagement_audit_areas');
        Schema::dropIfExists('audit_engagement_offices');
        Schema::dropIfExists('audit_engagements');
    }
};
