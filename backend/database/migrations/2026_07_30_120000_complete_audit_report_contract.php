<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_reports', function (Blueprint $table): void {
            $table->foreignId('document_id')
                ->nullable()
                ->after('confidentiality_level_id')
                ->constrained('documents')
                ->restrictOnDelete();
            $table->foreignId('current_version_id')
                ->nullable()
                ->after('document_id')
                ->constrained('audit_report_versions')
                ->restrictOnDelete();
        });

        Schema::table('audit_report_versions', function (Blueprint $table): void {
            $table->string('pdf_file_name')->nullable()->after('checksum_sha256');
            $table->unsignedBigInteger('file_size')->nullable()->after('pdf_file_name');
            $table->boolean('is_locked')->default(false)->after('file_size');
            $table->timestamp('locked_at')->nullable()->after('is_locked');
            $table->foreignId('locked_by')
                ->nullable()
                ->after('locked_at')
                ->constrained('users')
                ->restrictOnDelete();
        });

        Schema::create('audit_report_review_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_report_id')
                ->constrained('audit_reports')
                ->restrictOnDelete();
            $table->foreignId('audit_report_version_id')
                ->constrained('audit_report_versions')
                ->restrictOnDelete();
            $table->string('review_action', 30);
            $table->text('comment');
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps();
            $table->index(
                ['audit_report_id', 'audit_report_version_id'],
                'aem_report_review_version_idx',
            );
        });

        Schema::create('cms_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_audit_recommendation_id')
                ->unique()
                ->constrained('audit_recommendations')
                ->restrictOnDelete();
            $table->uuid('transfer_key')->unique();
            $table->foreignId('audit_report_version_id')
                ->constrained('audit_report_versions')
                ->restrictOnDelete();
            $table->foreignId('audit_finding_id')
                ->constrained('audit_findings')
                ->restrictOnDelete();
            $table->string('recommendation_code', 100);
            $table->jsonb('source_snapshot');
            $table->foreignId('responsible_office_id')
                ->constrained('offices')
                ->restrictOnDelete();
            $table->date('target_implementation_date')->nullable();
            $table->string('status', 30)->default('OPEN')->index();
            $table->timestamp('transferred_at');
            $table->foreignId('transferred_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE audit_report_review_comments
                 ADD CONSTRAINT aem_report_review_action_check
                 CHECK (review_action IN ('REVIEWED', 'RETURNED', 'APPROVED'))",
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_recommendations');
        Schema::dropIfExists('audit_report_review_comments');

        Schema::table('audit_report_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn([
                'pdf_file_name',
                'file_size',
                'is_locked',
                'locked_at',
            ]);
        });

        Schema::table('audit_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_version_id');
            $table->dropConstrainedForeignId('document_id');
        });
    }
};
