<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_reports', function (Blueprint $table): void {
            $table->timestamp('administratively_closed_at')->nullable();
            $table->foreignId('administratively_closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('administrative_closure_reason')->nullable();
            $table->string('administrative_closure_reference', 160)->nullable();
        });

        Schema::table('audit_report_versions', function (Blueprint $table): void {
            $table->foreignId('source_interim_report_version_id')->nullable()
                ->constrained('audit_report_versions')->restrictOnDelete();
            $table->string('interim_treatment', 40)->nullable();
            $table->json('source_manifest')->nullable();
            $table->string('source_manifest_sha256', 64)->nullable();
            $table->string('reproducibility_key', 120)->nullable()->unique();
        });

        Schema::table('aems_evidence_report_links', function (Blueprint $table): void {
            $table->string('treatment', 40)->nullable()->after('sequence_number');
        });

        Schema::create('aems_report_issue_links', function (Blueprint $table): void {
            $table->foreignId('audit_report_version_id')->constrained('audit_report_versions')->restrictOnDelete();
            $table->foreignId('audit_issue_id')->constrained('audit_issues')->restrictOnDelete();
            $table->unsignedInteger('sequence_number')->default(0);
            $table->string('treatment', 40)->nullable();
            $table->string('link_reason', 500)->nullable();
            $table->foreignId('linked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['audit_report_version_id', 'audit_issue_id'], 'aems_report_issue_link_pk');
        });

        Schema::create('aems_report_working_paper_links', function (Blueprint $table): void {
            $table->foreignId('audit_report_version_id')->constrained('audit_report_versions')->restrictOnDelete();
            $table->foreignId('working_paper_version_id')->constrained('working_paper_versions')->restrictOnDelete();
            $table->unsignedInteger('sequence_number')->default(0);
            $table->string('treatment', 40)->nullable();
            $table->string('link_reason', 500)->nullable();
            $table->foreignId('linked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['audit_report_version_id', 'working_paper_version_id'], 'aems_report_wp_link_pk');
        });

        Schema::create('aems_report_authority_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_report_id')->constrained('audit_reports')->restrictOnDelete();
            $table->foreignId('audit_report_version_id')->constrained('audit_report_versions')->restrictOnDelete();
            $table->string('authority_role', 60);
            $table->string('decision_code', 40);
            $table->text('comment')->nullable();
            $table->string('decision_reference', 160)->nullable();
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->useCurrent();
            $table->timestamps();
            $table->index(['audit_report_version_id', 'authority_role'], 'aems_report_authority_role_idx');
        });

        Schema::create('aems_report_signatories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_report_id')->constrained('audit_reports')->restrictOnDelete();
            $table->foreignId('audit_report_version_id')->constrained('audit_report_versions')->restrictOnDelete();
            $table->string('signatory_role', 60);
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('signatory_name', 255)->nullable();
            $table->string('signature_method', 40);
            $table->string('signature_reference', 160)->nullable();
            $table->timestamp('signed_at')->useCurrent();
            $table->timestamps();
            $table->index(['audit_report_version_id', 'signatory_role'], 'aems_report_signatory_role_idx');
        });

        Schema::create('aems_report_transmittals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_report_id')->constrained('audit_reports')->restrictOnDelete();
            $table->foreignId('audit_report_version_id')->constrained('audit_report_versions')->restrictOnDelete();
            $table->string('transmittal_reference', 160)->unique();
            $table->string('transmittal_method', 40);
            $table->string('delivery_status', 40)->default('PREPARED');
            $table->text('note')->nullable();
            $table->foreignId('sent_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->index(['audit_report_version_id', 'delivery_status'], 'aems_report_transmittal_status_idx');
        });

        Schema::create('aems_report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_report_id')->constrained('audit_reports')->restrictOnDelete();
            $table->foreignId('audit_report_version_id')->constrained('audit_report_versions')->restrictOnDelete();
            $table->string('format', 10);
            $table->foreignId('document_version_id')->nullable()->constrained('document_versions')->restrictOnDelete();
            $table->string('source_manifest_sha256', 64);
            $table->string('file_checksum_sha256', 64);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_name', 255);
            $table->string('storage_path', 500);
            $table->string('scope_hash', 64)->nullable();
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
            $table->index(['audit_report_version_id', 'format'], 'aems_report_export_format_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aems_report_exports');
        Schema::dropIfExists('aems_report_transmittals');
        Schema::dropIfExists('aems_report_signatories');
        Schema::dropIfExists('aems_report_authority_decisions');
        Schema::dropIfExists('aems_report_working_paper_links');
        Schema::dropIfExists('aems_report_issue_links');
        Schema::table('aems_evidence_report_links', fn (Blueprint $table) => $table->dropColumn('treatment'));
        Schema::table('audit_report_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_interim_report_version_id');
            $table->dropColumn(['interim_treatment', 'source_manifest', 'source_manifest_sha256', 'reproducibility_key']);
        });
        Schema::table('audit_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('administratively_closed_by');
            $table->dropColumn(['administratively_closed_at', 'administrative_closure_reason', 'administrative_closure_reference']);
        });
    }
};
