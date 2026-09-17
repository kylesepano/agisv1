<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Stores exact private document versions exchanged during finding dialogue. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aems_dialogue_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->foreignId('audit_finding_id')->constrained('audit_findings')->restrictOnDelete();
            $table->foreignId('management_response_id')
                ->nullable()
                ->constrained('management_responses')
                ->restrictOnDelete();
            $table->foreignId('auditor_rejoinder_id')
                ->nullable()
                ->constrained('auditor_rejoinders')
                ->restrictOnDelete();
            $table->string('attachment_code', 120)->unique();
            $table->string('caption')->nullable();
            $table->foreignId('document_version_id')->constrained('document_versions')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(
                ['management_response_id', 'document_version_id'],
                'aem_response_attachment_version_unique',
            );
            $table->unique(
                ['auditor_rejoinder_id', 'document_version_id'],
                'aem_rejoinder_attachment_version_unique',
            );
            $table->index(
                ['audit_finding_id', 'management_response_id', 'auditor_rejoinder_id'],
                'aem_dialogue_attachment_subject_idx',
            );
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE aems_dialogue_attachments
                 ADD CONSTRAINT aem_dialogue_attachment_one_subject_check
                 CHECK (
                    (management_response_id IS NOT NULL AND auditor_rejoinder_id IS NULL)
                    OR
                    (management_response_id IS NULL AND auditor_rejoinder_id IS NOT NULL)
                 )',
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aems_dialogue_attachments');
    }
};
