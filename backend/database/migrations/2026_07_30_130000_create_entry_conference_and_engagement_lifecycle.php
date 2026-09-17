<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_engagements', function (Blueprint $table): void {
            $table->json('suspension_metadata')->nullable()->after('status_reason');
            $table->json('cancellation_metadata')->nullable()->after('suspension_metadata');
            $table->foreignId('transitioned_by')->nullable()->after('cancellation_metadata')
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('transitioned_at')->nullable()->after('transitioned_by');
        });

        Schema::create('entry_conferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->unique()
                ->constrained('audit_engagements')->restrictOnDelete();
            $table->string('conference_code', 100)->unique();
            $table->string('status', 40)->default('DRAFT')->index();
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->timestamp('held_at')->nullable();
            $table->string('venue')->nullable();
            $table->string('meeting_link', 1000)->nullable();
            $table->text('online_meeting_details')->nullable();
            $table->text('agenda')->nullable();
            $table->json('briefing_paper')->nullable();
            $table->text('auditee_views')->nullable();
            $table->text('auditee_expectations')->nullable();
            $table->text('conference_notes')->nullable();
            $table->text('material_matters_disposition')->nullable();
            $table->timestamp('notes_circulated_at')->nullable();
            $table->foreignId('notes_circulated_by')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->text('reschedule_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->text('waiver_reason')->nullable();
            $table->string('waiver_authority', 255)->nullable();
            $table->timestamp('waived_at')->nullable();
            $table->foreignId('waived_by')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('entry_conference_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_conference_id')
                ->constrained('entry_conferences')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->string('participant_type', 30)->index();
            $table->string('participant_role', 100)->nullable();
            $table->string('external_name')->nullable();
            $table->string('external_email')->nullable();
            $table->string('attendance_status', 30)->default('INVITED');
            $table->timestamp('attended_at')->nullable();
            $table->text('attendance_notes')->nullable();
            $table->timestamps();
            $table->index(['entry_conference_id', 'participant_type'], 'entry_conference_participant_type_idx');
        });

        Schema::create('entry_conference_matters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_conference_id')
                ->constrained('entry_conferences')->cascadeOnDelete();
            $table->string('matter_type', 50)->default('GENERAL');
            $table->text('description');
            $table->boolean('is_material')->default(false);
            $table->string('disposition_status', 30)->default('OPEN');
            $table->text('disposition')->nullable();
            $table->foreignId('responsible_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->foreignId('responsible_office_id')->nullable()
                ->constrained('offices')->restrictOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        Schema::create('entry_conference_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_conference_id')
                ->constrained('entry_conferences')->cascadeOnDelete();
            $table->text('agreement');
            $table->foreignId('responsible_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->foreignId('responsible_office_id')->nullable()
                ->constrained('offices')->restrictOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('OPEN');
            $table->timestamps();
        });

        Schema::create('entry_conference_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_conference_id')
                ->constrained('entry_conferences')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->unsignedInteger('conference_version');
            $table->string('acknowledgement_status', 40);
            $table->text('reservation')->nullable();
            $table->timestamp('acknowledged_at');
            $table->timestamps();
            $table->unique(
                ['entry_conference_id', 'user_id', 'conference_version'],
                'entry_conference_ack_user_version_unique',
            );
        });

        Schema::create('entry_conference_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_conference_id')
                ->constrained('entry_conferences')->restrictOnDelete();
            $table->string('attachment_code', 120)->unique();
            $table->string('category', 40)->index();
            $table->string('caption')->nullable();
            $table->foreignId('document_version_id')
                ->constrained('document_versions')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['entry_conference_id', 'document_version_id'],
                'entry_conference_document_version_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_conference_attachments');
        Schema::dropIfExists('entry_conference_acknowledgements');
        Schema::dropIfExists('entry_conference_agreements');
        Schema::dropIfExists('entry_conference_matters');
        Schema::dropIfExists('entry_conference_participants');
        Schema::dropIfExists('entry_conferences');

        Schema::table('audit_engagements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('transitioned_by');
            $table->dropColumn([
                'suspension_metadata',
                'cancellation_metadata',
                'transitioned_at',
            ]);
        });
    }
};
