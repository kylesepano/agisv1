<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completes the AEMS issue-to-final-recommendation workflow contract without
 * rewriting the already-applied foundation migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_issues', function (Blueprint $table): void {
            $table->index(
                ['audit_engagement_id', 'status', 'deleted_at'],
                'aem_issue_engagement_status_idx',
            );
        });

        Schema::table('audit_findings', function (Blueprint $table): void {
            $table->text('no_recommendation_reason')->nullable()->after('effect');
            $table->text('non_response_reason')->nullable()->after('communicated_snapshot');
            $table->timestamp('non_response_recorded_at')->nullable()->after('non_response_reason');
            $table->foreignId('non_response_recorded_by')
                ->nullable()
                ->after('non_response_recorded_at')
                ->constrained('users')
                ->restrictOnDelete();
            $table->json('finalized_snapshot')->nullable()->after('finalized_by');
            $table->index(
                ['audit_engagement_id', 'status', 'deleted_at'],
                'aem_finding_engagement_status_idx',
            );
        });
        $this->restoreSqliteFindingCurrentIndex();

        Schema::table('audit_recommendations', function (Blueprint $table): void {
            $table->foreignId('updated_by')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->json('finalized_snapshot')->nullable()->after('finalized_by');
        });

        Schema::table('management_responses', function (Blueprint $table): void {
            $table->foreignId('finalized_by')
                ->nullable()
                ->after('finalized_at')
                ->constrained('users')
                ->restrictOnDelete();
            $table->index(
                ['audit_finding_id', 'status', 'deleted_at'],
                'aem_response_finding_status_idx',
            );
        });
        $this->restoreSqliteResponseCurrentIndex();
    }

    public function down(): void
    {
        Schema::table('management_responses', function (Blueprint $table): void {
            $table->dropIndex('aem_response_finding_status_idx');
            $table->dropConstrainedForeignId('finalized_by');
        });
        $this->restoreSqliteResponseCurrentIndex();

        Schema::table('audit_recommendations', function (Blueprint $table): void {
            $table->dropColumn('finalized_snapshot');
            $table->dropConstrainedForeignId('updated_by');
        });

        Schema::table('audit_findings', function (Blueprint $table): void {
            $table->dropIndex('aem_finding_engagement_status_idx');
            $table->dropColumn([
                'no_recommendation_reason',
                'non_response_reason',
                'non_response_recorded_at',
                'finalized_snapshot',
            ]);
            $table->dropConstrainedForeignId('non_response_recorded_by');
        });
        $this->restoreSqliteFindingCurrentIndex();

        Schema::table('audit_issues', function (Blueprint $table): void {
            $table->dropIndex('aem_issue_engagement_status_idx');
        });
    }

    private function restoreSqliteFindingCurrentIndex(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }
        DB::statement('DROP INDEX IF EXISTS aem_current_finding_unique');
        DB::statement(
            'CREATE UNIQUE INDEX aem_current_finding_unique
             ON audit_findings (audit_engagement_id, finding_code)
             WHERE is_current_revision = true AND deleted_at IS NULL',
        );
    }

    private function restoreSqliteResponseCurrentIndex(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }
        DB::statement('DROP INDEX IF EXISTS aem_current_response_unique');
        DB::statement(
            'CREATE UNIQUE INDEX aem_current_response_unique
             ON management_responses (audit_finding_id, response_code)
             WHERE is_current_revision = true AND deleted_at IS NULL',
        );
    }
};
