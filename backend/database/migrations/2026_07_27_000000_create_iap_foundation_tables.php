<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Creates Annual Plan, engagement, workflow-history, comment, and attachment records. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('owner_module', 50)->nullable()->index()->after('description');
            $table->boolean('library_visible')->default(true)->index()->after('owner_module');
        });

        Schema::create('internal_audit_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('plan_code', 50)->unique();
            $table->unsignedSmallInteger('fiscal_year')->index();
            $table->foreignId('planning_period_type_id')->constrained('master_list_items')->restrictOnDelete();
            $table->date('planning_period_start');
            $table->date('planning_period_end');
            $table->string('title');
            $table->text('executive_summary')->nullable();
            $table->text('planning_methodology')->nullable();
            $table->text('overall_objective');
            $table->text('overall_scope');
            $table->text('limitations')->nullable();
            $table->string('status', 40)->default('DRAFT')->index();
            $table->unsignedSmallInteger('revision_number')->default(0);
            $table->foreignId('supersedes_plan_id')->nullable()->constrained('internal_audit_plans')->nullOnDelete();
            $table->boolean('is_current_revision')->default(true)->index();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['fiscal_year', 'revision_number'], 'iap_year_revision_unique');
            $table->index(['status', 'fiscal_year'], 'iap_status_year_index');
        });

        DB::statement(
            'CREATE UNIQUE INDEX iap_one_current_revision_per_year '.
            'ON internal_audit_plans (fiscal_year) '.
            'WHERE is_current_revision = true AND deleted_at IS NULL',
        );

        Schema::create('iap_risk_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('internal_audit_plans')->cascadeOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->foreignId('audit_area_id')->constrained('audit_areas')->restrictOnDelete();
            $table->foreignId('assessed_by')->constrained('users')->restrictOnDelete();
            $table->date('assessment_date');
            $table->date('last_audit_date')->nullable();
            $table->text('inherent_risk_notes')->nullable();
            $table->text('control_environment_notes')->nullable();
            $table->decimal('total_weighted_score', 5, 2)->default(0);
            $table->foreignId('calculated_risk_level_id')->nullable()->constrained('master_list_items')->restrictOnDelete();
            $table->foreignId('override_risk_level_id')->nullable()->constrained('master_list_items')->restrictOnDelete();
            $table->text('override_reason')->nullable();
            $table->foreignId('final_risk_level_id')->nullable()->constrained('master_list_items')->restrictOnDelete();
            $table->text('justification');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['plan_id', 'office_id', 'audit_area_id'],
                'iap_risk_plan_office_area_unique',
            );
            $table->index(['plan_id', 'final_risk_level_id'], 'iap_risk_plan_level_index');
        });

        Schema::create('iap_risk_assessment_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('risk_assessment_id')->constrained('iap_risk_assessments')->cascadeOnDelete();
            $table->foreignId('risk_criterion_id')->constrained('master_list_items')->restrictOnDelete();
            $table->decimal('criterion_weight', 5, 2);
            $table->decimal('rating', 3, 2);
            $table->decimal('weighted_score', 7, 4);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(
                ['risk_assessment_id', 'risk_criterion_id'],
                'iap_risk_score_criterion_unique',
            );
        });

        Schema::create('iap_plan_engagements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('internal_audit_plans')->cascadeOnDelete();
            $table->string('engagement_code', 60);
            $table->string('title');
            $table->foreignId('engagement_type_id')->constrained('master_list_items')->restrictOnDelete();
            $table->foreignId('audit_approach_id')->nullable()->constrained('master_list_items')->restrictOnDelete();
            $table->foreignId('priority_id')->constrained('master_list_items')->restrictOnDelete();
            $table->foreignId('risk_level_id')->constrained('master_list_items')->restrictOnDelete();
            $table->foreignId('risk_assessment_id')->nullable()->constrained('iap_risk_assessments')->nullOnDelete();
            $table->text('background')->nullable();
            $table->text('objectives');
            $table->text('scope');
            $table->text('exclusions')->nullable();
            $table->text('audit_criteria')->nullable();
            $table->text('proposed_methodology')->nullable();
            $table->date('planned_start_date');
            $table->date('planned_end_date');
            $table->decimal('estimated_person_days', 8, 2);
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->unsignedSmallInteger('sequence_number')->default(0);
            $table->text('planning_notes')->nullable();
            $table->unsignedBigInteger('aem_engagement_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['plan_id', 'engagement_code'], 'iap_plan_engagement_code_unique');
            $table->index(['plan_id', 'planned_start_date', 'planned_end_date'], 'iap_engagement_schedule_index');
        });

        Schema::create('iap_engagement_offices', function (Blueprint $table): void {
            $table->foreignId('plan_engagement_id')->constrained('iap_plan_engagements')->cascadeOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->timestamps();

            $table->primary(['plan_engagement_id', 'office_id']);
        });

        Schema::create('iap_engagement_audit_areas', function (Blueprint $table): void {
            $table->foreignId('plan_engagement_id')->constrained('iap_plan_engagements')->cascadeOnDelete();
            $table->foreignId('audit_area_id')->constrained('audit_areas')->restrictOnDelete();
            $table->timestamps();

            $table->primary(['plan_engagement_id', 'audit_area_id']);
        });

        Schema::create('iap_engagement_audit_focuses', function (Blueprint $table): void {
            $table->foreignId('plan_engagement_id')->constrained('iap_plan_engagements')->cascadeOnDelete();
            $table->foreignId('audit_focus_id')->constrained('audit_focuses')->restrictOnDelete();
            $table->timestamps();

            $table->primary(['plan_engagement_id', 'audit_focus_id']);
        });

        Schema::create('iap_engagement_team_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_engagement_id')->constrained('iap_plan_engagements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('team_role_id')->constrained('master_list_items')->restrictOnDelete();
            $table->decimal('planned_person_days', 8, 2);
            $table->text('assignment_notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['plan_engagement_id', 'user_id'],
                'iap_engagement_team_user_unique',
            );
        });

        Schema::create('iap_workflow_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('internal_audit_plans')->cascadeOnDelete();
            $table->string('action', 50)->index();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->index();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_role_code', 50);
            $table->text('comment')->nullable();
            $table->unsignedInteger('plan_lock_version');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['plan_id', 'created_at'], 'iap_workflow_plan_date_index');
        });

        Schema::create('iap_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('internal_audit_plans')->cascadeOnDelete();
            $table->foreignId('plan_engagement_id')->nullable()->constrained('iap_plan_engagements')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('comment_type_id')->constrained('master_list_items')->restrictOnDelete();
            $table->foreignId('parent_comment_id')->nullable()->constrained('iap_comments')->nullOnDelete();
            $table->string('visibility', 20)->default('INTERNAL')->index();
            $table->text('body');
            $table->boolean('is_immutable')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['plan_id', 'plan_engagement_id'], 'iap_comment_subject_index');
        });

        Schema::create('iap_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('internal_audit_plans')->cascadeOnDelete();
            $table->foreignId('plan_engagement_id')->nullable()->constrained('iap_plan_engagements')->cascadeOnDelete();
            $table->foreignId('risk_assessment_id')->nullable()->constrained('iap_risk_assessments')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignId('attachment_type_id')->constrained('master_list_items')->restrictOnDelete();
            $table->string('display_name');
            $table->string('visibility', 20)->default('INTERNAL')->index();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['plan_id', 'plan_engagement_id'], 'iap_attachment_subject_index');
            $table->unique(['plan_id', 'document_id'], 'iap_plan_document_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iap_attachments');
        Schema::dropIfExists('iap_comments');
        Schema::dropIfExists('iap_workflow_events');
        Schema::dropIfExists('iap_engagement_team_members');
        Schema::dropIfExists('iap_engagement_audit_focuses');
        Schema::dropIfExists('iap_engagement_audit_areas');
        Schema::dropIfExists('iap_engagement_offices');
        Schema::dropIfExists('iap_plan_engagements');
        Schema::dropIfExists('iap_risk_assessment_scores');
        Schema::dropIfExists('iap_risk_assessments');
        Schema::dropIfExists('internal_audit_plans');

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn(['owner_module', 'library_visible']);
        });
    }
};
