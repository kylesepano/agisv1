<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the AEMS-6 issue disposition and finding revision contract without
 * rewriting the already-applied foundation tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_issues', function (Blueprint $table): void {
            $table->string('disposition', 50)->nullable()->after('status')->index();
            $table->text('disposition_reason')->nullable()->after('disposition');
            $table->foreignId('disposition_recorded_by')->nullable()->after('disposition_reason')
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('disposition_recorded_at')->nullable()->after('disposition_recorded_by');
            $table->foreignId('merged_into_issue_id')->nullable()->after('disposition_recorded_at')
                ->constrained('audit_issues')->restrictOnDelete();
            $table->string('referred_to', 255)->nullable()->after('merged_into_issue_id');
            $table->text('resolution_details')->nullable()->after('referred_to');
            $table->index(['audit_engagement_id', 'disposition'], 'aem_issue_engagement_disposition_idx');
        });

        Schema::table('audit_findings', function (Blueprint $table): void {
            $table->text('conclusion')->nullable()->after('effect');
            $table->string('significance_classification', 50)->nullable()->after('conclusion');
            $table->string('effect_classification', 50)->nullable()->after('significance_classification');
            $table->string('revision_type', 30)->default('ORIGINAL')->after('revision_number');
            $table->text('revision_reason')->nullable()->after('revision_type');
            $table->json('revision_snapshot')->nullable()->after('revision_reason');
            $table->timestamp('withdrawn_at')->nullable()->after('finalized_snapshot');
            $table->foreignId('withdrawn_by')->nullable()->after('withdrawn_at')
                ->constrained('users')->restrictOnDelete();
            $table->index(['finding_family_uuid', 'revision_number'], 'aem_finding_family_revision_idx');
        });

        Schema::create('audit_finding_fieldwork_record', function (Blueprint $table): void {
            $table->foreignId('audit_finding_id')->constrained('audit_findings')->cascadeOnDelete();
            $table->foreignId('fieldwork_record_id')->constrained('aems_fieldwork_records')->restrictOnDelete();
            $table->foreignId('fieldwork_record_version_id')->constrained('aems_fieldwork_record_versions')->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['audit_finding_id', 'fieldwork_record_version_id'],
                'aem_finding_fieldwork_version_unique',
            );
            $table->index(['fieldwork_record_id', 'fieldwork_record_version_id'], 'aem_finding_fieldwork_lookup_idx');
        });

        // Revisions intentionally retain the finding code. Uniqueness belongs
        // to the current family revision, not to every historical row.
        DB::statement('DROP INDEX IF EXISTS aem_current_finding_unique');
        DB::statement(
            'CREATE UNIQUE INDEX aem_current_finding_family_unique
             ON audit_findings (audit_engagement_id, finding_family_uuid)
             WHERE is_current_revision = true AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_finding_fieldwork_record');

        DB::statement('DROP INDEX IF EXISTS aem_current_finding_family_unique');
        DB::statement(
            'CREATE UNIQUE INDEX aem_current_finding_unique
             ON audit_findings (audit_engagement_id, finding_code)
             WHERE is_current_revision = true AND deleted_at IS NULL',
        );

        Schema::table('audit_findings', function (Blueprint $table): void {
            $table->dropIndex('aem_finding_family_revision_idx');
            $table->dropConstrainedForeignId('withdrawn_by');
            $table->dropColumn([
                'conclusion', 'significance_classification', 'effect_classification',
                'revision_type', 'revision_reason', 'revision_snapshot', 'withdrawn_at',
            ]);
        });

        Schema::table('audit_issues', function (Blueprint $table): void {
            $table->dropIndex('aem_issue_engagement_disposition_idx');
            $table->dropConstrainedForeignId('merged_into_issue_id');
            $table->dropConstrainedForeignId('disposition_recorded_by');
            $table->dropColumn([
                'disposition', 'disposition_reason', 'disposition_recorded_at', 'referred_to',
                'resolution_details',
            ]);
        });
    }
};
