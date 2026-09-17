<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Complete the AEP and Audit Program records required by the controlled
     * planning and fieldwork-baseline workflows.
     */
    public function up(): void
    {
        Schema::table('audit_engagement_plan_versions', function (Blueprint $table): void {
            $table->text('materiality')->nullable()->after('audit_criteria');
            $table->json('management_coordination')->nullable()->after('resource_requirements');
        });

        Schema::table('audit_programs', function (Blueprint $table): void {
            $table->text('revision_reason')->nullable()->after('supersedes_program_id');
        });

        Schema::table('audit_program_procedures', function (Blueprint $table): void {
            $table->string('working_paper_reference', 120)->nullable()->after('expected_evidence');
            $table->string('reviewer_result', 30)->nullable()->after('status');
            $table->text('reviewer_comments')->nullable()->after('reviewer_result');
            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('reviewer_comments')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('audit_program_procedures', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'working_paper_reference',
                'reviewer_result',
                'reviewer_comments',
                'reviewed_at',
            ]);
        });

        Schema::table('audit_programs', function (Blueprint $table): void {
            $table->dropColumn('revision_reason');
        });

        Schema::table('audit_engagement_plan_versions', function (Blueprint $table): void {
            $table->dropColumn(['materiality', 'management_coordination']);
        });
    }
};
