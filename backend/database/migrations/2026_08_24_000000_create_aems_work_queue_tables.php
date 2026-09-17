<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the AEMS-7A work-queue and due-process aggregates without changing
 * the existing conference, finding, or dialogue tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aems_engagement_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->string('task_code', 100)->unique();
            $table->string('task_type', 50);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('audit_finding_id')->nullable()->constrained('audit_findings')->restrictOnDelete();
            $table->foreignId('entry_conference_id')->nullable()->constrained('entry_conferences')->restrictOnDelete();
            $table->foreignId('exit_conference_id')->nullable()->constrained('exit_conferences')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->string('status', 30)->default('OPEN')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('completion_comment')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['audit_engagement_id', 'status', 'due_at'], 'aems_task_queue_idx');
        });

        Schema::create('aems_engagement_task_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aems_engagement_task_id')->constrained('aems_engagement_tasks')->restrictOnDelete();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('action', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('content')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
            $table->json('snapshot')->nullable();
            $table->unique(['aems_engagement_task_id', 'version_number'], 'aems_task_event_version_unique');
            $table->index(['audit_engagement_id', 'recorded_at'], 'aems_task_event_engagement_idx');
        });

        Schema::create('aems_review_notes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('note_family_uuid');
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('supersedes_note_id')->nullable()->constrained('aems_review_notes')->restrictOnDelete();
            $table->boolean('is_current_revision')->default(true)->index();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->foreignId('audit_finding_id')->nullable()->constrained('audit_findings')->restrictOnDelete();
            $table->foreignId('entry_conference_id')->nullable()->constrained('entry_conferences')->restrictOnDelete();
            $table->foreignId('exit_conference_id')->nullable()->constrained('exit_conferences')->restrictOnDelete();
            $table->foreignId('aems_engagement_task_id')->nullable()->constrained('aems_engagement_tasks')->restrictOnDelete();
            $table->string('note_code', 120);
            $table->string('note_type', 50)->default('REVIEW');
            $table->text('content');
            $table->string('status', 30)->default('DRAFT')->index();
            $table->text('revision_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['note_code', 'version_number'], 'aems_review_note_version_unique');
            $table->index(['audit_engagement_id', 'status'], 'aems_review_note_engagement_status_idx');
        });

        Schema::create('aems_review_note_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aems_review_note_id')->constrained('aems_review_notes')->restrictOnDelete();
            $table->string('attachment_code', 120)->unique();
            $table->string('caption')->nullable();
            $table->foreignId('document_version_id')->constrained('document_versions')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['aems_review_note_id', 'document_version_id'], 'aems_review_note_document_unique');
        });

        Schema::create('aems_dialogue_due_process', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->foreignId('audit_finding_id')->constrained('audit_findings')->restrictOnDelete();
            $table->foreignId('management_response_id')->nullable()->constrained('management_responses')->restrictOnDelete();
            $table->string('event_code', 120)->unique();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('event_type', 50);
            $table->text('content');
            $table->date('due_date')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->unique(['audit_finding_id', 'version_number', 'event_type'], 'aems_due_process_version_unique');
            $table->index(['audit_engagement_id', 'audit_finding_id', 'recorded_at'], 'aems_due_process_finding_idx');
        });

        Schema::create('aems_due_process_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aems_dialogue_due_process_id')->constrained('aems_dialogue_due_process')->restrictOnDelete();
            $table->string('attachment_code', 120)->unique();
            $table->string('caption')->nullable();
            $table->foreignId('document_version_id')->constrained('document_versions')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['aems_dialogue_due_process_id', 'document_version_id'], 'aems_due_process_document_unique');
        });

        Schema::create('aems_escalation_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->string('candidate_code', 120)->unique();
            $table->string('detection_key', 190)->unique();
            $table->string('candidate_type', 50);
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('audit_finding_id')->nullable()->constrained('audit_findings')->restrictOnDelete();
            $table->foreignId('aems_engagement_task_id')->nullable()->constrained('aems_engagement_tasks')->restrictOnDelete();
            $table->foreignId('entry_conference_id')->nullable()->constrained('entry_conferences')->restrictOnDelete();
            $table->foreignId('exit_conference_id')->nullable()->constrained('exit_conferences')->restrictOnDelete();
            $table->string('status', 30)->default('OPEN')->index();
            $table->text('reason');
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamp('due_at')->nullable();
            $table->json('trigger_snapshot')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['audit_engagement_id', 'status', 'detected_at'], 'aems_escalation_candidate_queue_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE aems_engagement_tasks ADD CONSTRAINT aems_task_status_check CHECK (status IN ('OPEN','IN_PROGRESS','COMPLETED','CANCELLED'))");
            DB::statement("ALTER TABLE aems_review_notes ADD CONSTRAINT aems_review_note_status_check CHECK (status IN ('DRAFT','FINALIZED','VOIDED'))");
            DB::statement("ALTER TABLE aems_escalation_candidates ADD CONSTRAINT aems_escalation_candidate_status_check CHECK (status IN ('OPEN','ACKNOWLEDGED','RESOLVED','DISMISSED'))");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE aems_escalation_candidates DROP CONSTRAINT IF EXISTS aems_escalation_candidate_status_check');
            DB::statement('ALTER TABLE aems_review_notes DROP CONSTRAINT IF EXISTS aems_review_note_status_check');
            DB::statement('ALTER TABLE aems_engagement_tasks DROP CONSTRAINT IF EXISTS aems_task_status_check');
        }
        Schema::dropIfExists('aems_escalation_candidates');
        Schema::dropIfExists('aems_due_process_attachments');
        Schema::dropIfExists('aems_dialogue_due_process');
        Schema::dropIfExists('aems_review_note_attachments');
        Schema::dropIfExists('aems_review_notes');
        Schema::dropIfExists('aems_engagement_task_events');
        Schema::dropIfExists('aems_engagement_tasks');
    }
};
