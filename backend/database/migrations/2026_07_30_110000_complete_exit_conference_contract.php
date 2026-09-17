<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exit_conferences', function (Blueprint $table): void {
            $table->text('online_meeting_details')->nullable()->after('meeting_link');
            $table->text('minutes')->nullable()->after('discussion_summary');
            $table->text('disagreements')->nullable()->after('agreements');
            $table->jsonb('completion_snapshot')->nullable()->after('disagreements');
            $table->text('waiver_reason')->nullable()->after('status');
            $table->text('cancellation_reason')->nullable()->after('waiver_reason');
            $table->foreignId('updated_by')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->restrictOnDelete();
        });

        Schema::table('exit_conference_participants', function (Blueprint $table): void {
            $table->text('attendance_notes')->nullable()->after('attendance_status');
            $table->timestamp('attendance_recorded_at')->nullable()->after('attendance_notes');
            $table->foreignId('attendance_recorded_by')
                ->nullable()
                ->after('attendance_recorded_at')
                ->constrained('users')
                ->restrictOnDelete();
        });

        Schema::create('exit_conference_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exit_conference_id')
                ->constrained('exit_conferences')
                ->cascadeOnDelete();
            $table->foreignId('audit_finding_id')
                ->constrained('audit_findings')
                ->restrictOnDelete();
            $table->unsignedInteger('sequence_number')->default(1);
            $table->string('discussion_status', 30)->default('PENDING');
            $table->string('agreement_status', 30)->nullable();
            $table->text('discussion_notes')->nullable();
            $table->text('agreement_details')->nullable();
            $table->text('disagreement_details')->nullable();
            $table->date('revised_target_date')->nullable();
            $table->timestamps();
            $table->unique(
                ['exit_conference_id', 'audit_finding_id'],
                'aem_exit_conference_finding_unique',
            );
            $table->index(
                ['exit_conference_id', 'sequence_number'],
                'aem_exit_conference_finding_sequence_idx',
            );
        });

        Schema::create('exit_conference_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exit_conference_id')
                ->constrained('exit_conferences')
                ->restrictOnDelete();
            $table->string('attachment_code', 120)->unique();
            $table->string('category', 30)->default('SUPPORTING');
            $table->string('caption')->nullable();
            $table->foreignId('document_version_id')
                ->constrained('document_versions')
                ->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['exit_conference_id', 'document_version_id'],
                'aem_exit_conference_document_unique',
            );
        });

        Schema::create('exit_conference_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exit_conference_id')
                ->constrained('exit_conferences')
                ->restrictOnDelete();
            $table->foreignId('exit_conference_participant_id')
                ->constrained('exit_conference_participants')
                ->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('acknowledgement_status', 30)->default('ACKNOWLEDGED');
            $table->text('comment')->nullable();
            $table->timestamp('acknowledged_at');
            $table->timestamps();
            $table->unique(
                ['exit_conference_id', 'user_id'],
                'aem_exit_conference_user_ack_unique',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE exit_conference_findings
                 ADD CONSTRAINT aem_exit_finding_discussion_status_check
                 CHECK (discussion_status IN ('PENDING', 'DISCUSSED', 'NOT_DISCUSSED'))",
            );
            DB::statement(
                "ALTER TABLE exit_conference_findings
                 ADD CONSTRAINT aem_exit_finding_agreement_status_check
                 CHECK (agreement_status IS NULL OR agreement_status IN ('AGREED', 'PARTIALLY_AGREED', 'DISAGREED'))",
            );
            DB::statement(
                "ALTER TABLE exit_conference_attachments
                 ADD CONSTRAINT aem_exit_attachment_category_check
                 CHECK (category IN ('MINUTES', 'SUPPORTING'))",
            );
            DB::statement(
                "ALTER TABLE exit_conference_acknowledgements
                 ADD CONSTRAINT aem_exit_ack_status_check
                 CHECK (acknowledgement_status IN ('ACKNOWLEDGED', 'WITH_RESERVATIONS'))",
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_conference_acknowledgements');
        Schema::dropIfExists('exit_conference_attachments');
        Schema::dropIfExists('exit_conference_findings');

        Schema::table('exit_conference_participants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('attendance_recorded_by');
            $table->dropColumn([
                'attendance_notes',
                'attendance_recorded_at',
            ]);
        });

        Schema::table('exit_conferences', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'online_meeting_details',
                'minutes',
                'disagreements',
                'completion_snapshot',
                'waiver_reason',
                'cancellation_reason',
            ]);
        });
    }
};
