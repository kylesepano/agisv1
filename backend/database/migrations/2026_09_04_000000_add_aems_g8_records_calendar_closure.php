<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagement_retention_records', function (Blueprint $table): void {
            $table->string('archive_status', 40)->default('ACTIVE')->index();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('archive_reason')->nullable();
            $table->timestamp('legal_hold_released_at')->nullable();
            $table->foreignId('legal_hold_released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('legal_hold_release_reason')->nullable();
            $table->string('destruction_eligibility_status', 40)->default('NOT_REVIEWED')->index();
            $table->timestamp('destruction_reviewed_at')->nullable();
            $table->foreignId('destruction_reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('destruction_review_reason')->nullable();
            $table->timestamp('disposition_recorded_at')->nullable();
            $table->foreignId('disposition_recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('disposition_reference', 160)->nullable();
        });

        Schema::create('aems_record_disposition_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->foreignId('engagement_retention_record_id')->constrained('engagement_retention_records')->restrictOnDelete();
            $table->string('action_code', 60);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('reason');
            $table->string('reference_code', 160)->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->jsonb('snapshot_json')->nullable();
            $table->timestamps();
            $table->index(['audit_engagement_id', 'occurred_at'], 'aems_record_action_engagement_idx');
        });

        Schema::create('aems_engagement_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->string('milestone_code', 100);
            $table->string('category_code', 60)->default('GENERAL');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->string('status_code', 30)->default('OPEN');
            $table->boolean('required_flag')->default(true);
            $table->foreignId('responsible_office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('related_record_type', 120)->nullable();
            $table->unsignedBigInteger('related_record_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['audit_engagement_id', 'milestone_code'], 'aems_milestone_engagement_code_unique');
            $table->index(['audit_engagement_id', 'due_date', 'status_code'], 'aems_milestone_calendar_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aems_engagement_milestones');
        Schema::dropIfExists('aems_record_disposition_actions');
        Schema::table('engagement_retention_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropConstrainedForeignId('legal_hold_released_by');
            $table->dropConstrainedForeignId('destruction_reviewed_by');
            $table->dropConstrainedForeignId('disposition_recorded_by');
            $table->dropColumn([
                'archive_status', 'archived_at', 'archive_reason',
                'legal_hold_released_at', 'legal_hold_release_reason',
                'destruction_eligibility_status', 'destruction_reviewed_at',
                'destruction_review_reason', 'disposition_recorded_at',
                'disposition_reference',
            ]);
        });
    }
};
