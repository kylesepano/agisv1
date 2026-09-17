<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores AEMS team declarations and the immutable provider/readiness decisions
 * used to approve a team for controlled fieldwork.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aems_team_safeguard_declarations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('declaration_family_uuid');
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->foreignId('engagement_team_id')->constrained('engagement_teams')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('declaration_type', 40);
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('supersedes_id')->nullable()
                ->constrained('aems_team_safeguard_declarations')->nullOnDelete();
            $table->boolean('is_current_revision')->default(true);
            $table->string('outcome', 30)->default('CLEAR');
            $table->text('statement');
            $table->text('mitigation_plan')->nullable();
            $table->foreignId('evidence_document_version_id')->nullable()
                ->constrained('document_versions')->nullOnDelete();
            $table->string('status', 30)->default('SUBMITTED')->index();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->useCurrent();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(
                ['declaration_family_uuid', 'version_number'],
                'aems_safeguard_declaration_family_version_unique',
            );
            $table->index(
                ['audit_engagement_id', 'engagement_team_id', 'declaration_type', 'is_current_revision'],
                'aems_safeguard_declaration_current_idx',
            );
        });

        Schema::create('aems_team_safeguard_assessments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('assessment_uuid')->unique();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->boolean('is_current_revision')->default(true);
            $table->string('status', 30)->default('PENDING')->index();
            $table->string('provider_mode', 40);
            $table->jsonb('provider_status');
            $table->jsonb('reconciliation');
            $table->jsonb('checks');
            $table->jsonb('blockers');
            $table->jsonb('warnings')->nullable();
            $table->foreignId('supersedes_id')->nullable()
                ->constrained('aems_team_safeguard_assessments')->nullOnDelete();
            $table->foreignId('assessed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assessed_at')->useCurrent();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('decision_comment')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(
                ['audit_engagement_id', 'version_number'],
                'aems_safeguard_assessment_engagement_version_unique',
            );
            $table->index(
                ['audit_engagement_id', 'is_current_revision', 'status'],
                'aems_safeguard_assessment_current_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aems_team_safeguard_assessments');
        Schema::dropIfExists('aems_team_safeguard_declarations');
    }
};
